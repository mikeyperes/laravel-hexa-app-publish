<?php

namespace hexa_app_publish\Publishing\Dashboard\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the publishing dashboard overview.
     *
     * @return View
     */
    public function index(): View
    {
        $stats = [
            'total_accounts' => PublishAccount::where('status', 'active')->count(),
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

        $activeCampaigns = PublishCampaign::with(['site', 'account'])
            ->where('status', 'active')
            ->orderBy('next_run_at')
            ->limit(10)
            ->get();

        return view('app-publish::publishing.dashboard.index', [
            'stats' => $stats,
            'recentArticles' => $recentArticles,
            'activeCampaigns' => $activeCampaigns,
        ]);
    }
}
