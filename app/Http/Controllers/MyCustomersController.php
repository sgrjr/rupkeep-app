<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CustomerRequest;
use App\Actions\InviteCustomerUser;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyCustomersController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request){
        // Always get all customers for metrics and full listing
        $allCustomers = Customer::with(['contacts', 'jobs'])->where('organization_id', auth()->user()->organization_id)->get();
        
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

    public function show(Request $request, int $customer_id){
        $customer = Customer::with([
            'contacts',
            'jobs.singleInvoices.children',
            'jobs.summaryInvoices.children',
        ])->findOrFail($customer_id);

        // Cross-tenant guard (TASK-356): a customer belongs to exactly one
        // organization. Without this check any authenticated staff member could
        // read another org's customer, contacts and invoices. Mirrors the
        // sibling update/destroy actions, which already authorize.
        $this->authorize('view', $customer);

        // The invoices() method on PilotCarJob will merge singleInvoices and summaryInvoices
        // when accessed, so we don't need additional merging logic
        
        // Prepare transaction register data
        $transactions = collect();
        
        // Account credit is shown as a display line but represents current available credit
        // It will be applied to the final balance calculation
        $accountCredit = $customer->account_credit ?? 0;
        
        // Get all invoices for this customer (only parent invoices to avoid duplicates)
        $invoices = \App\Models\Invoice::where('customer_id', $customer_id)
            ->where('organization_id', auth()->user()->organization_id)
            ->whereNull('parent_invoice_id') // Only parent invoices
            ->notVoid() // a void invoice is not owed (TASK-480)
            ->orderBy('created_at')
            ->get();
        
        foreach ($invoices as $invoice) {
            $values = is_array($invoice->values) ? $invoice->values : [];
            $total = $values['total'] ?? 0;
            
            // Add invoice as debit transaction (charges increase what customer owes)
            if ($total > 0) {
                $transactions->push([
                    'date' => $invoice->created_at,
                    'type' => 'debit',
                    'amount' => $total,
                    'description' => $invoice->isSummary() 
                        ? __('Summary Invoice') 
                        : __('Invoice'),
                    'reference' => $invoice->invoice_number,
                    'reference_url' => route('my.invoices.edit', ['invoice' => $invoice->id]),
                    'sort_order' => 1,
                ]);
            }
            
            // Add payments as credit transactions (payments reduce what customer owes)
            $payments = $invoice->getPayments();
            foreach ($payments as $payment) {
                if (isset($payment['amount']) && $payment['amount'] > 0) {
                    $paymentDate = null;
                    if (isset($payment['date'])) {
                        $paymentDate = \Carbon\Carbon::parse($payment['date']);
                    } elseif (isset($payment['paid_at'])) {
                        $paymentDate = \Carbon\Carbon::parse($payment['paid_at']);
                    } else {
                        $paymentDate = $invoice->updated_at;
                    }
                    
                    $transactions->push([
                        'date' => $paymentDate,
                        'type' => 'credit',
                        'amount' => $payment['amount'],
                        'description' => __('Payment') . (isset($payment['check_number']) ? ' - ' . __('Check #:number', ['number' => $payment['check_number']]) : ''),
                        'reference' => $invoice->invoice_number,
                        'reference_url' => route('my.invoices.edit', ['invoice' => $invoice->id]),
                        'sort_order' => 1,
                    ]);
                }
            }
        }
        
        return view('customers.show', compact('customer', 'transactions', 'accountCredit'));
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

    public function destroy(Request $request, $customer){

        $customer = Customer::find($customer);

        if (! $customer) {
            return redirect()->route('customers.index')
                ->with('error', __('That customer no longer exists.'));
        }

        $this->authorize('delete', $customer);
        $name = $customer->name;
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', __(':name deleted.', ['name' => $name]));
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

    public function invite(Request $request, int $customer, InviteCustomerUser $inviter){
        $customer = Customer::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($customer);

        $this->authorize('update', $customer);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $inviter->invite($customer, $data['email'], $data['name'] ?? null);

        return back()->with('status', $result['created']
            ? __('Invitation sent to :email — a portal account was created.', ['email' => $result['user']->email])
            : __('Re-sent portal sign-in instructions to :email.', ['email' => $result['user']->email]));
    }
}
