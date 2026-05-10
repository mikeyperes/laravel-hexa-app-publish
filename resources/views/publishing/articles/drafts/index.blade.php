{{-- All Articles --}}
@extends('layouts.app')
@section('title', 'Articles')
@section('header', 'Articles')

@section('content')
<div class="space-y-4" x-data="articlesList()">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <p class="text-sm text-gray-500"><span x-text="totalCount">{{ $drafts->total() }}</span> articles</p>
            <div class="relative">
                <input type="text" x-model="searchQuery" @input.debounce.400ms="search()" placeholder="Search articles..." class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-64 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <svg x-show="searching" x-cloak class="absolute right-3 top-2 w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>
            <select @change="filterStatus($event.target.value)" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <option value="">All Statuses</option>
                <option value="drafting" {{ request('status') === 'drafting' ? 'selected' : '' }}>Draft</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button x-show="selectedIds.length > 0" x-cloak @click="bulkDelete()" :disabled="bulkDeleting" class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 disabled:opacity-50 inline-flex items-center gap-1">
                <svg x-show="bulkDeleting" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Delete Selected (<span x-text="selectedIds.length"></span>)
            </button>
            <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Article
            </a>
        </div>
    </div>

    {{-- Article List --}}
    <div class="space-y-2">
        @forelse($drafts as $draft)
        @php
            $statePayload = is_array($draft->pipelineState?->payload ?? null) ? $draft->pipelineState->payload : [];
            $thumb = null;
            if ($draft->wp_images && is_array($draft->wp_images)) {
                $featured = collect($draft->wp_images)->firstWhere('is_featured', true);
                if (!$featured) $featured = collect($draft->wp_images)->first();
                if ($featured && is_array($featured)) {
                    $thumb = $featured['sizes']['medium'] ?? $featured['sizes']['thumbnail'] ?? $featured['media_url'] ?? null;
                }
            }
            if (!$thumb && $draft->photos && is_array($draft->photos)) {
                $firstPhoto = collect($draft->photos)->first();
                if (is_array($firstPhoto)) {
                    $thumb = $firstPhoto['sizes']['medium'] ?? $firstPhoto['sizes']['thumbnail'] ?? $firstPhoto['url'] ?? $firstPhoto['src'] ?? $firstPhoto['thumb'] ?? null;
                } elseif (is_string($firstPhoto)) {
                    $thumb = $firstPhoto;
                }
            }
            $isPublished = in_array($draft->status, ['completed', 'published'], true) || $draft->wp_post_url;
            $accentClass = $isPublished ? 'border-l-4 border-l-green-500' : 'border-l-4 border-l-gray-300';
            $previewBody = $draft->body ? preg_replace('/<div[^>]*class="[^"]*photo-placeholder[^"]*"[^>]*>.*?<\/div>/s', '', $draft->body) : '';
            if ($previewBody && !preg_match('/<p[\s>]/i', $previewBody)) {
                $parts = preg_split('/\n{2,}/', trim($previewBody));
                $previewBody = implode('', array_map(fn($p) => '<p>' . nl2br(trim($p)) . '</p>', array_filter($parts)));
            }
            $previewCategories = is_array($draft->categories) && $draft->categories
                ? array_values(array_filter(array_map(fn($value) => trim((string) $value), $draft->categories)))
                : array_values(array_filter(array_map(fn($value) => trim((string) $value), (array) ($statePayload['categories'] ?? []))));
            $previewTags = is_array($draft->tags) && $draft->tags
                ? array_values(array_filter(array_map(fn($value) => trim((string) $value), $draft->tags)))
                : array_values(array_filter(array_map(fn($value) => trim((string) $value), (array) ($statePayload['tags'] ?? []))));
            $previewSyndication = ($draft->article_type === 'press-release' && ($draft->site?->is_press_release_source))
                ? array_values(array_filter(array_map(fn($value) => (string) $value, (array) ($statePayload['selectedSyndicationCats'] ?? []))))
                : [];
            $previewWpAdminUrl = ($draft->site && $draft->wp_post_id)
                ? rtrim((string) $draft->site->url, '/') . '/wp-admin/post.php?post=' . $draft->wp_post_id . '&action=edit'
                : null;
        @endphp
        <div class="bg-white rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition-all p-4 {{ $accentClass }}" :class="deletingId === {{ $draft->id }} ? '!border-red-300 !bg-red-50' : ''" id="row-{{ $draft->id }}">
            <div class="flex items-start gap-4">
                {{-- Checkbox --}}
                <div class="pt-1.5 flex-shrink-0">
                    <input type="checkbox" value="{{ $draft->id }}" @change="toggleSelect({{ $draft->id }})" :checked="selectedIds.includes({{ $draft->id }})" class="rounded border-gray-300 text-blue-600">
                </div>

                {{-- Featured image (160x112) --}}
                <a href="{{ route('publish.pipeline', ['id' => $draft->id]) }}" class="flex-shrink-0 w-40 h-28 rounded-md bg-gray-100 overflow-hidden block group">
                    @if($thumb)
                        <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover group-hover:opacity-90 transition" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-300\'><svg class=\'w-9 h-9\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    @endif
                </a>

                {{-- Main content (top-aligned to image) --}}
                <div class="flex-1 min-w-0">

                    {{-- Title row: title (flex-1) + action icons (top-right) --}}
                    <div class="flex items-start gap-3">
                        <a href="{{ route('publish.pipeline', ['id' => $draft->id]) }}" class="flex-1 min-w-0 block text-base font-semibold text-gray-900 hover:text-blue-600 break-words leading-snug">{{ $draft->title ?: 'Untitled' }}</a>
                        <div class="flex-shrink-0 flex items-center gap-1">
                            <button @click="togglePreview({{ $draft->id }})" class="p-1.5 rounded-md text-gray-400 hover:text-slate-700 hover:bg-slate-50 transition-colors" :title="isPreviewOpen({{ $draft->id }}) ? 'Hide preview' : 'Preview in place'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                            <a href="{{ route('publish.pipeline', ['id' => $draft->id]) }}" class="p-1.5 rounded-md text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Open in pipeline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <button @click="openApprovalEmail({{ $draft->id }})" class="p-1.5 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Approval email">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8m-16 8h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            </button>
                            @if($draft->wp_post_url)
                                <a href="{{ $draft->wp_post_url }}" target="_blank" rel="noopener" class="p-1.5 rounded-md text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors" title="Open live post">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            @endif
                            <button @click="deleteSingle({{ $draft->id }})" :disabled="deletingId === {{ $draft->id }}" class="p-1.5 rounded-md text-gray-300 hover:text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Author · Site --}}
                    <p class="text-xs text-gray-500 mt-1">
                        @if($draft->author)
                            By <span class="font-medium text-gray-700">{{ $draft->author }}</span>
                        @endif
                        @if($draft->author && $draft->site)
                            <span class="text-gray-300 mx-1">·</span>
                        @endif
                        @if($draft->site)
                            <span class="inline-flex items-center gap-1 text-gray-600">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                                {{ $draft->site->name }}
                            </span>
                        @endif
                    </p>

                    {{-- Primary meta line: ART · words · AI% · Creator · time-ago --}}
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5 text-[11px] text-gray-500">
                        <span class="font-mono">{{ $draft->article_id }}</span>
                        @if($draft->word_count)
                            <span class="text-gray-300">·</span>
                            <span>{{ number_format($draft->word_count) }} words</span>
                        @endif
                        @if($draft->ai_detection_score !== null && $draft->ai_detection_score !== '' && $draft->ai_detection_score !== '0')
                            <span class="text-gray-300">·</span>
                            <span class="inline-flex items-center gap-1 {{ (float) $draft->ai_detection_score > 50 ? 'text-red-500' : ((float) $draft->ai_detection_score > 20 ? 'text-yellow-600' : 'text-green-600') }}" title="AI detection score">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                AI {{ $draft->ai_detection_score }}%
                            </span>
                        @endif
                        @if($draft->creator)
                            <span class="text-gray-300">·</span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $draft->creator->name }}
                            </span>
                        @endif
                        <span class="text-gray-300">·</span>
                        <span title="{{ $draft->updated_at ?? $draft->created_at }}">{{ $draft->updated_at ? $draft->updated_at->diffForHumans() : ($draft->created_at ? $draft->created_at->diffForHumans() : '') }}</span>
                    </div>

                    {{-- Bottom row: published meta (left, when applicable) + chips (right) --}}
                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] flex-1 min-w-0">
                            @if($draft->published_at)
                                <span class="text-green-600 font-medium inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Published {{ $draft->published_at->format('M j, Y · g:ia') }}
                                </span>
                            @endif
                            @if($draft->wp_post_id)
                                @if($draft->published_at)<span class="text-gray-300">·</span>@endif
                                <span class="text-gray-400">WP #{{ $draft->wp_post_id }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            @if($draft->article_type)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">{{ ucfirst(str_replace('_', ' ', $draft->article_type)) }}</span>
                            @endif
                            @if($draft->status === 'completed' || $draft->status === 'published')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700 border border-green-200">{{ ucfirst($draft->status) }}</span>
                            @elseif($draft->status === 'drafting')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">Draft</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-50 text-yellow-700 border border-yellow-200">{{ ucfirst($draft->status) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="isPreviewOpen({{ $draft->id }})" x-cloak class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 overflow-hidden">
                <div class="border-b border-slate-200 bg-white px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Draft Preview & Approval</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Review the article, prepare it for WordPress, publish it without leaving this page, and send the completion email from the same card.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="refreshWpStatus({{ $draft->id }})" :disabled="rowRunning({{ $draft->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Refresh WP
                        </button>
                        <button @click="prepareWordPress({{ $draft->id }})" :disabled="rowRunning({{ $draft->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50">
                            <svg x-show="rowRunning({{ $draft->id }}) && rowPhase({{ $draft->id }}) === 'Preparing'" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span>Prepare Only</span>
                        </button>
                        <button x-show="!rowHasWpPost({{ $draft->id }})" x-cloak @click="prepareAndCreateWpDraft({{ $draft->id }})" :disabled="rowRunning({{ $draft->id }})" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                            <svg x-show="rowRunning({{ $draft->id }})" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span>Prepare & Create WP Draft</span>
                        </button>
                        <button x-show="rowHasWpPost({{ $draft->id }}) && !rowIsLive({{ $draft->id }})" x-cloak @click="publishExistingDraft({{ $draft->id }}, 'publish')" :disabled="rowRunning({{ $draft->id }})" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50">
                            <svg x-show="rowRunning({{ $draft->id }})" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span>Publish Existing Draft</span>
                        </button>
                        <button x-show="rowIsLive({{ $draft->id }})" x-cloak @click="publishExistingDraft({{ $draft->id }}, 'publish')" :disabled="rowRunning({{ $draft->id }})" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">
                            <svg x-show="rowRunning({{ $draft->id }})" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span>Update Live Post</span>
                        </button>
                        <button x-show="rowHasWpPost({{ $draft->id }})" x-cloak @click="openApprovalEmail({{ $draft->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8m-16 8h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            Email Completed Draft
                        </button>
                    </div>
                </div>

                <div class="px-4 py-4 space-y-4">
                    <div x-show="rowMessage({{ $draft->id }})" x-cloak class="rounded-lg border px-3 py-2 text-sm" :class="rowError({{ $draft->id }}) ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'">
                        <span x-text="rowMessage({{ $draft->id }})"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 text-xs">
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                                    <p class="font-semibold uppercase tracking-wide text-slate-400">Publication</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $draft->site?->name ?? 'No site selected' }}</p>
                                    @if($draft->site?->url)
                                        <a href="{{ $draft->site->url }}" target="_blank" rel="noopener" class="mt-1 inline-flex break-all text-blue-600 hover:underline">{{ $draft->site->url }}</a>
                                    @endif
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                                    <p class="font-semibold uppercase tracking-wide text-slate-400">WordPress</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900" x-text="rowWpPostLabel({{ $draft->id }})">{{ $draft->wp_post_id ? ('#' . $draft->wp_post_id) : 'No WP post yet' }}</p>
                                    <a x-show="rowWpPostUrl({{ $draft->id }})" x-cloak :href="rowWpPostUrl({{ $draft->id }})" target="_blank" rel="noopener" class="mt-1 inline-flex break-all text-blue-600 hover:underline" x-text="rowWpPostUrl({{ $draft->id }})"></a>
                                    <a x-show="rowWpAdminUrl({{ $draft->id }})" x-cloak :href="rowWpAdminUrl({{ $draft->id }})" target="_blank" rel="noopener" class="mt-1 inline-flex break-all text-slate-500 hover:text-slate-700 hover:underline">WP Admin Edit</a>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                                    <p class="font-semibold uppercase tracking-wide text-slate-400">Article Settings</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ ucfirst(str_replace('_', ' ', $draft->article_type ?: 'editorial')) }}</p>
                                    <p class="mt-1 text-slate-500">Delivery: {{ str_replace('-', ' ', $draft->delivery_mode ?: 'draft-local') }}</p>
                                    <p class="mt-1 text-slate-500">Author: {{ $draft->author ?: ($draft->site?->default_author ?: '—') }}</p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Categories</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @forelse($previewCategories as $category)
                                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700">{{ $category }}</span>
                                            @empty
                                                <span class="text-xs text-slate-400">None selected</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tags</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @forelse($previewTags as $tag)
                                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700">{{ $tag }}</span>
                                            @empty
                                                <span class="text-xs text-slate-400">None selected</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                                @if(count($previewSyndication) > 0)
                                    <div class="mt-4 border-t border-slate-100 pt-4">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Publication Syndication</p>
                                        <p class="mt-2 text-xs text-slate-500">Selected term IDs: {{ implode(', ', $previewSyndication) }}</p>
                                    </div>
                                @endif
                            </div>

                            @if($draft->excerpt)
                                <div class="rounded-lg border border-slate-200 bg-white px-4 py-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Excerpt</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-700">{{ $draft->excerpt }}</p>
                                </div>
                            @endif

                            <div class="rounded-lg border border-slate-200 bg-white">
                                <div class="border-b border-slate-200 px-4 py-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Article Preview</p>
                                        <p class="mt-0.5 text-xs text-slate-500">Inline preview of the current article body and formatting.</p>
                                    </div>
                                    <span class="text-xs text-slate-400">{{ number_format($draft->word_count ?: str_word_count(strip_tags($previewBody))) }} words</span>
                                </div>
                                <div class="max-h-[36rem] overflow-y-auto px-4 py-4">
                                    <div class="prose prose-sm max-w-none prose-headings:text-slate-900 prose-p:text-slate-700 prose-a:text-blue-600 prose-img:rounded-lg">
                                        {!! $previewBody ?: '<p class="text-sm text-slate-400">No article body saved yet.</p>' !!}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Prepare Status</p>
                                        <p class="mt-0.5 text-xs text-slate-500">Uses the same WordPress preparation stages as the main publishing flow.</p>
                                    </div>
                                    <span x-show="rowPhase({{ $draft->id }})" x-cloak class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600" x-text="rowPhase({{ $draft->id }})"></span>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <template x-for="item in rowChecklist({{ $draft->id }})" :key="'{{ $draft->id }}-' + item.key">
                                        <div class="flex items-start gap-3 rounded-lg border border-slate-100 px-3 py-3">
                                            <div class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-bold"
                                                 :class="{
                                                    'bg-green-100 text-green-700': item.status === 'done',
                                                    'bg-red-100 text-red-700': item.status === 'failed',
                                                    'bg-blue-100 text-blue-700': item.status === 'running',
                                                    'bg-slate-100 text-slate-500': item.status === 'pending' || item.status === 'skipped'
                                                 }"
                                                 x-text="item.status === 'done' ? '✓' : (item.status === 'failed' ? '!' : (item.status === 'running' ? '…' : '○'))"></div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-medium text-slate-900" x-text="item.label"></p>
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                                          :class="{
                                                              'bg-green-100 text-green-700': item.status === 'done',
                                                              'bg-red-100 text-red-700': item.status === 'failed',
                                                              'bg-blue-100 text-blue-700': item.status === 'running',
                                                              'bg-slate-100 text-slate-500': item.status === 'pending' || item.status === 'skipped'
                                                          }"
                                                          x-text="item.status"></span>
                                                </div>
                                                <p class="mt-1 text-xs leading-5 text-slate-500" x-text="item.live_detail || item.detail"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div x-show="rowLog({{ $draft->id }}).length > 0" x-cloak class="rounded-lg border border-slate-200 bg-slate-950 px-4 py-4 max-h-72 overflow-y-auto">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Live Activity</p>
                                    <span class="text-[10px] text-slate-500" x-text="rowLog({{ $draft->id }}).length + ' entries'"></span>
                                </div>
                                <template x-for="(entry, idx) in rowLog({{ $draft->id }})" :key="'{{ $draft->id }}-log-' + idx">
                                    <p class="py-1 text-xs font-mono"
                                       :class="{
                                            'text-green-400': entry.type === 'success' || entry.type === 'done',
                                            'text-red-400': entry.type === 'error',
                                            'text-yellow-400': entry.type === 'warning',
                                            'text-blue-400': entry.type === 'info',
                                            'text-slate-400': entry.type === 'step'
                                       }"
                                       x-text="entry.message"></p>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Delete log --}}
            <div x-show="deletingId === {{ $draft->id }} && deleteLog.length > 0" x-cloak class="mt-3 rounded bg-gray-900 p-3">
                <template x-for="(entry, idx) in deleteLog" :key="idx">
                    <p class="text-xs font-mono py-0.5" :class="{'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-gray-400': entry.type === 'step'}" x-text="entry.message"></p>
                </template>
            </div>
        </div>
                @empty
        <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="text-gray-400 text-sm">No articles found.</p>
            <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}" class="inline-flex items-center gap-1.5 mt-3 text-sm text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create your first article
            </a>
        </div>
        @endforelse
    </div>

    <div x-show="approvalEmailOpen" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="approvalEmailOpen = false"></div>
    <aside x-show="approvalEmailOpen" x-cloak class="fixed right-0 top-0 bottom-0 z-50 flex w-full flex-col bg-white shadow-2xl transition-[max-width] duration-200" :class="{ 'max-w-3xl': approvalEmailWidth === 'M', 'max-w-5xl': approvalEmailWidth === 'L', 'max-w-[1280px]': approvalEmailWidth === 'XL' }">
        <div class="flex items-start gap-4 border-b border-gray-200 px-6 py-5">
            {{-- Featured image --}}
            <div class="flex-shrink-0 w-20 h-20 rounded-md bg-gray-100 overflow-hidden">
                <template x-if="approvalEmailArticle?.featured_image_url">
                    <img :src="approvalEmailArticle.featured_image_url" alt="" class="w-full h-full object-cover">
                </template>
                <template x-if="!approvalEmailArticle?.featured_image_url">
                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                </template>
            </div>
            {{-- Title block --}}
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">Draft Approval Email</p>
                <h2 class="mt-1 text-lg font-semibold text-gray-900 break-words" x-text="approvalEmailArticle?.title || 'Draft Approval Email'"></h2>
                <p class="mt-1 text-sm text-gray-500">Send the full draft inline for review, preview the exact email, and inspect the full article-level send log.</p>
            </div>
            <button type="button" @click="cycleApprovalEmailWidth()" class="rounded-lg px-2 py-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 flex-shrink-0 inline-flex items-center gap-1 text-xs font-medium" :title="'Panel width: ' + approvalEmailWidth + ' — click to cycle (M → L → XL)'" aria-label="Cycle panel width">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7L4 12l4 5M16 7l4 5-4 5"/></svg>
                <span x-text="approvalEmailWidth"></span>
            </button>
            <button type="button" @click="approvalEmailOpen = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            @include('app-publish::publishing.articles.partials.draft-approval-email-panel-body')
        </div>
    </aside>

    {{-- Pagination --}}
    @if($drafts->hasPages())
    <div class="flex justify-between items-center text-sm text-gray-500 pt-2">
        <span>Showing {{ $drafts->firstItem() ?? 0 }}&ndash;{{ $drafts->lastItem() ?? 0 }} of {{ $drafts->total() }}</span>
        {{ $drafts->appends(request()->query())->links() }}
    </div>
    @endif
</div>

@push('scripts')
@include('app-publish::publishing.articles.partials.draft-approval-email-script')
<script>
function articlesList() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    const draftWordPressChecklist = @json(collect($wordpressChecklist ?? [])->filter(fn ($item) => in_array($item['key'] ?? '', ['wp_connection', 'wp_html', 'wp_media', 'wp_taxonomies', 'wp_integrity', 'delivery'], true))->values()->all());
    const initialRows = @json($drafts->mapWithKeys(function ($draft) {
        return [
            $draft->id => [
                'wp_post_id' => $draft->wp_post_id ? (int) $draft->wp_post_id : null,
                'wp_status' => (string) ($draft->wp_status ?? ''),
                'wp_post_url' => (string) ($draft->wp_post_url ?? ''),
                'status' => (string) ($draft->status ?? ''),
                'delivery_mode' => (string) ($draft->delivery_mode ?? 'draft-local'),
                'published_at' => optional($draft->published_at)->toIso8601String(),
                'site_url' => (string) ($draft->site?->url ?? ''),
            ],
        ];
    })->all());
    return {
        ...draftApprovalEmailMixin(),
        searchQuery: new URLSearchParams(window.location.search).get('q') || '',
        searching: false,
        totalCount: {{ $drafts->total() }},
        selectedIds: [],
        deletingId: null,
        deleteLog: [],
        bulkDeleting: false,
        approvalEmailWidth: (typeof localStorage !== 'undefined' && localStorage.getItem('approvalEmailWidth')) || 'M',
        previewOpenIds: [],
        rowStates: {},

        buildChecklist() {
            return JSON.parse(JSON.stringify(draftWordPressChecklist)).map((item) => ({
                ...item,
                status: item.status || 'pending',
                live_detail: item.live_detail || '',
            }));
        },

        baseArticleState(id) {
            return {
                id,
                wp_post_id: initialRows[id]?.wp_post_id || null,
                wp_status: initialRows[id]?.wp_status || '',
                wp_post_url: initialRows[id]?.wp_post_url || '',
                status: initialRows[id]?.status || 'drafting',
                delivery_mode: initialRows[id]?.delivery_mode || 'draft-local',
                published_at: initialRows[id]?.published_at || null,
                site_url: initialRows[id]?.site_url || '',
            };
        },

        ensureRowState(id) {
            if (!this.rowStates[id]) {
                this.rowStates[id] = {
                    open: false,
                    running: false,
                    error: false,
                    message: '',
                    phase: '',
                    log: [],
                    checklist: this.buildChecklist(),
                    article: this.baseArticleState(id),
                };
            }
            return this.rowStates[id];
        },

        isPreviewOpen(id) {
            return this.ensureRowState(id).open;
        },

        togglePreview(id) {
            const state = this.ensureRowState(id);
            state.open = !state.open;
        },

        rowRunning(id) {
            return this.ensureRowState(id).running;
        },

        rowError(id) {
            return this.ensureRowState(id).error;
        },

        rowMessage(id) {
            return this.ensureRowState(id).message;
        },

        rowPhase(id) {
            return this.ensureRowState(id).phase;
        },

        rowChecklist(id) {
            return this.ensureRowState(id).checklist;
        },

        rowLog(id) {
            return this.ensureRowState(id).log;
        },

        rowHasWpPost(id) {
            return !!this.ensureRowState(id).article?.wp_post_id;
        },

        rowIsLive(id) {
            const article = this.ensureRowState(id).article || {};
            return article.wp_status === 'publish' || article.status === 'published' || article.status === 'completed' || !!article.published_at;
        },

        rowWpPostLabel(id) {
            const article = this.ensureRowState(id).article || {};
            return article.wp_post_id ? ('#' + article.wp_post_id) : 'No WP post yet';
        },

        rowWpPostUrl(id) {
            return this.ensureRowState(id).article?.wp_post_url || '';
        },

        rowWpAdminUrl(id) {
            const article = this.ensureRowState(id).article || {};
            if (!article.wp_post_id || !article.site_url) return '';
            return article.site_url.replace(/\/$/, '') + '/wp-admin/post.php?post=' + article.wp_post_id + '&action=edit';
        },

        setRowMessage(id, message, isError = false) {
            const state = this.ensureRowState(id);
            state.message = message || '';
            state.error = !!isError;
        },

        setRowPhase(id, phase = '') {
            this.ensureRowState(id).phase = phase;
        },

        clearRowLog(id) {
            this.ensureRowState(id).log = [];
        },

        pushRowLog(id, message, type = 'info') {
            this.ensureRowState(id).log.push({ message, type });
        },

        applyChecklist(id, checklist = []) {
            if (!Array.isArray(checklist) || checklist.length === 0) return;
            this.ensureRowState(id).checklist = checklist.map((item) => ({
                ...item,
                status: item.status || 'pending',
                live_detail: item.live_detail || '',
            }));
        },

        applyArticleState(id, article = {}) {
            if (!article || typeof article !== 'object') return;
            const state = this.ensureRowState(id);
            state.article = {
                ...state.article,
                ...article,
                wp_post_id: article.wp_post_id ?? state.article.wp_post_id ?? null,
                wp_status: article.wp_status ?? state.article.wp_status ?? '',
                wp_post_url: article.wp_post_url ?? state.article.wp_post_url ?? '',
                status: article.status ?? state.article.status ?? 'drafting',
                delivery_mode: article.delivery_mode ?? state.article.delivery_mode ?? 'draft-local',
                published_at: article.published_at ?? state.article.published_at ?? null,
                site_url: article.site?.url ?? article.site_url ?? state.article.site_url ?? '',
            };
        },

        async requestJson(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers,
                ...options,
            });
            const text = await response.text();
            let data = null;
            try {
                data = text ? JSON.parse(text) : {};
            } catch (error) {
                throw new Error(text || ('Unexpected response (' + response.status + ')'));
            }
            if (!response.ok) {
                throw new Error(data?.message || data?.error || ('Request failed (' + response.status + ')'));
            }
            return data;
        },

        resetChecklistToTemplate(id) {
            this.ensureRowState(id).checklist = this.buildChecklist();
        },

        async prepareWordPress(id) {
            const state = this.ensureRowState(id);
            state.open = true;
            state.running = true;
            this.setRowMessage(id, '');
            this.setRowPhase(id, 'Preparing');
            this.clearRowLog(id);
            this.resetChecklistToTemplate(id);
            this.pushRowLog(id, 'Starting WordPress preparation…', 'step');

            try {
                const data = await this.requestJson(`/article/articles/${id}/prepare-wordpress`, { method: 'POST' });
                this.applyChecklist(id, data.checklist || []);
                this.applyArticleState(id, data.article || {});
                (data.steps || []).forEach((entry) => this.pushRowLog(id, entry.message || entry.details || 'Step complete', entry.type || 'info'));
                this.setRowMessage(id, data.message || 'Draft prepared for WordPress.');
                this.pushRowLog(id, data.message || 'Draft prepared for WordPress.', 'success');
                return data;
            } catch (error) {
                this.setRowMessage(id, error.message || 'WordPress preparation failed.', true);
                this.pushRowLog(id, error.message || 'WordPress preparation failed.', 'error');
                return null;
            } finally {
                state.running = false;
                this.setRowPhase(id, '');
            }
        },

        async prepareAndCreateWpDraft(id) {
            const prepared = await this.prepareWordPress(id);
            if (!prepared?.success) return null;
            return this.publishExistingDraft(id, 'draft', 'Creating Draft');
        },

        async publishExistingDraft(id, status = 'publish', phaseLabel = null) {
            const state = this.ensureRowState(id);
            state.open = true;
            state.running = true;
            this.setRowMessage(id, '');
            this.setRowPhase(id, phaseLabel || (status === 'draft' ? 'Creating Draft' : (this.rowIsLive(id) ? 'Updating Live Post' : 'Publishing Draft')));
            this.pushRowLog(id, status === 'draft' ? 'Sending prepared draft to WordPress…' : 'Publishing WordPress article…', 'step');

            try {
                const data = await this.requestJson(`/publish/articles/${id}/publish`, {
                    method: 'POST',
                    body: JSON.stringify({ status }),
                });
                this.applyArticleState(id, data.article || {});
                this.setRowMessage(id, data.message || 'WordPress delivery complete.');
                this.pushRowLog(id, data.message || 'WordPress delivery complete.', 'success');
                return data;
            } catch (error) {
                this.setRowMessage(id, error.message || 'WordPress publish failed.', true);
                this.pushRowLog(id, error.message || 'WordPress publish failed.', 'error');
                return null;
            } finally {
                state.running = false;
                this.setRowPhase(id, '');
            }
        },

        async refreshWpStatus(id) {
            const state = this.ensureRowState(id);
            state.running = true;
            this.setRowMessage(id, '');
            this.setRowPhase(id, 'Refreshing');
            this.clearRowLog(id);
            this.pushRowLog(id, 'Refreshing WordPress state…', 'step');

            try {
                const data = await this.requestJson(`/publish/articles/${id}/refresh-wp`, { method: 'POST' });
                this.applyArticleState(id, data.article || {});
                (data.steps || []).forEach((entry) => this.pushRowLog(id, entry.detail ? `${entry.label}: ${entry.detail}` : entry.label, entry.status === 'error' ? 'error' : (entry.status === 'ok' ? 'success' : 'info')));
                this.setRowMessage(id, data.message || 'WordPress state refreshed.', !data.success);
                return data;
            } catch (error) {
                this.setRowMessage(id, error.message || 'Failed to refresh WordPress status.', true);
                this.pushRowLog(id, error.message || 'Failed to refresh WordPress status.', 'error');
                return null;
            } finally {
                state.running = false;
                this.setRowPhase(id, '');
            }
        },

        cycleApprovalEmailWidth() {
            const order = ['M', 'L', 'XL'];
            const next = order[(order.indexOf(this.approvalEmailWidth) + 1) % order.length];
            this.approvalEmailWidth = next;
            try { localStorage.setItem('approvalEmailWidth', next); } catch (e) {}
        },

        init() {
            Object.keys(initialRows).forEach((id) => this.ensureRowState(Number(id)));
            const params = new URLSearchParams(window.location.search);
            const emailParam = params.get('email');
            if (emailParam) {
                const idNum = parseInt(emailParam, 10);
                if (!isNaN(idNum) && idNum > 0) {
                    this.openApprovalEmail(idNum);
                }
            }
            this.$watch('approvalEmailOpen', (open) => {
                if (!open) this.syncEmailQueryParam(null);
            });
        },

        search() {
            this.searching = true;
            const params = new URLSearchParams(window.location.search);
            if (this.searchQuery) params.set('q', this.searchQuery); else params.delete('q');
            params.delete('page');
            window.location.href = '{{ route("publish.drafts.index") }}?' + params.toString();
        },

        filterStatus(val) {
            const params = new URLSearchParams(window.location.search);
            if (val) params.set('status', val); else params.delete('status');
            params.delete('page');
            window.location.href = '{{ route("publish.drafts.index") }}?' + params.toString();
        },

        toggleAll(checked) {
            if (checked) {
                this.selectedIds = [{{ $drafts->pluck('id')->join(',') }}];
            } else {
                this.selectedIds = [];
            }
        },

        toggleSelect(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) this.selectedIds.push(id); else this.selectedIds.splice(idx, 1);
        },

        async openApprovalEmail(id) {
            this.approvalEmailTargetId = id;
            this.approvalEmailOpen = true;
            this.syncEmailQueryParam(id);
            await this.approvalEmailLoad(id, { preserveFilled: false, open: false });
        },

        syncEmailQueryParam(id) {
            try {
                const url = new URL(window.location.href);
                if (id) url.searchParams.set('email', id); else url.searchParams.delete('email');
                window.history.replaceState({}, '', url.toString());
            } catch (e) {}
        },

        async deleteSingle(id) {
            if (!confirm('Delete this article? Published articles will also be removed from WordPress.')) return;
            this.deletingId = id;
            this.deleteLog = [{ type: 'step', message: 'Deleting...' }];
            try {
                const r = await fetch('/article/articles/' + id, { method: 'DELETE', headers });
                const d = await r.json();
                this.deleteLog = d.log || [{ type: d.success ? 'success' : 'error', message: d.message }];
                if (d.success) {
                    setTimeout(() => {
                        const row = document.getElementById('row-' + id);
                        if (row) { row.style.opacity = '0'; row.style.transition = 'opacity 0.3s'; setTimeout(() => row.remove(), 300); }
                        this.deletingId = null;
                        this.deleteLog = [];
                        this.totalCount = Math.max(0, this.totalCount - 1);
                    }, 1500);
                }
            } catch(e) {
                this.deleteLog = [{ type: 'error', message: 'Network error: ' + e.message }];
            }
        },

        async bulkDelete() {
            if (!confirm('Delete ' + this.selectedIds.length + ' article(s)? Published articles will also be removed from WordPress.')) return;
            this.bulkDeleting = true;
            try {
                const r = await fetch('{{ route("publish.drafts.bulk-destroy") }}', { method: 'POST', headers, body: JSON.stringify({ ids: this.selectedIds }) });
                const d = await r.json();
                if (d.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            } catch(e) { alert('Error: ' + e.message); }
            this.bulkDeleting = false;
        },
    };
}
</script>
@endpush
@endsection
