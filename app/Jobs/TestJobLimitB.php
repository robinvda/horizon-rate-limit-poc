<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TestJobLimitB implements ShouldQueue
{
    use Queueable;

    public string $rateLimitKey = 'limit-b';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ray()->count('With limit B');
    }
}
