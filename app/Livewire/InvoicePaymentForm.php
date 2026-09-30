<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Services\InvoicePayments;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class InvoicePaymentForm extends Component
{
    public Invoice $invoice;
    public $showModal = false;

    /**
     * Minted when the modal opens and sent with the submit. A double-click
     * sends two requests carrying the same key; the second records nothing
     * (TASK-449 b).
     */
    public string $submissionKey = '';

    // Zero is allowed: a payment can be made entirely from account credit
    // (TASK-449 c). The total (cash + credit) still has to be positive.
    #[Validate('nullable|numeric|min:0', message: 'Payment amount must be a number of at least 0.')]
    public $paymentAmount = '';

    #[Validate('nullable|string|max:255')]
    public $paymentMethod = '';

    #[Validate('nullable|string|max:255')]
    public $checkNumber = '';

    #[Validate('nullable|date')]
    public $paymentDate = '';

    #[Validate('nullable|string|max:1000')]
    public $notes = '';

    #[Validate('nullable|boolean')]
    public $useAccountCredit = false;

    #[Validate('nullable|numeric|min:0')]
    public $creditAmount = '';

    public function mount(Invoice $invoice)
    {
        // Recording a payment is editing the invoice (TASK-438).
        $this->authorize('update', $invoice);

        $this->invoice = $invoice;
        $this->paymentDate = now()->format('Y-m-d');
        $this->loadAvailableCredit();
    }

    public function boot()
    {
        $this->listeners['open-invoice-payment-modal-' . $this->invoice->id] = 'openModal';
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->resetForm();
        $this->loadAvailableCredit();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm()
    {
        $this->submissionKey = (string) Str::uuid();
        $this->paymentAmount = '';
        $this->paymentMethod = '';
        $this->checkNumber = '';
        $this->paymentDate = now()->format('Y-m-d');
        $this->notes = '';
        $this->useAccountCredit = false;
        $this->creditAmount = '';
        $this->resetValidation();
    }

    protected function loadAvailableCredit()
    {
        if ($this->invoice->customer) {
            $this->creditAmount = (string) max(0, (float) $this->invoice->customer->account_credit);
        }
    }

    public function updatedUseAccountCredit($value)
    {
        if ($value) {
            $this->loadAvailableCredit();
        } else {
            $this->creditAmount = '';
        }
    }

    public function applyPayment()
    {
        $this->authorize('update', $this->invoice);

        if ($this->invoice->isVoid()) {
            $this->addError('paymentAmount', __('A void invoice is not owed; nothing to pay.'));

            return;
        }

        $this->validate();

        try {
            $result = InvoicePayments::record($this->invoice, [
                'cash_amount' => $this->paymentAmount,
                'credit_amount' => $this->useAccountCredit ? $this->creditAmount : 0,
                'payment_method' => $this->paymentMethod ?: null,
                'check_number' => $this->checkNumber ?: null,
                'payment_date' => $this->paymentDate ?: null,
                'notes' => $this->notes ?: null,
                'recorded_by' => Auth::id(),
                'submission_key' => $this->submissionKey ?: (string) Str::uuid(),
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? __('The payment could not be recorded.'));
            }

            return;
        }

        if ($result['duplicate']) {
            session()->flash('info', __('That payment was already recorded.'));
        } else {
            $parts = [__('Payment of :amount recorded.', ['amount' => Money::currency($result['payment']['amount'])])];

            if ($result['overpayment'] > 0) {
                $parts[] = __(':amount over the balance was added to the customer\'s account credit.', [
                    'amount' => Money::currency($result['overpayment']),
                ]);
            }

            $parts[] = $result['paid_in_full']
                ? __('The invoice is paid in full.')
                : __('Remaining balance :amount.', ['amount' => Money::currency($result['remaining_balance'])]);

            session()->flash('success', implode(' ', $parts));
        }

        // The totals and the payments table around this modal are rendered by
        // the page, not by this component, so a re-render here would leave
        // them stale (TASK-449 g). Reload the page; the layout shows the flash.
        $this->closeModal();

        return $this->redirectRoute('my.invoices.edit', ['invoice' => $this->invoice->id]);
    }

    public function render()
    {
        $lateFees = $this->invoice->calculateLateFees();
        $totalDue = $lateFees['total_with_late_fees'];
        $totalPaid = $this->invoice->total_paid;
        $remainingBalance = $this->invoice->remaining_balance;
        $availableCredit = $this->invoice->customer ? (float) $this->invoice->customer->account_credit : 0;

        return view('livewire.invoice-payment-form', [
            'totalDue' => $totalDue,
            'totalPaid' => $totalPaid,
            'remainingBalance' => $remainingBalance,
            'availableCredit' => $availableCredit,
        ]);
    }
}
