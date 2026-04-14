@extends('layouts.app')
@section('title', 'Campaign Presets')
@section('header', 'Campaign Presets')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="campaignPresets()">

    {{-- Create / Edit form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-show="showForm" x-cloak>
        <h3 class="font-semibold text-gray-800 mb-4" x-text="editId ? 'Edit Preset' : 'New Campaign Preset'"></h3>

        {{-- User (admin section) --}}
        <div class="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-xs font-semibold text-yellow-700 mb-2">Admin — Assign to User</p>
            <div class="max-w-md"
                 @hexa-search-selected.window="if ($event.detail.component_id === 'preset-user') form.user_id = $event.detail.item.id"
                 @hexa-search-cleared.window="if ($event.detail.component_id === 'preset-user') form.user_id = null">
                <x-hexa-smart-search
                    url="{{ route('api.search.users') }}"
                    name="user_id"
                    placeholder="Search users..."
                    display-field="name"
                    subtitle-field="email"
                    value-field="id"
                    id="preset-user"
                    show-id
                />
            </div>
        </div>

        <div class="space-y-4">
            {{-- Name --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Preset Name</label>
                <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Tech News Daily">
            </div>

            {{-- Final Article Method --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Final Article Method</label>
                <select x-model="form.final_article_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                    @foreach($finalArticleMethods as $method)
                        <option value="{{ $method }}">{{ ucwords(str_replace('-', ' ', $method)) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Search terms --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Search Terms / Prompts <span class="text-gray-400">(one per line, one is chosen at random per run)</span></label>
                <textarea x-model="keywordsText" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Find breaking news in entertainment&#10;Latest AI startup funding news&#10;Trending local education stories"></textarea>
            </div>

            {{-- Local preference --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Local News Preference <span class="text-gray-400">(city or state)</span></label>
                <input type="text" x-model="form.local_preference" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md" placeholder="e.g. New York, California">
            </div>

            {{-- Source method --}}
            <div>
                <label class="block text-xs text-gray-500 mb-2">Default Source Method</label>
                <div class="flex gap-4">
                    @foreach($discoveryModes as $mode)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" x-model="form.source_method" value="{{ $mode }}" class="text-blue-600">
                            <span class="text-sm">{{ ucwords(str_replace('-', ' ', $mode)) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Genre --}}
            <div x-show="form.source_method === 'genre'">
                <label class="block text-xs text-gray-500 mb-1">Genre</label>
                <select x-model="form.genre" class="border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                    <option value="">Select genre...</option>
                    @foreach($newsCategories as $cat)
                        <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Trending categories --}}
            <div x-show="form.source_method === 'trending'">
                <label class="block text-xs text-gray-500 mb-2">Trending Categories</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($newsCategories as $cat)
                        <button type="button" @click="toggleTrendingCat('{{ $cat }}')"
                            class="px-3 py-1 rounded-full text-xs font-medium transition-colors"
                            :class="form.trending_categories.includes('{{ $cat }}') ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">
                            {{ ucfirst($cat) }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Auto-select toggle --}}
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-700">Automatically Publish</p>
                    <p class="text-xs text-gray-400">System handles everything: topics, articles, photos, spinning, publishing. No manual steps.</p>
                </div>
                <button @click="form.auto_select_sources = !form.auto_select_sources" type="button"
                    class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                    :class="form.auto_select_sources ? 'bg-green-500' : 'bg-gray-300'"
                    role="switch" :aria-checked="form.auto_select_sources">
                    <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        :class="form.auto_select_sources ? 'translate-x-7' : 'translate-x-0'"></span>
                </button>
            </div>

            {{-- AI Instructions --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Additional Instructions (AI feed)</label>
                <textarea x-model="form.ai_instructions" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Custom AI instructions that will be fed into the prompt when this campaign runs..."></textarea>
            </div>

            {{-- Save --}}
            <div class="flex gap-3">
                <button @click="savePreset()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                    <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="saving ? 'Saving...' : (editId ? 'Update Preset' : 'Create Preset')"></span>
                </button>
                <button @click="resetForm()" class="text-gray-500 hover:text-gray-700 px-3 py-2 text-sm">Cancel</button>
            </div>

            <div x-show="saveResult" x-cloak class="p-3 rounded-lg text-sm border" :class="saveSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" x-text="saveResult"></div>
        </div>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Presets ({{ $presets->total() }})</h3>
            <button @click="showForm = true; editId = null; resetFormFields()" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700">+ New Preset</button>
        </div>
        @forelse($presets as $preset)
        <div class="p-4 border-b border-gray-100 hover:bg-gray-50 flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 break-words">{{ $preset->name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    Method: <span class="font-medium">{{ ucfirst(str_replace('-', ' ', $preset->source_method)) }}</span>
                    @if($preset->final_article_method) &middot; Output: {{ ucwords(str_replace('-', ' ', $preset->final_article_method)) }} @endif
                    @if($preset->genre) &middot; Genre: {{ ucfirst($preset->genre) }} @endif
                    @if($preset->local_preference) &middot; Local: {{ $preset->local_preference }} @endif
                    @if($preset->user) &middot; User: {{ $preset->user->name }} @endif
                    @if($preset->auto_select_sources) &middot; <span class="text-green-600">Auto-select</span> @endif
                </p>
                @if($preset->keywords && count($preset->keywords) > 0)
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($preset->keywords as $kw)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] bg-blue-50 text-blue-700">{{ $kw }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @if($preset->is_default)
                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700">Default</span>
                @endif
                <button @click="toggleDefault({{ $preset->id }})" class="text-xs {{ $preset->is_default ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800' }}">{{ $preset->is_default ? 'Unset Default' : 'Set Default' }}</button>
                <button @click="editPreset({{ json_encode($preset) }})" class="text-xs text-blue-600 hover:text-blue-800">Edit</button>
                <button @click="deletePreset({{ $preset->id }}, '{{ addslashes($preset->name) }}')" class="text-xs text-red-400 hover:text-red-600">Delete</button>
            </div>
        </div>
        @empty
        <div class="p-8 text-center text-gray-400 text-sm">No campaign presets yet.</div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
function campaignPresets() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showForm: false,
        editId: null,
        saving: false, saveResult: '', saveSuccess: false,
        keywordsText: '',
        form: { user_id: null, name: '', final_article_method: 'news-search', keywords: [], local_preference: '', source_method: 'keyword', genre: '', trending_categories: [], auto_select_sources: false, ai_instructions: '' },

        init() {
            @if(isset($editPreset) && $editPreset)
                this.editPreset(@json($editPreset));
            @endif
        },

        toggleTrendingCat(cat) {
            const idx = this.form.trending_categories.indexOf(cat);
            if (idx === -1) this.form.trending_categories.push(cat);
            else this.form.trending_categories.splice(idx, 1);
        },

        resetFormFields() {
            this.form = { user_id: null, name: '', final_article_method: 'news-search', keywords: [], local_preference: '', source_method: 'keyword', genre: '', trending_categories: [], auto_select_sources: false, ai_instructions: '' };
            this.keywordsText = '';
            this.saveResult = '';
        },

        resetForm() { this.showForm = false; this.editId = null; this.resetFormFields(); },

        editPreset(preset) {
            this.editId = preset.id;
            this.form = {
                user_id: preset.user_id,
                name: preset.name,
                final_article_method: preset.final_article_method || 'news-search',
                keywords: preset.keywords || [],
                local_preference: preset.local_preference || '',
                source_method: preset.source_method || 'keyword',
                genre: preset.genre || '',
                trending_categories: preset.trending_categories || [],
                auto_select_sources: preset.auto_select_sources || false,
                ai_instructions: preset.ai_instructions || '',
            };
            this.keywordsText = (preset.keywords || []).join('\n');
            this.showForm = true;
        },

        async savePreset() {
            this.saving = true; this.saveResult = '';
            this.form.keywords = this.keywordsText.split('\n').map(k => k.trim()).filter(k => k);
            const url = this.editId ? '/campaigns/presets/' + this.editId : '{{ route("campaigns.presets.store") }}';
            const method = this.editId ? 'PUT' : 'POST';
            try {
                const r = await fetch(url, { method, headers, body: JSON.stringify(this.form) });
                const d = await r.json();
                this.saveSuccess = d.success;
                this.saveResult = d.message || (d.success ? 'Saved.' : 'Error.');
                if (d.success && d.preset?.id) {
                    history.replaceState(null, '', '{{ route("campaigns.presets.index") }}?id=' + d.preset.id);
                    setTimeout(() => location.reload(), 800);
                }
            } catch(e) { this.saveSuccess = false; this.saveResult = 'Error: ' + e.message; }
            this.saving = false;
        },

        async deletePreset(id, name) {
            if (!confirm('Delete preset "' + name + '"?')) return;
            try {
                const r = await fetch('/campaigns/presets/' + id, { method: 'DELETE', headers });
                const d = await r.json();
                if (d.success) location.reload();
            } catch(e) { alert('Error: ' + e.message); }
        },

        async toggleDefault(id) {
            try {
                const r = await fetch('/campaigns/presets/' + id + '/toggle-default', { method: 'POST', headers });
                const d = await r.json();
                if (d.success) location.reload();
            } catch(e) { alert('Error: ' + e.message); }
        },
    };
}
</script>
@endpush
@endsection
