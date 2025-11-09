<?php

namespace App\Http\Controllers;

use App\Events\InvoiceReady;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Models\JobInvoice;
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
    public function print(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if (! $user->is_super && ! $user->isAdmin() && ! $user->isManager()) {
            abort(403);
        }

        $this->authorize('view', $invoice);

        $invoice->loadMissing(['customer', 'organization', 'job']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'values' => is_array($invoice->values) ? $invoice->values : [],
        ]);
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

                    JobInvoice::where('invoice_id', $invoice->id)->delete();
                    $invoice->forceDelete();

                    session()->flash('success', __('Summary invoice deleted. Child invoices released.'));

                    return redirect()->route('my.jobs.show', ['job' => $jobId]);
                }

                foreach ($children as $child) {
                    JobInvoice::where('invoice_id', $child->id)->delete();
                    $child->forceDelete();
                }
            }

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
            'extra_charge',
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

        $invoice->values = $values;

        if ($request->filled('paid_in_full')) {
            $invoice->paid_in_full = $request->input('paid_in_full') === 'yes';
        }

        $invoice->save();

        session()->flash('success', __('Invoice updated.'));

        return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
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

        $jobs = PilotCarJob::with('customer', 'organization')
            ->whereIn('id', $jobIds)
            ->get();

        if ($jobs->isEmpty()) {
            return back()->with('error', __('No matching jobs were found for invoicing.'));
        }

        if ($jobs->pluck('customer_id')->unique()->count() > 1) {
            return back()->with('error', __('Invoices can only be generated for one customer at a time.'));
        }

        $createdInvoices = DB::transaction(function () use ($jobs) {
            $createdInvoices = collect();

            foreach ($jobs as $job) {
                $invoiceValues = $job->invoiceValues();

                $invoice = Invoice::create([
                    'paid_in_full' => false,
                    'values' => $invoiceValues,
                    'organization_id' => $invoiceValues['organization_id'],
                    'customer_id' => $invoiceValues['customer_id'],
                    'pilot_car_job_id' => $job->id,
                    'invoice_type' => 'single',
                ]);

                JobInvoice::firstOrCreate([
                    'invoice_id' => $invoice->id,
                    'pilot_car_job_id' => $job->id,
                ]);

                $createdInvoices->push($invoice->fresh());
            }

            if ($createdInvoices->count() > 1) {
                $summary = Invoice::create([
                    'paid_in_full' => false,
                    'values' => $this->buildSummaryValues($createdInvoices),
                    'organization_id' => $createdInvoices->first()->organization_id,
                    'customer_id' => $createdInvoices->first()->customer_id,
                    'invoice_type' => 'summary',
                ]);

                foreach ($createdInvoices as $child) {
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

            return $createdInvoices;
        });

        /** @var \App\Models\Invoice $invoice */
        $invoice = $createdInvoices->first();

        event(new InvoiceReady($invoice));

        return redirect()->route('my.invoices.edit', ['invoice' => $invoice->id]);
    }

    protected function buildSummaryValues(Collection $childInvoices): array
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

            $items[] = [
                'invoice_id' => $child->id,
                'invoice_number' => $child->invoice_number,
                'title' => data_get($childValues, 'title', 'INVOICE'),
                'job_no' => data_get($childValues, 'load_no'),
                'total' => $childTotal,
                'billable_miles' => $childMiles,
                'rate_code' => data_get($childValues, 'effective_rate_code') ?? data_get($childValues, 'rate_code'),
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


