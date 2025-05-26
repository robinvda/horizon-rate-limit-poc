<?php

namespace App\Tasks\Queues;

use Illuminate\Support\Facades\RateLimiter;

abstract class TaskQueue
{
    /**
     * @var int The number of tasks that are allowed.
     */
    public static int $limit = 1;

    /**
     * @var int The window (in seconds) in which $limit number of tasks are allowed to execute.
     */
    public static int $window = 60;

    public static function attempt($key): bool
    {
        if (RateLimiter::tooManyAttempts($key, static::$limit)) {
            return false;
        }

        RateLimiter::hit($key, static::$window);

        return true;
    }
}
