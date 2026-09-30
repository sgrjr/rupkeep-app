<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;
class Poc3Test extends TestCase {
  use RefreshDatabase, CreatesMultiTenantFixtures;
  public function test_x(): void {
    $a = $this->createOrganization('A'); $b = $this->createOrganization('B');
    $adminA = $this->createUserForOrganization($a, User::ROLE_ADMIN);
    $cB = $this->createCustomerForOrganization($b); $jB = $this->createJobForOrganization($b,$cB);
    $inv = $this->createInvoiceForOrganization($b,$cB,$jB);
    fwrite(STDERR, "role=".$adminA->organization_role." can update: ".var_export($adminA->can('update',$inv),true)." view:".var_export($adminA->can('view',$inv),true)." viewAny:".var_export($adminA->can('viewAny',\App\Models\Invoice::class),true)."\n");
    $this->assertTrue(true);
  }
}
