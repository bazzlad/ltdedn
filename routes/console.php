<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reports:send-monthly')->monthlyOn(1, '09:00');
Schedule::command('transfers:expire')->hourly();
