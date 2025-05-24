<?php

namespace App\Console\Commands;

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
    protected $signature = 'task:supervisor {processes=5}';

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

    protected bool $shouldExit = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->setExitHandlers();

        $this->processes = collect();

        for ($i = 0; $i < $this->argument('processes'); $i++) {
            $id = Str::random();

            $this->info("Starting process ($id)");

            $process = Process::forever()->start("php artisan task:worker $id");

            $this->processes->put($id, $process);
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

            // todo test rate limit
            if (true) {
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

                Redis::command('set', ["task-worker:$processId", $task]);

                // TODO Since we now set a redis key for the worker with the active task, we may not need the pubsub pattern
                Redis::publish("task-worker-$processId", $task);

                if (Redis::command('llen', [$queue]) == 0) {
                    Redis::command('srem', [Task::REDIS_KEY_QUEUES, $queue]);
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
        sleep(3);

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

            $process->stop();
        }
    }
}
