{{-- Article detail --}}
@extends('layouts.app')
@section('title', $article->title ?? 'Article')
@section('header', $article->title ?? 'Untitled Article')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-mono text-gray-400 mb-1">{{ $article->article_id }}</p>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $article->title ?? '(untitled)' }}</h2>
                <p class="text-sm text-gray-500 mt-1">Site: <a href="{{ route('publish.sites.show', $article->site->id) }}" class="text-blue-600 hover:text-blue-800">{{ $article->site->name }}</a></p>
                @if($article->campaign)
                    <p class="text-sm text-gray-500">Campaign: <a href="{{ route('publish.campaigns.show', $article->campaign->id) }}" class="text-blue-600 hover:text-blue-800">{{ $article->campaign->name }}</a></p>
                @else
                    <p class="text-sm text-gray-500">One-off article</p>
                @endif
                <p class="text-sm text-gray-500">Created by: {{ $article->creator->name ?? '—' }} &middot; {{ $article->created_at->format('M j, Y g:i A') }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if($article->status === 'published')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Published</span>
                @elseif($article->status === 'completed')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-600">Completed</span>
                @elseif($article->status === 'review')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Review</span>
                @elseif($article->status === 'ready')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Ready</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($article->status) }}</span>
                @endif
                <a href="{{ route('publish.articles.edit', $article->id) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Edit / Editor</a>
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-lg font-bold text-gray-800">{{ $article->word_count ? number_format($article->word_count) : '—' }}</p>
            <p class="text-xs text-gray-500">Words</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-lg font-bold {{ $article->ai_detection_score !== null ? ($article->ai_detection_score < 30 ? 'text-green-600' : ($article->ai_detection_score < 60 ? 'text-yellow-600' : 'text-red-600')) : 'text-gray-400' }}">
                {{ $article->ai_detection_score !== null ? number_format($article->ai_detection_score, 0) . '%' : '—' }}
            </p>
            <p class="text-xs text-gray-500">AI Detection</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-lg font-bold {{ $article->seo_score !== null ? ($article->seo_score >= 70 ? 'text-green-600' : ($article->seo_score >= 40 ? 'text-yellow-600' : 'text-red-600')) : 'text-gray-400' }}">
                {{ $article->seo_score !== null ? number_format($article->seo_score, 0) : '—' }}
            </p>
            <p class="text-xs text-gray-500">SEO Score</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-lg font-bold text-gray-800">{{ $article->article_type ? ucwords(str_replace('-', ' ', $article->article_type)) : '—' }}</p>
            <p class="text-xs text-gray-500">Type</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-lg font-bold text-gray-800">{{ $article->ai_engine_used ? ucfirst($article->ai_engine_used) : '—' }}</p>
            <p class="text-xs text-gray-500">AI Engine</p>
        </div>
    </div>

    {{-- WordPress info --}}
    @if($article->wp_post_id)
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm">
        <p class="font-medium text-green-800">Published to WordPress</p>
        <p class="text-green-700 mt-1">Post ID: {{ $article->wp_post_id }} &middot; Status: {{ $article->wp_status }}</p>
        @if($article->wp_post_url)
            <p class="mt-1"><a href="{{ $article->wp_post_url }}" target="_blank" class="text-green-700 underline hover:text-green-900">{{ $article->wp_post_url }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></p>
        @endif
    </div>
    @endif

    {{-- Article body preview --}}
    @if($article->body)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Content Preview</h3>
        <div class="prose max-w-none text-sm break-words">{!! $article->body !!}</div>
    </div>
    @endif

    {{-- Source articles --}}
    @if($article->source_articles && count($article->source_articles) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Source Articles</h3>
        <div class="space-y-2">
            @foreach($article->source_articles as $src)
            <div class="flex items-start gap-2 text-sm">
                <span class="text-gray-400 flex-shrink-0">&#8226;</span>
                <div class="break-words">
                    @if(isset($src['url']))
                        <a href="{{ $src['url'] }}" target="_blank" class="text-blue-600 hover:text-blue-800">{{ $src['title'] ?? $src['url'] }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                    @else
                        <span class="text-gray-600">{{ is_string($src) ? $src : json_encode($src) }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
