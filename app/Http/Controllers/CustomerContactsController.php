<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerContact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class CustomerContactsController extends Controller
{

    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        if(Auth::user()->is_super){
            $contacts = CustomerContact::all();
        }else{
            $contacts = CustomerContact::where('organization_id', Auth::user()->organization_id)->get();
        }
        return view('customers.contact_index', compact('contacts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!$request->has('organization_id')){
            $request->merge([
                'organization_id' => Auth::user()->organization_id
            ]);
        }
        $data = $request->except('_method');
        // Convert checkbox values to booleans
        $data['is_main_contact'] = $request->has('is_main_contact') && $request->input('is_main_contact') == '1';
        $data['is_billing_contact'] = $request->has('is_billing_contact') && $request->input('is_billing_contact') == '1';
        
        $customer_contact = new CustomerContact($data);
        $this->authorize('create', $customer_contact);

        $customer_contact->save();
        return back()->with('success', __('Contact created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        dd($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        dd($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $customer_id, Int $id)
    {
        $customer_contact = CustomerContact::where('id',$id)->where('customer_id', $customer_id)->first();
        
        if (!$customer_contact) {
            abort(404);
        }
        
        $this->authorize('update', $customer_contact);
        
        if($request->has('delete') && $request->get('delete') === 'on'){
            $customer_contact->delete();
            return back()->with('success', __('Contact deleted.'));
        }
        
        $data = $request->only(['name', 'phone', 'email', 'memo', 'notification_address']);
        // Convert checkbox values to booleans
        $data['is_main_contact'] = $request->has('is_main_contact') && $request->input('is_main_contact') == '1';
        $data['is_billing_contact'] = $request->has('is_billing_contact') && $request->input('is_billing_contact') == '1';
        
        $customer_contact->update($data);
        
        return back()->with('success', __('Contact updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
