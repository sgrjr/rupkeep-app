<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fold one person's account into another (two logins for the same driver,
 * usually). Every row that names the old account is re-pointed at the one
 * being kept, then the old account is soft-deleted.
 *
 * The first version moved only user_logs.car_driver_id and vehicles.user_id,
 * so attachments, task submissions, job default drivers, approvals and
 * comments kept naming an account that was then deleted (TASK-471). It also
 * had no dry run and asked no questions.
 */
class MergeUsers extends Command
{
    protected $signature = 'user:merge {keep : id of the account to keep} {old : id of the account to fold into it}
        {--dry-run : show what would move and change nothing}
        {--force : do not ask for confirmation}';

    protected $description = 'Re-point every reference from one user account to another, then soft-delete the old account';

    /**
     * Every column that names a user, by table. Rows are moved; where the
     * old and new ids would collide on a unique key (push subscriptions,
     * login codes) the old rows are simply removed.
     *
     * @var array<string, array<int, string>>
     */
    public const USER_COLUMNS = [
        'user_logs' => ['car_driver_id', 'approved_by_id', 'completed_by_id'],
        'vehicles' => ['user_id'],
        'pilot_car_jobs' => ['default_driver_id'],
        'invoices' => ['voided_by_id'],
        'invoice_comments' => ['user_id'],
        'tasks' => ['submitter_user_id', 'assignee_user_id'],
        'task_comments' => ['user_id'],
        'user_events' => ['user_id'],
        'vehicle_maintenance_records' => ['created_by'],
        'admin_notes' => ['updated_by_user_id'],
        'organizations' => ['user_id'],
    ];

    /** Rows that belong to a device or a login session: dropped, not moved. */
    public const DROP_TABLES = [
        'push_subscriptions' => ['subscribable_id', 'subscribable_type'],
        'login_codes' => ['user_id', null],
    ];

    public function handle(): int
    {
        $keep = User::find($this->argument('keep'));
        $old = User::withTrashed()->find($this->argument('old'));

        if (! $keep || ! $old) {
            $this->error('Could not find both accounts.');

            return self::FAILURE;
        }

        if ($keep->is($old)) {
            $this->error('That is the same account twice.');

            return self::FAILURE;
        }

        if ($keep->organization_id !== $old->organization_id) {
            $this->error(sprintf('The accounts belong to different organizations (%s vs %s); not merging.', $keep->organization_id, $old->organization_id));

            return self::FAILURE;
        }

        $plan = $this->plan($old);

        $this->info(sprintf('Keep #%d %s <%s>; fold in #%d %s <%s>', $keep->id, $keep->name, $keep->email, $old->id, $old->name, $old->email));
        $this->table(['Table', 'Column', 'Rows'], collect($plan)->map(fn ($row) => [$row['table'], $row['column'], $row['count']])->all());

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Move these rows and soft-delete the old account?')) {
            $this->comment('Nothing was changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($keep, $old) {
            foreach (self::USER_COLUMNS as $table => $columns) {
                foreach ($columns as $column) {
                    if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                        continue;
                    }

                    DB::table($table)->where($column, $old->id)->update([$column => $keep->id]);
                }
            }

            // A User morph on attachments (profile documents and the like).
            if (\Illuminate\Support\Facades\Schema::hasTable('attachments')) {
                DB::table('attachments')
                    ->where('attachable_type', User::class)
                    ->where('attachable_id', $old->id)
                    ->update(['attachable_id' => $keep->id]);
            }

            foreach (self::DROP_TABLES as $table => [$column, $typeColumn]) {
                if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                    continue;
                }

                $query = DB::table($table)->where($column, $old->id);

                if ($typeColumn) {
                    $query->where($typeColumn, User::class);
                }

                $query->delete();
            }

            $old->delete();
        });

        $this->info('Merged. The old account is soft-deleted; restore it from the users list if this was a mistake.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{table: string, column: string, count: int}>
     */
    private function plan(User $old): array
    {
        $rows = [];

        foreach (self::USER_COLUMNS as $table => $columns) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $count = DB::table($table)->where($column, $old->id)->count();

                if ($count > 0) {
                    $rows[] = ['table' => $table, 'column' => $column, 'count' => $count];
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('attachments')) {
            $count = DB::table('attachments')->where('attachable_type', User::class)->where('attachable_id', $old->id)->count();

            if ($count > 0) {
                $rows[] = ['table' => 'attachments', 'column' => 'attachable_id', 'count' => $count];
            }
        }

        foreach (self::DROP_TABLES as $table => [$column, $typeColumn]) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table)->where($column, $old->id);

            if ($typeColumn) {
                $query->where($typeColumn, User::class);
            }

            $count = $query->count();

            if ($count > 0) {
                $rows[] = ['table' => $table, 'column' => $column.' (dropped)', 'count' => $count];
            }
        }

        return $rows;
    }
}
