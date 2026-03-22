<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishAccountController — now loads Users instead of PublishAccounts.
 * Same views, same functionality, different data source.
 */
class PublishAccountController extends Controller
{
    /**
     * List all users (replaces publish accounts listing).
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->get()->map(function ($user) {
            $user->sites_count = PublishSite::where('user_id', $user->id)->count();
            $user->campaigns_count = PublishCampaign::where('user_id', $user->id)->count();
            $user->articles_count = PublishArticle::where('user_id', $user->id)->count();
            return $user;
        });

        return view('app-publish::accounts.index', [
            'users' => $users,
        ]);
    }

    /**
     * Show a single user with their publishing data.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $user = User::findOrFail($id);

        $sites = PublishSite::where('user_id', $user->id)->get();
        $campaigns = PublishCampaign::where('user_id', $user->id)->with('site')->get();
        $templates = PublishTemplate::where('user_id', $user->id)->get();

        $articleStats = [
            'total' => PublishArticle::where('user_id', $user->id)->count(),
            'published' => PublishArticle::where('user_id', $user->id)->where('status', 'published')->count(),
            'completed' => PublishArticle::where('user_id', $user->id)->where('status', 'completed')->count(),
            'review' => PublishArticle::where('user_id', $user->id)->where('status', 'review')->count(),
            'drafting' => PublishArticle::where('user_id', $user->id)->whereIn('status', ['sourcing', 'drafting', 'spinning'])->count(),
        ];

        return view('app-publish::accounts.show', [
            'user' => $user,
            'sites' => $sites,
            'campaigns' => $campaigns,
            'templates' => $templates,
            'articleStats' => $articleStats,
        ]);
    }

    /**
     * Show edit form for a user's publishing settings.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $user = User::findOrFail($id);

        $sites = PublishSite::where('user_id', $user->id)->get();
        $campaigns = PublishCampaign::where('user_id', $user->id)->get();

        return view('app-publish::accounts.edit', [
            'user' => $user,
            'sites' => $sites,
            'campaigns' => $campaigns,
        ]);
    }
}
