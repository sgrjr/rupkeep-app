<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\Vehicle;
use App\Models\UserLog;
use App\Models\PilotCarJob as Job;
use App\Models\User;
use App\Models\Attachment;
use Livewire\WithFileUploads;

class JobAssignmentForm extends Form
{
    #[Validate('required|exists:users,id|max:255')]
    public $car_driver_id = null;
 
    #[Validate('nullable|exists:vehicles,id|max:255')]
    public $vehicle_id = null;

    #[Validate('required|string')]
    public $vehicle_position = null;
}

class ShowPilotCarJob extends Component
{

    use WithFileUploads;

    public JobAssignmentForm $assignment;
    public $job;

    public $vehicles = [];
    public $drivers = [];
    public $vehicle_positions = [];
    public $file;

    public function mount(Int $job){
        $this->job = Job::with('logs','logs.vehicle','logs.truck_driver','logs.user','logs.attachments','customer','customer.contacts')->find($job)->append('invoices_count');

        $this->vehicles = [
            ['name' => '(none selected)', 'value'=>null]
        ];
        
        Vehicle::where('organization_id', $this->job->organization_id)->get()->map(fn($v)=>$this->vehicles[] = ['name'=>$v->name, 'value'=> $v->id ]);

        $this->drivers = [
            ['name' => '(none selected)', 'value'=>null]
        ];
        
        $this->vehicle_positions = Vehicle::positionOptions();

        User::where('organization_id', $this->job->organization_id)->get()->map(fn($d)=>$this->drivers[] = ['name'=>$d->name, 'value'=> $d->id ]);

        $this->authorize('view', $this->job);
    }

    public function render()
    {
        return view('livewire.show-pilot-car-job');
    }

    public function assignJob(){
        $new_log = UserLog::make([
            'car_driver_id' => $this->assignment->car_driver_id,
            'job_id'=>$this->job->id,
            'vehicle_id'=> $this->assignment->vehicle_id,
            'organization_id' => $this->job->organization_id,
            'vehicle_position' => $this->assignment->vehicle_position,
        ]);

        $new_log->save();

        $this->assignment->reset();
        $this->dispatch('saved');
    }

    public function uploadFile()
    {
  
        $originalName = $this->file->getClientOriginalName();
        $this->file->storeAs(path: 'jobs/attachments_'.$this->job->id, name:$originalName);

        Attachment::create([
            'attachable_id' => $this->job->id,
            'attachable_type' => $this->job::class,
            'location' => storage_path('app/private/jobs/attachments_'.$this->job->id.'/'.$originalName),
            'organization_id' => $this->job->organization_id,
        ]);

        $this->dispatch('uploaded');
        
        return back();
    }

}
