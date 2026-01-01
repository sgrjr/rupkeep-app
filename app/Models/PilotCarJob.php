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
use App\Models\PricingSetting;
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
        'public_memo',
        'organization_id',
        'default_driver_id',
        'default_truck_driver_id',
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

    public function defaultDriver(){
        return $this->belongsTo(User::class, 'default_driver_id');
    }

    public function defaultTruckDriver(){
        return $this->belongsTo(CustomerContact::class, 'default_truck_driver_id');
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
                    'organization_role'=> User::ROLE_EMPLOYEE_STANDARD,
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
                if(!empty($value['timestamp'])){
                    $values['timestamp'] = Carbon::make($values['timestamp'])->toDateTimeString();
                }else{
                    $values['timestamp'] = null;
                }

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
                $hotel = null;

                if($values['hotel'] === 'NA' || empty($values['hotel'])){
                    //do nothing? 0.00:$values['hotel']
                }else{
                    $hotel = (Float)(trim(str_replace('$','', $values['hotel'])));
                }

                $l = UserLog::create([
                    'job_id'=> $job->id,
                    'car_driver_id'=> $car_driver->id,
                    'truck_driver_id'=> $truck_driver->id,
                    'vehicle_id'=> $car->id,
                    'pretrip_check'=> strtolower($values['pretrip_check_answer']) === 'yes',
                    'truck_no'=>$values['truck_no'],
                    'trailer_no'=>$values['trailer_no'],
                    'start_mileage'=>empty($values['start_mileage'])?null:$values['start_mileage'],
                    'end_mileage'=> empty($values['end_mileage'])?null:$values['end_mileage'],
                    'start_job_mileage'=> empty($values['start_job_mileage'])?null:$values['start_job_mileage'],
                    'end_job_mileage'=> empty($values['end_job_mileage'])?null:$values['end_job_mileage'],
                    'load_canceled'=>$values['if_load_canceled'] && strtolower($values['if_load_canceled']) === 'canceled',
                    'is_deadhead'=>$values['is_deadhead'] && strtolower($values['is_deadhead']) === 'yes',
                    'extra_load_stops_count'=> empty($values['extra_load_stops_count'])?0:(int)$values['extra_load_stops_count'],
                    'wait_time_hours'=> empty($values['wait_time_hours'])?0.00:$values['wait_time_hours'],
                    'tolls'=> empty($values['tolls'])?0.00:$values['tolls'],
                    'gas'=>empty($values['gas'])?0.00:$values['gas'],
                    'extra_charge'=>empty($values['extra_charge'])?0.00:$values['extra_charge'],
                    'hotel'=> $hotel,
                    'memo'=>$values['wait_time_reason'],
                    'maintenance_memo'=>$values['maintenance_memo'],
                    'started_at'=> $job_started?->toDateTimeString(),
                    'ended_at'=> $job_ended?->toDateTimeString(),
                    'organization_id' => $organization_id
                ]);
            }
            
    }

    /**
     * Get pricing value for this job's organization with fallback to config
     */
    private function getPricingValue(string $key, $default = null)
    {
        return PricingSetting::getValueForOrganization($this->organization_id, $key, $default);
    }

    public static function rates(?int $organizationId = null)
    {
        // If organization ID provided, use organization-scoped pricing
        $pricingConfig = $organizationId 
            ? static::getRatesForOrganization($organizationId)
            : config('pricing.rates', []);
        $legacyRates = config('pricing.legacy_rates', []);
        
        $rates = [];
        
        // Add new pricing structure rates
        foreach ($pricingConfig as $code => $config) {
            $rates[$code] = $config['name'] . ' - ' . $config['description'];
        }
        
        // Add legacy per-mile rates
        foreach ($legacyRates as $code => $config) {
            if (isset($config['rate_per_mile'])) {
                $rates[$code] = '$' . number_format($config['rate_per_mile'], 2) . ' Per Mile';
            } elseif ($code === 'flat_rate') {
                $rates[$code] = 'Flat Price (includes expenses)';
            } elseif ($code === 'flat_rate_excludes_expenses') {
                $rates[$code] = 'Flat Price (excludes expenses)';
            }
        }
        
        // Add custom rate option
        $rates['new_per_mile_rate'] = 'Custom Per Mile Rate (enter below)';
        $rates['custom_flat_rate'] = 'Custom Flat Rate (enter below)';

        return collect($rates)->map(function (string $title, string $value) {
            return (object) ['value' => $value, 'title' => $title];
        })->values()->all();
    }

    /**
     * Get rates for a specific organization (with overrides)
     */
    private static function getRatesForOrganization(int $organizationId): array
    {
        $configRates = config('pricing.rates', []);
        $rates = [];
        
        foreach ($configRates as $code => $config) {
            $rates[$code] = $config;
            
            // Override with organization-specific values if they exist
            if (isset($config['rate_per_mile'])) {
                $orgValue = PricingSetting::getValueForOrganization(
                    $organizationId,
                    "rates.{$code}.rate_per_mile",
                    $config['rate_per_mile']
                );
                $rates[$code]['rate_per_mile'] = $orgValue;
            }
            
            if (isset($config['flat_amount'])) {
                $orgValue = PricingSetting::getValueForOrganization(
                    $organizationId,
                    "rates.{$code}.flat_amount",
                    $config['flat_amount']
                );
                $rates[$code]['flat_amount'] = $orgValue;
            }
        }
        
        return $rates;
    }

    public static function defaultRateValue(?string $rateCode, ?int $organizationId = null): ?string
    {
        if (! $rateCode) {
            return null;
        }

        // Check new pricing structure first
        $pricingConfig = $organizationId 
            ? static::getRatesForOrganization($organizationId)
            : config('pricing.rates', []);
            
        if (isset($pricingConfig[$rateCode])) {
            $config = $pricingConfig[$rateCode];
            if (isset($config['rate_per_mile'])) {
                return number_format($config['rate_per_mile'], 2, '.', '');
            }
            if (isset($config['flat_amount'])) {
                return number_format($config['flat_amount'], 2, '.', '');
            }
        }

        // Legacy per-mile rate parsing
        if (preg_match('/per_mile_rate_(\d+)_(\d+)/', $rateCode, $matches)) {
            $dollars = (int) $matches[1];
            $cents = (int) $matches[2];

            return number_format($dollars + ($cents / 100), 2, '.', '');
        }

        return null;
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
            'notes' =>$this->public_memo ?? '',
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
            // Driver details for invoice
            'pilot_car_driver_name' => $this->getPilotCarDrivers($logs),
            'pilot_car_driver_position' => $this->getPilotCarDriverPositions($logs),
            'start_job_mileage' => $this->getStartJobMileage($logs),
            'end_job_mileage' => $this->getEndJobMileage($logs),
            'start_job_time' => $this->getStartJobTime($logs),
            'end_job_time' => $this->getEndJobTime($logs),
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

    public function getPilotCarDrivers($logs = false)
    {
        if (!$logs) {
            $logs = $this->logs;
        }
        
        $drivers = [];
        foreach ($logs as $log) {
            if ($log->car_driver_id && $log->user) {
                $name = $log->user->name;
                if (!in_array($name, $drivers)) {
                    $drivers[] = $name;
                }
            }
        }
        
        return implode(' & ', $drivers) ?: '—';
    }

    public function getPilotCarDriverPositions($logs = false)
    {
        if (!$logs) {
            $logs = $this->logs;
        }
        
        $positions = [];
        foreach ($logs as $log) {
            if ($log->vehicle_position) {
                $pos = $log->vehicle_position;
                if (!in_array($pos, $positions)) {
                    $positions[] = $pos;
                }
            }
        }
        
        return implode(' & ', $positions) ?: '—';
    }

    public function getStartJobMileage($logs = false)
    {
        if (!$logs) {
            $logs = $this->logs;
        }
        
        $mileages = [];
        foreach ($logs as $log) {
            if ($log->start_job_mileage !== null) {
                $mileages[] = (float) $log->start_job_mileage;
            }
        }
        
        if (empty($mileages)) {
            return null;
        }
        
        return min($mileages); // Use earliest start mileage
    }

    public function getEndJobMileage($logs = false)
    {
        if (!$logs) {
            $logs = $this->logs;
        }
        
        $mileages = [];
        foreach ($logs as $log) {
            if ($log->end_job_mileage !== null) {
                $mileages[] = (float) $log->end_job_mileage;
            }
        }
        
        if (empty($mileages)) {
            return null;
        }
        
        return max($mileages); // Use latest end mileage
    }

    public function getStartJobTime($logs = false)
    {
        if (!$logs) {
            $logs = $this->logs;
        }
        
        $times = [];
        foreach ($logs as $log) {
            if ($log->started_at) {
                $times[] = Carbon::parse($log->started_at);
            }
        }
        
        if (empty($times)) {
            return null;
        }
        
        return min($times)->toDateTimeString(); // Use earliest start time
    }

    public function getEndJobTime($logs = false)
    {
        if (!$logs) {
            $logs = $this->logs;
        }
        
        $times = [];
        foreach ($logs as $log) {
            if ($log->ended_at) {
                $times[] = Carbon::parse($log->ended_at);
            }
        }
        
        if (empty($times)) {
            return null;
        }
        
        return max($times)->toDateTimeString(); // Use latest end time
    }

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

        foreach ($logs as $log) {
            $miles['start'][] = $log->start_mileage;
            $miles['end'][] = $log->end_mileage;
            $miles['total'][] = $log->end_mileage - $log->start_mileage;
            $miles['job_start'][] = $log->start_job_mileage;
            $miles['job_end'][] = $log->end_job_mileage;

            // Calculate from job mileage
            $billable = ($log->end_job_mileage - $log->start_job_mileage);

            // Manual override
            if ($log->billable_miles !== null && $log->billable_miles !== '' && is_numeric($log->billable_miles)) {
                $billable = (float) $log->billable_miles;
            }

            $miles['billable'][] = max(0, $billable);
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

        // Get organization ID from totals or job
        $organizationId = $totals['organization_id'] ?? $this->organization_id ?? null;
        
        // Extra stops: $30.00 per stop
        if($totals['extra_load_stops_count'] > 0){
            $extraStopRate = $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'charges.extra_stop.rate_per_stop', config('pricing.charges.extra_stop.rate_per_stop', 30.00))
                : config('pricing.charges.extra_stop.rate_per_stop', 30.00);
            $values['load_stops'] = $totals['extra_load_stops_count'] * $extraStopRate;
        }

        // Wait time: $25.00 per hour (charged after first hour)
        if($totals['wait_time_hours'] > 1){
            $waitTimeRate = $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'charges.wait_time.rate_per_hour', config('pricing.charges.wait_time.rate_per_hour', 25.00))
                : config('pricing.charges.wait_time.rate_per_hour', 25.00);
            $values['wait_time'] = ($totals['wait_time_hours'] - 1) * $waitTimeRate;
        }

        $expenses = 0.00;

        foreach($values as $v){
            $expenses += $v;
        }

        $normalizedRateValue = (float) str_replace(',', '', (string) ($totals['rate_value'] ?? 0));
        $rateCode = $totals['rate_code'] ?? '';
        $billableMiles = (float) ($totals['billable_miles'] ?? 0);
        
        // Get organization-scoped pricing config
        $pricingConfig = $organizationId
            ? static::getRatesForOrganization($organizationId)
            : config('pricing.rates', []);

        // Check if using new pricing structure
        if (isset($pricingConfig[$rateCode])) {
            $rateConfig = $pricingConfig[$rateCode];
            
            if ($rateConfig['type'] === 'per_mile') {
                // Per mile rate (Lead/Chase)
                $ratePerMile = $rateConfig['rate_per_mile'] ?? $normalizedRateValue;
                $values['miles_charge'] = $billableMiles * $ratePerMile;
                $values['effective_rate_code'] = $rateCode;
                $values['effective_rate_value'] = $ratePerMile;
                $values['total'] = ($values['miles_charge'] ?? 0) + $expenses;
            } elseif ($rateConfig['type'] === 'flat') {
                // Flat rate (Mini, Show No-Go, Cancellation, Day Rate, etc.)
                $flatAmount = $rateConfig['flat_amount'] ?? $normalizedRateValue;
                $values['miles_charge'] = 0.00;
                $values['effective_rate_code'] = $rateCode;
                $values['effective_rate_value'] = $flatAmount;
                
                // Check for mini rate: if billable miles <= max_miles, use flat rate
                if ($rateCode === 'mini_flat_rate' && isset($rateConfig['max_miles'])) {
                    if ($billableMiles > $rateConfig['max_miles']) {
                        // Exceeds mini threshold, fall back to per-mile calculation
                        $fallbackRate = $organizationId
                            ? PricingSetting::getValueForOrganization($organizationId, 'rates.lead_chase_per_mile.rate_per_mile', config('pricing.rates.lead_chase_per_mile.rate_per_mile', 2.00))
                            : config('pricing.rates.lead_chase_per_mile.rate_per_mile', 2.00);
                        $values['miles_charge'] = $billableMiles * $fallbackRate;
                        $values['effective_rate_code'] = 'lead_chase_per_mile';
                        $values['effective_rate_value'] = $fallbackRate;
                        $values['total'] = ($values['miles_charge'] ?? 0) + $expenses;
                    } else {
                        $values['total'] = $flatAmount + $expenses;
                    }
                } else {
                    // Other flat rates
                    $values['total'] = $flatAmount + $expenses;
                }
            }
        } elseif (str_starts_with($rateCode, 'per_mile_rate') || $rateCode === 'new_per_mile_rate') {
            // Legacy per-mile rates or custom per-mile
            $value = $normalizedRateValue > 0 ? round($normalizedRateValue, 2) : 2.00; // Default to $2.00
            $values['miles_charge'] = $billableMiles * $value;
            $values['effective_rate_code'] = 'per_mile_rate';
            $values['effective_rate_value'] = $value;
            $values['total'] = ($values['miles_charge'] ?? 0) + $expenses;
        } elseif (str_starts_with($rateCode, 'flat_rate') || $rateCode === 'custom_flat_rate') {
            // Legacy flat rates or custom flat
            $values['miles_charge'] = 0.00;
            $flatAmount = $normalizedRateValue > 0 ? $normalizedRateValue : 0;
            
            if ($rateCode === 'flat_rate_excludes_expenses') {
                $values['effective_rate_code'] = 'flat_rate_excludes_expenses';
                $values['effective_rate_value'] = $flatAmount;
                $values['total'] = number_format($flatAmount, 2);
            } else {
                $values['effective_rate_code'] = 'flat_rate';
                $values['effective_rate_value'] = $flatAmount;
                $values['total'] = number_format($flatAmount + $expenses, 2);
            }
        } else {
            // Fallback: default per-mile rate
            $defaultRate = $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'rates.lead_chase_per_mile.rate_per_mile', 2.00)
                : 2.00;
            $values['miles_charge'] = $billableMiles * $defaultRate;
            $values['effective_rate_code'] = 'per_mile_rate';
            $values['effective_rate_value'] = $defaultRate;
            $values['total'] = ($values['miles_charge'] ?? 0) + $expenses;
        }
        
        // Format total as number (not string)
        $values['total'] = (float) str_replace(',', '', (string) $values['total']);

        return $values;
    }

    public function getMilesAttribute(){
        $miles = (Object)[
            'total' => 0.0,
            'personal' => 0.0,
            'billable' => 0.0
        ];

        foreach($this->logs as $log){
            $miles->billable += $log->total_billable_miles ?? 0.0;
            $miles->total += $log->total_miles ?? 0.0;
            $miles->personal += $log->personal_miles ?? 0.0;
        }

        return $miles;
    }

    /**
     * Determine the cancellation type based on timing and job status
     * 
     * @return string The cancellation rate code to use
     */
    public function determineCancellationType(): string
    {
        $now = now();
        $pickupTime = $this->scheduled_pickup_at;
        
        if (!$pickupTime) {
            // No pickup time set, default to no billing
            return 'cancel_without_billing';
        }

        $pickupTime = Carbon::parse($pickupTime);
        $hoursUntilPickup = $now->diffInHours($pickupTime, false); // false = don't return absolute value
        
        $organizationId = $this->organization_id;
        $hoursThreshold = $organizationId
            ? PricingSetting::getValueForOrganization($organizationId, 'cancellation.hours_before_pickup_for_24hr_charge', config('pricing.cancellation.hours_before_pickup_for_24hr_charge', 24))
            : config('pricing.cancellation.hours_before_pickup_for_24hr_charge', 24);

        // Check if canceled within 24 hours of pickup
        if ($hoursUntilPickup >= 0 && $hoursUntilPickup <= $hoursThreshold) {
            return 'cancellation_24hr';
        }

        // Check if there are any logs (driver showed up)
        if ($this->logs()->exists()) {
            return 'show_no_go';
        }

        // Default: no billing
        return 'cancel_without_billing';
    }

    /**
     * Get the job status
     * 
     * @return string 'ACTIVE', 'CANCELLED', 'CANCELLED_NO_GO', or 'COMPLETED'
     */
    public function getStatusAttribute(): string
    {
        if ($this->canceled_at) {
            // Check if it's a "show but no-go" (has logs but was canceled)
            if ($this->logs()->exists()) {
                return 'CANCELLED'; // Show but no-go
            }
            return 'CANCELLED_NO_GO'; // Cancelled before any work
        }

        // Check if job has invoices (completed)
        if ($this->invoices()->exists()) {
            return 'COMPLETED';
        }

        return 'ACTIVE';
    }

    /**
     * Get human-readable status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'ACTIVE' => __('Active'),
            'CANCELLED' => __('Cancelled (Show But No-Go)'),
            'CANCELLED_NO_GO' => __('Cancelled (No-Go)'),
            'COMPLETED' => __('Completed'),
            default => __('Unknown'),
        };
    }

    /**
     * Compare flat rate vs mini rate and return which is better
     * Returns the rate code that should be used
     */
    public function getOptimalRateCode(): ?string
    {
        $billableMiles = (float) ($this->miles->billable ?? 0);
        
        if ($billableMiles <= 125) {
            // Check if mini rate is better than current rate
            $organizationId = $this->organization_id;
            $miniRate = $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'rates.mini_flat_rate.flat_amount', config('pricing.rates.mini_flat_rate.flat_amount', 350.00))
                : config('pricing.rates.mini_flat_rate.flat_amount', 350.00);
            $currentRateValue = (float) ($this->rate_value ?? 0);
            
            // If using per-mile rate, calculate cost
            if (str_starts_with($this->rate_code ?? '', 'per_mile_rate')) {
                $perMileCost = $billableMiles * $currentRateValue;
                if ($perMileCost > $miniRate) {
                    return 'mini_flat_rate';
                }
            }
        }
        
        return $this->rate_code;
    }

    /**
     * Get rate comparison data for display
     * Returns array with current rate cost, mini rate cost, and savings
     */
    public function getRateComparison(): ?array
    {
        $billableMiles = (float) ($this->miles->billable ?? 0);
        
        // Only show comparison if billable miles <= 125 (mini threshold)
        $organizationId = $this->organization_id;
        $miniMaxMiles = $organizationId
            ? PricingSetting::getValueForOrganization($organizationId, 'rates.mini_flat_rate.max_miles', config('pricing.rates.mini_flat_rate.max_miles', 125))
            : config('pricing.rates.mini_flat_rate.max_miles', 125);
            
        if ($billableMiles > $miniMaxMiles) {
            return null; // Not eligible for mini rate
        }

        $miniRate = $organizationId
            ? PricingSetting::getValueForOrganization($organizationId, 'rates.mini_flat_rate.flat_amount', config('pricing.rates.mini_flat_rate.flat_amount', 350.00))
            : config('pricing.rates.mini_flat_rate.flat_amount', 350.00);

        // Calculate current rate cost
        $currentCost = 0.00;
        $currentRateCode = $this->rate_code ?? '';
        $currentRateValue = (float) ($this->rate_value ?? 0);

        // Get expenses (these apply to both rates)
        $invoiceValues = $this->invoiceValues();
        $expenses = 0.00;
        if (isset($invoiceValues['values'])) {
            $vals = $invoiceValues['values'];
            $expenses = (float) ($vals['tolls'] ?? 0) + 
                       (float) ($vals['hotel'] ?? 0) + 
                       (float) ($vals['extra'] ?? 0) + 
                       (float) ($vals['load_stops'] ?? 0) + 
                       (float) ($vals['wait_time'] ?? 0);
        }

        // Calculate cost based on current rate
        if (str_starts_with($currentRateCode, 'per_mile_rate') || $currentRateCode === 'lead_chase_per_mile') {
            // Per-mile rate
            $perMileRate = $currentRateValue > 0 ? $currentRateValue : 
                ($organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, 'rates.lead_chase_per_mile.rate_per_mile', config('pricing.rates.lead_chase_per_mile.rate_per_mile', 2.00))
                    : config('pricing.rates.lead_chase_per_mile.rate_per_mile', 2.00));
            $currentCost = ($billableMiles * $perMileRate) + $expenses;
        } elseif (str_starts_with($currentRateCode, 'flat_rate') || $currentRateCode === 'custom_flat_rate') {
            // Flat rate
            $flatAmount = $currentRateValue > 0 ? $currentRateValue : 0;
            if ($currentRateCode === 'flat_rate_excludes_expenses') {
                $currentCost = $flatAmount + $expenses;
            } else {
                $currentCost = $flatAmount + $expenses;
            }
        } elseif ($currentRateCode === 'mini_flat_rate') {
            // Already using mini rate
            $currentCost = $miniRate + $expenses;
        } else {
            // Unknown rate type, try to calculate from invoice values
            if (isset($invoiceValues['values']['total'])) {
                $currentCost = (float) $invoiceValues['values']['total'];
            }
        }

        // Calculate mini rate cost (always includes expenses)
        $miniCost = $miniRate + $expenses;

        // Determine which is better
        $savings = $currentCost - $miniCost;
        $isMiniBetter = $savings > 0;

        return [
            'current_cost' => $currentCost,
            'current_rate_code' => $currentRateCode,
            'current_rate_label' => $this->getRateLabel($currentRateCode),
            'mini_cost' => $miniCost,
            'mini_rate' => $miniRate,
            'savings' => abs($savings),
            'is_mini_better' => $isMiniBetter,
            'expenses' => $expenses,
            'billable_miles' => $billableMiles,
        ];
    }

    /**
     * Get human-readable label for rate code
     */
    private function getRateLabel(string $rateCode): string
    {
        $labels = [
            'per_mile_rate' => 'Per Mile Rate',
            'lead_chase_per_mile' => 'Lead/Chase Per Mile',
            'flat_rate' => 'Flat Rate',
            'flat_rate_excludes_expenses' => 'Flat Rate (Excludes Expenses)',
            'mini_flat_rate' => 'Mini-Run Rate',
            'custom_flat_rate' => 'Custom Flat Rate',
        ];

        return $labels[$rateCode] ?? $rateCode;
    }
}