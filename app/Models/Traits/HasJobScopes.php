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
}