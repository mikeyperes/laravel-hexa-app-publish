<?php

namespace hexa_app_publish\Publishing\Accounts\Services;

use hexa_core\Models\User;
use hexa_package_whm\Models\HostingAccount;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;

/**
 * Builds the full publishing-profile data bundle for a given user.
 * Used by both the legacy /publish/users/{id} controller and the core
 * profile-sections injection on /settings/users/{id}.
 */
class UserProfileDataService
{
    /**
     * Return the full publishing profile payload for $user.
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $attachedAccounts = HostingAccount::with('whmServer')
            ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
            ->get()
            ->map(function ($acct) {
                $acct->is_reseller = $acct->isReseller();
                $acct->child_count = $acct->is_reseller ? $acct->getChildAccountCount() : 0;
                return $acct;
            });

        $attachedIds = $attachedAccounts->pluck('id')->toArray();

        $availableAccounts = HostingAccount::with('whmServer')
            ->whereNotIn('id', $attachedIds)
            ->where('status', 'active')
            ->orderBy('domain')
            ->get()
            ->map(function ($acct) {
                $acct->is_reseller = $acct->isReseller();
                $acct->child_count = $acct->is_reseller ? $acct->getChildAccountCount() : 0;
                return $acct;
            });

        $sites = PublishSite::where('user_id', $user->id)->get();
        $campaigns = PublishCampaign::where('user_id', $user->id)->with('site')->get();
        $templates = PublishTemplate::where('user_id', $user->id)->get();
        $presets = PublishPreset::where('user_id', $user->id)->get();
        $drafts = PublishArticle::where('user_id', $user->id)->where('status', 'draft')->orderByDesc('updated_at')->limit(20)->get();
        $bookmarks = PublishBookmark::where('user_id', $user->id)->orderByDesc('created_at')->limit(20)->get();

        $articleStats = [
            'total' => PublishArticle::where('user_id', $user->id)->count(),
            'published' => PublishArticle::where('user_id', $user->id)->where('status', 'published')->count(),
            'completed' => PublishArticle::where('user_id', $user->id)->where('status', 'completed')->count(),
            'review' => PublishArticle::where('user_id', $user->id)->where('status', 'review')->count(),
            'drafting' => PublishArticle::where('user_id', $user->id)->whereIn('status', ['sourcing', 'drafting', 'spinning'])->count(),
        ];

        $defaultPreset = $presets->where('is_default', true)->first();
        $defaultSiteId = $defaultPreset ? $defaultPreset->default_site_id : null;

        $attachedAccountsJson = $attachedAccounts->map(fn($a) => [
            'id' => $a->id, 'domain' => $a->domain, 'username' => $a->username, 'hostname' => $a->whmServer->hostname ?? '',
        ])->values();

        $availableAccountsJson = $availableAccounts->map(fn($a) => [
            'id' => $a->id, 'domain' => $a->domain, 'username' => $a->username, 'hostname' => $a->whmServer->hostname ?? '',
        ])->values();

        $sitesJson = $sites->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'url' => $s->url,
            'default_author' => $s->default_author,
            'status' => $s->status,
            'connection_type' => $s->connection_type,
            'wp_username' => $s->wp_username,
            'last_connected_at' => $s->last_connected_at?->toISOString(),
        ])->values();

        return compact(
            'attachedAccounts', 'availableAccounts', 'sites', 'campaigns',
            'templates', 'presets', 'drafts', 'bookmarks', 'articleStats',
            'defaultSiteId', 'attachedAccountsJson', 'availableAccountsJson', 'sitesJson'
        );
    }
}
