<?php

return [

    'version' => '1.0.4',

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
