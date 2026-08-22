<?php

namespace App\Http\Controllers;

use App\Events\InvoiceReady;
use App\Events\JobStatusChanged;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Models\JobInvoice;
use App\Models\UserLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MyInvoicesController extends Controller
{

    use AuthorizesRequests;

    public function edit(Request $request, Invoice $invoice){

      $this->authorize('update', $invoice);

      return view('invoices.edit', compact('invoice'));
    }
    /**
     * The bare /my/invoices/{invoice} URL. There is no separate read-only staff
     * view — edit is the staff view — so send them there rather than 405 on a
     * URL that looks like it should work (TASK-370).
     */
    public function show(Invoice $invoice)
    {
        return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
    }

    public function print(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if (! $user->isSuper() && ! $user->isAdmin() && ! $user->isManager()) {
            abort(403);
        }

        $this->authorize('view', $invoice);

        $invoice->loadMissing(['customer', 'organization', 'job']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'values' => is_array($invoice->values) ? $invoice->values : [],
            'forPdf' => false,
        ]);
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        // Check if PDF downloads are enabled
        if (!config('features.invoice_pdf_downloads', false)) {
            abort(404, 'PDF downloads are not currently available.');
        }

        $user = $request->user();

        if (! $user->isSuper() && ! $user->isAdmin() && ! $user->isManager()) {
            abort(403);
        }

        $this->authorize('view', $invoice);

        // Load relationships needed for PDF
        $invoice->loadMissing(['customer', 'organization', 'job', 'children']);

        $values = is_array($invoice->values) ? $invoice->values : [];

        // Use the existing print template which handles both single and summary invoices
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.print', [
            'invoice' => $invoice,
            'values' => $values,
            'forPdf' => true,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('letter', 'portrait');

        $invoiceType = $invoice->isSummary() ? 'Summary' : 'Invoice';
        $filename = $invoiceType . '-' . $invoice->invoice_number . '.pdf';

        return $pdf->download($filename);
    }

    public function update(Request $request, Invoice $invoice){

        $this->authorize('update', $invoice);

        if ($request->boolean('delete')) {
            $jobId = $invoice->pilot_car_job_id ?? $invoice->children()->value('pilot_car_job_id');
            $deleteMode = $request->input('delete_mode', $invoice->isSummary() ? 'release_children' : 'delete_children');

            if ($invoice->isSummary()) {
                $children = $invoice->children()->get();

                if ($deleteMode === 'release_children') {
                    foreach ($children as $child) {
                        $child->update(['parent_invoice_id' => null]);
                    }

                    // Delete pivot entries for summary invoice (summary invoices use pivot table)
                    JobInvoice::where('invoice_id', $invoice->id)->delete();
                    $invoice->forceDelete();

                    session()->flash('success', __('Summary invoice deleted. Child invoices released.'));

                    return redirect()->route('my.jobs.show', ['job' => $jobId]);
                }

                // Delete children and their pivot entries (if they're summaries)
                foreach ($children as $child) {
                    if ($child->isSummary()) {
                        // Only summary invoices have pivot entries
                        JobInvoice::where('invoice_id', $child->id)->delete();
                    }
                    $child->forceDelete();
                }
            }

            // Defensive cleanup: remove any pivot rows for this invoice regardless
            // of single/summary type. Single invoices normally have none, but a
            // stale row would orphan otherwise.
            JobInvoice::where('invoice_id', $invoice->id)->delete();

            $invoice->forceDelete();

            session()->flash('success', __('Invoice deleted.'));

            return redirect()->route('my.jobs.show', ['job' => $jobId]);
        }

        $values = $invoice->values ?? [];

        $incomingValues = $request->input('values', []);

        if (! is_array($incomingValues)) {
            $incomingValues = [];
        }

        $numericKeys = [
            'wait_time_hours',
            'extra_load_stops_count',
            'dead_head',
            'tolls',
            'hotel',
            'cars_count',
            'billable_miles',
            'nonbillable_miles',
            'rate_value',
            'effective_rate_value',
            'total_due',
            'total',
        ];

        foreach (Arr::dot($incomingValues) as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }

            if (in_array($key, $numericKeys, true)) {
                $value = $value === null ? null : (float) $value;
            }

            Arr::set($values, $key, $value);
        }

        // Explicitly handle top-level numeric keys that may not work with Arr::dot/set
        // extra_charge is deliberately absent: it is derived from the job's
        // log_extra_charges rows (TASK-330) and kept in step by
        // App\Livewire\LogExtraCharges. Accepting it from this form would let a
        // stale posted value clobber the itemized charges it is meant to total.
        foreach (['tolls', 'hotel', 'wait_time_hours', 'billable_miles', 'total'] as $topKey) {
            if (isset($incomingValues[$topKey])) {
                $val = trim((string) $incomingValues[$topKey]);
                $values[$topKey] = $val === '' ? null : (float) $val;
            }
        }

        $invoice->values = $values;

        if ($request->filled('paid_in_full')) {
            $invoice->paid_in_full = $request->input('paid_in_full') === 'yes';
        }

        $invoice->save();

        session()->flash('success', __('Invoice updated.'));

        return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
    }

    public function applyLateFees(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if ($invoice->paid_in_full) {
            session()->flash('error', __('Cannot apply late fees to a paid invoice.'));
            return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
        }

        $lateFees = $invoice->calculateLateFees();

        if (!$lateFees['is_past_due'] || $lateFees['late_fee_amount'] <= 0) {
            session()->flash('info', __('This invoice is not past due. No late fees to apply.'));
            return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
        }

        $values = $invoice->values ?? [];
        
        // Save late fee information to invoice values
        $values['late_fees'] = [
            'applied_at' => now()->toDateTimeString(),
            'applied_by' => $request->user()->id,
            'is_past_due' => $lateFees['is_past_due'],
            'days_overdue' => $lateFees['days_overdue'],
            'late_fee_periods' => $lateFees['late_fee_periods'],
            'late_fee_amount' => $lateFees['late_fee_amount'],
            'original_total' => (float) ($values['total'] ?? 0),
            'total_with_late_fees' => $lateFees['total_with_late_fees'],
        ];

        // Update the total to include late fees
        $values['total'] = $lateFees['total_with_late_fees'];
        
        $invoice->values = $values;
        $invoice->save();

        session()->flash('success', __('Late fees applied. Invoice total updated to $:amount.', [
            'amount' => number_format($lateFees['total_with_late_fees'], 2)
        ]));

        return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
    }

    public function toggleMarkedForAttention(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->marked_for_attention = !$invoice->marked_for_attention;
        $invoice->save();
        $invoice->refresh();

        return response()->json([
            'success' => true,
            'marked_for_attention' => $invoice->marked_for_attention,
        ]);
    }

    public function store(Request $request){

        $jobIds = collect($request->input('invoice_this', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($jobIds->isEmpty()) {
            return back()->with('error', __('Please select at least one job to invoice.'));
        }

        $jobs = PilotCarJob::with('customer', 'organization', 'singleInvoices', 'summaryInvoices')
            ->whereIn('id', $jobIds)
            ->get();

        if ($jobs->isEmpty()) {
            return back()->with('error', __('No matching jobs were found for invoicing.'));
        }

        if ($jobs->pluck('customer_id')->unique()->count() > 1) {
            return back()->with('error', __('Invoices can only be generated for one customer at a time.'));
        }

        // Validate that jobs with invoices are not already in a summary
        $jobsInSummary = collect();
        foreach ($jobs as $job) {
            $primaryInvoice = $job->invoices->whereNull('parent_invoice_id')->sortByDesc('created_at')->first();
            if ($primaryInvoice && $primaryInvoice->isSummary()) {
                $jobsInSummary->push($job);
            }
        }

        if ($jobsInSummary->isNotEmpty()) {
            $jobNumbers = $jobsInSummary->pluck('job_no')->filter()->implode(', ');
            return back()->with('error', __('Some selected jobs are already part of a summary invoice: :jobs', ['jobs' => $jobNumbers ?: __('Job IDs: ') . $jobsInSummary->pluck('id')->implode(', ')]));
        }

        // Capture each job's status before invoicing so we can announce the
        // ACTIVE -> COMPLETED transition to assigned drivers afterwards
        // (TASK-311).
        $statusBefore = $jobs->mapWithKeys(fn (PilotCarJob $job) => [$job->id => $job->status]);

        $createdInvoices = DB::transaction(function () use ($jobs) {
            $createdInvoices = collect();
            $existingInvoices = collect();

            // Separate jobs into those needing new invoices and those with existing invoices
            foreach ($jobs as $job) {
                $primaryInvoice = $job->invoices->whereNull('parent_invoice_id')->sortByDesc('created_at')->first();
                
                if ($primaryInvoice && !$primaryInvoice->isSummary()) {
                    // Job already has an invoice - use it for grouping
                    $existingInvoices->push($primaryInvoice);
                } else {
                    // Job needs a new invoice - create single invoice (no pivot entry)
                    $invoice = $job->createInvoice([
                        'paid_in_full' => false,
                        'invoice_type' => 'single',
                    ]);

                    $createdInvoices->push($invoice->fresh());
                }
            }

            // Combine new and existing invoices
            $allInvoices = $createdInvoices->merge($existingInvoices);

            // If we have multiple invoices (new or existing), create a summary
            if ($allInvoices->count() > 1) {
                $summary = Invoice::create([
                    'paid_in_full' => false,
                    'values' => $this->buildSummaryValues($allInvoices),
                    'organization_id' => $allInvoices->first()->organization_id,
                    'customer_id' => $allInvoices->first()->customer_id,
                    'invoice_type' => 'summary',
                ]);

                foreach ($allInvoices as $child) {
                    $child->update(['parent_invoice_id' => $summary->id]);
                }

                foreach ($jobs as $job) {
                    JobInvoice::firstOrCreate([
                        'invoice_id' => $summary->id,
                        'pilot_car_job_id' => $job->id,
                    ]);
                }

                return collect([$summary]);
            }

            // Single invoice - return it (either newly created or existing)
            return $allInvoices;
        });

        /** @var \App\Models\Invoice $invoice */
        $invoice = $createdInvoices->first();

        event(new InvoiceReady($invoice));

        // A job moves ACTIVE -> COMPLETED once it has an invoice; tell assigned
        // drivers for any job whose status actually changed (TASK-311).
        foreach ($jobs as $job) {
            JobStatusChanged::fireIfChanged($job, $statusBefore[$job->id]);
        }

        return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
    }

    /**
     * Create a summary invoice from selected existing invoices
     */
    public function createSummaryFromInvoices(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $invoiceIds = collect($request->input('invoice_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty()) {
            return back()->with('error', __('Please select at least one invoice to group.'));
        }

        if ($invoiceIds->count() < 2) {
            return back()->with('error', __('Please select at least two invoices to create a summary.'));
        }

        $invoices = Invoice::with('customer', 'organization', 'job')
            ->whereIn('id', $invoiceIds)
            ->get();

        if ($invoices->isEmpty()) {
            return back()->with('error', __('No matching invoices were found.'));
        }

        // Check all invoices belong to same customer
        if ($invoices->pluck('customer_id')->unique()->count() > 1) {
            return back()->with('error', __('All selected invoices must belong to the same customer.'));
        }

        // Check all invoices belong to same organization
        if ($invoices->pluck('organization_id')->unique()->count() > 1) {
            return back()->with('error', __('All selected invoices must belong to the same organization.'));
        }

        // Check that invoices are not already part of a summary
        $alreadyInSummary = $invoices->filter(fn($inv) => $inv->parent_invoice_id !== null || $inv->isSummary());
        if ($alreadyInSummary->isNotEmpty()) {
            return back()->with('error', __('Some selected invoices are already part of a summary invoice.'));
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

        session()->flash('success', __('Summary invoice created successfully from :count invoice(s).', [
            'count' => $invoices->count()
        ]));

        return redirect()->route('my.invoices.edit', ['invoice' => $summary->id]);
    }

    /**
     * The `values` blob for a summary invoice.
     *
     * A summary is a cover sheet: one row per child invoice at that child's
     * total, and nothing else. It therefore carries NO per-job scalars of its
     * own -- no hotel, tolls, extra_charge, rate_code, job_no, wait time. This
     * used to seed itself from the first child's whole values array, so a
     * summary silently inherited one arbitrary child's expense figures as dead
     * keys, which the QuickBooks export then wrote out as if they were the
     * summary's own (TASK-379).
     *
     * Carrying none rather than rolling them up is deliberate. Every child's
     * expenses are already inside the child total this sums, and are itemized
     * on the child invoice, which stays individually openable and is linked
     * from the summary. A rolled-up figure would be a second set of numbers no
     * template prints, and nothing recomputes a summary when a child is edited,
     * so it would start going stale immediately.
     *
     * Only presentation carries over from the first child -- who the invoice is
     * from and to, and the logo/footer chrome. All children share a customer and
     * an organization (both call sites enforce it), so that much is not
     * arbitrary.
     */
    protected function buildSummaryValues(Collection $childInvoices): array
    {
        $firstValues = $childInvoices->first()->values ?? [];

        $baseValues = Arr::only($firstValues, ['bill_from', 'bill_to', 'logo', 'footer']);

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

            // The job may be gone (force-deleted) while the invoice remains, so
            // every read below goes through optional chaining rather than
            // assuming a relation.
            $job = $child->job;

            $items[] = [
                'invoice_id' => $child->id,
                'invoice_number' => $child->invoice_number ?? '—',
                'title' => data_get($childValues, 'title', 'INVOICE'),
                'job_no' => data_get($childValues, 'job_no') ?? $job?->job_no ?? data_get($childValues, 'load_no') ?? '—',
                'load_no' => data_get($childValues, 'load_no') ?? $job?->load_no ?? '—',
                'pickup_address' => $pickupAddress ?? $job?->pickup_address ?? '—',
                'delivery_address' => $deliveryAddress ?? $job?->delivery_address ?? '—',
                'description' => $description ?? '—',
                'total' => $childTotal > 0 ? $childTotal : (float)data_get($childValues, 'total', 0),
                'billable_miles' => $childMiles > 0 ? $childMiles : (float)data_get($childValues, 'billable_miles', 0),
                'rate_code' => data_get($childValues, 'effective_rate_code') ?? data_get($childValues, 'rate_code') ?? $job?->rate_code ?? '—',
                // When the WORK happened, not when the invoice was cut (TASK-345).
                // These were previously all the child invoice's created_at, so
                // every row of a monthly summary carried the same date.
                'date_of_service' => $job?->scheduled_pickup_at
                    ?? data_get($childValues, 'start_job_time')
                    ?? $child->created_at?->format('Y-m-d')
                    ?? '—',
                // The TASK-344 required fields that map onto a summary row. A
                // summary aggregates several jobs, so these ride per-row rather
                // than in the single-invoice job block.
                'truck_driver_name' => trim((string) (data_get($childValues, 'truck_driver_name') ?? '')) ?: null,
                'truck_number' => trim((string) (data_get($childValues, 'truck_number') ?? '')) ?: null,
                'trailer_number' => trim((string) (data_get($childValues, 'trailer_number') ?? '')) ?: null,
                'canceled_at' => $job?->canceled_at ? (string) $job->canceled_at : null,
                'canceled_reason' => trim((string) ($job?->canceled_reason ?? '')) ?: null,
            ];
        }

        $baseValues['title'] = 'SUMMARY INVOICE';
        $baseValues['total'] = round($total, 2);
        $baseValues['billable_miles'] = round($billableMiles, 2);
        $baseValues['summary_items'] = $items;
        $baseValues['child_invoice_ids'] = $childInvoices->pluck('id')->all();

        return $baseValues;
    }

    public function delete(Request $request, $log){
        return $this->destroy($request, $log);
    }
    public function destroy(Request $request, $log){

        $log = UserLog::find($log);

        if($log && $this->authorize('delete', $log)){
           $log->delete();
        }

        return back();
    }

    public function restore(Request $request, $log){
        $log = UserLog::withTrashed()->find($log);

        if($log && $this->authorize('restore', $log)){
           $log->restore();
        }

        return back();
    }

    public function forceDelete(Request $request, $log){

        $log = UserLog::withTrashed()->find($log);

        if($log && $this->authorize('delete', $log)){
           $log->forceDelete();
        }

        return back();
    }
}


