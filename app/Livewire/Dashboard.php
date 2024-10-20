<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\PilotCarJob;

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

       $cards[] = (Object)['title'=>'Jobs', 'count'=> $organization->jobs()->count(), 'links'=> [
            ['url'=> route('my.jobs.index'), 'title'=>'View All'],
            ['url'=> route('my.jobs.create'), 'title'=>'+Create New'],
        ]];

       $cards[] = (Object)['title'=>'Users', 'count'=> $organization->users()->count(), 'links'=> [
            ['url'=> route('my.users.index'), 'title'=>'View All'],
            ['url'=> route('my.users.create'), 'title'=>'+Create New'],
        ]];

        $cards[] = (Object)['title'=>'Customers', 'count'=> $organization->customers()->count(), 'links'=> [
            ['url'=> route('my.customers.index'), 'title'=>'View All'],
            ['url'=> route('my.customers.create'), 'title'=>'+Create New'],
        ]];
       
        if(Auth::user()->is_super){
            $organizations = \App\Models\Organization::all();
        }

        return view('livewire.dashboard', compact('organization', 'organizations','cards'));
    }

    public function uploadFile()
    {
        //$this->file->storeAs(path: 'jobs', name: 'org_logs_'.auth()->user()->organization_role.'_'.auth()->user()->organization_id.'_'.now()->timestamp .'.'.$this->file->extension());
        $originalName = $this->file->getClientOriginalName();
        $this->file->storeAs(path: 'jobs/org_'.auth()->user()->organization_id, name:$originalName);
        $this->dispatch('uploaded');

        $files = [[
            'original_name' => $this->file->getClientOriginalName(),
            'contents' => file_get_contents($this->file->getPathName())
        ]];

        PilotCarJob::import($files, auth()->user()->organization_id);

        return back();
    }
}
