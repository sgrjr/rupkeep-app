<?php

namespace App\Observers;

use App\Models\PilotCarJob;
use App\Models\Invoice;

class PilotCarJobObserver
{
    /**
     * Handle the PilotCarJob "updated" event.
     */
    public function updated(PilotCarJob $job): void
    {
        // Only process if invoice_paid status changed
        if (!$job->isDirty('invoice_paid')) {
            return;
        }

        $invoicePaidValue = $job->invoice_paid;
        $isPaid = !empty($invoicePaidValue) && (float) $invoicePaidValue >= 1;

        // Sync to all related invoices via pivot table
        $relatedInvoices = $job->invoices()->get();
        foreach ($relatedInvoices as $invoice) {
            // Only update if status actually changed
            if ($invoice->paid_in_full !== $isPaid) {
                $invoice->paid_in_full = $isPaid;
                $invoice->saveQuietly(); // Prevent observer recursion
                
                // If this was a child invoice and now paid, check parent summary
                if ($isPaid && $invoice->parent) {
                    $this->checkAndUpdateSummaryInvoice($invoice->parent);
                }
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
                // Recalculate job payment status based on summary invoice
                $this->syncJobFromSummaryInvoice($job, $summaryInvoice);
            }

            // Also sync via direct relationship
            if ($summaryInvoice->job) {
                if (!$summaryJobs->contains('id', $summaryInvoice->job->id)) {
                    $this->syncJobFromSummaryInvoice($summaryInvoice->job, $summaryInvoice);
                }
            }
        }
    }

    /**
     * Sync job payment status from summary invoice
     * Recalculates based on all paid invoices for the job
     */
    private function syncJobFromSummaryInvoice(PilotCarJob $job, Invoice $summaryInvoice): void
    {
        // Recalculate from all paid invoices (not just summary children)
        // This ensures accuracy if job has multiple invoices
        $allPaidInvoices = $job->invoices()
            ->where('paid_in_full', true)
            ->get();
        
        if ($allPaidInvoices->isEmpty()) {
            $job->invoice_paid = '0';
        } else {
            $totalPaid = $allPaidInvoices->sum(fn($inv) => (float) ($inv->values['total'] ?? 0));
            $job->invoice_paid = (string) $totalPaid;
        }
        
        $job->saveQuietly();
    }
}
