<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\CustomerContact;
use App\Mail\InvoiceEmail;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvoiceEmailForm extends Component
{
    public $invoice;
    public $showModal = false;

    protected $listeners = [];

    #[Validate('required|string')]
    public $to = '';

    #[Validate('nullable|string')]
    public $bcc = '';

    #[Validate('nullable|string|max:5000')]
    public $preliminaryText = '';

    #[Validate('nullable|boolean')]
    public $includePdf = true;

    public function mount(Invoice $invoice)
    {
        // Sending an invoice is editing it (TASK-438). The blade only renders
        // this for users who may, and the action checks again below.
        $this->authorize('update', $invoice);

        $this->invoice = $invoice;
        $this->loadDefaultRecipients();
    }

    public function boot()
    {
        $this->listeners['open-invoice-email-modal-' . $this->invoice->id] = 'openModal';
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->loadDefaultRecipients();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['to', 'bcc', 'preliminaryText', 'includePdf']);
        $this->loadDefaultRecipients();
    }

    protected function loadDefaultRecipients()
    {
        // Load billing contact email if available
        if ($this->invoice->customer) {
            $billingContact = CustomerContact::where('customer_id', $this->invoice->customer_id)
                ->where('is_billing_contact', true)
                ->whereNotNull('email')
                ->first();

            if ($billingContact && empty($this->to)) {
                $this->to = $billingContact->email;
            }
        }

        // Set BCC to current user's email
        if (Auth::check() && empty($this->bcc)) {
            $this->bcc = Auth::user()->email;
        }
    }

    public function send()
    {
        $this->authorize('update', $this->invoice);

        // Validate email addresses
        $toEmails = array_map('trim', array_filter(explode(',', $this->to)));
        $bccEmails = $this->bcc ? array_map('trim', array_filter(explode(',', $this->bcc))) : [];

        foreach ($toEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addError('to', __('Invalid email address: :email', ['email' => $email]));
                return;
            }
        }

        foreach ($bccEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addError('bcc', __('Invalid email address: :email', ['email' => $email]));
                return;
            }
        }

        if (empty($toEmails)) {
            $this->addError('to', __('At least one recipient email is required.'));
            return;
        }

        try {
            foreach ($toEmails as $email) {
                $mailable = new InvoiceEmail(
                    $this->invoice,
                    $this->preliminaryText,
                    $this->includePdf
                );

                if (!empty($bccEmails)) {
                    $mailable->bcc($bccEmails);
                }

                Mail::to($email)->send($mailable);
            }

            // Emailing the invoice IS sending it (TASK-480). The email itself
            // is the customer's notice, so InvoiceReady is not fired here --
            // that would be a second "invoice ready" mail on their doorstep.
            $this->invoice->markSent();

            session()->flash('success', __('Invoice email sent successfully to :count recipient(s).', ['count' => count($toEmails)]));
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', __('Failed to send email: :message', ['message' => $e->getMessage()]));
            Log::error('Invoice email send failed', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.invoice-email-form');
    }
}
