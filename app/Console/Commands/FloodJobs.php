<?php

namespace App\Console\Commands;

use App\Jobs\TenPerSec;
use App\Jobs\OnePerSec;
use App\Jobs\OnePerMin;
use App\Jobs\TestJobWithoutRateLimit;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FloodJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flood-jobs {tenSec=20} {oneSec=10} {oneMin=2} {noRateLimit=10}';

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
        $tenSec = (int)$this->argument('tenSec');
        $oneSec = (int)$this->argument('oneSec');
        $oneMin = (int)$this->argument('oneMin');
        $noRateLimit = (int)$this->argument('noRateLimit');

        collect(range(1, $tenSec))->each(fn () => TenPerSec::dispatch(Carbon::now()));
        collect(range(1, $oneSec))->each(fn () => OnePerSec::dispatch(Carbon::now()));
        collect(range(1, $oneMin))->each(fn () => OnePerMin::dispatch(Carbon::now()));
        collect(range(1, $noRateLimit))->each(fn () => TestJobWithoutRateLimit::dispatch(Carbon::now()));
    }
}
