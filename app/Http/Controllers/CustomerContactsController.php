<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerContact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CustomerContactsController extends Controller
{

    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        if(auth()->user()->is_super){
            $contacts = CustomerContact::all();
        }else{
            $contacts = CustomerContact::where('organization_id', auth()->user()->organization_id)->get();
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
                'organization_id' => auth()->user()->organization_id
            ]);
        }
        $customer_contact = new CustomerContact($request->except('_method'));
        $this->authorize('create', $customer_contact);
        $customer_contact->save();
        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
