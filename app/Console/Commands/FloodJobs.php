<?php

namespace App\Console\Commands;

use App\Jobs\TestJob;
use App\Jobs\TestJobWithoutRateLimit;
use Illuminate\Console\Command;

class FloodJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flood-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add many jobs to the queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        for ($i = 0; $i < 20; $i++) {
            // Max 5 jobs every 2 seconds
            TestJob::dispatch('rate-limit-key', 5, 2);

            // No rate limit
            TestJobWithoutRateLimit::dispatch();
        }
    }
}
