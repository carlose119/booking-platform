<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('booking:clean-holds')->everyMinute();
Schedule::command('booking:auto-refund')->hourly();
Schedule::command('booking:send-reminders')->dailyAt('08:00');
