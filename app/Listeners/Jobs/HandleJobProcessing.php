<?php

namespace App\Listeners\Jobs;

use App\Events\Jobs\JobProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleJobProcessing
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
    public function handle(JobProcessing $event): void
    {
        //
    }
}
