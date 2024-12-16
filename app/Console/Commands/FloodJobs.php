<?php

namespace App\Console\Commands;

use App\Jobs\TestJobLimitA;
use App\Jobs\TestJobLimitB;
use App\Jobs\TestJobLimitC;
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
        for ($i = 0; $i < 1000; $i++) {
            TestJobLimitA::dispatch();
        }

        for ($i = 0; $i < 1000; $i++) {
            TestJobLimitB::dispatch();
        }

        for ($i = 0; $i < 20; $i++) {
            TestJobLimitC::dispatch();
        }

        TestJobWithoutRateLimit::dispatch();
    }
}
