{{-- All Articles --}}
@extends('layouts.app')
@section('title', 'Articles')
@section('header', 'Articles')

@section('content')
<div class="space-y-4">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">{{ $drafts->total() }} article(s)</p>
        <a href="{{ route('publish.pipeline') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Article</a>
    </div>

    @if($drafts->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No articles yet.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Words</th>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($drafts as $article)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-xs text-gray-400 font-mono">#{{ $article->id }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('publish.articles.show', $article->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $article->title ?? 'Untitled' }}</a>
                        </td>
                        <td class="px-4 py-2">
                            @switch($article->status)
                                @case('completed')
                                @case('published')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ $article->wp_post_id ? 'Published' : 'Completed' }}
                                    </span>
                                    @break
                                @case('drafting')
                                @case('sourcing')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        {{ $article->wp_post_id ? 'WP Draft' : 'Local Draft' }}
                                    </span>
                                    @break
                                @case('spinning')
                                @case('review')
                                @case('ai-check')
                                @case('ready')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ ucfirst($article->status) }}</span>
                                    @break
                                @case('failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($article->status) }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-500">{{ $article->site->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs text-gray-500">{{ $article->word_count ? number_format($article->word_count) : '—' }}</td>
                        <td class="px-4 py-2 text-xs text-gray-400">{{ $article->updated_at->diffForHumans() }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('publish.articles.show', $article->id) }}" class="text-xs text-blue-600 hover:text-blue-800">View</a>
                                @if($article->wp_post_url)
                                    <a href="{{ $article->wp_post_url }}" target="_blank" class="text-xs text-green-600 hover:text-green-800">WP Post</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $drafts->links() }}</div>
    @endif
</div>
@endsection
