<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-462, the view sweep: Dispatch missing from the mobile menu, a
 * leftover `true ||` forcing the dark theme on every guest page, the
 * customer's comment form printing under their invoice, six console.log
 * lines on every page load, blank-target links without rel, images without
 * alt, and Livewire rows in loops without keys.
 */
class MobilePolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_mobile_menu_carries_the_dispatch_links_too(): void
    {
        $organization = Organization::factory()->create();
        $super = User::factory()->superUser()->create(['organization_id' => $organization->id]);

        $html = $this->actingAs($super)->get(route('dashboard'))->assertOk()->getContent();

        // Once in the desktop dropdown, once in the responsive menu.
        $this->assertGreaterThanOrEqual(2, substr_count($html, route('tasks.board')), 'Board link is in both menus');
        $this->assertGreaterThanOrEqual(2, substr_count($html, route('tasks.index')));
        $this->assertGreaterThanOrEqual(2, substr_count($html, route('documentation.roadmap')));
        $this->assertGreaterThanOrEqual(2, substr_count($html, route('admin.feedback.index')));
    }

    public function test_a_driver_sees_no_dispatch_links_in_either_menu(): void
    {
        $organization = Organization::factory()->create();
        $driver = User::factory()->standard()->create(['organization_id' => $organization->id]);

        $this->actingAs($driver)->get(route('dashboard'))->assertOk()->assertDontSee(route('tasks.board'), false);
    }

    public function test_the_login_page_is_not_forced_dark(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="welcome default-theme"', false)
            ->assertDontSee('welcome dark-theme', false);
    }

    public function test_the_customer_invoice_hides_the_comment_form_and_its_own_company_name_when_printing(): void
    {
        $organization = Organization::factory()->create(['name' => 'Northwind Escorts']);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->asCustomer($customer)->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        $html = $this->actingAs($user)->get(route('customer.invoices.show', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('invoice-comments-section no-print', $html);
        $this->assertStringContainsString('invoice-support-message no-print', $html);
        $this->assertStringContainsString('Northwind Escorts coordinator', $html);
        $this->assertStringNotContainsString('Casco Bay Pilot Car coordinator', $html);
    }

    public function test_the_push_component_no_longer_logs_on_every_page_load(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);

        $html = $this->actingAs($manager)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString("console.log('started')", $html);
        $this->assertStringNotContainsString('Service Worker is ready', $html);
        $this->assertStringNotContainsString('Registration initiated', $html);
    }

    public function test_blank_target_links_carry_rel_noopener(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'organization_id' => $organization->id]);

        $html = $this->actingAs($manager)->get(route('my.invoices.edit', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertDoesNotMatchRegularExpression('/target="_blank"(?![^>]*\brel=)/', $html, 'every _blank link names rel');
    }

    public function test_the_swept_views_have_no_image_without_alt_and_no_keyless_livewire_row(): void
    {
        $views = resource_path('views');

        foreach ([
            'cbpc.blade.php',
            'pricing.blade.php',
            'welcome.blade.php',
            'components/application-logo.blade.php',
            'components/authentication-card-logo.blade.php',
            'components/public-layout.blade.php',
            'livewire/primary-navigation-menu.blade.php',
        ] as $file) {
            $source = file_get_contents("{$views}/{$file}");
            // `->` inside a Blade echo is not the end of the tag.
            preg_match_all('/<img\b(?:->|[^>])*>/i', $source, $tags);

            foreach ($tags[0] as $tag) {
                $this->assertMatchesRegularExpression('/\balt=/', $tag, "{$file}: {$tag}");
            }
        }

        $this->assertStringContainsString(
            ':key="\'annual-vehicle-report-\'.$vehicle->id"',
            file_get_contents("{$views}/vehicles/index.blade.php"),
            'one modal per vehicle row needs its own key'
        );

        foreach ([
            'livewire/onboarding-wizard.blade.php' => ['onboarding-user-', 'onboarding-vehicle-', 'onboarding-email-user-'],
            'livewire/show-pilot-car-job.blade.php' => ['contact-row-', 'trashed-log-'],
            'livewire/invoice-comments.blade.php' => ['invoice-comment-'],
            'livewire/log-extra-charges.blade.php' => ['extra-charge-'],
            'livewire/task-create.blade.php' => ['label-option-'],
            'livewire/task-show.blade.php' => ['label-option-'],
            'livewire/task-thread.blade.php' => ['thread-comment-'],
        ] as $file => $keys) {
            $source = file_get_contents("{$views}/{$file}");

            foreach ($keys as $key) {
                $this->assertStringContainsString('wire:key="'.$key, $source, "{$file} keys its {$key} rows");
            }
        }
    }
}
