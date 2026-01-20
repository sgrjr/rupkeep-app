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
            $allJobs = $organization->jobs()->with(['customer', 'singleInvoices', 'summaryInvoices'])->get();
            
            // Calculate job statuses
            $activeJobs = $allJobs->filter(fn($job) => $job->status === 'ACTIVE');
            $cancelledJobs = $allJobs->filter(fn($job) => in_array($job->status, ['CANCELLED', 'CANCELLED_NO_GO']));
            $completedJobs = $allJobs->filter(fn($job) => $job->status === 'COMPLETED');
            $missingJobNo = $allJobs->filter(fn($job) => $job->job_no === null)->count();
            
            // Calculate invoice stats
            // Only count single invoices (non-summary, non-child) for revenue to avoid double-counting
            // Summary invoices contain totals from their child invoices, so counting both would duplicate amounts
            $allInvoices = \App\Models\Invoice::where('organization_id', $organization->id)->get();
            $singleInvoices = $allInvoices->filter(fn($inv) => 
                $inv->invoice_type !== 'summary' && $inv->parent_invoice_id === null
            );
            $unpaidInvoices = $allInvoices->filter(fn($inv) => !$inv->paid_in_full);
            $unpaidSingleInvoices = $singleInvoices->filter(fn($inv) => !$inv->paid_in_full);
            
            // Diagnostic: Analyze invoice totals and sources
            $revenueBreakdown = [
                'csv_source' => ['count' => 0, 'total' => 0.0],
                'calculated_source' => ['count' => 0, 'total' => 0.0],
                'unknown_source' => ['count' => 0, 'total' => 0.0],
                'zero_totals' => 0,
                'null_totals' => 0,
                'invalid_totals' => []
            ];
            
            foreach ($singleInvoices as $inv) {
                $values = $inv->values ?? [];
                $total = $values['total'] ?? null;
                $source = $values['import_source'] ?? 'unknown';
                
                if ($total === null) {
                    $revenueBreakdown['null_totals']++;
                    $revenueBreakdown['invalid_totals'][] = [
                        'invoice_id' => $inv->id,
                        'job_id' => $inv->pilot_car_job_id,
                        'issue' => 'null_total'
                    ];
                } elseif ((float)$total == 0) {
                    $revenueBreakdown['zero_totals']++;
                } else {
                    $totalFloat = (float)$total;
                    if ($source === 'csv') {
                        $revenueBreakdown['csv_source']['count']++;
                        $revenueBreakdown['csv_source']['total'] += $totalFloat;
                    } elseif ($source === 'calculated') {
                        $revenueBreakdown['calculated_source']['count']++;
                        $revenueBreakdown['calculated_source']['total'] += $totalFloat;
                    } else {
                        $revenueBreakdown['unknown_source']['count']++;
                        $revenueBreakdown['unknown_source']['total'] += $totalFloat;
                    }
                }
            }
            
            // Total revenue = sum of single invoices with CSV source only (excludes calculated/unknown source invoices)
            // This ensures revenue matches the CSV import total exactly
            $totalRevenue = $singleInvoices
                ->filter(fn($inv) => ($inv->values['import_source'] ?? 'unknown') === 'csv')
                ->sum(fn($inv) => (float)($inv->values['total'] ?? 0));
            // Outstanding = sum of unpaid single invoices only
            $unpaidAmount = $unpaidSingleInvoices->sum(fn($inv) => (float)($inv->values['total'] ?? 0));
            
            // Log diagnostic information
            \Illuminate\Support\Facades\Log::info('Dashboard: Revenue calculation diagnostics', [
                'organization_id' => $organization->id,
                'total_single_invoices' => $singleInvoices->count(),
                'total_revenue' => $totalRevenue,
                'revenue_breakdown' => $revenueBreakdown,
                'unpaid_amount' => $unpaidAmount,
                'unpaid_invoices_count' => $unpaidSingleInvoices->count(),
                'validation' => [
                    'has_null_totals' => $revenueBreakdown['null_totals'] > 0,
                    'has_zero_totals' => $revenueBreakdown['zero_totals'] > 0,
                    'has_invalid_totals' => count($revenueBreakdown['invalid_totals']) > 0,
                    'csv_vs_calculated_ratio' => $revenueBreakdown['calculated_source']['total'] > 0 
                        ? round(($revenueBreakdown['csv_source']['total'] / $revenueBreakdown['calculated_source']['total']) * 100, 2)
                        : 0
                ]
            ]);
            
            // Log warnings for issues
            if ($revenueBreakdown['null_totals'] > 0 || count($revenueBreakdown['invalid_totals']) > 0) {
                \Illuminate\Support\Facades\Log::warning('Dashboard: Invoices with invalid totals detected', [
                    'null_totals_count' => $revenueBreakdown['null_totals'],
                    'invalid_totals' => array_slice($revenueBreakdown['invalid_totals'], 0, 10) // Limit to first 10
                ]);
            }
            
            // Calculate total account credits
            $totalAccountCredits = \App\Models\Customer::where('organization_id', $organization->id)
                ->sum('account_credit');
            
            $managerStats = (object)[
                'total_jobs' => $allJobs->count(),
                'active_jobs' => $activeJobs->count(),
                'cancelled_jobs' => $cancelledJobs->count(),
                'completed_jobs' => $completedJobs->count(),
                'missing_job_no' => $missingJobNo,
                'total_invoices' => $allInvoices->count(),
                'unpaid_invoices' => $unpaidInvoices->count(),
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
            
            // Get incomplete jobs first (not completed, not cancelled) - ordered by scheduled_pickup_at newest first
            $incompleteJobs = $allJobs->filter(function($job) {
                $status = $job->status;
                return $status !== 'COMPLETED' && 
                       $status !== 'CANCELLED' && 
                       $status !== 'CANCELLED_NO_GO';
            })
            ->sortByDesc('scheduled_pickup_at')
            ->take(10);
            
            // If no incomplete jobs, fall back to recent jobs (all jobs ordered by scheduled_pickup_at newest first)
            if ($incompleteJobs->count() > 0) {
                $recentJobs = $incompleteJobs;
            } else {
                $recentJobs = $allJobs->sortByDesc('scheduled_pickup_at')->take(10);
            }
        }

        return view('livewire.dashboard', compact('organization', 'organizations','cards','jobs', 'managerStats', 'recentJobs', 'jobsMarkedForAttention', 'recentFeedback', 'totalFeedback'));
    }

    public function confirmImport()
    {
        // Clear previous errors and hide preview
        $this->resetErrorBag();
        session()->forget(['error', 'success']);
        $this->showPreview = false;
        $this->previewConfirmed = true;
        
        $this->uploadFile();
    }
    
    public function uploadFile()
    {
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

    public function deleteJobs(){
        // Delete all entries from the pivot table (summary invoices only)
        \App\Models\JobInvoice::where('id', '!=', 0)->delete();
        
        // Delete all invoices (single invoices don't have pivot entries)
        \App\Models\Invoice::where('id', '!=', 0)->forceDelete();
        
        // Delete logs
        UserLog::where('id','!=', 0)->forceDelete();
        
        // Delete jobs (this should cascade, but being explicit)
        PilotCarJob::where('id','!=', 0)->forceDelete();
        
        return back();
    }
}
