{{-- Templates list --}}
@extends('layouts.app')
@section('title', 'Article Presets')
@section('header', 'Article Presets')

@section('content')
<div class="space-y-4">

    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.templates.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <select name="account_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Accounts</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
            <select name="article_type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Types</option>
                @foreach($articleTypes as $type)
                    <option value="{{ $type }}" {{ request('article_type') === $type ? 'selected' : '' }}>{{ ucwords(str_replace('-', ' ', $type)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
        <a href="{{ route('publish.templates.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Template</a>
    </div>

    <p class="text-sm text-gray-500">{{ $templates->count() }} template(s)</p>

    @if($templates->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No templates created yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($templates as $template)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-2">
                    <a href="{{ route('publish.templates.show', $template->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $template->name }}</a>
                    <a href="{{ route('publish.templates.edit', $template->id) }}" class="text-gray-400 hover:text-blue-600 flex-shrink-0 ml-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                </div>
                <p class="text-xs text-gray-400 mb-1">{{ $template->account->name }}</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ ($template->status ?? 'draft') === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ ucfirst($template->status ?? 'draft') }}</span>
                @if($template->is_default)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">Default</span>
                @endif
                @if($template->article_type)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">{{ ucwords(str_replace('-', ' ', $template->article_type)) }}</span>
                @endif
                @if($template->ai_engine)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 ml-1">{{ ucfirst($template->ai_engine) }}</span>
                @endif
                @if($template->word_count_min || $template->word_count_max)
                    <p class="text-xs text-gray-400 mt-2">{{ $template->word_count_min ?? '—' }} - {{ $template->word_count_max ?? '—' }} words</p>
                @endif
                @if($template->description)
                    <p class="text-xs text-gray-500 mt-2 break-words">{{ \Illuminate\Support\Str::limit($template->description, 120) }}</p>
                @endif
                <p class="text-xs text-gray-400 mt-2">{{ $template->campaigns->count() }} campaign(s) using this</p>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
