<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TestJobLimitA implements ShouldQueue
{
    use Queueable;

    public string $rateLimitKey = 'limit-a';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //ray()->count('With limit A');
    }
}
