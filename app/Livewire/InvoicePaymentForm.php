<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Customer;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

class InvoicePaymentForm extends Component
{
    public Invoice $invoice;
    public $showModal = false;

    #[Validate('required|numeric|min:0.01', message: 'Payment amount is required and must be greater than 0.')]
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
        $this->validate();

        $paymentAmount = (float) $this->paymentAmount;
        $creditAmount = $this->useAccountCredit ? (float) ($this->creditAmount ?? 0) : 0;
        $totalPayment = $paymentAmount + $creditAmount;

        if ($totalPayment <= 0) {
            $this->addError('paymentAmount', 'Total payment (cash + credit) must be greater than 0.');
            return;
        }

        // Check if customer has enough credit
        if ($this->useAccountCredit && $creditAmount > 0) {
            $availableCredit = (float) $this->invoice->customer->account_credit;
            if ($creditAmount > $availableCredit) {
                $this->addError('creditAmount', "Customer only has $" . number_format($availableCredit, 2) . " in account credit.");
                return;
            }
        }

        // Get current payments
        $values = $this->invoice->values ?? [];
        $payments = $values['payments'] ?? [];

        // Calculate remaining balance
        $lateFees = $this->invoice->calculateLateFees();
        $totalDue = $lateFees['total_with_late_fees'];
        $totalPaid = array_sum(array_column($payments, 'amount'));
        $remainingBalance = max(0, $totalDue - $totalPaid);

        // Check if payment exceeds remaining balance
        if ($totalPayment > $remainingBalance) {
            $overpayment = $totalPayment - $remainingBalance;
            // Allow overpayment - it becomes account credit
        }

        // Record payment
        $payment = [
            'amount' => $totalPayment,
            'cash_amount' => $paymentAmount,
            'credit_amount' => $creditAmount,
            'used_credit' => $this->useAccountCredit && $creditAmount > 0,
            'payment_method' => $this->paymentMethod,
            'check_number' => $this->checkNumber,
            'payment_date' => $this->paymentDate ?: now()->format('Y-m-d'),
            'notes' => $this->notes,
            'recorded_by' => Auth::id(),
            'recorded_at' => now()->toDateTimeString(),
        ];

        $payments[] = $payment;
        $values['payments'] = $payments;

        // Update total paid
        $newTotalPaid = array_sum(array_column($payments, 'amount'));
        $values['total_paid'] = $newTotalPaid;

        // Update paid_in_full status
        if ($newTotalPaid >= $totalDue) {
            $this->invoice->paid_in_full = true;
        }

        // Update customer account credit
        if ($this->useAccountCredit && $creditAmount > 0) {
            $customer = $this->invoice->customer;
            $customer->account_credit = max(0, (float) $customer->account_credit - $creditAmount);
            $customer->save();
        }

        // Handle overpayment - add to customer credit
        if ($newTotalPaid > $totalDue) {
            $overpayment = $newTotalPaid - $totalDue;
            $customer = $this->invoice->customer;
            $customer->account_credit = ((float) $customer->account_credit) + $overpayment;
            $customer->save();
            $payment['overpayment'] = $overpayment;
            $payment['overpayment_added_to_credit'] = true;
        }

        // Save invoice
        $this->invoice->values = $values;
        $this->invoice->save();

        session()->flash('success', __('Payment recorded successfully.'));
        $this->closeModal();
        $this->dispatch('payment-recorded');
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
