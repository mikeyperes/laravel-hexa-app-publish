{{-- Master Publishing Settings --}}
@extends('layouts.app')
@section('title', 'Publishing Settings')
@section('header', 'Publishing Settings')

@section('content')
<div class="space-y-6" x-data="masterSettingsManager()">

    <div class="bg-yellow-50 rounded-lg border border-yellow-200 p-3 text-sm text-yellow-800">
        <strong>Admin only</strong> -- These guidelines are system-wide and control how AI generates and publishes content. Users cannot see these settings.
    </div>

    {{-- ═══ WordPress Publishing Guidelines ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">WordPress Publishing Guidelines</h3>
            <button @click="showNewWp = !showNewWp" class="text-sm text-blue-600 hover:text-blue-800">+ New Document</button>
        </div>

        {{-- New WP guideline form --}}
        <div x-show="showNewWp" x-cloak class="p-5 bg-gray-50 border-b border-gray-200">
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Document Name</label>
                <input type="text" x-model="newWp.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Default WordPress Content Rules">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Content</label>
                <textarea id="new-wp-editor" x-model="newWp.content" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Enter the WordPress publishing guidelines..."></textarea>
            </div>
            <div class="flex items-center gap-2">
                <button @click="saveNew('wordpress_guidelines', newWp)" :disabled="savingWp" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="savingWp" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="savingWp ? 'Creating...' : 'Create Document'"></span>
                </button>
            </div>
            <div x-show="wpResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="wpSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="wpResult"></span>
            </div>
        </div>

        @if($wordpressGuidelines->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No WordPress guidelines created yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($wordpressGuidelines as $doc)
                <div class="p-5" x-data="{ editing: false, docName: '{{ addslashes($doc->name) }}', docContent: {{ json_encode($doc->content ?? '') }}, docActive: {{ $doc->is_active ? 'true' : 'false' }}, docSaving: false, docResult: '', docSuccess: false }">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3 flex-1">
                            <template x-if="!editing">
                                <h4 class="font-medium text-gray-800 break-words">{{ $doc->name }}</h4>
                            </template>
                            <template x-if="editing">
                                <input type="text" x-model="docName" class="border border-gray-300 rounded-lg px-3 py-1 text-sm flex-1">
                            </template>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $doc->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $doc->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                            <button @click="editing = !editing" class="text-xs text-blue-600 hover:text-blue-800" x-text="editing ? 'Cancel' : 'Edit'"></button>
                            <button @click="toggleActive({{ $doc->id }}, !docActive)" class="text-xs" :class="docActive ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800'" x-text="docActive ? 'Deactivate' : 'Activate'"></button>
                            <button @click="deleteSetting({{ $doc->id }}, '{{ addslashes($doc->name) }}')" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                        </div>
                    </div>
                    <template x-if="!editing">
                        <div class="text-sm text-gray-600 break-words" style="white-space: pre-wrap;">{{ $doc->content }}</div>
                    </template>
                    <template x-if="editing">
                        <div>
                            <textarea x-model="docContent" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2"></textarea>
                            <div class="flex items-center gap-2">
                                <button @click="
                                    docSaving = true; docResult = '';
                                    fetch('/publishing/settings/{{ $doc->id }}', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ name: docName, content: docContent, is_active: docActive }) })
                                        .then(r => r.json()).then(d => { docSuccess = d.success; docResult = d.message; if (d.success) setTimeout(() => location.reload(), 600); })
                                        .catch(e => { docSuccess = false; docResult = 'Error: ' + e.message; })
                                        .finally(() => docSaving = false);
                                " :disabled="docSaving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                                    <svg x-show="docSaving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span x-text="docSaving ? 'Saving...' : 'Save Changes'"></span>
                                </button>
                            </div>
                            <div x-show="docResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="docSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                <span x-text="docResult"></span>
                            </div>
                        </div>
                    </template>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ Master AI Spinning Guidelines ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Master AI Spinning Guidelines</h3>
            <button @click="showNewSpin = !showNewSpin" class="text-sm text-blue-600 hover:text-blue-800">+ New Document</button>
        </div>

        {{-- New Spinning guideline form --}}
        <div x-show="showNewSpin" x-cloak class="p-5 bg-gray-50 border-b border-gray-200">
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Document Name</label>
                <input type="text" x-model="newSpin.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Master Spinning Rules v1">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Content</label>
                <textarea id="new-spin-editor" x-model="newSpin.content" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Enter the AI spinning/rewriting guidelines..."></textarea>
            </div>
            <div class="flex items-center gap-2">
                <button @click="saveNew('spinning_guidelines', newSpin)" :disabled="savingSpin" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="savingSpin" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="savingSpin ? 'Creating...' : 'Create Document'"></span>
                </button>
            </div>
            <div x-show="spinResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="spinSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="spinResult"></span>
            </div>
        </div>

        @if($spinningGuidelines->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No spinning guidelines created yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($spinningGuidelines as $doc)
                <div class="p-5" x-data="{ editing: false, docName: '{{ addslashes($doc->name) }}', docContent: {{ json_encode($doc->content ?? '') }}, docActive: {{ $doc->is_active ? 'true' : 'false' }}, docSaving: false, docResult: '', docSuccess: false }">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3 flex-1">
                            <template x-if="!editing">
                                <h4 class="font-medium text-gray-800 break-words">{{ $doc->name }}</h4>
                            </template>
                            <template x-if="editing">
                                <input type="text" x-model="docName" class="border border-gray-300 rounded-lg px-3 py-1 text-sm flex-1">
                            </template>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $doc->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $doc->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                            <button @click="editing = !editing" class="text-xs text-blue-600 hover:text-blue-800" x-text="editing ? 'Cancel' : 'Edit'"></button>
                            <button @click="toggleActive({{ $doc->id }}, !docActive)" class="text-xs" :class="docActive ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800'" x-text="docActive ? 'Deactivate' : 'Activate'"></button>
                            <button @click="deleteSetting({{ $doc->id }}, '{{ addslashes($doc->name) }}')" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                        </div>
                    </div>
                    <template x-if="!editing">
                        <div class="text-sm text-gray-600 break-words" style="white-space: pre-wrap;">{{ $doc->content }}</div>
                    </template>
                    <template x-if="editing">
                        <div>
                            <textarea x-model="docContent" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2"></textarea>
                            <div class="flex items-center gap-2">
                                <button @click="
                                    docSaving = true; docResult = '';
                                    fetch('/publishing/settings/{{ $doc->id }}', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ name: docName, content: docContent, is_active: docActive }) })
                                        .then(r => r.json()).then(d => { docSuccess = d.success; docResult = d.message; if (d.success) setTimeout(() => location.reload(), 600); })
                                        .catch(e => { docSuccess = false; docResult = 'Error: ' + e.message; })
                                        .finally(() => docSaving = false);
                                " :disabled="docSaving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                                    <svg x-show="docSaving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span x-text="docSaving ? 'Saving...' : 'Save Changes'"></span>
                                </button>
                            </div>
                            <div x-show="docResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="docSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                <span x-text="docResult"></span>
                            </div>
                        </div>
                    </template>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function masterSettingsManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showNewWp: false, showNewSpin: false,
        newWp: { name: '', content: '' },
        newSpin: { name: '', content: '' },
        savingWp: false, wpResult: '', wpSuccess: false,
        savingSpin: false, spinResult: '', spinSuccess: false,
        async saveNew(type, data) {
            const isWp = type === 'wordpress_guidelines';
            if (isWp) { this.savingWp = true; this.wpResult = ''; }
            else { this.savingSpin = true; this.spinResult = ''; }

            // Sync TinyMCE if active
            const editorId = isWp ? 'new-wp-editor' : 'new-spin-editor';
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                data.content = tinymce.get(editorId).getContent();
            }

            try {
                const r = await fetch('{{ route("publish.settings.master.store") }}', {
                    method: 'POST', headers, body: JSON.stringify({ ...data, type })
                });
                const d = await r.json();
                if (isWp) { this.wpSuccess = d.success; this.wpResult = d.message; }
                else { this.spinSuccess = d.success; this.spinResult = d.message; }
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch(e) {
                if (isWp) { this.wpSuccess = false; this.wpResult = 'Error: ' + e.message; }
                else { this.spinSuccess = false; this.spinResult = 'Error: ' + e.message; }
            }
            if (isWp) this.savingWp = false;
            else this.savingSpin = false;
        },
        async toggleActive(id, newState) {
            try {
                const r = await fetch('/publishing/settings/' + id, {
                    method: 'PUT', headers,
                    body: JSON.stringify({ name: '', is_active: newState })
                });
                const d = await r.json();
                if (d.success) location.reload();
            } catch(e) { alert('Error: ' + e.message); }
        },
        async deleteSetting(id, name) {
            if (!confirm('Delete setting "' + name + '"?')) return;
            try { await fetch('/publishing/settings/' + id, { method: 'DELETE', headers }); location.reload(); } catch(e) { alert('Error: ' + e.message); }
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
            selector: '#new-wp-editor, #new-spin-editor',
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
