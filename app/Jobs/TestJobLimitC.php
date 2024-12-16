<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TestJobLimitC implements ShouldQueue
{
    use Queueable;

    public string $rateLimitKey = 'limit-c';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ray()->count('With limit C');
    }
}
