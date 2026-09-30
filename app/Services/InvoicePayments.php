<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record a payment against an invoice (TASK-449).
 *
 * The payment ledger is the `payments` array inside the invoice `values`
 * blob, and the customer's account credit is a column on the customer row.
 * A payment touches both, and the old inline version in InvoicePaymentForm
 * touched them one after the other with no lock and no transaction, compared
 * unrounded floats to decide "paid", and set the overpayment on the payment
 * row after that row had already been appended, so it was never stored.
 *
 * Everything here happens inside one transaction with the invoice row (and
 * the customer row, when credit moves) locked for update:
 *
 *  - money is rounded to cents before anything is compared or stored;
 *  - a submission key makes a double-click record one payment, not two;
 *  - the cash part may be zero when account credit covers the payment;
 *  - the overpayment is the part of THIS payment beyond what was still owed,
 *    recorded on the payment row and added to the customer's credit once.
 */
class InvoicePayments
{
    /**
     * @param array{
     *     cash_amount?: float|string|null,
     *     credit_amount?: float|string|null,
     *     payment_method?: string|null,
     *     check_number?: string|null,
     *     payment_date?: string|null,
     *     notes?: string|null,
     *     recorded_by?: int|null,
     *     submission_key?: string|null,
     * } $attributes
     * @return array{payment: array<string, mixed>, duplicate: bool, overpayment: float, paid_in_full: bool, remaining_balance: float}
     *
     * @throws ValidationException when there is nothing to record or the credit is not there
     */
    public static function record(Invoice $invoice, array $attributes): array
    {
        $cash = self::cents($attributes['cash_amount'] ?? 0);
        $credit = self::cents($attributes['credit_amount'] ?? 0);
        $key = $attributes['submission_key'] ?? null;

        if ($cash < 0 || $credit < 0) {
            throw ValidationException::withMessages(['paymentAmount' => __('A payment cannot be negative.')]);
        }

        if ($cash + $credit <= 0) {
            throw ValidationException::withMessages(['paymentAmount' => __('Enter a payment amount, or apply account credit.')]);
        }

        return DB::transaction(function () use ($invoice, $attributes, $cash, $credit, $key) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            $values = is_array($locked->values) ? $locked->values : [];
            $payments = array_values(is_array($values['payments'] ?? null) ? $values['payments'] : []);

            // Same form, same click, second request: hand back the payment
            // the first request wrote and change nothing.
            if ($key !== null && $key !== '') {
                foreach ($payments as $existing) {
                    if (($existing['submission_key'] ?? null) === $key) {
                        $invoice->setRawAttributes($locked->getAttributes(), true);

                        return [
                            'payment' => $existing,
                            'duplicate' => true,
                            'overpayment' => self::cents($existing['overpayment'] ?? 0),
                            'paid_in_full' => (bool) $locked->paid_in_full,
                            'remaining_balance' => $locked->remaining_balance,
                        ];
                    }
                }
            }

            // Locked whether or not credit is being spent: an overpayment
            // writes to it too.
            $customer = $locked->customer_id
                ? Customer::query()->whereKey($locked->customer_id)->lockForUpdate()->first()
                : null;

            if ($credit > 0) {
                $available = self::cents($customer?->account_credit ?? 0);

                if ($credit > $available) {
                    throw ValidationException::withMessages([
                        'creditAmount' => __('The customer only has :amount in account credit.', [
                            'amount' => \App\Support\Money::currency($available),
                        ]),
                    ]);
                }
            }

            $totalDue = self::cents($locked->calculateLateFees()['total_with_late_fees'] ?? 0);
            $paidBefore = self::cents(array_sum(array_map(fn ($p) => (float) ($p['amount'] ?? 0), $payments)));
            $owedBefore = max(0.0, self::cents($totalDue - $paidBefore));

            $amount = self::cents($cash + $credit);
            $overpayment = max(0.0, self::cents($amount - $owedBefore));

            $payment = [
                'amount' => $amount,
                'cash_amount' => $cash,
                'credit_amount' => $credit,
                'used_credit' => $credit > 0,
                'payment_method' => $attributes['payment_method'] ?? null,
                'check_number' => $attributes['check_number'] ?? null,
                'payment_date' => ($attributes['payment_date'] ?? null) ?: now()->format('Y-m-d'),
                'notes' => $attributes['notes'] ?? null,
                'recorded_by' => $attributes['recorded_by'] ?? null,
                'recorded_at' => now()->toDateTimeString(),
            ];

            if ($key !== null && $key !== '') {
                $payment['submission_key'] = $key;
            }

            if ($overpayment > 0) {
                $payment['overpayment'] = $overpayment;
                $payment['overpayment_added_to_credit'] = $customer !== null;
            }

            $payments[] = $payment;
            $values['payments'] = $payments;

            $paidAfter = self::cents($paidBefore + $amount);
            $values['total_paid'] = $paidAfter;

            $locked->values = $values;

            if ($paidAfter >= $totalDue) {
                $locked->paid_in_full = true;
            }

            $locked->save();

            if ($customer !== null && ($credit > 0 || $overpayment > 0)) {
                $customer->account_credit = self::cents((float) $customer->account_credit - $credit + $overpayment);
                $customer->save();
            }

            // Leave the caller's instance looking like what was written.
            $invoice->setRawAttributes($locked->getAttributes(), true);
            $invoice->unsetRelation('customer');

            return [
                'payment' => $payment,
                'duplicate' => false,
                'overpayment' => $overpayment,
                'paid_in_full' => (bool) $locked->paid_in_full,
                'remaining_balance' => max(0.0, self::cents($totalDue - $paidAfter)),
            ];
        });
    }

    public static function cents(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) ($value ?? 0)), 2);
    }
}
