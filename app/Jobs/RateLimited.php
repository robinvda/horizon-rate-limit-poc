<?php

namespace App\Jobs;

interface RateLimited
{
    public function rateLimit();
}
