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
use Illuminate\Support\Facades\Log;

class PilotCarJob extends Model
{
    use HasFactory, SoftDeletes, HasJobScopes;
    public $timestamps = true;
    
    /**
     * Retrieve the model for route model binding, including soft-deleted models.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
    
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

    /**
     * Get single invoices linked to this job via pilot_car_job_id.
     * These are regular invoices that belong to one job.
     */
    public function singleInvoices()
    {
        return $this->hasMany(Invoice::class, 'pilot_car_job_id')
            ->where('invoice_type', '!=', 'summary')
            ->whereNull('parent_invoice_id');
    }

    /**
     * Get summary invoices linked to this job via pivot table.
     * Summary invoices can link to multiple jobs.
     */
    public function summaryInvoices()
    {
        return $this->belongsToMany(Invoice::class, 'summary_invoice_jobs')
            ->where('invoice_type', 'summary');
    }

    /**
     * Get all invoices for this job (both single and summary).
     * This is a relationship method that can be eager loaded.
     * When accessed as a property, it merges singleInvoices and summaryInvoices.
     */
    public function invoices()
    {
        // Return a relationship that merges both single and summary invoices
        // We'll use a custom approach: return singleInvoices as the base relationship
        // and handle merging in the accessor
        return $this->singleInvoices();
    }

    /**
     * Accessor for invoices property.
     * Merges singleInvoices and summaryInvoices when accessed as $job->invoices.
     */
    public function getInvoicesAttribute()
    {
        // If both relationships are eager loaded, merge them
        if ($this->relationLoaded('singleInvoices') && $this->relationLoaded('summaryInvoices')) {
            return $this->getRelation('singleInvoices')
                ->merge($this->getRelation('summaryInvoices'))
                ->unique('id');
        }
        
        // If 'invoices' was eager loaded (which loads as singleInvoices), get it and merge with summaryInvoices
        if ($this->relationLoaded('invoices')) {
            $singleInvoices = $this->getRelation('invoices');
            $summaryInvoices = $this->relationLoaded('summaryInvoices')
                ? $this->getRelation('summaryInvoices')
                : $this->summaryInvoices()->get();
            return $singleInvoices->merge($summaryInvoices)->unique('id');
        }
        
        // If only singleInvoices is loaded, load summaryInvoices and merge
        if ($this->relationLoaded('singleInvoices')) {
            $singleInvoices = $this->getRelation('singleInvoices');
            $summaryInvoices = $this->summaryInvoices()->get();
            return $singleInvoices->merge($summaryInvoices)->unique('id');
        }
        
        // Fallback: load both and merge
        return $this->getAllInvoices();
    }

    /**
     * Get all invoices for this job from both single and summary relationships.
     * This ensures we catch invoices linked either way.
     * Uses eager-loaded relationships if available.
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllInvoices()
    {
        // Use eager-loaded relationships if available, otherwise query
        $singleInvoices = $this->relationLoaded('singleInvoices') 
            ? $this->getRelation('singleInvoices')
            : Invoice::where('pilot_car_job_id', $this->id)
                ->where('invoice_type', '!=', 'summary')
                ->whereNull('parent_invoice_id')
                ->get();
        
        $summaryInvoices = $this->relationLoaded('summaryInvoices')
            ? $this->getRelation('summaryInvoices')
            : $this->summaryInvoices()->get();
        
        // Merge and deduplicate by ID
        return $singleInvoices->merge($summaryInvoices)->unique('id');
    }

    /**
     * Create a single invoice for this job.
     * 
     * Single invoices use pilot_car_job_id directly (no pivot table entry).
     * This method should NOT be used for summary invoices - they are created differently.
     * 
     * @param array $invoiceValues Invoice data (will be merged with job/customer data)
     * @return \App\Models\Invoice
     */
    public function createInvoice(array $invoiceValues = [])
    {
        // Ensure invoice values include required fields
        if (empty($invoiceValues)) {
            $invoiceValues = $this->invoiceValues();
        }
        
        // Ensure pilot_car_job_id is set and invoice_type is single
        $invoiceValues['pilot_car_job_id'] = $this->id;
        $invoiceValues['invoice_type'] = $invoiceValues['invoice_type'] ?? 'single';
        
        // Create invoice directly (no pivot table entry for single invoices)
        $invoice = Invoice::create($invoiceValues);
        
        return $invoice;
    }

    public function getInvoicesCountAttribute(){
        return $this->getAllInvoices()->count();
    }

    public function logSchema(){
        $log = new UserLog([
            'job_id' => $this->id,
        ]);

        return $log->schema()->remove(['id'])->hide(['maintenance_memo']);
    }
    public static function import($files, $organization_id, $autoCreateInvoices = false){
        // Increase execution time limit for bulk import operations
        set_time_limit(600); // 10 minutes should be sufficient for large imports
        
        //add each file details to database

        $number = 0;
        $header = [];
        $l = [];
        $errors = [];
        $skippedRows = [];
        $processedJobs = [];
        $paymentData = []; // Store payment data: job_id => [total_cost, check_no, invoice_no, etc.]
        $csvInvoiceTotals = []; // Store CSV total_cost per job: job_id => total_cost from CSV

        // Check if database is empty - if so, skip deduplication logic entirely
        $existingJobCount = static::where('organization_id', $organization_id)->count();
        $skipDeduplication = $existingJobCount === 0;
        
        if ($skipDeduplication) {
            Log::info('Import: Database is empty - skipping deduplication logic for all CSV entries', [
                'organization_id' => $organization_id,
                'existing_job_count' => $existingJobCount
            ]);
        } else {
            Log::info('Import: Database has existing jobs - deduplication will be performed', [
                'organization_id' => $organization_id,
                'existing_job_count' => $existingJobCount
            ]);
        }

        try {
            if (!isset($files[0]['full_path']) || !file_exists($files[0]['full_path'])) {
                throw new \Exception('Import file not found or invalid.');
            }

            if (($handle = fopen($files[0]['full_path'], "r")) !== FALSE) {
                while (($data = fgetcsv($handle, separator: ",")) !== FALSE) {  
                    if($number == 0){
                        $originalHeaders = $data; // Keep original headers for error reporting
                        
                        // Trim trailing empty columns from headers (same as preview)
                        while (!empty($originalHeaders) && trim(end($originalHeaders)) === '') {
                            array_pop($originalHeaders);
                        }
                        
                        $h_eader = [];
                        foreach($originalHeaders as $h){
                            $normalized = str_replace('__','_',str_replace([' ','-'],'_',trim(str_replace(['#','(',')','/','?'],'', strtolower($h)))));
                            $h_eader[] = $normalized;
                        }
            
                        try {
                            // Pass the trimmed original headers to translateHeaders
                            $header = static::translateHeaders($h_eader, $originalHeaders);
                        } catch (\Exception $e) {
                            fclose($handle);
                            throw $e;
                        }
                    }else{
                        // Trim trailing empty columns from data rows to match trimmed headers
                        while (!empty($data) && trim(end($data)) === '') {
                            array_pop($data);
                        }
                        
                        $expectedColumnCount = count($header);
                        
                        // STRICT MODE: Require exact column count (after trimming empty columns)
                        if(count($data) != $expectedColumnCount){
                            $errorMsg = "Row " . ($number + 1) . " has " . count($data) . " columns but expected {$expectedColumnCount}. Please check your CSV file format.";
                            Log::error('Import: Row with incorrect column count', ['line' => $number, 'expected' => $expectedColumnCount, 'actual' => count($data)]);
                            $errors[] = $errorMsg;
                            // Continue collecting errors but don't process this row
                        }else{
                            $new_values = [];
                            foreach($data as $index=>$v){
                                if(isset($header[$index]) && $header[$index] !== null){
                                    $new_values[$header[$index]] = $v;
                                }
                            }
                            
                            // STRICT MODE: Validation failures are collected and reported
                            try {
                                $expectedCount = count($header);
                                static::validate($new_values, $expectedCount);
                                $l[] = $new_values;
                            } catch (\Exception $e) {
                                $errorMsg = "Row " . ($number + 1) . ": " . $e->getMessage();
                                Log::error('Import: Validation failed for row', ['line' => $number, 'error' => $e->getMessage(), 'row_data' => $new_values]);
                                $errors[] = $errorMsg;
                                // Continue collecting errors but don't process this row
                            }
                        }
                        
                    }
                    $number++;
                }
                fclose($handle);
            } else {
                throw new \Exception('Could not open import file for reading.');
            }
              
            // STRICT MODE: Don't process any rows if there are validation errors
            if (!empty($errors)) {
                $errorCount = count($errors);
                $errorSummary = implode("\n", array_slice($errors, 0, 10));
                if ($errorCount > 10) {
                    $errorSummary .= "\n... and " . ($errorCount - 10) . " more error(s)";
                }
                Log::error('Import failed: Validation errors detected', ['error_count' => $errorCount, 'errors' => $errors, 'valid_rows' => count($l)]);
                throw new \Exception("Import failed with {$errorCount} error(s). Please fix these issues and try again:\n\n" . $errorSummary);
            }

            // Only process rows if validation passed
            // Disable events during import to prevent job assignment notifications
            $originalDispatcher = UserLog::getEventDispatcher();
            UserLog::unsetEventDispatcher();
            
            try {
                foreach($l as $lineIndex => $line){
                    try {
                        $result = static::processLog($line, $organization_id, $processedJobs, $skipDeduplication);
                        if ($result === 'skipped') {
                            $skippedRows[] = [
                                'row' => $lineIndex + 2, // +2 because line 1 is header, line 2 is first data row
                                'reason' => 'Skipped for other reason',
                                'job_no' => $line['job_no'] ?? 'MISSING'
                            ];
                        } elseif ($result && isset($result['job_id'])) {
                            // Log if job_no was missing (but still imported)
                            if (empty(trim($line['job_no'] ?? ''))) {
                                $skippedRows[] = [
                                    'row' => $lineIndex + 2,
                                    'reason' => 'Missing job_no (imported with job_no=null)',
                                    'job_no' => 'MISSING',
                                    'job_id' => $result['job_id']
                                ];
                            }
                            $processedJobs[] = $result['job_id'];
                            // Log if job or log was created vs found existing
                            if (isset($result['job_created']) && $result['job_created']) {
                                Log::debug('Import: New job created', ['job_id' => $result['job_id'], 'row' => $lineIndex + 2]);
                            }
                            if (isset($result['log_created']) && !$result['log_created'] && isset($result['log_id'])) {
                                Log::debug('Import: Using existing log', ['log_id' => $result['log_id'], 'job_id' => $result['job_id'], 'row' => $lineIndex + 2]);
                            }
                            
                            // Store CSV total_cost for ALL jobs (not just paid ones)
                            // If multiple rows exist for same job, use LAST row's value (most recent)
                            // Each CSV line should be a unique job, so multiple rows per job_id indicates a matching issue
                            
                            // Diagnostic: Log available keys in line to debug mapping issues
                            if ($lineIndex < 5) { // Only log first 5 rows to avoid spam
                                Log::debug('Import: Sample CSV row keys', [
                                    'row' => $lineIndex + 2,
                                    'available_keys' => array_keys($line),
                                    'has_total_cost' => isset($line['total_cost']),
                                    'total_cost_value' => $line['total_cost'] ?? 'NOT_SET'
                                ]);
                            }
                            
                            if (isset($line['total_cost'])) {
                                $csvTotal = static::normalizeNumericValue($line['total_cost']);
                                if ($csvTotal > 0) {
                                    if (isset($csvInvoiceTotals[$result['job_id']])) {
                                        // Multiple rows for same job - use the last (most recent) value
                                        $existingTotal = $csvInvoiceTotals[$result['job_id']];
                                        if (abs($existingTotal - $csvTotal) > 0.01) {
                                            Log::warning('Import: Multiple total_cost values for same job - using last row value', [
                                                'job_id' => $result['job_id'],
                                                'previous_total' => $existingTotal,
                                                'new_total' => $csvTotal,
                                                'difference' => abs($existingTotal - $csvTotal),
                                                'row' => $lineIndex + 2,
                                                'note' => 'Each CSV line should be unique job - multiple rows per job_id may indicate matching issue'
                                            ]);
                                        }
                                    }
                                    // Always update to use the last row's value (most recent data)
                                    $csvInvoiceTotals[$result['job_id']] = $csvTotal;
                                    Log::debug('Import: Stored CSV total_cost for job', [
                                        'job_id' => $result['job_id'],
                                        'csv_total_cost' => $csvTotal,
                                        'row' => $lineIndex + 2,
                                        'raw_value' => $line['total_cost'],
                                        'is_update' => isset($csvInvoiceTotals[$result['job_id']])
                                    ]);
                                } else {
                                    Log::debug('Import: CSV total_cost is zero or invalid', [
                                        'job_id' => $result['job_id'],
                                        'raw_value' => $line['total_cost'] ?? 'MISSING',
                                        'normalized' => $csvTotal,
                                        'row' => $lineIndex + 2
                                    ]);
                                }
                            } else {
                                Log::debug('Import: CSV row missing total_cost', [
                                    'job_id' => $result['job_id'],
                                    'row' => $lineIndex + 2
                                ]);
                            }
                            
                            // Store payment data if invoice is marked as paid
                            $isPaid = isset($line['invoice_paid']) && strtolower(trim($line['invoice_paid'])) == 'paid';
                            if ($isPaid && isset($line['total_cost'])) {
                                $totalCost = static::normalizeNumericValue($line['total_cost']);
                                if ($totalCost > 0) {
                                    $paymentData[$result['job_id']] = [
                                        'total_cost' => $totalCost,
                                        'check_no' => $line['check_no'] ?? null,
                                        'invoice_no' => $line['invoice_no'] ?? null,
                                        'timestamp' => $line['timestamp'] ?? null,
                                    ];
                                    Log::debug('Import: Stored payment data for job', [
                                        'job_id' => $result['job_id'],
                                        'total_cost' => $paymentData[$result['job_id']]['total_cost'],
                                        'check_no' => $paymentData[$result['job_id']]['check_no'],
                                        'invoice_no' => $paymentData[$result['job_id']]['invoice_no']
                                    ]);
                                } else {
                                    Log::warning('Import: Invoice marked as paid but total_cost is zero or invalid', [
                                        'job_id' => $result['job_id'],
                                        'total_cost' => $line['total_cost'] ?? 'MISSING'
                                    ]);
                                }
                            }
                        } else {
                            // Unexpected result - log it
                            Log::warning('Import: Unexpected processLog result', ['result' => $result, 'row' => $lineIndex + 2, 'line' => $line]);
                            $skippedRows[] = [
                                'row' => $lineIndex + 2,
                                'reason' => 'Unexpected result from processLog',
                                'job_no' => $line['job_no'] ?? 'MISSING'
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Import: Failed to process log', ['line_index' => $lineIndex, 'line' => $line, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                        $errors[] = "Processing error for row " . ($lineIndex + 2) . ": " . $e->getMessage();
                    }
                }
                
                // Auto-create invoices if requested
                if ($autoCreateInvoices && !empty($processedJobs)) {
                    Log::info('Import: Auto-creating invoices', ['job_count' => count($processedJobs)]);
                    $uniqueJobIds = array_unique($processedJobs);
                    foreach ($uniqueJobIds as $jobId) {
                        try {
                            $job = static::find($jobId);
                            if ($job && $job->logs()->count() > 0) {
                                // Check if single invoice already exists for this job
                                // Single invoices use pilot_car_job_id directly (no pivot)
                                // Summary invoices use pivot table, but we're only creating single invoices here
                                $existingInvoice = \App\Models\Invoice::where('pilot_car_job_id', $jobId)
                                    ->where('invoice_type', '!=', 'summary')
                                    ->whereNull('parent_invoice_id')
                                    ->first();
                                
                                if (!$existingInvoice) {
                                    // Generate invoice for this job
                                    $invoiceValues = $job->invoiceValues();
                                    
                                    // Use CSV total_cost if available, otherwise use calculated total
                                    // CSV total_cost is the source of truth from the spreadsheet
                                    // Note: invoiceValues() returns ['values' => [...], 'paid_in_full' => ..., etc.]
                                    // So we need to modify $invoiceValues['values']['total'], not $invoiceValues['total']
                                    $values = $invoiceValues['values'] ?? [];
                                    $calculatedTotal = (float)($values['total'] ?? 0);
                                    $finalTotal = $calculatedTotal;
                                    $importSource = 'calculated';
                                    
                                    if (isset($csvInvoiceTotals[$jobId]) && $csvInvoiceTotals[$jobId] > 0) {
                                        $csvTotal = (float)$csvInvoiceTotals[$jobId]; // Ensure it's a float
                                        
                                        // Use CSV total (source of truth) - ensure it's stored as a number, not string
                                        $values['total'] = $csvTotal;
                                        $finalTotal = $csvTotal;
                                        $importSource = 'csv';
                                        
                                        // Log discrepancy if calculated total differs significantly (> $1 difference)
                                        $difference = abs($calculatedTotal - $csvTotal);
                                        if ($difference > 1.00) {
                                            Log::warning('Import: Invoice total discrepancy between CSV and calculated', [
                                                'job_id' => $jobId,
                                                'job_no' => $job->job_no,
                                                'csv_total' => $csvTotal,
                                                'calculated_total' => $calculatedTotal,
                                                'difference' => $difference,
                                                'difference_percent' => $calculatedTotal > 0 ? round(($difference / $calculatedTotal) * 100, 2) : 0
                                            ]);
                                        } else {
                                            Log::debug('Import: Using CSV total_cost for invoice', [
                                                'job_id' => $jobId,
                                                'csv_total' => $csvTotal,
                                                'calculated_total' => $calculatedTotal
                                            ]);
                                        }
                                    } else {
                                        Log::warning('Import: No CSV total_cost found, using calculated total (may be inaccurate)', [
                                            'job_id' => $jobId,
                                            'job_no' => $job->job_no,
                                            'calculated_total' => $calculatedTotal
                                        ]);
                                    }
                                    
                                    // Store import source flag for diagnostics (inside values array)
                                    $values['import_source'] = $importSource;
                                    $values['import_calculated_total'] = $calculatedTotal;
                                    if ($importSource === 'csv') {
                                        $values['import_csv_total'] = $finalTotal;
                                    }
                                    
                                    // Update the values array in invoiceValues
                                    $invoiceValues['values'] = $values;
                                    
                                    // Set paid_in_full based on job's invoice_paid status from CSV
                                    $invoiceValues['paid_in_full'] = (bool)($job->invoice_paid ?? false);
                                    
                                    // Create single invoice using createInvoice() - this does NOT create pivot entries
                                    // Single invoices only use pilot_car_job_id, pivot table is only for summary invoices
                                    $invoice = $job->createInvoice($invoiceValues);
                                    
                                    Log::info('Import: Created single invoice for job', [
                                        'job_id' => $jobId,
                                        'job_no' => $job->job_no,
                                        'invoice_id' => $invoice->id,
                                        'final_total' => $finalTotal,
                                        'import_source' => $importSource,
                                        'paid_in_full' => $invoiceValues['paid_in_full'],
                                        'pilot_car_job_id' => $invoice->pilot_car_job_id
                                    ]);
                                    
                                    // Verify no pivot entry was created (single invoices shouldn't have them)
                                    $pivotEntry = \App\Models\JobInvoice::where('invoice_id', $invoice->id)
                                        ->where('pilot_car_job_id', $jobId)
                                        ->first();
                                    if ($pivotEntry) {
                                        Log::warning('Import: Unexpected pivot entry created for single invoice', [
                                            'invoice_id' => $invoice->id,
                                            'job_id' => $jobId,
                                            'pivot_id' => $pivotEntry->id
                                        ]);
                                        // Remove the unexpected pivot entry
                                        $pivotEntry->delete();
                                        Log::info('Import: Removed unexpected pivot entry for single invoice', [
                                            'invoice_id' => $invoice->id,
                                            'job_id' => $jobId
                                        ]);
                                    }
                                    
                                    // Process payment if this job has payment data from CSV
                                    if (isset($paymentData[$jobId])) {
                                        static::processPaymentFromImport($invoice, $paymentData[$jobId], $job);
                                    }
                                } else {
                                    // Update existing invoice's paid status if job indicates it's paid
                                    if ($job->invoice_paid && !$existingInvoice->paid_in_full) {
                                        $existingInvoice->update(['paid_in_full' => true]);
                                        Log::info('Import: Updated existing invoice paid status', [
                                            'job_id' => $jobId, 
                                            'invoice_id' => $existingInvoice->id
                                        ]);
                                    }
                                    
                                    // Process payment if this job has payment data from CSV
                                    if (isset($paymentData[$jobId])) {
                                        static::processPaymentFromImport($existingInvoice, $paymentData[$jobId], $job);
                                    }
                                    
                                    Log::debug('Import: Single invoice already exists for job', [
                                        'job_id' => $jobId, 
                                        'invoice_id' => $existingInvoice->id,
                                        'pilot_car_job_id' => $existingInvoice->pilot_car_job_id
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Import: Failed to create invoice for job', ['job_id' => $jobId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                            // Don't fail entire import if invoice creation fails
                        }
                    }
                }
            } finally {
                // Always re-enable events, even if there was an error
                UserLog::setEventDispatcher($originalDispatcher);
            }
            
            // Log summary with detailed breakdown
            $uniqueJobIds = array_unique($processedJobs);
            $skippedRowNumbers = array_column($skippedRows, 'row');
            $skippedReasons = array_count_values(array_column($skippedRows, 'reason'));
            
            // Calculate revenue statistics
            $totalCsvRevenue = array_sum($csvInvoiceTotals);
            $csvTotalsCount = count($csvInvoiceTotals);
            $jobsWithoutCsvTotal = count($uniqueJobIds) - $csvTotalsCount;
            
            // If invoices were created, get statistics about them
            $invoiceStats = [
                'total_invoices_created' => 0,
                'csv_source_count' => 0,
                'calculated_source_count' => 0,
                'total_revenue_csv' => 0,
                'total_revenue_calculated' => 0
            ];
            
            if ($autoCreateInvoices && !empty($uniqueJobIds)) {
                $createdInvoices = \App\Models\Invoice::whereIn('pilot_car_job_id', $uniqueJobIds)
                    ->where('invoice_type', '!=', 'summary')
                    ->whereNull('parent_invoice_id')
                    ->get();
                
                $invoiceStats['total_invoices_created'] = $createdInvoices->count();
                
                foreach ($createdInvoices as $invoice) {
                    $values = $invoice->values ?? [];
                    $source = $values['import_source'] ?? 'unknown';
                    $total = (float)($values['total'] ?? 0);
                    
                    if ($source === 'csv') {
                        $invoiceStats['csv_source_count']++;
                        $invoiceStats['total_revenue_csv'] += $total;
                    } elseif ($source === 'calculated') {
                        $invoiceStats['calculated_source_count']++;
                        $invoiceStats['total_revenue_calculated'] += $total;
                    }
                }
            }
            
            Log::info('Import: Completed', [
                'total_csv_rows' => count($l),
                'total_rows_in_file' => $number - 1, // Subtract header row
                'unique_jobs_created_or_found' => count($uniqueJobIds),
                'skipped_rows_count' => count($skippedRows),
                'skipped_row_numbers' => $skippedRowNumbers,
                'skipped_reasons_breakdown' => $skippedReasons,
                'errors' => count($errors),
                'expected_jobs' => count($l) - count($skippedRows),
                'actual_jobs' => count($uniqueJobIds),
                'discrepancy' => (count($l) - count($skippedRows)) - count($uniqueJobIds),
                'revenue_statistics' => [
                    'csv_totals_collected' => $csvTotalsCount,
                    'jobs_without_csv_total' => $jobsWithoutCsvTotal,
                    'total_csv_revenue_collected' => $totalCsvRevenue,
                    'invoice_statistics' => $invoiceStats
                ]
            ]);

            // STRICT MODE: Fail if any processing errors occurred
            if (!empty($errors)) {
                $errorCount = count($errors);
                $errorSummary = implode("\n", array_slice($errors, 0, 10));
                if ($errorCount > 10) {
                    $errorSummary .= "\n... and " . ($errorCount - 10) . " more error(s)";
                }
                Log::error('Import failed: Processing errors', ['error_count' => $errorCount, 'errors' => $errors]);
                throw new \Exception("Import failed with {$errorCount} processing error(s):\n\n" . $errorSummary);
            }

        } catch (\Exception $e) {
            Log::error('Import failed', ['error' => $e->getMessage(), 'file' => $files[0]['full_path'] ?? 'unknown']);
            throw $e;
        }
    }

    /**
     * Process payment from CSV import data
     * Creates a payment transaction in the invoice's values array
     */
    public static function processPaymentFromImport($invoice, $paymentData, $job)
    {
        try {
            $totalCost = $paymentData['total_cost'] ?? 0;
            if ($totalCost <= 0) {
                Log::warning('Import: Payment amount is zero or invalid', [
                    'invoice_id' => $invoice->id,
                    'job_id' => $job->id,
                    'total_cost' => $totalCost
                ]);
                return;
            }

            $values = $invoice->values ?? [];
            $payments = $values['payments'] ?? [];

            // Check if payment already exists (avoid duplicates)
            $paymentExists = false;
            foreach ($payments as $existingPayment) {
                if (isset($existingPayment['import_source']) && 
                    isset($existingPayment['check_number']) && 
                    $existingPayment['check_number'] == ($paymentData['check_no'] ?? null) &&
                    abs((float)$existingPayment['amount'] - (float)$totalCost) < 0.01) {
                    $paymentExists = true;
                    break;
                }
            }

            if ($paymentExists) {
                Log::debug('Import: Payment already exists for invoice', [
                    'invoice_id' => $invoice->id,
                    'job_id' => $job->id,
                    'check_no' => $paymentData['check_no'] ?? null
                ]);
                return;
            }

            // Parse payment date from timestamp if available
            $paymentDate = now()->format('Y-m-d');
            if (!empty($paymentData['timestamp'])) {
                try {
                    $parsedDate = \Carbon\Carbon::parse($paymentData['timestamp']);
                    $paymentDate = $parsedDate->format('Y-m-d');
                } catch (\Exception $e) {
                    Log::warning('Import: Could not parse payment date', [
                        'invoice_id' => $invoice->id,
                        'timestamp' => $paymentData['timestamp'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Create payment record
            $payment = [
                'amount' => (float)$totalCost,
                'cash_amount' => (float)$totalCost,
                'credit_amount' => 0,
                'used_credit' => false,
                'payment_method' => 'Check',
                'check_number' => $paymentData['check_no'] ?? null,
                'payment_date' => $paymentDate,
                'notes' => 'Imported from CSV',
                'recorded_by' => null, // System import
                'recorded_at' => now()->toDateTimeString(),
                'import_source' => true, // Flag to identify imported payments
            ];

            $payments[] = $payment;
            $values['payments'] = $payments;

            // Update total paid
            $newTotalPaid = array_sum(array_column($payments, 'amount'));
            $values['total_paid'] = $newTotalPaid;

            // Calculate total due (with late fees if applicable)
            $lateFees = $invoice->calculateLateFees();
            $totalDue = $lateFees['total_with_late_fees'] ?? ($values['total'] ?? 0);

            // Update paid_in_full status
            if ($newTotalPaid >= $totalDue) {
                $invoice->paid_in_full = true;
            } else {
                $invoice->paid_in_full = false;
            }

            // Save invoice with payment data
            $invoice->values = $values;
            $invoice->save();

            Log::info('Import: Processed payment for invoice', [
                'invoice_id' => $invoice->id,
                'job_id' => $job->id,
                'payment_amount' => $totalCost,
                'total_paid' => $newTotalPaid,
                'total_due' => $totalDue,
                'paid_in_full' => $invoice->paid_in_full,
                'check_no' => $paymentData['check_no'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Import: Failed to process payment', [
                'invoice_id' => $invoice->id,
                'job_id' => $job->id ?? null,
                'payment_data' => $paymentData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't throw - payment processing failure shouldn't break the import
        }
    }

    /**
     * Normalize vehicle name: "Car 06", "Car 006", "Car 6" all become "Car 6"
     */
    public static function normalizeVehicleName($name)
    {
        if (empty($name)) {
            return '';
        }

        // Pattern: Extract prefix (e.g., "Car") and number (e.g., "06", "006", "6")
        if (preg_match('/^(.+?)\s*(\d+)$/i', trim($name), $matches)) {
            $prefix = trim($matches[1]);
            $number = (int)$matches[2]; // Convert "06" to 6, "006" to 6
            // Normalize to always 3 digits with leading zeros (e.g., 6 -> 006, 10 -> 010)
            return $prefix . ' ' . str_pad($number, 3, '0', STR_PAD_LEFT);
        }

        // If no number found, return as-is
        return trim($name);
    }

    /**
     * Normalize numeric values from CSV (remove commas, dollar signs, handle empty strings)
     */
    public static function normalizeNumericValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Remove dollar signs, commas, and whitespace, then convert to numeric
        $cleaned = str_replace(['$', ',', ' '], '', trim($value));
        
        if ($cleaned === '' || $cleaned === null) {
            return null;
        }
        
        // Convert to float/int - let PHP handle the conversion
        $numeric = is_numeric($cleaned) ? (float)$cleaned : null;
        
        return $numeric;
    }

    public static function validate($row, $count): void{
        // Check if invoice_no key exists and has a non-empty value
        if(!isset($row['invoice_no']) || trim($row['invoice_no']) === '') {
            $invoiceNoValue = $row['invoice_no'] ?? 'NOT SET';
            Log::error('Import validation failed: Missing or empty invoice_no', [
                'row' => $row, 
                'invoice_no_value' => $invoiceNoValue,
                'invoice_no_is_set' => isset($row['invoice_no']),
                'invoice_no_trimmed' => isset($row['invoice_no']) ? trim($row['invoice_no']) : 'N/A'
            ]);
            throw new \Exception('Import failed: Row is missing required field "invoice_no" or it is empty. Please check your CSV file.');
        }
        if(count($row) != $count) {
            Log::error('Import validation failed: Column count mismatch', ['row' => $row, 'expected' => $count, 'actual' => count($row)]);
            throw new \Exception('Import failed: Row has ' . count($row) . ' columns but expected ' . $count . '. Please check your CSV file format.');
        }
    }

    public static function getHeaderDictionary(){
        return [
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
    }

    public static function translateHeaders($headers, $originalHeaders = null){
        $dictionary = static::getHeaderDictionary();

        $values = [];
        $unknownHeaders = [];
        $unknownHeadersWithIndex = [];
        
        foreach($headers as $index => $hdr){
            $value = collect($dictionary)->filter(fn($entry)=> in_array($hdr, $entry))->keys()->first();

            if($value){
                $values[] = $value;
            }else{
                // STRICT MODE: Unknown headers are errors, not warnings
                $originalHeader = ($originalHeaders && isset($originalHeaders[$index])) ? $originalHeaders[$index] : $hdr;
                $originalHeaderTrimmed = trim($originalHeader ?? '');
                $displayHeader = !empty($originalHeaderTrimmed) ? $originalHeader : '(empty column ' . ($index + 1) . ')';
                
                $unknownHeaders[] = $displayHeader;
                $unknownHeadersWithIndex[] = [
                    'index' => $index + 1,
                    'original' => $originalHeader,
                    'normalized' => $hdr,
                    'display' => $displayHeader
                ];
                
                Log::error('Import: Unknown header found in CSV', [
                    'column_index' => $index + 1,
                    'original_header' => $originalHeader,
                    'normalized_header' => $hdr,
                    'all_headers' => $headers,
                    'all_original_headers' => $originalHeaders
                ]);
            }
            
        }
        
        // STRICT MODE: Fail immediately if any unknown headers are found
        if(!empty($unknownHeaders)) {
            $headerList = [];
            foreach($unknownHeadersWithIndex as $info) {
                $originalTrimmed = trim($info['original'] ?? '');
                if (!empty($originalTrimmed) && $info['original'] !== $info['normalized']) {
                    $headerList[] = "Column {$info['index']}: \"{$info['original']}\" (normalized to: \"{$info['normalized']}\")";
                } else if (!empty($originalTrimmed)) {
                    $headerList[] = "Column {$info['index']}: \"{$info['original']}\"";
                } else {
                    $headerList[] = "Column {$info['index']}: {$info['display']}";
                }
            }
            
            $errorMessage = "Import failed: The following " . count($unknownHeaders) . " header(s) in your CSV file are not recognized:\n\n" . 
                          implode("\n", $headerList) . 
                          "\n\nPlease check your file format. All headers must match the expected format.";
            
            Log::error('Import: Unknown headers detected', [
                'unknown_headers' => $unknownHeadersWithIndex,
                'all_headers' => $headers,
                'all_original_headers' => $originalHeaders
            ]);
            throw new \Exception($errorMessage);
        }
        
        if(count($headers) != count($values)) {
            Log::error('Import: Header/Value count mismatch', ['headers' => $headers, 'values' => $values]);
            throw new \Exception('Import failed: Header translation error. Found ' . count($headers) . ' headers but ' . count($values) . ' values.');
        }
        
        return $values; 
    }

    public static function processLog($values, $organization_id, &$processedJobs = [], $skipDeduplication = false){
        // Log all processing attempts for debugging
        Log::debug('Import: Processing log row', [
            'organization_id' => $organization_id,
            'has_job_no' => array_key_exists('job_no', $values),
            'job_no' => $values['job_no'] ?? 'MISSING',
            'has_invoice_no' => array_key_exists('invoice_no', $values),
            'invoice_no' => $values['invoice_no'] ?? 'MISSING',
            'skip_deduplication' => $skipDeduplication
        ]);

        // Allow import even if job_no is missing - set to null and log it
        $hasJobNo = array_key_exists('job_no', $values) && !empty(trim($values['job_no'] ?? ''));
        if (!$hasJobNo) {
            Log::warning('Import: Row missing job_no - importing anyway with job_no=null', [
                'row_data' => array_filter($values, fn($k) => !in_array($k, ['job_no']), ARRAY_FILTER_USE_KEY)
            ]);
            $values['job_no'] = null; // Set to null instead of skipping
        }
        
        if(!array_key_exists('invoice_no', $values) || empty($values['invoice_no'])){
            Log::warning('Import: Row missing invoice_no', ['job_no' => $values['job_no'] ?? 'MISSING']);
            // Continue processing but log the issue
        }

        if(!array_key_exists('end_time', $values)){
           $values['end_time'] = null;
        }
        
            // Find existing job by invoice_no (most unique identifier)
            // job_no is NOT unique - it's more like a "Job Name" that customers reuse
            // Each CSV row is unique, but we need to deduplicate from existing database entries
            $job = null;
            $matchMethod = null;
            $customer = null;
            $job_started = null;
            $job_ended = null;
            
            // Skip all deduplication logic if database is empty
            if (!$skipDeduplication) {
                if (!empty($values['invoice_no'])) {
                    // Primary: Match by invoice_no (most unique)
                    $job = static::where('organization_id', $organization_id)
                        ->where('invoice_no', $values['invoice_no'])
                        ->first();
                    
                    if ($job) {
                        $matchMethod = 'invoice_no';
                        Log::debug('Import: Found existing job by invoice_no', [
                            'job_id' => $job->id,
                            'invoice_no' => $values['invoice_no'],
                            'existing_job_no' => $job->job_no,
                            'csv_job_no' => $values['job_no'] ?? 'MISSING'
                        ]);
                    }
                }
                
                // Fallback: If no invoice_no or no match found, check if ALL values match an existing entry
                if (!$job && empty($values['invoice_no'])) {
                    // Prepare values for comparison
                    $job_started = Carbon::make($values['start_date'] . ' ' . $values['start_time']);
                    $job_ended = Carbon::make($values['start_date'] . ' ' . $values['end_time']);
                    
                    // Get customer first (needed for comparison)
                    $customer = Customer::where('organization_id', $organization_id)->where('name', $values['customer_name'])->first();
                    
                    if ($customer) {
                        // Build query to match all key fields exactly
                        $query = static::where('organization_id', $organization_id)
                            ->where('customer_id', $customer->id)
                            ->whereNull('invoice_no'); // Only match jobs that also have no invoice_no
                        
                        // Match scheduled dates
                        if ($job_started) {
                            $query->where('scheduled_pickup_at', $job_started->toDateTimeString());
                        } else {
                            $query->whereNull('scheduled_pickup_at');
                        }
                        
                        if ($job_ended) {
                            $query->where('scheduled_delivery_at', $job_ended->toDateTimeString());
                        } else {
                            $query->whereNull('scheduled_delivery_at');
                        }
                        
                        // Match other key fields
                        if (!empty($values['load_no'])) {
                            $query->where('load_no', $values['load_no']);
                        } else {
                            $query->whereNull('load_no');
                        }
                        
                        if (!empty($values['pickup_address'])) {
                            $query->where('pickup_address', $values['pickup_address']);
                        } else {
                            $query->whereNull('pickup_address');
                        }
                        
                        if (!empty($values['delivery_address'])) {
                            $query->where('delivery_address', $values['delivery_address']);
                        } else {
                            $query->whereNull('delivery_address');
                        }
                        
                        // Note: check_no is NOT included in matching as it can be updated (e.g., was empty, now has value)
                        
                        $job = $query->first();
                        
                        if ($job) {
                            $matchMethod = 'exact_match_fallback';
                            Log::info('Import: Found existing job by exact field match (no invoice_no)', [
                                'job_id' => $job->id,
                                'customer' => $values['customer_name'],
                                'scheduled_pickup_at' => $job_started?->toDateTimeString(),
                                'load_no' => $values['load_no'] ?? 'NULL',
                                'match_method' => 'exact_match_fallback'
                            ]);
                        } else {
                            Log::debug('Import: No exact match found, will create new job (no invoice_no)', [
                                'customer' => $values['customer_name'],
                                'scheduled_pickup_at' => $job_started?->toDateTimeString(),
                                'load_no' => $values['load_no'] ?? 'NULL'
                            ]);
                        }
                    } else {
                        // Customer doesn't exist yet, definitely a new job
                        Log::debug('Import: Customer does not exist, will create new job', [
                            'customer_name' => $values['customer_name']
                        ]);
                    }
                }
            } else {
                // Database is empty - skip deduplication, treat all entries as new
                Log::debug('Import: Skipping deduplication (database is empty) - treating as new job', [
                    'invoice_no' => $values['invoice_no'] ?? 'MISSING',
                    'job_no' => $values['job_no'] ?? 'MISSING'
                ]);
            }
            
            // Get customer if not already retrieved (for job creation)
            if (!$customer) {
                $customer = Customer::where('organization_id',$organization_id)->where('name', $values['customer_name'])->first();
            }
            
            // Prepare job dates if not already done (for job creation)
            if (!$job_started) {
                $job_started = Carbon::make($values['start_date'] . ' ' . $values['start_time']);
            }
            if (!$job_ended) {
                $job_ended = Carbon::make($values['start_date'] . ' ' . $values['end_time']);
            }

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

            // Normalize vehicle name: "Car 06", "Car 006", "Car 6" all become "Car 6"
            $vehicleName = static::normalizeVehicleName($values['pilot_car_name'] ?? '');
            
            // Try to find vehicle by normalized name first
            $car = Vehicle::where('organization_id', $organization_id)
                ->get()
                ->first(function($v) use ($vehicleName) {
                    return static::normalizeVehicleName($v->name) === $vehicleName;
                });

            if(!$car){
                // Create with normalized name
                $car = Vehicle::create([
                    'name'=> $vehicleName ?: $values['pilot_car_name'] ?? 'Unknown',
                    'odometer'=> static::normalizeNumericValue($values['end_mileage'] ?? null),
                    'odometer_updated_at'=> $job_ended?->toDateTimeString(),
                    'organization_id' => $organization_id
                ]);
                Log::info('Import: Created new vehicle', ['name' => $car->name, 'original' => $values['pilot_car_name'] ?? '']);
            }else{
                // Update existing vehicle (may have different name format)
                $car->update([
                    'odometer'=> static::normalizeNumericValue($values['end_mileage'] ?? null),
                    'odometer_updated_at'=> $job_ended?->toDateTimeString()
                ]);
                Log::debug('Import: Updated existing vehicle', ['id' => $car->id, 'name' => $car->name]);
            }

            $jobCreated = false;
            if(!$job){
                if(!empty($values['timestamp'])){
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
                $jobCreated = true;
                Log::info('Import: Created new job', ['job_id' => $job->id, 'job_no' => $job->job_no, 'invoice_no' => $job->invoice_no]);
            } else {
                // Update existing job found by invoice_no
                // Update job_no if it's different (since job_no can change but invoice_no is unique)
                $updates = [];
                if (!empty($values['job_no']) && $job->job_no !== $values['job_no']) {
                    $updates['job_no'] = $values['job_no'];
                    Log::debug('Import: Updating job_no for existing job', [
                        'job_id' => $job->id,
                        'invoice_no' => $values['invoice_no'],
                        'old_job_no' => $job->job_no,
                        'new_job_no' => $values['job_no']
                    ]);
                }
                
                // Update invoice_paid status if CSV indicates it's paid
                $isPaid = $values['invoice_paid'] && strtolower($values['invoice_paid']) == 'paid';
                if ($isPaid && !$job->invoice_paid) {
                    $updates['invoice_paid'] = true;
                }
                
                if (!empty($updates)) {
                    $job->update($updates);
                    Log::info('Import: Updated existing job', [
                        'job_id' => $job->id,
                        'invoice_no' => $values['invoice_no'],
                        'updates' => $updates
                    ]);
                } else {
                    Log::debug('Import: Existing job found, no updates needed', [
                        'job_id' => $job->id,
                        'invoice_no' => $values['invoice_no']
                    ]);
                }
            }

            // Try to find existing log - use more flexible matching
            $log = UserLog::where('organization_id',$organization_id)
                ->where('job_id', $job->id)
                ->where('vehicle_id', $car->id)
                ->where('started_at', $job_started?->toDateTimeString())
                ->first();

            $logCreated = false;
            if(!$log){
                $hotel = null;

                if($values['hotel'] === 'NA' || empty($values['hotel'])){
                    //do nothing? 0.00:$values['hotel']
                }else{
                    $hotel = (Float)(trim(str_replace('$','', $values['hotel'])));
                }

                try {
                    $log = UserLog::create([
                        'job_id'=> $job->id,
                        'car_driver_id'=> $car_driver->id,
                        'truck_driver_id'=> $truck_driver->id,
                        'vehicle_id'=> $car->id,
                        'pretrip_check'=> strtolower($values['pretrip_check_answer']) === 'yes',
                        'truck_no'=>$values['truck_no'],
                        'trailer_no'=>$values['trailer_no'],
                        'start_mileage'=> static::normalizeNumericValue($values['start_mileage'] ?? null),
                        'end_mileage'=> static::normalizeNumericValue($values['end_mileage'] ?? null),
                        'start_job_mileage'=> static::normalizeNumericValue($values['start_job_mileage'] ?? null),
                        'end_job_mileage'=> static::normalizeNumericValue($values['end_job_mileage'] ?? null),
                        'load_canceled'=>$values['if_load_canceled'] && strtolower($values['if_load_canceled']) === 'canceled',
                        'is_deadhead'=>$values['is_deadhead'] && strtolower($values['is_deadhead']) === 'yes',
                        'extra_load_stops_count'=> static::normalizeNumericValue($values['extra_load_stops_count'] ?? null) ?? 0,
                        'wait_time_hours'=> static::normalizeNumericValue($values['wait_time_hours'] ?? null) ?? 0.00,
                        'tolls'=> static::normalizeNumericValue($values['tolls'] ?? null) ?? 0.00,
                        'gas'=> static::normalizeNumericValue($values['gas'] ?? null) ?? 0.00,
                        'extra_charge'=> static::normalizeNumericValue($values['extra_charge'] ?? null) ?? 0.00,
                        'hotel'=> $hotel,
                        'memo'=>$values['wait_time_reason'],
                        'maintenance_memo'=>$values['maintenance_memo'],
                        'started_at'=> $job_started?->toDateTimeString(),
                        'ended_at'=> $job_ended?->toDateTimeString(),
                        'organization_id' => $organization_id
                    ]);
                    $logCreated = true;
                    Log::info('Import: Created new log', ['log_id' => $log->id, 'job_id' => $job->id, 'job_no' => $values['job_no']]);
                } catch (\Exception $e) {
                    Log::error('Import: Failed to create log', [
                        'job_id' => $job->id,
                        'job_no' => $values['job_no'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Still return job_id even if log creation failed
                }
            } else {
                Log::debug('Import: Using existing log', ['log_id' => $log->id, 'job_id' => $job->id, 'job_no' => $values['job_no']]);
            }
            
            // Always return job_id, even if log creation failed or was skipped
            // This ensures the job is counted in processedJobs
            return [
                'job_id' => $job->id, 
                'log_id' => $log->id ?? null,
                'job_created' => $jobCreated,
                'log_created' => $logCreated,
                'invoice_paid' => $values['invoice_paid'] ?? false,
                'total_cost' => $values['total_cost'] ?? null,
                'check_no' => $values['check_no'] ?? null,
                'invoice_no' => $values['invoice_no'] ?? null,
                'timestamp' => $values['timestamp'] ?? null,
            ];
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

        // Check if job is marked as paid (from CSV import or manual update)
        $paidInFull = (bool)($this->invoice_paid ?? false);
        
        return [
            'paid_in_full' => $paidInFull,
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
        
        // Ensure total is always a float (handle both string and numeric inputs)
        if (is_string($values['total'])) {
            // Remove commas and convert to float
            $values['total'] = (float) str_replace([',', ' '], '', $values['total']);
        } else {
            // Already numeric, ensure it's a float
            $values['total'] = (float) $values['total'];
        }

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
        // Check both single invoices (via pilot_car_job_id) and summary invoices (via pivot)
        $hasSingleInvoice = Invoice::where('pilot_car_job_id', $this->id)
            ->where('invoice_type', '!=', 'summary')
            ->whereNull('parent_invoice_id')
            ->exists();
        $hasSummaryInvoice = $this->summaryInvoices()->exists();
        
        if ($hasSingleInvoice || $hasSummaryInvoice) {
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

        // Determine which is better FROM COMPANY'S PERSPECTIVE (higher revenue is better)
        // Mini-Run is better if it would charge MORE than current rate
        $isMiniBetter = $miniCost > $currentCost;
        
        // Calculate potential additional revenue (or savings if current is better)
        if ($isMiniBetter) {
            $savings = $miniCost - $currentCost; // How much MORE they'd make with Mini-Run
        } else {
            $savings = $currentCost - $miniCost; // How much MORE they're making with current rate
        }

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