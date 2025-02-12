<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OnePerMin implements ShouldQueue, RateLimited
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
            'key' => 'one-per-min',
            'limit' => 1,
            'window' => 60, // seconds
        ];
    }
}
