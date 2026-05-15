{{-- All Articles --}}
@extends('layouts.app')
@section('title', 'Articles')
@section('header', 'Articles')

@section('content')
<div class="space-y-4" x-data="articlesList()" @article-count-change.window="totalCount = Math.max(0, totalCount + Number(($event.detail && $event.detail.delta) || 0))" @toggle-select.window="toggleSelect($event.detail.id)" @open-approval-email="openApprovalEmail($event.detail.id)">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-gray-500"><span x-text="totalCount">{{ $drafts->total() }}</span> articles</p>
            <div class="relative">
                <input type="text" x-model="searchQuery" @input.debounce.400ms="search()" placeholder="Search articles..." class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-64 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <svg x-show="searching" x-cloak class="absolute right-3 top-2 w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>
            <select x-ref="article_type_filter" x-model="articleTypeFilter" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <option value="">All Article Types</option>
                @foreach($articleTypes as $type)
                    <option value="{{ $type }}" {{ request('article_type') === $type ? 'selected' : '' }}>{{ ucwords(str_replace(['_', '-'], ' ', $type)) }}</option>
                @endforeach
            </select>
            <select x-ref="site_filter" x-model="siteFilter" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <option value="">All Publication Sources</option>
                <option value="__none__" {{ request('site_id') === '__none__' ? 'selected' : '' }}>No Publication Source</option>
                @foreach($sites as $site)
                    <option value="{{ $site->id }}" {{ (string) request('site_id') === (string) $site->id ? 'selected' : '' }}>{{ $site->name }}</option>
                @endforeach
            </select>
            <select x-ref="user_filter" x-model="userFilter" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <option value="">All Users</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ (string) request('user_id') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
            </select>
            <select x-ref="status_filter" x-model="statusFilter" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <option value="">All Statuses</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" x-show="hasActiveFilters()" x-cloak @click="clearFilters()" class="cursor-pointer text-xs px-2.5 py-1 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Clear</button>
        </div>
        <div class="flex flex-wrap items-center gap-2" data-v3c="1">
            <template x-if="$store.drafts.deleteMode">
                <div class="flex flex-wrap items-center gap-2 mr-2">
                    <span class="text-xs text-gray-500"><span class="font-semibold text-gray-700" x-text="$store.drafts.selectedIds.length"></span> selected</span>
                    <button type="button" @click="$store.drafts.selectAll()" class="cursor-pointer text-xs px-2.5 py-1 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Select all</button>
                    <button type="button" @click="$store.drafts.selectNone()" class="cursor-pointer text-xs px-2.5 py-1 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Select none</button>
                    <button type="button" @click="bulkDelete()" :disabled="bulkDeleting || $store.drafts.selectedIds.length === 0" class="cursor-pointer text-sm bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1.5">
                        <svg x-show="bulkDeleting" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <svg x-show="!bulkDeleting" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete (<span x-text="$store.drafts.selectedIds.length"></span>)
                    </button>
                </div>
            </template>
            <button type="button" @click="$store.drafts.toggleDeleteMode()" :class="$store.drafts.deleteMode ? 'bg-gray-700 text-white hover:bg-gray-800 border-gray-700' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'" class="cursor-pointer text-sm px-3 py-1.5 rounded-lg border inline-flex items-center gap-1.5">
                <svg x-show="!$store.drafts.deleteMode" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <svg x-show="$store.drafts.deleteMode" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span x-text="$store.drafts.deleteMode ? 'Cancel' : 'Delete'"></span>
            </button>
            <a href="{{ route('publish.pipeline.v2', ['spawn' => 1]) }}" class="cursor-pointer bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Article
            </a>
        </div>
    </div>

    <div x-ref="results" id="draft-results">
    {{-- Article List --}}
    <div class="space-y-4" data-v3d="1">
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
            $googleDocExportState = is_array($statePayload['googleDocExport'] ?? null) ? $statePayload['googleDocExport'] : [];
            $googleDocFingerprint = md5(implode("\n", [
                trim((string) ($draft->title ?: 'Untitled')),
                trim($excerptPreview),
                trim($bodySource),
                trim((string) ($draft->article_type ?? '')),
                trim((string) ($site->name ?? '')),
            ]));
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
                'author' => $draft->resolved_author ?: '',
                'articleType' => (string) ($draft->article_type ?? ''),
                'status' => (string) ($draft->status ?? ''),
                'deliveryMode' => (string) ($draft->delivery_mode ?? ''),
                'siteId' => (int) ($site->id ?? 0),
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
                'googleDocExport' => [
                    'document_id' => trim((string) ($googleDocExportState['document_id'] ?? '')),
                    'url' => trim((string) ($googleDocExportState['url'] ?? $googleDocExportState['web_view_link'] ?? $googleDocExportState['normalized_url'] ?? '')),
                    'owner_email' => trim((string) ($googleDocExportState['owner_email'] ?? app(\hexa_package_google_docs\Services\GoogleDocsWriteService::class)->ownerEmail())),
                    'connected_email' => trim((string) ($googleDocExportState['connected_email'] ?? '')),
                    'last_exported_at' => trim((string) ($googleDocExportState['last_exported_at'] ?? '')),
                    'last_export_hash' => trim((string) ($googleDocExportState['last_export_hash'] ?? '')),
                ],
                'googleDocFingerprint' => $googleDocFingerprint,
                'googleDocExportUrl' => route('publish.drafts.google-doc.export', $draft->id),
            ];
        @endphp
        <div
            id="row-{{ $draft->id }}"
            x-data="articleCard(@js($rowPayload))"
            class="bg-white rounded-xl border border-gray-200 hover:border-gray-300 hover:shadow-md transition-all overflow-hidden cursor-pointer"
            data-v3c="1"
            :class="{
                '!border-red-300 !bg-red-50': removing,
                '!border-blue-200 !shadow-md': expanded,
                '!border-blue-400 !shadow-md ring-2 ring-blue-400 ring-offset-1': $store.drafts.isSelected({{ $draft->id }}),
            }"
        >
            <div class="flex items-stretch gap-0 min-h-[220px]" @click.self="togglePreview()">
                <a :href="pipelineUrl" @click.stop="$store.drafts.deleteMode && (event.preventDefault(), $store.drafts.toggleSelect({{ $draft->id }}))" class="flex-shrink-0 w-64 self-stretch bg-gray-100 overflow-hidden block group relative" data-v3h-overlay="1">
                    @if($thumb)
                        <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-300\'><svg class=\'w-9 h-9\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    @endif
                    {{-- Selection overlay (only in delete mode) --}}
                    <template x-if="$store.drafts.deleteMode">
                        <div class="absolute inset-0 transition-colors" :class="$store.drafts.isSelected({{ $draft->id }}) ? 'bg-blue-500/25' : 'bg-black/0 hover:bg-black/10'">
                            <div class="absolute top-3 left-3">
                                <span x-show="!$store.drafts.isSelected({{ $draft->id }})" class="block w-7 h-7 rounded-full border-2 border-white bg-white/60 shadow-md backdrop-blur-sm"></span>
                                <span x-show="$store.drafts.isSelected({{ $draft->id }})" x-cloak class="w-7 h-7 rounded-full bg-blue-600 border-2 border-white shadow-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            </div>
                        </div>
                    </template>
                </a>

                <div class="flex-1 min-w-0 p-5" @click.self="togglePreview()">
    <div class="flex items-start justify-between gap-4" @click.self="togglePreview()">
                        <div class="flex-1 min-w-0" @click.self="togglePreview()">
                            <h3 class="text-3xl font-bold text-gray-900 break-words leading-tight">
                                <a :href="pipelineUrl" @click.stop class="hover:text-blue-600 cursor-pointer" x-text="title || 'Untitled'"></a>
                            </h3>
                            <p class="text-sm text-gray-500 mt-1" x-show="author" x-cloak>
                                By
                                <template x-if="siteUrl && author">
                                    <a :href="(siteUrl || '').replace(/\/+$/, '') + '/author/' + encodeURIComponent(author) + '/'" target="_blank" rel="noopener" @click.stop class="cursor-pointer font-medium text-gray-700 hover:text-blue-600 inline-flex items-center gap-1">
                                        <span x-text="author"></span>
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </template>
                                <template x-if="!siteUrl && author">
                                    <span class="font-medium text-gray-600" x-text="author"></span>
                                </template>
                            </p>
                            <p class="text-sm text-gray-500 mt-0.5" x-show="siteName" x-cloak>
                                <span class="font-medium text-gray-700" x-text="siteName"></span>
                                <template x-if="siteUrl">
                                    <span>
                                        <span class="text-gray-400 mx-1">—</span>
                                        <a :href="siteUrl" target="_blank" rel="noopener" @click.stop class="cursor-pointer text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                            <span x-text="siteUrl"></span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </span>
                                </template>
                            </p>
                        </div>

                        <div class="flex-shrink-0 flex flex-col items-end gap-2">
                            <div class="flex items-center gap-0.5">
                                <button @click.stop="togglePreview()" class="cursor-pointer p-1.5 rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" :title="expanded ? 'Collapse preview' : 'Expand to preview & approve'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                <button @click.stop="$dispatch('open-approval-email', { id })" class="cursor-pointer p-1.5 rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Send draft approval email">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8m-16 8h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                </button>
                                <a :href="pipelineUrl" @click.stop class="cursor-pointer p-1.5 rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Open in full editor">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button @click.stop="denyArticle()" :disabled="removing || preparing || approving" class="cursor-pointer p-1.5 rounded text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Delete draft">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            <div class="flex items-center gap-1.5">
                                @if($draft->article_type)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 text-indigo-600 border border-indigo-100">{{ ucfirst(str_replace('_', ' ', $draft->article_type)) }}</span>
                                @endif
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border" :class="deliveryToneClass()" x-text="currentStatePillLabel()"></span>
                                <button type="button" @click.stop="hasGoogleDoc() && !googleDocIsStale() ? openGoogleDoc() : exportGoogleDoc()" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100">Google Doc</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-3 text-[11px] text-gray-500" @click.self="togglePreview()">
                        <span class="font-mono text-gray-400">{{ $draft->article_id }}</span>
                        @if($draft->word_count)
                            <span class="text-gray-300 select-none">·</span>
                            <span>{{ number_format($draft->word_count) }} words</span>
                        @endif
                        @if($draft->creator)
                            <span class="text-gray-300 select-none">·</span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>Submitted by {{ $draft->creator->name }}</span>
                            </span>
                        @endif
                        <span class="text-gray-300 select-none">·</span>
                        <span title="{{ $draft->updated_at ?? $draft->created_at }}">{{ $draft->updated_at ? $draft->updated_at->diffForHumans() : ($draft->created_at ? $draft->created_at->diffForHumans() : '') }}</span>
                        @if($draft->wp_post_id)
                            <span class="text-gray-300 select-none">·</span>
                            <span class="text-gray-400">WP #{{ $draft->wp_post_id }}</span>
                        @endif
                        @if($draft->published_at)
                            <span class="text-gray-300 select-none">·</span>
                            <span class="text-green-600 font-medium">Published {{ $draft->published_at->format('M j, Y g:ia') }}</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mt-2 text-[11px]" x-show="(wpPostUrl && isLive()) || wpAdminUrl" x-cloak>
                        <template x-if="wpPostUrl && isLive()">
                            <a :href="wpPostUrl" target="_blank" rel="noopener" @click.stop class="cursor-pointer text-blue-500 hover:text-blue-700 inline-flex items-center gap-1">
                                Live link
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </template>
                        <template x-if="wpAdminUrl">
                            <a :href="wpAdminUrl" target="_blank" rel="noopener" @click.stop class="cursor-pointer text-gray-500 hover:text-gray-700 inline-flex items-center gap-1">
                                WP Admin
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </template>
                    </div>
                </div>
            </div>


            <div x-show="expanded" x-cloak data-v3c-expanded-flat="1" class="border-t border-gray-100 px-5 pb-5 pt-5 space-y-6 bg-gradient-to-b from-gray-50/50 to-white">

                {{-- Status banners --}}
                <div x-show="notice" x-cloak class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800" x-text="notice"></div>
                <div x-show="prepareError || approveError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 whitespace-pre-line" x-text="prepareError || approveError"></div>

                {{-- Dashboard banner — all status + links at a glance --}}
                <section data-v3f-dashboard="1" class="rounded-lg bg-gray-50/70 border border-gray-200 px-5 py-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pb-3 border-b border-gray-200">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold border" :class="deliveryToneClass()" x-text="currentStateLabel()"></span>
                        @if($draft->article_type)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">{{ ucfirst(str_replace('_', ' ', $draft->article_type)) }}</span>
                        @endif
                        <span x-show="wordCount" x-cloak class="inline-flex items-center gap-1 text-xs text-gray-600">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            <span class="font-medium text-gray-700" x-text="(wordCount || 0).toLocaleString() + ' words'"></span>
                        </span>
                        <span x-show="updatedAtHuman" x-cloak class="inline-flex items-center gap-1 text-xs text-gray-600">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="'Updated ' + updatedAtHuman"></span>
                        </span>
                    </div>
                    <dl class="space-y-2 text-sm" data-v3g-dl="1">
                        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5">
                            <dt class="w-32 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Publishing to</dt>
                            <dd class="text-gray-800 min-w-0 flex-1">
                                <span class="font-semibold" x-text="siteName || '—'"></span>
                                <template x-if="siteUrl">
                                    <span>
                                        <span class="text-gray-300 mx-2">→</span>
                                        <a :href="siteUrl" target="_blank" rel="noopener" @click.stop class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                            <span x-text="siteUrl"></span>
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </span>
                                </template>
                            </dd>
                        </div>

                        <template x-if="author">
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5">
                                <dt class="w-32 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Author</dt>
                                <dd class="text-gray-800 min-w-0 flex-1">
                                    <template x-if="siteUrl && author">
                                        <a :href="(siteUrl || '').replace(/\/+$/, '') + '/author/' + encodeURIComponent(author) + '/'" target="_blank" rel="noopener" @click.stop class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                            <span x-text="author"></span>
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <template x-if="!(siteUrl && author)">
                                        <span x-text="author"></span>
                                    </template>
                                </dd>
                            </div>
                        </template>

                        <template x-if="deliveryMode">
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5">
                                <dt class="w-32 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Delivery</dt>
                                <dd class="text-gray-800 min-w-0 flex-1 font-medium" x-text="deliveryMode"></dd>
                            </div>
                        </template>

                        <template x-if="sourceName || sourceUrl">
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5">
                                <dt class="w-32 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Source</dt>
                                <dd class="text-gray-800 min-w-0 flex-1">
                                    <template x-if="sourceUrl">
                                        <a :href="sourceUrl" target="_blank" rel="noopener" @click.stop class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1 min-w-0">
                                            <span class="truncate" x-text="sourceName || sourceUrl"></span>
                                            <svg class="w-3 h-3 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <template x-if="!sourceUrl">
                                        <span x-text="sourceName"></span>
                                    </template>
                                </dd>
                            </div>
                        </template>

                        <template x-if="wpPostId">
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5">
                                <dt class="w-32 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">WordPress</dt>
                                <dd class="text-gray-800 min-w-0 flex-1 inline-flex items-center gap-3">
                                    <span class="font-mono" x-text="'#' + wpPostId"></span>
                                    <template x-if="wpAdminUrl">
                                        <a :href="wpAdminUrl" target="_blank" rel="noopener" @click.stop class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                            wp-admin
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                </dd>
                            </div>
                        </template>

                        <template x-if="wpPostUrl && isLive()">
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5">
                                <dt class="w-32 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Live URL</dt>
                                <dd class="text-gray-800 min-w-0 flex-1">
                                    <a :href="wpPostUrl" target="_blank" rel="noopener" @click.stop class="text-green-700 hover:text-green-900 inline-flex items-center gap-1 min-w-0">
                                        <span class="truncate" x-text="wpPostUrl"></span>
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </dd>
                            </div>
                        </template>

                        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5">
                            <dt class="w-32 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Google Doc</dt>
                            <dd class="text-gray-800 min-w-0 flex-1 flex flex-wrap items-center gap-3">
                                <span class="text-xs font-medium" :class="googleDocIsStale() ? 'text-amber-700' : (hasGoogleDoc() ? 'text-emerald-700' : 'text-gray-500')" x-text="googleDocStatusLabel()"></span>
                                <template x-if="hasGoogleDoc() && googleDocExport?.url">
                                    <a :href="googleDocExport.url" target="_blank" rel="noopener" @click.stop class="text-emerald-700 hover:text-emerald-900 inline-flex items-center gap-1">
                                        Open Google Doc
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </template>
                                <template x-if="googleDocExport?.owner_email">
                                    <span class="text-[11px] text-gray-500" x-text="'Owner: ' + googleDocExport.owner_email"></span>
                                </template>
                            </dd>
                        </div>
                    </dl>
                </section>

                {{-- Article preview --}}
                <section>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-400 mb-2">Article Preview</p>
                    <h2 class="text-2xl font-bold text-gray-900 leading-tight break-words" x-text="title || 'Untitled'"></h2>
                    <p x-show="excerpt" x-cloak class="mt-2 text-base text-gray-600 italic leading-relaxed" x-text="excerpt"></p>
                    <div x-show="bodyHtml" x-cloak class="mt-4 prose prose-base prose-gray max-w-none text-gray-800 [&_figcaption]:italic [&_figcaption]:text-center [&_figcaption]:text-sm [&_figcaption]:text-gray-500 [&_figcaption]:mt-2" x-html="bodyHtml"></div>
                    <p x-show="!bodyHtml" x-cloak class="mt-4 text-gray-400 italic text-sm">No saved article body yet — open the full editor to add content.</p>
                </section>

                {{-- Categories + Tags --}}
                <section data-v3f-classify="1" class="border-t border-gray-200 pt-5 space-y-5">
                    {{-- Categories full-width --}}
                    <div x-show="categories.length > 0" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-gray-400 mb-2">Categories <span class="text-gray-300 ml-1" x-text="'(' + categories.length + ')'"></span></p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="category in categories" :key="category">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200" x-text="category"></span>
                            </template>
                        </div>
                    </div>

                    {{-- Tags full-width --}}
                    <div x-show="tags.length > 0" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-gray-400 mb-2">Tags <span class="text-gray-300 ml-1" x-text="'(' + tags.length + ')'"></span></p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="tag in tags" :key="tag">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100" x-text="tag"></span>
                            </template>
                        </div>
                    </div>

                    <p x-show="categories.length === 0 && tags.length === 0" x-cloak class="text-xs text-gray-400 italic">No categories or tags set yet.</p>
                </section>

{{-- Sticky action bar — shadow only, no border, no nested card --}}
                <div class="sticky bottom-2 z-10 -mx-5 px-5 pt-2">
                    <div class="bg-white shadow-md ring-1 ring-gray-100 rounded-lg px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-[11px] text-gray-500 flex-1 min-w-0">
                            <span class="font-semibold text-gray-700">Ready to act?</span>
                            <span class="hidden sm:inline ml-1" x-text="isLive() ? 'Article is live on WordPress.' : (isWpDraft() ? 'WordPress draft ready — approve to publish live.' : 'Run prepare first, then approve to publish.')"></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button @click.stop="prepareArticle()" :disabled="preparing || approving || removing" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-blue-200 text-blue-700 hover:bg-blue-50 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="preparing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <svg x-show="!preparing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-text="preparing ? 'Preparing…' : prepareButtonLabel()"></span>
                            </button>
                            <button @click.stop="approveArticle()" :disabled="approving || preparing || removing || isLive()" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                                <svg x-show="approving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <svg x-show="!approving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                <span x-text="approving ? 'Pushing…' : approveButtonLabel()"></span>
                            </button>
                            <button @click.stop="exportGoogleDoc(true)" :disabled="exportingGoogleDoc || removing || preparing || approving" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="exportingGoogleDoc" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <svg x-show="!exportingGoogleDoc" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h6M8 4h8a2 2 0 012 2v12a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                                <span x-text="exportingGoogleDoc ? 'Exporting…' : googleDocActionLabel()"></span>
                            </button>
                            <a x-show="hasGoogleDoc() && googleDocExport?.url" x-cloak :href="googleDocExport.url" target="_blank" rel="noopener" @click.stop class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50 text-sm font-medium">
                                Open Google Doc
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            <a :href="pipelineUrl" target="_blank" rel="noopener" @click.stop class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium">
                                Open Full Editor
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            <button data-delete-draft-action type="button" @click.stop="denyArticle()" :disabled="removing || preparing || approving" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-200 bg-white text-red-600 hover:bg-red-50 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="removing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <svg x-show="!removing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                <span x-text="removing ? 'Deleting…' : 'Delete Draft'"></span>
                            </button>
                        </div>
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
        <div data-draft-pagination>
            {{ $drafts->appends(request()->query())->links() }}
        </div>
    </div>
    @endif
    </div>
</div>

@push('scripts')
@include('app-publish::publishing.articles.partials.draft-approval-email-script')
<script>
document.addEventListener('alpine:init', () => {
    if (!window.Alpine || !Alpine.store) return;
    if (Alpine.store('drafts')) return;
    Alpine.store('drafts', {
        deleteMode: false,
        selectedIds: [],
        allIds: window._draftAllIds || [],
        toggleDeleteMode() {
            this.deleteMode = !this.deleteMode;
            if (!this.deleteMode) this.selectedIds = [];
        },
        toggleSelect(id) {
            const i = this.selectedIds.indexOf(id);
            if (i === -1) this.selectedIds.push(id);
            else this.selectedIds.splice(i, 1);
        },
        selectAll() { this.selectedIds = [...this.allIds]; },
        selectNone() { this.selectedIds = []; },
        isSelected(id) { return this.selectedIds.includes(id); },
    });
});
window._draftAllIds = [{{ $drafts->pluck('id')->join(',') }}];
function articlesList() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    const asyncHeaders = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
    const baseUrl = @json(route('publish.drafts.index'));
    const initialParams = new URLSearchParams(window.location.search);
    return {
        ...draftApprovalEmailMixin(),
        searchQuery: initialParams.get('q') || '',
        articleTypeFilter: initialParams.get('article_type') || '',
        siteFilter: initialParams.get('site_id') || '',
        userFilter: initialParams.get('user_id') || '',
        statusFilter: initialParams.get('status') || '',
        searching: false,
        totalCount: {{ $drafts->total() }},
        currentPage: {{ $drafts->currentPage() }},
        selectedIds: [],
        bulkDeleting: false,
        requestController: null,
        get deleteMode() { return this.$store.drafts.deleteMode; },
        get selectedIds() { return this.$store.drafts.selectedIds; },
        approvalEmailWidth: (typeof localStorage !== 'undefined' && localStorage.getItem('approvalEmailWidth')) || 'L',

        init() {
            const params = new URLSearchParams(window.location.search);
            const emailParam = params.get('email');
            if (emailParam) {
                const idNum = parseInt(emailParam, 10);
                if (!isNaN(idNum) && idNum > 0) {
                    this.openApprovalEmail(idNum);
                }
            }
            try { localStorage.setItem('approvalEmailWidth', this.approvalEmailWidth || 'L'); } catch (e) {}
            this.$watch('approvalEmailOpen', (open) => {
                if (!open) this.syncEmailQueryParam(null);
            });
            this.$refs.results.addEventListener('click', (event) => {
                const link = event.target.closest('[data-draft-pagination] a[href]');
                if (!link) return;
                event.preventDefault();
                const url = new URL(link.href, window.location.origin);
                const page = parseInt(url.searchParams.get('page') || '1', 10);
                this.loadResults(page);
            });
        },

        cycleApprovalEmailWidth() {
            const order = ['M', 'L', 'XL'];
            const next = order[(order.indexOf(this.approvalEmailWidth) + 1) % order.length];
            this.approvalEmailWidth = next;
            try { localStorage.setItem('approvalEmailWidth', next); } catch (e) {}
        },

        hasActiveFilters() {
            return !!(this.searchQuery.trim() || this.articleTypeFilter || this.siteFilter || this.userFilter || this.statusFilter);
        },

        buildParams(page = 1) {
            const params = new URLSearchParams();
            if (this.searchQuery.trim()) params.set('q', this.searchQuery.trim());
            if (this.articleTypeFilter) params.set('article_type', this.articleTypeFilter);
            if (this.siteFilter) params.set('site_id', this.siteFilter);
            if (this.userFilter) params.set('user_id', this.userFilter);
            if (this.statusFilter) params.set('status', this.statusFilter);
            if (page > 1) params.set('page', String(page));
            return params;
        },

        syncUrl(page = 1) {
            const query = this.buildParams(page).toString();
            window.history.replaceState({}, '', query ? `${baseUrl}?${query}` : baseUrl);
        },

        async loadResults(page = 1) {
            const controller = new AbortController();
            if (this.requestController) this.requestController.abort();
            this.requestController = controller;
            this.searching = true;

            try {
                const query = this.buildParams(page).toString();
                const response = await fetch(query ? `${baseUrl}?${query}` : baseUrl, {
                    headers: asyncHeaders,
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error(`Request failed with status ${response.status}`);

                const payload = await response.json();
                const parser = new DOMParser();
                const doc = parser.parseFromString(payload.html || '', 'text/html');
                const nextResults = doc.querySelector('#draft-results');
                if (!nextResults) throw new Error('Results container missing');

                this.$refs.results.innerHTML = nextResults.innerHTML;
                this.totalCount = payload.total ?? 0;
                this.currentPage = payload.current_page ?? page;
                this.$store.drafts.allIds = Array.isArray(payload.ids) ? payload.ids : [];
                this.$store.drafts.selectedIds = [];
                this.$store.drafts.deleteMode = false;
                window._draftAllIds = this.$store.drafts.allIds;
                this.syncUrl(this.currentPage);

                this.$nextTick(() => window.Alpine?.initTree(this.$refs.results));
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Failed to load filtered drafts:', error);
                }
            } finally {
                if (this.requestController === controller) {
                    this.searching = false;
                    this.requestController = null;
                }
            }
        },

        search() {
            this.loadResults(1);
        },

        applyFilters() {
            this.loadResults(1);
        },

        clearFilters() {
            this.searchQuery = '';
            this.articleTypeFilter = '';
            this.siteFilter = '';
            this.userFilter = '';
            this.statusFilter = '';
            this.loadResults(1);
        },

        filterStatus(val) {
            this.statusFilter = val || '';
            this.loadResults(1);
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
            const ids = this.$store.drafts.selectedIds.slice();
            if (ids.length === 0) return;
            if (!confirm('Delete ' + ids.length + ' article(s)? Published articles will also be removed from WordPress.')) return;
            this.bulkDeleting = true;
            try {
                const r = await fetch('{{ route("publish.drafts.bulk-destroy") }}', { method: 'POST', headers, body: JSON.stringify({ ids }) });
                const d = await r.json();
                if (d.success) {
                    // Stagger row fade-out, then remove from DOM
                    ids.forEach((id, idx) => {
                        setTimeout(() => {
                            const row = document.getElementById('row-' + id);
                            if (row) {
                                row.style.transition = 'opacity 0.3s, transform 0.3s, max-height 0.3s, margin 0.3s';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(-12px)';
                                setTimeout(() => { row.style.maxHeight = '0'; row.style.margin = '0'; row.style.padding = '0'; }, 280);
                                setTimeout(() => row.remove(), 600);
                            }
                        }, idx * 50);
                    });
                    // Update store + counter
                    this.totalCount = Math.max(0, this.totalCount - ids.length);
                    this.$store.drafts.allIds = (this.$store.drafts.allIds || []).filter(x => !ids.includes(x));
                    this.$store.drafts.selectedIds = [];
                    this.$store.drafts.deleteMode = false;
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
        googleDocExport: config.googleDocExport || {},
        googleDocFingerprint: config.googleDocFingerprint || '',
        googleDocExportUrl: config.googleDocExportUrl || '',
        exportingGoogleDoc: false,
        googleDocError: '',

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

        hasGoogleDoc() {
            return !!String(this.googleDocExport?.document_id || "").trim();
        },

        googleDocIsStale() {
            return this.hasGoogleDoc() && String(this.googleDocExport?.last_export_hash || "") !== String(this.googleDocFingerprint || "");
        },

        googleDocStatusLabel() {
            if (!this.hasGoogleDoc()) return "No Google Doc yet";
            return this.googleDocIsStale() ? "Google Doc out of date" : "Google Doc up to date";
        },

        googleDocActionLabel() {
            if (!this.hasGoogleDoc()) return "Create Google Doc";
            return this.googleDocIsStale() ? "Update Google Doc" : "Refresh Google Doc";
        },

        openGoogleDoc() {
            if (this.googleDocExport?.url) {
                window.open(this.googleDocExport.url, "_blank", "noopener");
            }
        },

        async exportGoogleDoc(openAfter = true) {
            if (this.exportingGoogleDoc || !this.googleDocExportUrl) return;
            this.notice = "";
            this.googleDocError = "";
            this.prepareError = "";
            this.approveError = "";
            this.exportingGoogleDoc = true;
            this.expanded = true;
            try {
                const response = await fetch(this.googleDocExportUrl, { method: "POST", headers: jsonHeaders, body: JSON.stringify({}) });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    this.googleDocError = this.formatFailure(response.status, payload);
                    this.approveError = this.googleDocError;
                    return;
                }

                this.googleDocExport = payload.google_doc || {};
                this.notice = payload.message || "Google Doc exported successfully.";
                if (openAfter && this.googleDocExport?.url) {
                    window.open(this.googleDocExport.url, "_blank", "noopener");
                }
            } catch (error) {
                this.googleDocError = "Network error: " + (error.message || "unknown");
                this.approveError = this.googleDocError;
            } finally {
                this.exportingGoogleDoc = false;
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
            const operationSiteId = Number(operation.site_id || ((operation.request_summary && operation.request_summary.site_id) || 0));
            if (this.siteId && operationSiteId && Number(this.siteId) !== operationSiteId) return;
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
