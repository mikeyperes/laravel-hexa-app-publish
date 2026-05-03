<?php

use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineV2Controller;

Route::get('/publish2', [PipelineV2Controller::class, 'index'])->name('publish.pipeline.v2');
