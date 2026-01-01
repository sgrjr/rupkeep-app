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
        $links = [
             ['url'=> route('organizations.index'), 'title'=>'View All'],
             ['url'=> route('organizations.create'), 'title'=>'+Create New'],
         ];
        if(auth()->user()->is_super){
            $links[] = ['url'=> route('organizations.onboard'), 'title'=>'Onboard New'];
        }
        $cards[] = (Object)['title'=>'Organizations', 'count'=> Organization::count(), 'links'=> $links];
       }

       // Experience Tracker for super users
       if(Auth::user()->is_super){
           $errorCount = \App\Models\UserEvent::errors()->whereDate('created_at', '>=', now()->subDays(7))->count();
           $cards[] = (Object)['title'=>'Experience Tracker', 'count'=> $errorCount, 'links'=> [
               ['url'=> route('user-events.index'), 'title'=>'View Events'],
           ]];
           
           // Recent feedback for super users
           $recentFeedback = \App\Models\UserEvent::where('type', \App\Models\UserEvent::TYPE_FEEDBACK)
               ->with('user')
               ->orderBy('created_at', 'desc')
               ->take(5)
               ->get();
           $totalFeedback = \App\Models\UserEvent::where('type', \App\Models\UserEvent::TYPE_FEEDBACK)->count();
           
           // Add Feedback card for super users
           $cards[] = (Object)['title'=>'Feedback', 'count'=> $totalFeedback, 'links'=> [
               ['url'=> route('admin.feedback.index'), 'title'=>'View All'],
           ]];
       } else {
           $recentFeedback = collect();
           $totalFeedback = 0;
       }

       if(auth()->user()->can('createJob', $organization)){
       $cards[] = (Object)['title'=>'Jobs', 'count'=> $organization->jobs()->count(), 'links'=> [
            ['url'=> route('my.jobs.index'), 'title'=>'View All'],
            ['url'=> route('my.jobs.create'), 'title'=>'+Create New'],
        ]];
       }

       $canManageUsers = auth()->user()->can('createUser', $organization);
       $cards[] = (object) [
           'title' => 'Users',
           'count' => $organization->users()->count(),
           'links' => array_filter([
               ['url' => route('my.users.index'), 'title' => 'View All'],
               $canManageUsers ? ['url' => route('my.users.create'), 'title' => '+Create New'] : null,
           ]),
       ];

       if(auth()->user()->can('createCustomer', $organization)){
        $cards[] = (Object)['title'=>'Customers', 'count'=> $organization->customers()->count(), 'links'=> [
            ['url'=> route('my.customers.index'), 'title'=>'View All'],
            ['url'=> route('my.customers.create'), 'title'=>'+Create New'],
        ]];
       }

       if(auth()->user()->can('createVehicle', $organization)){
        $cards[] = (Object)['title'=>'Vehicles', 'count'=> $organization->vehicles()->count(), 'links'=> [
            ['url'=> route('my.vehicles.index'), 'title'=>'View All'],
            ['url'=> route('my.vehicles.create'), 'title'=>'+Create New'],
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

        // Manager dashboard stats
        $managerStats = null;
        $recentJobs = null;
        $jobsMarkedForAttention = null;
        if(auth()->user()->can('createJob', $organization)){
            $allJobs = $organization->jobs()->with(['customer', 'invoices'])->get();
            
            // Calculate job statuses
            $activeJobs = $allJobs->filter(fn($job) => $job->status === 'ACTIVE');
            $cancelledJobs = $allJobs->filter(fn($job) => in_array($job->status, ['CANCELLED', 'CANCELLED_NO_GO']));
            $completedJobs = $allJobs->filter(fn($job) => $job->status === 'COMPLETED');
            
            // Calculate invoice stats
            $allInvoices = \App\Models\Invoice::where('organization_id', $organization->id)->get();
            $unpaidInvoices = $allInvoices->filter(fn($inv) => !$inv->paid_in_full);
            $totalRevenue = $allInvoices->sum(fn($inv) => (float)($inv->values['total'] ?? 0));
            $unpaidAmount = $unpaidInvoices->sum(fn($inv) => (float)($inv->values['total'] ?? 0));
            
            // Calculate total account credits
            $totalAccountCredits = \App\Models\Customer::where('organization_id', $organization->id)
                ->sum('account_credit');
            
            $managerStats = (object)[
                'total_jobs' => $allJobs->count(),
                'active_jobs' => $activeJobs->count(),
                'cancelled_jobs' => $cancelledJobs->count(),
                'completed_jobs' => $completedJobs->count(),
                'total_invoices' => $allInvoices->count(),
                'unpaid_invoices' => $unpaidInvoices->count(),
                'total_revenue' => $totalRevenue,
                'unpaid_amount' => $unpaidAmount,
                'total_account_credits' => (float)$totalAccountCredits,
            ];
            
            // Get jobs with invoices marked for attention
            // Check both direct pilot_car_job_id and via jobs_invoices pivot table
            $invoicesMarkedForAttention = \App\Models\Invoice::where('organization_id', $organization->id)
                ->where('marked_for_attention', true)
                ->get();
            
            $jobIdsFromDirect = $invoicesMarkedForAttention->pluck('pilot_car_job_id')->filter()->unique();
            $jobIdsFromPivot = \App\Models\JobInvoice::whereIn('invoice_id', $invoicesMarkedForAttention->pluck('id'))
                ->pluck('pilot_car_job_id')
                ->unique();
            
            $allJobIdsMarked = $jobIdsFromDirect->merge($jobIdsFromPivot)->unique();
            
            $jobsMarkedForAttention = $organization->jobs()
                ->with(['customer', 'invoices'])
                ->whereIn('id', $allJobIdsMarked)
                ->orderByDesc('scheduled_pickup_at')
                ->get();
            
            // Get recent jobs (last 10)
            $recentJobs = $allJobs->sortByDesc('created_at')->take(10);
        }

        return view('livewire.dashboard', compact('organization', 'organizations','cards','jobs', 'managerStats', 'recentJobs', 'jobsMarkedForAttention', 'recentFeedback', 'totalFeedback'));
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
        // Delete invoices and their pivot table entries first
        \App\Models\JobInvoice::where('id', '!=', 0)->delete();
        \App\Models\Invoice::where('id', '!=', 0)->forceDelete();
        
        // Delete logs
        UserLog::where('id','!=', 0)->forceDelete();
        
        // Delete jobs (this should cascade, but being explicit)
        PilotCarJob::where('id','!=', 0)->forceDelete();
        
        return back();
    }
}
