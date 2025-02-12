<?php

namespace App\Providers;

use App\Horizon\RedisConnector;
use App\Horizon\RedisQueue;
use App\Jobs\RateLimited;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $jobData = Arr::get($payload, 'data.command');

            if ($jobData instanceof RateLimited) {
                $payload['rateLimit'] = $jobData->rateLimit();
            }

            return $payload;
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->callAfterResolving(QueueManager::class, function ($manager) {
            $manager->addConnector('redis', function () {
                return new RedisConnector($this->app['redis']);
            });
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
}
