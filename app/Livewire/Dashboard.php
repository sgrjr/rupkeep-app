<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\PilotCarJob;
use App\Models\UserLog;

class Dashboard extends Component
{

    public $data = [];

    public $component = null;

    use WithFileUploads;
 
    #[Validate('max:1024')] // 1MB Max
    public $file;

    public function mount($component = null){
        $this->component = $component;
    }

    public function render(Request $request)
    {
       $organization = Auth::user()->organization;
       $organizations = false;

       $cards = [];
        //dd(PilotCarJob::all());
       if(auth()->user()->can('viewAny', new Organization)){
        $cards[] = (Object)['title'=>'Organizations', 'count'=> Organization::count(), 'links'=> [
             ['url'=> route('organizations.index'), 'title'=>'View All'],
             ['url'=> route('organizations.create'), 'title'=>'+Create New'],
         ]];
       }

       if(auth()->user()->can('createJob', $organization)){
       $cards[] = (Object)['title'=>'Jobs', 'count'=> $organization->jobs()->count(), 'links'=> [
            ['url'=> route('my.jobs.index'), 'title'=>'View All'],
            ['url'=> route('my.jobs.create'), 'title'=>'+Create New'],
        ]];
       }

       if(auth()->user()->can('createUser', $organization)){
       $cards[] = (Object)['title'=>'Users', 'count'=> $organization->users()->count(), 'links'=> [
            ['url'=> route('my.users.index'), 'title'=>'View All'],
            ['url'=> route('my.users.create'), 'title'=>'+Create New'],
        ]];
       }

       if(auth()->user()->can('createCustomer', $organization)){
        $cards[] = (Object)['title'=>'Customers', 'count'=> $organization->customers()->count(), 'links'=> [
            ['url'=> route('my.customers.index'), 'title'=>'View All'],
            ['url'=> route('my.customers.create'), 'title'=>'+Create New'],
        ]];
       }
       
        if(Auth::user()->is_super){
            $organizations = \App\Models\Organization::all();
        }

        if(auth()->user()->can('work', $organization)){
            $jobs = PilotCarJob::
                orderBy('id','desc')
                ->with(['logs','customer'])
                ->whereHas('logs', function($query){
                    return $query->where('car_driver_id', auth()->user()->id);
                })
                ->get();
           
        }else{
            $jobs = false;
        }

        return view('livewire.dashboard', compact('organization', 'organizations','cards','jobs'));
    }

    public function uploadFile()
    {
        //$this->file->storeAs(path: 'jobs', name: 'org_logs_'.auth()->user()->organization_role.'_'.auth()->user()->organization_id.'_'.now()->timestamp .'.'.$this->file->extension());
        $originalName = $this->file->getClientOriginalName();
        $this->file->storeAs(path: 'jobs/org_'.auth()->user()->organization_id, name:$originalName);
        $this->dispatch('uploaded');

        $files = [[
            'full_path' => $this->file->getPathName(),
            'original_name' => $this->file->getClientOriginalName(),
            //'contents' => file_get_contents($this->file->getPathName())
        ]];

        PilotCarJob::import($files, auth()->user()->organization_id);

        return back();
    }

    public function deleteJobs(){
        UserLog::where('id','!=', 0)->forceDelete();
        PilotCarJob::where('id','!=', 0)->forceDelete();
        return back();
    }
}
