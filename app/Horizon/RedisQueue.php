<?php

namespace App\Horizon;

use Laravel\Horizon\RedisQueue as BaseQueue;

class RedisQueue extends BaseQueue
{
    /**
     * @param $queue
     * @param $block
     * @return array|null[]
     */
    protected function retrieveNextJob($queue, $block = true)
    {
        $nextJob = $this->getConnection()->eval(
            LuaScripts::pop(), 4, $queue, $queue.':reserved', $queue.':notify', '',
            $this->availableAt($this->retryAfter)
        );

        if (empty($nextJob)) {
            return [null, null];
        }

        [$job, $reserved] = $nextJob;

        if (! $job && ! is_null($this->blockFor) && $block &&
            $this->getConnection()->blpop([$queue.':notify'], $this->blockFor)) {
            return $this->retrieveNextJob($queue, false);
        }

        return [$job, $reserved];
    }
}
