<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CustomersController extends Controller
{
    use \App\Http\Controllers\Concerns\DeletesCustomers;


    use AuthorizesRequests;

    public function index(Request $request){
        // Always get all customers for metrics and full listing
        if(auth()->user()->isSuper()){
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

        $customer = Customer::with('contacts')->findOrFail($customer_id);

        // Cross-tenant guard (TASK-356): editing is gated by the same ability as
        // the sibling update action — same-org admins/managers (or a super user).
        $this->authorize('update', $customer);

        return view('customers.edit', compact('customer'));
    }

    public function store(CustomerRequest $request){
        // organization_id comes from the actor, never the form (TASK-437).
        $customer = new Customer(array_merge($request->validated(), [
            'organization_id' => auth()->user()->organization_id,
        ]));
        $this->authorize('createCustomer', $customer);
        $customer->save();

        // Create, update and delete all returned to the list without a word
        // (TASK-460).
        return redirect()->route('customers.index')
            ->with('success', __(':name created.', ['name' => $customer->name]));
    }

    public function update(CustomerRequest $request, $customer){
        $customer = Customer::findOrFail($customer);

        $this->authorize('update', $customer);

        // Validated allow-list only: organization_id stays what it is.
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('success', __(':name updated.', ['name' => $customer->name]));
    }

    public function destroy(Request $request, $customer, \App\Services\CustomerArchive $archive){

        // Archive first, then hard delete; see DeletesCustomers (TASK-461).
        return $this->destroyCustomer($request, $customer, $archive);
    }

    public function createContact(Request $request, $customer){

        $customer = Customer::find($customer);
        
        if($customer && $this->authorize('createContact', $customer)){
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'memo' => ['nullable', 'string', 'max:2000'],
                'notification_address' => ['nullable', 'email', 'max:255'],
            ]);

            // The contact belongs to the customer in the URL, never one named
            // in the body (TASK-437).
            CustomerContact::create(array_merge($validated, [
                'customer_id' => $customer->id,
                'organization_id' => $customer->organization_id,
                'is_main_contact' => $request->boolean('is_main_contact'),
                'is_billing_contact' => $request->boolean('is_billing_contact'),
            ]));
        }

        return back();
    }
}
