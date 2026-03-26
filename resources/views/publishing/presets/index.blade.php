{{-- Article Presets --}}
@extends('layouts.app')
@section('title', $editingPreset ? 'Edit Preset: ' . $editingPreset->name : 'Article Presets')
@section('header', $editingPreset ? 'Edit Preset: ' . $editingPreset->name : 'Article Presets')

@section('content')
<div class="space-y-4" x-data="presetsManager()">

    @if($editingPreset)
    {{-- Edit preset form (full page, not modal) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">Edit Preset</h3>
            <a href="{{ route('publish.presets.index', request()->only('user_id')) }}" class="text-sm text-gray-500 hover:text-gray-700">Back to list</a>
        </div>

        {{-- Account section --}}
        <div class="mb-4 pb-4 border-b border-gray-200">
            <label class="block text-xs text-gray-500 mb-1">Account <span class="text-red-500">*</span></label>
            <select x-model="editData.user_id" class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Select user...</option>
                @foreach(\hexa_core\Models\User::orderBy('name')->get() as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Preset Name</label>
                <input type="text" x-model="editData.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Follow / Nofollow</label>
                <div class="flex items-center gap-4 mt-1">
                    <label class="flex items-center gap-1 text-sm"><input type="radio" x-model="editData.follow_links" value="follow"> Follow</label>
                    <label class="flex items-center gap-1 text-sm"><input type="radio" x-model="editData.follow_links" value="nofollow"> Nofollow</label>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Image Preference</label>
                <select x-model="editData.image_preference" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select preference...</option>
                    @foreach($imagePreferences as $pref)
                        <option value="{{ $pref }}">{{ $pref }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Default Publish Action</label>
                <select x-model="editData.default_publish_action" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select action...</option>
                    @foreach($publishActions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Category Count</label>
                <input type="number" x-model="editData.default_category_count" min="0" max="20" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tag Count</label>
                <input type="number" x-model="editData.default_tag_count" min="0" max="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Image Layout</label>
                <select x-model="editData.image_layout" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select layout...</option>
                    @foreach($imageLayouts as $layout)
                        <option value="{{ $layout }}">{{ $layout }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="inline-flex items-center gap-2 mt-4 cursor-pointer">
            <input type="checkbox" x-model="editData.is_default" class="rounded border-gray-300 text-green-600">
            <span class="text-sm text-gray-600">Set as default preset</span>
        </label>
        <div class="mt-3 flex items-center gap-2">
            <button @click="updatePreset('active')" :disabled="updating" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="updating && saveType === 'active'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="updating && saveType === 'active' ? 'Saving...' : 'Save Changes'"></span>
            </button>
            <button @click="updatePreset('draft')" :disabled="updating" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="updating && saveType === 'draft'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="updating && saveType === 'draft' ? 'Saving Draft...' : 'Save as Draft'"></span>
            </button>
            <a href="{{ route('publish.presets.index', request()->only('user_id')) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
        </div>
        <div x-show="editResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="editSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <span x-text="editResult"></span>
        </div>
    </div>

    @else
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
                        <a :href="'{{ route('publish.presets.index') }}?user_id=' + user.id"
                           class="block px-3 py-2 text-sm hover:bg-gray-50" x-text="user.name + ' (' + user.email + ')'"></a>
                    </template>
                </div>
            </div>
            @if(request('user_id'))
                <a href="{{ route('publish.presets.index') }}" class="text-sm text-red-500 hover:text-red-700">Clear filter</a>
            @endif
        </div>
        <button @click="showNew = !showNew" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Preset</button>
    </div>

    {{-- New preset form --}}
    <div x-show="showNew" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Create New Preset</h3>

        {{-- Account section --}}
        <div class="mb-4 pb-4 border-b border-gray-200">
            <label class="block text-xs text-gray-500 mb-1">Account <span class="text-red-500">*</span></label>
            <select x-model="newPreset.user_id" class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Select user...</option>
                @foreach(\hexa_core\Models\User::orderBy('name')->get() as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Preset Name</label>
                <input type="text" x-model="newPreset.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Standard Editorial">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Default WordPress Site</label>
                <input type="text" x-model="newPreset.default_site_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Site ID (will connect to WP Toolkit)">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Follow / Nofollow</label>
                <div class="flex items-center gap-4 mt-1">
                    <label class="flex items-center gap-1 text-sm">
                        <input type="radio" x-model="newPreset.follow_links" value="follow"> Follow
                    </label>
                    <label class="flex items-center gap-1 text-sm">
                        <input type="radio" x-model="newPreset.follow_links" value="nofollow"> Nofollow
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Image Preference</label>
                <select x-model="newPreset.image_preference" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select preference...</option>
                    @foreach($imagePreferences as $pref)
                        <option value="{{ $pref }}">{{ $pref }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Default Publish Action</label>
                <select x-model="newPreset.default_publish_action" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select action...</option>
                    @foreach($publishActions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Category Count</label>
                <input type="number" x-model="newPreset.default_category_count" min="0" max="20" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="3">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tag Count</label>
                <input type="number" x-model="newPreset.default_tag_count" min="0" max="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="5">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Image Layout</label>
                <select x-model="newPreset.image_layout" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select layout...</option>
                    @foreach($imageLayouts as $layout)
                        <option value="{{ $layout }}">{{ $layout }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="inline-flex items-center gap-2 mt-3 cursor-pointer">
            <input type="checkbox" x-model="newPreset.is_default" class="rounded border-gray-300 text-green-600">
            <span class="text-sm text-gray-600">Set as default preset</span>
        </label>
        <div class="mt-3 flex items-center gap-2">
            <button @click="savePreset('active')" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving && saveType === 'active'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving && saveType === 'active' ? 'Creating...' : 'Create Preset'"></span>
            </button>
            <button @click="savePreset('draft')" :disabled="saving" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving && saveType === 'draft'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving && saveType === 'draft' ? 'Saving Draft...' : 'Save as Draft'"></span>
            </button>
        </div>
        <div x-show="result" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <span x-text="result"></span>
        </div>
    </div>

    {{-- Results count --}}
    <p class="text-sm text-gray-500">{{ $presets->count() }} preset(s)</p>

    {{-- Preset cards --}}
    @if($presets->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No presets created yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($presets as $preset)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-2">
                    <h4 class="font-medium text-gray-800 break-words">{{ $preset->name }}</h4>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                        <a href="{{ route('publish.presets.index', array_merge(request()->only('user_id'), ['edit' => $preset->id])) }}" class="text-gray-400 hover:text-blue-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <button @click="deletePreset({{ $preset->id }}, '{{ addslashes($preset->name) }}')" class="text-gray-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mb-1">{{ $preset->user->name ?? 'Unassigned' }}</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mb-1 {{ ($preset->status ?? 'draft') === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ ucfirst($preset->status ?? 'draft') }}</span>
                @if($preset->is_default)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mb-1">Default</span>
                @endif
                <div class="space-y-1 text-xs text-gray-500">
                    @if($preset->image_preference)
                        <p><span class="text-gray-400">Images:</span> {{ $preset->image_preference }}</p>
                    @endif
                    @if($preset->default_publish_action)
                        <p><span class="text-gray-400">Action:</span> {{ ucwords(str_replace('_', ' ', $preset->default_publish_action)) }}</p>
                    @endif
                    @if($preset->follow_links)
                        <p><span class="text-gray-400">Links:</span> {{ ucfirst($preset->follow_links) }}</p>
                    @endif
                    @if($preset->image_layout)
                        <p><span class="text-gray-400">Layout:</span> {{ $preset->image_layout }}</p>
                    @endif
                    <p><span class="text-gray-400">Categories:</span> {{ $preset->default_category_count }} / <span class="text-gray-400">Tags:</span> {{ $preset->default_tag_count }}</p>
                </div>
            </div>
            @endforeach
        </div>
    @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
function presetsManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showNew: false,
        newPreset: { name: '', is_default: false, user_id: '', default_site_id: '', follow_links: 'follow', image_preference: '', default_publish_action: '', default_category_count: 3, default_tag_count: 5, image_layout: '' },
        newUserQuery: '', newUserResults: [],
        saving: false, saveType: '', result: '', success: false,
        editData: @json($editingPreset ?? (object)[]),
        updating: false, editResult: '', editSuccess: false,
        async searchNewUsers() {
            if (this.newUserQuery.length < 2) { this.newUserResults = []; return; }
            try {
                const r = await fetch('{{ route("publish.users.search") }}?q=' + encodeURIComponent(this.newUserQuery), { headers: { 'Accept': 'application/json' } });
                this.newUserResults = await r.json();
            } catch(e) { this.newUserResults = []; }
        },
        async savePreset(status) {
            this.saving = true; this.saveType = status; this.result = '';
            try {
                const r = await fetch('{{ route("publish.presets.store") }}', { method: 'POST', headers, body: JSON.stringify({ ...this.newPreset, status }) });
                const d = await r.json(); this.success = d.success; this.result = d.message;
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch(e) { this.success = false; this.result = 'Error: ' + e.message; }
            this.saving = false;
        },
        async updatePreset(status) {
            this.updating = true; this.saveType = status; this.editResult = '';
            try {
                const r = await fetch('/publishing/presets/' + this.editData.id, { method: 'PUT', headers, body: JSON.stringify({ ...this.editData, status }) });
                const d = await r.json(); this.editSuccess = d.success; this.editResult = d.message;
                if (d.success) setTimeout(() => { window.location.href = '{{ route("publish.presets.index") }}'; }, 600);
            } catch(e) { this.editSuccess = false; this.editResult = 'Error: ' + e.message; }
            this.updating = false;
        },
        async toggleDefault(id, event) {
            try {
                const r = await fetch('/publishing/presets/' + id + '/toggle-default', { method: 'POST', headers });
                const d = await r.json();
                if (d.success) setTimeout(() => location.reload(), 400);
            } catch(e) { alert('Error: ' + e.message); }
        },
        async deletePreset(id, name) {
            if (!confirm('Delete preset "' + name + '"?')) return;
            try { await fetch('/publishing/presets/' + id, { method: 'DELETE', headers }); location.reload(); } catch(e) { alert('Error: ' + e.message); }
        }
    };
}
</script>
@endpush
