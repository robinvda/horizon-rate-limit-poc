<?php

namespace App\Tasks;

use App\Tasks\Queues\ProcessingQueue;

class ProcessTask extends Task
{
    public function __construct(public int $value)
    {
    }

    /**
     * @return class-string
     */
    public function queue(): string
    {
        return ProcessingQueue::class;
    }

    public function subQueue(): string
    {
        return $this->value;
    }

    public function handle()
    {
        ray('Processing ' . $this->value);
        sleep(1); // Simulate work
    }
}
