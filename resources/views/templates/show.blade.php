{{-- Template detail --}}
@extends('layouts.app')
@section('title', $template->name)
@section('header', $template->name)

@section('content')
<div class="space-y-6">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">Account: <a href="{{ route('publish.accounts.show', $template->account->id) }}" class="text-blue-600 hover:text-blue-800">{{ $template->account->name }}</a></p>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $template->name }}</h2>
                @if($template->description)
                    <p class="text-sm text-gray-500 mt-2 break-words">{{ $template->description }}</p>
                @endif
            </div>
            <a href="{{ route('publish.templates.edit', $template->id) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Edit</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
            <div>
                <p class="text-xs text-gray-400 uppercase">Article Type</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->article_type ? ucwords(str_replace('-', ' ', $template->article_type)) : '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">AI Engine</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->ai_engine ? ucfirst($template->ai_engine) : 'Default' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Tone</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->tone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Word Count</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->word_count_min ?? '—' }} - {{ $template->word_count_max ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Photos</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->photos_per_article ?? '—' }} per article</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Max Links</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->max_links ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Photo Sources</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->photo_sources ? implode(', ', $template->photo_sources) : '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase">Campaigns Using</p>
                <p class="text-sm text-gray-700 mt-1">{{ $template->campaigns->count() }}</p>
            </div>
        </div>
    </div>

    @if($template->ai_prompt)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">AI Prompt / Instructions</h3>
        <div class="bg-gray-900 text-green-400 rounded-lg p-4 text-sm font-mono break-words whitespace-pre-wrap">{{ $template->ai_prompt }}</div>
    </div>
    @endif

    @if($template->campaigns->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Campaigns Using This Template</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($template->campaigns as $c)
            <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                <div>
                    <a href="{{ route('publish.campaigns.show', $c->id) }}" class="text-sm text-blue-600 hover:text-blue-800 break-words">{{ $c->name }}</a>
                    <p class="text-xs text-gray-400">{{ $c->site->name ?? '—' }} &middot; {{ $c->articles_per_interval }}/{{ $c->interval_unit }}</p>
                </div>
                @if($c->status === 'active')<span class="text-xs text-green-600 font-medium">Active</span>
                @elseif($c->status === 'paused')<span class="text-xs text-yellow-600 font-medium">Paused</span>
                @else <span class="text-xs text-gray-400">{{ ucfirst($c->status) }}</span>@endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
