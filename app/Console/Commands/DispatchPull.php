<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DispatchPull extends Command
{
    protected $signature = 'dispatch:pull
        {--path=docs/tasks.jsonld : Where to write the fetched JSON-LD snapshot}
        {--dry-run : Fetch + write the file, but do not import to the local DB}';

    protected $description = 'Pull the canonical task state from production into the local DB (via dispatch.snapshot API).';

    public function handle(): int
    {
        $url = rtrim(config('dispatch.remote.url') ?? '', '/');
        $token = config('dispatch.remote.token');

        if (!$url || !$token) {
            $this->error('Set DISPATCH_REMOTE_URL and DISPATCH_REMOTE_TOKEN in .env first.');
            return self::FAILURE;
        }

        $endpoint = $url . '/api/dispatch/snapshot';
        $this->line("Fetching {$endpoint} …");

        $client = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('dispatch.remote.timeout', 30));

        if (! config('dispatch.remote.verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        $response = $client->get($endpoint);

        if (!$response->successful()) {
            $this->error("HTTP {$response->status()}: " . substr($response->body(), 0, 500));
            return self::FAILURE;
        }

        $path = base_path($this->option('path'));
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);

        $body = $response->body();
        // Pretty-print for git diffability
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $body = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }
        file_put_contents($path, $body);

        $taskCount = is_array($decoded['tasks'] ?? null) ? count($decoded['tasks']) : '?';
        $labelCount = is_array($decoded['labels'] ?? null) ? count($decoded['labels']) : '?';
        $this->info("Wrote {$path} ({$taskCount} tasks, {$labelCount} labels).");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — local DB not modified. Run `php artisan tasks:import` to apply.');
            return self::SUCCESS;
        }

        $this->call('tasks:import', ['--path' => $this->option('path')]);
        return self::SUCCESS;
    }
}
