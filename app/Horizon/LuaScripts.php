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
-- Add rate limit
redis.call('hset', KEYS[1] .. '::rate_limits', ARGV[2], ARGV[3])

local queue = KEYS[1]
if ARGV[2] ~= '' then
    queue = queue .. ':' .. ARGV[2]
end

-- Push the job onto a queue
redis.call('rpush', queue, ARGV[1])

-- Push a notification onto the "notify" queue...
redis.call('rpush', KEYS[2], 1)
LUA;
    }

    /**
     * Get the Lua script for releasing reserved jobs.
     *
     * KEYS[1] - The "delayed" queue we release jobs onto, for example: queues:foo:delayed
     * KEYS[2] - The queue the jobs are currently on, for example: queues:foo:reserved
     * ARGV[1] - The raw payload of the job to add to the "delayed" queue
     * ARGV[2] - The UNIX timestamp at which the job should become available
     *
     * @return string
     */
    public static function release()
    {
        return <<<'LUA'
-- Remove the job from the current queue...
redis.call('zrem', KEYS[2], ARGV[1])

-- Add rate limit
redis.call('hset', KEYS[3] .. '::rate_limits', ARGV[3], ARGV[4])

-- Add the job onto the "delayed" queue...
redis.call('zadd', KEYS[1], ARGV[2], ARGV[1])

return true
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
