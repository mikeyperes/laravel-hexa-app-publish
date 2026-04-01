{{-- Article Full Report --}}
@extends('layouts.app')
@section('title', ($article->title ?? 'Article') . ' — #' . $article->id)
@section('header', 'Article Report — #' . $article->id)

@section('content')
<div class="max-w-6xl mx-auto space-y-4">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-mono text-gray-400 mb-1">#{{ $article->id }} &middot; {{ $article->article_id }}</p>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $article->title ?? '(untitled)' }}</h2>
                <div class="flex flex-wrap gap-3 mt-2 text-sm text-gray-500">
                    @if($article->site)
                        <span>Site: <a href="{{ $article->site->url ?? '#' }}" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1">{{ $article->site->name }} <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></span>
                    @endif
                    @if($article->author)
                        <span>Author: <span class="font-medium text-gray-700">{{ $article->author }}</span></span>
                    @endif
                    @if($article->creator)
                        <span>Created by: <a href="{{ route('publish.users.show', $article->creator->id) }}" class="text-blue-600 hover:underline">{{ $article->creator->name }}</a> ({{ $article->creator->email }})</span>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3 mt-1 text-xs text-gray-400">
                    @if($article->created_at)
                        <span>Created: {{ $article->created_at->setTimezone(config('app.timezone', 'America/New_York'))->format('M j, Y g:i A T') }} ({{ $article->created_at->utc()->format('H:i') }} UTC)</span>
                    @endif
                    @if($article->published_at)
                        <span>Published: {{ $article->published_at->setTimezone(config('app.timezone', 'America/New_York'))->format('M j, Y g:i A T') }}</span>
                    @endif
                    @if($article->user_ip)
                        <span>IP: {{ $article->user_ip }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($article->status === 'completed' || $article->status === 'published')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ ucfirst($article->status) }}</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($article->status) }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 text-center">
            <p class="text-lg font-bold text-gray-800">{{ $article->word_count ? number_format($article->word_count) : '—' }}</p>
            <p class="text-xs text-gray-500">Words</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 text-center">
            <p class="text-lg font-bold text-gray-800">{{ $article->ai_engine_used ?? '—' }}</p>
            <p class="text-xs text-gray-500">AI Model</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 text-center">
            <p class="text-lg font-bold text-green-600">${{ $article->ai_cost ? number_format($article->ai_cost, 4) : '0.00' }}</p>
            <p class="text-xs text-gray-500">AI Cost</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 text-center">
            <p class="text-lg font-bold text-gray-800">{{ is_array($article->wp_images) ? count($article->wp_images) : 0 }}</p>
            <p class="text-xs text-gray-500">WP Images</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 text-center">
            <p class="text-lg font-bold text-gray-800">{{ is_array($article->categories) ? count($article->categories) : 0 }}</p>
            <p class="text-xs text-gray-500">Categories</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 text-center">
            <p class="text-lg font-bold text-gray-800">{{ is_array($article->tags) ? count($article->tags) : 0 }}</p>
            <p class="text-xs text-gray-500">Tags</p>
        </div>
    </div>

    {{-- WordPress Info + Links --}}
    @if($article->wp_post_id || $article->site)
    <div class="bg-green-50 border border-green-200 rounded-xl p-5">
        <h3 class="font-semibold text-green-800 mb-3">WordPress</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
            <div><span class="text-green-600 text-xs">Post ID</span><p class="font-medium text-green-900">{{ $article->wp_post_id ?? '—' }}</p></div>
            <div><span class="text-green-600 text-xs">Status</span><p class="font-medium text-green-900">{{ ucfirst($article->wp_status ?? 'unknown') }}</p></div>
            <div><span class="text-green-600 text-xs">Site</span><p class="font-medium text-green-900">{{ $article->site->name ?? '—' }}</p></div>
            <div><span class="text-green-600 text-xs">Author</span><p class="font-medium text-green-900">{{ $article->author ?? '—' }}</p></div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($article->wp_post_url)
                <a href="{{ $article->wp_post_url }}" target="_blank" class="inline-flex items-center gap-1 text-sm bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700">View Post <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
            @endif
            @if($article->site && $article->wp_post_id)
                <a href="{{ rtrim($article->site->url, '/') }}/wp-admin/post.php?post={{ $article->wp_post_id }}&action=edit" target="_blank" class="inline-flex items-center gap-1 text-sm bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">Edit in WP <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
            @endif
            @if($article->site)
                <a href="{{ rtrim($article->site->url, '/') }}/wp-admin/" target="_blank" class="inline-flex items-center gap-1 text-sm bg-gray-600 text-white px-3 py-1.5 rounded-lg hover:bg-gray-700">WP Admin <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
            @endif
        </div>
    </div>
    @endif

    {{-- Source Articles --}}
    @if($article->source_articles && count($article->source_articles) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Source Articles ({{ count($article->source_articles) }})</h3>
        <div class="space-y-3">
            @foreach($article->source_articles as $src)
            <div class="border border-gray-100 rounded-lg p-3">
                <p class="text-sm font-medium text-gray-900 break-words">{{ $src['title'] ?? 'Untitled Source' }}</p>
                @if(isset($src['url']))
                    <a href="{{ $src['url'] }}" target="_blank" class="text-xs text-blue-600 hover:underline break-all inline-flex items-center gap-1 mt-1">
                        {{ $src['url'] }}
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- WordPress Images --}}
    @if($article->wp_images && count($article->wp_images) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">WordPress Images ({{ count($article->wp_images) }})</h3>
        <div class="space-y-4">
            @foreach($article->wp_images as $img)
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start gap-4">
                    <img src="{{ $img['sizes']['thumbnail'] ?? $img['media_url'] ?? '' }}" alt="{{ $img['alt_text'] ?? '' }}" class="w-32 h-32 object-cover rounded-lg flex-shrink-0 border">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 break-words">{{ $img['filename'] ?? 'Unknown' }}</p>
                        <p class="text-xs text-gray-500 mt-1">Media ID: {{ $img['media_id'] ?? '—' }}</p>
                        @if(!empty($img['alt_text']))<p class="text-xs text-gray-400 mt-1">Alt: {{ $img['alt_text'] }}</p>@endif
                        @if(!empty($img['sizes']))
                            <div class="mt-2 space-y-1">
                                @foreach($img['sizes'] as $sizeName => $sizeUrl)
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="text-gray-400 w-24 flex-shrink-0">{{ $sizeName }}</span>
                                        <a href="{{ $sizeUrl }}" target="_blank" class="text-blue-600 hover:underline break-all">{{ $sizeUrl }}</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Categories & Tags --}}
    @if(($article->categories && count($article->categories) > 0) || ($article->tags && count($article->tags) > 0))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if($article->categories && count($article->categories) > 0)
            <div>
                <h3 class="font-semibold text-gray-800 mb-2">Categories ({{ count($article->categories) }})</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($article->categories as $cat)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ is_string($cat) ? $cat : ($cat['name'] ?? json_encode($cat)) }}</span>
                    @endforeach
                </div>
            </div>
            @endif
            @if($article->tags && count($article->tags) > 0)
            <div>
                <h3 class="font-semibold text-gray-800 mb-2">Tags ({{ count($article->tags) }})</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($article->tags as $tag)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ is_string($tag) ? $tag : ($tag['name'] ?? json_encode($tag)) }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- AI Details (dark theme) --}}
    <div class="bg-gray-900 rounded-xl border border-gray-700 p-6">
        <h3 class="font-semibold text-white mb-4">AI Generation Details</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div><p class="text-xs text-gray-500">Model</p><p class="text-sm text-purple-400 font-mono">{{ $article->ai_engine_used ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Provider</p><p class="text-sm text-blue-400">{{ $article->ai_provider ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Cost</p><p class="text-sm text-yellow-400">${{ $article->ai_cost ? number_format($article->ai_cost, 4) : '0.00' }}</p></div>
            <div><p class="text-xs text-gray-500">Tokens</p><p class="text-sm text-green-400">{{ number_format($article->ai_tokens_input ?? 0) }} + {{ number_format($article->ai_tokens_output ?? 0) }} = {{ number_format(($article->ai_tokens_input ?? 0) + ($article->ai_tokens_output ?? 0)) }}</p></div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div><p class="text-xs text-gray-500">User</p><p class="text-sm text-white">{{ $article->creator->name ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Email</p><p class="text-sm text-gray-300">{{ $article->creator->email ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">IP</p><p class="text-sm text-gray-400 font-mono">{{ $article->user_ip ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Time</p><p class="text-sm text-gray-300">{{ $article->created_at ? $article->created_at->setTimezone(config('app.timezone', 'America/New_York'))->format('M j g:i A T') : '—' }}</p><p class="text-xs text-gray-500">{{ $article->created_at ? $article->created_at->utc()->format('H:i') . ' UTC' : '' }}</p></div>
        </div>
        @if($article->resolved_prompt)
        <div x-data="{ showPrompt: false }">
            <button @click="showPrompt = !showPrompt" class="text-xs text-gray-400 hover:text-gray-200 flex items-center gap-1 mb-2">
                <svg class="w-3 h-3 transition-transform" :class="showPrompt ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                Full AI Prompt (<span>{{ number_format(strlen($article->resolved_prompt)) }}</span> chars)
            </button>
            <pre x-show="showPrompt" x-cloak class="text-xs text-green-300 bg-gray-800 rounded-lg p-4 whitespace-pre-wrap break-words overflow-y-auto" style="max-height:500px;">{{ $article->resolved_prompt }}</pre>
        </div>
        @endif
    </div>

    {{-- Article Content --}}
    @if($article->body)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Article Content</h3>
        <div class="prose max-w-none text-sm break-words">{!! $article->body !!}</div>
    </div>
    @endif

</div>
@endsection
