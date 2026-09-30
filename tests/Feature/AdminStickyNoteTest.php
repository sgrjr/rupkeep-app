<?php

namespace Tests\Feature;

use App\Livewire\AdminStickyNote;
use App\Models\AdminNote;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "where are the SSH credentials" sticky note in Super Admin Tools.
 * Shared by every super user; invisible and un-editable to everyone else.
 */
class AdminStickyNoteTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
    }

    private function super(): User
    {
        return User::factory()->superUser()->create(['organization_id' => $this->organization->id]);
    }

    public function test_super_user_can_write_the_note_and_another_super_user_reads_it(): void
    {
        $author = $this->super();

        Livewire::actingAs($author)
            ->test(AdminStickyNote::class)
            ->assertSee(__('Nothing written yet'))
            ->call('edit')
            ->set('body', 'Bitwarden, Servers folder, entry "pilotcar.io ssh"')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', false)
            ->assertSee('pilotcar.io ssh');

        $this->assertDatabaseHas('admin_notes', [
            'key' => AdminNote::SSH_CREDENTIALS,
            'body' => 'Bitwarden, Servers folder, entry "pilotcar.io ssh"',
            'updated_by_user_id' => $author->id,
        ]);

        Livewire::actingAs($this->super())
            ->test(AdminStickyNote::class)
            ->assertSee('pilotcar.io ssh')
            ->assertSee($author->name);
    }

    public function test_note_is_rendered_inside_the_dashboard_super_admin_tools(): void
    {
        AdminNote::create(['key' => AdminNote::SSH_CREDENTIALS, 'body' => 'Look in the vault under Servers']);

        $this->actingAs($this->super())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Super Admin Tools'))
            ->assertSee('Look in the vault under Servers');
    }

    public function test_non_super_admin_never_sees_the_note(): void
    {
        AdminNote::create(['key' => AdminNote::SSH_CREDENTIALS, 'body' => 'Look in the vault under Servers']);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Look in the vault under Servers');

        // The component is its own endpoint; the blade wrapper is not the guard.
        Livewire::actingAs($admin)
            ->test(AdminStickyNote::class)
            ->assertForbidden();
    }

    public function test_body_is_length_limited(): void
    {
        Livewire::actingAs($this->super())
            ->test(AdminStickyNote::class)
            ->call('edit')
            ->set('body', str_repeat('x', 2001))
            ->call('save')
            ->assertHasErrors(['body']);

        $this->assertDatabaseCount('admin_notes', 0);
    }

    public function test_cancel_discards_unsaved_edits(): void
    {
        AdminNote::create(['key' => AdminNote::SSH_CREDENTIALS, 'body' => 'original']);

        Livewire::actingAs($this->super())
            ->test(AdminStickyNote::class)
            ->call('edit')
            ->set('body', 'scribble')
            ->call('cancel')
            ->assertSet('editing', false)
            ->assertSet('body', 'original');
    }
}
