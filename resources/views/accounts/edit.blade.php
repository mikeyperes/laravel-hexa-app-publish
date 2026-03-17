{{-- Edit Publishing Account --}}
@extends('layouts.app')
@section('title', 'Edit ' . $account->name)
@section('header', 'Edit Account: ' . $account->name)

@section('content')
<div class="max-w-2xl" x-data="accountEditForm()">

    <p class="text-xs font-mono text-gray-400 mb-4">{{ $account->account_id }}</p>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account Name <span class="text-red-500">*</span></label>
            <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" x-model="form.email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select x-model="form.status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="canceled">Canceled</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
            <select x-model="form.owner_user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">— No Owner —</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
            <input type="text" x-model="form.plan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. Pro, Enterprise">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea x-model="form.notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
        </div>

        {{-- Save button with spinner --}}
        <div class="flex items-center gap-3">
            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
            </button>
            <a href="{{ route('publish.accounts.show', $account->id) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        {{-- Result banner --}}
        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function accountEditForm() {
    return {
        form: {
            name: @json($account->name),
            email: @json($account->email ?? ''),
            status: @json($account->status),
            owner_user_id: @json($account->owner_user_id ?? ''),
            plan: @json($account->plan ?? ''),
            notes: @json($account->notes ?? ''),
        },
        saving: false,
        resultMessage: '',
        resultSuccess: false,
        async save() {
            this.saving = true;
            this.resultMessage = '';
            try {
                const res = await fetch('{{ route("publish.accounts.update", $account->id) }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message || (data.success ? 'Saved.' : 'Failed.');
            } catch (e) {
                this.resultSuccess = false;
                this.resultMessage = 'Error: ' + e.message;
            }
            this.saving = false;
        }
    };
}
</script>
@endpush
