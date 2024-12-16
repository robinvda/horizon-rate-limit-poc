<?php

namespace App\Horizon;

use Illuminate\Support\Facades\RateLimiter;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\JobPayload;
use Laravel\Horizon\RedisQueue as BaseQueue;

class RedisQueue extends BaseQueue
{
    /**
     * Get the number of queue jobs that are ready to process.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function readyNow($queue = null)
    {
        $total = $this->getConnection()->llen($this->getQueue($queue) . ':');

        foreach (config('rate-limit.keys') as $rateLimitKey => $rateLimit) {
            $total += $this->getConnection()->llen($this->getQueue($queue) . ':' . $rateLimitKey);
        }

        return $total;
    }

    /**
     * Get the size of the queue.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function size($queue = null)
    {
        $queue = $this->getQueue($queue);

        $total = $this->getConnection()->eval(
            LuaScripts::size(), 3, $queue . ':', $queue.':delayed', $queue.':reserved'
        );

        foreach (config('rate-limit.keys') as $rateLimitKey => $rateLimit) {
            $total += $this->getConnection()->eval(
                LuaScripts::size(), 3, $queue . ':' . $rateLimitKey, $queue.':delayed', $queue.':reserved'
            );
        }

        return $total;
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $payload = (new JobPayload($payload))->prepare($this->lastPushed);

        $this->getConnection()->eval(
            LuaScripts::push(), 2, $this->getQueue($queue), $this->getQueue($queue).':notify',
            $payload->value, $payload->decoded['rateLimitKey'] ?? ''
        );

        $this->event($this->getQueue($queue), new JobPushed($payload->value));

        return $payload->id();
    }

    /**
     * @param $queue
     * @param $block
     * @return array|null[]
     */
    protected function retrieveNextJob($queue, $block = true)
    {
        // First check the normal queue (without rate limits)
        $nextJob = $this->getNextJob($queue);

        if (empty($nextJob)) {
            foreach (config('rate-limit.keys') as $rateLimitKey => $rateLimit) {
                if (RateLimiter::attempt($rateLimitKey, $rateLimit['limit'], function () use (&$nextJob, $queue, $rateLimitKey) {
                        $nextJob = $this->getNextJob($queue, $rateLimitKey);
                    }, decaySeconds: $rateLimit['window']) && $nextJob) {
                    break;
                }
            }
        }

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

    /**
     * @param string $queue
     * @param string $rateLimitKey
     * @return mixed
     */
    protected function getNextJob(string $queue, string $rateLimitKey = '')
    {
        $nextJob = $this->getConnection()->eval(
            LuaScripts::pop(), 3, $queue . ':' . $rateLimitKey, $queue . ':reserved', $queue . ':notify',
            $this->availableAt($this->retryAfter)
        );

        [$job] = $nextJob;

        return $job ? $nextJob : null;
    }
}
