{{-- Publishing Account detail --}}
@extends('layouts.app')
@section('title', $account->name)
@section('header', $account->name)

@section('content')
<div class="space-y-6">

    {{-- Account header card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-mono text-gray-400 mb-1">{{ $account->account_id }}</p>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $account->name }}</h2>
                @if($account->email)
                    <p class="text-sm text-gray-500 break-words mt-1">{{ $account->email }}</p>
                @endif
                <p class="text-sm text-gray-500 mt-1">Owner: {{ $account->owner->name ?? '—' }}</p>
                <p class="text-sm text-gray-500">Plan: {{ $account->plan ?? '—' }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if($account->status === 'active')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                @elseif($account->status === 'suspended')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Suspended</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Canceled</span>
                @endif
                <a href="{{ route('publish.accounts.edit', $account->id) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Edit</a>
            </div>
        </div>
        @if($account->notes)
            <p class="text-sm text-gray-500 mt-3 break-words">{{ $account->notes }}</p>
        @endif
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $account->sites->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Sites</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $account->campaigns->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Campaigns</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $articleStats['total'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Articles</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $articleStats['published'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Published</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-yellow-600">{{ $articleStats['review'] }}</p>
            <p class="text-xs text-gray-500 mt-1">In Review</p>
        </div>
    </div>

    {{-- Sites --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Sites ({{ $account->sites->count() }})</h3>
            <a href="{{ route('publish.sites.create', ['account_id' => $account->id]) }}" class="text-sm text-blue-600 hover:text-blue-800">+ Add Site</a>
        </div>
        @if($account->sites->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No sites added yet.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">URL</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Connection</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($account->sites as $site)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2"><a href="{{ route('publish.sites.show', $site->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $site->name }}</a></td>
                        <td class="px-5 py-2 text-gray-500 break-words"><a href="{{ $site->url }}" target="_blank" class="hover:text-blue-600">{{ $site->url }} <svg class="inline w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $site->connection_type === 'wptoolkit' ? 'WP Toolkit' : 'REST API' }}</td>
                        <td class="px-5 py-2">
                            @if($site->status === 'connected')
                                <span class="text-xs text-green-600 font-medium">Connected</span>
                            @elseif($site->status === 'error')
                                <span class="text-xs text-red-600 font-medium">Error</span>
                            @else
                                <span class="text-xs text-gray-400">Disconnected</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Users --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="accountUsers()">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Users ({{ $account->users->count() }})</h3>
        </div>
        @if($account->users->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No users assigned.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($account->users as $au)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2 break-words">{{ $au->user->name ?? '—' }}</td>
                        <td class="px-5 py-2 text-gray-500 break-words">{{ $au->user->email ?? '—' }}</td>
                        <td class="px-5 py-2">
                            @if($au->role === 'super_admin')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Super Admin</span>
                            @elseif($au->role === 'admin')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Admin</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">User</span>
                            @endif
                        </td>
                        <td class="px-5 py-2">
                            <button @click="removeUser({{ $au->user_id }})" class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Add user form --}}
        <div class="p-5 border-t border-gray-200">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Add User</label>
                    <select x-model="newUserId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select user...</option>
                        @foreach(\hexa_core\Models\User::orderBy('name')->get() as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select x-model="newUserRole" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <button @click="addUser()" :disabled="addingUser || !newUserId" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="addingUser" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="addingUser ? 'Adding...' : 'Add'"></span>
                </button>
            </div>
            <div x-show="userResultMessage" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="userResultSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="userResultMessage"></span>
            </div>
        </div>
    </div>

    {{-- Templates --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Templates ({{ $account->templates->count() }})</h3>
            <a href="{{ route('publish.templates.create', ['account_id' => $account->id]) }}" class="text-sm text-blue-600 hover:text-blue-800">+ New Template</a>
        </div>
        @if($account->templates->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No templates created yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($account->templates as $template)
                <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                    <div>
                        <a href="{{ route('publish.templates.show', $template->id) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm break-words">{{ $template->name }}</a>
                        @if($template->article_type)
                            <span class="ml-2 text-xs text-gray-400">{{ $template->article_type }}</span>
                        @endif
                    </div>
                    <a href="{{ route('publish.templates.edit', $template->id) }}" class="text-gray-400 hover:text-blue-600 text-xs">Edit</a>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Campaigns --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Campaigns ({{ $account->campaigns->count() }})</h3>
            <a href="{{ route('publish.campaigns.create', ['account_id' => $account->id]) }}" class="text-sm text-blue-600 hover:text-blue-800">+ New Campaign</a>
        </div>
        @if($account->campaigns->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No campaigns created yet.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Campaign</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Schedule</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Mode</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($account->campaigns as $campaign)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2"><a href="{{ route('publish.campaigns.show', $campaign->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $campaign->name }}</a></td>
                        <td class="px-5 py-2 text-gray-500 break-words">{{ $campaign->site->name ?? '—' }}</td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $campaign->articles_per_interval }}/{{ $campaign->interval_unit }}</td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $campaign->delivery_mode }}</td>
                        <td class="px-5 py-2">
                            @if($campaign->status === 'active')
                                <span class="text-xs text-green-600 font-medium">Active</span>
                            @elseif($campaign->status === 'paused')
                                <span class="text-xs text-yellow-600 font-medium">Paused</span>
                            @else
                                <span class="text-xs text-gray-400">{{ ucfirst($campaign->status) }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function accountUsers() {
    return {
        newUserId: '',
        newUserRole: 'user',
        addingUser: false,
        userResultMessage: '',
        userResultSuccess: false,
        async addUser() {
            this.addingUser = true;
            this.userResultMessage = '';
            try {
                const res = await fetch('{{ route("publish.accounts.add-user", $account->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: this.newUserId, role: this.newUserRole })
                });
                const data = await res.json();
                this.userResultSuccess = data.success;
                this.userResultMessage = data.message;
                if (data.success) setTimeout(() => location.reload(), 600);
            } catch (e) {
                this.userResultSuccess = false;
                this.userResultMessage = 'Error: ' + e.message;
            }
            this.addingUser = false;
        },
        async removeUser(userId) {
            if (!confirm('Remove this user from the account?')) return;
            try {
                const res = await fetch('{{ route("publish.accounts.show", $account->id) }}/remove-user/' + userId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) location.reload();
                else { this.userResultSuccess = false; this.userResultMessage = data.message; }
            } catch (e) {
                this.userResultSuccess = false;
                this.userResultMessage = 'Error: ' + e.message;
            }
        }
    };
}
</script>
@endpush
