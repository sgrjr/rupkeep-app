<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Attachment;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class UserLog extends Model
{
    use HasFactory;
    public $timestamps = true;
    public $fillable = [
        'job_id','car_driver_id','truck_driver_id','vehicle_id','vehicle_position','pretrip_check', 'truck_no','trailer_no','start_mileage','end_mileage','start_job_mileage','end_job_mileage','load_canceled','extra_charge','is_deadhead','extra_load_stops_count','wait_time_hours','tolls','gas','hotel','memo','maintenance_memo', 'started_at','ended_at','organization_id','billable_miles'
    ];

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getTotalBillableMilesAttribute(){
        //TODO: Calculate Total Billable Miles	
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function job(){
        return $this->belongsTo(PilotCarJob::class, 'job_id');
    }

    public function vehicle(){
        return $this->belongsTo(Vehicle::class);
    }

    public function user(){
        return $this->belongsTo(User::class, 'car_driver_id');
    }

    public function truck_driver(){
        return $this->belongsTo(CustomerContact::class, 'truck_driver_id');
    }
}
