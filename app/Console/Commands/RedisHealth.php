<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RedisHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Redis server health, status, and details';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $output = [];
        $output[] = "=== Redis Health Check ===";
        $output[] = "";

        try {
            // Test connection
            $output[] = "Testing Redis connection...";
            $pingResult = Redis::ping();
            $output[] = "PING: " . ($pingResult === 'PONG' || $pingResult === true ? 'OK' : 'FAILED');
            $output[] = "";

            // Get Redis server info
            $output[] = "=== Redis Server Information ===";
            $info = Redis::info();
            
            // Handle case where info() returns string instead of array
            if (is_string($info)) {
                $infoArray = [];
                foreach (explode("\r\n", $info) as $line) {
                    if (empty($line) || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (str_contains($line, ':')) {
                        [$key, $value] = explode(':', $line, 2);
                        $infoArray[trim($key)] = trim($value);
                    }
                }
                $info = $infoArray;
            }
            
            // Server section
            if (isset($info['redis_version'])) {
                $output[] = "Redis Version: " . $info['redis_version'];
            }
            if (isset($info['os'])) {
                $output[] = "OS: " . $info['os'];
            }
            if (isset($info['process_id'])) {
                $output[] = "Process ID: " . $info['process_id'];
            }
            if (isset($info['tcp_port'])) {
                $output[] = "TCP Port: " . $info['tcp_port'];
            }
            $output[] = "";

            // Memory section
            $output[] = "=== Memory Information ===";
            if (isset($info['used_memory_human'])) {
                $output[] = "Used Memory: " . $info['used_memory_human'];
            }
            if (isset($info['used_memory_peak_human'])) {
                $output[] = "Peak Memory: " . $info['used_memory_peak_human'];
            }
            if (isset($info['mem_fragmentation_ratio'])) {
                $output[] = "Memory Fragmentation Ratio: " . $info['mem_fragmentation_ratio'];
            }
            if (isset($info['used_memory_rss_human'])) {
                $output[] = "Used Memory RSS: " . $info['used_memory_rss_human'];
            }
            $output[] = "";

            // Clients section
            $output[] = "=== Clients ===";
            if (isset($info['connected_clients'])) {
                $output[] = "Connected Clients: " . $info['connected_clients'];
            }
            if (isset($info['blocked_clients'])) {
                $output[] = "Blocked Clients: " . $info['blocked_clients'];
            }
            $output[] = "";

            // Stats section
            $output[] = "=== Statistics ===";
            if (isset($info['total_connections_received'])) {
                $output[] = "Total Connections Received: " . number_format($info['total_connections_received']);
            }
            if (isset($info['total_commands_processed'])) {
                $output[] = "Total Commands Processed: " . number_format($info['total_commands_processed']);
            }
            if (isset($info['instantaneous_ops_per_sec'])) {
                $output[] = "Instantaneous Ops Per Sec: " . $info['instantaneous_ops_per_sec'];
            }
            if (isset($info['keyspace_hits'])) {
                $output[] = "Keyspace Hits: " . number_format($info['keyspace_hits']);
            }
            if (isset($info['keyspace_misses'])) {
                $output[] = "Keyspace Misses: " . number_format($info['keyspace_misses']);
            }
            if (isset($info['keyspace_hits']) && isset($info['keyspace_misses'])) {
                $total = $info['keyspace_hits'] + $info['keyspace_misses'];
                if ($total > 0) {
                    $hitRate = ($info['keyspace_hits'] / $total) * 100;
                    $output[] = "Hit Rate: " . number_format($hitRate, 2) . "%";
                }
            }
            $output[] = "";

            // Keyspace section
            $output[] = "=== Keyspace ===";
            $keyspaceFound = false;
            foreach ($info as $key => $value) {
                if (str_starts_with($key, 'db')) {
                    $keyspaceFound = true;
                    $output[] = "$key: $value";
                }
            }
            if (!$keyspaceFound) {
                $output[] = "No databases configured or no keys found";
            }
            $output[] = "";

            // Get database count
            $dbCount = 0;
            foreach ($info as $key => $value) {
                if (str_starts_with($key, 'db')) {
                    // Extract key count from value (e.g., "keys=123,expires=0")
                    if (preg_match('/keys=(\d+)/', $value, $matches)) {
                        $dbCount += (int)$matches[1];
                    }
                }
            }
            $output[] = "Total Keys: " . $dbCount;
            $output[] = "";

            // Get connection configuration
            $output[] = "=== Connection Configuration ===";
            $redisConfig = config('database.redis.default');
            if ($redisConfig) {
                $output[] = "Host: " . ($redisConfig['host'] ?? 'localhost');
                $output[] = "Port: " . ($redisConfig['port'] ?? 6379);
                $output[] = "Database: " . ($redisConfig['database'] ?? 0);
            }
            $output[] = "";

            $output[] = "=== Status: HEALTHY ===";
            
        } catch (\Exception $e) {
            $output[] = "=== ERROR ===";
            $output[] = "Failed to connect to Redis: " . $e->getMessage();
            $output[] = "";
            $output[] = "Connection Details:";
            $redisConfig = config('database.redis.default');
            if ($redisConfig) {
                $output[] = "Host: " . ($redisConfig['host'] ?? 'localhost');
                $output[] = "Port: " . ($redisConfig['port'] ?? 6379);
            }
            $output[] = "";
            $output[] = "=== Status: UNHEALTHY ===";
            
            // Return non-zero exit code for unhealthy
            $this->error(implode("\n", $output));
            return 1;
        }

        $this->info(implode("\n", $output));
        return 0;
    }
}
