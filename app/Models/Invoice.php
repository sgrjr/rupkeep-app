<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\Customer;
use App\Models\InvoiceComment;
use App\Models\Attachment;
use App\Models\UserLog;
use App\Models\PricingSetting;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'paid_in_full',
        'status',
        'sent_at',
        'paid_at',
        'replaces_invoice_id',
        'marked_for_attention',
        'values',
        'organization_id',
        'customer_id',
        'pilot_car_job_id',
        'parent_invoice_id',
        'invoice_type',
    ];

    /**
     * The lifecycle (TASK-480).
     *
     *   draft -> sent -> paid
     *     \       \
     *      +-------+--> void
     *
     * draft  Staff only. Excluded from the portal, InvoiceReady, exports and
     *        summaries' visibility to the customer. Fully editable;
     *        regenerating replaces it in place.
     * sent   In the customer's hands. Editing asks for confirmation and is
     *        recorded on the invoice thread.
     * paid   Balance reached zero (payment form) or marked paid. Kept in step
     *        with the legacy paid_in_full flag both ways, see booted().
     * void   Replaces deletion everywhere. Excluded from totals, the
     *        dashboard, exports and summary parents. The row stays, so the
     *        number is never reused and the history is never lost.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_PAID, self::STATUS_VOID];

    /** The statuses a customer may see: what was actually billed to them. */
    public const CUSTOMER_VISIBLE_STATUSES = [self::STATUS_SENT, self::STATUS_PAID];

    public $timestamps = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_in_full' => 'boolean',
            'marked_for_attention' => 'boolean',
            'values' => 'array',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // `paid_in_full` predates `status` and is read in dozens of places
        // (observer job sync, exports, the portal, the dashboard). Rather than
        // migrate every reader at once, the two are kept in step here, in
        // whichever direction the write came from. A void invoice is never
        // "paid" -- its money is not owed -- so void wins.
        static::saving(function (self $invoice): void {
            if ($invoice->status === null) {
                $invoice->status = $invoice->paid_in_full ? self::STATUS_PAID : self::STATUS_DRAFT;
            }

            if ($invoice->status === self::STATUS_VOID) {
                $invoice->paid_in_full = false;

                return;
            }

            if (! $invoice->exists) {
                // On creation every attribute is "dirty", so neither side can
                // be taken as the write that happened. Legacy callers (CSV
                // import, the factory, older tests) say paid_in_full and let
                // status default; a paid invoice is paid whatever the default.
                if ($invoice->paid_in_full) {
                    $invoice->status = self::STATUS_PAID;
                } else {
                    $invoice->paid_in_full = $invoice->status === self::STATUS_PAID;
                }
            } elseif ($invoice->isDirty('status')) {
                $invoice->paid_in_full = $invoice->status === self::STATUS_PAID;
            } elseif ($invoice->isDirty('paid_in_full')) {
                if ($invoice->paid_in_full) {
                    $invoice->status = self::STATUS_PAID;
                } elseif ($invoice->status === self::STATUS_PAID) {
                    // Un-marking a paid invoice puts it back in the customer's
                    // hands, not back on the drafting table.
                    $invoice->status = self::STATUS_SENT;
                }
            }

            if ($invoice->status === self::STATUS_PAID && ! $invoice->paid_at) {
                $invoice->paid_at = now();
            }

            if ($invoice->status !== self::STATUS_DRAFT && ! $invoice->sent_at) {
                $invoice->sent_at = now();
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /** Sent or paid: the customer may see it and it counts toward what they owe. */
    public function isVisibleToCustomer(): bool
    {
        return in_array($this->status, self::CUSTOMER_VISIBLE_STATUSES, true);
    }

    public function scopeNotVoid($query)
    {
        return $query->where('status', '!=', self::STATUS_VOID);
    }

    /**
     * The invoices that carry money once: not a summary (its total is its
     * children's) and not a child of one (billed through the summary). The
     * dashboard's revenue, unpaid count and outstanding amount all read this
     * set, so they cannot disagree (TASK-472).
     */
    public function scopeSingle($query)
    {
        return $query->where('invoice_type', '!=', self::TYPE_SUMMARY_VALUE)->whereNull('parent_invoice_id');
    }

    /** Value of invoice_type on a summary invoice. */
    public const TYPE_SUMMARY_VALUE = 'summary';

    /**
     * SUM of values->total for a query, in the database. The dashboard used
     * to load every invoice into memory to add them up (TASK-472).
     */
    public static function sumTotals($query): float
    {
        $grammar = $query->getQuery()->getGrammar();
        $driver = $query->getQuery()->getConnection()->getDriverName();
        $total = $grammar->wrap('values->total');
        $type = $driver === 'sqlite' ? 'REAL' : 'DECIMAL(14,2)';

        return (float) (clone $query)->selectRaw("COALESCE(SUM(CAST({$total} AS {$type})), 0) as aggregate")->value('aggregate');
    }

    public function scopeVisibleToCustomer($query)
    {
        return $query->whereIn('status', self::CUSTOMER_VISIBLE_STATUSES);
    }

    /**
     * Put the invoice in the customer's hands. The caller fires InvoiceReady;
     * this only records the transition. Returns false when there was nothing
     * to do (already sent or paid, or void).
     */
    public function markSent(): bool
    {
        if (! $this->isDraft()) {
            return false;
        }

        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        $this->save();

        return true;
    }

    /**
     * Void replaces deletion. The row stays; every total, export, list and
     * summary parent leaves it out.
     */
    public function void(?int $byUserId = null, ?string $reason = null): bool
    {
        if ($this->isVoid()) {
            return false;
        }

        $values = $this->values ?? [];
        $values['voided'] = array_filter([
            'from_status' => $this->status,
            'reason' => $reason,
        ]);

        $this->values = $values;
        $this->status = self::STATUS_VOID;
        $this->voided_at = now();
        $this->voided_by_id = $byUserId;
        $this->save();

        // A summary's pivot rows are what make its jobs "billed" (job status,
        // the active/completed scopes, the job page). A void summary bills
        // nothing, so they go the way the old delete took them; the job
        // numbers it covered are still in the values snapshot.
        if ($this->isSummary()) {
            $this->jobs()->detach();
        }

        return true;
    }

    /**
     * Rebuild a single invoice's snapshot from its job, in place. Only a draft
     * is rebuilt this way -- a sent invoice is regenerated by voiding it and
     * drafting a replacement (MyInvoicesController::regenerate), so the
     * document the customer holds is never silently rewritten.
     */
    public function regenerateFromJob(): bool
    {
        if ($this->isSummary() || ! $this->isDraft() || ! $this->job) {
            return false;
        }

        $this->values = $this->job->fresh()->invoiceValues()['values'];
        $this->save();

        return true;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_SENT => __('Sent'),
            self::STATUS_PAID => __('Paid'),
            self::STATUS_VOID => __('Void'),
            default => (string) $this->status,
        };
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function job(){
        return $this->belongsTo(PilotCarJob::class, 'pilot_car_job_id');
    }

    /**
     * Get all jobs linked to this invoice via pivot table.
     * 
     * Only used for summary invoices that link to multiple jobs.
     * Single invoices use the job() relationship via pilot_car_job_id.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function jobs()
    {
        return $this->belongsToMany(PilotCarJob::class, 'summary_invoice_jobs');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_invoice_id');
    }

    public function comments()
    {
        return $this->hasMany(InvoiceComment::class);
    }

    public function publicProofAttachments()
    {
        if ($this->isSummary()) {
            return $this->children()
                ->with('job.attachments', 'job.logs.attachments')
                ->get()
                ->flatMap(fn (Invoice $child) => $child->publicProofAttachments())
                ->unique('id')
                ->values();
        }

        $job = $this->job;

        if (!$job) {
            return collect();
        }

        $attachments = $job->attachments()
            ->where('is_public', true)
            ->get();

        $logIds = $job->logs()->pluck('id');

        if ($logIds->isNotEmpty()) {
            $logAttachments = Attachment::query()
                ->where('is_public', true)
                ->where('attachable_type', UserLog::class)
                ->whereIn('attachable_id', $logIds)
                ->get();

            $attachments = $attachments->merge($logAttachments);
        }

        return $attachments;
    }

    public function getInvoiceNumberAttribute(){
        return substr($this->created_at,0,4) . str_pad((String)$this->id, 5, "0", STR_PAD_LEFT );
    }

    public function isSummary(): bool
    {
        return $this->invoice_type === 'summary';
    }

    /**
     * A payment-terms setting for this invoice's organization, falling back to
     * the config default when the invoice has no organization (unit tests,
     * imported rows).
     */
    private function paymentTermsSetting(string $key, mixed $default): mixed
    {
        $configured = config('pricing.payment_terms.' . $key, $default);

        return $this->organization_id
            ? PricingSetting::getValueForOrganization($this->organization_id, 'payment_terms.' . $key, $configured)
            : $configured;
    }

    /**
     * The late-fee position of this invoice.
     *
     * `values.total` is always the invoice amount BEFORE any late fee. An
     * applied fee lives beside it in `values.late_fees` and is added on top at
     * read time; it is never folded into `total`. applyLateFees() used to write
     * total = total + fee, so every later read (Total Due, remaining balance,
     * the print and PDF templates, the paid-in-full test) added the saved fee a
     * second time, and re-applying compounded it (TASK-443).
     *
     * The fee shown is made of two parts:
     *  - the APPLIED fee: locked onto the invoice by "Apply to Invoice", with
     *    the number of periods it covered. Fixed from then on, whatever later
     *    happens to the rate settings or the invoice total.
     *  - the ADDITIONAL fee: what has accrued in periods the applied fee does
     *    not cover (before any apply, everything accrued so far). Due, but not
     *    yet locked; "Apply" folds it into the applied fee.
     *
     * A child rolled into a summary invoice accrues nothing of its own -- the
     * summary is the document the customer pays and it accrues on its own
     * date. A paid invoice accrues nothing more either. In both cases a fee
     * that was already applied is still honoured, so Total Due keeps agreeing
     * with what was billed and paid.
     *
     * @return array{
     *   is_past_due: bool, days_overdue: int, due_date: \Carbon\Carbon,
     *   late_fee_periods: int, late_fee_amount: float, total_with_late_fees: float,
     *   late_fees_applied: bool, applied_at: ?string,
     *   applied_late_fee_amount: float, applied_late_fee_periods: int,
     *   additional_late_fee_amount: float, additional_late_fee_periods: int,
     *   late_fee_base: float, late_fee_percentage: float
     * }
     */
    public function calculateLateFees(): array
    {
        $invoiceDate = $this->created_at ?? now();
        $gracePeriod = (int) $this->paymentTermsSetting('grace_period_days', 30);
        $lateFeePercentage = (float) $this->paymentTermsSetting('late_fee_percentage', 10.0);
        $lateFeePeriodDays = max(1, (int) $this->paymentTermsSetting('late_fee_period_days', 30));

        $values = $this->values ?? [];
        $total = (float) ($values['total'] ?? 0);

        $applied = is_array($values['late_fees'] ?? null) ? $values['late_fees'] : [];
        $appliedAt = $applied['applied_at'] ?? null;
        $appliedAmount = $appliedAt ? round((float) ($applied['late_fee_amount'] ?? 0), 2) : 0.0;
        $appliedPeriods = $appliedAt ? (int) ($applied['late_fee_periods'] ?? 0) : 0;

        $result = [
            'due_date' => $invoiceDate->copy()->addDays($gracePeriod),
            'late_fees_applied' => (bool) $appliedAt,
            'applied_at' => $appliedAt,
            'applied_late_fee_amount' => $appliedAmount,
            'applied_late_fee_periods' => $appliedPeriods,
            'late_fee_base' => $total,
            'late_fee_percentage' => $lateFeePercentage,
        ];

        // Settled, void, or billed through a summary: nothing more accrues.
        // What was applied stays on the invoice so the figures the customer
        // saw and paid still add up.
        if ($this->paid_in_full || $this->isVoid() || $this->parent_invoice_id) {
            return $result + [
                'is_past_due' => false,
                'days_overdue' => 0,
                'late_fee_periods' => $appliedPeriods,
                'late_fee_amount' => $appliedAmount,
                'additional_late_fee_amount' => 0.0,
                'additional_late_fee_periods' => 0,
                'total_with_late_fees' => round($total + $appliedAmount, 2),
            ];
        }

        // Carbon 3 returns a float here where Carbon 2 returned an int, so the
        // fractional time-of-day leaked all the way to the screen as
        // "60.000032710208 days overdue". Floor rather than round: never
        // overstate how late a customer is.
        $daysSinceInvoice = (int) floor($invoiceDate->diffInDays(now()));
        $isPastDue = $daysSinceInvoice > $gracePeriod;
        $daysOverdue = max(0, $daysSinceInvoice - $gracePeriod);
        $accruedPeriods = $isPastDue ? intdiv($daysOverdue, $lateFeePeriodDays) : 0;

        // Only periods the applied fee does not already cover are charged
        // again -- this is what stops a re-apply from compounding.
        $additionalPeriods = max(0, $accruedPeriods - $appliedPeriods);
        $additionalAmount = round($total * ($lateFeePercentage / 100) * $additionalPeriods, 2);
        $lateFeeAmount = round($appliedAmount + $additionalAmount, 2);

        return $result + [
            'is_past_due' => $isPastDue,
            'days_overdue' => $daysOverdue,
            'late_fee_periods' => $appliedPeriods + $additionalPeriods,
            'late_fee_amount' => $lateFeeAmount,
            'additional_late_fee_amount' => $additionalAmount,
            'additional_late_fee_periods' => $additionalPeriods,
            'total_with_late_fees' => round($total + $lateFeeAmount, 2),
        ];
    }

    /**
     * Get payment status information
     */
    public function getPaymentStatusAttribute(): array
    {
        return $this->calculateLateFees();
    }

    /**
     * Get all payments recorded for this invoice
     */
    public function getPayments(): array
    {
        return $this->values['payments'] ?? [];
    }

    /**
     * Get total amount paid on this invoice
     */
    public function getTotalPaidAttribute(): float
    {
        $payments = $this->getPayments();
        return array_sum(array_column($payments, 'amount'));
    }

    /**
     * Get remaining balance (total with late fees - total paid)
     */
    public function getRemainingBalanceAttribute(): float
    {
        // Marked paid from the dropdown, or void: nothing is owed, whatever
        // the recorded payments add up to (TASK-449 e).
        if ($this->paid_in_full || $this->isVoid()) {
            return 0.0;
        }

        $lateFees = $this->calculateLateFees();
        $totalDue = $lateFees['total_with_late_fees'];
        $totalPaid = $this->total_paid;
        return max(0, $totalDue - $totalPaid);
    }

    /**
     * Get account credit used for this invoice
     */
    public function getAccountCreditUsedAttribute(): float
    {
        $payments = $this->getPayments();
        return array_sum(array_column(array_filter($payments, fn($p) => $p['used_credit'] ?? false), 'credit_amount'));
    }

    /**
     * Generate a "Description of Work" string from address data
     * 
     * @param array|string|null $pickupAddress
     * @param array|string|null $deliveryAddress
     * @return string
     */
    public static function generateDescriptionOfWork($pickupAddress = null, $deliveryAddress = null): string
    {
        $pickup = self::extractCityState($pickupAddress);
        $delivery = self::extractCityState($deliveryAddress);

        if ($pickup && $delivery) {
            return $pickup . ' To ' . $delivery;
        } elseif ($pickup) {
            return $pickup;
        } elseif ($delivery) {
            return $delivery;
        }

        return '—';
    }

    /**
     * Extract city and state from an address string
     * 
     * @param array|string|null $address
     * @return string|null
     */
    protected static function extractCityState($address): ?string
    {
        if (empty($address)) {
            return null;
        }

        // If it's an array with city/state, use those
        if (is_array($address)) {
            $city = $address['city'] ?? null;
            $state = $address['state'] ?? null;
            if ($city && $state) {
                return $city . ', ' . $state;
            } elseif ($city) {
                return $city;
            }
            return null;
        }

        // If it's a string, try to extract city and state
        $addressString = trim($address);
        if (empty($addressString)) {
            return null;
        }

        // Try to match patterns like "City, State" or "City, State ZIP"
        // Common patterns:
        // - "City, State"
        // - "City, State ZIP"
        // - "123 Street, City, State ZIP"
        if (preg_match('/([^,]+),\s*([A-Z]{2})(?:\s+\d{5})?$/', $addressString, $matches)) {
            return trim($matches[1]) . ', ' . $matches[2];
        }

        // If no pattern matches, try to extract the last two comma-separated parts
        $parts = array_map('trim', explode(',', $addressString));
        if (count($parts) >= 2) {
            // Check if last part looks like a state (2 letters) or state + zip
            $lastPart = end($parts);
            if (preg_match('/^([A-Z]{2})(?:\s+\d{5})?$/', $lastPart, $stateMatch)) {
                $city = $parts[count($parts) - 2];
                return $city . ', ' . $stateMatch[1];
            }
        }

        // Fallback: return the address as-is (might be just a city name)
        return $addressString;
    }
}
