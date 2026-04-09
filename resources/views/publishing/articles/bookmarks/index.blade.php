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

        <div x-show="showAdd" x-cloak class="p-5 bg-gray-50 border-b border-gray-200"
             @hexa-search-selected.window="if ($event.detail.component_id === 'bookmark-user') { newBookmark.user_id = $event.detail.item.id; selectedUserName = $event.detail.item.name; }"
             @hexa-search-cleared.window="if ($event.detail.component_id === 'bookmark-user') { newBookmark.user_id = ''; selectedUserName = ''; }">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Assign to User</label>
                    <x-hexa-smart-search
                        url="{{ route('api.search.users') }}"
                        name="user_id"
                        placeholder="Type to search users..."
                        display-field="name"
                        subtitle-field="email"
                        value-field="id"
                        id="bookmark-user"
                        show-id
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">URL <span class="text-red-500">*</span></label>
                    <input type="url" x-model="newBookmark.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button @click="saveBookmark()" :disabled="saving || !newBookmark.url" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
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

    {{-- ═══ Failed Sources ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 inline-flex items-center gap-2">
                Failed Sources
                <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">{{ $failedSources->total() }}</span>
            </h3>
        </div>
        @if($failedSources->isEmpty())
            <div class="p-5 text-center text-gray-400 text-sm">No failed sources recorded.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Title / URL</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Error</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($failedSources as $failed)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2">
                            @if($failed->title)<p class="font-medium text-gray-800 break-words">{{ $failed->title }}</p>@endif
                            <a href="{{ $failed->url }}" target="_blank" class="text-xs text-blue-500 hover:underline break-all">{{ \Illuminate\Support\Str::limit($failed->url, 60) }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        </td>
                        <td class="px-5 py-2 text-xs text-red-500 break-words">{{ \Illuminate\Support\Str::limit($failed->error_message, 80) }}</td>
                        <td class="px-5 py-2 text-xs text-gray-400">{{ $failed->created_at->format('M j, Y H:i') }}</td>
                        <td class="px-5 py-2">
                            <button @click="deleteFailed({{ $failed->id }})" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3">{{ $failedSources->appends(request()->except('failed_page'))->links() }}</div>
        @endif
    </div>

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
        newBookmark: { url: '', user_id: '' },
        selectedUserName: '',
        saving: false, result: '', success: false,
        editingId: null, editData: {}, updating: false, editResult: '', editSuccess: false,
        // User search handled by hexa-smart-search component
        async saveBookmark() {
            this.saving = true; this.result = '';
            try {
                const r = await fetch('{{ route("publish.bookmarks.store") }}', { method: 'POST', headers, body: JSON.stringify(this.newBookmark) });
                const d = await r.json();
                this.success = d.success;
                this.result = d.message || (d.success ? 'Saved!' : 'Failed.');
                this.saving = false;
                if (d.success && d.bookmark) {
                    this.result = 'Bookmark saved! Fetching title...';
                    // Fire title fetch in background — don't block
                    fetch('/article/bookmarks/' + d.bookmark.id + '/fetch-title', { method: 'POST', headers }).catch(() => {});
                    setTimeout(() => location.reload(), 1200);
                }
            } catch(e) { this.success = false; this.result = 'Error: ' + e.message; this.saving = false; }
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
        },
        async deleteFailed(id) {
            if (!confirm('Remove this failed source?')) return;
            try { await fetch('/article/failed-sources/' + id, { method: 'DELETE', headers }); location.reload(); } catch(e) { alert('Error: ' + e.message); }
        }
    };
}
</script>
@endpush
