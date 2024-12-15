<?php

namespace App\Horizon;

use Illuminate\Queue\LuaScripts as BaseScripts;

class LuaScripts extends BaseScripts
{
    /**
     * Get the Lua script for popping the next job off of the queue.
     *
     * KEYS[1] - The queue to pop jobs from, for example: queues:foo
     * KEYS[2] - The queue to place reserved jobs on, for example: queues:foo:reserved
     * KEYS[3] - The notify queue
     * ARGV[1] - The time at which the reserved job will expire
     *
     * @return string
     */
    public static function pop(): string
    {
        return <<<'LUA'
local function rateLimit(key, limit, window)
    local key = key           -- The key to rate limit
    local limit = tonumber(limit) -- The maximum number of requests allowed
    local window = tonumber(window) -- The time window in seconds

    -- Get the current count of requests for the key
    local current = redis.call('GET', key)

    if current and tonumber(current) >= limit then
        return false -- Rate limit exceeded
    else
        -- If the key does not exist or the limit has not been exceeded, increment the count
        if not current then
            redis.call('SET', key, 1, 'EX', window) -- Set the key with an expiration time
            return true -- Allowed
        else
            redis.call('INCR', key) -- Increment the count
            return true -- Allowed
        end
    end
end

-- Pop the first job off of the queue...
local job = false
local reserved = false

local decoded = false
local jobs = redis.call('lrange', KEYS[1], 0, -1)
for i=1,#jobs do
    decoded = cjson.decode(jobs[i])

    -- If there's no rate limit set or if it passes the rate limit check, we will pull the job from the queue
    if(decoded['rateLimitKey'] == nil or rateLimit(decoded['rateLimitKey'], decoded['rateLimit'], decoded['rateLimitWindow'])) then
        job = jobs[i]

        -- Since we can't remove a specific index, we empty the value so we can remove it later
        redis.call('lset', KEYS[1], i - 1, '')
        break
    end
end

-- Remove empty values from the queue, effectively removing the job from the queue
redis.call('lrem', KEYS[1], 0, '')

if(job ~= false) then
    -- Increment the attempt count and place job on the reserved queue...
    reserved = cjson.decode(job)
    reserved['attempts'] = reserved['attempts'] + 1
    reserved = cjson.encode(reserved)
    redis.call('zadd', KEYS[2], ARGV[1], reserved)
    redis.call('lpop', KEYS[3])
end

return {job, reserved}
LUA;
    }
}
