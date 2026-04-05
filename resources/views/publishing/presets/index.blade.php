{{-- WordPress Presets --}}
@extends('layouts.app')
@section('title', 'WordPress Presets')
@section('header', 'WordPress Presets')

@section('content')
<div class="space-y-4" x-data="presetsManager()">

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
        <a href="{{ route('publish.presets.create', request()->only('user_id')) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Preset</a>
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
                        <a href="{{ route('publish.presets.edit', $preset->id) }}" class="text-gray-400 hover:text-blue-600">
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
                <div class="space-y-1 text-xs text-gray-500 mt-2">
                    @if($preset->follow_links)
                        <p><span class="text-gray-400">Links:</span> {{ ucfirst($preset->follow_links) }}</p>
                    @endif
                    @if($preset->image_preference)
                        <p><span class="text-gray-400">Images:</span> {{ $preset->image_preference }}</p>
                    @endif
                    @if($preset->default_publish_action)
                        <p><span class="text-gray-400">Action:</span> {{ ucwords(str_replace('_', ' ', $preset->default_publish_action)) }}</p>
                    @endif
                    @if($preset->article_format)
                        <p><span class="text-gray-400">Format:</span> {{ ucfirst($preset->article_format) }}</p>
                    @endif
                    @if($preset->image_layout)
                        <p><span class="text-gray-400">Layout:</span> {{ $preset->image_layout }}</p>
                    @endif
                    <p><span class="text-gray-400">Categories:</span> {{ $preset->default_category_count ?? 0 }} / <span class="text-gray-400">Tags:</span> {{ $preset->default_tag_count ?? 0 }}</p>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function presetsManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        async deletePreset(id, name) {
            if (!confirm('Delete preset "' + name + '"? This cannot be undone.')) return;
            try {
                const r = await fetch('/publishing/presets/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.success) window.location.reload();
                else alert(d.message || 'Delete failed');
            } catch (e) { alert('Error: ' + e.message); }
        }
    };
}
</script>
@endpush
