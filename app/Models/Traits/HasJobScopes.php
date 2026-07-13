<?php

namespace App\Models\Traits;


trait HasJobScopes {
    public function scopeIsPaid($query){
        return $query->whereNotNull('invoice_paid')->where('invoice_paid', '>',0);
    }
    public function scopeIsNotPaid($query){
        return $query->whereNull('invoice_paid')->orWhere('invoice_paid', '<',1);
    }
    public function scopeIsCanceled($query){
        return $query->whereNotNull('canceled_at');
    }
    public function scopeMissingJobNo($query){
        return $query->whereNull('job_no');
    }
    public function scopeIsActive($query){
        // Active jobs are those that are not completed, not cancelled
        // Status is ACTIVE if: no canceled_at AND no invoices (single or summary)
        // Match the logic from getStatusAttribute()
        return $query->whereNull('canceled_at')
            ->whereDoesntHave('singleInvoices')
            ->whereDoesntHave('summaryInvoices');
    }
    public function scopeIsCompleted($query){
        // Completed jobs are those that have invoices (single or summary)
        // Status is COMPLETED if: has single invoices OR has summary invoices
        // Match the logic from getStatusAttribute()
        return $query->where(function($q) {
            $q->whereHas('singleInvoices')
              ->orWhereHas('summaryInvoices');
        });
    }
    public function scopeIsFlagged($query){
        // Flagged jobs are those with any invoice (single or summary) that a
        // customer or staff member marked for attention.
        return $query->where(function($q) {
            $q->whereHas('singleInvoices', fn($i) => $i->where('marked_for_attention', true))
              ->orWhereHas('summaryInvoices', fn($i) => $i->where('marked_for_attention', true));
        });
    }
}