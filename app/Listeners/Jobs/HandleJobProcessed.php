<?php

namespace App\Listeners\Jobs;

use App\Events\Jobs\JobProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleJobProcessed
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JobProcessed $event): void
    {
        //
    }
}
