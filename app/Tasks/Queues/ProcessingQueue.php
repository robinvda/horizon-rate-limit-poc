<?php

namespace App\Tasks\Queues;

class ProcessingQueue extends TaskQueue
{
    // This queue allows 10 tasks every second
    public static int $limit = 10;
    public static int $window = 1;
}
