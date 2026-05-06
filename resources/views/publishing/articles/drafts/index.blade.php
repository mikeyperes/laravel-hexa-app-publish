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
        @endphp
        <div class="bg-white rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition-all p-4" :class="deletingId === {{ $draft->id }} ? '!border-red-300 !bg-red-50' : ''" id="row-{{ $draft->id }}">
            <div class="flex items-start gap-4">
                {{-- Checkbox --}}
                <div class="pt-2 flex-shrink-0">
                    <input type="checkbox" value="{{ $draft->id }}" @change="toggleSelect({{ $draft->id }})" :checked="selectedIds.includes({{ $draft->id }})" class="rounded border-gray-300 text-blue-600">
                </div>

                {{-- Thumbnail --}}
                <div class="flex-shrink-0 w-14 h-14 rounded bg-gray-100 overflow-hidden mt-0.5">
                    @if($thumb)
                        <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-300\'><svg class=\'w-5 h-5\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    @endif
                </div>

                {{-- Main Content --}}
                <div class="flex-1 min-w-0">
                    {{-- Title + Status --}}
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('publish.pipeline', ['id' => $draft->id]) }}" class="text-sm font-semibold text-gray-900 hover:text-blue-600 break-words leading-snug">{{ $draft->title ?: 'Untitled' }}</a>
                        <div class="flex-shrink-0 flex items-center gap-2">
                            @if($draft->article_type)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 text-indigo-600 border border-indigo-100">{{ ucfirst(str_replace('_', ' ', $draft->article_type)) }}</span>
                            @endif
                            @if($draft->status === 'completed' || $draft->status === 'published')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700 border border-green-200">{{ ucfirst($draft->status) }}</span>
                            @elseif($draft->status === 'drafting')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500 border border-gray-200">Draft</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-50 text-yellow-700 border border-yellow-200">{{ ucfirst($draft->status) }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- URL --}}
                    @if($draft->wp_post_url)
                        <a href="{{ $draft->wp_post_url }}" target="_blank" rel="noopener" class="text-xs text-blue-500 hover:text-blue-700 break-all leading-tight mt-0.5 inline-flex items-center gap-0.5">
                            {{ $draft->wp_post_url }}
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @elseif($sourceUrl)
                        <p class="text-xs text-gray-400 break-all leading-tight mt-0.5">{{ $sourceUrl }}</p>
                    @endif

                    {{-- Author --}}
                    @if($draft->author)
                        <p class="text-xs text-gray-500 mt-0.5">By <span class="font-medium text-gray-600">{{ $draft->author }}</span></p>
                    @endif

                    {{-- Meta --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-[11px] text-gray-400">
                        <span class="font-mono">{{ $draft->article_id }}</span>

                        @if($draft->site)
                            <span class="inline-flex items-center gap-1" title="{{ $draft->site->url ?? '' }}">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                                {{ $draft->site->name }}
                            </span>
                        @endif

                        @if($draft->word_count)
                            <span>{{ number_format($draft->word_count) }} words</span>
                        @endif

                        @if($draft->ai_detection_score !== null && $draft->ai_detection_score !== '' && $draft->ai_detection_score !== '0')
                            <span class="{{ (float) $draft->ai_detection_score > 50 ? 'text-red-500' : ((float) $draft->ai_detection_score > 20 ? 'text-yellow-600' : 'text-green-600') }}">AI {{ $draft->ai_detection_score }}%</span>
                        @endif

                        @if($draft->ai_engine_used)
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                {{ $draft->ai_engine_used }}
                            </span>
                        @endif

                        @if($draft->creator)
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $draft->creator->name }}
                            </span>
                        @endif

                        <span title="{{ $draft->updated_at ?? $draft->created_at }}">{{ $draft->updated_at ? $draft->updated_at->diffForHumans() : ($draft->created_at ? $draft->created_at->diffForHumans() : '') }}</span>

                        @if($draft->published_at)
                            <span class="text-green-600 font-medium">Published {{ $draft->published_at->format('M j, Y g:ia') }}</span>
                        @endif

                        @if($draft->wp_post_id)
                            <span class="text-gray-400">WP #{{ $draft->wp_post_id }}</span>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex-shrink-0 flex items-center gap-1.5 pt-1">
                    <a href="{{ route('publish.pipeline', ['id' => $draft->id]) }}" class="p-1.5 rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Open in Pipeline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    @if($draft->wp_post_url)
                        <a href="{{ $draft->wp_post_url }}" target="_blank" rel="noopener" class="p-1.5 rounded text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors" title="View on WordPress">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @endif
                    <button @click="deleteSingle({{ $draft->id }})" :disabled="deletingId === {{ $draft->id }}" class="p-1.5 rounded text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
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

    {{-- Pagination --}}
    @if($drafts->hasPages())
    <div class="flex justify-between items-center text-sm text-gray-500 pt-2">
        <span>Showing {{ $drafts->firstItem() ?? 0 }}&ndash;{{ $drafts->lastItem() ?? 0 }} of {{ $drafts->total() }}</span>
        {{ $drafts->appends(request()->query())->links() }}
    </div>
    @endif
</div>

@push('scripts')
<script>
function articlesList() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        searchQuery: new URLSearchParams(window.location.search).get('q') || '',
        searching: false,
        totalCount: {{ $drafts->total() }},
        selectedIds: [],
        deletingId: null,
        deleteLog: [],
        bulkDeleting: false,

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
