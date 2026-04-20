{{-- Article Full Report --}}
@extends('layouts.app')
@section('title', ($article->title ?? 'Article') . ' — #' . $article->article_id)
@section('header', '')

@section('content')
@php
    $rawBody = $article->body ? preg_replace('/<div[^>]*class="[^"]*photo-placeholder[^"]*"[^>]*>.*?<\/div>/s', '', $article->body) : '';
    // If the body has no <p> tags but has newlines, convert to paragraphs
    $cleanBody = $rawBody;
    if ($rawBody && !preg_match('/<p[\s>]/i', $rawBody)) {
        $lines = preg_split('/\n{2,}/', trim($rawBody));
        $cleanBody = implode('', array_map(fn($p) => '<p>' . nl2br(trim($p)) . '</p>', array_filter($lines)));
    }
    $featuredImg = null;
    if ($article->wp_images && count($article->wp_images) > 0) {
        foreach ($article->wp_images as $img) { if (!empty($img['is_featured'])) { $featuredImg = $img; break; } }
        if (!$featuredImg) $featuredImg = $article->wp_images[0] ?? null;
    }
    $featuredUrl = $featuredImg['sizes']['medium_large'] ?? $featuredImg['media_url'] ?? null;
    $tz = config('app.timezone', 'America/New_York');
@endphp
<div class="max-w-4xl mx-auto space-y-6" x-data="articleReport()">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('publish.drafts.index') }}" class="text-sm text-gray-400 hover:text-gray-600 inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> All Articles</a>
        <div class="flex items-center gap-2">
            @if($article->wp_post_url)<a href="{{ $article->wp_post_url }}" target="_blank" class="text-xs text-green-600 hover:text-green-800 px-3 py-1.5 border border-green-200 rounded-lg hover:bg-green-50 inline-flex items-center gap-1">View Live <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>@endif
            @if($article->wp_post_id && $article->site)<a href="{{ rtrim($article->site->url, '/') }}/wp-admin/post.php?post={{ $article->wp_post_id }}&action=edit" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 px-3 py-1.5 border border-blue-200 rounded-lg hover:bg-blue-50 inline-flex items-center gap-1">Edit in WP <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>@endif
            <button @click="confirmDelete = true; $nextTick(() => $refs.deleteSection?.scrollIntoView({ behavior: 'smooth' }))" class="text-xs text-red-400 hover:text-red-600 px-3 py-1.5 border border-red-200 rounded-lg hover:bg-red-50">Delete</button>
        </div>
    </div>

    <article class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if($featuredUrl)
        <div class="w-full aspect-video bg-gray-100 overflow-hidden"><img src="{{ $featuredUrl }}" alt="{{ $featuredImg['alt_text'] ?? $article->title }}" class="w-full h-full object-cover"></div>
        @endif
        <div class="p-8">
            <h1 class="text-3xl font-bold text-gray-900 leading-tight mb-4">{{ $article->title ?? '(untitled)' }}</h1>
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mb-6 pb-6 border-b border-gray-100">
                @if($article->published_at)<span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> {{ $article->published_at->setTimezone($tz)->format('F j, Y') }}</span>@endif
                @if($article->author)<span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> {{ $article->author }}</span>@endif
                @if($article->word_count)<span>{{ number_format($article->word_count) }} words</span>@endif
                @if($article->site)<a href="{{ $article->site->url }}" target="_blank" class="text-blue-500 hover:text-blue-700">{{ $article->site->name }}</a>@endif
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ in_array($article->status, ['completed','published']) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($article->wp_status ?? $article->status) }}</span>
            </div>
            @if($cleanBody)
            <div class="article-body break-words">{!! $cleanBody !!}</div>
            <style>
                .article-body { font-size: 1.125rem; line-height: 1.8; color: #1f2937; }
                .article-body p { margin-bottom: 1.25em; }
                .article-body h1 { font-size: 2em; font-weight: 800; margin-top: 1.5em; margin-bottom: 0.5em; color: #111827; }
                .article-body h2 { font-size: 1.3em; font-weight: 700; margin-top: 1.5em; margin-bottom: 0.5em; color: #111827; }
                .article-body h3 { font-size: 1.25em; font-weight: 600; margin-top: 1.3em; margin-bottom: 0.4em; color: #1f2937; }
                .article-body h4 { font-size: 1.1em; font-weight: 600; margin-top: 1.2em; margin-bottom: 0.4em; color: #374151; }
                .article-body img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 1.5em 0; display: block; }
                .article-body figure { margin: 1.5em 0; }
                .article-body figcaption { font-size: 0.875em; color: #6b7280; margin-top: 0.5em; text-align: center; font-style: italic; }
                .article-body a { color: #2563eb; text-decoration: underline; }
                .article-body a:hover { color: #1d4ed8; }
                .article-body blockquote { border-left: 4px solid #d1d5db; padding-left: 1em; margin: 1.5em 0; color: #4b5563; font-style: italic; }
                .article-body ul, .article-body ol { margin: 1em 0; padding-left: 1.5em; }
                .article-body li { margin-bottom: 0.5em; }
                .article-body ul { list-style-type: disc; }
                .article-body ol { list-style-type: decimal; }
                .article-body strong { font-weight: 700; }
                .article-body em { font-style: italic; }
                .article-body hr { border: none; border-top: 1px solid #e5e7eb; margin: 2em 0; }
                .article-body table { width: 100%; border-collapse: collapse; margin: 1.5em 0; }
                .article-body th, .article-body td { border: 1px solid #e5e7eb; padding: 0.5em 0.75em; text-align: left; }
                .article-body th { background: #f9fafb; font-weight: 600; }
            </style>
            @else<p class="text-gray-400 italic">No article body.</p>@endif
            @if(($article->categories && count($article->categories) > 0) || ($article->tags && count($article->tags) > 0))
            <div class="mt-8 pt-6 border-t border-gray-100 space-y-3">
                @if($article->categories && count($article->categories) > 0)<div class="flex flex-wrap items-center gap-2"><span class="text-xs text-gray-400 font-medium uppercase">Categories:</span>@foreach($article->categories as $cat)<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ is_string($cat) ? $cat : ($cat['name'] ?? json_encode($cat)) }}</span>@endforeach</div>@endif
                @if($article->tags && count($article->tags) > 0)<div class="flex flex-wrap items-center gap-2"><span class="text-xs text-gray-400 font-medium uppercase">Tags:</span>@foreach($article->tags as $tag)<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ is_string($tag) ? $tag : ($tag['name'] ?? json_encode($tag)) }}</span>@endforeach</div>@endif
            </div>
            @endif
        </div>
    </article>

    {{-- Generation Breakdown —  AI usage, article, WordPress, source links --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="{ open: true }">
        <button @click="open = !open" class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 rounded-xl">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-gray-700">Generation Breakdown</h3>
                @if($article->ai_cost)<span class="text-[10px] font-medium text-emerald-700 bg-emerald-50 border border-emerald-100 rounded px-1.5 py-0.5">${{ number_format($article->ai_cost, 4) }}</span>@endif
                @if($article->ai_engine_used)<span class="text-[10px] font-medium text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5">{{ $article->ai_engine_used }}</span>@endif
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-cloak class="px-5 pb-5 space-y-5">

            {{-- AI Generation --}}
            <div>
                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 pb-1.5 border-b border-gray-100">AI Generation</div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-x-6 gap-y-3 text-xs">
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Provider</div><div class="text-gray-800 font-medium mt-0.5 break-words">{{ $article->ai_provider ?? '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Model</div><div class="text-gray-800 font-medium mt-0.5 font-mono break-words">{{ $article->ai_engine_used ?? '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Input Tokens</div><div class="text-gray-800 font-medium mt-0.5 font-mono">{{ $article->ai_tokens_input ? number_format($article->ai_tokens_input) : '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Output Tokens</div><div class="text-gray-800 font-medium mt-0.5 font-mono">{{ $article->ai_tokens_output ? number_format($article->ai_tokens_output) : '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Cost</div><div class="text-emerald-700 font-semibold mt-0.5 font-mono">{{ $article->ai_cost ? '$' . number_format($article->ai_cost, 4) : '—' }}</div></div>
                </div>
            </div>

            {{-- Article --}}
            <div>
                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 pb-1.5 border-b border-gray-100">Article</div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-x-6 gap-y-3 text-xs">
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Article ID</div><div class="text-gray-800 font-medium mt-0.5 font-mono">#{{ $article->id }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Public Ref</div><div class="text-gray-800 font-medium mt-0.5 font-mono break-words">{{ $article->article_id ?? '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Type</div><div class="text-gray-800 font-medium mt-0.5">{{ $article->article_type ?? '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Status</div><div class="text-gray-800 font-medium mt-0.5">{{ ucfirst($article->status ?? '—') }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Word Count</div><div class="text-gray-800 font-medium mt-0.5 font-mono">{{ $article->word_count ? number_format($article->word_count) : '—' }}</div></div>
                </div>
            </div>

            {{-- WordPress --}}
            <div>
                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 pb-1.5 border-b border-gray-100">WordPress</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-xs">
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Delivery Mode</div><div class="text-gray-800 font-medium mt-0.5">{{ $article->delivery_mode ?? '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">WP Post ID</div><div class="text-gray-800 font-medium mt-0.5 font-mono">{{ $article->wp_post_id ?? '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">WP Status</div><div class="text-gray-800 font-medium mt-0.5">{{ $article->wp_status ?? '—' }}</div></div>
                    <div class="min-w-0 md:col-span-1"><div class="text-gray-400 text-[11px]">Published</div><div class="text-gray-800 font-medium mt-0.5">{{ $article->published_at ? $article->published_at->setTimezone($tz)->format('M j, Y g:i A') : '—' }}</div></div>
                    <div class="min-w-0 col-span-2 md:col-span-4"><div class="text-gray-400 text-[11px]">WP URL</div><div class="text-gray-800 font-medium mt-0.5 break-all">@if($article->wp_post_url)<a href="{{ $article->wp_post_url }}" target="_blank" class="text-blue-600 hover:underline">{{ $article->wp_post_url }}</a>@else —@endif</div></div>
                </div>
            </div>

            {{-- Source links --}}
            <div>
                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 pb-1.5 border-b border-gray-100">Source</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-xs">
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Site</div><div class="text-gray-800 font-medium mt-0.5 break-words">@if($article->site)<a href="{{ $article->site->url }}" target="_blank" class="text-blue-600 hover:underline">{{ $article->site->name }}</a>@else —@endif</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Campaign</div><div class="text-gray-800 font-medium mt-0.5">@if($article->publish_campaign_id)<a href="{{ route('campaigns.show', $article->publish_campaign_id) }}" class="text-blue-600 hover:underline">#{{ $article->publish_campaign_id }}</a>@else —@endif</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Template</div><div class="text-gray-800 font-medium mt-0.5 font-mono">{{ $article->publish_template_id ? '#' . $article->publish_template_id : '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Preset</div><div class="text-gray-800 font-medium mt-0.5 font-mono">{{ $article->preset_id ? '#' . $article->preset_id : '—' }}</div></div>
                </div>
            </div>

            {{-- Audit --}}
            <div>
                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 pb-1.5 border-b border-gray-100">Audit</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-xs">
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Created By</div><div class="text-gray-800 font-medium mt-0.5 break-words">{{ $article->creator?->name ?? $article->author ?? '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Created</div><div class="text-gray-800 font-medium mt-0.5">{{ $article->created_at ? $article->created_at->setTimezone($tz)->format('M j, Y g:i A') : '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">Updated</div><div class="text-gray-800 font-medium mt-0.5">{{ $article->updated_at ? $article->updated_at->setTimezone($tz)->format('M j, Y g:i A') : '—' }}</div></div>
                    <div class="min-w-0"><div class="text-gray-400 text-[11px]">IP Address</div><div class="text-gray-800 font-medium mt-0.5 font-mono">{{ $article->user_ip ?? '—' }}</div></div>
                </div>
            </div>
        </div>
    </div>

    @php $seo = $article->seo_data ?? []; @endphp
    <div class="bg-gray-50 rounded-xl border border-gray-200" x-data="{ open: true }">
        <button @click="open = !open" class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-100 rounded-xl"><h3 class="font-semibold text-gray-700">SEO</h3><svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
        <div x-show="open" x-cloak class="px-5 pb-5 space-y-2 text-xs">
            <div class="flex gap-2 py-1"><span class="text-gray-400 w-28">Meta Title</span><span class="text-blue-700 font-medium break-words">{{ $article->title ?? '—' }}</span></div>
            <div class="flex gap-2 py-1"><span class="text-gray-400 w-28">Description</span><span class="text-gray-600 break-words">{{ $article->excerpt ?? '—' }}</span></div>
        </div>
    </div>

    @if($article->wp_images && count($article->wp_images) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="{ open: true }">
        <button @click="open = !open" class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 rounded-xl"><h3 class="font-semibold text-gray-700">WordPress Photos ({{ count($article->wp_images) }})</h3><svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
        <div x-show="open" x-cloak class="px-5 pb-5 space-y-3">
            @foreach($article->wp_images as $img)
            <div class="flex items-start gap-4 border border-gray-100 rounded-lg p-3">
                <img src="{{ $img['sizes']['thumbnail'] ?? $img['media_url'] ?? '' }}" alt="{{ $img['alt_text'] ?? '' }}" class="w-20 h-20 object-cover rounded-lg flex-shrink-0 border">
                <div class="flex-1 min-w-0 space-y-1 text-xs">
                    <p class="font-medium text-gray-800">{{ $img['filename'] ?? '—' }}</p>
                    <p class="text-gray-500">ID: {{ $img['media_id'] ?? '—' }} @if(!empty($img['file_size']))| {{ round($img['file_size'] / 1024) }} KB @endif @if(!empty($img['is_featured']))<span class="text-green-600 font-medium ml-1">Featured</span>@endif</p>
                    @if(!empty($img['alt_text']))<p class="text-gray-500">Alt: {{ $img['alt_text'] }}</p>@endif
                    @if(!empty($img['source_url']))<p class="text-gray-400 break-all">Source: {{ \Illuminate\Support\Str::limit($img['source_url'], 80) }}</p>@endif
                    @if(!empty($img['file_path']))<p class="text-gray-400 break-all">Path: {{ $img['file_path'] }}</p>@endif
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-green-500 text-[10px]">_hexa_generated = true</span>
                        @if(!empty($img['media_id']) && $article->publishSite)
                            <a href="{{ rtrim($article->publishSite->url, '/') }}/wp-admin/upload.php?item={{ $img['media_id'] }}" target="_blank" class="text-[10px] text-blue-500 hover:text-blue-700 inline-flex items-center gap-0.5">WP Media Library <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($article->source_articles && count($article->source_articles) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="{ open: true }">
        <button @click="open = !open" class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 rounded-xl"><h3 class="font-semibold text-gray-700">Source Articles ({{ count($article->source_articles) }})</h3><svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
        <div x-show="open" x-cloak class="px-5 pb-5 space-y-3">
            @foreach($article->source_articles as $src)
            <div class="border border-gray-100 rounded-lg p-3"><p class="text-sm font-medium text-gray-900 break-words">{{ $src['title'] ?? 'Untitled' }}</p>@if(isset($src['url']))<a href="{{ $src['url'] }}" target="_blank" class="text-xs text-blue-600 hover:underline break-all">{{ $src['url'] }}</a>@endif</div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-gray-900 rounded-xl border border-gray-700" x-data="{ open: true }">
        <button @click="open = !open" class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-800 rounded-xl"><h3 class="font-semibold text-white">AI Generation</h3><svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
        <div x-show="open" x-cloak class="px-5 pb-5 space-y-2">
            <div class="flex gap-3 py-1"><span class="text-xs text-gray-500 w-28">Model</span><span class="text-sm text-purple-400 font-mono">{{ $article->ai_engine_used ?? '—' }}</span></div>
            <div class="flex gap-3 py-1"><span class="text-xs text-gray-500 w-28">Provider</span><span class="text-sm text-blue-400">{{ $article->ai_provider ?? '—' }}</span></div>
            <div class="flex gap-3 py-1"><span class="text-xs text-gray-500 w-28">Cost</span><span class="text-sm text-yellow-400">${{ $article->ai_cost ? number_format($article->ai_cost, 4) : '0.00' }}</span></div>
            <div class="flex gap-3 py-1"><span class="text-xs text-gray-500 w-28">Tokens</span><span class="text-sm text-green-400">{{ number_format($article->ai_tokens_input ?? 0) }} + {{ number_format($article->ai_tokens_output ?? 0) }}</span></div>
            @if($article->resolved_prompt)
            <div class="mt-3" x-data="{ show: false }"><button @click="show = !show" class="text-xs text-gray-400 hover:text-gray-200 flex items-center gap-1"><svg class="w-3 h-3 transition-transform" :class="show ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg> Full Prompt</button><pre x-show="show" x-cloak class="mt-2 text-xs text-green-300 bg-gray-800 rounded-lg p-4 whitespace-pre-wrap break-words overflow-y-auto" style="max-height:500px;">{{ $article->resolved_prompt }}</pre></div>
            @endif
        </div>
    </div>

    {{-- Inline Delete Section --}}
    <div x-ref="deleteSection" x-show="confirmDelete" x-cloak class="bg-red-50 border border-red-200 rounded-xl p-6 space-y-4">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <div>
                <h3 class="text-lg font-semibold text-red-700">Delete Article</h3>
                <p class="text-sm text-gray-600 mt-1">This will permanently delete <strong>{{ $article->article_id }}</strong> from the local database.</p>
                @if($article->wp_post_id)
                    <p class="text-sm text-red-600 mt-1 font-medium">WordPress post (ID: {{ $article->wp_post_id }}) on {{ $article->site?->name ?? 'unknown site' }} will also be deleted, along with all uploaded media attachments.</p>
                @endif
            </div>
        </div>

        <div x-show="!deleting && !deleteComplete" class="flex gap-3">
            <button @click="deleteArticle()" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 inline-flex items-center gap-2">Delete Everything</button>
            <button @click="confirmDelete = false" class="text-gray-500 hover:text-gray-700 px-4 py-2 text-sm border border-gray-300 rounded-lg">Cancel</button>
        </div>

        <div x-show="deleteLog.length > 0" class="bg-gray-900 rounded-lg p-4">
            <template x-for="(entry, idx) in deleteLog" :key="idx">
                <div class="py-0.5">
                    <div class="flex items-start gap-2 text-xs font-mono">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span :class="{ 'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info', 'text-gray-400': entry.type === 'step' }" x-html="entry.message.replace(/(https?:\/\/[^\s)]+)/g, '<a href=&quot;$1&quot; target=&quot;_blank&quot; class=&quot;underline hover:text-white&quot;>$1 &#8599;</a>')"></span>
                    </div>
                    <template x-if="entry.urls && entry.urls.length > 0">
                        <div class="ml-16 mt-1 space-y-1">
                            <template x-for="(u, uidx) in entry.urls" :key="uidx">
                                <a :href="u.url" target="_blank" class="flex items-center gap-2 text-xs font-mono text-yellow-400 hover:text-yellow-200 underline break-all">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    <span x-text="u.label + ': ' + u.url"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div x-show="deleteComplete" x-cloak class="flex items-center gap-3">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span class="text-sm text-green-700 font-medium">Article deleted successfully.</span>
            <a href="{{ route('publish.drafts.index') }}" class="text-sm text-blue-600 hover:underline ml-2">Back to Articles</a>
        </div>
    </div>
</div>
@push('scripts')
<script>
function articleReport() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    return {
        confirmDelete: false, deleting: false, deleteComplete: false, deleteLog: [],
        _logDel(type, msg) {
            const t = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.deleteLog.push({ type, message: msg, time: t, urls: null });
        },
        async deleteArticle() {
            this.deleting = true;
            this._logDel('step', 'Starting deletion...');
            try {
                const r = await fetch('{{ route("publish.drafts.destroy", $article->id) }}', { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' } });
                const d = await r.json();
                if (d.success) {
                    // Use the detailed log from the backend
                    if (d.log && Array.isArray(d.log)) {
                        d.log.forEach(entry => {
                            const t = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                            this.deleteLog.push({ type: entry.type, message: entry.message, time: t, urls: entry.urls || null });
                        });
                    }
                    this._logDel('success', 'Deletion complete.');
                    this.deleteComplete = true;
                } else {
                    this._logDel('error', d.message || 'Delete failed.');
                    this.deleting = false;
                }
            } catch(e) { this._logDel('error', 'Network error: ' + e.message); this.deleting = false; }
        },
    };
}
</script>
@endpush
@endsection
