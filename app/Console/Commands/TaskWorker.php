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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->id = $this->argument('id');

        $this->setWaitingState();

        // We're using a different connection otherwise we won't be able to read/write because of the active subscription
        Redis::connection('subscriber')
            ->subscribe("task-worker-$this->id", function ($serializedTask) {
                try {
                    $task = unserialize($serializedTask);

                    /** @var Task $task */
                    $task->handle();
                } catch (Throwable $exception) {
                    // TODO Possibly retry if a task fails

                    report($exception);
                }

                $this->setWaitingState();
            });
    }

    protected function setWaitingState(): void
    {
        Redis::command('set', ["task-worker:$this->id", 'none']);
    }
}
