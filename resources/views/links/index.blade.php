{{-- Links & Sitemaps management --}}
@extends('layouts.app')
@section('title', 'Links & Sitemaps')
@section('header', 'Links & Sitemaps')

@section('content')
<div class="space-y-6" x-data="linksManager()">

    {{-- Filter --}}
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.links.index') }}" class="flex items-center gap-2">
            <select name="user_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
    </div>

    {{-- ═══ Backlinks / Internal Links ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between cursor-pointer" @click="showAddLink = !showAddLink">
            <h3 class="font-semibold text-gray-800">Links ({{ $links->count() }})</h3>
            <span class="text-sm text-blue-600">+ Add Link</span>
        </div>

        {{-- Add link form --}}
        <div x-show="showAddLink" x-cloak class="p-5 bg-gray-50 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">User</label>
                    <select x-model="newLink.user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select...</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Name</label>
                    <input type="text" x-model="newLink.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Company homepage">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">URL</label>
                    <input type="url" x-model="newLink.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Type</label>
                    <select x-model="newLink.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="backlink">Backlink</option>
                        <option value="internal">Internal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Preferred Anchor Text</label>
                    <input type="text" x-model="newLink.anchor_text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Optional">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Priority (0-100)</label>
                    <input type="number" x-model="newLink.priority" min="0" max="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="0">
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs text-gray-500 mb-1">Context (AI hint for placement)</label>
                <input type="text" x-model="newLink.context" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Place when article mentions AI tools">
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button @click="addLink()" :disabled="savingLink" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="savingLink" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="savingLink ? 'Adding...' : 'Add Link'"></span>
                </button>
            </div>
            <div x-show="linkResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="linkSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="linkResult"></span>
            </div>
        </div>

        @if($links->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No links added yet.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">URL</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Anchor</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Priority</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Used</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($links as $link)
                    <tr class="hover:bg-gray-50 {{ !$link->active ? 'opacity-40' : '' }}">
                        <td class="px-5 py-2 font-medium break-words">{{ $link->name }}</td>
                        <td class="px-5 py-2 text-xs break-words"><a href="{{ $link->url }}" target="_blank" class="text-blue-600 hover:text-blue-800">{{ \Illuminate\Support\Str::limit($link->url, 40) }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></td>
                        <td class="px-5 py-2 text-xs">
                            @if($link->type === 'backlink')<span class="text-purple-600">Backlink</span>
                            @else <span class="text-blue-600">Internal</span>@endif
                        </td>
                        <td class="px-5 py-2 text-xs text-gray-500 break-words">{{ $link->anchor_text ?? '—' }}</td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $link->priority }}</td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $link->times_used }}x</td>
                        <td class="px-5 py-2 text-xs text-gray-400">{{ $link->user->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-2">
                            <div class="flex items-center gap-2">
                                <button @click="toggleLink({{ $link->id }})" class="text-xs {{ $link->active ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800' }}">
                                    {{ $link->active ? 'Disable' : 'Enable' }}
                                </button>
                                <button @click="deleteLink({{ $link->id }})" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ═══ Sitemaps ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between cursor-pointer" @click="showAddSitemap = !showAddSitemap">
            <h3 class="font-semibold text-gray-800">Sitemaps ({{ $sitemaps->count() }})</h3>
            <span class="text-sm text-blue-600">+ Add Sitemap</span>
        </div>

        {{-- Add sitemap form --}}
        <div x-show="showAddSitemap" x-cloak class="p-5 bg-gray-50 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">User</label>
                    <select x-model="newSitemap.user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select...</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Name</label>
                    <input type="text" x-model="newSitemap.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Main site sitemap">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sitemap URL</label>
                    <input type="url" x-model="newSitemap.sitemap_url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://example.com/sitemap.xml">
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button @click="addSitemap()" :disabled="savingSitemap" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="savingSitemap" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="savingSitemap ? 'Adding & Parsing...' : 'Add Sitemap'"></span>
                </button>
            </div>
            <div x-show="sitemapResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="sitemapSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="sitemapResult"></span>
            </div>
        </div>

        @if($sitemaps->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No sitemaps added yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($sitemaps as $sitemap)
                <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                    <div>
                        <p class="font-medium text-sm break-words">{{ $sitemap->name }}</p>
                        <p class="text-xs text-gray-400 break-words"><a href="{{ $sitemap->sitemap_url }}" target="_blank" class="hover:text-blue-600">{{ $sitemap->sitemap_url }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></p>
                        <p class="text-xs text-gray-400">{{ $sitemap->url_count }} URLs &middot; {{ $sitemap->user->name ?? 'Unassigned' }} @if($sitemap->last_parsed_at) &middot; Parsed {{ $sitemap->last_parsed_at->diffForHumans() }}@endif</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="refreshSitemap({{ $sitemap->id }})" class="text-xs text-blue-600 hover:text-blue-800">Refresh</button>
                        <button @click="deleteSitemap({{ $sitemap->id }})" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function linksManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showAddLink: false, showAddSitemap: false,
        newLink: { user_id: '', name: '', type: 'backlink', url: '', anchor_text: '', context: '', priority: 0 },
        newSitemap: { user_id: '', name: '', sitemap_url: '' },
        savingLink: false, linkResult: '', linkSuccess: false,
        savingSitemap: false, sitemapResult: '', sitemapSuccess: false,
        async addLink() {
            this.savingLink = true; this.linkResult = '';
            try {
                const r = await fetch('{{ route("publish.links.store") }}', { method: 'POST', headers, body: JSON.stringify(this.newLink) });
                const d = await r.json(); this.linkSuccess = d.success; this.linkResult = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch (e) { this.linkSuccess = false; this.linkResult = 'Error: ' + e.message; }
            this.savingLink = false;
        },
        async toggleLink(id) {
            try { await fetch('/publish/links/' + id + '/toggle', { method: 'POST', headers }); location.reload(); } catch (e) {}
        },
        async deleteLink(id) {
            if (!confirm('Delete this link?')) return;
            try { await fetch('/publish/links/' + id, { method: 'DELETE', headers }); location.reload(); } catch (e) {}
        },
        async addSitemap() {
            this.savingSitemap = true; this.sitemapResult = '';
            try {
                const r = await fetch('{{ route("publish.sitemaps.store") }}', { method: 'POST', headers, body: JSON.stringify(this.newSitemap) });
                const d = await r.json(); this.sitemapSuccess = d.success; this.sitemapResult = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch (e) { this.sitemapSuccess = false; this.sitemapResult = 'Error: ' + e.message; }
            this.savingSitemap = false;
        },
        async refreshSitemap(id) {
            try { const r = await fetch('/publish/sitemaps/' + id + '/refresh', { method: 'POST', headers }); const d = await r.json(); alert(d.message); if (d.success) location.reload(); } catch (e) { alert('Error: ' + e.message); }
        },
        async deleteSitemap(id) {
            if (!confirm('Delete this sitemap?')) return;
            try { await fetch('/publish/sitemaps/' + id, { method: 'DELETE', headers }); location.reload(); } catch (e) {}
        }
    };
}
</script>
@endpush
