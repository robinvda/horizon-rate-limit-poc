<?php

namespace App\Tasks;

class ProcessTask extends Task
{
    public function __construct(public int $value)
    {
    }

    public function queue(): string
    {
        return 'john-doe-queue';
    }

    public function handle()
    {
        usleep(100);
    }
}
