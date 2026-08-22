<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsInvoiceIndex;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Every organization's invoices, for super users -- the counterpart to /jobs,
 * and the cross-organization twin of /my/invoices.
 *
 * One difference from /jobs is intentional. JobsController::index authorizes on
 * viewAny, which any employee passes, and then applies an organization filter
 * only if the request happens to carry one -- so a bare GET /jobs lists every
 * organization's work to anyone signed in. This screen authorizes on a distinct
 * viewAcrossOrganizations ability that only super users hold, because that is
 * what the screen actually does.
 */
class InvoicesController extends Controller
{
    use AuthorizesRequests;
    use BuildsInvoiceIndex;

    public function index(Request $request)
    {
        $this->authorize('viewAcrossOrganizations', Invoice::class);

        $filters = $this->invoiceIndexFilters($request);

        $query = $this->invoiceIndexQuery($filters)->with('organization');

        // Narrowing to one organization is a filter here, not the security
        // boundary. The boundary is the authorize() above.
        if ($organizationId = ($filters['organization_id'] ?? null)) {
            $query->where('organization_id', $organizationId);
        }

        return view('invoices.index', array_merge(
            $this->invoiceIndexPayload($query, $filters, crossOrganization: true),
            ['organizations' => Organization::orderBy('name')->get()]
        ));
    }
}
