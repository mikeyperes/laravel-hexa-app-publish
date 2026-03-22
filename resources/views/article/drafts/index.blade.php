{{-- Drafted Articles --}}
@extends('layouts.app')
@section('title', 'Drafted Articles')
@section('header', 'Drafted Articles')

@section('content')
<div class="space-y-4" x-data="draftsManager()">

    {{-- Header row --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 flex-1" x-data="{ userQuery: '', userResults: [], selectedUser: null }">
            <label class="text-sm text-gray-500">Filter by user:</label>
            <div class="relative">
                <input type="text" x-model="userQuery" @input.debounce.300ms="searchUsers()" placeholder="Type to search users..."
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
                <div x-show="userResults.length > 0" x-cloak class="absolute z-10 mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                    <template x-for="user in userResults" :key="user.id">
                        <a :href="'{{ route('publish.drafts.index') }}?user_id=' + user.id"
                           class="block px-3 py-2 text-sm hover:bg-gray-50" x-text="user.name + ' (' + user.email + ')'"></a>
                    </template>
                </div>
            </div>
            @if(request('user_id'))
                <a href="{{ route('publish.drafts.index') }}" class="text-sm text-red-500 hover:text-red-700">Clear filter</a>
            @endif
        </div>
        <button @click="showNewDraft = !showNewDraft" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Draft</button>
    </div>

    {{-- New draft form --}}
    <div x-show="showNewDraft" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Create New Draft</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Title</label>
                <input type="text" x-model="newDraft.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Article title">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Assign to User</label>
                <div class="relative">
                    <input type="text" x-model="newDraftUserQuery" @input.debounce.300ms="searchNewDraftUsers()" placeholder="Type to search..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <div x-show="newDraftUserResults.length > 0" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="user in newDraftUserResults" :key="user.id">
                            <button type="button" @click="newDraft.created_by = user.id; newDraftUserQuery = user.name; newDraftUserResults = []"
                                    class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" x-text="user.name + ' (' + user.email + ')'"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-xs text-gray-500 mb-1">Excerpt</label>
            <textarea x-model="newDraft.excerpt" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Short excerpt or summary"></textarea>
        </div>
        <div class="mt-3">
            <label class="block text-xs text-gray-500 mb-1">Notes</label>
            <textarea x-model="newDraft.notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Internal notes"></textarea>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button @click="saveDraft()" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Creating...' : 'Create Draft'"></span>
            </button>
        </div>
        <div x-show="result" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <span x-text="result"></span>
        </div>
    </div>

    {{-- Results count --}}
    <p class="text-sm text-gray-500">{{ $drafts->total() }} draft(s)</p>

    {{-- Drafts table --}}
    @if($drafts->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No drafts yet. Create one to get started.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Assigned User</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Word Count</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Last Updated</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($drafts as $draft)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2">
                            <a href="{{ route('publish.drafts.show', $draft->id) }}?id={{ $draft->id }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $draft->title ?? 'Untitled' }}</a>
                            <p class="text-xs text-gray-400">{{ $draft->article_id }}</p>
                        </td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $draft->creator->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $draft->word_count ?? 0 }}</td>
                        <td class="px-5 py-2 text-xs text-gray-400">{{ $draft->updated_at->diffForHumans() }}</td>
                        <td class="px-5 py-2">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('publish.drafts.show', $draft->id) }}?id={{ $draft->id }}" class="text-xs text-blue-600 hover:text-blue-800">Edit</a>
                                <button @click="deleteDraft({{ $draft->id }}, '{{ addslashes($draft->title) }}')" class="text-xs text-red-400 hover:text-red-600">Delete</button>
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

@push('scripts')
<script>
function draftsManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showNewDraft: false,
        newDraft: { title: '', excerpt: '', notes: '', created_by: '' },
        newDraftUserQuery: '',
        newDraftUserResults: [],
        saving: false, result: '', success: false,
        async searchUsers() {
            const q = this.$el.querySelector('input[x-model="userQuery"]')?.value || '';
            if (q.length < 2) { this.userResults = []; return; }
            try {
                const r = await fetch('{{ route("publish.users.search") }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                this.userResults = await r.json();
            } catch(e) { this.userResults = []; }
        },
        async searchNewDraftUsers() {
            if (this.newDraftUserQuery.length < 2) { this.newDraftUserResults = []; return; }
            try {
                const r = await fetch('{{ route("publish.users.search") }}?q=' + encodeURIComponent(this.newDraftUserQuery), { headers: { 'Accept': 'application/json' } });
                this.newDraftUserResults = await r.json();
            } catch(e) { this.newDraftUserResults = []; }
        },
        async saveDraft() {
            this.saving = true; this.result = '';
            try {
                const r = await fetch('{{ route("publish.drafts.store") }}', { method: 'POST', headers, body: JSON.stringify(this.newDraft) });
                const d = await r.json(); this.success = d.success; this.result = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch(e) { this.success = false; this.result = 'Error: ' + e.message; }
            this.saving = false;
        },
        async deleteDraft(id, title) {
            if (!confirm('Delete draft "' + title + '"?')) return;
            try {
                await fetch('/article/drafts/' + id, { method: 'DELETE', headers });
                location.reload();
            } catch(e) { alert('Error: ' + e.message); }
        }
    };
}
</script>
@endpush
