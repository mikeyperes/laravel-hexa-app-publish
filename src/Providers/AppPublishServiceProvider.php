<?php

namespace hexa_app_publish\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_app_publish\Services\PublishService;
use hexa_app_publish\Console\RunCampaignsCommand;

class AppPublishServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/app-publish.php', 'hws-publish');

        $this->app->singleton(PublishService::class);
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

        $this->registerPermissions();

        if ($this->app->runningInConsole()) {
            $this->commands([RunCampaignsCommand::class]);
        }
    }

    /**
     * Inject sidebar menu items into the core layout.
     */
    private function registerSidebarItems(): void
    {
        view()->composer('layouts.app', function ($view) {
            $factory = $view->getFactory();
            $factory->startPush('sidebar-menu',
                view('app-publish::partials.sidebar-menu')->render());
            $factory->startPush('sidebar-settings',
                view('app-publish::partials.sidebar-settings')->render());
        });
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
                'publish.campaigns.*',
                'publish.articles.*',
                'publish.templates.*',
            ]),
        ]);

        $viewerExisting = config('hws.role_permissions.viewer', []);
        config([
            'hws.role_permissions.viewer' => array_merge($viewerExisting, [
                'publish.accounts.index',
                'publish.accounts.show',
                'publish.sites.index',
                'publish.articles.index',
            ]),
        ]);
    }
}
