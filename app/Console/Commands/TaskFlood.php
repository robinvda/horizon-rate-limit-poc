<?php

namespace App\Console\Commands;

use App\Tasks\ProcessTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class TaskFlood extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:flood {uniqueTasks=10} {duplicates=10}';

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
        $uniqueTasks = $this->argument('uniqueTasks');
        $duplicates = $this->argument('duplicates');

        $this->info("Dispatching $uniqueTasks unique tasks $duplicates times = " . ($uniqueTasks * $duplicates) . " total");

        for ($i = 0; $i < $duplicates; $i++) {
            for ($j = 0; $j < $uniqueTasks; $j++) {
                ProcessTask::dispatch($j);
            }
        }
    }
}
