<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasJobScopes;

class PilotCarJob extends Model
{
    use HasFactory, SoftDeletes, HasJobScopes;
    public $timestamps = true;
    public $fillable = [
        'job_no',
        'customer_id',
        'scheduled_pickup_at',
        'scheduled_delivery_at',
        'load_no',
        'pickup_address',
        'delivery_address',
        'check_no',
        'invoice_paid',
        'invoice_no',
        'rate_code',
        'rate_value',
        'canceled_at',
        'canceled_reason',
        'memo',
        'organization_id',
        'deleted_at'
    ];

    public function attachments(){
        //TODO: has many Attachment: Permits & Invoice	
    }

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function logs(){
        return $this->hasMany(UserLog::class, 'job_id');
    }

    public function invoices(){
        return $this->belongsToMany(Invoice::class,  'jobs_invoices');
    }

    public function getInvoicesCountAttribute(){
        return $this->invoices()->count();
    }

    public function logSchema(){
        $log = new UserLog([
            'job_id' => $this->id,
        ]);

        return $log->schema()->remove(['id'])->hide(['maintenance_memo']);
    }
    public static function import($files, $organization_id){
        //add each file details to database

        foreach ($files as $file) {
            $lines = explode(PHP_EOL, $file['contents']);
            $number = 0;
            $header = [];
            $l = [];

            foreach($lines as $line){
               
                if($number == 0){
                    $header = str_getcsv($line);
                    $h_eader = [];
                    foreach($header as $h){
                        $h_eader[] = str_replace('__','_',str_replace([' ','-'],'_',trim(str_replace(['#','(',')','/','?'],'', strtolower($h)))));
                    }
        
                    $header = static::translateHeaders($h_eader);
                }else{
                    $values = str_getcsv($line);
                    if(count($values) != 57){
                        //dd('line#: '.$number, $values, $line);
                    }else{
                        $new_values = [];
                        foreach($values as $index=>$v){
                            $new_values[$header[$index]] = $v;
                        }
    
                        $l[] = $new_values;
                    }
                   
                }
                $number++;
            }
            foreach($l as $line){
                static::processLog($line, $organization_id);
            }
                   
        }
    }

    public static function translateHeaders($headers){
        $dictionary = [
            'job_no' => ['job'],
            'load_no' => ['load'],
            'timestamp' => ['timestamp','Timestamp','229',229],
            'check_no' => ['check'],
            'invoice_paid' => ['invoice_paid'],
            'invoice_no' => ['invoice'],
            'start_date' => ['date'],
            'start_time' => ['start_time'],
            'start_mileage' => ['start_mileage'],
            'driver_of_pilot_car' => ['driver_of_pilot_car'],
            'pilot_car_name' => ['pilot_car'],
            'pretrip_check_answer' => ['did_you_pre_trip_your_vehicle_look_it_over_and_check_oil'],
            'customer_name' => ['company_name'],
            'street' => ['address'],
            'city' => ['city'],
            'state' => ['state'],
            'zip_code' => ['zip_code'],
            'truck_driver_name' => ['truck_driver_name'],
            'truck_no' => ['truck'],
            'trailer_no' => ['trailer'],
            'pickup_address' => ['load_pickup_address'],
            'delivery_address' => ['load_deliver_address'],
            'start_job_mileage' => ['start_job_mileage'],
            'load_canceled' => ['load_canceled'],
            'is_deadhead' => ['is_this_a_dead_head_run_to_manh_line'],
            'extra_load_stops_count' => ['extra_load_stops'],
            'wait_time_hours' => ['wait_time'],
            'wait_time_reason' => ['trip_notes_reason_for_wait_time'],
            'end_job_mileage' => ['end_job_mileage'],
            'total_billable_miles' => ['total_billable_miles'],
            'tolls' => ['tolls'],
            'gas' => ['gas'],
            'upload_receiptsinvoices' => ['upload_receiptsinvoices',"upload_receipts\ninvoices","upload_receipts"],
            'end_mileage' => ['end_mileage'],
            'maintenance_memo' => ['any_questions_or_concerns_with_the_vehicle_any_maintenance_required'],
            'end_time' => ['end_time'],
            'hotel' => ['hotel_stay'],
            'total_hours_worked' => ['total_hours_worked'],
            'if_load_canceled' => ['if_load_canceled'],
            'cost_of_extra_stop' => ['cost_of_extra_stop'],
            'cost_of_wait_time' => ['cost_of_wait_time'],
            'total_job_mileage' => ['total_job_mileage'],
            'mini_mileage_range' => ['mini_mileage_range'],
            'price_per_mile' => ['price_per_mile'],
            'canceled_reason' => ['job_description1'],
            'was_mini' => ['job_descripton2'],
            'mini_cost' => ['mini_cost'],
            'extra_charge' => ['extra_charge'],
            'dead_head_charge' => ['dead_head_charge'],
            'cost_for_mileage' => ['cost_for_mileage'],
            'subtotal_mileage_cost' => ['subtotal_mileage_cost'],
            'total_cost' => ['total_cost'],
            'total_vehicle_miles' => ['total_vehicle_miles'],
            'merged_doc_id__invoice_2024' => ['merged_doc_id__invoice_2024'],
            'job_memo' => ['merged_doc_url__invoice_2024'],
            'link_to_merged_doc__invoice_2024' => ['link_to_merged_doc__invoice_2024'],
            'document_merge_status__invoice_2024' => ['document_merge_status__invoice_2024']
        ];

        $values = [];
        foreach($headers as $hdr){
            $value = collect($dictionary)->filter(fn($entry)=> in_array($hdr, $entry))->keys()->first();

            if($value){
                $values[] = $value;
            }else{
                dd($hdr);
            }
            
        }

        return $values; 
    }

    public static function processLog($values, $organization_id){

        if(!array_key_exists('job_no', $values)){
            return true;
        }

        if(!array_key_exists('end_time', $values)){
           $values['end_time'] = null;
        }
        
            $job = static::where('organization_id',$organization_id)
                    ->where('job_no', $values['job_no'] )
                    ->where('invoice_no', $values['invoice_no'] )
                    ->first();

            $customer = Customer::where('organization_id',$organization_id)->where('name', $values['customer_name'])->first();
            $job_started =  Carbon::make($values['start_date'] . ' ' . $values['start_time']);
            $job_ended =  Carbon::make($values['start_date'] . ' ' . $values['end_time']);

            if(!$customer){
               $customer = Customer::create([
                'name'=> $values['customer_name'],
                'street'=> $values['street'],
                'city'=> $values['city'],
                'state'=> $values['state'],
                'zip'=> $values['zip_code'],
                'organization_id' => $organization_id
                ]);
            }

            $car_driver = User::where('organization_id',$organization_id)->where('name', $values['driver_of_pilot_car'])->first();

            if(!$car_driver){
                $car_driver = User::create([
                    'name'=> $values['driver_of_pilot_car'],
                    'email'=> 'missing_email_'.uniqid().'@email.com',
                    'password'=> 'DEFAULT_MISSING_PASSWORD_9Jx',
                    'organization_role'=>'driver',
                    'organization_id' => $organization_id
                ]);
            }

            $truck_driver_name = explode('#',$values['truck_driver_name'],2);

            if(count($truck_driver_name) === 2){
                $truck_driver_phone = trim($truck_driver_name[1]);
                $truck_driver_name = trim($truck_driver_name[0]);
            }else{
                $truck_driver_phone = null;
                $truck_driver_name = trim($truck_driver_name[0]);
            }

            $truck_driver = CustomerContact::where('organization_id',$organization_id)->where('name', $truck_driver_name)->where('customer_id', $customer->id)->first();

            if(!$truck_driver){
                $truck_driver = CustomerContact::create([
                    'name'=> $truck_driver_name,
                    'customer_id'=> $customer->id,
                    'phone' => $truck_driver_phone,
                    'memo' => $values['truck_driver_name'],
                    'organization_id' => $organization_id
                ]);
            }

            $car = Vehicle::where('organization_id',$organization_id)->where('name', $values['pilot_car_name'])->first();

            if(!$car){
                $car = Vehicle::create([
                    'name'=> $values['pilot_car_name'],
                    'odometer'=> $values['end_mileage'],
                    'odometer_updated_at'=> $job_ended->toDateTimeString(),
                    'organization_id' => $organization_id
                ]);
            }else{
                $car->update([
                    'odometer'=> $values['end_mileage'],
                    'odometer_updated_at'=> $job_ended->toDateTimeString()
                ]);
            }

            if(!$job){
                $job = static::create([
                    'job_no'=> $values['job_no'],
                    'customer_id'=> $customer->id,
                    'scheduled_pickup_at'=>$job_started->toDateTimeString(),
                    'scheduled_delivery_at'=>$job_ended->toDateTimeString(),
                    'load_no'=>$values['load_no'],
                    'pickup_address'=>$values['pickup_address'],
                    'delivery_address'=>$values['delivery_address'],
                    'check_no'=>$values['check_no'],
                    'invoice_paid'=> $values['invoice_paid'] && strtolower($values['invoice_paid']) == 'paid',
                    'invoice_no'=>$values['invoice_no'],
                    'rate_code'=> 'per_mile_rate',
                    'rate_value'=> $values['price_per_mile'],
                    'canceled_at'=>$values['if_load_canceled'] && strtolower($values['if_load_canceled']) == 'canceled'? $values['timestamp']:null,
                    'canceled_reason'=>$values['canceled_reason'],
                    'memo'=> $values['job_memo'],
                    'organization_id' => $organization_id
                ]);
            }

            $log = UserLog::where('organization_id',$organization_id)->where('job_id', $job->id)->where('vehicle_id', $car->id)->where('started_at', $job_started->toDateTimeString())->first();

            if(!$log){
                $l = UserLog::create([
                    'job_id'=> $job->id,
                    'cart_driver_id'=> $car_driver->id,
                    'truck_driver_id'=> $truck_driver->id,
                    'vehicle_id'=> $car->id,
                    'pretrip_check'=> strtolower($values['pretrip_check_answer']) === 'yes',
                    'truck_no'=>$values['truck_no'],
                    'trailer_no'=>$values['trailer_no'],
                    'start_mileage'=>$values['start_mileage'],
                    'end_mileage'=>$values['end_mileage'],
                    'start_job_mileage'=>$values['start_job_mileage'],
                    'end_job_mileage'=>$values['end_job_mileage'],
                    'load_canceled'=>$values['if_load_canceled'] && strtolower($values['if_load_canceled']) === 'canceled',
                    'is_deadhead'=>$values['is_deadhead'] && strtolower($values['is_deadhead']) === 'yes',
                    'extra_load_stops_count'=>$values['extra_load_stops_count'],
                    'wait_time_hours'=>$values['wait_time_hours'],
                    'tolls'=>$values['tolls'],
                    'gas'=>$values['gas'],
                    'extra_charge'=>$values['extra_charge'],
                    'hotel'=>$values['hotel'] === 'NA'? null:$values['hotel'],
                    'memo'=>$values['wait_time_reason'],
                    'maintenance_memo'=>$values['maintenance_memo'],
                    'started_at'=> $job_started->toDateTimeString(),
                    'ended_at'=> $job_ended->toDateTimeString(),
                    'organization_id' => $organization_id
                ]);
            }
            
    }

    public static function rates(){
        return [
            (Object)['value'=>'per_mile_rate_2_00','title'=>'$2.00 Per Mile (default)'],
            (Object)['value'=>'per_mile_rate_1_00','title'=>'$1.00 Per Mile'],
            (Object)['value'=>'per_mile_rate_1_25','title'=>'$1.25 Per Mile'],
            (Object)['value'=>'per_mile_rate_1_50','title'=>'$1.50 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_00','title'=>'$2.00 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_25','title'=>'$2.25 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_50','title'=>'$2.50 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_75','title'=>'$2.75 Per Mile'],
            (Object)['value'=>'per_mile_rate_3_00','title'=>'$3.00 Per Mile'],
            (Object)['value'=>'per_mile_rate_3_25','title'=>'$3.25 Per Mile'],
            (Object)['value'=>'per_mile_rate_3_50','title'=>'$3.50 Per Mile'],
            (Object)['value'=>'new_per_mile_rate','title'=>'NEW Per Mile Rate (enter value below)'],
            (Object)['value'=>'flat_rate_excludes_expenses','title'=>'Flat Price (excludes expenses)'],
            (Object)['value'=>'flat_rate','title'=>'Flat Price (includes Expenses)'],
        ];
    }

    /* TODO Implement all this calculation */
    public function getTruckDrivers(Bool $return_string = false){
        return '';
    }

    public function getTruckNumbers(Bool $return_string = false){
        return '';
    }

    public function getTrailerNumbers(Bool $return_string = false){
        return '';
    }

    public function getInvoiceNotes(Bool $return_string = false){
        return '';
    }

    public function totalWaitTimeHours(Bool $return_string = false){
        return '';
    }

    public function totalExtraLoadStops(Bool $return_string = false){
        return '';
    }

    public function getTotalTolls(Bool $return_string = false){
        return '';
    }

    public function getTotalHotel(Bool $return_string = false){
        return '';
    }

    public function getExtraCharges(Bool $return_string = false){
        return '';
    }

    public function getTotalMiles(Bool $return_string = false){
        return '';
    }

    public function getTotalDue(Bool $return_string = false){
        return '';
    }

    public function getTotalDeadHead(Bool $return_string = false){
        return '';
    }
}