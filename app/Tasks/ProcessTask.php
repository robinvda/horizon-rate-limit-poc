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
        return rand(1, 3);
    }

    public function handle()
    {
        ray('Processing ' . $this->value);
        for ($i = 0; $i < 1000000000; $i++) {
            $a = $i * pi();
        }
        ray('Processed ' . $this->value);
    }
}
