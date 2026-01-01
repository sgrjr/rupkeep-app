<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PilotCarJob as Job;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Customer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class MyJobsController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request){

        $eagerLoad = ['customer', 'organization', 'logs', 'invoices'];
        
        $user = Auth::user();
        $organizationId = $user->organization_id;
        $showDeleted = $request->boolean('show_deleted', false);
        
        $query = Job::with($eagerLoad)->where('organization_id', $organizationId);
        
        if ($showDeleted) {
            $query->withTrashed();
        }
        
        // Calculate statistics from base query (before pagination)
        $statsQuery = (clone $query);
        $totalJobs = $statsQuery->count();
        $paidJobs = $statsQuery->where('invoice_paid', '>=', 1)->count();
        $unpaidJobs = $totalJobs - $paidJobs;
        $canceledJobs = $statsQuery->whereNotNull('canceled_at')->count();
        
        if($request->has('customer')){
            $jobs = (clone $query)
                ->where('customer_id', $request->get('customer'))
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('scheduled_pickup_at', 'desc')
                ->paginate(15);
            $customer = Customer::where('id', $request->get('customer'))->where('organization_id', $organizationId)->first();
        }else if($request->has('search_field')){
            
            $customer = false;
            if(in_array($request->search_field, ['job_no','load_no','invoice_no','check_no','delivery_address','pickup_address'])){
                $jobs = (clone $query)
                ->where($request->search_field, $request->search_value)
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('scheduled_pickup_at', 'desc')
                ->paginate(15);
            }else if($request->search_field === 'has_customer_name'){
                // Get customer IDs matching the name
                $customerIds = Customer::where('name', 'like', '%'.$request->search_value.'%')
                    ->where('organization_id', $organizationId)
                    ->pluck('id');
                
                // Query jobs for those customers
                $jobs = (clone $query)
                    ->whereIn('customer_id', $customerIds)
                    ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('scheduled_pickup_at', 'desc')
                    ->paginate(15);
            }else{
                $scope = Str::camel($request->search_field);

                $jobs = (clone $query)
                ->$scope()
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('scheduled_pickup_at', 'desc')
                ->paginate(15);
            }
        }else{
            $jobs = $query
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('scheduled_pickup_at', 'desc')
                ->paginate(15);
            $customer = false;
        }
        
        return view('pilot-car-jobs.index', compact('jobs','customer', 'showDeleted', 'totalJobs', 'paidJobs', 'unpaidJobs', 'canceledJobs'));
    }

    public function create(Request $request){
        return view('pilot-car-jobs.create');
    }

    public function edit(Request $request, int $customer_id){
        $job = Job::where('id', $customer_id)->first();
        return view('pilot-car-.edit', compact('job'));
    }

    public function store(Request $request){
        if(!$request->has('organization_id')){
            $request->merge([
                'organization_id' => Auth::user()->organization_id
            ]);
        }

        $job = new Job($request->except('_method'));
        $this->authorize('create', $job);
        $job->save();
        return redirect()->route('my.jobs.edit', ['job'=>$job->id]);
    }

    public function update(Request $request, $job){
        $job = Job::find($job);

        if($job && $this->authorize('update', $job)){
           $job->update($request->except('_method'));
        }

        return redirect()->route('my.jobs.show', ['job'=>$job->id]);
    }

    public function destroy(Request $request, $job){

        $job = Job::find($job);

        if($job && $this->authorize('delete', $job)){
           $job->delete();
           session()->flash('message', __('Job archived successfully.'));
        }

        if($request->has('redirect_to_route') && $request->get('redirect_to_route') != "0" && $request->get('redirect_to_route') != "false" && ($request->get('redirect_to_route') === true || (String)$request->get('redirect_to_route') === "1")){
            return redirect()->route('jobs.index');
        }else{
            return redirect()->route('my.jobs.index');
        }
       
    }

    public function restore(Request $request, $job){
        $job = Job::withTrashed()->find($job);

        if($job && $this->authorize('restore', $job)){
           $job->restore();
           session()->flash('message', __('Job restored successfully.'));
        }

        return back();
    }
}
