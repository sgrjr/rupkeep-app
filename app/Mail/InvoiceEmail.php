<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $preliminaryText;
    public $includePdf;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice, string $preliminaryText = '', bool $includePdf = true)
    {
        $this->invoice = $invoice;
        $this->preliminaryText = $preliminaryText;
        $this->includePdf = $includePdf;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = __('Invoice #:number from :company', [
            'number' => $this->invoice->invoice_number,
            'company' => $this->invoice->organization?->name ?? config('app.name'),
        ]);

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->invoice->loadMissing(['customer', 'organization', 'job', 'children']);

        $values = is_array($this->invoice->values) ? $this->invoice->values : [];

        return new Content(
            view: 'mail.invoice-email',
            with: [
                'invoice' => $this->invoice,
                'values' => $values,
                'preliminaryText' => $this->preliminaryText,
                'isSummary' => $this->invoice->isSummary(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (!$this->includePdf) {
            return [];
        }

        $this->invoice->loadMissing(['customer', 'organization', 'job']);

        $values = is_array($this->invoice->values) ? $this->invoice->values : [];

        // Generate PDF
        $pdf = Pdf::loadView('invoices.print', [
            'invoice' => $this->invoice,
            'values' => $values,
        ]);

        $filename = 'Invoice-' . $this->invoice->invoice_number . '.pdf';

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $filename
            )->withMime('application/pdf'),
        ];
    }
}
