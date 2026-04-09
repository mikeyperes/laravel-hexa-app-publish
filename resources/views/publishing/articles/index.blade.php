{{-- Articles list --}}
@extends('layouts.app')
@section('title', 'Articles')
@section('header', 'Articles')

@section('content')
<div class="space-y-4">

    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.articles.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48" placeholder="Search title/ID...">
            <select name="account_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Accounts</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('-', ' ', $s)) }}</option>
                @endforeach
            </select>
            <select name="sort" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="recent" {{ request('sort', 'recent') === 'recent' ? 'selected' : '' }}>Most Recent</option>
                <option value="published" {{ request('sort') === 'published' ? 'selected' : '' }}>Recently Published</option>
                <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
        <a href="{{ route('publish.articles.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Article</a>
    </div>

    <p class="text-sm text-gray-500">{{ $articles->total() }} article(s)</p>

    @if($articles->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No articles yet.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Article</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Words</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">AI Score</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Published</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">WP Link</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($articles as $article)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('publish.articles.show', $article->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $article->title ?? '(untitled)' }}</a>
                            <p class="text-xs text-gray-400 font-mono">{{ $article->article_id }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-600 text-xs break-words">{{ $article->site->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $article->article_type ? ucwords(str_replace('-', ' ', $article->article_type)) : '—' }}</td>
                        <td class="px-5 py-3 text-gray-600 text-xs">{{ $article->word_count ? number_format($article->word_count) : '—' }}</td>
                        <td class="px-5 py-3 text-xs">
                            @if($article->ai_detection_score !== null)
                                <span class="{{ $article->ai_detection_score < 30 ? 'text-green-600' : ($article->ai_detection_score < 60 ? 'text-yellow-600' : 'text-red-600') }}">{{ number_format($article->ai_detection_score, 0) }}%</span>
                            @else — @endif
                        </td>
                        <td class="px-5 py-3">
                            @if($article->status === 'published')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Published</span>
                            @elseif($article->status === 'completed')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Completed</span>
                            @elseif($article->status === 'review')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Review</span>
                            @elseif($article->status === 'failed')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Failed</span>
                            @elseif($article->status === 'ready')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Ready</span>
                            @elseif($article->status === 'drafting')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Draft</span>
                            @else <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($article->status) }}</span>@endif
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs">
                            @if($article->published_at)
                                {{ $article->published_at->format('M j, Y g:ia') }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs">
                            @if($article->wp_post_url)
                                <a href="{{ $article->wp_post_url }}" target="_blank" class="text-green-600 hover:text-green-800 inline-flex items-center gap-1">
                                    View <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">{{ $articles->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
