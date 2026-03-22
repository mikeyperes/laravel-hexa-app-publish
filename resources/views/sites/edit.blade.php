{{-- Edit Site --}}
@extends('layouts.app')
@section('title', 'Edit ' . $site->name)
@section('header', 'Edit Site: ' . $site->name)

@section('content')
<div class="max-w-2xl" x-data="siteEditForm()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">User <span class="text-red-500">*</span></label>
            <select x-model="form.user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Site Name <span class="text-red-500">*</span></label>
            <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">URL <span class="text-red-500">*</span></label>
            <input type="url" x-model="form.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Connection Type</label>
            <select x-model="form.connection_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="wp_rest_api">WordPress REST API</option>
                <option value="wptoolkit">WP Toolkit (cPanel)</option>
            </select>
        </div>

        <template x-if="form.connection_type === 'wp_rest_api'">
            <div class="space-y-4 border-l-4 border-blue-200 pl-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">WordPress Username</label>
                    <input type="text" x-model="form.wp_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Application Password</label>
                    @if($site->wp_application_password)
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" disabled value="••••••••••••••••" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
                            <button @click="showPasswordChange = !showPasswordChange" class="text-xs text-blue-600 hover:text-blue-800" x-text="showPasswordChange ? 'Cancel' : 'Change'"></button>
                        </div>
                    @endif
                    <input x-show="showPasswordChange || !{{ $site->wp_application_password ? 'true' : 'false' }}" type="password" x-model="form.wp_application_password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="New application password">
                </div>
            </div>
        </template>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea x-model="form.notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
            </button>
            <a href="{{ route('publish.sites.show', $site->id) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function siteEditForm() {
    return {
        form: {
            user_id: @json($site->user_id),
            name: @json($site->name),
            url: @json($site->url),
            connection_type: @json($site->connection_type),
            wp_username: @json($site->wp_username ?? ''),
            wp_application_password: '',
            notes: @json($site->notes ?? ''),
        },
        showPasswordChange: false,
        saving: false, resultMessage: '', resultSuccess: false,
        async save() {
            this.saving = true; this.resultMessage = '';
            try {
                const res = await fetch('{{ route("publish.sites.update", $site->id) }}', {
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
