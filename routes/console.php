<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reports:send-monthly')->monthlyOn(1, '09:00');
Schedule::command('transfers:expire')->hourly();
Schedule::command('shop:expire-reservations')->everyFiveMinutes();
Schedule::command('shop:reconcile-pending-orders')->everyTenMinutes();
