<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = Invoice::query()
            ->with(['customer', 'job'])
            ->where('customer_id', $user->customer_id ?? null)
            ->latest()
            ->paginate(15);

        return view('customer.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return view('customer.invoices.show', [
            'invoice' => $invoice,
            'attachments' => $invoice->publicProofAttachments(),
        ]);
    }
}

