<?php

namespace App\Horizon;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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

        // Here we add the jobs count of each sub queue
        foreach ($this->getRateLimits($this->getQueue($queue)) as $rateLimit) {
            $jobsCount = $this->getConnection()->llen($this->getQueue($queue) . ':' . $rateLimit->key);

            if (app()->runningInConsole()) {
                // When running in console (Horizon processes) we only count the jobs which can actually be executed,
                // which is either the total jobs count or the remaining rate limit attempts, depending on which is lowest
                $remainingAttempts = RateLimiter::remaining($rateLimit->key, $rateLimit->limit);

                $total += min($jobsCount, $remainingAttempts);
            } else {
                // When this is an API request (Horizon dashboard) we get all jobs that are in the queue
                $total += $jobsCount;
            }
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
        $queue = $this->getQueue($queue);

        $total = $this->getConnection()->eval(
            LuaScripts::size(), 3, $queue . ':', $queue . ':delayed', $queue . ':reserved'
        );

        foreach ($this->getRateLimits($queue) as $rateLimit) {
            $total += $this->getConnection()->eval(
                LuaScripts::size(), 3, $queue . ':' . $rateLimit->key, $queue . ':delayed', $queue . ':reserved'
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
            $payload->value, $payload->decoded['rateLimit']['key'] ?? '', json_encode($payload->decoded['rateLimit'] ?? '')
        );

        $this->event($this->getQueue($queue), new JobPushed($payload->value));

        return $payload->id();
    }

    /**
     * For each sub-queue we get the first (oldest) job and put it in an array where the key is the name of the queue ($availableJobs).
     * The job is only added to this array if the rate limit of that sub-queue allows it.
     * Then we execute the oldest job in that array.
     *
     * @param $queue
     * @param $block
     * @return array|null[]
     */
    protected function retrieveNextJob($queue, $block = true)
    {
        // We're using a lock to prevent multiple process running this code at the same time
        $lock = Cache::lock('lock-' . $queue, 1);

        try {
            // If we can't acquire a lock within 1 second, we'll report it (catch) and return nothing (no job was found)
            $lock->block(1);

            // Jobs in the default queue (without rate limit) are always available, so we'll add it here
            $availableJobs = [
                new RateLimitJob(null, json_decode($this->getNextJob($queue)[0] ?? '')),
            ];

            foreach ($this->getRateLimits($queue) as $rateLimit) {
                if (! RateLimiter::tooManyAttempts($rateLimit->key, $rateLimit->limit)) {
                    $availableJobs[] = new RateLimitJob($rateLimit, json_decode($this->getNextJob($queue, $rateLimit->key)[0] ?? ''));
                }
            }

            // We will sort the available jobs by the timestamp of when it was pushed to the queue
            // Then we take the first (oldest) one and that's the queue we will process a job for
            $rateLimitJob = Arr::first(Arr::sort(array_filter($availableJobs, fn($rateLimitJob) => !! $rateLimitJob->job), function ($rateLimitJob, $key) {
                return $rateLimitJob?->job?->pushedAt;
            }));

            if ($rateLimitJob) {
                $subQueue = $queue . ':';

                if ($rateLimitJob->rateLimit) {
                    // We've found a job so here we'll increase the attempts of the rate limit
                    RateLimiter::hit($rateLimitJob->rateLimit->key, $rateLimitJob->rateLimit->window);

                    // If there are no other jobs left for this rate limit, we'll clean it up
                    $jobsCount = $this->getConnection()->eval(LuaScripts::size(), 3, $queue . ':' . ($rateLimitJob->rateLimit->key ?? ''), $queue . ':delayed', $queue . ':reserved');

                    if ($jobsCount <= 1) {
                        $this->removeRateLimit($queue, $rateLimitJob->rateLimit->key ?? '');
                    }

                    $subQueue .= $rateLimitJob->rateLimit->key;
                }

                return parent::retrieveNextJob($subQueue, $block);
            }
        } catch (Throwable $e) {
            if (! ($e instanceof LockTimeoutException)) {
                report($e);
            }
        } finally {
            $lock->release();
        }

        return [null, null];
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
     * @return mixed
     */
    protected function getRateLimits(string $queue)
    {
        return array_filter(array_map(
            fn($value) => json_decode($value),
            $this->getConnection()->eval(LuaScripts::rateLimits(), 1, $queue),
        ));
    }

    /**
     * @param string $queue
     * @param string $rateLimitKey
     */
    protected function removeRateLimit(string $queue, string $rateLimitKey)
    {
        $this->getConnection()->eval(
            LuaScripts::removeRateLimit(), 1, $queue,
            $rateLimitKey
        );
    }
}

class RateLimitJob {
    public $rateLimit;
    public $job;

    public function __construct($rateLimit, $job)
    {
        $this->rateLimit = $rateLimit;
        $this->job = $job;
    }
}
