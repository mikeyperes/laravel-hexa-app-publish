@extends('layouts.app')
@section('title', 'AI Smart Edit Templates')
@section('header', 'AI Smart Edit Templates')

@section('content')
<div class="max-w-4xl space-y-4" x-data="smartEdits()">

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $templates->count() }} template(s)</p>
        <button @click="showNew = !showNew" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Template</button>
    </div>

    {{-- New template form --}}
    <div x-show="showNew" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Create Template</h3>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Name</label>
                <input type="text" x-model="newTemplate.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Make More Formal">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Category</label>
                <select x-model="newTemplate.category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="tone">Tone</option>
                    <option value="seo">SEO</option>
                    <option value="length">Length</option>
                    <option value="structure">Structure</option>
                    <option value="polish">Polish</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Prompt</label>
                <textarea x-model="newTemplate.prompt" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Instructions for the AI..."></textarea>
            </div>
            <button @click="createTemplate()" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Creating...' : 'Create'"></span>
            </button>
        </div>
        <div x-show="result" x-cloak class="mt-2 text-sm" :class="success ? 'text-green-600' : 'text-red-600'" x-text="result"></div>
    </div>

    {{-- Template cards --}}
    @foreach($templates as $t)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5" x-data="{ editing: false, editData: @json($t) }">
        <div x-show="!editing">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h4 class="font-medium text-gray-800 break-words">{{ $t->name }}</h4>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 mt-1">{{ $t->category }}</span>
                    @if(!$t->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">Inactive</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button @click="editing = true" class="text-gray-400 hover:text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button @click="deleteTemplate({{ $t->id }}, '{{ addslashes($t->name) }}')" class="text-gray-400 hover:text-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-2 break-words">{{ $t->prompt }}</p>
        </div>
        <div x-show="editing" x-cloak class="space-y-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Name</label>
                <input type="text" x-model="editData.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Category</label>
                <select x-model="editData.category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="tone">Tone</option>
                    <option value="seo">SEO</option>
                    <option value="length">Length</option>
                    <option value="structure">Structure</option>
                    <option value="polish">Polish</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Prompt</label>
                <textarea x-model="editData.prompt" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="flex items-center gap-3">
                <button @click="updateTemplate({{ $t->id }}, editData)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Save</button>
                <button @click="editing = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                <label class="flex items-center gap-2 text-sm text-gray-600 ml-auto">
                    <input type="checkbox" x-model="editData.is_active" class="rounded border-gray-300 text-green-600">
                    Active
                </label>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
function smartEdits() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showNew: false,
        saving: false,
        result: '',
        success: false,
        newTemplate: { name: '', prompt: '', category: 'general' },
        async createTemplate() {
            this.saving = true; this.result = '';
            try {
                const r = await fetch('{{ route("publish.smart-edits.store") }}', { method: 'POST', headers, body: JSON.stringify(this.newTemplate) });
                const d = await r.json();
                this.success = d.success; this.result = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch (e) { this.success = false; this.result = 'Error: ' + e.message; }
            this.saving = false;
        },
        async updateTemplate(id, data) {
            try {
                const r = await fetch('/article/smart-edits/' + id, { method: 'PUT', headers, body: JSON.stringify(data) });
                const d = await r.json();
                if (d.success) location.reload();
            } catch (e) { alert('Error: ' + e.message); }
        },
        async deleteTemplate(id, name) {
            if (!confirm('Delete "' + name + '"?')) return;
            try {
                await fetch('/article/smart-edits/' + id, { method: 'DELETE', headers });
                location.reload();
            } catch (e) { alert('Error: ' + e.message); }
        },
    };
}
</script>
@endpush
