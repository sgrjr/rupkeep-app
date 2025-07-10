<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\User;
use App\Models\UserLog;
use App\Models\PilotCarJob;
use App\Models\Vehicle; // Corrected typo here
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
    public $new_truck_driver_phone = null;
    public $new_truck_driver_memo = null;
    public $vehicle_id = null;
    public $vehicle_position = null;

    public $truck_no = null;

    public $trailer_no = null;

    public $start_mileage = null;

    public $end_mileage = null;

    public $start_job_mileage = null;

    public $end_job_mileage = null;

    // These are booleans from the form, will convert to 0/1 for DB
    #[Validate('nullable|boolean')]
    public $load_canceled = false;

    public $extra_charge = null;

    #[Validate('nullable|boolean')]
    public $is_deadhead = false;

    public $extra_load_stops_count = null;

    public $wait_time_hours = null;

    public $tolls = null;

    public $gas = null;

    public $hotel = null;

    public $memo = null;

    // This is a boolean from the form, will convert to 0/1 for DB
    #[Validate('nullable|boolean')]
    public $pretrip_check = false;

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

    public UserLog $log; // Holds the UserLog model instance, now type-hinted

    public $file; // For file uploads

    // Properties to manage the open/closed state of details sections
    public $isDriverVehicleOpen = false; // Default to open
    public $isTripTimingOpen = false;
    public $isMileageDetailsOpen = false;
    public $isTruckTrailerOpen = false;
    public $isLoadTripStatusOpen = false;
    public $isExpensesOpen = false;
    public $isMaintenanceMemoOpen = false;

    protected $listeners = [
        'saved' => '$refresh',
    ];

    public function mount(UserLog $log)
    {
        $this->log = $log->load('organization', 'job', 'job.customer', 'attachments');

        if (!auth()->user()?->can('update', $this->log)) {
            abort(403, 'Unauthorized action.');
        }

        $this->car_drivers = [['name' => '(none selected)', 'value' => null]];
        User::where('organization_id', $this->log->organization_id)->get()->each(function ($user) {
            $this->car_drivers[] = ['name' => $user->name, 'value' => $user->id];
        });

        $this->vehicles = [['name' => '(none selected)', 'value' => null]];
        Vehicle::where('organization_id', $this->log->organization_id)->get()->each(function ($v) {
            $this->vehicles[] = ['name' => $v->name, 'value' => $v->id];
        });

        $this->customer_contacts = [['name' => '(none selected)', 'value' => null]];
        CustomerContact::where('customer_id', $this->log->job->customer_id)->get()->each(function ($c) {
            $this->customer_contacts[] = ['name' => $c->name . ' (' . $c->phone . ')', 'value' => $c->id];
        });

        $this->vehicle_positions = Vehicle::positionOptions();

        $this->form->fill([
            'job_id' => $this->log->job_id,
            'car_driver_id' => $this->log->car_driver_id,
            'truck_driver_id' => $this->log->truck_driver_id,
            'vehicle_id' => $this->log->vehicle_id,
            'vehicle_position' => $this->log->vehicle_position,
            'truck_no' => $this->log->truck_no,
            'trailer_no' => $this->log->trailer_no,
            'start_mileage' => $this->log->start_mileage,
            'end_mileage' => $this->log->end_mileage,
            'start_job_mileage' => $this->log->start_job_mileage,
            'end_job_mileage' => $this->log->end_job_mileage,
            'load_canceled' => (bool)$this->log->load_canceled,
            'extra_charge' => $this->log->extra_charge,
            'is_deadhead' => (bool)$this->log->is_deadhead,
            'extra_load_stops_count' => $this->log->extra_load_stops_count,
            'wait_time_hours' => $this->log->wait_time_hours,
            'tolls' => $this->log->tolls,
            'gas' => $this->log->gas,
            'hotel' => $this->log->hotel,
            'memo' => $this->log->memo,
            'pretrip_check' => (bool)$this->log->pretrip_check,
            'maintenance_memo' => $this->log->maintenance_memo,
            'started_at' => $this->log->started_at,
            'ended_at' => $this->log->ended_at,
            'new_truck_driver_name' => null,
            'new_truck_driver_phone' => null,
            'new_truck_driver_memo' => null,
        ]);
    }

    public function render()
    {
        return view('livewire.edit-user-log');
    }

    public function saveLog()
    {
        try {
            $this->form->validate();

            if (!empty($this->form->new_truck_driver_name)) {
                $truck_driver_data = [
                    'name' => $this->form->new_truck_driver_name,
                    'customer_id' => $this->log->job->customer_id,
                    'phone' => $this->form->new_truck_driver_phone,
                    'memo' => $this->form->new_truck_driver_memo,
                    'organization_id' => $this->log->organization_id,
                ];

                $existing = CustomerContact::where('name', $truck_driver_data['name'])
                                            ->where('customer_id', $truck_driver_data['customer_id'])
                                            ->where('phone', $truck_driver_data['phone'])
                                            ->where('organization_id', $truck_driver_data['organization_id'])
                                            ->first();

                if ($existing) {
                    if (!empty($truck_driver_data['memo'])) {
                        $existing->update(['memo' => $truck_driver_data['memo']]);
                    }
                    $this->form->truck_driver_id = $existing->id;
                } else {
                    $newTruckDriver = CustomerContact::create($truck_driver_data);
                    $this->form->truck_driver_id = $newTruckDriver->id;
                }

                $this->customer_contacts = [['name' => '(none selected)', 'value' => null]];
                CustomerContact::where('customer_id', $this->log->job->customer_id)->get()->each(function ($c) {
                    $this->customer_contacts[] = ['name' => $c->name . ' (' . $c->phone . ')', 'value' => $c->id];
                });

                $this->form->new_truck_driver_name = null;
                $this->form->new_truck_driver_phone = null;
                $this->form->new_truck_driver_memo = null;
            }

            $updateData = $this->form->except([
                'job_id',
                'new_truck_driver_name',
                'new_truck_driver_phone',
                'new_truck_driver_memo'
            ]);

            $updateData['load_canceled'] = $this->form->load_canceled ? 1 : 0;
            $updateData['is_deadhead'] = $this->form->is_deadhead ? 1 : 0;
            $updateData['pretrip_check'] = $this->form->pretrip_check ? 1 : 0;

            $this->log->update($updateData);

            $this->dispatch('saved');

        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Please correct the validation errors below.');
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'An unexpected error occurred while saving: ' . $e->getMessage());
        }
    }

    public function uploadFile()
    {
        $this->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            $originalName = $this->file->getClientOriginalName();
            $path = 'jobs/attachments_' . $this->log->job_id;
            $this->file->storeAs(path: $path, name: $originalName, disk: 'private');

            Attachment::create([
                'attachable_id' => $this->log->id,
                'attachable_type' => get_class($this->log),
                'location' => $path . '/' . $originalName,
                'file_name' => $originalName,
                'organization_id' => $this->log->organization_id,
            ]);

            $this->dispatch('uploaded');
            $this->log->refresh();
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to upload file: ' . $e->getMessage());
        }
    }
}
