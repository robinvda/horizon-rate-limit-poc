<?php

namespace App\Horizon;

use Illuminate\Queue\LuaScripts as BaseScripts;

class LuaScripts extends BaseScripts
{
    public static function push()
    {
        return <<<'LUA'
-- Push the job onto a queue
redis.call('rpush', KEYS[1] .. ':' .. ARGV[2], ARGV[1])

-- Push a notification onto the "notify" queue...
redis.call('rpush', KEYS[2], 1)
LUA;
    }
}
