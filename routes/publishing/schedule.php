<?php

use hexa_app_publish\Http\Controllers\PublishScheduleController;

Route::get('/schedule', [PublishScheduleController::class, 'index'])->name('publish.schedule.index');
Route::post('/schedule/fetch', [PublishScheduleController::class, 'fetchScheduled'])->name('publish.schedule.fetch');
