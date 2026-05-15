<?php

namespace hexa_app_publish\Publishing\Sites\Services;

use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_wordpress\Services\WordPressManagerService;
use Illuminate\Support\Str;

class SiteAuthorResolutionService
{
    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    private array $authorCache = [];

    public function __construct(
        private WordPressManagerService $wordpress,
        private PublishSiteWordPressTargetFactory $targets,
    ) {
    }

    public function resolvePreferredAuthor(?PublishSite $site, ?string $preferredLogin): ?string
    {
        $preferredLogin = trim((string) $preferredLogin);
        if (!$site) {
            return $preferredLogin !== "" ? $preferredLogin : null;
        }

        $defaultLogin = trim((string) ($site->default_author ?? ""));
        $wpUsername = trim((string) ($site->wp_username ?? ""));
        $authors = $this->fetchPublishAuthors($site);

        if ($preferredLogin !== "" && $this->findAuthor($authors, $preferredLogin)) {
            return $preferredLogin;
        }

        if ($defaultLogin !== "" && ($authors === [] || $this->findAuthor($authors, $defaultLogin))) {
            return $defaultLogin;
        }

        if ($wpUsername !== "" && $this->findAuthor($authors, $wpUsername)) {
            return $wpUsername;
        }

        return $authors === []
            ? ($defaultLogin !== "" ? $defaultLogin : null)
            : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPublishAuthors(?PublishSite $site): array
    {
        if (!$site) {
            return [];
        }

        $cacheKey = (int) $site->id;
        if (array_key_exists($cacheKey, $this->authorCache)) {
            return $this->authorCache[$cacheKey];
        }

        try {
            $result = $this->wordpress->listAuthors($this->targets->fromSite($site));
            $authors = array_values(array_filter(
                (array) ($result["authors"] ?? []),
                fn ($author) => is_array($author) && filled($author["user_login"] ?? null)
            ));

            return $this->authorCache[$cacheKey] = $authors;
        } catch (\Throwable $e) {
            return $this->authorCache[$cacheKey] = [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $authors
     * @return array<string, mixed>|null
     */
    public function findAuthor(array $authors, string $needle): ?array
    {
        $needle = Str::lower(trim($needle));
        if ($needle === "") {
            return null;
        }

        foreach ($authors as $author) {
            $values = array_filter([
                $author["user_login"] ?? null,
                $author["display_name"] ?? null,
                $author["email"] ?? null,
            ]);

            foreach ($values as $value) {
                if (Str::lower(trim((string) $value)) === $needle) {
                    return $author;
                }
            }
        }

        return null;
    }
}
