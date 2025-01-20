<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OnePerMin implements ShouldQueue
{
    use Queueable;

    public string $rateLimitKey = 'one-per-min';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //ray()->count('With limit C');
    }
}
