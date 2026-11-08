<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 読書計画の期限切れ判定・リマインダー通知（日次バッチ）
// 毎日20:00（日本時間）に実行
app(\Illuminate\Console\Scheduling\Schedule::class)
    ->command('reading-plans:process')
    ->dailyAt('20:00')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping();