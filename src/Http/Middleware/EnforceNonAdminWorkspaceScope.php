<?php

namespace hexa_app_publish\Http\Middleware;

use Closure;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceNonAdminWorkspaceScope
{
    public function __construct(private PublishAccessService $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $this->access->isAdmin($user)) {
            return $next($request);
        }

        if (!$this->access->isRestrictedWorkspaceUser($user)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (!$routeName || $this->access->canAccessRestrictedRoute($routeName)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
