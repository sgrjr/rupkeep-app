<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\TaskList;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-324 — the dashboard "Feedback + Requests" card is scoped to the viewer's
 * own submissions for CUSTOMER viewers, while staff keep the org-wide triage view.
 */
class DashboardFeedbackCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'code' => Task::nextCode(),
            'title' => 'A request',
            'type' => 'feature',
            'priority' => 'low',
            'status' => 'triage',
        ], $overrides));
    }

    public function test_customer_sees_only_their_own_open_request_count(): void
    {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $customerUser = User::factory()->asCustomer($customer)->create();

        // The customer's own submissions: 2 open (counted), 2 closed (excluded).
        $this->makeTask(['submitter_user_id' => $customerUser->id, 'status' => 'triage']);
        $this->makeTask(['submitter_user_id' => $customerUser->id, 'status' => 'in_progress']);
        $this->makeTask(['submitter_user_id' => $customerUser->id, 'status' => 'done']);
        $this->makeTask(['submitter_user_id' => $customerUser->id, 'status' => 'declined']);

        // Other people's org-wide triage tasks must NOT be counted for the customer.
        $other = User::factory()->create();
        $this->makeTask(['submitter_user_id' => $other->id, 'status' => 'triage']);
        $this->makeTask(['submitter_user_id' => null, 'status' => 'triage']);

        Livewire::actingAs($customerUser)
            ->test(Dashboard::class)
            ->assertViewHas('totalFeedback', 2)
            ->assertViewHas('recentFeedback', function ($recent) use ($customerUser) {
                // Recent list is scoped to the customer's own tasks (all statuses, up to 5).
                return $recent->count() === 4
                    && $recent->every(fn ($t) => $t->submitter_user_id === $customerUser->id);
            });
    }

    public function test_manager_still_sees_org_wide_triage_count(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->forOrganization($org)->create();

        $submitter = User::factory()->create();
        // 3 triage tasks (org-wide) — all counted regardless of submitter.
        $this->makeTask(['submitter_user_id' => $submitter->id, 'status' => 'triage']);
        $this->makeTask(['submitter_user_id' => null, 'status' => 'triage']);
        $this->makeTask(['submitter_user_id' => $manager->id, 'status' => 'triage']);
        // Non-triage tasks are not part of the org-wide count.
        $this->makeTask(['submitter_user_id' => $submitter->id, 'status' => 'in_progress']);
        $this->makeTask(['submitter_user_id' => $submitter->id, 'status' => 'done']);

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->assertViewHas('totalFeedback', 3)
            ->assertViewHas('recentFeedback', fn ($recent) => $recent->count() === 3);
    }

    public function test_admin_still_sees_org_wide_triage_count(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($org)->create();

        $submitter = User::factory()->create();
        $this->makeTask(['submitter_user_id' => $submitter->id, 'status' => 'triage']);
        $this->makeTask(['submitter_user_id' => null, 'status' => 'triage']);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewHas('totalFeedback', 2);
    }

    /**
     * TASK-376 - the card's "View Triage" link pointed at ?statusFilter=triage,
     * but TaskList declares #[Url(as: 'status')]. Livewire drops the
     * unrecognised key without complaint, so the link opened an unfiltered list
     * showing every status.
     *
     * Asserted as a round trip rather than a hardcoded string on both sides:
     * whatever URL the dashboard emits is fed to the real TaskList, which must
     * come up filtered to triage.
     */
    public function test_view_triage_link_actually_filters_the_task_list(): void
    {
        $org = Organization::factory()->create();
        $super = User::factory()->superUser()->forOrganization($org)->create();

        $this->makeTask(['status' => 'triage', 'title' => 'Needs triage']);
        $this->makeTask(['status' => 'done', 'title' => 'Already finished']);

        $url = null;

        Livewire::actingAs($super)
            ->test(Dashboard::class)
            ->assertViewHas('cards', function ($cards) use (&$url) {
                foreach ($cards as $card) {
                    foreach ($card->links ?? [] as $link) {
                        if (($link['title'] ?? null) === 'View Triage') {
                            $url = $link['url'];
                        }
                    }
                }

                return $url !== null;
            });

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertNotEmpty($params, "The View Triage link carried no query string: {$url}");

        Livewire::actingAs($super)
            ->withQueryParams($params)
            ->test(TaskList::class)
            ->assertSet('statusFilter', 'triage')
            ->assertSee('Needs triage')
            ->assertDontSee('Already finished');
    }
}
