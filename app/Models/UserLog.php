<?php

namespace App\Models;

use App\Events\JobAssigned;
use App\Models\Attachment;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLog extends Model
{
    use HasFactory, SoftDeletes;
    public $timestamps = true;
    public $fillable = [
        'job_id','car_driver_id','truck_driver_id','vehicle_id','vehicle_position','pretrip_check', 'truck_no','trailer_no','start_mileage','end_mileage','start_job_mileage','end_job_mileage','load_canceled','extra_charge','is_deadhead','extra_load_stops_count','wait_time_hours','tolls','gas','hotel','memo','maintenance_memo', 'started_at','ended_at','organization_id','billable_miles','dead_head_driven','dead_head_billed','approval_status','approved_at','approved_by_id','clock_in','clock_out','completed_at','completed_by_id'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $log): void {
            $log->loadMissing('job', 'user');

            if ($log->job && $log->user) {
                event(new JobAssigned($log->job, $log->user, $log));
            }
        });

        static::updated(function (self $log): void {
            if ($log->wasChanged('car_driver_id')) {
                $log->loadMissing('job', 'user');

                if ($log->job && $log->user) {
                    event(new JobAssigned($log->job, $log->user, $log));
                }
            }
        });
    }

    /**
     * Has the driver signalled they are finished with this log? (TASK-364)
     */
    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Named ad-hoc charges on this log (TASK-330). These replaced the single
     * unlabeled `extra_charge` column and each becomes its own invoice line.
     */
    public function extraCharges(): HasMany
    {
        return $this->hasMany(LogExtraCharge::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getTotalBillableMilesAttribute()
    {
        // If billable_miles is manually set, use that
        if ($this->billable_miles !== null && $this->billable_miles !== '' && is_numeric($this->billable_miles)) {
            return (float) $this->billable_miles;
        }

        // Otherwise calculate from start/end job mileage
        if ($this->start_job_mileage !== null && $this->end_job_mileage !== null) {
            $start = (float) $this->start_job_mileage;
            $end = (float) $this->end_job_mileage;
            
            if ($end >= $start) {
                return $end - $start;
            }
        }

        // Fallback: use total miles if job mileage not available
        return $this->total_miles;
    }

    
    public function getTotalMilesAttribute()
    {
        // If billable_miles is manually set, use that
        if ($this->start_mileage === null || $this->end_mileage === null) {
            return (float) 0.0;
        }

        $start = (float) $this->start_mileage;
        $end = (float) $this->end_mileage;
        
        if ($end >= $start) {
            return $end - $start;
        }

        return 0.0;
    }

    public function getPersonalMilesAttribute()
    {
        $total_miles = $this->total_miles ?? 0.0;
        $billable_miles = $this->total_billable_miles ?? 0.0;

        if ($total_miles >= $billable_miles) {
            return $total_miles  - $billable_miles;
        }
        return 0.0;
    }

    /**
     * The drive to the pickup, as the odometer describes it: everything
     * between clocking on and the job's own start reading. This is what
     * `dead_head_driven` is seeded from, and it stays available as an
     * accessor so the log form can offer it as a suggestion and so a stored
     * value that has drifted from the odometer can be spotted (TASK-354).
     *
     * Null - not zero - when the readings cannot describe an approach, so
     * "we do not know" stays distinguishable from "drove straight there".
     */
    public function getApproachMilesAttribute(): ?float
    {
        if (! $this->hasOrderedMileageReadings()) {
            return null;
        }

        return (float) $this->start_job_mileage - (float) $this->start_mileage;
    }

    /**
     * Miles driven after the job released the driver. Tracked for the mileage
     * ledger, never billable: the published price sheet covers deadhead
     * "to the pickup location only". Where the driver goes next decides what
     * these become - head home and they stay release miles, head to another
     * job and that job's own log records them as ITS approach.
     */
    public function getReleaseMilesAttribute(): ?float
    {
        if (! $this->hasOrderedMileageReadings()) {
            return null;
        }

        return (float) $this->end_mileage - (float) $this->end_job_mileage;
    }

    /**
     * Do the four odometer readings describe a coherent trip
     * (clock on -> job start -> job end -> clock off)? Production holds rows
     * where they do not, so every derived segment has to ask first.
     */
    public function hasOrderedMileageReadings(): bool
    {
        foreach (['start_mileage', 'start_job_mileage', 'end_job_mileage', 'end_mileage'] as $reading) {
            if ($this->{$reading} === null || $this->{$reading} === '' || ! is_numeric($this->{$reading})) {
                return false;
            }
        }

        return (float) $this->start_mileage <= (float) $this->start_job_mileage
            && (float) $this->start_job_mileage <= (float) $this->end_job_mileage
            && (float) $this->end_job_mileage <= (float) $this->end_mileage;
    }

    /**
     * Approach legs longer than this are odometer typos, not driving. One
     * production row implies a 190,065-mile drive to the pickup; seeding that
     * as a suggested figure would put a six-figure charge in front of someone.
     */
    public const MAX_PLAUSIBLE_APPROACH = 1000;

    /**
     * What to offer as this log's deadhead miles when nothing is recorded yet:
     * the odometer's own approach leg, but only when it is believable.
     *
     * Kept separate from `approach_miles`, which stays pure arithmetic so the
     * form can still show a wild reading as a cross-check. This one is the
     * number we are willing to put in a field on someone's behalf, so it
     * refuses to guess from readings that describe no real trip.
     */
    public function suggestedDeadHeadMiles(): ?float
    {
        $approach = $this->approach_miles;

        if ($approach === null || $approach <= 0 || $approach > self::MAX_PLAUSIBLE_APPROACH) {
            return null;
        }

        return $approach;
    }

    /**
     * The most this log's deadhead may be billed for: everything driven
     * beyond the free allowance the organization publishes. Billing is
     * opt-in, so this caps a human's decision - it is never an amount that
     * bills on its own (TASK-354).
     */
    public function deadHeadBillingCeiling(): float
    {
        return max(0.0, (float) ($this->dead_head_driven ?? 0) - $this->deadHeadFreeMiles());
    }

    /**
     * One plain sentence describing this log's deadhead, for every read-only
     * surface that shows a log (TASK-354).
     *
     * Deadhead is now two numbers with a policy between them, which is easy to
     * misread from raw figures: 17 miles driven and nothing billed looks
     * identical to 100 driven and nothing billed, but the first had nothing
     * billable in it and the second was a decision someone made. The sentence
     * says which, so no reader has to do the subtraction themselves.
     */
    public function deadHeadSummary(): string
    {
        $driven = (float) ($this->dead_head_driven ?? 0);

        if ($driven <= 0) {
            return __('No deadhead miles recorded.');
        }

        $billed = (float) ($this->dead_head_billed ?? 0);
        $free = $this->deadHeadFreeMiles();
        $ceiling = $this->deadHeadBillingCeiling();
        $n = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');

        if ($billed <= 0) {
            // Nothing billed for two very different reasons. Say which one.
            return $ceiling <= 0
                ? __(':driven deadhead miles driven, none billable (within the first :free free).', [
                    'driven' => $n($driven),
                    'free' => $n($free),
                ])
                : __(':driven deadhead miles driven, none billed (up to :ceiling could have been).', [
                    'driven' => $n($driven),
                    'ceiling' => $n($ceiling),
                ]);
        }

        $forgiven = $ceiling - $billed;

        return $forgiven > 0
            ? __(':driven deadhead miles driven, :billed billed (first :free free, :forgiven more not charged).', [
                'driven' => $n($driven),
                'billed' => $n($billed),
                'free' => $n($free),
                'forgiven' => $n($forgiven),
            ])
            : __(':driven deadhead miles driven, :billed billed (first :free free).', [
                'driven' => $n($driven),
                'billed' => $n($billed),
                'free' => $n($free),
            ]);
    }

    /**
     * The organization's published free-miles allowance, falling back to the
     * config default. This is the same lookup the public pricing page renders
     * from, so the price sheet and the invoice cannot advertise different
     * numbers.
     */
    public function deadHeadFreeMiles(): float
    {
        $default = (float) config('pricing.charges.dead_head.free_miles', 75);

        if (! $this->organization_id) {
            return $default;
        }

        return (float) PricingSetting::getValueForOrganization(
            $this->organization_id,
            'charges.dead_head.free_miles',
            $default
        );
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function job()
    {
        return $this->belongsTo(PilotCarJob::class, 'job_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'car_driver_id');
    }

    public function truck_driver()
    {
        return $this->belongsTo(CustomerContact::class, 'truck_driver_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
