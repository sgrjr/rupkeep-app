<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only. Explains why an organization's dashboard can report thousands of
 * invoices while its jobs list shows a handful.
 *
 * The two figures are counted from different things: total_invoices is
 * Invoice::where('organization_id'), while total_jobs walks
 * $organization->jobs(), a hasMany on a soft-deleting model. Any job that is
 * archived, carries a different organization_id, or was deleted outright drops
 * out of the second count while its invoice stays in the first.
 *
 * This command says which of those it is. It writes nothing.
 */
class DiagnoseJobVisibility extends Command
{
    protected $signature = 'jobs:diagnose {organization_id? : Defaults to every organization}';

    protected $description = 'Explain a mismatch between the invoice count and the job list (read-only)';

    public function handle(): int
    {
        $organizations = $this->argument('organization_id')
            ? Organization::where('id', $this->argument('organization_id'))->get()
            : Organization::all();

        if ($organizations->isEmpty()) {
            $this->error('No such organization.');

            return self::FAILURE;
        }

        foreach ($organizations as $organization) {
            $this->organization($organization);
        }

        $this->orphanSweep();

        return self::SUCCESS;
    }

    private function organization(Organization $organization): void
    {
        $this->newLine();
        $this->info("=== Organization {$organization->id}: {$organization->name} ===");

        $jobs = PilotCarJob::withTrashed()->where('organization_id', $organization->id);
        $total = (clone $jobs)->count();
        $live = (clone $jobs)->whereNull('deleted_at')->count();
        $archived = (clone $jobs)->whereNotNull('deleted_at')->count();

        $invoices = Invoice::withTrashed()->where('organization_id', $organization->id);

        $this->table(['', 'count'], [
            ['jobs (all, including archived)', $total],
            ['  live', $live],
            ['  archived (soft-deleted)', $archived],
            ['invoices (all)', (clone $invoices)->count()],
            ['  live', (clone $invoices)->whereNull('deleted_at')->count()],
            ['  archived', (clone $invoices)->whereNotNull('deleted_at')->count()],
            ['  summaries', (clone $invoices)->where('invoice_type', 'summary')->count()],
        ]);

        // The dashboard counts jobs through $organization->jobs(), which is what
        // the "Total Jobs" tile shows. If this disagrees with "live" above, the
        // relation is not the thing hiding them.
        $this->line('  dashboard "Total Jobs" would read: '.$organization->jobs()->count());

        $this->line('');
        $this->line('  Jobs with no customer: '
            .(clone $jobs)->whereNull('customer_id')->count()
            .'  <- these vanish from the jobs list whenever a FIELD filter is set,'
        );
        $this->line('     even with an empty search box, because that filter matches on customer.');

        $this->highestIds($organization);
    }

    /**
     * Auto-increment high-water marks. If the largest job id is far above the
     * number of jobs that still exist, rows were created and later removed --
     * which is a different story from rows that were never created.
     */
    private function highestIds(Organization $organization): void
    {
        $maxJob = PilotCarJob::withTrashed()->where('organization_id', $organization->id)->max('id');
        $maxInvoice = Invoice::withTrashed()->where('organization_id', $organization->id)->max('id');

        $this->line('');
        $this->line("  Highest job id: ".($maxJob ?? '-')."   highest invoice id: ".($maxInvoice ?? '-'));
    }

    /**
     * Invoices whose job is gone, archived, or filed under another organization.
     * This is what turns a real revenue figure into an unreachable one.
     */
    private function orphanSweep(): void
    {
        $this->newLine();
        $this->info('=== Invoice -> job linkage (all organizations) ===');

        $withJob = Invoice::withTrashed()->whereNotNull('pilot_car_job_id');

        $missing = (clone $withJob)
            ->whereNotIn('pilot_car_job_id', PilotCarJob::withTrashed()->select('id'))
            ->count();

        $archivedJob = (clone $withJob)
            ->whereIn('pilot_car_job_id', PilotCarJob::onlyTrashed()->select('id'))
            ->count();

        $mismatched = DB::table('invoices')
            ->join('pilot_car_jobs', 'invoices.pilot_car_job_id', '=', 'pilot_car_jobs.id')
            ->whereColumn('invoices.organization_id', '!=', 'pilot_car_jobs.organization_id')
            ->count();

        $this->table(['', 'count'], [
            ['invoices with no job at all (pilot_car_job_id null)',
                Invoice::withTrashed()->whereNull('pilot_car_job_id')->count()],
            ['invoices pointing at a job row that does not exist', $missing],
            ['invoices whose job is archived', $archivedJob],
            ['invoices filed under a different org than their job', $mismatched],
        ]);

        $this->newLine();
        $this->line('Reading this:');
        $this->line('  "job row does not exist"   -> the jobs were hard-deleted; invoices outlived them.');
        $this->line('  "job is archived"          -> the jobs are still there. Un-archiving restores them.');
        $this->line('  "different org"            -> the jobs exist but are filed elsewhere and');
        $this->line('                                the org-scoped list will never show them.');
        $this->line('  "pilot_car_job_id null"    -> invoices were imported without being tied to a job.');
    }
}
