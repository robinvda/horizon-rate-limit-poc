<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TenPerSec implements ShouldQueue
{
    use Queueable;

    public string $rateLimitKey = 'ten-per-sec';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //ray()->count('With limit A');
    }
}
