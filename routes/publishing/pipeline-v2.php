<?php

use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineV2Controller;
use Illuminate\Http\Request;

Route::get("/publish2", [PipelineV2Controller::class, "index"])->name("publish.pipeline.v2");

Route::get("/article/publish", function (Request $request) {
    return redirect()->route("publish.pipeline.v2", $request->query());
})->name("publish.pipeline");
