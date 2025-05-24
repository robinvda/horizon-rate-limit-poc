<?php

namespace App\Tasks;

use Illuminate\Support\Facades\Redis;

abstract class Task
{
    const REDIS_KEY_QUEUES = 'laravel-tasks:queues';

    public abstract function queue(): string;

    public abstract function handle();

    public static function dispatch(...$arguments)
    {
        $task = new static(...$arguments);

        $queue = $task->queue();

        Redis::command('rpush', [$queue, serialize($task)]);

        Redis::command('sadd', [static::REDIS_KEY_QUEUES, $queue]);
    }
}
