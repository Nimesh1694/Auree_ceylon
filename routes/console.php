<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('commissions:process-eligible')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('inventory:send-low-stock-digest')
    ->dailyAt('08:00')
    ->timezone('Asia/Colombo')
    ->withoutOverlapping();

Schedule::command('aura:prune-recently-viewed')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('back-in-stock:process-alerts')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('back-in-stock:recover-stale-leases')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('agents:process-announcement-lifecycle')
    ->everyMinute()
    ->timezone('Asia/Colombo')
    ->withoutOverlapping(10);

Schedule::command('agent:prune-expired-achievement-requests')
    ->dailyAt('00:05')
    ->timezone('Asia/Colombo')
    ->withoutOverlapping();




