<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OnePerSec implements ShouldQueue
{
    use Queueable;

    public string $rateLimitKey = 'one-per-sec';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //ray()->count('With limit B');
    }
}
