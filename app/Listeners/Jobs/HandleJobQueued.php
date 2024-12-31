<?php

namespace App\Listeners\Jobs;

use App\Events\Jobs\JobQueued;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleJobQueued
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
    public function handle(JobQueued $event): void
    {
        //
    }
}
