{{-- Publishing Dashboard --}}
@extends('layouts.app')
@section('title', 'Publishing Dashboard')
@section('header', 'Publishing Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500">Users</p>
            <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['total_users'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500">Connected Sites</p>
            <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['total_sites'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500">Active Campaigns</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['active_campaigns'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500">Articles Today</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['articles_today'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Active campaigns --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">Active Campaigns</h3>
            </div>
            @if($activeCampaigns->isEmpty())
                <div class="p-5 text-center text-gray-500 text-sm">No active campaigns.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($activeCampaigns as $c)
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <a href="{{ route('publish.campaigns.show', $c->id) }}" class="text-sm text-blue-600 hover:text-blue-800 break-words">{{ $c->name }}</a>
                            <p class="text-xs text-gray-400">{{ $c->site->name ?? '—' }} &middot; {{ $c->articles_per_interval }}/{{ $c->interval_unit }}</p>
                        </div>
                        <div class="text-right">
                            @if($c->next_run_at)
                                <p class="text-xs text-gray-500">Next: {{ $c->next_run_at->diffForHumans() }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent articles --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">Recent Articles</h3>
            </div>
            @if($recentArticles->isEmpty())
                <div class="p-5 text-center text-gray-500 text-sm">No articles yet.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($recentArticles as $article)
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <a href="{{ route('publish.articles.show', $article->id) }}" class="text-sm text-blue-600 hover:text-blue-800 break-words">{{ $article->title ?? '(untitled)' }}</a>
                            <p class="text-xs text-gray-400">{{ $article->site->name ?? '—' }} &middot; {{ $article->created_at->diffForHumans() }}</p>
                        </div>
                        @if($article->status === 'published')<span class="text-xs text-green-600 font-medium">Published</span>
                        @elseif($article->status === 'review')<span class="text-xs text-yellow-600 font-medium">Review</span>
                        @else <span class="text-xs text-gray-400">{{ ucfirst($article->status) }}</span>@endif
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Pipeline summary --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Article Pipeline</h3>
        <div class="flex flex-wrap gap-4 text-sm">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-gray-300"></span>
                <span class="text-gray-600">Drafting: {{ $stats['articles_drafting'] }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
                <span class="text-gray-600">In Review: {{ $stats['articles_in_review'] }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                <span class="text-gray-600">Published: {{ $stats['articles_published'] }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
