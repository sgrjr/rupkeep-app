<?php
namespace Tests\Feature;
use App\Models\{User,Attachment,Invoice};
use App\Livewire\{CreatePilotCarJob,ShowPilotCarJob,InvoiceEmailForm};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;
class Poc4Test extends TestCase {
  use RefreshDatabase, CreatesMultiTenantFixtures;
  protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
  public function test_customer_lists_all_org_invoices(): void {
    $a = $this->createOrganization('A');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $other = $this->createCustomerForOrganization($a);
    $j = $this->createJobForOrganization($a,$other);
    $inv = $this->createInvoiceForOrganization($a,$other,$j);
    $r = $this->actingAs($cust)->get('/my/invoices')->assertOk();
    $this->assertTrue($r->viewData('invoices')->pluck('id')->contains($inv->id));
  }
  public function test_customer_creates_job(): void {
    $a = $this->createOrganization('A');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $this->actingAs($cust)->get('/my/jobs/create')->assertOk();
  }
  public function test_customer_attachment_download_delete(): void {
    $a = $this->createOrganization('A');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $c2 = $this->createCustomerForOrganization($a); $j=$this->createJobForOrganization($a,$c2);
    $path = tempnam(sys_get_temp_dir(),'att'); file_put_contents($path,'secret');
    $att = Attachment::create(['attachable_id'=>$j->id,'attachable_type'=>get_class($j),'location'=>$path,'file_name'=>'x.txt','organization_id'=>$a->id,'is_public'=>false]);
    $this->actingAs($cust)->get("/attachments/{$att->id}")->assertOk();
    $this->actingAs($cust)->delete("/attachments/{$att->id}");
    $this->assertNull(Attachment::find($att->id));
  }
  public function test_customer_emails_invoice(): void {
    Mail::fake();
    $a = $this->createOrganization('A');
    $cust = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
    $c2 = $this->createCustomerForOrganization($a); $j=$this->createJobForOrganization($a,$c2);
    $inv = $this->createInvoiceForOrganization($a,$c2,$j);
    $this->actingAs($cust);
    Livewire::test(InvoiceEmailForm::class, ['invoice'=>$inv])->set('to','victim@example.com')->call('send');
    Mail::assertSent(\App\Mail\InvoiceEmail::class);
  }
}
