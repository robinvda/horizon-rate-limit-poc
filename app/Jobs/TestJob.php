<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TestJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param string $rateLimitKey
     * @param int $rateLimit
     * @param int $rateLimitWindow
     */
    public function __construct(
        public string $rateLimitKey,
        public int    $rateLimit, // Number of jobs
        public int    $rateLimitWindow, // Window in seconds
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ray()->count('with limit');
    }
}
