{{-- Edit Campaign --}}
@extends('layouts.app')
@section('title', 'Edit ' . $campaign->name)
@section('header', 'Edit Campaign: ' . $campaign->name)

@section('content')
<div class="max-w-3xl" x-data="campaignEditForm()">

    <p class="text-xs font-mono text-gray-400 mb-4">{{ $campaign->campaign_id }}</p>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Name <span class="text-red-500">*</span></label>
            <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

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

        <div>
            <label class="block text-xs text-gray-500 mb-1">Topic / Keywords</label>
            <input type="text" x-model="form.topic" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Article Type Override</label>
                <select x-model="form.article_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Template Default —</option>
                    @foreach($articleTypes as $type)
                        <option value="{{ $type }}">{{ ucwords(str_replace('-', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">AI Engine Override</label>
                <select x-model="form.ai_engine" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Template Default —</option>
                    @foreach($aiEngines as $engine)
                        <option value="{{ $engine }}">{{ ucfirst($engine) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Max Links Per Article</label>
            <input type="number" x-model="form.max_links_per_article" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Article Sources</label>
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

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea x-model="form.notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
            </button>
            <a href="{{ route('campaigns.show', $campaign->id) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function campaignEditForm() {
    return {
        form: {
            name: @json($campaign->name),
            articles_per_interval: @json($campaign->articles_per_interval),
            interval_unit: @json($campaign->interval_unit),
            delivery_mode: @json($campaign->delivery_mode),
            topic: @json($campaign->topic ?? ''),
            article_type: @json($campaign->article_type ?? ''),
            ai_engine: @json($campaign->ai_engine ?? ''),
            max_links_per_article: @json($campaign->max_links_per_article ?? ''),
            article_sources: @json($campaign->article_sources ?? []),
            description: @json($campaign->description ?? ''),
            notes: @json($campaign->notes ?? ''),
        },
        saving: false, resultMessage: '', resultSuccess: false,
        toggleArray(arr, val) { const i = arr.indexOf(val); if (i === -1) arr.push(val); else arr.splice(i, 1); },
        async save() {
            this.saving = true; this.resultMessage = '';
            try {
                const res = await fetch('{{ route("campaigns.update", $campaign->id) }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(this.form)
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
