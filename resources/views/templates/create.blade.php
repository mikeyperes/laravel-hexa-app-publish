{{-- Create Template --}}
@extends('layouts.app')
@section('title', 'Create Template')
@section('header', 'Create Article Template')

@section('content')
<div class="max-w-3xl" x-data="templateForm()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account <span class="text-red-500">*</span></label>
                <select x-model="form.publish_account_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select account...</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" {{ ($preselected_account_id ?? '') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Template Name <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Tech Press Release">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Article Type</label>
                <select x-model="form.article_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— None —</option>
                    @foreach($articleTypes as $type)
                        <option value="{{ $type }}">{{ ucwords(str_replace('-', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">AI Engine</label>
                <select x-model="form.ai_engine" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Default —</option>
                    @foreach($aiEngines as $engine)
                        <option value="{{ $engine }}">{{ ucfirst($engine) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tone</label>
            <input type="text" x-model="form.tone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. formal, casual, authoritative, conversational">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Min Words</label>
                <input type="number" x-model="form.word_count_min" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="800">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Words</label>
                <input type="number" x-model="form.word_count_max" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="1500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Photos Per Article</label>
                <input type="number" x-model="form.photos_per_article" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="2" min="0" max="20">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Links Per Article</label>
            <input type="number" x-model="form.max_links" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="5" min="0">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Photo Sources</label>
            <div class="flex flex-wrap gap-3 mt-1">
                @foreach($photoSources as $src)
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" :checked="form.photo_sources.includes('{{ $src }}')" @change="toggleArray(form.photo_sources, '{{ $src }}')" class="rounded border-gray-300 text-blue-600">
                    {{ ucfirst($src) }}
                </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea x-model="form.description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="What this template is for..."></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">AI Prompt / Instructions</label>
            <textarea x-model="form.ai_prompt" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="Custom instructions for the AI when using this template. e.g. 'Write in third person, include expert quotes, mention the company in the first paragraph...'"></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Creating...' : 'Create Template'"></span>
            </button>
            <a href="{{ route('publish.templates.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function templateForm() {
    return {
        form: {
            publish_account_id: '{{ $preselected_account_id ?? '' }}',
            name: '', article_type: '', ai_engine: '', tone: '',
            word_count_min: '', word_count_max: '', photos_per_article: '',
            max_links: '', photo_sources: [], description: '', ai_prompt: '',
        },
        saving: false, resultMessage: '', resultSuccess: false,
        toggleArray(arr, val) {
            const i = arr.indexOf(val);
            if (i === -1) arr.push(val); else arr.splice(i, 1);
        },
        async save() {
            this.saving = true; this.resultMessage = '';
            try {
                const res = await fetch('{{ route("publish.templates.store") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message || (data.success ? 'Created.' : 'Failed.');
                if (data.redirect) setTimeout(() => window.location.href = data.redirect, 600);
            } catch (e) { this.resultSuccess = false; this.resultMessage = 'Error: ' + e.message; }
            this.saving = false;
        }
    };
}
</script>
@endpush
