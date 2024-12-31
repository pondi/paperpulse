<?php

namespace App\Listeners\Jobs;

use App\Events\Jobs\JobFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleJobFailed
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
    public function handle(JobFailed $event): void
    {
        //
    }
}
