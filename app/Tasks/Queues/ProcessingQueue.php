<?php

namespace App\Tasks\Queues;

class ProcessingQueue extends TaskQueue
{
    // This queue allows 1 task each second
    public static int $limit = 1;
    public static int $window = 1;
}
