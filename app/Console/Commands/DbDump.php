<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * A copy of the database before anything risky (TASK-470). The in-app
 * deploy runs this before it pulls and migrates; it is also the building
 * block for the scheduled backup (TASK-103).
 *
 * MySQL goes through mysqldump; SQLite is a file copy. Either lands on the
 * private disk under backups/db and the oldest dumps beyond --keep are
 * pruned.
 */
class DbDump extends Command
{
    public const DISK = 'local';

    public const DIRECTORY = 'backups/db';

    protected $signature = 'db:dump
                            {--connection= : Database connection to dump (default: the default connection)}
                            {--keep=14 : How many dumps to keep on disk}';

    protected $description = 'Dump the default database to storage/app/private/backups/db';

    public function handle(): int
    {
        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $config = (array) config("database.connections.{$connection}", []);
        $driver = $config['driver'] ?? $connection;
        $stamp = now()->format('Ymd-His');
        $disk = Storage::disk(self::DISK);

        try {
            $path = match ($driver) {
                'sqlite' => $this->dumpSqlite($config, $stamp),
                'mysql', 'mariadb' => $this->dumpMysql($config, $stamp),
                default => throw new \RuntimeException("db:dump does not support the {$driver} driver."),
            };
        } catch (\Throwable $e) {
            $this->error('Database dump failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->prune((int) $this->option('keep'));

        $this->info(sprintf('Dumped %s (%s) to %s', $connection, $driver, $path));

        return self::SUCCESS;
    }

    protected function dumpSqlite(array $config, string $stamp): string
    {
        $file = (string) ($config['database'] ?? '');

        if ($file === '' || $file === ':memory:' || ! is_file($file)) {
            throw new \RuntimeException("SQLite database file not found: {$file}");
        }

        $path = self::DIRECTORY.'/'.$stamp.'.sqlite';

        if (Storage::disk(self::DISK)->put($path, file_get_contents($file)) === false) {
            throw new \RuntimeException('Could not write the dump.');
        }

        return $path;
    }

    protected function dumpMysql(array $config, string $stamp): string
    {
        $command = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? 3306),
            '-u', (string) ($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ];

        $process = new Process($command, base_path(), ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump exited with '.$process->getExitCode());
        }

        $path = self::DIRECTORY.'/'.$stamp.'.sql';

        if (Storage::disk(self::DISK)->put($path, $process->getOutput()) === false) {
            throw new \RuntimeException('Could not write the dump.');
        }

        return $path;
    }

    protected function prune(int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        $files = collect($disk->files(self::DIRECTORY))->sort()->values();

        foreach ($files->slice(0, max(0, $files->count() - $keep)) as $old) {
            $disk->delete($old);
        }
    }
}
