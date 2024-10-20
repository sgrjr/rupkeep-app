<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Organization;

class UserLog extends Model
{
    use HasFactory;
    public $timestamps = true;
    public $fillable = [
        'job_id','car_driver_id','truck_driver_id','vehicle_id','pretrip_check', 'truck_no','trailer_no','start_mileage','end_mileage','start_job_mileage','end_job_mileage','load_canceled','extra_charge','is_deadhead','extra_load_stops_count','wait_time_hours','tolls','gas','hotel','memo','maintenance_memo', 'started_at','ended_at','organization_id'
    ];

    public function attachments(){
        //TODO: has many Attachment: Upload Receipts/Invoices	
    }

    public function getTotalBillableMilesAttribute(){
        //TODO: Calculate Total Billable Miles	
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }
}
