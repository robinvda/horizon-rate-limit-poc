<?php

namespace App\Tasks;

use Illuminate\Support\Facades\Redis;

abstract class Task
{
    const REDIS_KEY_QUEUES = 'laravel-tasks:queues';

    public abstract function queue(): string;

    public abstract function handle();

    public function subqueue(): ?string
    {
        return null;
    }

    public static function dispatch(...$arguments)
    {
        $task = new static(...$arguments);

        $queue = $task->makeQueue();

        Redis::command('rpush', [$queue, serialize($task)]);

        Redis::command('sadd', [static::REDIS_KEY_QUEUES, $queue]);
    }

    protected function makeQueue(): string
    {
        return $this->queue() . ':' . $this->subqueue();
    }
}
