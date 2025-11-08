<?php

namespace App\Events;

use App\Models\InvoiceComment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceFlagged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public InvoiceComment $comment
    ) {
    }
}


