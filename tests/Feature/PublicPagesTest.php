<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-475. Terms and Privacy were Jetstream's "Edit this file" stubs with
 * no route; the "Public Roadmap" required sign-in and, for a user with no
 * organization, showed every organization's public tasks; the nav's
 * "View All Feedback" pointed at the pre-integration user_events rows.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_terms_and_privacy_are_real_pages_that_name_the_operator(): void
    {
        Organization::factory()->create(['name' => 'Casco Bay Pilot Car', 'email' => 'office@cascobay.example', 'telephone' => '207-555-0100']);

        $this->get(route('terms.show'))
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Casco Bay Pilot Car')
            ->assertSee('office@cascobay.example')
            ->assertDontSee('Edit this file');

        $this->get(route('policy.show'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('What we collect')
            ->assertSee('207-555-0100')
            ->assertDontSee('Edit this file');
    }

    public function test_a_visitor_can_read_the_roadmap_and_sees_one_organizations_public_tasks(): void
    {
        $ours = Organization::factory()->create(['name' => 'Casco Bay Pilot Car']);
        $theirs = Organization::factory()->create(['name' => 'Someone Else']);

        Task::create(['code' => 'TASK-910', 'title' => 'Our public item', 'type' => 'feature', 'priority' => 'medium', 'status' => 'open', 'is_public' => true, 'organization_id' => $ours->id]);
        Task::create(['code' => 'TASK-911', 'title' => 'Their public item', 'type' => 'feature', 'priority' => 'medium', 'status' => 'open', 'is_public' => true, 'organization_id' => $theirs->id]);
        Task::create(['code' => 'TASK-912', 'title' => 'Our private item', 'type' => 'feature', 'priority' => 'medium', 'status' => 'open', 'is_public' => false, 'organization_id' => $ours->id]);

        $this->get(route('documentation.roadmap'))
            ->assertOk()
            ->assertSee('Our public item')
            ->assertDontSee('Their public item')
            ->assertDontSee('Our private item');

        $this->get(route('documentation.index'))->assertOk()->assertSee('Documentation');
    }

    public function test_a_signed_in_user_still_gets_the_app_layout_on_the_roadmap(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);

        $this->actingAs($manager)->get(route('documentation.roadmap'))
            ->assertOk()
            ->assertSee('Public Roadmap')
            ->assertSee(route('dashboard'), false);
    }

    public function test_the_staff_guide_still_needs_sign_in(): void
    {
        $this->get(route('documentation.show', 'onboarding'))->assertRedirect(route('login'));
    }

    public function test_view_all_feedback_points_at_the_dispatch_triage_queue(): void
    {
        $organization = Organization::factory()->create();
        $super = User::factory()->superUser()->create(['organization_id' => $organization->id]);

        $html = $this->actingAs($super)->get(route('dashboard'))->assertOk()->getContent();

        // Blade escapes the & in the query string.
        $this->assertStringContainsString(e(route('tasks.index', ['status' => 'triage', 'label' => 'source:feedback'])), $html);
        $this->assertStringNotContainsString(e(route('user-events.index', ['type' => 'feedback'])), $html);
    }

    public function test_the_staff_guide_no_longer_promises_sms_for_the_future(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);

        $this->actingAs($manager)->get(route('documentation.show', 'onboarding'))
            ->assertOk()
            ->assertDontSee('SMS integration in the future')
            ->assertDontSee('PDF library integration can be added later');
    }
}
