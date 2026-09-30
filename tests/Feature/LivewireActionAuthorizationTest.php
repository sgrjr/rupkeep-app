<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\EditPilotCarJob;
use App\Livewire\ServerManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-442. Two things:
 *
 * 1. A structural guard. A Livewire action is its own callable endpoint, so
 *    an authorize() in mount() protects nothing a public method does later.
 *    This walks every public method on every App\Livewire component and
 *    requires an authorization token in its body, except for the lifecycle
 *    hooks and a short, explicit list of read-only / UI-only methods. A new
 *    unguarded method fails here until it is either guarded or added to that
 *    list on purpose.
 *
 * 2. Behavioural checks for the guards this task added.
 */
class LivewireActionAuthorizationTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    /** Methods that touch nothing sensitive: modal toggles, computed reads, paging. */
    private const ALLOWED_UNGUARDED = [
        'AdminStickyNote::cancel',
        'AnnualVehicleReportModal::openModal',
        'AnnualVehicleReportModal::closeModal',
        'CancelJob::closeModal',
        'CancelJob::getCancellationReasons',
        'CancelJob::getCancellationTypeOptions',
        'DeleteConfirmationButton::confirmDelete',
        'EditUserLog::openAllSections',
        'EditUserLog::closeAllSections',
        'EditUserLog::calculatedBillableMiles',
        'EditUserLog::totalMilesFromForm',
        'EditUserLog::approachMilesFromForm',
        'EditUserLog::deadHeadCeiling',
        'FeedbackForm::openModal',
        'FeedbackForm::closeModal',
        'FeedbackForm::submit',            // any signed-in user may file feedback (TaskPolicy::create)
        'InvoiceEmailForm::openModal',
        'InvoiceEmailForm::closeModal',
        'InvoicePaymentForm::openModal',
        'InvoicePaymentForm::closeModal',
        'ManagePricing::loadPricingData',  // read-only; mount() picks the organization
        'OnboardingWizard::nextStep',
        'OnboardingWizard::previousStep',
        'RestoreButton::confirmRestore',
        'ShowPilotCarJob::refreshJob',
        'TaskCreate::closeModal',
        'TaskList::clearFilters',
        'UserProfile::sessions',
        'UserProfile::clearNotificationTestStatus',
    ];

    private const LIFECYCLE = [
        'mount', 'render', 'boot', 'booted', 'hydrate', 'dehydrate', 'rules', 'messages',
        'validationAttributes', 'paginationView', 'placeholder', 'exception', 'rendering', 'rendered',
    ];

    private const TOKENS = [
        'authorize(', 'abort_unless(', 'abort_if(', 'abort(', '->can(', '->cannot(', 'Gate::', 'isSuper()', 'authorizeReset(',
    ];

    public function test_every_public_livewire_action_authorizes_or_is_listed_as_harmless(): void
    {
        $unguarded = [];

        foreach (glob(app_path('Livewire/*.php')) as $file) {
            $src = file_get_contents($file);
            if (! preg_match('/^namespace\s+([^;]+);/m', $src, $ns)) {
                continue;
            }
            preg_match_all('/^class\s+(\w+)\s+extends\s+(?:\\\\?Livewire\\\\)?Component\b/m', $src, $classes);
            $lines = file($file);

            foreach ($classes[1] as $short) {
                $ref = new ReflectionClass($ns[1].'\\'.$short);

                foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                    // Only methods written in this file: trait and framework methods are not ours.
                    // (realpath: glob() and Reflection disagree on separators on Windows.)
                    if (realpath($m->getFileName()) !== realpath($file)) {
                        continue;
                    }
                    $name = $m->getName();
                    if (in_array($name, self::LIFECYCLE, true)
                        || str_starts_with($name, 'updated') || str_starts_with($name, 'updating')
                        || preg_match('/^get\w+Property$/', $name)) {
                        continue;
                    }

                    $body = implode('', array_slice($lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
                    foreach (self::TOKENS as $token) {
                        if (str_contains($body, $token)) {
                            continue 2;
                        }
                    }
                    $unguarded[] = $short.'::'.$name;
                }
            }
        }

        sort($unguarded);
        $allowed = self::ALLOWED_UNGUARDED;
        sort($allowed);

        $this->assertSame(
            [],
            array_values(array_diff($unguarded, $allowed)),
            "Public Livewire methods with no authorization. Guard them, or add them to ALLOWED_UNGUARDED if they are genuinely harmless:\n  "
            .implode("\n  ", array_diff($unguarded, $allowed))
        );
        $this->assertSame(
            [],
            array_values(array_diff($allowed, $unguarded)),
            "Listed as unguarded but now guarded or gone; prune ALLOWED_UNGUARDED:\n  ".implode("\n  ", array_diff($allowed, $unguarded))
        );
    }

    public function test_server_management_actions_are_super_only_even_after_mount(): void
    {
        $org = $this->createOrganization('A');
        $admin = $this->createUserForOrganization($org, User::ROLE_ADMIN);
        $super = User::factory()->superUser()->create(['organization_id' => $org->id]);

        Livewire::actingAs($admin)->test(ServerManagement::class)->assertForbidden();

        // One instance per action: a 403 leaves a test instance without a snapshot.
        $command = Livewire::actingAs($super)->test(ServerManagement::class)->assertOk();
        $queue = Livewire::actingAs($super)->test(ServerManagement::class)->assertOk();
        $super->forceFill(['is_super' => false])->save();

        $command->call('executeCommand', 'artisan_env_check')->assertForbidden();
        $queue->call('loadQueueJobs')->assertForbidden();
    }

    public function test_job_editor_save_and_dashboard_import_check_on_every_call(): void
    {
        $org = $this->createOrganization('A');
        $manager = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_MANAGER);
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $job = $this->createJobForOrganization($org, $this->createCustomerForOrganization($org), ['load_no' => 'BEFORE']);

        $editor = Livewire::actingAs($manager)->test(EditPilotCarJob::class, ['job' => $job->id])->assertOk();
        $manager->forceFill(['organization_role' => User::ROLE_EMPLOYEE_STANDARD])->save();
        $editor->set('form.load_no', 'AFTER')->call('saveJob')->assertForbidden();
        $this->assertSame('BEFORE', $job->fresh()->load_no);

        Livewire::actingAs($driver)->test(Dashboard::class)->call('uploadFile')->assertForbidden();
        Livewire::actingAs($driver)->test(Dashboard::class)->call('confirmImport')->assertForbidden();
    }
}
