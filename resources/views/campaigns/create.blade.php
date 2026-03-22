{{-- Create Campaign --}}
@extends('layouts.app')
@section('title', 'Create Campaign')
@section('header', 'Create Campaign')

@section('content')
<div class="max-w-3xl" x-data="campaignForm()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

        {{-- Identity --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User <span class="text-red-500">*</span></label>
                <select x-model="form.user_id" @change="filterSitesAndTemplates()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select user...</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Site <span class="text-red-500">*</span></label>
                <select x-model="form.publish_site_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select site...</option>
                    <template x-for="s in filteredSites" :key="s.id">
                        <option :value="s.id" x-text="s.name"></option>
                    </template>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Name <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. AI News Daily">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                <select x-model="form.publish_template_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— No Template —</option>
                    <template x-for="t in filteredTemplates" :key="t.id">
                        <option :value="t.id" x-text="t.name"></option>
                    </template>
                </select>
            </div>
        </div>

        {{-- Schedule --}}
        <div class="border-t border-gray-200 pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">Schedule</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Articles Per Interval</label>
                    <input type="number" x-model="form.articles_per_interval" min="1" max="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Interval</label>
                    <select x-model="form.interval_unit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($intervalUnits as $unit)
                            <option value="{{ $unit }}">{{ ucfirst($unit) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Delivery Mode</label>
                    <select x-model="form.delivery_mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($deliveryModes as $mode)
                            <option value="{{ $mode }}">{{ ucwords(str_replace('-', ' ', $mode)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Content rules --}}
        <div class="border-t border-gray-200 pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">Content Rules</p>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Topic / Keywords</label>
                <input type="text" x-model="form.topic" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. artificial intelligence, machine learning">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Article Type Override</label>
                    <select x-model="form.article_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Use Template Default —</option>
                        @foreach($articleTypes as $type)
                            <option value="{{ $type }}">{{ ucwords(str_replace('-', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">AI Engine Override</label>
                    <select x-model="form.ai_engine" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Use Template Default —</option>
                        @foreach($aiEngines as $engine)
                            <option value="{{ $engine }}">{{ ucfirst($engine) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <label class="block text-xs text-gray-500 mb-1">Max Links Per Article</label>
                <input type="number" x-model="form.max_links_per_article" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="5">
            </div>
        </div>

        {{-- Sources --}}
        <div class="border-t border-gray-200 pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">Article Sources</p>
            <div class="flex flex-wrap gap-3">
                @foreach($articleSources as $src)
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" :checked="form.article_sources.includes('{{ $src }}')" @change="toggleArray(form.article_sources, '{{ $src }}')" class="rounded border-gray-300 text-blue-600">
                    {{ ucwords(str_replace('-', ' ', $src)) }}
                </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea x-model="form.description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Creating...' : 'Create Campaign'"></span>
            </button>
            <a href="{{ route('publish.campaigns.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function campaignForm() {
    const allSites = @json($sites->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'user_id' => $s->user_id]));
    const allTemplates = @json($templates->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'user_id' => $t->user_id]));
    return {
        form: {
            user_id: '{{ $preselected_user_id ?? '' }}',
            publish_site_id: '{{ $preselected_site_id ?? '' }}',
            publish_template_id: '',
            name: '', description: '', topic: '',
            article_type: '', ai_engine: '',
            delivery_mode: 'review',
            articles_per_interval: 1, interval_unit: 'daily',
            article_sources: [], max_links_per_article: '',
        },
        allSites, allTemplates,
        filteredSites: [], filteredTemplates: [],
        saving: false, resultMessage: '', resultSuccess: false,
        init() { this.filterSitesAndTemplates(); },
        filterSitesAndTemplates() {
            const uid = this.form.user_id;
            this.filteredSites = uid ? this.allSites.filter(s => s.user_id == uid) : this.allSites;
            this.filteredTemplates = uid ? this.allTemplates.filter(t => t.user_id == uid) : this.allTemplates;
        },
        toggleArray(arr, val) { const i = arr.indexOf(val); if (i === -1) arr.push(val); else arr.splice(i, 1); },
        async save() {
            this.saving = true; this.resultMessage = '';
            try {
                const res = await fetch('{{ route("publish.campaigns.store") }}', {
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
