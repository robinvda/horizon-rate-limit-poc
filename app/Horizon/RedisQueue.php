<?php

namespace App\Horizon;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\JobPayload;
use Laravel\Horizon\RedisQueue as BaseQueue;
use Throwable;

class RedisQueue extends BaseQueue
{
    /**
     * Get the number of queue jobs that are ready to process.
     *
     * @param string|null $queue
     * @return int
     */
    public function readyNow($queue = null)
    {
        $total = $this->getConnection()->llen($this->getQueue($queue) . ':');

        foreach (config('rate-limit.queues.' . $queue, []) as $rateLimitKey => $rateLimit) {
            $total += $this->getConnection()->llen($this->getQueue($queue) . ':' . $rateLimitKey);
        }

        return $total;
    }

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size($queue = null)
    {
        $baseQueue = $queue;
        $queue = $this->getQueue($queue);

        $total = $this->getConnection()->eval(
            LuaScripts::size(), 3, $queue . ':', $queue . ':delayed', $queue . ':reserved'
        );

        foreach (config('rate-limit.queues.' . $baseQueue, []) as $rateLimitKey => $rateLimit) {
            $total += $this->getConnection()->eval(
                LuaScripts::size(), 3, $queue . ':' . $rateLimitKey, $queue . ':delayed', $queue . ':reserved'
            );
        }

        return $total;
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string $queue
     * @param array $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $payload = (new JobPayload($payload))->prepare($this->lastPushed);

        $this->getConnection()->eval(
            LuaScripts::push(), 2, $this->getQueue($queue), $this->getQueue($queue) . ':notify',
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
        $lock = Cache::lock('lock-' . $queue, 1);

        try {
            $lock->block(1);

            // For each sub-queue we will get the first (oldest) job and put it in an array where the key is the name of the queue ($availableJobs)
            // The job is only added to this array if the rate limit of that sub-queue allows it

            $availableJobs = [
                $queue . ':' => $this->getNextJob($queue)[0] ?? null,
            ];

            foreach (config('rate-limit.queues.' . explode(':', $queue)[1], []) as $rateLimitKey => $rateLimit) {
                RateLimiter::attempt($rateLimitKey, $rateLimit['limit'], function () use (&$availableJobs, $queue, $rateLimitKey) {
                    $availableJobs[$queue . ':' . $rateLimitKey] = $this->getNextJob($queue, $rateLimitKey)[0] ?? null;
                }, decaySeconds: $rateLimit['window']);
            }

            // We will sort the available jobs by the timestamp of when it was pushed to the queue
            // Then we take the first (oldest) one and that's the queue we will process a job for
            $queue = array_key_first(Arr::sort(array_filter($availableJobs), function ($value, $key) {
                return json_decode($value)->pushedAt ?? null;
            }));

            if (! $queue) {
                return [null, null];
            }

            return parent::retrieveNextJob($queue, $block);
        } catch (Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param string $queue
     * @param string $rateLimitKey
     * @return mixed
     */
    protected function getNextJob(string $queue, string $rateLimitKey = '')
    {
        return $this->getConnection()->eval(
            LuaScripts::first(), 1, $queue . ':' . $rateLimitKey,
        );
    }

    /**
     * @param string $queue
     * @param string $rateLimitKey
     * @return mixed
     */
    protected function popNextJob(string $queue)
    {
        return $this->getConnection()->eval(
            LuaScripts::pop(), 3, $queue, $queue . ':reserved', $queue . ':notify',
            $this->availableAt($this->retryAfter)
        );
    }
}
