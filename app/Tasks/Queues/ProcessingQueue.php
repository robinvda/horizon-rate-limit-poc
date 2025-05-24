<?php

namespace App\Tasks\Queues;

class ProcessingQueue extends TaskQueue
{
    public static int $limit = 1;
    public static int $window = 1;
}
