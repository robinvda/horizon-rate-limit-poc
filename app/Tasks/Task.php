<?php

namespace App\Tasks;

use App\Tasks\Queues\TaskQueue;
use Illuminate\Support\Facades\Redis;

abstract class Task
{
    const REDIS_KEY_QUEUES = 'laravel-tasks:queues';

    /**
     * @return class-string<TaskQueue>
     */
    public abstract function queue(): string;

    public abstract function handle();

    /**
     * Each sub queue has its own rate limit, using the properties of the queue
     */
    public function subQueue(): ?string
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
        return $this->queue() . ':' . $this->subQueue();
    }
}
