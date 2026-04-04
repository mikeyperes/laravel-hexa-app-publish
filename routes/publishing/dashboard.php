<?php

use hexa_app_publish\Publishing\Dashboard\Http\Controllers\DashboardController;

Route::get('/publish/dashboard', [DashboardController::class, 'index'])->name('publish.dashboard');
