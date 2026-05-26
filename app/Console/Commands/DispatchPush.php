<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DispatchPush extends Command
{
    protected $signature = 'dispatch:push
        {--path=docs/tasks.jsonld : JSON-LD file to upload (re-exported from local DB first)}
        {--skip-export : Use the existing file as-is; do not re-export from the local DB}';

    protected $description = 'Push local task state to production (via dispatch.apply API).';

    public function handle(): int
    {
        $url = rtrim(config('dispatch.remote.url') ?? '', '/');
        $token = config('dispatch.remote.token');

        if (!$url || !$token) {
            $this->error('Set DISPATCH_REMOTE_URL and DISPATCH_REMOTE_TOKEN in .env first.');
            return self::FAILURE;
        }

        $path = base_path($this->option('path'));

        if (!$this->option('skip-export')) {
            $this->call('tasks:export', ['--path' => $this->option('path')]);
        }

        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $payload = json_decode(file_get_contents($path), true);
        if (!is_array($payload)) {
            $this->error('File is not valid JSON.');
            return self::FAILURE;
        }

        $endpoint = $url . '/api/dispatch/apply';
        $this->line("Pushing to {$endpoint} …");

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('dispatch.remote.timeout', 30))
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            $this->error("HTTP {$response->status()}: " . substr($response->body(), 0, 500));
            return self::FAILURE;
        }

        $body = $response->json();
        $this->info('Pushed.');
        if (isset($body['summary'])) {
            foreach ($body['summary'] as $k => $v) {
                $this->line("  {$k}: {$v}");
            }
        }
        return self::SUCCESS;
    }
}
