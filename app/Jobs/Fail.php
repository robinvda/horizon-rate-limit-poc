<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class Fail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 2;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ray('Fail test job');
        throw new RuntimeException('Fail test job');
    }
}
