<?php

namespace hexa_app_publish\Providers;

use hexa_app_publish\Console\RunCampaignsCommand;
use hexa_app_publish\Publishing\Pipeline\Services\PublishPipelineApiContext;
use hexa_app_publish\Publishing\Pipeline\Services\PublishPipelineHttpActivityTracker;
use hexa_app_publish\Publishing\Schedule\Feeds\PublishScheduleCalendarFeed;
use hexa_app_publish\Publishing\Uploads\Console\CleanupOrphanUploadsCommand;
use hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm;
use hexa_app_publish\Publishing\Presets\Forms\WordPressPresetForm;
use hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm;
use hexa_app_publish\Support\PublishListCatalog;
use hexa_app_publish\Services\PublishService;
use hexa_package_calendar\Calendar\Registry\Services\CalendarRegistryService;
use hexa_core\CronManager\Services\CronManagerService;
use hexa_core\Forms\Services\FormRegistryService;
use hexa_core\ListRegistry\Services\ListService;
use hexa_core\Services\PackageRegistryService;
use hexa_core\UserRoles\Models\Role;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Accounts\Services\UserProfileDataService;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppPublishServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/app-publish.php', 'hws-publish');

        $this->app->singleton(PublishService::class);
        $this->app->singleton(PublishPipelineApiContext::class);
        $this->app->singleton(PublishPipelineHttpActivityTracker::class);
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/app-publish.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'app-publish');

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->registerSidebarItems();

        $this->registerCalendarSources();

        $this->registerPermissions();

        $this->syncNonAdminSiteRolePatterns();

        $this->registerListCategories();
        $this->registerForms();

        $this->registerTemplateCenterSupport();

        $this->registerCrons();

        $this->registerNonAdminWorkspaceGuard();
        $this->registerPipelineHttpTelemetry();

        // Register publish commands for both CLI and web-triggered Artisan::call() cron runs.
        $this->commands([RunCampaignsCommand::class, CleanupOrphanUploadsCommand::class]);
    }

    private function registerPipelineHttpTelemetry(): void
    {
        Event::listen(RequestSending::class, function (RequestSending $event): void {
            app(PublishPipelineHttpActivityTracker::class)->onRequestSending($event);
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            app(PublishPipelineHttpActivityTracker::class)->onResponseReceived($event);
        });

        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
            app(PublishPipelineHttpActivityTracker::class)->onConnectionFailed($event);
        });
    }

    private function registerCalendarSources(): void
    {
        if (!class_exists(CalendarRegistryService::class)) {
            return;
        }

        app(CalendarRegistryService::class)->register('publish.schedule', [
            'label' => 'Publishing Schedule',
            'title' => 'Schedule - All Scheduled Posts',
            'description' => 'Connected WordPress scheduled posts across publishing sites.',
            'provider' => PublishScheduleCalendarFeed::class,
            'color' => '#3b82f6',
            'empty_state' => 'No scheduled posts found. Click "Fetch Scheduled Posts" to check connected WordPress sites.',
            'sort_order' => 10,
        ]);
    }

    /**
     * Register sidebar links via core's PackageRegistryService.
     */
    private function registerSidebarItems(): void
    {
        if (!config('hexa.app_controls_sidebar', false)) {
            $registry = app(PackageRegistryService::class);
            // HWS-SIDEBAR-MENU-3L-BEGIN
            $registry->registerDomainGroup('Publishing', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 10);
            $registry->registerDomainGroup('Discovery', 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 20);
            $registry->registerSectionGroup('Publish', 'Publishing', '', 10);
            $registry->registerSectionGroup('Campaigns', 'Publishing', '', 10);
            $registry->registerSectionGroup('Content', 'Discovery', '', 20);
            $registry->registerSectionGroup('Prompts', 'Publishing', '', 10);
            $registry->registerSectionGroup('Schedule', 'Publishing', '', 10);
            $registry->registerSectionGroup('Search', 'Discovery', '', 20);
            // HWS-SIDEBAR-MENU-3L-END


            // Search (10)
            $registry->registerSidebarLink('publish.search.images', 'Images', 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'Search', 'app-publish', 10);
            $registry->registerSidebarLink('publish.search.articles', 'Articles', 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z', 'Search', 'app-publish', 11);

            // Article (12)
            $registry->registerSidebarLink('publish.pipeline', 'Publish Article', 'M13 5l7 7-7 7M5 5l7 7-7 7', 'Publish', 'app-publish', 12);
            $registry->registerSidebarLink('publish.editor', 'Editor', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'Publish', 'app-publish', 13);
            $registry->registerSidebarLink('publish.drafts.index', 'Drafts', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'Publish', 'app-publish', 14);
            $registry->registerSidebarLink('publish.bookmarks.index', 'Bookmarked Articles', 'M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z', 'Publish', 'app-publish', 14);
            $registry->registerSidebarLink('publish.templates.index', 'Article Presets', 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z', 'Publish', 'app-publish', 15);
            $registry->registerSidebarLink('prompt-center.index', 'Prompt Center', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'Prompts', 'app-publish', 15);
            $registry->registerSidebarLink('prompt-center.create', 'New Prompt', 'M12 4v16m8-8H4', 'Prompts', 'app-publish', 15);
            $registry->registerSidebarLink('publish.smart-edits.index', 'AI Smart Edit Templates', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'Publish', 'app-publish', 15);

            // Content (16)
            $registry->registerSidebarLink('publish.sites.index', 'Sites', 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9', 'Content', 'app-publish', 17);
            $registry->registerSidebarLink('campaigns.index', 'Campaigns', 'M13 10V3L4 14h7v7l9-11h-7z', 'Campaigns', 'app-publish', 18);
            $registry->registerSidebarLink('campaigns.create', 'Create Campaign', 'M12 4v16m8-8H4', 'Campaigns', 'app-publish', 19);
            $registry->registerSidebarLink('campaigns.presets.index', 'Campaign Presets', 'M4 6h16M4 10h16M4 14h16M4 18h16', 'Campaigns', 'app-publish', 20);
            $registry->registerSidebarLink('publish.articles.index', 'Articles', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'Content', 'app-publish', 19);
            $registry->registerSidebarLink('publish.links.index', 'Links & Sitemaps', 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'Content', 'app-publish', 19);
            $registry->registerSidebarLink('publish.ai-activity.index', 'AI Activity', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'Content', 'app-publish', 19);
            $registry->registerSidebarLink('publish.scrape-activity', 'Scrape Activity', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'Content', 'app-publish', 19);

            // Publish (20 — directly after Content)
            $registry->registerSidebarLink('publish.prompts.index', 'Prompts', 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'Publish', 'app-publish', 20);
            $registry->registerSidebarLink('publish.presets.index', 'WordPress Templates', 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4', 'Publish', 'app-publish', 21);
            $registry->registerSidebarLink('publish.settings.master', 'Settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'Publish', 'app-publish', 22);

            // Schedule (23)
            $registry->registerSidebarLink('publish.schedule.index', 'Calendar', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'Schedule', 'app-publish', 23);
        }

        // Settings card on /settings page
        view()->composer('settings.index', function ($view) {
            $view->getFactory()->startPush('settings-cards',
                view('app-publish::partials.settings-card')->render());
        });

        // Inject API key settings into the core integrations page
        view()->composer('settings.integrations', function ($view) {
            $factory = $view->getFactory();
            $factory->startPush('integrations-modules',
                view('app-publish::settings.integrations')->render());
            $factory->startPush('scripts',
                view('app-publish::settings.partials.integrations-scripts')->render());
        });

        // Merge the publishing profile into core's canonical user profile page.
        // Also back-fills data for the legacy /publish/users/{id} view since both render the same partial.
        view()->composer('app-publish::publishing.partials.user-profile-sections', function ($view) {
            $user = $view->getData()['user'] ?? null;
            if (!$user) return;
            $view->with(app(UserProfileDataService::class)->forUser($user));
        });

        // Register as a section on core's /settings/users/{id} profile page.
        if (class_exists(PackageRegistryService::class)) {
            app(PackageRegistryService::class)->registerUserProfileSection(
                'publish.profile',
                'Publishing Profile',
                'app-publish::publishing.partials.user-profile-sections',
                'app-publish',
                40
            );
        }
    }

    private function registerTemplateCenterSupport(): void
    {
        if (!class_exists(PackageRegistryService::class)) {
            return;
        }

        $registry = app(PackageRegistryService::class);
        $shortcodes = config('hws-publish.shortcodes', []);

        if (method_exists($registry, 'registerShortcodes')) {
            $registry->registerShortcodes($shortcodes, 'app-publish');
        }

        if (method_exists($registry, 'registerTemplateUseCase')) {
            $registry->registerTemplateUseCase('publication_notification', [
                'label' => 'Publication Notification',
                'used_in' => 'Publish Article / publish2 → Review & Publish after a live post is published.',
                'shortcodes' => array_keys($shortcodes),
                'sort_order' => 30,
                'mode' => 'full_email',
                'mode_label' => 'Email Template',
                'mode_note' => 'Used by the post-publish client notification composer in Publish Article.',
            ], 'app-publish');
        }
    }

    /**
     * Register role permissions for this app's routes.
     */
    private function registerPermissions(): void
    {
        $existing = config('hws.role_permissions.manager', []);
        config([
            'hws.role_permissions.manager' => array_merge($existing, [
                'publish.accounts.*',
                'publish.sites.*',
                'campaigns.*',
                'publish.articles.*',
                'publish.templates.*',
            ]),
        ]);

        $viewerExisting = config('hws.role_permissions.viewer', []);
        config([
            'hws.role_permissions.viewer' => array_merge($viewerExisting, [
                'publish.accounts.show',
                'publish.sites.index',
                'publish.articles.index',
            ]),
        ]);
    }

    private function syncNonAdminSiteRolePatterns(): void
    {
        try {
            if (!Schema::hasTable('roles')) {
                return;
            }

            $siteRoutes = ['publish.sites.index', 'publish.sites.show'];

            foreach (['user', 'employee', 'customer'] as $slug) {
                $role = Role::where('slug', $slug)->first();
                if (!$role) {
                    continue;
                }

                $patterns = array_values(array_unique(array_merge($role->route_patterns ?? [], $siteRoutes)));
                if (($role->route_patterns ?? []) !== $patterns) {
                    $role->update(['route_patterns' => $patterns]);
                }
            }
        } catch (\Throwable $e) {
            // Keep publish boot resilient when roles are not available.
        }
    }

    private function registerForms(): void
    {
        if (!class_exists(FormRegistryService::class)) {
            return;
        }

        app(FormRegistryService::class)->register(
            ArticlePresetForm::FORM_KEY,
            fn (array $context = []) => ArticlePresetForm::make($context)
        );

        app(FormRegistryService::class)->register(
            WordPressPresetForm::FORM_KEY,
            fn (array $context = []) => WordPressPresetForm::make($context)
        );

        app(FormRegistryService::class)->register(
            CampaignPresetForm::FORM_KEY,
            fn (array $context = []) => CampaignPresetForm::make($context)
        );
    }

    private function registerCrons(): void
    {
        if (!class_exists(CronManagerService::class)) {
            return;
        }

        app(CronManagerService::class)->register(
            'app-publish',
            'publish:run-campaigns',
            (string) config('hws-publish.campaign_cron_schedule', '* * * * *'),
            'Process due publishing campaigns and generate scheduled news articles.'
        );
    }

    private function registerNonAdminWorkspaceGuard(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', \hexa_app_publish\Http\Middleware\EnforceNonAdminWorkspaceScope::class);

        view()->composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (!$user) {
                return;
            }

            if (app(PublishAccessService::class)->isAdmin($user)) {
                return;
            }

            $view->getFactory()->startPush('scripts', view('app-publish::partials.non-admin-menu-guard')->render());
        });
    }

    /**
     * Register list categories for the publishing system.
     * Each category provides dropdown options with descriptions and AI prompts.
     *
     * @return void
     */
    private function registerListCategories(): void
    {
        /** @var ListService $listService */
        $listService = $this->app->make(ListService::class);

        foreach (PublishListCatalog::registryCategories() as $category) {
            $listService->registerCategory(
                $category['key'],
                $category['label'],
                $category['description'],
                $category['values'],
                'app-publish'
            );
        }
    }
}
