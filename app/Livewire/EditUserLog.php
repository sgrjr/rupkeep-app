<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\User;
use App\Models\UserLog;
use App\Models\PilotCarJob;
use App\Models\Vehicle;
use App\Models\CustomerContact;
use App\Models\Attachment;
use Livewire\WithFileUploads;
class EditLogForm extends Form
{

    #[Validate('exists:pilot_car_jobs,id|min:1')]
    public $job_id = null;
 
    #[Validate('nullable|exists:users,id|min:1')]
    public $car_driver_id = null;

    #[Validate('nullable|exists:customer_contacts,id|min:1')]
    public $truck_driver_id = null;
    public $new_truck_driver_name = null;
    public $new_truck_driver_phone= null;
    public $new_truck_driver_memo= null;
    public $vehicle_id = null; 
    public $vehicle_position = null;

    public $truck_no = null;

    public $trailer_no = null;

    public $start_mileage = null;

    public $end_mileage = null;

    public $start_job_mileage = null;

    public $end_job_mileage = null;

    public $load_canceled = null;

    public $extra_charge = null;

    public $is_deadhead = null;

    public $extra_load_stops_count = null;

    public $wait_time_hours = null;

    public $tolls= null;

    public $gas = null;

    public $hotel = null;

    public $memo= null;

    public $pretrip_check = null;

    public $maintenance_memo = null;

    public $started_at = null;

    public $ended_at = null;
}

class EditUserLog extends Component
{
    use WithFileUploads;

    public EditLogForm $form;

    public $rates = [];
    public $car_drivers = [];
    public $vehicles = [];
    public $customer_contacts = [];

    public $vehicle_positions = [];

    public $log;

    public $file;

    protected $listeners = [
        'saved' => '$refresh',
    ];

    public function mount(Int $log){

       $this->rates = PilotCarJob::rates();
       $log = UserLog::with('organization','job','job.customer','attachments')->find($log);

       //Car Drivers
        $car_drivers = [
            ['name' => '(none selected)', 'value'=> null]
        ];

        User::where('organization_id', $log->organization_id)->get()->map(function($user)use(&$car_drivers){
            $car_drivers[] = [
                'name' => $user->name, 'value'=> $user->id
            ];
        });

        $this->car_drivers = $car_drivers;

        //Pilot Vehicles
        $vehicles = [
            ['name' => '(none selected)', 'value'=> null]
        ];

        Vehicle::where('organization_id', $log->organization_id)->get()->map(function($v)use(&$vehicles){
            $vehicles[] = [
                'name' => $v->name, 'value'=> $v->id
            ];
        });

        $this->vehicles = $vehicles;

        //Customer Contacts (Truck Drivers)
        $contacts = [
            ['name' => '(none selected)', 'value'=> null]
        ];
 
        CustomerContact::where('customer_id', $log->job->customer_id)->get()->map(function($c)use(&$contacts){
            $contacts[] = [
                'name' => $c->name . ' ('.$c->phone.')', 'value'=> $c->id
            ];
        });

        $this->customer_contacts = $contacts;

        $this->vehicle_positions = Vehicle::positionOptions();

       if($this->authorize('update', $log)){
            $this->log = $log;
            $this->form->job_id =$log->job_id;
            $this->form->car_driver_id =$log->car_driver_id;
            $this->form->truck_driver_id = $log->truck_driver_id;
            $this->form->vehicle_id =$log->vehicle_id;
            $this->form->vehicle_position =$log->vehicle_position;
            $this->form->truck_no =$log->truck_no;
            $this->form->trailer_no =$log->trailer_no;
            $this->form->start_mileage =$log->start_mileage;
            $this->form->end_mileage =$log->end_mileage;
            $this->form->start_job_mileage =$log->start_job_mileage;
            $this->form->end_job_mileage =$log->end_job_mileage;
            $this->form->load_canceled = $log->load_canceled != null && $log->load_canceled > 0? true:false;
            $this->form->extra_charge =$log->extra_charge;
            $this->form->is_deadhead =$log->is_deadhead;
            $this->form->extra_load_stops_count =$log->extra_load_stops_count;
            $this->form->wait_time_hours =$log->wait_time_hours;
            $this->form->tolls =$log->tolls;
            $this->form->gas =$log->gas;
            $this->form->hotel =$log->hotel;
            $this->form->memo =$log->memo;
            $this->form->pretrip_check = $log->pretrip_check != null && $log->pretrip_check > 0? true:false;
            $this->form->maintenance_memo =$log->maintenance_memo;
            $this->form->started_at =$log->started_at;
            $this->form->ended_at =$log->ended_at;
            $this->form->new_truck_driver_name = null;
            $this->form->new_truck_driver_phone = null;
            $this->form->new_truck_driver_memo = null;
        }
    }

    public function render()
    {
        return view('livewire.edit-user-log');
    }

    public function saveLog(){
        $regular = $this->form->except([
            'job_id','new_truck_driver_name','new_truck_driver_phone','new_truck_driver_memo'
        ]);

        if(!empty($this->form->new_truck_driver_name)){

            $truck_driver = CustomerContact::make([
                'name'=> $this->form->new_truck_driver_name,
                'customer_id'=> $this->log->job->customer_id,
                'phone' => $this->form->new_truck_driver_phone,
                'memo' => $this->form->new_truck_driver_memo,
                'organization_id' => $this->log->organization_id
            ]);

            $existing = CustomerContact::where('name', $truck_driver->name)->where('customer_id', $truck_driver->customer_id)->where('phone', $truck_driver->phone)->where('organization_id', $truck_driver->organization_id)->first();

            if($existing){
                if(!empty($truck_driver->memo)) $truck_driver = $existing->update(['memo'=>$truck_driver->memo]);
                $truck_driver = $existing;
            }else{
                $truck_driver->save();
            }

            $this->form->truck_driver_id = $truck_driver->id;
            $this->log->truck_driver_id = $truck_driver->id;

            $this->customer_contacts = array_merge($this->customer_contacts, [[
                'name' => $truck_driver->name . ' ('.$truck_driver->phone.')', 'value'=> $truck_driver->id
            ]]);

            $regular['truck_driver_id'] = $truck_driver->id;
        }

        $this->log->update($regular);

        $this->dispatch('saved');
    }

    public function uploadFile()
    {
  
        $originalName = $this->file->getClientOriginalName();
        $this->file->storeAs(path: 'jobs/attachments_'.$this->log->job_id, name:$originalName);

        Attachment::create([
            'attachable_id' => $this->log->id,
            'attachable_type' => $this->log::class,
            'location' => storage_path('app/private/jobs/attachments_'.$this->log->job_id.'/'.$originalName),
            'organization_id' => $this->log->organization_id,
        ]);

        $this->dispatch('uploaded');
        
        return back();
    }
}
