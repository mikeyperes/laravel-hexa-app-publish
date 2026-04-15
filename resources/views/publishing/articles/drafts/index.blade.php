{{-- All Articles --}}
@extends('layouts.app')
@section('title', 'Articles')
@section('header', 'Articles')

@section('content')
<div class="space-y-4" x-data="articlesList()">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <p class="text-sm text-gray-500"><span x-text="totalCount">{{ $drafts->total() }}</span> article(s)</p>
            <div class="relative">
                <input type="text" x-model="searchQuery" @input.debounce.400ms="search()" placeholder="Search articles..." class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-64 focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                <svg x-show="searching" x-cloak class="absolute right-3 top-2 w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button x-show="selectedIds.length > 0" @click="bulkDelete()" :disabled="bulkDeleting" class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 disabled:opacity-50 inline-flex items-center gap-1">
                <svg x-show="bulkDeleting" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Delete Selected (<span x-text="selectedIds.length"></span>)
            </button>
            <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700">+ New Article</a>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 w-10"><input type="checkbox" @change="toggleAll($event.target.checked)" class="rounded border-gray-300 text-blue-600"></th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-12">ID</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Article</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-20">Status</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-16">Words</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-28">Created</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($drafts as $draft)
                <tr class="hover:bg-gray-50 transition-colors" :class="deletingId === {{ $draft->id }} ? 'bg-red-50' : ''" id="row-{{ $draft->id }}">
                    <td class="px-4 py-3"><input type="checkbox" value="{{ $draft->id }}" @change="toggleSelect({{ $draft->id }})" :checked="selectedIds.includes({{ $draft->id }})" class="rounded border-gray-300 text-blue-600"></td>
                    <td class="px-4 py-3 text-xs text-gray-400 font-mono">#{{ $draft->id }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('publish.articles.show', $draft->id) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 break-words">{{ $draft->title ?: 'Untitled' }}</a>
                        @if($draft->site)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $draft->site->name }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($draft->status === 'completed' || $draft->status === 'published')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700">{{ ucfirst($draft->status) }}</span>
                        @elseif($draft->status === 'drafting')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">Draft</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700">{{ ucfirst($draft->status) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $draft->word_count ? number_format($draft->word_count) : '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $draft->created_at ? $draft->created_at->diffForHumans() : '—' }}</td>
                    <td class="px-4 py-3">
                        <button @click="deleteSingle({{ $draft->id }})" :disabled="deletingId === {{ $draft->id }}" class="text-gray-300 hover:text-red-500 transition-colors" title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </td>
                </tr>
                {{-- Delete log row (hidden until active) --}}
                <tr x-show="deletingId === {{ $draft->id }} && deleteLog.length > 0" x-cloak>
                    <td colspan="7" class="px-4 py-2 bg-gray-900">
                        <div class="max-h-32 overflow-y-auto">
                            <template x-for="(entry, idx) in deleteLog" :key="idx">
                                <p class="text-xs font-mono py-0.5" :class="{'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-gray-400': entry.type === 'step'}" x-text="entry.message"></p>
                            </template>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">No articles found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex justify-between items-center text-sm text-gray-500">
        <span>Showing {{ $drafts->firstItem() ?? 0 }} to {{ $drafts->lastItem() ?? 0 }} of {{ $drafts->total() }} results</span>
        {{ $drafts->appends(request()->query())->links() }}
    </div>
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
                        if (row) row.remove();
                        this.deletingId = null;
                        this.deleteLog = [];
                    }, 2000);
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
