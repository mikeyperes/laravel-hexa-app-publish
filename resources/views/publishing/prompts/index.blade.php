{{-- Prompts --}}
@extends('layouts.app')
@section('title', 'Prompts')
@section('header', 'AI Prompts')

@section('content')
<div class="space-y-4" x-data="promptsManager()">

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
                        <a :href="'{{ route('publish.prompts.index') }}?user_id=' + user.id"
                           class="block px-3 py-2 text-sm hover:bg-gray-50" x-text="user.name + ' (' + user.email + ')'"></a>
                    </template>
                </div>
            </div>
            @if(request('user_id'))
                <a href="{{ route('publish.prompts.index') }}" class="text-sm text-red-500 hover:text-red-700">Clear filter</a>
            @endif
        </div>
        <button @click="showNew = !showNew" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Prompt</button>
    </div>

    {{-- New prompt form --}}
    <div x-show="showNew" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Create New Prompt</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Prompt Name</label>
                <input type="text" x-model="newPrompt.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Editorial Spin v2">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Assign to User</label>
                <div class="relative">
                    <input type="text" x-model="newUserQuery" @input.debounce.300ms="searchNewUsers()" placeholder="Type to search..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <div x-show="newUserResults.length > 0" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="user in newUserResults" :key="user.id">
                            <button type="button" @click="newPrompt.user_id = user.id; newUserQuery = user.name; newUserResults = []"
                                    class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" x-text="user.name + ' (' + user.email + ')'"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-xs text-gray-500 mb-1">Prompt Content</label>
            <textarea id="new-prompt-editor" x-model="newPrompt.content" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Enter the AI prompt instructions..."></textarea>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button @click="savePrompt()" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Creating...' : 'Create Prompt'"></span>
            </button>
        </div>
        <div x-show="result" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <span x-text="result"></span>
        </div>
    </div>

    {{-- Results count --}}
    <p class="text-sm text-gray-500">{{ $prompts->count() }} prompt(s)</p>

    {{-- Prompt cards --}}
    @if($prompts->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No prompts created yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($prompts as $prompt)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="{ expanded: false, editing: false, editName: '{{ addslashes($prompt->name) }}', editContent: {{ json_encode($prompt->content) }}, editSaving: false, editResult: '', editSuccess: false }">
                <div class="px-5 py-4 flex items-center justify-between cursor-pointer" @click="expanded = !expanded">
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-800 break-words">{{ $prompt->name }}</h4>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $prompt->user->name ?? 'Unassigned' }} -- Updated {{ $prompt->updated_at->diffForHumans() }}</p>
                        <p x-show="!expanded" class="text-sm text-gray-500 mt-1 break-words">{{ \Illuminate\Support\Str::limit(strip_tags($prompt->content), 150) }}</p>
                    </div>
                    <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                        <button @click.stop="editing = !editing; expanded = true" class="text-xs text-blue-600 hover:text-blue-800">Edit</button>
                        <button @click.stop="$dispatch('delete-prompt', { id: {{ $prompt->id }}, name: '{{ addslashes($prompt->name) }}' })" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                <div x-show="expanded" x-cloak class="px-5 pb-4 border-t border-gray-100 pt-3">
                    <template x-if="!editing">
                        <div class="text-sm text-gray-700 break-words" style="white-space: pre-wrap;">{!! nl2br(e($prompt->content)) !!}</div>
                    </template>
                    <template x-if="editing">
                        <div>
                            <div class="mb-3">
                                <label class="block text-xs text-gray-500 mb-1">Prompt Name</label>
                                <input type="text" x-model="editName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div class="mb-3">
                                <label class="block text-xs text-gray-500 mb-1">Content</label>
                                <textarea x-model="editContent" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="
                                    editSaving = true; editResult = '';
                                    fetch('/publishing/prompts/{{ $prompt->id }}', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ name: editName, content: editContent }) })
                                        .then(r => r.json()).then(d => { editSuccess = d.success; editResult = d.message; if (d.success) setTimeout(() => location.reload(), 600); })
                                        .catch(e => { editSuccess = false; editResult = 'Error: ' + e.message; })
                                        .finally(() => editSaving = false);
                                " :disabled="editSaving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                                    <svg x-show="editSaving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span x-text="editSaving ? 'Saving...' : 'Save Changes'"></span>
                                </button>
                                <button @click="editing = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</button>
                            </div>
                            <div x-show="editResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="editSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                <span x-text="editResult"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function promptsManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showNew: false,
        newPrompt: { name: '', user_id: '', content: '' },
        newUserQuery: '', newUserResults: [],
        saving: false, result: '', success: false,
        init() {
            window.addEventListener('delete-prompt', (e) => {
                this.deletePrompt(e.detail.id, e.detail.name);
            });
        },
        async searchNewUsers() {
            if (this.newUserQuery.length < 2) { this.newUserResults = []; return; }
            try {
                const r = await fetch('{{ route("publish.users.search") }}?q=' + encodeURIComponent(this.newUserQuery), { headers: { 'Accept': 'application/json' } });
                this.newUserResults = await r.json();
            } catch(e) { this.newUserResults = []; }
        },
        async savePrompt() {
            // Sync TinyMCE if active
            if (typeof tinymce !== 'undefined' && tinymce.get('new-prompt-editor')) {
                this.newPrompt.content = tinymce.get('new-prompt-editor').getContent();
            }
            this.saving = true; this.result = '';
            try {
                const r = await fetch('{{ route("publish.prompts.store") }}', { method: 'POST', headers, body: JSON.stringify(this.newPrompt) });
                const d = await r.json(); this.success = d.success; this.result = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch(e) { this.success = false; this.result = 'Error: ' + e.message; }
            this.saving = false;
        },
        async deletePrompt(id, name) {
            if (!confirm('Delete prompt "' + name + '"?')) return;
            try { await fetch('/publishing/prompts/' + id, { method: 'DELETE', headers }); location.reload(); } catch(e) { alert('Error: ' + e.message); }
        }
    };
}
</script>

{{-- TinyMCE CDN for WYSIWYG editing --}}
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#new-prompt-editor',
            height: 300,
            menubar: false,
            plugins: 'lists link code',
            toolbar: 'undo redo | bold italic | bullist numlist | link code',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; }',
        });
    }
});
</script>
@endpush
