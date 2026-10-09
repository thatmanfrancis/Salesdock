<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tax-filings:generate')->monthlyOn(1, '0:00')->timezone('Africa/Lagos');
Schedule::command('subscriptions:expire')->dailyAt('00:05')->timezone('Africa/Lagos');
Schedule::command('subscriptions:downgrade')->dailyAt('00:10')->timezone('Africa/Lagos');
Schedule::command('subscriptions:end-trials')->dailyAt('08:00')->timezone('Africa/Lagos');
Schedule::command('tenants:purge')->dailyAt('02:00')->timezone('Africa/Lagos');
Schedule::command('registrations:prune')->dailyAt('02:30')->timezone('Africa/Lagos');
