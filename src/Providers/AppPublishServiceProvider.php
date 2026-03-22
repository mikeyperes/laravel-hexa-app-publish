<?php

namespace hexa_app_publish\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_app_publish\Services\PublishService;
use hexa_app_publish\Console\RunCampaignsCommand;
use hexa_core\ListRegistry\Services\ListService;

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

        $this->registerListCategories();

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

        // Inject API key settings into the core integrations page
        view()->composer('settings.integrations', function ($view) {
            $factory = $view->getFactory();
            $factory->startPush('integrations-modules',
                view('app-publish::settings.integrations')->render());
            $factory->startPush('scripts',
                view('app-publish::settings.partials.integrations-scripts')->render());
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

        // Article Formats
        $listService->registerCategory(
            'article_formats',
            'Article Formats',
            'Available article format types for content generation',
            [
                [
                    'value'       => 'Editorial',
                    'description' => 'A balanced article presenting facts and analysis on a topic',
                    'ai_prompt'   => 'Write a well-researched editorial that presents multiple viewpoints while maintaining a clear thesis. Include data points and expert perspectives.',
                ],
                [
                    'value'       => 'Expert Article',
                    'description' => 'An authoritative piece written from a specialist perspective',
                    'ai_prompt'   => 'Write as a subject matter expert. Use technical terminology appropriately, cite relevant research, and provide actionable insights.',
                ],
                [
                    'value'       => 'Full Feature PR',
                    'description' => 'A comprehensive promotional piece disguised as editorial content',
                    'ai_prompt'   => 'Write a full-length feature article that naturally incorporates the subject\'s achievements, products, or services within a compelling narrative.',
                ],
                [
                    'value'       => 'Press Release',
                    'description' => 'A formal announcement following AP style conventions',
                    'ai_prompt'   => 'Write in standard press release format with dateline, strong lead paragraph, quotes from stakeholders, and boilerplate company description.',
                ],
                [
                    'value'       => 'Listicle',
                    'description' => 'A list-based article with numbered or bulleted key points',
                    'ai_prompt'   => 'Structure the article as a numbered list with descriptive headers for each point. Include brief explanations under each item.',
                ],
            ],
            'app-publish'
        );

        // Tones
        $listService->registerCategory(
            'tones',
            'Writing Tones',
            'Available writing tones for content generation',
            [
                [
                    'value'       => 'Professional',
                    'description' => 'Formal, business-appropriate language',
                    'ai_prompt'   => 'Use formal language, avoid colloquialisms, maintain objectivity, and write in third person where appropriate.',
                ],
                [
                    'value'       => 'Conversational',
                    'description' => 'Friendly, approachable writing style',
                    'ai_prompt'   => 'Write as if speaking to a friend. Use contractions, rhetorical questions, and relatable examples. Keep sentences shorter.',
                ],
                [
                    'value'       => 'Authoritative',
                    'description' => 'Expert-level confidence and depth',
                    'ai_prompt'   => 'Write with confidence and certainty. Use strong declarative statements, cite sources, and demonstrate deep subject knowledge.',
                ],
                [
                    'value'       => 'Casual',
                    'description' => 'Relaxed, informal tone',
                    'ai_prompt'   => 'Use everyday language, humor where appropriate, and a laid-back style. First person is fine.',
                ],
                [
                    'value'       => 'Investigative',
                    'description' => 'Deep-dive analytical approach',
                    'ai_prompt'   => 'Present findings methodically. Question assumptions, follow evidence trails, and present conclusions supported by data.',
                ],
                [
                    'value'       => 'Persuasive',
                    'description' => 'Compelling, action-oriented writing',
                    'ai_prompt'   => 'Use emotional appeals alongside logic. Include calls to action, address objections, and build urgency.',
                ],
            ],
            'app-publish'
        );

        // Image Preferences
        $listService->registerCategory(
            'image_preferences',
            'Image Preferences',
            'Preferred image styles for article illustrations',
            [
                [
                    'value'       => 'Stock Photography',
                    'description' => 'Clean, professional stock-style images',
                    'ai_prompt'   => 'Search for high-quality, well-lit professional photographs that match the article topic.',
                ],
                [
                    'value'       => 'Editorial Photography',
                    'description' => 'Photojournalistic, documentary-style images',
                    'ai_prompt'   => 'Search for candid, real-world photographs that tell a story and add authenticity.',
                ],
                [
                    'value'       => 'Lifestyle',
                    'description' => 'People-centric, aspirational imagery',
                    'ai_prompt'   => 'Search for lifestyle photographs showing people in real-world settings related to the article topic.',
                ],
                [
                    'value'       => 'Abstract/Conceptual',
                    'description' => 'Symbolic, metaphorical imagery',
                    'ai_prompt'   => 'Search for abstract or conceptual images that represent the article\'s themes symbolically.',
                ],
                [
                    'value'       => 'Infographic-style',
                    'description' => 'Data visualization and informational graphics',
                    'ai_prompt'   => 'Search for clean, data-driven visuals, charts, or infographic-style images.',
                ],
                [
                    'value'       => 'Minimalist',
                    'description' => 'Simple, clean imagery with negative space',
                    'ai_prompt'   => 'Search for minimalist photographs with clean compositions and plenty of white space.',
                ],
            ],
            'app-publish'
        );

        // Category Generation Rules
        $listService->registerCategory(
            'category_generation_rules',
            'Category Generation Rules',
            'Rules for how AI generates WordPress categories',
            [
                [
                    'value'       => 'Broad Topic Match',
                    'description' => 'Match to the widest applicable topic',
                    'ai_prompt'   => 'Assign 2-3 broad categories that represent the main topics. Prefer existing WordPress categories over creating new ones.',
                ],
                [
                    'value'       => 'Specific Niche',
                    'description' => 'Target narrow, specific categories',
                    'ai_prompt'   => 'Create specific, niche categories that precisely describe the article content. Be granular.',
                ],
                [
                    'value'       => 'Industry Standard',
                    'description' => 'Use standard industry category names',
                    'ai_prompt'   => 'Use standard industry terminology for categories. Follow common news/blog categorization patterns.',
                ],
                [
                    'value'       => 'SEO-Optimized',
                    'description' => 'Categories optimized for search engine visibility',
                    'ai_prompt'   => 'Choose categories that contain high-value keywords. Consider search volume and competition.',
                ],
            ],
            'app-publish'
        );

        // Tag Generation Rules
        $listService->registerCategory(
            'tag_generation_rules',
            'Tag Generation Rules',
            'Rules for how AI generates WordPress tags',
            [
                [
                    'value'       => 'Keyword Focused',
                    'description' => 'Tags based on primary and secondary keywords',
                    'ai_prompt'   => 'Extract the most important keywords and phrases from the article as tags. Focus on terms people would search for.',
                ],
                [
                    'value'       => 'Entity Based',
                    'description' => 'Tags for people, places, organizations mentioned',
                    'ai_prompt'   => 'Create tags for every named entity -- people, companies, locations, products, events mentioned in the article.',
                ],
                [
                    'value'       => 'Long-tail SEO',
                    'description' => 'Tags targeting long-tail search queries',
                    'ai_prompt'   => 'Generate tags that match long-tail search queries. Use 2-4 word phrases that readers might search for.',
                ],
                [
                    'value'       => 'Topic Cluster',
                    'description' => 'Tags that connect related content',
                    'ai_prompt'   => 'Create tags that help cluster related articles together. Think about content pillars and topic relationships.',
                ],
            ],
            'app-publish'
        );

        // Image Layout Rules
        $listService->registerCategory(
            'image_layout_rules',
            'Image Layout Rules',
            'Rules for how images are placed within articles',
            [
                [
                    'value'       => 'After Introduction',
                    'description' => 'Single image after the opening paragraph',
                    'ai_prompt'   => 'Place one hero image immediately after the introductory paragraph. No images in the body unless the article is very long.',
                ],
                [
                    'value'       => 'Every Other Paragraph',
                    'description' => 'Images distributed between paragraphs',
                    'ai_prompt'   => 'Insert an image between every second paragraph. Alternate sides if layout supports it.',
                ],
                [
                    'value'       => '3 Photos Evenly Spaced',
                    'description' => 'Three images distributed evenly throughout',
                    'ai_prompt'   => 'Place the first image after the introduction, the second at the midpoint, and the third before the conclusion.',
                ],
                [
                    'value'       => 'Hero Image Only',
                    'description' => 'Single prominent image at the top',
                    'ai_prompt'   => 'Place one large, high-quality image at the very top of the article. No additional images.',
                ],
                [
                    'value'       => '5 Photos Randomly Placed',
                    'description' => 'Five images placed at natural break points',
                    'ai_prompt'   => 'Insert 5 images at natural content transitions throughout the article. Vary placement to avoid predictable patterns.',
                ],
                [
                    'value'       => 'Between Sections',
                    'description' => 'Images as section dividers',
                    'ai_prompt'   => 'Place an image between each major section or heading change. Images serve as visual breaks between topics.',
                ],
            ],
            'app-publish'
        );
    }
}
