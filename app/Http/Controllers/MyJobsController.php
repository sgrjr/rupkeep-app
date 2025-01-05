<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PilotCarJob as Job;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Customer;
use Illuminate\Support\Str;

class MyJobsController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request){

        if($request->has('customer')){
            $jobs = Job::where('organization_id', auth()->user()
                ->organization_id)->where('customer_id', $request->get('customer'))
                ->orderBy('scheduled_pickup_at', 'desc')
                ->get();
            $customer = Customer::where('id', $request->get('customer'))->where('organization_id', auth()->user()->organization_id)->first();
        }else if($request->has('search_field')){
            
            $customer = false;
            if(in_array($request->search_field, ['job_no','load_no','invoice_no','check_no','delivery_address','pickup_address'])){
                $jobs = Job::where('organization_id', auth()->user()->organization_id)
                ->where($request->search_field, $request->search_value)
                ->orderBy('scheduled_pickup_at', 'desc')
                ->get();
            }else if($request->search_field === 'has_customer_name'){
                $jobs = collect([]);
                Customer::with('jobs')->where('name', 'like', '%'.$request->search_value.'%')->where('organization_id', auth()->user()->organization_id)->get()->each(function($c)use(&$jobs){
                    $jobs = $jobs->merge($c->jobs); 
                });
            }else{
                $scope = Str::camel($request->search_field);

                $jobs = Job::where('organization_id', auth()->user()->organization_id)
                ->$scope()
                ->orderBy('scheduled_pickup_at', 'desc')
                ->get();
            }
        }else{
            $jobs = Job::where('organization_id', auth()->user()->organization_id)->orderBy('scheduled_pickup_at', 'desc')->get();
            $customer = false;
        }
        
        return view('pilot-car-jobs.index', compact('jobs','customer'));
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
                'organization_id' => auth()->user()->organization_id
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
        }

        return redirect()->route('my.jobs.index');
    }
}
