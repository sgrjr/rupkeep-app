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

        if($request->has('customer')){
            $jobs = Job::where('organization_id', auth()->user()
                ->organization_id)->where('customer_id', $request->get('customer'))
                ->orderBy('scheduled_pickup_at', 'desc')
                ->get();
            $customer = Customer::where('id', $request->get('customer'))->first();
        }else if($request->has('search_field')){
            
            $customer = false;
            if(in_array($request->search_field, ['job_no','load_no','invoice_no','check_no','delivery_address','pickup_address'])){
                $jobs = Job::where($request->search_field, $request->search_value)
                ->orderBy('scheduled_pickup_at', 'desc')
                ->get();
            }else if($request->search_field === 'has_customer_name'){
                $jobs = collect([]);
                Customer::with('jobs')->where('name', 'like', '%'.$request->search_value.'%')->where('organization_id', auth()->user()->organization_id)->get()->each(function($c)use(&$jobs){
                    $jobs = $jobs->merge($c->jobs); 
                });
            }else{
                $scope = Str::camel($request->search_field);

                $jobs = Job::scope()
                ->orderBy('scheduled_pickup_at', 'desc')
                ->get();
            }
        }else{
            $jobs = Job::orderBy('scheduled_pickup_at', 'desc')->get();
            $customer = false;
        }

        $redirect_to_root = true;
        
        return view('pilot-car-jobs.index', compact('jobs','customer', 'redirect_to_root'));
    }
}
