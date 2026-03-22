{{-- Create Article (one-off) --}}
@extends('layouts.app')
@section('title', 'New Article')
@section('header', 'New Article')

@section('content')
<div class="max-w-3xl" x-data="articleCreateForm()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User <span class="text-red-500">*</span></label>
                <select x-model="form.user_id" @change="filterSites()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
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

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
            <input type="text" x-model="form.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Article title">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Template</label>
                <select x-model="form.publish_template_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— None —</option>
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Article Type</label>
                <select x-model="form.article_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— None —</option>
                    @foreach($articleTypes as $type)
                        <option value="{{ $type }}">{{ ucwords(str_replace('-', ' ', $type)) }}</option>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Excerpt</label>
            <textarea x-model="form.excerpt" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Short summary..."></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Creating...' : 'Create & Open Editor'"></span>
            </button>
            <a href="{{ route('publish.articles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function articleCreateForm() {
    const allSites = @json($sites->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'user_id' => $s->user_id]));
    return {
        form: {
            user_id: '{{ $preselected_user_id ?? '' }}',
            publish_site_id: '{{ $preselected_site_id ?? '' }}',
            publish_template_id: '', title: '', excerpt: '',
            article_type: '', delivery_mode: 'review',
        },
        allSites, filteredSites: [],
        saving: false, resultMessage: '', resultSuccess: false,
        init() { this.filterSites(); },
        filterSites() {
            const uid = this.form.user_id;
            this.filteredSites = uid ? this.allSites.filter(s => s.user_id == uid) : this.allSites;
        },
        async save() {
            this.saving = true; this.resultMessage = '';
            try {
                const res = await fetch('{{ route("publish.articles.store") }}', {
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
