<?php

namespace App\Console\Commands;

use App\Tasks\Queues\TaskQueue;
use App\Tasks\Task;
use Illuminate\Console\Command;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TaskSupervisor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:supervisor {workers=5} {wait=1} {maxTasks=50}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a task supervisor';

    /**
     * @var Collection<string, InvokedProcess>
     */
    protected Collection $workers;

    protected string $id;

    protected ?string $processingQueue;
    protected ?string $processingSerializedTask;

    protected bool $shouldExit = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->id = Str::uuid()->toString();

        $this->setExitHandlers();

        Redis::command('sadd', ['task-supervisors', $this->id]);

        $this->workers = collect();

        for ($i = 0; $i < $this->argument('workers'); $i++) {
            $this->createWorker();
        }

        if (! $this->waitForWorkers()) {
            $this->error('Some workers were unable to start');

            $this->stopWorkers();

            return static::FAILURE;
        }

        $this->info('Workers started, waiting for tasks...');

        $this->waitForTasks();

        return static::SUCCESS;
    }

    protected function waitForTasks(): void
    {
        $this->handleExit();

        $taskFound = false;

        foreach (Redis::command('smembers', [Task::REDIS_KEY_QUEUES]) as $queue) {
            $this->handleExit();

            $this->processingQueue = $queue;

            list($queueClass, $subQueue) = explode(':', $queue);

            /** @var class-string<TaskQueue> $queueClass */
            if ($queueClass::attempt($queue)) {
                // If the supervisor stops (without signals) between popping the task and assigning it to a worker, the task is lost
                // However, this is the most performant way to avoid jobs being assigned twice when running multiple supervisors
                // An alternative could be to pop it and immediately set it to some temporary Redis key (requires an extra Redis call)
                $this->processingSerializedTask = Redis::command('lpop', [$queue]);

                // If no task was found, continue to next queue
                if (! $this->processingSerializedTask) {
                    continue;
                }

                $taskFound = true;

                $workerId = $this->findIdleWorkerId();

                $this->info("Running task on $workerId");

                // Mark the worker as "working" by setting the active task
                // Note: This can be any value (except `none`), the task value is not used at the moment
                Redis::command('set', ["task-worker:$workerId", $this->processingSerializedTask]);

                // Send the task to the worker
                Redis::publish("task-worker-$workerId", $this->processingSerializedTask);

                $this->processingSerializedTask = null;
                $this->processingQueue = null;

                // If the queue is empty
                if (Redis::command('llen', [$queue]) == 0) {
                    // Remove the queue from the list
                    Redis::command('srem', [Task::REDIS_KEY_QUEUES, $queue]);

                    // Remove the queue itself
                    Redis::command('del', [$queue]);
                }
            }
        }

        // If no task was found (all queues are empty), we will wait before trying again
        if (! $taskFound) {
            // Wait to avoid high cpu usage
            sleep($this->argument('wait'));
        }

        $this->waitForTasks();
    }

    protected function findIdleWorkerId(): string
    {
        $this->handleExit();

        $workerId = $this->workers
            ->keys()
            ->first(fn ($workerId) => Redis::command('get', ["task-worker:$workerId"]) === 'none');

        if ($workerId) {
            // In case the worker has already processed the max number of tasks, we will stop it and create a new one
            if (Redis::command('get', ["task-worker:$workerId:processed-tasks"]) >= $this->argument('maxTasks')) {
                $this->info("Stopping worker $workerId");

                $this->stopWorker($workerId);

                $this->createWorker();

                return $this->findIdleWorkerId();
            }

            return $workerId;
        }

        // Wait to avoid high cpu usage
        sleep($this->argument('wait'));

        return $this->findIdleWorkerId();
    }

    protected function setExitHandlers(): void
    {
        $this->trap([SIGTERM, SIGQUIT, SIGABRT, SIGINT], function (int $signal) {
            $this->shouldExit = true;
        });
    }

    protected function handleExit(): void
    {
        if ($this->shouldExit) {
            if ($this->processingQueue && $this->processingSerializedTask) {
                // If we were processing a task, push it back to the queue
                Redis::command('lpush', [$this->processingQueue, $this->processingSerializedTask]);
            }

            $this->stopWorkers();

            exit();
        }
    }

    protected function createWorker(): void
    {
        $id = Str::uuid()->toString();

        $this->info("Starting worker $id");

        $worker = Process::forever()->start("php artisan task:worker $id");

        $this->workers->put($id, $worker);

        Redis::command('sadd', ["task-supervisors:$this->id:workers", $id]);
    }

    protected function waitForWorkers($retries = 3): bool
    {
        $workersAreReady = true;

        foreach ($this->workers as $id => $worker) {
            if (! Redis::command('get', ["task-worker:$id"])) {
                $workersAreReady = false;
            }
        }

        if (! $workersAreReady) {
            if ($retries <= 0) {
                return false;
            }

            sleep(1);

            return $this->waitForWorkers($retries + 1);
        }

        return true;
    }

    protected function stopWorkers(): void
    {
        $this->info('Stopping workers...');

        foreach ($this->workers->keys() as $workerId) {
            $this->stopWorker($workerId);
        }

        Redis::command('del', ["task-supervisors:$this->id:workers"]);

        Redis::command('srem', ['task-supervisors', $this->id]);
    }

    protected function stopWorker($id): void
    {
        $worker = $this->workers->get($id);

        if (! $worker) {
            return;
        }

        // TODO Wait for worker to stop (wait for task to be executed)
        $worker->stop();

        $this->workers->forget($id);

        Redis::command('srem', ["task-supervisors:$this->id:workers", $id]);

        Redis::command('del', ["task-worker:$id"]);

        Redis::command('del', ["task-worker:$id:processed-tasks"]);
    }
}
