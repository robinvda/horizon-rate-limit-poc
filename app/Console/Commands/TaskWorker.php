<?php

namespace App\Console\Commands;

use App\Tasks\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class TaskWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:worker {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a task worker';

    protected string $id;

    protected ?Task $processingTask = null;

    protected bool $shouldExit = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->id = $this->argument('id');

        $this->setExitHandler();

        $this->setWaitingState();

        // We're using a different connection otherwise we won't be able to read/write because of the active subscription
        Redis::connection('subscriber')
            ->subscribe("task-worker-$this->id", function ($serializedTask) {
                try {
                    $this->processingTask = unserialize($serializedTask);

                    $this->processingTask->handle();
                } catch (Throwable $exception) {
                    // TODO Possibly retry if a task fails

                    report($exception);
                } finally {
                    $this->processingTask = null;
                }

                $this->incrementTaskProcessed();

                $this->setWaitingState();

                $this->handleExit();
            });
    }

    protected function setWaitingState(): void
    {
        Redis::command('set', ["task-worker:$this->id", 'none']);
    }

    protected function incrementTaskProcessed(): void
    {
        Redis::command('incr', ["task-worker:$this->id:processed-tasks"]);
    }

    protected function setExitHandler(): void
    {
        $this->trap([SIGTERM, SIGQUIT, SIGABRT, SIGINT], function (int $signal) {
            $this->shouldExit = true;

            // If we're not processing a task, we will exit immediately
            // Otherwise we'll exit after the task has been processed
            if (! $this->processingTask) {
                $this->handleExit();
            }
        });
    }

    protected function handleExit(): void
    {
        if ($this->shouldExit) {
            exit();
        }
    }
}
