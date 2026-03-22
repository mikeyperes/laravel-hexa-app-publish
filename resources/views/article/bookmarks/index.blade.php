{{-- Bookmarked Articles --}}
@extends('layouts.app')
@section('title', 'Bookmarked Articles')
@section('header', 'Bookmarked Articles')

@section('content')
<div class="space-y-4" x-data="bookmarksManager()">

    {{-- Header row --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 flex-1">
            <label class="text-sm text-gray-500">Filter by user:</label>
            <div class="relative" x-data="{ userQuery: '', userResults: [] }">
                <input type="text" x-model="userQuery" @input.debounce.300ms="
                    if (userQuery.length < 2) { userResults = []; return; }
                    fetch('{{ route('publish.users.search') }}?q=' + encodeURIComponent(userQuery), { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json()).then(d => userResults = d).catch(() => userResults = []);
                " placeholder="Type to search users..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
                <div x-show="userResults.length > 0" x-cloak class="absolute z-10 mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                    <template x-for="user in userResults" :key="user.id">
                        <a :href="'{{ route('publish.bookmarks.index') }}?user_id=' + user.id"
                           class="block px-3 py-2 text-sm hover:bg-gray-50" x-text="user.name + ' (' + user.email + ')'"></a>
                    </template>
                </div>
            </div>
            @if(request('user_id'))
                <a href="{{ route('publish.bookmarks.index') }}" class="text-sm text-red-500 hover:text-red-700">Clear filter</a>
            @endif
        </div>
    </div>

    {{-- Add Bookmark form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between cursor-pointer" @click="showAdd = !showAdd">
            <h3 class="font-semibold text-gray-800">Add Bookmark</h3>
            <span class="text-sm text-blue-600">+ Add</span>
        </div>

        <div x-show="showAdd" x-cloak class="p-5 bg-gray-50 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">URL</label>
                    <input type="url" x-model="newBookmark.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Title</label>
                    <input type="text" x-model="newBookmark.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Article title">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Assign to User</label>
                    <div class="relative">
                        <input type="text" x-model="userSearchQuery" @input.debounce.300ms="searchBookmarkUsers()" placeholder="Type to search..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <div x-show="userSearchResults.length > 0" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            <template x-for="user in userSearchResults" :key="user.id">
                                <button type="button" @click="newBookmark.user_id = user.id; userSearchQuery = user.name; userSearchResults = []"
                                        class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" x-text="user.name + ' (' + user.email + ')'"></button>
                            </template>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Source</label>
                    <select x-model="newBookmark.source" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select source...</option>
                        <option value="google">Google</option>
                        <option value="social">Social Media</option>
                        <option value="rss">RSS Feed</option>
                        <option value="newsletter">Newsletter</option>
                        <option value="direct">Direct</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tags (comma separated)</label>
                    <input type="text" x-model="newBookmark.tags" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="tag1, tag2, tag3">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <input type="text" x-model="newBookmark.notes" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Quick note about this bookmark">
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button @click="saveBookmark()" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="saving ? 'Saving...' : 'Save Bookmark'"></span>
                </button>
            </div>
            <div x-show="result" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="result"></span>
            </div>
        </div>
    </div>

    {{-- Results count --}}
    <p class="text-sm text-gray-500">{{ $bookmarks->total() }} bookmark(s)</p>

    {{-- Bookmarks table --}}
    @if($bookmarks->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No bookmarks saved yet.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">URL</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Source</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Assigned User</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($bookmarks as $bookmark)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2 font-medium break-words">{{ $bookmark->title ?? 'No title' }}</td>
                        <td class="px-5 py-2 text-xs break-words">
                            <a href="{{ $bookmark->url }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                {{ \Illuminate\Support\Str::limit($bookmark->url, 50) }}
                                <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ ucfirst($bookmark->source ?? '--') }}</td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $bookmark->user->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-2 text-xs text-gray-400">{{ $bookmark->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-2">
                            <div class="flex items-center gap-2">
                                <button @click="editBookmark({{ $bookmark->id }}, {{ json_encode($bookmark->only(['url','title','source','tags','notes','user_id'])) }})" class="text-xs text-blue-600 hover:text-blue-800">Edit</button>
                                <button @click="deleteBookmark({{ $bookmark->id }})" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $bookmarks->links() }}</div>
    @endif

    {{-- Edit modal --}}
    <div x-show="editingId" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 w-full max-w-lg mx-4">
            <h3 class="font-semibold text-gray-800 mb-3">Edit Bookmark</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">URL</label>
                    <input type="url" x-model="editData.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Title</label>
                    <input type="text" x-model="editData.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tags</label>
                    <input type="text" x-model="editData.tags" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <input type="text" x-model="editData.notes" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <button @click="updateBookmark()" :disabled="updating" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="updating" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="updating ? 'Saving...' : 'Save Changes'"></span>
                </button>
                <button @click="editingId = null" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</button>
            </div>
            <div x-show="editResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="editSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="editResult"></span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function bookmarksManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showAdd: false,
        newBookmark: { url: '', title: '', user_id: '', source: '', tags: '', notes: '' },
        userSearchQuery: '', userSearchResults: [],
        saving: false, result: '', success: false,
        editingId: null, editData: {}, updating: false, editResult: '', editSuccess: false,
        async searchBookmarkUsers() {
            if (this.userSearchQuery.length < 2) { this.userSearchResults = []; return; }
            try {
                const r = await fetch('{{ route("publish.users.search") }}?q=' + encodeURIComponent(this.userSearchQuery), { headers: { 'Accept': 'application/json' } });
                this.userSearchResults = await r.json();
            } catch(e) { this.userSearchResults = []; }
        },
        async saveBookmark() {
            this.saving = true; this.result = '';
            try {
                const r = await fetch('{{ route("publish.bookmarks.store") }}', { method: 'POST', headers, body: JSON.stringify(this.newBookmark) });
                const d = await r.json(); this.success = d.success; this.result = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch(e) { this.success = false; this.result = 'Error: ' + e.message; }
            this.saving = false;
        },
        editBookmark(id, data) {
            this.editingId = id;
            this.editData = JSON.parse(JSON.stringify(data));
            this.editResult = '';
        },
        async updateBookmark() {
            this.updating = true; this.editResult = '';
            try {
                const r = await fetch('/article/bookmarks/' + this.editingId, { method: 'PUT', headers, body: JSON.stringify(this.editData) });
                const d = await r.json(); this.editSuccess = d.success; this.editResult = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch(e) { this.editSuccess = false; this.editResult = 'Error: ' + e.message; }
            this.updating = false;
        },
        async deleteBookmark(id) {
            if (!confirm('Delete this bookmark?')) return;
            try { await fetch('/article/bookmarks/' + id, { method: 'DELETE', headers }); location.reload(); } catch(e) { alert('Error: ' + e.message); }
        }
    };
}
</script>
@endpush
