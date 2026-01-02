<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CustomersController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request){
        // Always get all customers for metrics and full listing
        if(auth()->user()->is_super){
            $allCustomers = Customer::with(['contacts', 'jobs'])->get();
        }else{
            $allCustomers = Customer::with(['contacts', 'jobs'])->where('organization_id', auth()->user()->organization_id)->get();
        }
        
        // Get filtered customers if filter is applied
        $filteredCustomers = collect();
        $hasFilter = false;
        $filterTitle = '';
        
        if ($request->has('has_account_credit') && $request->boolean('has_account_credit')) {
            $filteredCustomers = $allCustomers->filter(fn ($customer) => $customer->account_credit > 0);
            $hasFilter = true;
            $filterTitle = __('Has Account Credit');
        }
        
        // Calculate metrics from all customers
        $totalJobs = $allCustomers->sum(fn ($customer) => $customer->jobs->count());
        $customerCount = $allCustomers->count();
        $averageJobsPerCustomer = $customerCount > 0 ? round($totalJobs / $customerCount, 1) : 0;
        $customersWithCredit = $allCustomers->filter(fn ($customer) => $customer->account_credit > 0)->count();
        
        return view('customers.index', compact('allCustomers', 'filteredCustomers', 'hasFilter', 'filterTitle', 'averageJobsPerCustomer', 'customersWithCredit'));
    }

    public function create(Request $request){
        return view('customers.create');
    }

    public function edit(Request $request, int $customer_id){

        $customer = Customer::with('contacts')->where('id', $customer_id)->first();
        return view('customers.edit', compact('customer'));
    }

    public function store(Request $request){
        if(!$request->has('organization_id')){
            $request->merge([
                'organization_id' => auth()->user()->organization_id
            ]);
        }
        $customer = new Customer($request->except('_method'));
        $this->authorize('createCustomer', $customer);
        $customer->save();
        return redirect()->route('customers.index');
    }

    public function update(Request $request, $customer){

        $customer = Customer::find($customer);

        if($customer && $this->authorize('update', $customer)){
           $customer->update($request->except('_method'));
        }

        return redirect()->route('customers.index');
    }

    public function destroy(Request $request, $customer){

        $customer = Customer::find($customer);

        if($customer && $this->authorize('delete', $customer)){
           $customer->delete();
        }

        return redirect()->route('customers.index');
    }

    public function createContact(Request $request, $customer){

        $customer = Customer::find($customer);
        
        if($customer && $this->authorize('createContact', $customer)){
            CustomerContact::create($request->except('_method'));
        }

        return back();
    }
}
