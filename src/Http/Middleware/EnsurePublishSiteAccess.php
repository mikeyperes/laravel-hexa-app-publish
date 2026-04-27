<?php

namespace hexa_app_publish\Http\Middleware;

use Closure;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublishSiteAccess
{
    public function __construct(private PublishAccessService $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $siteId = (int) $request->route('id');

        if (!$user || !$this->access->canAccessSite($user, $siteId)) {
            abort(403, 'You do not have permission to access this site.');
        }

        return $next($request);
    }
}
