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
        if(auth()->user()->is_super){
            $customers = Customer::with('contacts')->get();
        }else{
            $customers = Customer::with('contacts')->where('organization_id', auth()->user()->organization_id)->get();
        }
        return view('customers.index', compact('customers'));
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
