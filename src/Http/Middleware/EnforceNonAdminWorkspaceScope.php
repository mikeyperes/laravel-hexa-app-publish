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

        $routeName = $request->route()?->getName();

        if (!$routeName || $this->isAllowed($routeName)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this page.');
    }

    private function isAllowed(string $routeName): bool
    {
        $patterns = [
            'dashboard',
            'profile.*',
            'settings.security',
            'settings.two-factor*',
            'publish.sites.index',
            'publish.sites.show',
            'logout',
        ];

        foreach ($patterns as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }

            if (str_ends_with($pattern, '.*') && str_starts_with($routeName, substr($pattern, 0, -1))) {
                return true;
            }

            if (str_ends_with($pattern, '*') && str_starts_with($routeName, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
