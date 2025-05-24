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
        $this->id = Str::random();

        $this->setExitHandlers();

        Redis::command('sadd', ['task-supervisors', $this->id]);

        $this->processes = collect();

        for ($i = 0; $i < $this->argument('processes'); $i++) {
            $id = Str::random();

            $this->info("Starting process ($id)");

            $process = Process::forever()->start("php artisan task:worker $id");

            $this->processes->put($id, $process);

            Redis::command('rpush', ["task-supervisors:$this->id:workers", $id]);
        }

        // Wait for processes to start
        sleep(3);

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
                // TODO We're popping the task before sending it to a worker which means it is lost if the supervisor stops/crashes in that time
                // TODO This risk can be limited by finding a process before popping the task
                // TODO Or by first getting the task and popping it after it was sent to a worker (increases load on Redis)
                $task = Redis::command('lpop', [$queue]);

                if (! $task) {
                    continue;
                }

                $taskFound = true;

                $processId = $this->findIdleProcessId();

                $this->info("Running task on $processId");

                // Mark the worker as "working" by setting the active task
                // Note: This can be any value, the task value is not used at the moment
                Redis::command('set', ["task-worker:$processId", $task]);

                // TODO Since we set a redis key for the worker with the active task, we may not need the pubsub pattern
                Redis::publish("task-worker-$processId", $task);

                if (Redis::command('llen', [$queue]) == 0) {
                    // If the queue is empty, remove it from the list of queues
                    Redis::command('srem', [Task::REDIS_KEY_QUEUES, $queue]);

                    // Also remove the queue itself
                    Redis::command('del', [$queue]);
                }
            }

            // If a task was found, we will immediately try to find another
            if ($taskFound) {
                $this->waitForTasks();
            }
        }

        $this->info('No tasks found');

        // Wait to avoid high cpu usage
        sleep($this->argument('wait'));

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
        usleep(100);

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

    protected function stopWorkers(): void
    {
        $this->info('Stopping workers...');

        foreach ($this->processes ?? [] as $processId => $process) {
            Redis::command('del', ["task-worker:$processId"]);

            Redis::command('del', ["task-supervisors:$processId:workers"]);

            Redis::command('srem', ['task-supervisors', $this->id]);

            $process->stop();
        }
    }
}
