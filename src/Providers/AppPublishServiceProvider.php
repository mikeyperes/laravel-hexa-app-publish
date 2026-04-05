<?php

namespace hexa_app_publish\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_app_publish\Services\PublishService;
use hexa_app_publish\Console\RunCampaignsCommand;
use hexa_core\ListRegistry\Services\ListService;
use hexa_core\Services\PackageRegistryService;

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
     * Register sidebar links via core's PackageRegistryService.
     */
    private function registerSidebarItems(): void
    {
        if (!config('hexa.app_controls_sidebar', false)) {
            $registry = app(PackageRegistryService::class);

            // Search (10)
            $registry->registerSidebarLink('publish.search.images', 'Images', 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'Search', 'app-publish', 10);
            $registry->registerSidebarLink('publish.search.articles', 'Articles', 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z', 'Search', 'app-publish', 11);

            // Article (12)
            $registry->registerSidebarLink('publish.pipeline', 'Publish Article', 'M13 5l7 7-7 7M5 5l7 7-7 7', 'Article', 'app-publish', 12);
            $registry->registerSidebarLink('publish.editor', 'Editor', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'Article', 'app-publish', 13);
            $registry->registerSidebarLink('publish.drafts.index', 'Articles', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'Article', 'app-publish', 14);
            $registry->registerSidebarLink('publish.bookmarks.index', 'Bookmarked Articles', 'M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z', 'Article', 'app-publish', 14);
            $registry->registerSidebarLink('publish.templates.index', 'AI Templates', 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z', 'Article', 'app-publish', 15);
            $registry->registerSidebarLink('publish.smart-edits.index', 'AI Smart Edit Templates', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'Article', 'app-publish', 15);

            // Content (16)
            $registry->registerSidebarLink('publish.accounts.index', 'Users', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'Content', 'app-publish', 16);
            $registry->registerSidebarLink('publish.sites.index', 'Sites', 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9', 'Content', 'app-publish', 17);
            $registry->registerSidebarLink('campaigns.index', 'Campaigns', 'M13 10V3L4 14h7v7l9-11h-7z', 'Campaigns', 'app-publish', 18);
            $registry->registerSidebarLink('campaigns.create', 'Create Campaign', 'M12 4v16m8-8H4', 'Campaigns', 'app-publish', 19);
            $registry->registerSidebarLink('campaigns.presets.index', 'Campaign Presets', 'M4 6h16M4 10h16M4 14h16M4 18h16', 'Campaigns', 'app-publish', 20);
            $registry->registerSidebarLink('publish.articles.index', 'Articles', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'Content', 'app-publish', 19);
            $registry->registerSidebarLink('publish.links.index', 'Links & Sitemaps', 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'Content', 'app-publish', 19);
            $registry->registerSidebarLink('publish.ai-activity.index', 'AI Activity', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'Content', 'app-publish', 19);

            // Publishing (20 — directly after Content)
            $registry->registerSidebarLink('publish.prompts.index', 'Prompts', 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'Publishing', 'app-publish', 20);
            $registry->registerSidebarLink('publish.presets.index', 'WordPress Templates', 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4', 'Publishing', 'app-publish', 21);
            $registry->registerSidebarLink('publish.settings.master', 'Settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'Publishing', 'app-publish', 22);

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

        // Backfill description + ai_prompt on existing rows (core's seedDefaults doesn't handle these)
        $this->backfillListDescriptions();
    }

    /**
     * Update existing list items with description and ai_prompt values.
     * Core's ListService only creates rows with list_value — this fills the extra columns.
     */
    private function backfillListDescriptions(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('lists', 'description')) {
            return;
        }

        $categories = [
            'article_formats' => [
                'Editorial' => ['A balanced article presenting facts and analysis on a topic', 'Write a well-researched editorial that presents multiple viewpoints while maintaining a clear thesis. Include data points and expert perspectives.'],
                'Expert Article' => ['An authoritative piece written from a specialist perspective', 'Write as a subject matter expert. Use technical terminology appropriately, cite relevant research, and provide actionable insights.'],
                'Full Feature PR' => ['A comprehensive promotional piece disguised as editorial content', 'Write a full-length feature article that naturally incorporates the subject\'s achievements, products, or services within a compelling narrative.'],
                'Press Release' => ['A formal announcement following AP style conventions', 'Write in standard press release format with dateline, strong lead paragraph, quotes from stakeholders, and boilerplate company description.'],
                'Listicle' => ['A list-based article with numbered or bulleted key points', 'Structure the article as a numbered list with descriptive headers for each point. Include brief explanations under each item.'],
            ],
            'tones' => [
                'Professional' => ['Formal, business-appropriate language', 'Use formal language, avoid colloquialisms, maintain objectivity, and write in third person where appropriate.'],
                'Conversational' => ['Friendly, approachable writing style', 'Write as if speaking to a friend. Use contractions, rhetorical questions, and relatable examples.'],
                'Authoritative' => ['Expert-level confidence and depth', 'Write with confidence and certainty. Use strong declarative statements, cite sources, and demonstrate deep subject knowledge.'],
                'Casual' => ['Relaxed, informal tone', 'Use everyday language, humor where appropriate, and a laid-back style. First person is fine.'],
                'Investigative' => ['Deep-dive analytical approach', 'Present findings methodically. Question assumptions, follow evidence trails, and present conclusions supported by data.'],
                'Persuasive' => ['Compelling, action-oriented writing', 'Use emotional appeals alongside logic. Include calls to action, address objections, and build urgency.'],
            ],
            'image_preferences' => [
                'Stock Photography' => ['Clean, professional stock-style images', 'Search for high-quality, well-lit professional photographs that match the article topic.'],
                'Editorial Photography' => ['Photojournalistic, documentary-style images', 'Search for candid, real-world photographs that tell a story and add authenticity.'],
                'Lifestyle' => ['People-centric, aspirational imagery', 'Search for lifestyle photographs showing people in real-world settings related to the article topic.'],
                'Abstract/Conceptual' => ['Symbolic, metaphorical imagery', 'Search for abstract or conceptual images that represent the article\'s themes symbolically.'],
                'Infographic-style' => ['Data visualization and informational graphics', 'Search for clean, data-driven visuals, charts, or infographic-style images.'],
                'Minimalist' => ['Simple, clean imagery with negative space', 'Search for minimalist photographs with clean compositions and plenty of white space.'],
            ],
            'category_generation_rules' => [
                'Broad Topic Match' => ['Match to the widest applicable topic', 'Assign 2-3 broad categories that represent the main topics. Prefer existing WordPress categories over creating new ones.'],
                'Specific Niche' => ['Target narrow, specific categories', 'Create specific, niche categories that precisely describe the article content. Be granular.'],
                'Industry Standard' => ['Use standard industry category names', 'Use standard industry terminology for categories. Follow common news/blog categorization patterns.'],
                'SEO-Optimized' => ['Categories optimized for search engine visibility', 'Choose categories that contain high-value keywords. Consider search volume and competition.'],
            ],
            'tag_generation_rules' => [
                'Keyword Focused' => ['Tags based on primary and secondary keywords', 'Extract the most important keywords and phrases from the article as tags.'],
                'Entity Based' => ['Tags for people, places, organizations mentioned', 'Create tags for every named entity -- people, companies, locations, products, events mentioned in the article.'],
                'Long-tail SEO' => ['Tags targeting long-tail search queries', 'Generate tags that match long-tail search queries. Use 2-4 word phrases that readers might search for.'],
                'Topic Cluster' => ['Tags that connect related content', 'Create tags that help cluster related articles together. Think about content pillars and topic relationships.'],
            ],
            'image_layout_rules' => [
                'After Introduction' => ['Single image after the opening paragraph', 'Place one hero image immediately after the introductory paragraph.'],
                'Every Other Paragraph' => ['Images distributed between paragraphs', 'Insert an image between every second paragraph.'],
                '3 Photos Evenly Spaced' => ['Three images distributed evenly throughout', 'Place the first image after the introduction, the second at the midpoint, and the third before the conclusion.'],
                'Hero Image Only' => ['Single prominent image at the top', 'Place one large, high-quality image at the very top of the article. No additional images.'],
                '5 Photos Randomly Placed' => ['Five images placed at natural break points', 'Insert 5 images at natural content transitions throughout the article.'],
                'Between Sections' => ['Images as section dividers', 'Place an image between each major section or heading change.'],
            ],
        ];

        foreach ($categories as $listKey => $items) {
            foreach ($items as $value => [$desc, $prompt]) {
                \Illuminate\Support\Facades\DB::table('lists')
                    ->where('list_key', $listKey)
                    ->where('list_value', $value)
                    ->whereNull('description')
                    ->update(['description' => $desc, 'ai_prompt' => $prompt]);
            }
        }
    }
}
