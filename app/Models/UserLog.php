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
use Illuminate\Database\Eloquent\Relations\MorphMany;

class UserLog extends Model
{
    use HasFactory;
    public $timestamps = true;
    public $fillable = [
        'job_id','car_driver_id','truck_driver_id','vehicle_id','vehicle_position','pretrip_check', 'truck_no','trailer_no','start_mileage','end_mileage','start_job_mileage','end_job_mileage','load_canceled','extra_charge','is_deadhead','extra_load_stops_count','wait_time_hours','tolls','gas','hotel','memo','maintenance_memo', 'started_at','ended_at','organization_id','billable_miles'
    ];

    protected static function booted(): void
    {
        static::created(function (self $log): void {
            $log->loadMissing('job', 'user');

            if ($log->job && $log->user) {
                event(new JobAssigned($log->job, $log->user));
            }
        });

        static::updated(function (self $log): void {
            if ($log->wasChanged('car_driver_id')) {
                $log->loadMissing('job', 'user');

                if ($log->job && $log->user) {
                    event(new JobAssigned($log->job, $log->user));
                }
            }
        });
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
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
}
