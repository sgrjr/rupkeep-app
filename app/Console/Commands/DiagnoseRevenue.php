<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class DiagnoseRevenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'revenue:diagnose {organization_id? : The organization ID to diagnose}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose revenue calculation discrepancies by comparing CSV totals vs stored invoice totals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $organizationId = $this->argument('organization_id');
        
        if ($organizationId) {
            $organization = Organization::find($organizationId);
            if (!$organization) {
                $this->error("Organization {$organizationId} not found.");
                return 1;
            }
            $organizations = collect([$organization]);
        } else {
            $organizations = Organization::all();
        }
        
        foreach ($organizations as $organization) {
            $this->info("\n" . str_repeat('=', 80));
            $this->info("Organization: {$organization->name} (ID: {$organization->id})");
            $this->info(str_repeat('=', 80));
            
            // Get all single invoices (non-summary, non-child)
            $allInvoices = Invoice::where('organization_id', $organization->id)->get();
            $singleInvoices = $allInvoices->filter(fn($inv) => 
                $inv->invoice_type !== 'summary' && $inv->parent_invoice_id === null
            );
            
            $this->info("\nInvoice Statistics:");
            $this->info("  Total Invoices: " . $allInvoices->count());
            $this->info("  Single Invoices: " . $singleInvoices->count());
            $this->info("  Summary Invoices: " . $allInvoices->filter(fn($inv) => $inv->invoice_type === 'summary')->count());
            $this->info("  Child Invoices: " . $allInvoices->filter(fn($inv) => $inv->parent_invoice_id !== null)->count());
            
            // Analyze by import source
            $breakdown = [
                'csv' => ['count' => 0, 'total' => 0.0, 'invoices' => []],
                'calculated' => ['count' => 0, 'total' => 0.0, 'invoices' => []],
                'unknown' => ['count' => 0, 'total' => 0.0, 'invoices' => []],
                'issues' => []
            ];
            
            foreach ($singleInvoices as $invoice) {
                $values = $invoice->values ?? [];
                $total = (float)($values['total'] ?? 0);
                $source = $values['import_source'] ?? 'unknown';
                
                if ($total === null || $total == 0) {
                    $breakdown['issues'][] = [
                        'invoice_id' => $invoice->id,
                        'job_id' => $invoice->pilot_car_job_id,
                        'total' => $total,
                        'issue' => $total === null ? 'null_total' : 'zero_total'
                    ];
                }
                
                if ($source === 'csv') {
                    $breakdown['csv']['count']++;
                    $breakdown['csv']['total'] += $total;
                    $breakdown['csv']['invoices'][] = [
                        'id' => $invoice->id,
                        'job_id' => $invoice->pilot_car_job_id,
                        'total' => $total,
                        'csv_total' => $values['import_csv_total'] ?? null,
                        'calculated_total' => $values['import_calculated_total'] ?? null
                    ];
                } elseif ($source === 'calculated') {
                    $breakdown['calculated']['count']++;
                    $breakdown['calculated']['total'] += $total;
                    $breakdown['calculated']['invoices'][] = [
                        'id' => $invoice->id,
                        'job_id' => $invoice->pilot_car_job_id,
                        'total' => $total,
                        'calculated_total' => $values['import_calculated_total'] ?? null
                    ];
                } else {
                    $breakdown['unknown']['count']++;
                    $breakdown['unknown']['total'] += $total;
                }
            }
            
            $totalRevenue = $singleInvoices->sum(fn($inv) => (float)($inv->values['total'] ?? 0));
            
            $this->info("\nRevenue Breakdown:");
            $this->info("  Total Revenue: $" . number_format($totalRevenue, 2));
            $this->info("  CSV Source: {$breakdown['csv']['count']} invoices = $" . number_format($breakdown['csv']['total'], 2));
            $this->info("  Calculated Source: {$breakdown['calculated']['count']} invoices = $" . number_format($breakdown['calculated']['total'], 2));
            $this->info("  Unknown Source: {$breakdown['unknown']['count']} invoices = $" . number_format($breakdown['unknown']['total'], 2));
            
            if (count($breakdown['issues']) > 0) {
                $this->warn("\nIssues Found:");
                foreach (array_slice($breakdown['issues'], 0, 10) as $issue) {
                    $this->warn("  Invoice #{$issue['invoice_id']} (Job #{$issue['job_id']}): {$issue['issue']}");
                }
                if (count($breakdown['issues']) > 10) {
                    $this->warn("  ... and " . (count($breakdown['issues']) - 10) . " more issues");
                }
            }
            
            // Show sample invoices with discrepancies
            if ($breakdown['csv']['count'] > 0) {
                $this->info("\nSample CSV-Sourced Invoices (first 5):");
                foreach (array_slice($breakdown['csv']['invoices'], 0, 5) as $inv) {
                    $csvTotal = $inv['csv_total'] ?? 'N/A';
                    $calcTotal = $inv['calculated_total'] ?? 'N/A';
                    $diff = $csvTotal !== 'N/A' && $calcTotal !== 'N/A' ? abs($csvTotal - $calcTotal) : 'N/A';
                    $this->info("  Invoice #{$inv['id']} (Job #{$inv['job_id']}): Total=${$inv['total']}, CSV={$csvTotal}, Calculated={$calcTotal}, Diff={$diff}");
                }
            }
            
            // Check for duplicate invoices per job
            $jobInvoiceCounts = DB::table('invoices')
                ->where('organization_id', $organization->id)
                ->where('invoice_type', '!=', 'summary')
                ->whereNull('parent_invoice_id')
                ->whereNotNull('pilot_car_job_id')
                ->select('pilot_car_job_id', DB::raw('count(*) as count'))
                ->groupBy('pilot_car_job_id')
                ->having('count', '>', 1)
                ->get();
            
            if ($jobInvoiceCounts->count() > 0) {
                $this->warn("\nJobs with Multiple Invoices:");
                foreach ($jobInvoiceCounts->take(10) as $job) {
                    $this->warn("  Job #{$job->pilot_car_job_id}: {$job->count} invoices");
                }
            }
        }
        
        $this->info("\n" . str_repeat('=', 80));
        $this->info("Diagnosis complete.");
        
        return 0;
    }
}
