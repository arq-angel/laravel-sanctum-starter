<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all log files in storage/logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $files = glob(storage_path('logs/*.log')); // Get all log files
        foreach ($files as $file) {
            file_put_contents($file, ''); // Truncate each file
        }

        $this->info('Logs have been cleared!');
    }
}
