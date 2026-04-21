@extends('layouts.app')
@section('title', 'Campaign Presets')
@section('header', 'Campaign Presets')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="campaignPresets()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-show="showForm" x-cloak>
        <h3 class="font-semibold text-gray-800 mb-4" x-text="editId ? 'Edit Campaign Preset' : 'New Campaign Preset'"></h3>

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
            <div>
                <label class="block text-xs text-gray-500 mb-1">Preset Name</label>
                <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Her Forward Celebrity Breaking">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Search Queries <span class="text-gray-400">(one per line)</span></label>
                <textarea x-model="searchQueriesText" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="celebrity breaking news&#10;celebrity legal filing&#10;celebrity business launch"></textarea>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Campaign Instructions</label>
                <textarea x-model="form.campaign_instructions" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Prioritize stories with a real news peg, not gossip filler."></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Posts Per Run</label>
                    <input type="number" x-model="form.posts_per_run" min="1" max="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Frequency</label>
                    <select x-model="form.frequency" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="hourly">Hourly</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Run At</label>
                    <input type="time" x-model="form.run_at_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Drip Minutes</label>
                    <input type="number" x-model="form.drip_minutes" min="1" max="1440" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

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
                    {{ ($preset->posts_per_run ?? 1) }} post(s) / {{ ucfirst($preset->frequency ?? 'daily') }}
                    @if($preset->run_at_time) &middot; {{ $preset->run_at_time }} @endif
                    &middot; Drip {{ $preset->drip_minutes ?? 60 }} min
                    @if($preset->user) &middot; User: {{ $preset->user->name }} @endif
                </p>
                @if($preset->search_queries && count($preset->search_queries) > 0)
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($preset->search_queries as $query)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] bg-blue-50 text-blue-700">{{ $query }}</span>
                        @endforeach
                    </div>
                @endif
                @if($preset->campaign_instructions)
                    <p class="text-xs text-gray-500 mt-2 break-words">{{ \Illuminate\Support\Str::limit($preset->campaign_instructions, 180) }}</p>
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
        saving: false,
        saveResult: '',
        saveSuccess: false,
        searchQueriesText: '',
        form: {
            user_id: null,
            name: '',
            search_queries: [],
            campaign_instructions: '',
            posts_per_run: 1,
            frequency: 'daily',
            run_at_time: '09:00',
            drip_minutes: 60,
        },

        init() {
            @if(isset($editPreset) && $editPreset)
                this.editPreset(@json($editPreset));
            @endif
        },

        resetFormFields() {
            this.form = {
                user_id: null,
                name: '',
                search_queries: [],
                campaign_instructions: '',
                posts_per_run: 1,
                frequency: 'daily',
                run_at_time: '09:00',
                drip_minutes: 60,
            };
            this.searchQueriesText = '';
            this.saveResult = '';
        },

        resetForm() {
            this.showForm = false;
            this.editId = null;
            this.resetFormFields();
        },

        editPreset(preset) {
            this.editId = preset.id;
            this.form = {
                user_id: preset.user_id,
                name: preset.name || '',
                search_queries: preset.search_queries || preset.keywords || [],
                campaign_instructions: preset.campaign_instructions || preset.ai_instructions || '',
                posts_per_run: preset.posts_per_run || 1,
                frequency: preset.frequency || 'daily',
                run_at_time: preset.run_at_time || '09:00',
                drip_minutes: preset.drip_minutes || 60,
            };
            this.searchQueriesText = (this.form.search_queries || []).join('\n');
            this.showForm = true;
        },

        async savePreset() {
            this.saving = true;
            this.saveResult = '';
            this.form.search_queries = this.searchQueriesText.split('\n').map(v => v.trim()).filter(Boolean);
            const url = this.editId ? '/campaigns/presets/' + this.editId : '{{ route("campaigns.presets.store") }}';
            const method = this.editId ? 'PUT' : 'POST';

            try {
                const r = await fetch(url, { method, headers, body: JSON.stringify(this.form) });
                const d = await r.json();
                this.saveSuccess = d.success;
                this.saveResult = d.message || (d.success ? 'Saved.' : 'Error.');
                if (d.success && d.preset?.id) {
                    history.replaceState(null, '', '{{ route("campaigns.presets.index") }}?id=' + d.preset.id);
                    setTimeout(() => location.reload(), 600);
                }
            } catch (e) {
                this.saveSuccess = false;
                this.saveResult = 'Error: ' + e.message;
            }

            this.saving = false;
        },

        async deletePreset(id, name) {
            if (!confirm('Delete preset "' + name + '"?')) return;
            try {
                const r = await fetch('/campaigns/presets/' + id, { method: 'DELETE', headers });
                const d = await r.json();
                if (d.success) location.reload();
            } catch (e) {
                alert('Error: ' + e.message);
            }
        },

        async toggleDefault(id) {
            try {
                const r = await fetch('/campaigns/presets/' + id + '/toggle-default', { method: 'POST', headers });
                const d = await r.json();
                if (d.success) location.reload();
            } catch (e) {
                alert('Error: ' + e.message);
            }
        },
    };
}
</script>
@endpush
@endsection
