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
        'marked_for_attention',
        'values',
        'organization_id',
        'customer_id',
        'pilot_car_job_id',
        'parent_invoice_id',
        'invoice_type',
    ];

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
        ];
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

    public function jobs()
    {
        return $this->belongsToMany(PilotCarJob::class, 'jobs_invoices');
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
     * Calculate late fees based on payment terms
     * 
     * @return array ['is_past_due' => bool, 'days_overdue' => int, 'late_fee_periods' => int, 'late_fee_amount' => float, 'total_with_late_fees' => float]
     */
    public function calculateLateFees(): array
    {
        $invoiceDate = $this->created_at;
        $organizationId = $this->organization_id;
        $gracePeriod = $organizationId
            ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.grace_period_days', config('pricing.payment_terms.grace_period_days', 30))
            : config('pricing.payment_terms.grace_period_days', 30);
        
        if ($this->paid_in_full) {
            return [
                'is_past_due' => false,
                'days_overdue' => 0,
                'late_fee_periods' => 0,
                'late_fee_amount' => 0.0,
                'total_with_late_fees' => (float) ($this->values['total'] ?? 0),
                'due_date' => $invoiceDate->copy()->addDays($gracePeriod),
                'late_fees_applied' => false,
            ];
        }

        $now = now();
        $daysSinceInvoice = $invoiceDate->diffInDays($now);
        $lateFeePercentage = $organizationId
            ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.late_fee_percentage', config('pricing.payment_terms.late_fee_percentage', 10.0))
            : config('pricing.payment_terms.late_fee_percentage', 10.0);
        $lateFeePeriodDays = $organizationId
            ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.late_fee_period_days', config('pricing.payment_terms.late_fee_period_days', 30))
            : config('pricing.payment_terms.late_fee_period_days', 30);
        
        $isPastDue = $daysSinceInvoice > $gracePeriod;
        $daysOverdue = max(0, $daysSinceInvoice - $gracePeriod);
        
        // Calculate number of 30-day periods overdue
        $lateFeePeriods = $isPastDue ? (int) floor($daysOverdue / $lateFeePeriodDays) : 0;
        
        // Check if late fees have already been applied to the invoice total
        $lateFeesApplied = data_get($this->values, 'late_fees.applied_at');
        $originalTotal = (float) data_get($this->values, 'late_fees.original_total', 0);
        $currentTotal = (float) ($this->values['total'] ?? 0);
        
        // Use original total if late fees were already applied, otherwise use current total
        $baseTotal = $lateFeesApplied && $originalTotal > 0 ? $originalTotal : $currentTotal;
        
        $lateFeeAmount = 0.0;
        
        if ($lateFeePeriods > 0) {
            if ($lateFeesApplied && $originalTotal > 0) {
                // Late fees already applied - calculate additional fees if more time has passed
                $savedLateFeeAmount = (float) data_get($this->values, 'late_fees.late_fee_amount', 0);
                $savedPeriods = (int) data_get($this->values, 'late_fees.late_fee_periods', 0);
                
                // Calculate what the late fee should be now based on current periods
                $currentLateFeeAmount = $originalTotal * (($lateFeePercentage / 100) * $lateFeePeriods);
                
                // Show additional fees if more periods have passed
                if ($lateFeePeriods > $savedPeriods) {
                    $lateFeeAmount = $currentLateFeeAmount - $savedLateFeeAmount; // Additional fees due
                } else {
                    $lateFeeAmount = $savedLateFeeAmount; // Use saved amount
                }
            } else {
                // Calculate new late fees based on base total
                $lateFeeAmount = $baseTotal * (($lateFeePercentage / 100) * $lateFeePeriods);
            }
        }
        
        // Total with late fees: current total + additional late fees (if any)
        $totalWithLateFees = $lateFeesApplied ? ($currentTotal + $lateFeeAmount) : ($baseTotal + $lateFeeAmount);
        
        return [
            'is_past_due' => $isPastDue,
            'days_overdue' => $daysOverdue,
            'late_fee_periods' => $lateFeePeriods,
            'late_fee_amount' => round($lateFeeAmount, 2),
            'total_with_late_fees' => round($totalWithLateFees, 2),
            'due_date' => $invoiceDate->copy()->addDays($gracePeriod),
            'late_fees_applied' => (bool) $lateFeesApplied,
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
