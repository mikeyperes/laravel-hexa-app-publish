<?php

return [

    'version' => '18.16.0',

    /*
    |--------------------------------------------------------------------------
    | Article Types
    |--------------------------------------------------------------------------
    |
    | Available article types for templates and campaigns.
    |
    */
    'article_types' => [
        'editorial',
        'opinion',
        'news-report',
        'local-news',
        'expert-article',
        'pr-full-feature',
        'press-release',
    ],

    /*
    |--------------------------------------------------------------------------
    | Article Statuses
    |--------------------------------------------------------------------------
    |
    | Pipeline statuses an article flows through.
    |
    */
    'article_statuses' => [
        'sourcing',
        'drafting',
        'spinning',
        'review',
        'ai-check',
        'ready',
        'published',
        'failed',
        'completed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Modes
    |--------------------------------------------------------------------------
    |
    | How a campaign delivers its articles.
    |
    */
    'campaign_modes' => [
        'draft-local',
        'draft-wordpress',
        'auto-publish',
        'review',
        'notify',
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign-Supported Delivery Modes
    |--------------------------------------------------------------------------
    |
    | Campaigns are intentionally narrower than the full publishing system.
    |
    */
    'campaign_supported_modes' => [
        'draft-local',
        'draft-wordpress',
        'auto-publish',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supporting URL Types
    |--------------------------------------------------------------------------
    |
    | Guidance options for web research / supporting source selection in AI Spin.
    |
    */
    'supporting_url_types' => [
        'matching_content_type' => [
            'label' => 'Matching Content Type',
            'description' => 'Prefer supporting URLs that match the article’s actual content category and editorial style.',
        ],
        'news' => [
            'label' => 'News Sources',
            'description' => 'Favor journalistic reporting, trade coverage, and current news articles over research papers.',
        ],
        'academic_research' => [
            'label' => 'Academic / Research',
            'description' => 'Favor studies, journals, university research, and formal research publications.',
        ],
        'official_primary' => [
            'label' => 'Official / Primary',
            'description' => 'Favor government, company, regulator, nonprofit, and other primary-source URLs.',
        ],
        'passive_background' => [
            'label' => 'Passive / Background',
            'description' => 'Favor broad background and reference material for context, not heavy evidence gathering.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Photo Metadata Generation Strategies
    |--------------------------------------------------------------------------
    |
    | Controls how featured-image alt text, captions, and filenames are created.
    |
    */
    'photo_meta_generation_strategies' => [
        'local_deterministic_first' => [
            'label' => 'Local deterministic generator first',
            'description' => 'Use a fast PHP generator first and only fall back to AI when required.',
        ],
        'local_only' => [
            'label' => 'Local deterministic only',
            'description' => 'Always generate photo metadata locally with no AI fallback.',
        ],
        'ai_only' => [
            'label' => 'AI only',
            'description' => 'Always call an AI model for photo metadata.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign-Supported Article Types
    |--------------------------------------------------------------------------
    |
    | Campaign automation is news-focused. Promotional types are excluded.
    |
    */
    'campaign_supported_article_types' => [
        'news-report',
        'local-news',
        'editorial',
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Discovery Modes
    |--------------------------------------------------------------------------
    |
    | Shared discovery modes used by campaign presets and execution.
    |
    */
    'campaign_discovery_modes' => [
        'keyword',
        'local',
        'trending',
        'genre',
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Final Article Methods
    |--------------------------------------------------------------------------
    |
    | Keep campaigns focused on search-driven news generation for now.
    |
    */
    'campaign_final_article_methods' => [
        'news-search',
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Cron
    |--------------------------------------------------------------------------
    */
    'campaign_cron_schedule' => '* * * * *',

    /*
    |--------------------------------------------------------------------------
    | Campaign Intervals
    |--------------------------------------------------------------------------
    |
    | Scheduling interval units.
    |
    */
    'campaign_intervals' => [
        'hourly',
        'daily',
        'weekly',
        'monthly',
    ],

    /*
    |--------------------------------------------------------------------------
    | Photo Sources
    |--------------------------------------------------------------------------
    |
    | Available photo API sources.
    |
    */
    'photo_sources' => [
        'unsplash',
        'pexels',
        'pixabay',
    ],

    /*
    |--------------------------------------------------------------------------
    | Article Sources
    |--------------------------------------------------------------------------
    |
    | Available article/news API sources.
    |
    */
    'article_sources' => [
        'google-news-rss',
        'gnews',
        'newsdata',
        'currents',
        'web-scrape',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Engines
    |--------------------------------------------------------------------------
    |
    | Available AI engines for spinning.
    |
    */
    'ai_engines' => [
        'anthropic',
        'chatgpt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'word_count_min' => 800,
        'word_count_max' => 1500,
        'photos_per_article' => 2,
        'max_links_per_article' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Photo Quality Rules
    |--------------------------------------------------------------------------
    |
    | Internal thresholds used when the system auto-selects featured or inline
    | images. These are not user-facing preset knobs right now.
    |
    */
    'photo_quality' => [
        'probe_top_candidates' => 4,
        'featured' => [
            'min_width' => 1200,
            'min_height' => 630,
            'min_bytes' => 50000,
            'min_aspect_ratio' => 1.3,
            'max_aspect_ratio' => 2.4,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'preferred_sources' => ['google-cse', 'google', 'serpapi'],
        ],
        'inline' => [
            'min_width' => 900,
            'min_height' => 600,
            'min_bytes' => 30000,
            'min_aspect_ratio' => 1.0,
            'max_aspect_ratio' => 2.4,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'preferred_sources' => ['google-cse', 'google', 'serpapi', 'pexels', 'unsplash', 'pixabay'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Shortcodes
    |--------------------------------------------------------------------------
    */
    'shortcodes' => [
        '{account_name}' => 'Publishing account name',
        '{site_name}' => 'WordPress site name',
        '{site_url}' => 'WordPress site URL',
        '{article_title}' => 'Article title',
        '{campaign_name}' => 'Campaign name',
    ],
];
