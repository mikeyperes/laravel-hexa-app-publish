<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use Illuminate\View\View;

class PublishDashboardController extends Controller
{
    /**
     * Show the publishing dashboard overview.
     *
     * @return View
     */
    public function index(): View
    {
        $stats = [
            'total_users' => User::count(),
            'total_sites' => PublishSite::where('status', 'connected')->count(),
            'active_campaigns' => PublishCampaign::where('status', 'active')->count(),
            'articles_today' => PublishArticle::whereDate('created_at', today())->count(),
            'articles_published' => PublishArticle::where('status', 'published')->count(),
            'articles_in_review' => PublishArticle::where('status', 'review')->count(),
            'articles_drafting' => PublishArticle::whereIn('status', ['sourcing', 'drafting', 'spinning'])->count(),
        ];

        $recentArticles = PublishArticle::with(['site', 'campaign', 'creator'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $activeCampaigns = PublishCampaign::with(['site', 'user'])
            ->where('status', 'active')
            ->orderBy('next_run_at')
            ->limit(10)
            ->get();

        return view('app-publish::dashboard.index', [
            'stats' => $stats,
            'recentArticles' => $recentArticles,
            'activeCampaigns' => $activeCampaigns,
        ]);
    }
}
