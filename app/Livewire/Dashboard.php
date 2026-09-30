<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\PilotCarJob;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth as AuthFacade;

class Dashboard extends Component
{

    public $data = [];

    public $component = null;

    use WithFileUploads;
 
    #[Validate('nullable|file|max:10240')] // 10MB Max
    public $file;
    
    public $headerMappings = [];
    public $showPreview = false;
    public $previewConfirmed = false;
    public $recordCount = 0;
    public $autoCreateInvoices = false;

    public function mount($component = null){
        $this->component = $component;
    }
    
    public function previewHeaders()
    {
        // Importing jobs is creating jobs: admins and managers of this organization (TASK-442).
        $this->authorize('createJob', auth()->user()->organization);

        if (!$this->file) {
            $this->addError('file', __('Please select a file before previewing.'));
            return;
        }

        try {
            if (!method_exists($this->file, 'getPathname') || !file_exists($this->file->getPathname())) {
                $this->addError('file', __('The selected file is invalid or could not be processed.'));
                return;
            }

            // Read header row and count records
            $handle = fopen($this->file->getPathname(), "r");
            if ($handle === FALSE) {
                $this->addError('file', __('Could not open file for reading.'));
                return;
            }

            // Read header row
            $data = fgetcsv($handle, separator: ",");
            if ($data === FALSE || empty($data)) {
                fclose($handle);
                $this->addError('file', __('Could not read headers from file.'));
                return;
            }

            // Count data rows (skip header)
            $recordCount = 0;
            while (($row = fgetcsv($handle, separator: ",")) !== FALSE) {
                // Only count non-empty rows (at least one non-empty field)
                $hasData = false;
                foreach ($row as $field) {
                    if (trim($field) !== '') {
                        $hasData = true;
                        break;
                    }
                }
                if ($hasData) {
                    $recordCount++;
                }
            }
            fclose($handle);
            
            $this->recordCount = $recordCount;

            // Trim trailing empty columns
            $originalHeaders = $data;
            while (!empty($originalHeaders) && trim(end($originalHeaders)) === '') {
                array_pop($originalHeaders);
            }

            // Normalize headers
            $normalizedHeaders = [];
            foreach($originalHeaders as $h){
                $normalized = str_replace('__','_',str_replace([' ','-'],'_',trim(str_replace(['#','(',')','/','?'],'', strtolower($h)))));
                $normalizedHeaders[] = $normalized;
            }

            // Get mappings - use a helper method that doesn't throw for preview
            $mappedHeaders = $this->previewHeaderMappings($normalizedHeaders, $originalHeaders);
            
            // Build mapping display
            $this->headerMappings = [];
            foreach($originalHeaders as $index => $original) {
                $this->headerMappings[] = [
                    'column' => $index + 1,
                    'original' => $original ?: '(empty)',
                    'normalized' => $normalizedHeaders[$index] ?? '',
                    'mapped_to' => $mappedHeaders[$index] ?? '(unmapped)',
                    'status' => isset($mappedHeaders[$index]) && $mappedHeaders[$index] !== null ? 'mapped' : 'unmapped'
                ];
            }
            
            $this->showPreview = true;
            $this->previewConfirmed = false;
        } catch (\Exception $e) {
            $this->addError('file', __('Error previewing file: :message', ['message' => $e->getMessage()]));
            $this->showPreview = false;
        }
    }
    
    private function previewHeaderMappings($normalizedHeaders, $originalHeaders)
    {
        // Use the same dictionary as translateHeaders but don't throw on unmapped
        $dictionary = \App\Models\PilotCarJob::getHeaderDictionary();
        $values = [];
        
        foreach($normalizedHeaders as $index => $hdr){
            $value = collect($dictionary)->filter(fn($entry)=> in_array($hdr, $entry))->keys()->first();
            $values[] = $value; // Can be null for unmapped - use array append to maintain index
        }
        
        return $values;
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
        if(auth()->user()->isSuper()){
            $links[] = ['url'=> route('organizations.onboard'), 'title'=>'Onboard New'];
        }
        $cards[] = (Object)['title'=>'Organizations', 'count'=> Organization::count(), 'links'=> $links];
       }

       // Experience Tracker for super users
       if(Auth::user()->isSuper()){
           $errorCount = \App\Models\UserEvent::errors()->whereDate('created_at', '>=', now()->subDays(7))->count();
           $cards[] = (Object)['title'=>'Experience Tracker', 'count'=> $errorCount, 'links'=> [
               ['url'=> route('user-events.index'), 'title'=>'View Events'],
           ]];
       }

       // Feedback + Requests.
       // Customers see only THEIR OWN submissions ("You have N open requests"):
       // count excludes closed (done/declined) tasks, and the recent list is
       // scoped to their own tasks. Staff (admin/manager/standard employees)
       // keep the org-wide triage view unchanged.
       // Card link goes to the Public Roadmap for everyone; super users
       // additionally get a direct path into the staff triage queue.
       $viewer = Auth::user();
       if (! $viewer->can('viewAny', \App\Models\Task::class)) {
           // Customers and drivers: their own submissions only.
           $recentFeedback = \App\Models\Task::with('submitter')
               ->where('submitter_user_id', $viewer->id)
               ->orderBy('updated_at', 'desc')
               ->take(5)
               ->get();
           $totalFeedback = \App\Models\Task::where('submitter_user_id', $viewer->id)
               ->whereNotIn('status', ['done', 'declined'])
               ->count();
       } else {
           // Tracker staff: the organization's triage queue. This used to be
           // every tenant's queue (TASK-439); a super user still sees all.
           $triage = \App\Models\Task::query()
               ->where('status', 'triage')
               ->when(! $viewer->isSuper(), fn ($q) => $q->where('organization_id', $viewer->organization_id));

           $recentFeedback = (clone $triage)->with('submitter')
               ->orderBy('created_at', 'desc')
               ->take(5)
               ->get();
           $totalFeedback = $triage->count();
       }

       $links = [
           ['url'=> route('documentation.roadmap'), 'title'=>'Public Roadmap'],
       ];
       if ($viewer->isSuper()) {
           array_unshift($links, ['url'=> route('tasks.index', ['status' => 'triage']), 'title'=>'View Triage']);
       }

       $cards[] = (Object)[
           'title' => 'Feedback + Requests',
           'count' => $totalFeedback,
           'links' => $links,
       ];

       if(auth()->user()->can('createJob', $organization)){
           $jobsCount = $organization->jobs()->count();
           $missingJobNoCount = $organization->jobs()->whereNull('job_no')->count();
           $jobsLinks = [
               ['url'=> route('my.jobs.index'), 'title'=>'View All'],
               ['url'=> route('my.jobs.create'), 'title'=>'+Create New'],
           ];
           // Add link to filter for missing job_no if there are any
           if ($missingJobNoCount > 0) {
               $jobsLinks[] = [
                   'url'=> route('my.jobs.index', ['search_field' => 'missing_job_no', 'search_value' => '']), 
                   'title'=> "Missing Job # ({$missingJobNoCount})"
               ];
           }
           $cards[] = (Object)['title'=>'Jobs', 'count'=> $jobsCount, 'links'=> $jobsLinks, 'missingJobNo'=> $missingJobNoCount];
       }

       $canManageUsers = auth()->user()->can('createUser', $organization);
       // Staff only: customer-portal accounts share the organization_id and
       // were counted as users of the company (TASK-472).
       $cards[] = (object) [
           'title' => 'Users',
           'count' => $organization->users()->whereIn('organization_role', [
               \App\Models\User::ROLE_ADMIN,
               \App\Models\User::ROLE_EMPLOYEE_MANAGER,
               \App\Models\User::ROLE_EMPLOYEE_STANDARD,
           ])->count(),
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
       
        if(Auth::user()->isSuper()){
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
            // Counted in the database, not by loading every job and invoice into
            // memory on each render (TASK-472). A job is completed when a
            // non-void single invoice or a summary invoice bills it, cancelled
            // when canceled_at is set, active otherwise: the same rule as
            // PilotCarJob::getStatusAttribute().
            $jobQuery = fn () => $organization->jobs();
            $billed = function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('singleInvoices', fn ($i) => $i->notVoid())
                        ->orWhereHas('summaryInvoices');
                });
            };

            $totalJobs = $jobQuery()->count();
            $cancelledJobs = $jobQuery()->whereNotNull('canceled_at')->count();
            $completedJobs = $jobQuery()->whereNull('canceled_at')->where($billed)->count();
            $activeJobs = $totalJobs - $cancelledJobs - $completedJobs;
            $missingJobNo = $jobQuery()->whereNull('job_no')->count();

            // Void invoices are not bills (TASK-480). Revenue, the unpaid count
            // and the outstanding amount all read the same set of single
            // invoices, whatever their import source: the old revenue figure
            // counted CSV imports only, so nothing invoiced in the app ever
            // moved it, and "Unpaid" counted summaries and children while
            // "Outstanding" did not (TASK-472).
            $invoices = fn () => \App\Models\Invoice::where('organization_id', $organization->id)->notVoid();
            $totalInvoices = $invoices()->count();
            $totalRevenue = \App\Models\Invoice::sumTotals($invoices()->single());
            $unpaidInvoices = $invoices()->single()->where('paid_in_full', false)->count();
            $unpaidAmount = \App\Models\Invoice::sumTotals($invoices()->single()->where('paid_in_full', false));

            // Calculate total account credits
            $totalAccountCredits = \App\Models\Customer::where('organization_id', $organization->id)
                ->sum('account_credit');

            $managerStats = (object)[
                'total_jobs' => $totalJobs,
                'active_jobs' => $activeJobs,
                'cancelled_jobs' => $cancelledJobs,
                'completed_jobs' => $completedJobs,
                'missing_job_no' => $missingJobNo,
                'total_invoices' => $totalInvoices,
                'unpaid_invoices' => $unpaidInvoices,
                'total_revenue' => $totalRevenue,
                'unpaid_amount' => $unpaidAmount,
                'total_account_credits' => (float)$totalAccountCredits,
            ];
            
            // Get jobs with invoices marked for attention
            // Check both direct pilot_car_job_id (single invoices) and via summary_invoice_jobs pivot (summary invoices)
            $invoicesMarkedForAttention = \App\Models\Invoice::where('organization_id', $organization->id)
                ->where('marked_for_attention', true)
                ->get();
            
            // Single invoices: get job IDs from pilot_car_job_id
            $jobIdsFromDirect = $invoicesMarkedForAttention
                ->where('invoice_type', '!=', 'summary')
                ->pluck('pilot_car_job_id')
                ->filter()
                ->unique();
            
            // Summary invoices: get job IDs from pivot table
            $summaryInvoiceIds = $invoicesMarkedForAttention
                ->where('invoice_type', 'summary')
                ->pluck('id');
            $jobIdsFromPivot = \App\Models\JobInvoice::whereIn('invoice_id', $summaryInvoiceIds)
                ->pluck('pilot_car_job_id')
                ->unique();
            
            $allJobIdsMarked = $jobIdsFromDirect->merge($jobIdsFromPivot)->unique();
            
            $jobsMarkedForAttention = $organization->jobs()
                ->with(['customer', 'singleInvoices', 'summaryInvoices'])
                ->whereIn('id', $allJobIdsMarked)
                ->orderByDesc('scheduled_pickup_at')
                ->get();
            
            // Ten open jobs, newest pickup first; when there are none, the ten
            // most recent jobs of any state.
            $recentJobs = $jobQuery()
                ->whereNull('canceled_at')
                ->whereDoesntHave('singleInvoices', fn ($i) => $i->notVoid())
                ->whereDoesntHave('summaryInvoices')
                ->with(['customer', 'singleInvoices', 'summaryInvoices', 'logs'])
                ->orderByDesc('scheduled_pickup_at')
                ->take(10)
                ->get();

            if ($recentJobs->isEmpty()) {
                $recentJobs = $jobQuery()
                    ->with(['customer', 'singleInvoices', 'summaryInvoices', 'logs'])
                    ->orderByDesc('scheduled_pickup_at')
                    ->take(10)
                    ->get();
            }
        }

        return view('livewire.dashboard', compact('organization', 'organizations','cards','jobs', 'managerStats', 'recentJobs', 'jobsMarkedForAttention', 'recentFeedback', 'totalFeedback'));
    }

    public function confirmImport()
    {
        // Importing jobs is creating jobs: admins and managers of this organization (TASK-442).
        $this->authorize('createJob', auth()->user()->organization);

        // Clear previous errors and hide preview
        $this->resetErrorBag();
        session()->forget(['error', 'success']);
        $this->showPreview = false;
        $this->previewConfirmed = true;
        
        $this->uploadFile();
    }
    
    public function uploadFile()
    {
        // Importing jobs is creating jobs: admins and managers of this organization (TASK-442).
        $this->authorize('createJob', auth()->user()->organization);

        // Backend safety net: Check if file exists
        if (!$this->file) {
            $this->addError('file', __('Please select a file before uploading.'));
            return;
        }

        // If preview is shown but not confirmed, require confirmation
        if ($this->showPreview && !$this->previewConfirmed) {
            $this->addError('file', __('Please confirm the import by clicking "Confirm and Import".'));
            return;
        }

        // Validate file exists and is valid
        try {
            if (!method_exists($this->file, 'getPathname') || !file_exists($this->file->getPathname())) {
                $this->addError('file', __('The selected file is invalid or could not be processed.'));
                return;
            }

            $originalName = $this->file->getClientOriginalName();
            $this->file->storeAs(path: 'jobs/org_'.auth()->user()->organization_id, name:$originalName);
            
            $files = [[
                'full_path' => $this->file->getPathName(),
                'original_name' => $this->file->getClientOriginalName(),
                //'contents' => file_get_contents($this->file->getPathName())
            ]];

            PilotCarJob::import($files, auth()->user()->organization_id, $this->autoCreateInvoices);
            
            // Only dispatch success if import completed without throwing
            $this->dispatch('uploaded');
            session()->flash('success', __('File uploaded and imported successfully.'));
            
            // Reset preview state
            $this->showPreview = false;
            $this->previewConfirmed = false;
            $this->headerMappings = [];
            $this->recordCount = 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('File upload/import error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // Don't duplicate "Import failed:" prefix if it's already in the message
            $errorMessage = $e->getMessage();
            if (str_starts_with($errorMessage, 'Import failed:')) {
                $displayMessage = $errorMessage;
            } else {
                $displayMessage = __('Import failed: :message', ['message' => $errorMessage]);
            }
            $this->addError('file', $displayMessage);
        }
    }

    /**
     * Empty THIS organization's jobs, logs and invoices.
     *
     * This used to be the platform-wide wipe -- every statement was
     * where('id', '!=', 0), i.e. every row in the table -- so resetting your own
     * test data took every other organization's with it. Wanting a clean slate
     * for yourself is the ordinary case; wanting one for everybody is not, so
     * the ordinary case is what this button now does. nuclearReset() below
     * still exists for the rare time you mean it.
     */
    public function resetOrganization(){
        $user = auth()->user();

        abort_unless($user->isSuper() || $user->isAdmin(), 403);

        $this->purge($user->organization_id);

        return back();
    }

    /**
     * Empty EVERY organization's jobs, logs and invoices.
     *
     * Super users only, and checked here rather than trusted to the blade: the
     * section this is rendered in is wrapped in an isSuper() check, but a
     * Livewire action is its own callable endpoint and never sees that markup.
     */
    public function nuclearReset(){
        abort_unless(auth()->user()->isSuper(), 403);

        $this->purge(null);

        return back();
    }

    /**
     * @param  int|null  $organizationId  null means every organization.
     */
    private function purge(?int $organizationId): void
    {
        $jobs = PilotCarJob::withTrashed();
        $invoices = \App\Models\Invoice::withTrashed();
        $logs = UserLog::query();

        if ($organizationId !== null) {
            $jobs->where('organization_id', $organizationId);
            $invoices->where('organization_id', $organizationId);
            $logs->where('organization_id', $organizationId);
        }

        $jobIds = (clone $jobs)->pluck('id');
        $invoiceIds = (clone $invoices)->pluck('id');
        $logIds = (clone $logs)->pluck('id');

        // Mass deletes below fire no model events, so the files on the jobs
        // and logs being purged are removed here, row by row (TASK-454).
        \App\Models\Attachment::withTrashed()
            ->where(function ($q) use ($jobIds, $logIds) {
                $q->where(fn ($j) => $j->where('attachable_type', PilotCarJob::class)->whereIn('attachable_id', $jobIds))
                    ->orWhere(fn ($l) => $l->where('attachable_type', UserLog::class)->whereIn('attachable_id', $logIds));
            })
            ->get()
            ->each
            ->forceDelete();

        // The pivot first: it carries no organization of its own, so it has to
        // be reached through the rows being removed rather than scoped directly.
        \App\Models\JobInvoice::whereIn('invoice_id', $invoiceIds)
            ->orWhereIn('pilot_car_job_id', $jobIds)
            ->delete();

        \App\Models\Invoice::withTrashed()->whereIn('id', $invoiceIds)->forceDelete();
        $logs->forceDelete();
        PilotCarJob::withTrashed()->whereIn('id', $jobIds)->forceDelete();
    }

    /**
     * Confirm a log assignment from the dashboard.
     */
    public function confirmLog(int $logId): void
    {
        $log = UserLog::findOrFail($logId);
        $this->authorize('confirm', $log);

        $log->update([
            'approval_status' => 'confirmed',
            'approved_at' => now(),
            'approved_by_id' => AuthFacade::id(),
        ]);

        session()->flash('success', __('Log confirmed successfully.'));
    }

    /**
     * Deny a log assignment from the dashboard.
     */
    public function denyLog(int $logId): void
    {
        $log = UserLog::findOrFail($logId);
        $this->authorize('deny', $log);

        $log->update([
            'approval_status' => 'denied',
            'approved_at' => now(),
            'approved_by_id' => AuthFacade::id(),
        ]);

        session()->flash('success', __('Log denied.'));
    }
}
