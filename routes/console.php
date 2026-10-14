<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('clicks:purge')->daily();
Schedule::command('geolite:update')->weeklyOn(1, '02:00');
Schedule::command('queue:health')->everyFifteenMinutes();
Schedule::command('backup:snapshot')->dailyAt('03:00');
Schedule::job(new QueueHeartbeat)->everyTwoMinutes();
// Abuse/spam sweep: new & edited items hourly (with external reputation lookups),
// full sweep + purge of long-disabled items nightly.
Schedule::command('abuse:scan --since=75m')->hourly()->withoutOverlapping();
Schedule::command('abuse:scan --full --purge')->dailyAt('04:30')->withoutOverlapping();
Schedule::command('links:prune-guest')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('disposable:update')->weeklyOn(1, '03:00');
Schedule::command('abuse:feeds')->dailyAt('03:45')->withoutOverlapping();
