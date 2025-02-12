<?php

namespace App\Horizon;

use Illuminate\Queue\LuaScripts as BaseScripts;

class LuaScripts extends BaseScripts
{
    /**
     * Get the Lua script for pushing jobs onto the queue.
     *
     * KEYS[1] - The queue to push the job onto, for example: queues:foo
     * KEYS[2] - The notification list for the queue we are pushing jobs onto, for example: queues:foo:notify
     * ARGV[1] - The job payload
     *
     * @return string
     */
    public static function push()
    {
        return <<<'LUA'
-- Push to sub queues
redis.call('hset', KEYS[1] .. '::rate_limits', ARGV[2], ARGV[3])

-- Push the job onto a queue
redis.call('rpush', KEYS[1] .. ':' .. ARGV[2], ARGV[1])

-- Push a notification onto the "notify" queue...
redis.call('rpush', KEYS[2], 1)
LUA;
    }

    /**
     * @return string
     */
    public static function first()
    {
        return <<<'LUA'
return redis.call('lrange', KEYS[1], 0, 0)
LUA;
    }

    /**
     * @return string
     */
    public static function rateLimits()
    {
        return <<<'LUA'
return redis.call('hvals', KEYS[1] .. '::rate_limits')
LUA;
    }

    /**
     * @return string
     */
    public static function removeRateLimit()
    {
        return <<<'LUA'
return redis.call('hdel', KEYS[1] .. '::rate_limits', ARGV[1])
LUA;
    }
}
