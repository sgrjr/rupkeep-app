<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            'jobs.invoices.children',
        ])->where('id', $customer_id)->first();
        
        // Prepare transaction register data
        $transactions = collect();
        
        // Account credit is shown as a display line but represents current available credit
        // It will be applied to the final balance calculation
        $accountCredit = $customer->account_credit ?? 0;
        
        // Get all invoices for this customer (only parent invoices to avoid duplicates)
        $invoices = \App\Models\Invoice::where('customer_id', $customer_id)
            ->where('organization_id', auth()->user()->organization_id)
            ->whereNull('parent_invoice_id') // Only parent invoices
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
