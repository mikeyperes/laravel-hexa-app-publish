<?php

use hexa_app_publish\Http\Controllers\PublishDashboardController;

Route::get('/publish/dashboard', [PublishDashboardController::class, 'index'])->name('publish.dashboard');
