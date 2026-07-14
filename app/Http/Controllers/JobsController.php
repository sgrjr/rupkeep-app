<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PilotCarJob as Job;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Customer;
use Illuminate\Support\Str;

class JobsController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request){

        $this->authorize('viewAny', Job::class);

        $eagerLoad = ['customer', 'organization', 'logs', 'singleInvoices', 'summaryInvoices'];
        $userOrgId = auth()->user()->organization_id;
        $showDeleted = $request->boolean('show_deleted', false);

        // Build the base (filtered, unpaginated) query. Stats and the paginated
        // result set are both derived from this so they always agree. This view
        // is shared with MyJobsController@index, which requires the stat vars and
        // a paginator — returning a bare collection here broke /jobs entirely
        // (Undefined variable $totalJobs, then Collection::hasPages) — TASK-336.
        $query = Job::with($eagerLoad);

        if($request->has('organization_id')){
            $query->where('organization_id', $request->get('organization_id'));
            $customer = false;
        }else if($request->has('customer')){
            $query->where('organization_id', $userOrgId)
                ->where('customer_id', $request->get('customer'));
            $customer = Customer::where('id', $request->get('customer'))->first();
        }else if($request->has('search_field')){
            $customer = false;
            if(in_array($request->search_field, ['job_no','load_no','invoice_no','check_no','delivery_address','pickup_address'])){
                $query->where($request->search_field, $request->search_value);
            }else if($request->search_field === 'has_customer_name'){
                $customerIds = Customer::where('name', 'like', '%'.$request->search_value.'%')
                    ->where('organization_id', $userOrgId)
                    ->pluck('id');
                $query->whereIn('customer_id', $customerIds);
            }else{
                $scope = Str::camel($request->search_field);
                $query->$scope();
            }
        }else{
            $customer = false;
        }

        if($showDeleted){
            $query->withTrashed();
        }

        // Independent counts, each from a fresh clone so where() clauses don't
        // accumulate across counts (see TASK-325).
        $totalJobs = (clone $query)->count();
        $paidJobs = (clone $query)->where('invoice_paid', '>=', 1)->count();
        $unpaidJobs = $totalJobs - $paidJobs;
        $canceledJobs = (clone $query)->whereNotNull('canceled_at')->count();
        $missingJobNo = (clone $query)->whereNull('job_no')->count();

        $jobs = $query
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('scheduled_pickup_at', 'desc')
            ->paginate(15);

        $redirect_to_root = true;

        return view('pilot-car-jobs.index', compact('jobs','customer', 'redirect_to_root', 'showDeleted', 'totalJobs', 'paidJobs', 'unpaidJobs', 'canceledJobs', 'missingJobNo'));
    }
}
