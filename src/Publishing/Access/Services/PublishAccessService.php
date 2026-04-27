<?php

namespace hexa_app_publish\Publishing\Access\Services;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
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

    public function canAccessSite(User $user, int $siteId): bool
    {
        return $this->siteQuery($user)->whereKey($siteId)->exists();
    }
}
