<?php

use hexa_app_publish\Publishing\Schedule\Http\Controllers\ScheduleController;

Route::get('/schedule', [ScheduleController::class, 'index'])->name('publish.schedule.index');
Route::post('/schedule/fetch', [ScheduleController::class, 'fetchScheduled'])->name('publish.schedule.fetch');
