<?php
namespace Tests\Feature;
use App\Models\{User,Organization,Attachment,Invoice,PilotCarJob,Customer};
use App\Livewire\{OrganizationShow,ShowPilotCarJob,UserProfile,PrimaryNavigationMenu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;
class Poc2Test extends TestCase {
  use RefreshDatabase, CreatesMultiTenantFixtures;
  protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
  public function test_customer_reads_staff_pages(): void {
    $a = $this->createOrganization('A');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $other = $this->createCustomerForOrganization($a);
    $this->createCustomerContact($other, ['name'=>'SECRETCONTACT']);
    $job = $this->createJobForOrganization($a, $other);
    $this->actingAs($cust);
    $this->get("/my/jobs/{$job->id}")->assertOk();
    $this->get("/my/customers/{$other->id}")->assertOk();
    $this->get("/customers/{$other->id}/contacts")->assertOk()->assertSee('SECRETCONTACT');
  }
  public function test_customer_wipes_org_and_creates_admin(): void {
    $a = $this->createOrganization('A');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $job = $this->createJobForOrganization($a);
    $this->actingAs($cust);
    Livewire::test(OrganizationShow::class, ['organization'=>$a->id])
      ->set('form.name','Evil')->set('form.email','evil@x.com')->set('form.password','secret1')->set('form.password_confirmation','secret1')->set('form.role','admin')
      ->call('createUser')->call('deleteJobs');
    $this->assertDatabaseHas('users',['email'=>'evil@x.com','organization_role'=>'admin']);
    $this->assertNull(PilotCarJob::withTrashed()->find($job->id));
  }
  public function test_org_admin_cross_tenant_invoice(): void {
    $a = $this->createOrganization('A'); $b = $this->createOrganization('B');
    $adminA = $this->createUserForOrganization($a, User::ROLE_ADMIN);
    $cB = $this->createCustomerForOrganization($b); $jB = $this->createJobForOrganization($b,$cB);
    $inv = $this->createInvoiceForOrganization($b,$cB,$jB);
    $r=$this->actingAs($adminA)->get("/my/invoices/{$inv->id}/edit"); fwrite(STDERR, "STATUS ".$r->getStatusCode()." ".substr(strip_tags($r->getContent()),0,300)."
");
    $this->actingAs($adminA)->put("/my/invoices/{$inv->id}", ['delete'=>1,'delete_mode'=>'delete_children']);
    $this->assertNull(Invoice::withTrashed()->find($inv->id), 'deleted cross tenant');
  }
  public function test_driver_generates_invoice_and_assigns_foreign_driver(): void {
    $a = $this->createOrganization('A'); $b = $this->createOrganization('B');
    $driverA = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_STANDARD);
    $driverB = $this->createUserForOrganization($b, User::ROLE_EMPLOYEE_STANDARD);
    $c = $this->createCustomerForOrganization($a); $j = $this->createJobForOrganization($a,$c);
    $this->actingAs($driverA);
    Livewire::test(ShowPilotCarJob::class, ['job'=>$j->id])->call('generateInvoice');
    $this->assertEquals(1, Invoice::where('organization_id',$a->id)->count());
  }
  public function test_customer_bulk_invoice_foreign_job(): void {
    $a = $this->createOrganization('A'); $b = $this->createOrganization('B');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $cB = $this->createCustomerForOrganization($b); $jB = $this->createJobForOrganization($b,$cB);
    $this->actingAs($cust)->post('/my/invoices/create',['invoice_this'=>[$jB->id]]);
    $this->assertEquals(1, Invoice::where('organization_id',$b->id)->count());
  }
  public function test_nav_leaks_org_names(): void {
    $a = $this->createOrganization('A'); $b = $this->createOrganization('SecretOrgB');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $this->actingAs($cust);
    $t = Livewire::test(PrimaryNavigationMenu::class);
    $this->assertTrue($t->get('organizations')->pluck('name')->contains('SecretOrgB'));
  }
  public function test_customer_null_customer_id_sees_orphan_invoices(): void {
    $a = $this->createOrganization('A'); $b = $this->createOrganization('B');
    $u = User::factory()->forOrganization($a)->create(['organization_role'=>'customer','customer_id'=>null]);
    $cB = $this->createCustomerForOrganization($b);
    $inv = $this->createInvoiceForOrganization($b,$cB,null,['customer_id'=>null]);
    $this->actingAs($u)->get('/portal/invoices/'.$inv->id)->assertOk();
  }
}
