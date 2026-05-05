<?php

namespace hexa_app_publish\Publishing\Access\Services;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_core\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PublishAccessService
{
    public function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->permission_group_id) {
            $user->loadMissing('permissionGroup');
            return (bool) ($user->permissionGroup?->is_admin ?? false);
        }

        if ($user->role_id) {
            $user->loadMissing('roleRelation');
            return (bool) ($user->roleRelation?->is_default_admin ?? false);
        }

        return ($user->role ?? null) === 'admin';
    }

    public function isRestrictedWorkspaceUser(?User $user): bool
    {
        if (!$user || $this->isAdmin($user)) {
            return false;
        }

        if ($user->role_id) {
            $user->loadMissing('roleRelation');
        }

        $roleSlug = (string) ($user->roleRelation?->slug ?? $user->role ?? '');
        if (!in_array($roleSlug, ['user', 'customer'], true)) {
            return false;
        }

        if ($this->accessibleAccountIds($user) !== []) {
            return true;
        }

        return PublishSite::query()->where('user_id', $user->id)->exists();
    }

    public function restrictedRoutePatterns(): array
    {
        return [
            'dashboard',
            'logout',
            'profile.*',
            'publish.pipeline',
            'publish.pipeline.*',
            'publish.users.search',
            'publish.profiles.*',
            'publish.photos.search',
            'publish.drafts.*',
            'publish.templates.*',
        ];
    }

    public function canAccessRestrictedRoute(string $routeName): bool
    {
        foreach ($this->restrictedRoutePatterns() as $pattern) {
            if ($this->routeMatchesPattern($routeName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function routeAccessOverride(?User $user, string $routeName): ?bool
    {
        if (!$this->isRestrictedWorkspaceUser($user)) {
            return null;
        }

        return $this->canAccessRestrictedRoute($routeName);
    }

    public function accessibleAccountIds(User $user): array
    {
        if ($this->isAdmin($user)) {
            return PublishAccount::query()->pluck('id')->all();
        }

        $ids = PublishAccountUser::query()
            ->where('user_id', $user->id)
            ->pluck('publish_account_id')
            ->all();

        $ownerIds = PublishAccount::query()
            ->where('owner_user_id', $user->id)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($ids, $ownerIds)));
    }

    public function siteQuery(User $user): Builder
    {
        $query = PublishSite::query();

        if ($this->isAdmin($user)) {
            return $query;
        }

        $accountIds = $this->accessibleAccountIds($user);

        return $query->where(function (Builder $builder) use ($user, $accountIds) {
            $builder->where('user_id', $user->id);

            if ($accountIds !== []) {
                $builder->orWhereIn('publish_account_id', $accountIds);
            }
        });
    }

    public function resolveSiteOrFail(User $user, int $siteId): PublishSite
    {
        return $this->siteQuery($user)->findOrFail($siteId);
    }

    public function accountQuery(User $user): Builder
    {
        $query = PublishAccount::query();

        if ($this->isAdmin($user)) {
            return $query;
        }

        $accountIds = $this->accessibleAccountIds($user);

        if ($accountIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $accountIds);
    }

    public function articleQuery(User $user): Builder
    {
        $query = PublishArticle::query();

        if ($this->isAdmin($user)) {
            return $query;
        }

        $accountIds = $this->accessibleAccountIds($user);
        $siteIds = $this->siteQuery($user)->pluck('id')->all();

        return $query->where(function (Builder $builder) use ($user, $accountIds, $siteIds) {
            $builder->where('created_by', $user->id)
                ->orWhere('user_id', $user->id);

            if ($accountIds !== []) {
                $builder->orWhereIn('publish_account_id', $accountIds);
            }

            if ($siteIds !== []) {
                $builder->orWhereIn('publish_site_id', $siteIds);
            }
        });
    }

    public function presetQuery(User $user): Builder
    {
        $query = PublishPreset::query();

        if ($this->isAdmin($user)) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function templateQuery(User $user): Builder
    {
        $query = PublishTemplate::query();

        if ($this->isAdmin($user)) {
            return $query;
        }

        $accountIds = $this->accessibleAccountIds($user);

        return $query->where(function (Builder $builder) use ($user, $accountIds) {
            $builder->where('user_id', $user->id);

            if ($accountIds !== []) {
                $builder->orWhereIn('publish_account_id', $accountIds);
            }
        });
    }

    public function resolveArticleOrFail(User $user, int $articleId): PublishArticle
    {
        return $this->articleQuery($user)->findOrFail($articleId);
    }

    public function resolvePresetOrFail(User $user, int $presetId): PublishPreset
    {
        return $this->presetQuery($user)->findOrFail($presetId);
    }

    public function resolveTemplateOrFail(User $user, int $templateId): PublishTemplate
    {
        return $this->templateQuery($user)->findOrFail($templateId);
    }

    public function canAccessSite(User $user, int $siteId): bool
    {
        return $this->siteQuery($user)->whereKey($siteId)->exists();
    }

    private function routeMatchesPattern(string $routeName, string $pattern): bool
    {
        if ($pattern === $routeName) {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            return str_starts_with($routeName, substr($pattern, 0, -1));
        }

        if (str_ends_with($pattern, '*')) {
            return str_starts_with($routeName, substr($pattern, 0, -1));
        }

        return false;
    }
}
