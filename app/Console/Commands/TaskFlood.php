<?php

namespace App\Console\Commands;

use App\Tasks\ProcessTask;
use Illuminate\Console\Command;

class TaskFlood extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:flood';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add many tasks to the queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        collect(range(1, 10))->each(fn ($value) => ProcessTask::dispatch($value));
    }
}
