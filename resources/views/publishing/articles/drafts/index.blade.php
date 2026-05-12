{{-- All Articles --}}
@extends('layouts.app')
@section('title', 'Articles')
@section('header', 'Articles')

@section('content')
<div class="space-y-4" x-data="articlesList()" @article-count-change.window="totalCount = Math.max(0, totalCount + Number(($event.detail && $event.detail.delta) || 0))" @toggle-select.window="toggleSelect($event.detail.id)" @open-approval-email="openApprovalEmail($event.detail.id)">

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
            <a href="{{ route('publish.pipeline.v2', ['spawn' => 1]) }}" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Article
            </a>
        </div>
    </div>

    {{-- Article List --}}
    <div class="space-y-2">
        @forelse($drafts as $draft)
        @php
            $thumb = null;
            if ($draft->wp_images && is_array($draft->wp_images)) {
                $featured = collect($draft->wp_images)->firstWhere('is_featured', true);
                if (!$featured) $featured = collect($draft->wp_images)->first();
                if ($featured && is_array($featured)) {
                    $thumb = $featured['sizes']['thumbnail'] ?? $featured['sizes']['medium'] ?? $featured['media_url'] ?? null;
                }
            }
            if (!$thumb && $draft->photos && is_array($draft->photos)) {
                $firstPhoto = collect($draft->photos)->first();
                if (is_array($firstPhoto)) {
                    $thumb = $firstPhoto['sizes']['thumbnail'] ?? $firstPhoto['url'] ?? $firstPhoto['src'] ?? $firstPhoto['thumb'] ?? null;
                } elseif (is_string($firstPhoto)) {
                    $thumb = $firstPhoto;
                }
            }

            $sourceUrl = null;
            $sourceName = null;
            if ($draft->source_articles && is_array($draft->source_articles)) {
                $firstSource = collect($draft->source_articles)->first();
                if (is_array($firstSource)) {
                    $sourceUrl = $firstSource['url'] ?? $firstSource['link'] ?? null;
                    $sourceName = $firstSource['title'] ?? null;
                }
            }

            $site = $draft->site;
            $siteUrl = $site ? rtrim((string) ($site->url ?? ''), '/') : '';
            $wpAdminUrl = ($siteUrl && $draft->wp_post_id)
                ? $siteUrl . '/wp-admin/post.php?post=' . $draft->wp_post_id . '&action=edit'
                : null;

            $statePayload = is_array(optional($draft->pipelineState)->payload) ? $draft->pipelineState->payload : [];
            $bodySource = (string) ($statePayload['editorContent'] ?? $statePayload['spunContent'] ?? $draft->body ?? '');
            $bodyHtmlPreview = trim($bodySource);
            if ($bodyHtmlPreview !== '' && strip_tags($bodyHtmlPreview) === $bodyHtmlPreview) {
                $bodyHtmlPreview = nl2br(e($bodyHtmlPreview));
            }
            $bodyPreviewText = trim(preg_replace('/\s+/', ' ', strip_tags($bodySource)));
            $excerptPreview = trim((string) ($draft->excerpt ?? ($statePayload['articleDescription'] ?? '')));

            $activePhotos = collect((array) ($statePayload['photoSuggestions'] ?? $draft->photo_suggestions ?? []))
                ->filter(fn ($item) => is_array($item) && empty($item['removed']) && !empty($item['autoPhoto']))
                ->values()
                ->map(function ($item, $index) {
                    return [
                        'label' => trim((string) ($item['search_term'] ?? ('image ' . ($index + 1)))),
                        'filename' => trim((string) ($item['suggestedFilename'] ?? '')),
                    ];
                })
                ->all();

            $featuredSearch = trim((string) ($statePayload['featuredImageSearch'] ?? ''));
            $hasFeaturedSelection = !empty($statePayload['featuredPhoto']);
            $operationSnapshots = $draftOperationSnapshots[$draft->id] ?? [];
            $wpStatus = strtolower(trim((string) ($draft->wp_status ?? '')));
            $deliveryState = $draft->wp_post_id
                ? ($wpStatus === 'publish' ? 'live' : 'wp_draft')
                : 'local';
            $deliveryLabel = $deliveryState === 'live'
                ? 'Live'
                : ($deliveryState === 'wp_draft' ? 'WP Draft' : 'Local Draft');
            $deliveryTone = $deliveryState === 'live'
                ? 'bg-green-50 text-green-700 border-green-200'
                : ($deliveryState === 'wp_draft' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-gray-100 text-gray-500 border-gray-200');

            $rowPayload = [
                'id' => $draft->id,
                'title' => $draft->title ?: 'Untitled',
                'articleId' => $draft->article_id,
                'author' => $draft->author ?: '',
                'articleType' => (string) ($draft->article_type ?? ''),
                'status' => (string) ($draft->status ?? ''),
                'deliveryMode' => (string) ($draft->delivery_mode ?? ''),
                'siteName' => (string) ($site->name ?? ''),
                'siteUrl' => $siteUrl,
                'wpPostId' => (int) ($draft->wp_post_id ?? 0),
                'wpStatus' => (string) ($draft->wp_status ?? ''),
                'wpPostUrl' => (string) ($draft->wp_post_url ?? ''),
                'wpAdminUrl' => $wpAdminUrl,
                'publishedAt' => optional($draft->published_at)->toIso8601String(),
                'updatedAtHuman' => $draft->updated_at ? $draft->updated_at->diffForHumans() : ($draft->created_at ? $draft->created_at->diffForHumans() : ''),
                'wordCount' => (int) ($draft->word_count ?? 0),
                'categories' => array_values(array_filter((array) ($draft->categories ?? []))),
                'tags' => array_values(array_filter((array) ($draft->tags ?? []))),
                'excerpt' => $excerptPreview,
                'bodyHtml' => $bodyHtmlPreview,
                'bodyPreviewText' => $bodyPreviewText,
                'sourceUrl' => $sourceUrl,
                'sourceName' => $sourceName,
                'photoChecklist' => $activePhotos,
                'featuredSearch' => $featuredSearch,
                'hasFeaturedSelection' => $hasFeaturedSelection,
                'prepareUrl' => route('publish.drafts.prepare', $draft->id),
                'approveUrl' => route('publish.drafts.approve', $draft->id),
                'deleteUrl' => route('publish.drafts.destroy', $draft->id),
                'pipelineUrl' => route('publish.pipeline.v2', ['id' => $draft->id]),
                'operationSnapshots' => $operationSnapshots,
            ];
        @endphp
        <div
            id="row-{{ $draft->id }}"
            x-data="articleCard(@js($rowPayload))"
            class="v3-row bg-white rounded-xl border border-gray-200 hover:border-gray-300 hover:shadow-md transition-all p-5"
            :class="{
                '!border-red-300 !bg-red-50': removing,
                '!border-blue-200 !shadow-md': expanded,
            }"
        >
            <div class="flex items-start gap-5">
                <div class="pt-2 flex-shrink-0">
                    <input type="checkbox" value="{{ $draft->id }}" @change="$dispatch('toggle-select', { id: {{ $draft->id }} })" class="rounded border-gray-300 text-blue-600">
                </div>

                <div class="flex-shrink-0 w-48 h-32 rounded-lg bg-gray-100 overflow-hidden mt-0.5 shadow-sm">
                    @if($thumb)
                        <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-300\'><svg class=\'w-9 h-9\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    @endif
                </div>

                <div class="flex-1 min-w-0 flex flex-col gap-2">
                    {{-- Title row: title (left) + action icons (right, aligned to baseline of title) --}}
                    <div class="flex items-start justify-between gap-4">
                        <h3 class="flex-1 min-w-0 text-xl font-bold text-gray-900 break-words leading-tight">
                            <a :href="pipelineUrl" class="hover:text-blue-600" x-text="title || 'Untitled'"></a>
                        </h3>
                        <div class="flex-shrink-0 flex items-center gap-0.5 -mt-0.5">
                            <button @click="togglePreview()" class="p-2 rounded-md text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" :title="expanded ? 'Hide preview' : 'Expand to preview + push live'">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path x-show="!expanded" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/><path x-show="expanded" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            </button>
                            <button @click="$dispatch('open-approval-email', { id })" class="p-2 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Draft approval email">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8m-16 8h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            </button>
                            <a :href="pipelineUrl" class="p-2 rounded-md text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Open in full editor">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <button @click="denyArticle()" :disabled="removing || preparing || approving" class="p-2 rounded-md text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors disabled:opacity-50" title="Deny & delete">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Author · Site --}}
                    <p class="text-sm text-gray-500" x-show="author || siteName">
                        <template x-if="author">
                            <span>
                                By
                                <template x-if="siteUrl && author">
                                    <a :href="(siteUrl || '').replace(/\/+$/, '') + '/author/' + encodeURIComponent(author) + '/'" target="_blank" rel="noopener" class="font-medium text-gray-700 hover:text-blue-600 inline-flex items-center gap-1">
                                        <span x-text="author"></span>
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </template>
                                <template x-if="!siteUrl && author">
                                    <span class="font-medium text-gray-700" x-text="author"></span>
                                </template>
                            </span>
                        </template>
                        <template x-if="author && siteName"><span class="text-gray-300 mx-2">·</span></template>
                        <template x-if="siteName"><span class="text-gray-600" x-text="siteName"></span></template>
                    </p>

                    {{-- Meta + chips line --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[11px] text-gray-500">
                        <span class="font-mono text-gray-400">{{ $draft->article_id }}</span>
                        @if($draft->word_count)
                            <span class="text-gray-300">·</span>
                            <span>{{ number_format($draft->word_count) }} words</span>
                        @endif
                        @if($draft->creator)
                            <span class="text-gray-300">·</span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $draft->creator->name }}
                            </span>
                        @endif
                        <span class="text-gray-300">·</span>
                        <span title="{{ $draft->updated_at ?? $draft->created_at }}">{{ $draft->updated_at ? $draft->updated_at->diffForHumans() : ($draft->created_at ? $draft->created_at->diffForHumans() : '') }}</span>
                        @if($draft->wp_post_id)
                            <span class="text-gray-300">·</span>
                            <span>WP #{{ $draft->wp_post_id }}</span>
                        @endif
                        @if($draft->published_at)
                            <span class="text-gray-300">·</span>
                            <span class="text-green-600 font-medium">Published {{ $draft->published_at->format('M j, Y g:ia') }}</span>
                        @endif

                        {{-- Push chips to the right --}}
                        <div class="ml-auto flex items-center gap-1.5">
                            @if($draft->article_type)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 text-indigo-600 border border-indigo-100">{{ ucfirst(str_replace('_', ' ', $draft->article_type)) }}</span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border" :class="deliveryToneClass()" x-text="currentStatePillLabel()"></span>
                        </div>
                    </div>

                    {{-- Live link / WP Admin (only when applicable, on the collapsed row) --}}
                    <div class="flex flex-wrap items-center gap-3 text-[11px]" x-show="(wpPostUrl && isLive()) || wpAdminUrl" x-cloak>
                        <template x-if="wpPostUrl && isLive()">
                            <a :href="wpPostUrl" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                Live link
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </template>
                        <template x-if="wpAdminUrl">
                            <a :href="wpAdminUrl" target="_blank" rel="noopener" class="text-gray-500 hover:text-gray-700 inline-flex items-center gap-1">
                                WP Admin
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="expanded" x-cloak class="v3-expanded mt-6 border-t border-gray-100 pt-6 space-y-6">

                {{-- Status banners --}}
                <div x-show="notice" x-cloak class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800" x-text="notice"></div>
                <div x-show="prepareError || approveError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 whitespace-pre-line" x-text="prepareError || approveError"></div>

                {{-- Article preview --}}
                <article class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <header class="px-6 py-5 border-b border-gray-100 bg-gradient-to-b from-gray-50 to-white">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-400 mb-1.5">Article Preview</p>
                        <h2 class="text-2xl font-bold text-gray-900 leading-tight break-words" x-text="title || 'Untitled'"></h2>
                        <p x-show="excerpt" x-cloak class="mt-2.5 text-base text-gray-600 italic leading-relaxed" x-text="excerpt"></p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-500">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border" :class="deliveryToneClass()" x-text="currentStateLabel()"></span>
                            </span>
                            <span x-show="wordCount" x-cloak x-text="(wordCount || 0).toLocaleString() + ' words'"></span>
                            <span x-show="updatedAtHuman" x-cloak class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-text="'Updated ' + updatedAtHuman"></span>
                            </span>
                        </div>
                    </header>
                    <div class="px-6 py-7">
                        <div x-show="bodyHtml" x-cloak class="prose prose-base prose-gray max-w-none text-gray-800" x-html="bodyHtml"></div>
                        <p x-show="!bodyHtml" x-cloak class="text-gray-400 italic text-sm">No saved article body yet — open the full editor to add content.</p>
                    </div>
                </article>

                {{-- Metadata grid: Target Site / Categories / Tags --}}
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-gray-400 mb-2">Target Site</p>
                        <p class="text-base font-semibold text-gray-900 break-words" x-text="siteName || '—'"></p>
                        <dl class="mt-3 space-y-1 text-xs">
                            <div x-show="deliveryMode" x-cloak class="flex justify-between gap-3">
                                <dt class="text-gray-400">Delivery</dt>
                                <dd class="font-medium text-gray-700" x-text="deliveryMode"></dd>
                            </div>
                            <div x-show="author" x-cloak class="flex justify-between gap-3">
                                <dt class="text-gray-400">Author</dt>
                                <dd class="font-medium text-gray-700">
                                    <template x-if="siteUrl && author">
                                        <a :href="(siteUrl || '').replace(/\/+$/, '') + '/author/' + encodeURIComponent(author) + '/'" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                            <span x-text="author"></span>
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <template x-if="!(siteUrl && author)">
                                        <span x-text="author"></span>
                                    </template>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-gray-400 mb-2">Categories</p>
                        <div x-show="categories.length > 0" x-cloak class="flex flex-wrap gap-1.5">
                            <template x-for="category in categories" :key="category">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-slate-100 text-slate-700 border border-slate-200" x-text="category"></span>
                            </template>
                        </div>
                        <p x-show="categories.length === 0" x-cloak class="text-xs text-gray-400 italic mt-1">None set</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-gray-400 mb-2">Tags</p>
                        <div x-show="tags.length > 0" x-cloak class="flex flex-wrap gap-1.5">
                            <template x-for="tag in tags" :key="tag">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-indigo-50 text-indigo-700 border border-indigo-100" x-text="tag"></span>
                            </template>
                        </div>
                        <p x-show="tags.length === 0" x-cloak class="text-xs text-gray-400 italic mt-1">None set</p>
                    </div>
                </div>

                {{-- Source + WordPress info row — properly spaced --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-4">
                    <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3 text-xs">
                        <template x-if="sourceName || sourceUrl">
                            <div class="flex flex-col gap-0.5 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Source</p>
                                <template x-if="sourceUrl">
                                    <a :href="sourceUrl" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1 truncate">
                                        <span class="truncate" x-text="sourceName || sourceUrl"></span>
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </template>
                                <template x-if="!sourceUrl">
                                    <span class="text-gray-700" x-text="sourceName"></span>
                                </template>
                            </div>
                        </template>
                        <template x-if="wpPostId">
                            <div class="flex flex-col gap-0.5 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">WordPress</p>
                                <div class="inline-flex items-center gap-2.5 text-gray-700">
                                    <span class="font-mono" x-text="'#' + wpPostId"></span>
                                    <template x-if="wpAdminUrl">
                                        <a :href="wpAdminUrl" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                            wp-admin
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="wpPostUrl && isLive()">
                            <div class="flex flex-col gap-0.5 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Live URL</p>
                                <a :href="wpPostUrl" target="_blank" rel="noopener" class="text-green-700 hover:text-green-900 inline-flex items-center gap-1 truncate">
                                    <span class="truncate" x-text="wpPostUrl"></span>
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Publish status (collapsed unless an op is running) --}}
                <div x-show="prepareOperation || publishOperation || prepareChecklist.some(c => c.status === 'running' || c.status === 'done' || c.status === 'failed')" x-cloak class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <details>
                        <summary class="cursor-pointer px-5 py-3 flex flex-wrap items-center justify-between gap-3 select-none hover:bg-gray-50">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900">Publish status</p>
                                <p class="text-[11px] text-gray-500 truncate" x-text="publishStatusLine()"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-32 bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-green-500 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + checklistPercent() + '%'"></div>
                                </div>
                                <span class="text-[11px] text-gray-500 font-medium tabular-nums" x-text="checklistPercent() + '%'"></span>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </summary>
                        <div class="border-t border-gray-100 px-5 py-4 space-y-4 bg-gray-50/40">
                            <div x-show="prepareChecklist.length > 0" x-cloak class="space-y-1">
                                <template x-for="item in prepareChecklist" :key="item.type + '-' + item.label">
                                    @include('app-publish::publishing.pipeline.partials.checklist-item')
                                </template>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-[11px] text-gray-500 space-y-1">
                                    <p class="font-semibold text-gray-700">Prepare</p>
                                    <p x-text="prepareStatusLine()"></p>
                                    <p x-show="prepareOperation && prepareOperation.trace_id" x-cloak class="font-mono break-all text-gray-400" x-text="prepareOperation ? prepareOperation.trace_id : ''"></p>
                                </div>
                                <div class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-[11px] text-gray-500 space-y-1">
                                    <p class="font-semibold text-gray-700">Publish</p>
                                    <p x-text="publishStatusLine()"></p>
                                    <p x-show="publishOperation && publishOperation.trace_id" x-cloak class="font-mono break-all text-gray-400" x-text="publishOperation ? publishOperation.trace_id : ''"></p>
                                </div>
                            </div>
                            <details x-show="activityLog.length > 0" x-cloak class="rounded-lg border border-gray-700 bg-gray-900 overflow-hidden">
                                <summary class="cursor-pointer px-3 py-2 flex items-center justify-between gap-3 text-[11px] font-semibold uppercase tracking-wider text-gray-300 hover:bg-gray-800 select-none">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Live operation log
                                    </span>
                                    <button @click.prevent.stop="activityLog = []" class="text-[10px] text-gray-500 hover:text-gray-300 normal-case font-medium tracking-normal">Clear</button>
                                </summary>
                                <div class="px-3 py-2 space-y-0.5 max-h-72 overflow-auto">
                                    <template x-for="entry in activityLog" :key="entry.key">
                                        <p class="text-[11px] font-mono break-words leading-relaxed" :class="{
                                            'text-green-400': entry.type === 'success',
                                            'text-red-400': entry.type === 'error',
                                            'text-yellow-300': entry.type === 'warning',
                                            'text-blue-300': entry.type === 'info',
                                            'text-gray-400': !['success','error','warning','info'].includes(entry.type)
                                        }" x-text="entry.text"></p>
                                    </template>
                                </div>
                            </details>
                        </div>
                    </details>
                </div>

                {{-- Action bar — sticky at the bottom of the expanded panel --}}
                <div class="sticky bottom-2 z-10 rounded-xl border border-gray-200 bg-white shadow-lg px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-[11px] text-gray-500 flex-1 min-w-0">
                        <span class="font-semibold text-gray-700">Ready to act?</span>
                        <span class="hidden sm:inline ml-1" x-text="isLive() ? 'Article is live on WordPress.' : (isWpDraft() ? 'WordPress draft is ready — approve to publish live.' : 'Run prepare first, then approve to publish.')"></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="prepareArticle()" :disabled="preparing || approving || removing" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-blue-200 text-blue-700 hover:bg-blue-50 text-sm font-medium disabled:opacity-50">
                            <svg x-show="preparing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg x-show="!preparing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="preparing ? 'Preparing…' : prepareButtonLabel()"></span>
                        </button>
                        <button @click="approveArticle()" :disabled="approving || preparing || removing || isLive()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm font-semibold disabled:opacity-50 shadow-sm">
                            <svg x-show="approving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg x-show="!approving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            <span x-text="approving ? 'Pushing…' : approveButtonLabel()"></span>
                        </button>
                        <a :href="pipelineUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium">
                            Open Full Editor
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                </div>

            </div>
        </div>
        @empty
        <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="text-gray-400 text-sm">No articles found.</p>
            <a href="{{ route('publish.pipeline.v2', ['spawn' => 1]) }}" class="inline-flex items-center gap-1.5 mt-3 text-sm text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create your first article
            </a>
        </div>
        @endforelse
    </div>

    <div x-show="approvalEmailOpen" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="approvalEmailOpen = false"></div>
    <aside x-show="approvalEmailOpen" x-cloak class="fixed right-0 top-0 bottom-0 z-50 flex w-full flex-col bg-white shadow-2xl transition-[max-width] duration-200" :class="{ 'max-w-3xl': approvalEmailWidth === 'M', 'max-w-5xl': approvalEmailWidth === 'L', 'max-w-[1280px]': approvalEmailWidth === 'XL' }">
        <div class="flex items-start gap-4 border-b border-gray-200 px-6 py-5">
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
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        ...draftApprovalEmailMixin(),
        searchQuery: new URLSearchParams(window.location.search).get('q') || '',
        searching: false,
        totalCount: {{ $drafts->total() }},
        selectedIds: [],
        bulkDeleting: false,
        approvalEmailWidth: (typeof localStorage !== 'undefined' && localStorage.getItem('approvalEmailWidth')) || 'M',

        init() {
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

        cycleApprovalEmailWidth() {
            const order = ['M', 'L', 'XL'];
            const next = order[(order.indexOf(this.approvalEmailWidth) + 1) % order.length];
            this.approvalEmailWidth = next;
            try { localStorage.setItem('approvalEmailWidth', next); } catch (e) {}
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

        async bulkDelete() {
            if (!confirm('Delete ' + this.selectedIds.length + ' article(s)? Published articles will also be removed from WordPress.')) return;
            this.bulkDeleting = true;
            try {
                const r = await fetch('{{ route("publish.drafts.bulk-destroy") }}', { method: 'POST', headers, body: JSON.stringify({ ids: this.selectedIds }) });
                const d = await r.json();
                if (d.success) {
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert(d.message || 'Bulk delete failed.');
                }
            } catch(e) {
                alert('Error: ' + e.message);
            }
            this.bulkDeleting = false;
        },
    };
}

function articleCard(config = {}) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';
    const jsonHeaders = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

    return {
        ...config,
        expanded: false,
        removing: false,
        preparing: false,
        approving: false,
        notice: '',
        prepareError: '',
        approveError: '',
        prepareChecklist: [],
        activityLog: [],
        prepareOperation: (config.operationSnapshots && config.operationSnapshots.prepare) || null,
        publishOperation: (config.operationSnapshots && config.operationSnapshots.publish) || null,
        prepareSequence: 0,
        publishSequence: 0,
        preparePollTimer: null,
        publishPollTimer: null,
        autoApproveAfterPrepare: false,
        pendingTargetStatus: '',

        init() {
            this.seedPrepareChecklist();

            if (this.prepareOperation) {
                if (['queued', 'running'].includes(String(this.prepareOperation.status || '').toLowerCase())) {
                    this.startPolling('prepare', this.prepareOperation.show_url);
                } else {
                    this.applyPrepareOperation(this.prepareOperation);
                }
            }

            if (this.publishOperation) {
                if (['queued', 'running'].includes(String(this.publishOperation.status || '').toLowerCase())) {
                    this.startPolling('publish', this.publishOperation.show_url);
                } else {
                    this.applyPublishOperation(this.publishOperation);
                }
            }
        },

        isLive() {
            return String(this.wpStatus || '').toLowerCase() === 'publish';
        },

        isWpDraft() {
            return !!this.wpPostId && !this.isLive();
        },

        currentStatePillLabel() {
            if (this.isLive()) return 'Live';
            if (this.isWpDraft()) return 'WP Draft';
            return 'Local Draft';
        },

        deliveryToneClass() {
            if (this.isLive()) return 'bg-green-50 text-green-700 border-green-200';
            if (this.isWpDraft()) return 'bg-blue-50 text-blue-700 border-blue-200';
            return 'bg-gray-100 text-gray-500 border-gray-200';
        },

        currentStateLabel() {
            if (this.isLive()) return 'Live on WordPress';
            if (this.isWpDraft()) return 'Saved as WordPress draft';
            return 'Local draft only';
        },

        prepareButtonLabel() {
            if (this.isLive()) return 'Re-prepare Live Post';
            if (this.isWpDraft()) return 'Re-prepare WP Draft';
            return 'Prepare for WordPress';
        },

        approveButtonLabel() {
            if (this.isLive()) return 'Already Live';
            return this.isWpDraft() ? 'Approve & Publish Live' : 'Approve & Create WP Draft';
        },

        prepareStatusLine() {
            if (!this.prepareOperation) return 'Not prepared yet.';
            const status = String(this.prepareOperation.status || 'unknown');
            const stage = [this.prepareOperation.last_stage, this.prepareOperation.last_substage].filter(Boolean).join('/');
            const message = this.prepareOperation.error_message || this.prepareOperation.last_message || '';
            return [status, stage, message].filter(Boolean).join(' · ');
        },

        publishStatusLine() {
            if (!this.publishOperation) return this.isLive() ? 'Published live.' : (this.isWpDraft() ? 'WordPress draft ready.' : 'No WordPress publish action has run yet.');
            const status = String(this.publishOperation.status || 'unknown');
            const stage = [this.publishOperation.last_stage, this.publishOperation.last_substage].filter(Boolean).join('/');
            const message = this.publishOperation.error_message || this.publishOperation.last_message || '';
            return [status, stage, message].filter(Boolean).join(' · ');
        },

        checklistPercent() {
            if (!this.prepareChecklist.length) return 0;
            return Math.round((this.prepareChecklist.filter(c => c.status === 'done').length / this.prepareChecklist.length) * 100);
        },

        togglePreview() {
            this.expanded = !this.expanded;
        },

        seedPrepareChecklist() {
            if (this.prepareChecklist.length) return;

            const photoItems = Array.isArray(this.photoChecklist) ? this.photoChecklist.map((photo, index) => ({
                label: 'Photo ' + (index + 1) + ': ' + (photo.label || ('image ' + (index + 1))),
                status: 'pending',
                detail: photo.filename || '',
                type: 'photo',
            })) : [];

            const featuredItem = {
                label: 'Featured: ' + (this.featuredSearch || 'image'),
                status: this.hasFeaturedSelection ? 'pending' : 'skipped',
                detail: this.hasFeaturedSelection ? '' : 'none selected',
                type: 'featured',
            };

            const categoryItems = (this.categories || []).map((category) => ({
                label: category,
                status: 'pending',
                detail: '',
                type: 'category',
            }));

            const tagItems = (this.tags || []).map((tag) => ({
                label: tag,
                status: 'pending',
                detail: '',
                type: 'tag',
            }));

            this.prepareChecklist = [
                { label: 'Connect to WordPress & author', status: 'pending', detail: this.author || 'default author', type: 'auth' },
                { label: 'Clean HTML for WordPress', status: 'pending', detail: 'waiting to sanitize html...', type: 'html' },
                ...photoItems,
                featuredItem,
                ...categoryItems,
                ...tagItems,
                { label: 'Integrity check', status: 'pending', detail: '', type: 'integrity' },
            ];
        },

        async prepareArticle() {
            if (this.preparing || this.approving || this.removing) return;
            this.notice = '';
            this.prepareError = '';
            this.approveError = '';
            this.preparing = true;
            this.expanded = true;
            this.seedPrepareChecklist();

            try {
                const response = await fetch(this.prepareUrl, { method: 'POST', headers: jsonHeaders, body: JSON.stringify({}) });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    this.prepareError = this.formatFailure(response.status, payload);
                    return;
                }

                this.notice = payload.message || 'Prepare started.';
                this.pendingTargetStatus = payload.target_status || this.pendingTargetStatus;
                if (payload.operation) {
                    this.prepareOperation = payload.operation;
                    this.startPolling('prepare', payload.operation.show_url);
                }
            } catch (error) {
                this.prepareError = 'Network error: ' + (error.message || 'unknown');
            } finally {
                this.preparing = false;
            }
        },

        async approveArticle() {
            if (this.approving || this.preparing || this.removing || this.isLive()) return;
            this.notice = '';
            this.prepareError = '';
            this.approveError = '';
            this.approving = true;
            this.expanded = true;
            this.seedPrepareChecklist();

            try {
                const response = await fetch(this.approveUrl, { method: 'POST', headers: jsonHeaders, body: JSON.stringify({}) });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    this.approveError = this.formatFailure(response.status, payload);
                    return;
                }

                this.notice = payload.message || 'Approval started.';
                this.pendingTargetStatus = payload.target_status || this.pendingTargetStatus;
                if (payload.requires_prepare) {
                    this.autoApproveAfterPrepare = true;
                }
                if (payload.operation) {
                    if (String(payload.operation.operation_type || '') === 'prepare') {
                        this.prepareOperation = payload.operation;
                        this.startPolling('prepare', payload.operation.show_url);
                    } else {
                        this.publishOperation = payload.operation;
                        this.startPolling('publish', payload.operation.show_url);
                    }
                }
            } catch (error) {
                this.approveError = 'Network error: ' + (error.message || 'unknown');
            } finally {
                this.approving = false;
            }
        },

        async denyArticle() {
            if (this.removing || this.preparing || this.approving) return;
            if (!confirm('Deny and delete this article? Any linked WordPress draft/post will be removed too.')) return;

            this.removing = true;
            this.notice = '';
            this.prepareError = '';
            this.approveError = '';

            try {
                const response = await fetch(this.deleteUrl, { method: 'DELETE', headers: jsonHeaders });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    this.approveError = this.formatFailure(response.status, payload);
                    this.removing = false;
                    return;
                }

                this.notice = payload.message || 'Article denied and removed.';
                window.dispatchEvent(new CustomEvent('article-count-change', { detail: { delta: -1 } }));
                setTimeout(() => {
                    const row = document.getElementById('row-' + this.id);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transition = 'opacity 0.25s ease';
                        setTimeout(() => row.remove(), 260);
                    }
                }, 250);
            } catch (error) {
                this.approveError = 'Network error: ' + (error.message || 'unknown');
                this.removing = false;
            }
        },

        formatFailure(httpStatus, payload = {}) {
            const pieces = [];
            if (payload.message) pieces.push(payload.message);
            if (payload.code) pieces.push('Code: ' + payload.code);
            if (httpStatus) pieces.push('HTTP ' + httpStatus);
            return pieces.length ? pieces.join('\n') : 'Request failed.';
        },

        queueLogEntry(type, message, extra = '') {
            const text = [message, extra].filter(Boolean).join(' — ');
            this.activityLog.unshift({
                key: String(Date.now()) + '-' + Math.random().toString(16).slice(2),
                type: type || 'info',
                text: text,
            });
            this.activityLog = this.activityLog.slice(0, 30);
        },

        startPolling(type, showUrl) {
            if (!showUrl) return;
            if (type === 'prepare') {
                clearTimeout(this.preparePollTimer);
            } else {
                clearTimeout(this.publishPollTimer);
            }

            const tick = async () => {
                const afterSequence = type === 'prepare' ? this.prepareSequence : this.publishSequence;

                try {
                    const response = await fetch(showUrl + '?after_sequence=' + afterSequence, { headers: { 'Accept': 'application/json' } });
                    const payload = await response.json();

                    if (!payload.success) {
                        if (type === 'prepare') {
                            this.prepareError = payload.message || 'Operation polling failed.';
                        } else {
                            this.approveError = payload.message || 'Operation polling failed.';
                        }
                        return;
                    }

                    const operation = payload.operation || null;
                    const events = Array.isArray(payload.events) ? payload.events : [];

                    events.forEach((event) => {
                        if (type === 'prepare') {
                            this.prepareSequence = Math.max(this.prepareSequence, Number(event.id || 0));
                            this.applyPrepareChecklistEvent(event);
                        } else {
                            this.publishSequence = Math.max(this.publishSequence, Number(event.id || 0));
                        }

                        this.queueLogEntry(event.type || 'info', event.message || 'Operation update', [event.stage, event.substage].filter(Boolean).join('/'));
                    });

                    if (type === 'prepare') {
                        this.prepareOperation = operation;
                        this.applyPrepareOperation(operation);
                    } else {
                        this.publishOperation = operation;
                        this.applyPublishOperation(operation);
                    }

                    const status = String((operation && operation.status) || '').toLowerCase();
                    if (['completed', 'failed', 'cancelled'].includes(status)) {
                        if (type === 'prepare' && status === 'completed' && this.autoApproveAfterPrepare) {
                            this.autoApproveAfterPrepare = false;
                            await this.approveArticle();
                        }
                        return;
                    }

                    if (type === 'prepare') {
                        this.preparePollTimer = setTimeout(tick, 900);
                    } else {
                        this.publishPollTimer = setTimeout(tick, 900);
                    }
                } catch (error) {
                    if (type === 'prepare') {
                        this.prepareError = 'Polling error: ' + (error.message || 'unknown');
                        this.preparePollTimer = setTimeout(tick, 1500);
                    } else {
                        this.approveError = 'Polling error: ' + (error.message || 'unknown');
                        this.publishPollTimer = setTimeout(tick, 1500);
                    }
                }
            };

            tick();
        },

        applyPrepareOperation(operation) {
            if (!operation) return;
            const status = String(operation.status || '').toLowerCase();
            if (status === 'completed') {
                this.prepareChecklist.forEach((item) => {
                    if (item.status === 'pending' || item.status === 'running') item.status = 'done';
                });
                this.prepareError = '';
                const message = (operation.result_payload && operation.result_payload.message) || operation.last_message || 'Prepared for WordPress.';
                this.notice = message;
            } else if (status === 'failed' || status === 'cancelled') {
                this.prepareError = operation.error_message || ((operation.result_payload && operation.result_payload.message) || '') || operation.last_message || 'Prepare failed.';
            }
        },

        applyPublishOperation(operation) {
            if (!operation) return;
            const status = String(operation.status || '').toLowerCase();
            const result = operation.result_payload || {};

            if (status === 'completed') {
                if (result.post_id) {
                    this.wpPostId = Number(result.post_id);
                    if (this.siteUrl) {
                        this.wpAdminUrl = String(this.siteUrl).replace(/\/+$/, '') + '/wp-admin/post.php?post=' + String(result.post_id) + '&action=edit';
                    }
                }
                if (result.post_status) {
                    this.wpStatus = String(result.post_status);
                }
                if (result.post_url) {
                    this.wpPostUrl = String(result.post_url);
                }
                if (String(result.post_status || '').toLowerCase() === 'publish' && !this.publishedAt) {
                    this.publishedAt = new Date().toISOString();
                }
                this.approveError = '';
                this.notice = result.message || operation.last_message || 'WordPress action completed.';
            } else if (status === 'failed' || status === 'cancelled') {
                this.approveError = operation.error_message || result.message || operation.last_message || 'WordPress action failed.';
            }
        },

        applyPrepareChecklistEvent(event = {}) {
            const raw = String(event.message || '').trim();
            const msg = raw.toLowerCase();
            const stage = String(event.stage || '').toLowerCase();
            const substage = String(event.substage || '').toLowerCase();
            const findOne = (type) => this.prepareChecklist.find(item => item.type === type);
            const findMany = (type) => this.prepareChecklist.filter(item => item.type === type);

            const authItem = findOne('auth');
            if (authItem && stage === 'connection') {
                authItem.status = event.type === 'success' ? 'done' : (event.type === 'error' ? 'failed' : 'running');
                if (raw) authItem.detail = raw;
            }

            const htmlItem = findOne('html');
            if (htmlItem && stage === 'html') {
                htmlItem.status = event.type === 'success' ? 'done' : (event.type === 'error' ? 'failed' : 'running');
                if (raw) htmlItem.detail = raw;
            }

            if (stage === 'media') {
                const photoItems = findMany('photo');
                const featuredItem = findOne('featured');
                const photoMatch = msg.match(/photo\s+(\d+)/i);
                if (photoMatch) {
                    const idx = Math.max(0, Number(photoMatch[1]) - 1);
                    const item = photoItems[idx];
                    if (item) {
                        item.status = event.type === 'success' ? 'done' : (event.type === 'error' ? 'failed' : 'running');
                        if (raw) item.detail = raw;
                    }
                } else if (featuredItem && substage.startsWith('featured')) {
                    featuredItem.status = event.type === 'success' ? 'done' : (event.type === 'error' ? 'failed' : 'running');
                    if (raw) featuredItem.detail = raw;
                }

                if (event.type === 'success' && msg.includes('images uploaded')) {
                    photoItems.forEach((item) => {
                        if (item.status === 'pending' || item.status === 'running') item.status = 'done';
                    });
                }
            }

            if (stage === 'taxonomy') {
                if (msg.includes('categor')) {
                    const categoryItems = findMany('category');
                    const categoryMatch = raw.match(/'([^']+)'\s*(?:—|-)\s*(ready|skipped|created|already exists)/i);
                    if (categoryMatch) {
                        const item = categoryItems.find(entry => String(entry.label).toLowerCase() === String(categoryMatch[1]).toLowerCase());
                        if (item) {
                            item.status = 'done';
                            item.detail = categoryMatch[2];
                        }
                    } else {
                        categoryItems.forEach((item) => {
                            if (item.status === 'pending') item.status = event.type === 'error' ? 'failed' : 'running';
                        });
                    }
                }

                if (msg.includes('tag')) {
                    const tagItems = findMany('tag');
                    const tagMatch = raw.match(/'([^']+)'\s*(?:—|-)\s*(ready|skipped|created|already exists)/i);
                    if (tagMatch) {
                        const item = tagItems.find(entry => String(entry.label).toLowerCase() === String(tagMatch[1]).toLowerCase());
                        if (item) {
                            item.status = 'done';
                            item.detail = tagMatch[2];
                        }
                    } else {
                        tagItems.forEach((item) => {
                            if (item.status === 'pending') item.status = event.type === 'error' ? 'failed' : 'running';
                        });
                    }
                }
            }

            const integrityItem = findOne('integrity');
            if (integrityItem && stage === 'integrity') {
                integrityItem.status = event.type === 'error' ? 'failed' : (event.type === 'success' || event.type === 'warning' ? 'done' : 'running');
                if (raw) integrityItem.detail = raw;
            }
        },
    };
}
</script>
@endpush
@endsection
