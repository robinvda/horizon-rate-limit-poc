<?php

namespace App\Console\Commands;

use App\Tasks\Queues\TaskQueue;
use App\Tasks\Task;
use Illuminate\Console\Command;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TaskSupervisor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:supervisor {processes=5} {wait=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a task supervisor';

    /**
     * @var Collection<string, InvokedProcess>
     */
    protected Collection $processes;

    protected string $id;

    protected bool $shouldExit = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->id = Str::uuid()->toString();

        $this->setExitHandlers();

        Redis::command('sadd', ['task-supervisors', $this->id]);

        $this->processes = collect();

        for ($i = 0; $i < $this->argument('processes'); $i++) {
            $id = Str::uuid()->toString();

            $this->info("Starting process ($id)");

            $process = Process::forever()->start("php artisan task:worker $id");

            $this->processes->put($id, $process);

            Redis::command('rpush', ["task-supervisors:$this->id:workers", $id]);
        }

        if (! $this->waitForWorkers()) {
            $this->error('Some workers were unable to start');

            $this->stopWorkers();

            return static::FAILURE;
        }

        $this->info('Workers started, waiting for tasks...');

        $this->waitForTasks();
    }

    protected function waitForTasks(): void
    {
        $this->handleExit();

        $taskFound = false;

        foreach (Redis::command('smembers', [Task::REDIS_KEY_QUEUES]) as $queue) {
            $this->handleExit();

            list($queueClass, $subQueue) = explode(':', $queue);

            /** @var class-string<TaskQueue> $queueClass */
            if ($queueClass::attempt($queue)) {
                // If the supervisor stops (without signals) between popping the task and assigning it to a worker, the task is lost
                // However, this is the most performant way to avoid jobs being assigned twice when running multiple supervisors
                // An alternative could be to pop it and immediately set it to some temporary Redis key (requires an extra Redis call)
                $task = Redis::command('lpop', [$queue]);

                // If no task was found, continue to next queue
                if (! $task) {
                    continue;
                }

                $taskFound = true;

                $processId = $this->findIdleProcessId();

                $this->info("Running task on $processId");

                // Mark the worker as "working" by setting the active task
                // Note: This can be any value (except `none`), the task value is not used at the moment
                Redis::command('set', ["task-worker:$processId", $task]);

                // Send the task to the worker
                Redis::publish("task-worker-$processId", $task);

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
            $this->info('No tasks found');

            // Wait to avoid high cpu usage
            sleep($this->argument('wait'));
        }

        $this->waitForTasks();
    }

    protected function findIdleProcessId(): string
    {
        $this->handleExit();

        $processId = $this->processes
            ->keys()
            ->first(fn ($processId) => Redis::command('get', ["task-worker:$processId"]) === 'none');

        if ($processId) {
            return $processId;
        }

        // Wait to avoid high cpu usage
        sleep($this->argument('wait'));

        return $this->findIdleProcessId();
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
            $this->stopWorkers();

            exit();
        }
    }

    protected function waitForWorkers($retries = 3): bool
    {
        $processesAreReady = true;

        foreach ($this->processes as $id => $process) {
            if (! Redis::command('get', ["task-worker:$id"])) {
                $processesAreReady = false;
            }
        }

        if (! $processesAreReady) {
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

        foreach ($this->processes ?? [] as $processId => $process) {
            // TODO Wait for process to stop (wait for task to be executed)
            $process->stop();

            Redis::command('del', ["task-worker:$processId"]);

            Redis::command('del', ["task-supervisors:$this->id:workers"]);

            Redis::command('srem', ['task-supervisors', $this->id]);
        }
    }
}
