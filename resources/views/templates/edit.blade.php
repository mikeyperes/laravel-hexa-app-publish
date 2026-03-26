{{-- Edit Template --}}
@extends('layouts.app')
@section('title', 'Edit ' . $template->name)
@section('header', 'Edit Template: ' . $template->name)

@section('content')
<div class="max-w-3xl" x-data="templateEditForm()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

        {{-- Account section --}}
        <div class="pb-4 border-b border-gray-200">
            <label class="block text-sm font-medium text-gray-700 mb-1">Account <span class="text-red-500">*</span></label>
            <select x-model="form.publish_account_id" class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Select user...</option>
                @foreach(\hexa_core\Models\User::orderBy('name')->get() as $u)
                    <option value="{{ $u->id }}" {{ $template->publish_account_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Template Name <span class="text-red-500">*</span></label>
            <input type="text" x-model="form.name" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">AI Company</label>
                <select x-model="selectedCompany" @change="form.ai_engine = ''" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select company...</option>
                    @foreach(['Anthropic', 'OpenAI'] as $company)
                        <option value="{{ $company }}">{{ $company }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">AI Model</label>
                <select x-model="form.ai_engine" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" :disabled="!selectedCompany">
                    <option value="">Select model...</option>
                    @foreach(config('anthropic.available_models', []) as $model)
                        <option value="{{ $model }}" x-show="selectedCompany === 'Anthropic'">{{ $model }}</option>
                    @endforeach
                    @foreach(config('chatgpt.available_models', []) as $model)
                        <option value="{{ $model }}" x-show="selectedCompany === 'OpenAI'">{{ $model }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tone</label>
            <div class="flex flex-wrap gap-3 mt-1">
                @foreach(['Professional', 'Conversational', 'Authoritative', 'Casual', 'Investigative', 'Persuasive'] as $tone)
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" :checked="form.tone.includes('{{ $tone }}')" @change="toggleArray(form.tone, '{{ $tone }}')" class="rounded border-gray-300 text-blue-600">
                    {{ $tone }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Min Words</label>
                <input type="number" x-model="form.word_count_min" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Words</label>
                <input type="number" x-model="form.word_count_max" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Links Per Article</label>
            <input type="number" x-model="form.max_links" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" min="0">
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
            <textarea x-model="form.description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">AI Prompt / Instructions</label>
            <textarea x-model="form.ai_prompt" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"></textarea>
        </div>

        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" x-model="form.is_default" class="rounded border-gray-300 text-green-600">
            <span class="text-sm text-gray-600">Set as default template</span>
        </label>

        <div class="flex items-center gap-3">
            <button @click="save('active')" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving && saveType === 'active'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving && saveType === 'active' ? 'Saving...' : 'Save Changes'"></span>
            </button>
            <button @click="save('draft')" :disabled="saving" class="bg-gray-200 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving && saveType === 'draft'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving && saveType === 'draft' ? 'Saving Draft...' : 'Save as Draft'"></span>
            </button>
            <a href="{{ route('publish.templates.show', $template->id) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function templateEditForm() {
    const allCompanies = {
        'Anthropic': @json(config('anthropic.available_models', [])),
        'OpenAI': @json(config('chatgpt.available_models', [])),
    };
    const currentEngine = @json($template->ai_engine ?? '');
    let detectedCompany = '';
    for (const [company, models] of Object.entries(allCompanies)) {
        if (models.includes(currentEngine)) { detectedCompany = company; break; }
    }
    return {
        companies: allCompanies,
        selectedCompany: detectedCompany,
        form: {
            name: @json($template->name),
            is_default: @json((bool) $template->is_default),
            article_type: @json($template->article_type ?? ''),
            ai_engine: @json($template->ai_engine ?? ''),
            tone: @json(is_array($template->tone) ? $template->tone : ($template->tone ? [$template->tone] : [])),
            word_count_min: @json($template->word_count_min ?? ''),
            word_count_max: @json($template->word_count_max ?? ''),
            max_links: @json($template->max_links ?? ''),
            photo_sources: @json($template->photo_sources ?? []),
            description: @json($template->description ?? ''),
            ai_prompt: @json($template->ai_prompt ?? ''),
        },
        saving: false, saveType: '', resultMessage: '', resultSuccess: false,
        toggleArray(arr, val) {
            const i = arr.indexOf(val);
            if (i === -1) arr.push(val); else arr.splice(i, 1);
        },
        async save(status) {
            this.saving = true; this.saveType = status; this.resultMessage = '';
            try {
                const res = await fetch('{{ route("publish.templates.update", $template->id) }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ ...this.form, status })
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message || (data.success ? 'Saved.' : 'Failed.');
            } catch (e) { this.resultSuccess = false; this.resultMessage = 'Error: ' + e.message; }
            this.saving = false;
        }
    };
}
</script>
@endpush
