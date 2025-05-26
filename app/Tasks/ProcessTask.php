<?php

namespace App\Tasks;

use App\Tasks\Queues\ProcessingQueue;
use Illuminate\Support\Facades\Redis;

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
        Redis::command('incr', ["task:$this->value"]);
    }
}
