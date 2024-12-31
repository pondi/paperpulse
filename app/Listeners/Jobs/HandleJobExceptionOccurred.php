<?php

namespace App\Listeners\Jobs;

use App\Events\Jobs\JobExceptionOccurred;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleJobExceptionOccurred
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
    public function handle(JobExceptionOccurred $event): void
    {
        //
    }
}
