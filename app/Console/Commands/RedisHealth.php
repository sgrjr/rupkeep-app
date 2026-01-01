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

        // Check configuration and extension
        $redisConfig = config('database.redis.default');
        $redisClient = config('database.redis.client', 'phpredis');
        
        $output[] = "Redis Client: " . $redisClient;
        
        // Check if phpredis extension is loaded when using phpredis client
        if ($redisClient === 'phpredis' && !extension_loaded('redis')) {
            $output[] = "";
            $output[] = "=== ERROR ===";
            $output[] = "PHP Redis extension (phpredis) is not installed or not loaded.";
            $output[] = "";
            $output[] = "To fix this issue:";
            $output[] = "";
            $output[] = "Option 1: Install PHP Redis extension";
            $output[] = "  Ubuntu/Debian: sudo apt-get install php-redis php8.x-redis";
            $output[] = "  CentOS/RHEL:   sudo yum install php-redis";
            $output[] = "  Via PECL:      pecl install redis";
            $output[] = "";
            $output[] = "  After installation, restart PHP-FPM/web server:";
            $output[] = "  sudo systemctl restart php-fpm  (or php8.x-fpm)";
            $output[] = "  sudo systemctl restart nginx    (or apache2)";
            $output[] = "";
            $output[] = "Option 2: Switch to Predis client (PHP library, no extension needed)";
            $output[] = "  Set REDIS_CLIENT=predis in your .env file";
            $output[] = "  Then run: php artisan config:clear";
            $output[] = "";
            $output[] = "Connection Configuration:";
            if ($redisConfig) {
                $output[] = "  Host: " . ($redisConfig['host'] ?? 'localhost');
                $output[] = "  Port: " . ($redisConfig['port'] ?? 6379);
                $output[] = "  Database: " . ($redisConfig['database'] ?? 0);
            }
            $output[] = "";
            $output[] = "=== Status: UNHEALTHY ===";
            $this->error(implode("\n", $output));
            return 1;
        }
        
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
            
            // Handle case where info() returns string instead of array (predis)
            if (is_string($info)) {
                $infoArray = [];
                $lines = preg_split('/\r?\n/', $info);
                foreach ($lines as $line) {
                    $line = trim($line);
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
            $errorMessage = $e->getMessage();
            $output[] = "Failed to connect to Redis: " . $errorMessage;
            $output[] = "";
            
            // Check if it's the phpredis extension error
            if (str_contains($errorMessage, 'Class "Redis" not found') || str_contains($errorMessage, 'phpredis')) {
                $output[] = "This error indicates the PHP Redis extension is not installed.";
                $output[] = "";
                $output[] = "To fix:";
                $output[] = "  1. Install: sudo apt-get install php-redis (or php8.x-redis for specific PHP version)";
                $output[] = "  2. Restart PHP-FPM: sudo systemctl restart php-fpm";
                $output[] = "  3. Or switch to Predis: Set REDIS_CLIENT=predis in .env";
                $output[] = "";
            }
            
            $output[] = "Connection Details:";
            $redisConfig = config('database.redis.default');
            if ($redisConfig) {
                $output[] = "  Host: " . ($redisConfig['host'] ?? 'localhost');
                $output[] = "  Port: " . ($redisConfig['port'] ?? 6379);
                $output[] = "  Database: " . ($redisConfig['database'] ?? 0);
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
