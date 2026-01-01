<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assets:build';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild JavaScript assets using npm run build';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Building JavaScript assets...');
        
        // Execute npm run build in project root
        $process = new Process(['npm', 'run', 'build'], base_path());
        
        // Set timeout for long-running process (5 minutes)
        $process->setTimeout(300);
        
        // Run the process and capture output
        $process->run(function ($type, $buffer) {
            // Output in real-time to console
            if ($type === Process::ERR) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });
        
        // Return exit code (0 = success, non-zero = failure)
        return $process->getExitCode();
    }
}
