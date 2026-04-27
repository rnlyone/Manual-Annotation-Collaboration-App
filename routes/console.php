<?php

use App\Jobs\PollPhase2Batch;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll OpenAI batch status every 5 minutes
Schedule::job(new PollPhase2Batch)->everyFiveMinutes();
