<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\PilotCarJob;

class InvoiceObserver
{
    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        // Only process if paid_in_full status changed
        if (!$invoice->isDirty('paid_in_full')) {
            return;
        }

        $isPaid = $invoice->paid_in_full;

        // Sync to related jobs via pivot table (jobs_invoices)
        $relatedJobs = $invoice->jobs()->get();
        foreach ($relatedJobs as $job) {
            $this->syncJobPaymentStatus($job, $isPaid, $invoice);
        }

        // Also sync via direct relationship (pilot_car_job_id)
        if ($invoice->job) {
            // Avoid duplicate sync if job is already in the pivot relationship
            if (!$relatedJobs->contains('id', $invoice->job->id)) {
                $this->syncJobPaymentStatus($invoice->job, $isPaid, $invoice);
            }
        }

        // If summary invoice: cascade to children
        if ($invoice->isSummary() && $isPaid) {
            $children = $invoice->children()->get();
            foreach ($children as $child) {
                if (!$child->paid_in_full) {
                    $child->paid_in_full = true;
                    $child->saveQuietly(); // Prevent observer recursion
                    
                    // Sync child invoice's jobs
                    $this->syncChildInvoiceJobs($child);
                }
            }
        }

        // If child invoice: check if parent should be marked paid
        if ($invoice->parent && $isPaid) {
            $this->checkAndUpdateSummaryInvoice($invoice->parent);
        }
    }

    /**
     * Sync job payment status based on invoice payment status
     */
    private function syncJobPaymentStatus(PilotCarJob $job, bool $isPaid, Invoice $invoice): void
    {
        // Always recalculate from all paid invoices to ensure accuracy
        // This handles both paid and unpaid scenarios correctly
        $allPaidInvoices = $job->invoices()
            ->where('paid_in_full', true)
            ->get();
        
        if ($allPaidInvoices->isEmpty()) {
            // No paid invoices, mark job as unpaid
            $job->invoice_paid = '0';
        } else {
            // Sum all paid invoice amounts
            $totalPaid = $allPaidInvoices->sum(fn($inv) => (float) ($inv->values['total'] ?? 0));
            $job->invoice_paid = (string) $totalPaid;
        }
        
        $job->saveQuietly(); // Prevent observer recursion
    }

    /**
     * Sync child invoice's jobs when summary invoice is paid
     */
    private function syncChildInvoiceJobs(Invoice $childInvoice): void
    {
        // Sync to related jobs via pivot table
        $relatedJobs = $childInvoice->jobs()->get();
        foreach ($relatedJobs as $job) {
            $this->syncJobPaymentStatus($job, true, $childInvoice);
        }

        // Also sync via direct relationship
        if ($childInvoice->job) {
            if (!$relatedJobs->contains('id', $childInvoice->job->id)) {
                $this->syncJobPaymentStatus($childInvoice->job, true, $childInvoice);
            }
        }
    }

    /**
     * Check if all children are paid and update summary invoice if needed
     */
    private function checkAndUpdateSummaryInvoice(Invoice $summaryInvoice): void
    {
        if (!$summaryInvoice->isSummary()) {
            return;
        }

        $children = $summaryInvoice->children()->get();
        
        // Skip if no children
        if ($children->isEmpty()) {
            return;
        }

        // Check if all children are paid
        $allPaid = $children->every(fn($child) => $child->paid_in_full);
        
        if ($allPaid && !$summaryInvoice->paid_in_full) {
            $summaryInvoice->paid_in_full = true;
            $summaryInvoice->saveQuietly();
            
            // Also sync summary invoice's jobs
            $summaryJobs = $summaryInvoice->jobs()->get();
            foreach ($summaryJobs as $job) {
                $this->syncJobPaymentStatus($job, true, $summaryInvoice);
            }

            // Also sync via direct relationship
            if ($summaryInvoice->job) {
                if (!$summaryJobs->contains('id', $summaryInvoice->job->id)) {
                    $this->syncJobPaymentStatus($summaryInvoice->job, true, $summaryInvoice);
                }
            }
        }
    }
}
