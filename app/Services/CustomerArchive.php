<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Deleting a customer takes every contact, job and driver log with it, and
 * customers are deliberately not soft-deleted (TASK-461, decided 2026-09-30).
 * What makes that safe is this: before the delete, everything the customer
 * owned is written to one JSON file in private storage, kept on the server,
 * offered for download, and included in the backup (TASK-103).
 *
 * The archive is written first and the delete refuses to run if the write
 * fails; an archive that cannot be written is the one case where "delete
 * as today" would be worse than doing nothing.
 */
class CustomerArchive
{
    public const DISK = 'local';

    public const DIRECTORY = 'archives/customers';

    public const FORMAT = 'rupkeep.customer-archive/1';

    /** File names this class writes: {customer id}-{Ymd-His}.json */
    public const FILE_PATTERN = '/^\d+-\d{8}-\d{6}\.json$/';

    /**
     * Archive, then hard delete. Returns the archive path on the disk.
     *
     * @throws RuntimeException when the archive could not be written; nothing is deleted then.
     */
    public function archiveAndDelete(Customer $customer, User $actor): string
    {
        $path = $this->write($customer, $actor);

        DB::transaction(function () use ($customer) {
            // Through the models, not the FK cascade, so each log's and job's
            // forceDeleting hook removes its files from storage.
            $jobs = $customer->jobs()->withTrashed()->get();

            UserLog::withTrashed()->whereIn('job_id', $jobs->pluck('id'))->get()->each->forceDelete();
            $jobs->each->forceDelete();

            $customer->contacts()->get()->each->delete();
            $customer->delete();
        });

        return $path;
    }

    public function write(Customer $customer, User $actor): string
    {
        $path = sprintf('%s/%d-%s.json', self::DIRECTORY, $customer->id, now()->format('Ymd-His'));

        $json = json_encode(
            $this->payload($customer, $actor),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        if (Storage::disk(self::DISK)->put($path, $json) === false) {
            throw new RuntimeException("Could not write the customer archive to {$path}.");
        }

        return $path;
    }

    /**
     * Everything the customer owns, as plain arrays. Attachments are metadata
     * only: the files themselves stay in the backup, not in this document.
     */
    public function payload(Customer $customer, User $actor): array
    {
        $jobs = $customer->jobs()->withTrashed()->get();
        $jobIds = $jobs->pluck('id');

        $logs = UserLog::withTrashed()
            ->whereIn('job_id', $jobIds)
            ->with('extraCharges')
            ->get();

        $invoices = Invoice::withTrashed()->where('customer_id', $customer->id)->get();

        $attachmentsFor = function (string $type, iterable $ids): array {
            return Attachment::withTrashed()
                ->where('attachable_type', $type)
                ->whereIn('attachable_id', collect($ids)->all())
                ->get()
                ->map(fn (Attachment $a) => $a->only(['id', 'attachable_id', 'file_name', 'location', 'is_public', 'created_at', 'deleted_at']))
                ->values()
                ->all();
        };

        return [
            'format' => self::FORMAT,
            'archived_at' => now()->toIso8601String(),
            'archived_by' => $actor->only(['id', 'name', 'email']),
            'organization_id' => $customer->organization_id,
            'customer' => $customer->toArray(),
            'contacts' => $customer->contacts()->get()->toArray(),
            // Portal accounts are kept (users.customer_id is set null on delete);
            // this records which ones belonged here.
            'portal_users' => $customer->users()->get()->map(fn (User $u) => $u->only(['id', 'name', 'email', 'created_at']))->all(),
            'jobs' => $jobs->map(fn ($job) => $job->toArray())->all(),
            'logs' => $logs->map(function (UserLog $log) {
                $row = $log->toArray();
                $row['extra_charges'] = $log->extraCharges->toArray();

                return $row;
            })->all(),
            'invoices' => $invoices->toArray(),
            'attachments' => [
                'jobs' => $attachmentsFor($jobs->first()?->getMorphClass() ?? \App\Models\PilotCarJob::class, $jobIds),
                'logs' => $attachmentsFor($logs->first()?->getMorphClass() ?? UserLog::class, $logs->pluck('id')),
            ],
            'counts' => [
                'contacts' => $customer->contacts()->count(),
                'jobs' => $jobs->count(),
                'logs' => $logs->count(),
                'invoices' => $invoices->count(),
            ],
        ];
    }

    /**
     * The organization an archive belongs to, for authorising a download of
     * a file whose customer no longer exists.
     */
    public function organizationIdOf(string $path): ?int
    {
        $raw = Storage::disk(self::DISK)->get($path);
        $data = json_decode((string) $raw, true);

        return isset($data['organization_id']) ? (int) $data['organization_id'] : null;
    }
}
