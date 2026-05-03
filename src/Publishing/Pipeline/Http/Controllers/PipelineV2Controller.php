<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PipelineV2Controller extends Controller
{
    public function index(Request $request)
    {
        $response = app(PipelineController::class)->index($request);

        if ($response instanceof RedirectResponse) {
            $url = $response->getTargetUrl();
            if (str_contains($url, '/article/publish')) {
                $url = str_replace('/article/publish', '/publish2', $url);
                return redirect($url);
            }
            return $response;
        }

        if ($response instanceof View) {
            return view('app-publish::publishing.pipeline-v2.index', $response->getData());
        }

        return $response;
    }
}
