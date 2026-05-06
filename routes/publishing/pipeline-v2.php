<?php

use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineV2Controller;
use Illuminate\Http\Request;

Route::get('/article/publish', [PipelineV2Controller::class, 'index'])->name('publish.pipeline');

Route::get('/publish2', function (Request $request) {
    return redirect()->route('publish.pipeline', $request->query());
})->name('publish.pipeline.v2');
