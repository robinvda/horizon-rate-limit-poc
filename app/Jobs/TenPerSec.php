<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TenPerSec implements ShouldQueue, RateLimited
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
            'key' => 'ten-per-sec',
            'limit' => 10,
            'window' => 1, // seconds
        ];
    }
}
