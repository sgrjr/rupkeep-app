<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\JobInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CreateInvoiceSummary extends Component
{
    use AuthorizesRequests;

    public $customerId = null;
    public $selectedInvoiceIds = [];
    public $invoices = [];
    public $showModal = false;

    public function mount($customerId = null)
    {
        $this->customerId = $customerId;
        $this->loadInvoices();
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->selectedInvoiceIds = [];
        $this->loadInvoices();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedInvoiceIds = [];
    }

    public function loadInvoices()
    {
        $organizationId = Auth::user()->organization_id;
        
        $query = Invoice::with(['customer', 'job'])
            ->where('organization_id', $organizationId)
            ->where('invoice_type', '!=', 'summary') // Exclude existing summaries
            ->whereNull('parent_invoice_id') // Exclude invoices already in a summary
            ->orderBy('created_at', 'desc');

        if ($this->customerId) {
            $query->where('customer_id', $this->customerId);
        }

        $this->invoices = $query->get();
    }

    public function toggleInvoice($invoiceId)
    {
        if (in_array($invoiceId, $this->selectedInvoiceIds)) {
            $this->selectedInvoiceIds = array_values(array_diff($this->selectedInvoiceIds, [$invoiceId]));
        } else {
            $this->selectedInvoiceIds[] = $invoiceId;
        }
    }

    public function createSummary()
    {
        $this->authorize('create', Invoice::class);

        if (count($this->selectedInvoiceIds) < 2) {
            session()->flash('error', __('Please select at least two invoices to create a summary.'));
            return;
        }

        $invoices = Invoice::with('customer', 'organization', 'job')
            ->whereIn('id', $this->selectedInvoiceIds)
            ->get();

        if ($invoices->isEmpty()) {
            session()->flash('error', __('No matching invoices were found.'));
            return;
        }

        // Check all invoices belong to same customer
        if ($invoices->pluck('customer_id')->unique()->count() > 1) {
            session()->flash('error', __('All selected invoices must belong to the same customer.'));
            return;
        }

        // Check all invoices belong to same organization
        if ($invoices->pluck('organization_id')->unique()->count() > 1) {
            session()->flash('error', __('All selected invoices must belong to the same organization.'));
            return;
        }

        // Check that invoices are not already part of a summary
        $alreadyInSummary = $invoices->filter(fn($inv) => $inv->parent_invoice_id !== null || $inv->isSummary());
        if ($alreadyInSummary->isNotEmpty()) {
            session()->flash('error', __('Some selected invoices are already part of a summary invoice.'));
            return;
        }

        $summary = DB::transaction(function () use ($invoices) {
            $summary = Invoice::create([
                'paid_in_full' => false,
                'values' => $this->buildSummaryValues($invoices),
                'organization_id' => $invoices->first()->organization_id,
                'customer_id' => $invoices->first()->customer_id,
                'invoice_type' => 'summary',
            ]);

            foreach ($invoices as $child) {
                $child->update(['parent_invoice_id' => $summary->id]);
                
                // Link summary to all jobs from child invoices
                // Single invoices: use job() relationship (singular)
                // Summary invoices: use jobs() relationship (plural via pivot)
                if ($child->isSummary()) {
                    $childJobs = $child->jobs;
                } else {
                    // Single invoice - get job via pilot_car_job_id
                    $childJob = $child->job;
                    $childJobs = $childJob ? collect([$childJob]) : collect();
                }
                
                foreach ($childJobs as $job) {
                    JobInvoice::firstOrCreate([
                        'invoice_id' => $summary->id,
                        'pilot_car_job_id' => $job->id,
                    ]);
                }
            }

            return $summary;
        });

        $this->closeModal();
        session()->flash('success', __('Summary invoice created successfully from :count invoice(s).', [
            'count' => $invoices->count()
        ]));

        return $this->redirect(route('my.invoices.edit', ['invoice' => $summary->id]), navigate: false);
    }

    protected function buildSummaryValues($childInvoices)
    {
        $baseValues = $childInvoices->first()->values ?? [];

        $total = 0.0;
        $billableMiles = 0.0;
        $items = [];

        foreach ($childInvoices as $child) {
            $childValues = $child->values ?? [];
            $childTotal = (float) data_get($childValues, 'total', 0);
            $childMiles = (float) data_get($childValues, 'billable_miles', 0);

            $total += $childTotal;
            $billableMiles += $childMiles;

            // Generate description of work from pickup and delivery addresses
            $pickupAddress = data_get($childValues, 'pickup_address');
            $deliveryAddress = data_get($childValues, 'delivery_address');
            $description = \App\Models\Invoice::generateDescriptionOfWork($pickupAddress, $deliveryAddress);

            // Get job info if available
            $job = $child->job;
            $items[] = [
                'invoice_id' => $child->id,
                'invoice_number' => $child->invoice_number ?? '—',
                'title' => data_get($childValues, 'title', 'INVOICE'),
                'job_no' => data_get($childValues, 'job_no') ?? $job->job_no ?? data_get($childValues, 'load_no') ?? '—',
                'load_no' => data_get($childValues, 'load_no') ?? $job->load_no ?? '—',
                'pickup_address' => $pickupAddress ?? $job->pickup_address ?? '—',
                'delivery_address' => $deliveryAddress ?? $job->delivery_address ?? '—',
                'description' => $description ?? '—',
                'total' => $childTotal > 0 ? $childTotal : (float)data_get($childValues, 'total', 0),
                'billable_miles' => $childMiles > 0 ? $childMiles : (float)data_get($childValues, 'billable_miles', 0),
                'rate_code' => data_get($childValues, 'effective_rate_code') ?? data_get($childValues, 'rate_code') ?? $job->rate_code ?? '—',
                'date_of_service' => $child->created_at?->format('Y-m-d') ?? '—',
            ];
        }

        $baseValues['title'] = 'SUMMARY INVOICE';
        $baseValues['total'] = round($total, 2);
        $baseValues['billable_miles'] = round($billableMiles, 2);
        $baseValues['summary_items'] = $items;
        $baseValues['child_invoice_ids'] = $childInvoices->pluck('id')->all();

        return $baseValues;
    }

    public function render()
    {
        return view('livewire.create-invoice-summary');
    }
}
