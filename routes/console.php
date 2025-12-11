<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule performance review notifications check to run daily at 8 AM
Schedule::command('performance:check-notifications')->dailyAt('08:00');

// Schedule cleanup of expired temporary availability requests to run daily at midnight
Schedule::command('availability:cleanup-expired')->daily();
