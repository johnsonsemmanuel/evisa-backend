<?php

use App\Jobs\CheckSlaBreaches;
use App\Jobs\ExpireEtas;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CheckSlaBreaches)->everyFifteenMinutes();
Schedule::job(new ExpireEtas)->daily();
Schedule::job(new \App\Jobs\CleanupExpiredBacs)->daily();
