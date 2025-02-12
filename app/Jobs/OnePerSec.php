<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OnePerSec implements ShouldQueue, RateLimited
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }

    public function rateLimit()
    {
        return [
            'key' => 'one-per-sec',
            'limit' => 1,
            'window' => 1, // seconds
        ];
    }
}
