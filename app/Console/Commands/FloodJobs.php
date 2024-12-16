<?php

namespace App\Console\Commands;

use App\Jobs\TestJobLimitA;
use App\Jobs\TestJobLimitB;
use App\Jobs\TestJobLimitC;
use App\Jobs\TestJobWithoutRateLimit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

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
        for ($i = 0; $i < 200; $i++) {
            //TestJobLimitA::dispatch();
        }

        for ($i = 0; $i < 100; $i++) {
            TestJobLimitB::dispatch();
        }

        for ($i = 0; $i < 5; $i++) {
            TestJobLimitC::dispatch();
        }

        TestJobWithoutRateLimit::dispatch();
    }
}
