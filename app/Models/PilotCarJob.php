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
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
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

        $number = 0;
        $header = [];
        $l = [];

        if (($handle = fopen($files[0]['full_path'], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, separator: ",")) !== FALSE) {  
                if($number == 0){
                    $h_eader = [];
                    foreach($data as $h){
                        $h_eader[] = str_replace('__','_',str_replace([' ','-'],'_',trim(str_replace(['#','(',')','/','?'],'', strtolower($h)))));
                    }
        
                    $header = static::translateHeaders($h_eader);
                }else{

                    if(count($data) != 57){
                        //dd('line#: '.$number, $values, $line);
                    }else{
                        $new_values = [];
                        foreach($data as $index=>$v){
                            $new_values[$header[$index]] = $v;
                        }
                        
                        static::validate($new_values, count($header));
                        $l[] = $new_values;
                    }
                    
                }
                $number++;
            }
            fclose($handle);
        }
          
        foreach($l as $line){
            static::processLog($line, $organization_id);
        }
    }

    public static function validate($row, $count): void{
        if(empty($row['invoice_no'])) dd($row);
        if(count($row) != $count) dd($row);
    }

    public static function translateHeaders($headers){
        $dictionary = [
            'job_no' => ['job','job_no'],
            'load_no' => ['load','load_no'],
            'timestamp' => ['timestamp','Timestamp','229',229],
            'check_no' => ['check','check_no'],
            'invoice_paid' => ['invoice_paid','Invoice Paid','invoice paid'],
            'invoice_no' => ['invoice','invoice_no'],
            'start_date' => ['date','Date'],
            'start_time' => ['start_time'],
            'start_mileage' => ['start_mileage'],
            'driver_of_pilot_car' => ['driver_of_pilot_car'],
            'pilot_car_name' => ['pilot_car','pilot_car_name'],
            'pretrip_check_answer' => ['did_you_pre_trip_your_vehicle_look_it_over_and_check_oil','pretrip'],
            'customer_name' => ['company_name','company name','Company Name'],
            'street' => ['address'],
            'city' => ['city'],
            'state' => ['state'],
            'zip_code' => ['zip_code'],
            'truck_driver_name' => ['truck_driver_name'],
            'truck_no' => ['truck','truck_no'],
            'trailer_no' => ['trailer','trailer_no'],
            'pickup_address' => ['load_pickup_address'],
            'delivery_address' => ['load_deliver_address','load_delivery_address'],
            'start_job_mileage' => ['start_job_mileage'],
            'load_canceled' => ['load_canceled'],
            'is_deadhead' => ['is_this_a_dead_head_run_to_manh_line','deadhead'],
            'extra_load_stops_count' => ['extra_load_stops'],
            'wait_time_hours' => ['wait_time'],
            'wait_time_reason' => ['trip_notes_reason_for_wait_time','trip_notes'],
            'end_job_mileage' => ['end_job_mileage'],
            'total_billable_miles' => ['total_billable_miles'],
            'tolls' => ['tolls'],
            'gas' => ['gas'],
            'upload_receiptsinvoices' => ['upload_receiptsinvoices',"upload_receipts\ninvoices","upload_receipts"],
            'end_mileage' => ['end_mileage'],
            'maintenance_memo' => ['any_questions_or_concerns_with_the_vehicle_any_maintenance_required','maintenance_memo'],
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
            'merged_doc_id__invoice_2024' => ['merged_doc_id__invoice_2024','merged_doc_id_invoice_2024'],
            'job_memo' => ['merged_doc_url__invoice_2024','merged_doc_url_invoice_2024'],
            'link_to_merged_doc__invoice_2024' => ['link_to_merged_doc__invoice_2024','link_to_merged_doc_invoice_2024'],
            'document_merge_status__invoice_2024' => ['document_merge_status__invoice_2024','document_merge_status_invoice_2024']
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
        if(count($headers) != count($values)) dd($headers, $values);
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
                    'odometer_updated_at'=> $job_ended?->toDateTimeString(),
                    'organization_id' => $organization_id
                ]);
            }else{
                $car->update([
                    'odometer'=> $values['end_mileage'],
                    'odometer_updated_at'=> $job_ended?->toDateTimeString()
                ]);
            }

            if(!$job){
                $job = static::create([
                    'job_no'=> $values['job_no'],
                    'customer_id'=> $customer->id,
                    'scheduled_pickup_at'=>$job_started?->toDateTimeString(),
                    'scheduled_delivery_at'=>$job_ended?->toDateTimeString(),
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

            $log = UserLog::where('organization_id',$organization_id)->where('job_id', $job->id)->where('vehicle_id', $car->id)->where('started_at', $job_started?->toDateTimeString())->first();

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
                    'started_at'=> $job_started?->toDateTimeString(),
                    'ended_at'=> $job_ended?->toDateTimeString(),
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

    public function invoiceValues(){

        $logs = $this->logs;
        $miles = $this->getTotalMiles($logs);

        $values = [
            'pilot_car_job_id' =>$this->id,
            'organization_id' =>$this->organization_id,
            'customer_id' =>$this->customer_id,
            'title' => 'INVOICE',
            'logo' => null,
            'bill_from' => [
                "company" => 'Casco Bay Pilot Car',
                'attention' => 'Mary Reynolds',
                "street" => 'P.O. Box 104',
                'city' => 'Gorham',
                'state' => 'ME',
                'zip' => "04038"
            ],
            'bill_to' => [
                "company" =>$this->customer->name,
                'attention' => null,
                "street" =>$this->customer->street,
                'city' =>$this->customer->city,
                'state' =>$this->customer->state,
                'zip' =>$this->customer->zip,
            ],
            'footer' => 'Casco Bay Pilot Car would like to thank you for your business. Thank you!',
            'truck_driver_name' =>$this->getTruckDrivers($logs),
            'truck_number' =>$this->getTruckNumbers($logs),
            'trailer_number' =>$this->getTrailerNumbers($logs),
            'pickup_address' =>$this->pickup_address,
            'delivery_address' =>$this->delivery_address,
            'notes' =>$this->getInvoiceNotes($logs),
            'load_no' =>$this->load_no,
            'check_no' =>$this->check_no,
            'wait_time_hours' =>$this->totalWaitTimeHours($logs),
            'extra_load_stops_count' =>$this->totalExtraLoadStops($logs),
            'dead_head' =>$this->getTotalDeadHead($logs),
            'tolls' =>$this->getTotalTolls($logs),
            'hotel' =>$this->getTotalHotel($logs),
            'extra_charge' =>$this->getExtraCharges($logs),
            'cars_count' =>$this->getCarsCount($logs),
            'rate_code' =>$this->rate_code,
            'rate_value' =>$this->rate_value,
            'total_due' => 0.00,
            'billable_miles' => $miles['total_billable'],
            'nonbillable_miles' => $miles['total_nonbillable'],
        ];

        $values['total'] = $this->calculateTotalDue($values);
        $values['effective_rate_code'] = $values['total']['effective_rate_code'];
        $values['effective_rate_value'] = $values['total']['effective_rate_value'];
        $values['total'] = $values['total']['total'];

        return [
            'paid_in_full' => false,
            'values' => $values,
            'organization_id' => $values['organization_id'],
            'customer_id' => $values['customer_id'],
            'pilot_car_job_id' => $values['pilot_car_job_id']
        ];
    }

    /* TODO Implement all this calculation */
    public function getTruckDrivers($logs = false){

        if(!$logs) $logs = $this->logs;
        $drivers = [];

        foreach($logs as $log){
            if($log->truck_driver_id && !in_array($log->truck_driver?->name, array_values($drivers))){
                $drivers[] = $log->truck_driver->name;
            }
        }

        return implode(' & ',$drivers);
    }

    public function getTruckNumbers($logs = false){
        if(!$logs) $logs = $this->logs;
        $no = [];

        foreach($logs as $log){
            if($log->truck_no && !in_array($log->truck_no, array_values($no))){
                $no[] = $log->truck_no;
            }
        }

        return implode(' & ',$no);
    }

    public function getTrailerNumbers($logs = false){
        if(!$logs) $logs = $this->logs;
        $no = [];

        foreach($logs as $log){
            if($log->trailer_no && !in_array($log->trailer_no, array_values($no))){
                $no[] = $log->trailer_no;
            }
        }

        return implode(' & ',$no);
    }

    public function getInvoiceNotes($logs = false){
        if(!$logs) $logs = $this->logs;
        $memo = [];

        foreach($logs as $log){
            if($log->memo && !in_array($log->memo, array_values($memo))){
                $memo[] = $log->memo;
            }
        }

        return implode(' | ',$memo);
    }

    public function totalWaitTimeHours($logs = false){
        if(!$logs) $logs = $this->logs;
        $wait = 0;

        foreach($logs as $log){
            if($log->wait_time_hours && !empty($log->wait_time_hours)){
                $wait += $log->wait_time_hours;
            }
        }
        return $wait;
    }

    public function totalExtraLoadStops($logs = false){
        if(!$logs) $logs = $this->logs;
        $stops = 0;

        foreach($logs as $log){
            if($log->extra_load_stops_count && !empty($log->extra_load_stops_count)){
                $stops += $log->extra_load_stops_count;
            }
        }
        return $stops;
    }

    public function getTotalTolls($logs = false){
        if(!$logs) $logs = $this->logs;
        $tolls = 0;

        foreach($logs as $log){
            if($log->tolls && !empty($log->tolls)){
                $tolls += (Int)$log->tolls;
            }
        }
        return number_format($tolls,2);
    }

    public function getTotalHotel($logs = false){
        if(!$logs) $logs = $this->logs;
        $hotel = 0;

        foreach($logs as $log){
            if($log->hotel && !empty($log->hotel)){
                $hotel += (Int)$log->hotel;
            }
        }
        return number_format( $hotel,2);
    }

    public function getExtraCharges($logs = false){
        if(!$logs) $logs = $this->logs;
        $extra_charge = 0;

        foreach($logs as $log){
            if($log->extra_charge && !empty($log->extra_charge)){
                $extra_charge += (Int)$log->extra_charge;
            }
        }
        return number_format($extra_charge,2);
    }

    public function getCarsCount($logs = false){
        if(!$logs) $logs = $this->logs;
        return count($logs);
    }

    public function getTotalMiles($logs = false){

        if(!$logs) $logs = $this->logs;

        $miles = [
            'total' => [],
            'billable' => [],
            'start'=> [],
            'end'=> [],
            'job_start'=> [],
            'job_end'=> [],
            'nonbillable' => []
        ];

        foreach($logs as $log){
           $miles['start'][] = $log->start_mileage;
           $miles['end'][] = $log->end_mileage;
           $miles['total'][] = $log->end_mileage - $log->start_mileage;
           $miles['job_start'][] = $log->start_job_mileage;
           $miles['job_end'][] = $log->end_job_mileage;
           $miles['billable'][] = $log->billable_miles && $log->billable_miles > 0? $log->billable_miles:$log->end_job_mileage - $log->start_job_mileage;
           $miles['nonbillable'][] = ($log->end_mileage - $log->start_mileage) - ($log->end_job_mileage - $log->start_job_mileage);
        }

        $miles['total_billable'] = array_sum($miles['billable']);
        $miles['total_nonbillable'] = array_sum($miles['nonbillable']);
        return $miles;
    }

    public function getTotalDeadHead($logs = false){
        if(!$logs) $logs = $this->logs;
        $deadhead = 0;

        foreach($logs as $log){
            if($log->is_deadhead && !empty($log->is_deadhead) && (Int)$log->is_deadhead === 1){
                $deadhead += 1;
            }
        }
        return $deadhead;
    }

    public function calculateTotalDue(Array $totals){

        $values = [
            'tolls' => (float)$totals['tolls'],
            'hotel' => (float)$totals['hotel'],
            'extra' => (float)$totals['extra_charge'],
            'load_stops' => 0.00,
            'wait_time' => 0.00,
        ];

        if($totals['extra_load_stops_count'] > 0){
            $values['load_stops'] = $totals['extra_load_stops_count'] * 30.00;
        }

        if($totals['wait_time_hours'] > 1){
            $values['load_stops'] = $totals['wait_time_hours'] * 20.00;
        }

        $expenses = 0.00;

        foreach($values as $v){
            $expenses += $v;
        }

        if($totals['rate_code'] === 'per_mile_rate'){
            $value = 1.00 * (float)number_format($totals['rate_value'], 2);
            $values['miles_charge'] = $totals['billable_miles'] * $value;
        }else if(str_starts_with($totals['rate_code'],'flat_rate')){
            $values['miles_charge'] = 0.00;
        }

        if($totals['dead_head'] > 0){
            $values['effective_rate_code'] = 'deadhead';
            $values['effective_rate_value'] = $totals['dead_head'];
            $values['total'] = number_format($expenses + ($totals['dead_head'] * 250.00),2);
        }else if($totals['billable_miles'] <= 125){
            //is mini
            $values['effective_rate_code'] = 'mini';
            $values['effective_rate_value'] = $totals['cars_count'];
            $values['total'] = number_format($expenses + (250.00 * $totals['cars_count']),2);
        }else{
            //figure by given rate
            if($totals['rate_code'] === 'flat_rate_excludes_expenses'){
                $values['effective_rate_code'] = 'flat_rate_excludes_expenses';
                $values['effective_rate_value'] = $totals['rate_value'];
                $values['total'] = number_format($totals['rate_value'],2);
            }else if($totals['rate_code'] === 'flat_rate'){
                $values['effective_rate_code'] = 'flat_rate';
                $values['effective_rate_value'] = $totals['rate_value'];
                $values['total'] = number_format((float)number_format($totals['rate_value'],2) + $expenses,2);
            }else{
                $values['effective_rate_code'] = 'per_mile_rate';
                $values['effective_rate_value'] = $totals['rate_value'];
                $values['total'] = $values['miles_charge'] + $expenses;
            }
            
        }

        return $values;
    }
}