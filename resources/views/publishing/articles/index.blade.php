{{-- Articles list --}}
@extends('layouts.app')
@section('title', 'Articles')
@section('header', 'Articles')

@section('content')
<div class="space-y-4" x-data="articlesInfinite()">

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.articles.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-56 focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="Search title or ID...">
            <select name="account_id" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400">
                <option value="">All Accounts</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400">
                <option value="">All Statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('-', ' ', $s)) }}</option>
                @endforeach
            </select>
            <select name="sort" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-600 focus:ring-2 focus:ring-blue-400">
                <option value="recent" {{ request('sort', 'recent') === 'recent' ? 'selected' : '' }}>Most Recent</option>
                <option value="published" {{ request('sort') === 'published' ? 'selected' : '' }}>Recently Published</option>
                <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-1.5 rounded-lg text-sm hover:bg-gray-300">Filter</button>
            @if(request()->hasAny(['search', 'account_id', 'status', 'sort']))
                <a href="{{ route('publish.articles.index') }}" class="text-xs text-gray-400 hover:text-gray-600">Clear</a>
            @endif
        </form>
        <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 inline-flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Article
        </a>
    </div>

    {{-- Tabs --}}
    @php $tab = request('tab', 'active'); @endphp
    <div class="flex items-center gap-1 border-b border-gray-200">
        <a href="{{ route('publish.articles.index', array_merge(request()->except(['tab', 'page']), [])) }}"
           class="px-4 py-2 text-sm font-medium border-b-2 {{ $tab === 'active' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            All Articles
        </a>
        <a href="{{ route('publish.articles.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'deleted'])) }}"
           class="px-4 py-2 text-sm font-medium border-b-2 {{ $tab === 'deleted' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Deleted
            @if($deletedCount > 0)
                <span class="ml-1 text-[10px] bg-red-100 text-red-600 rounded-full px-1.5 py-0.5">{{ $deletedCount }}</span>
            @endif
        </a>
    </div>

    <p class="text-sm text-gray-500"><span x-text="totalCount">{{ $articles->total() }}</span> article<span x-show="totalCount !== 1">s</span></p>

    @if($articles->isEmpty())
        <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
            <p class="text-gray-400 text-sm">No articles found.</p>
            <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}" class="inline-block mt-3 text-sm text-blue-600 hover:text-blue-700">Create your first article</a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {{-- Initial rows --}}
            <div id="articles-container">
                @include('app-publish::publishing.articles.partials.article-rows', ['articles' => $articles])
            </div>

            {{-- Loading spinner --}}
            <div x-show="loading" x-cloak class="flex items-center justify-center py-6 border-t border-gray-100">
                <svg class="w-5 h-5 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span class="ml-2 text-sm text-gray-400">Loading more...</span>
            </div>

            {{-- End marker --}}
            <div x-show="!hasMore && !loading" x-cloak class="text-center py-4 text-xs text-gray-400 border-t border-gray-100">
                All articles loaded
            </div>

            {{-- Scroll sentinel --}}
            <div x-ref="sentinel" class="h-1"></div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function articlesInfinite() {
    return {
        loading: false,
        hasMore: {{ $articles->currentPage() < $articles->lastPage() ? 'true' : 'false' }},
        nextPage: {{ $articles->currentPage() < $articles->lastPage() ? $articles->currentPage() + 1 : 'null' }},
        totalCount: {{ $articles->total() }},

        init() {
            if (!this.hasMore) return;
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && this.hasMore && !this.loading) {
                    this.loadMore();
                }
            }, { rootMargin: '200px' });
            observer.observe(this.$refs.sentinel);
        },

        async loadMore() {
            if (this.loading || !this.hasMore || !this.nextPage) return;
            this.loading = true;

            const params = new URLSearchParams(window.location.search);
            params.set('page', this.nextPage);

            try {
                const resp = await fetch('{{ route("publish.articles.index") }}?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await resp.json();

                if (data.html) {
                    document.getElementById('articles-container').insertAdjacentHTML('beforeend', data.html);
                }

                this.nextPage = data.next_page;
                this.hasMore = !!data.next_page;
                this.totalCount = data.total;
            } catch (e) {
                console.error('Failed to load more articles:', e);
            }

            this.loading = false;
        }
    };
}

const _csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const _headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrf, 'Accept': 'application/json' };

async function trashArticle(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';
    try {
        const r = await fetch('/publish/articles/' + id, { method: 'PUT', headers: _headers, body: JSON.stringify({ status: 'deleted' }) });
        const d = await r.json();
        if (d.success !== false) {
            const row = btn.closest('[class*="flex items-start gap-4"]');
            if (row) { row.style.opacity = '0'; row.style.transition = 'opacity 0.3s'; setTimeout(() => row.remove(), 300); }
        }
    } catch(e) { console.error(e); }
    btn.disabled = false;
}

async function restoreArticle(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';
    try {
        const r = await fetch('/publish/articles/' + id, { method: 'PUT', headers: _headers, body: JSON.stringify({ status: 'drafting' }) });
        const d = await r.json();
        if (d.success !== false) {
            const row = btn.closest('[class*="flex items-start gap-4"]');
            if (row) { row.style.opacity = '0'; row.style.transition = 'opacity 0.3s'; setTimeout(() => row.remove(), 300); }
        }
    } catch(e) { console.error(e); }
    btn.disabled = false;
}
</script>
@endpush
@endsection
