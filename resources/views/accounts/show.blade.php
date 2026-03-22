{{-- User Publishing Profile --}}
@extends('layouts.app')
@section('title', $user->name . ' — Publishing Profile')
@section('header', $user->name . ' — Publishing Profile')

@section('content')
<div class="space-y-6" x-data="userProfile()">

    {{-- User header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $user->name }}</h2>
                <p class="text-sm text-gray-500 break-words mt-1">{{ $user->email }}</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 mt-1">{{ $user->role ?? 'user' }}</span>
            </div>
            @if(Route::has('settings.users.edit'))
                <a href="{{ route('settings.users.edit', $user->id) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Edit User</a>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $attachedAccounts->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">cPanel Accounts</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $sites->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Sites</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $articleStats['total'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Articles</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $articleStats['published'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Published</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $campaigns->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Campaigns</p>
        </div>
    </div>

    {{-- cPanel Accounts --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">cPanel Accounts</h3>

        {{-- Attached accounts --}}
        @if($attachedAccounts->isNotEmpty())
            <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 mb-4">
                @foreach($attachedAccounts as $acct)
                <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-900">{{ $acct->domain }}</span>
                        <span class="text-xs text-gray-400">{{ $acct->username }}</span>
                        <span class="text-xs text-gray-400">{{ $acct->whmServer->hostname ?? '' }}</span>
                        @if($acct->is_reseller)
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 font-medium">Reseller ({{ $acct->child_count }} accounts)</span>
                        @endif
                    </div>
                    <button @click="detachAccount({{ $acct->id }})" class="text-xs text-red-400 hover:text-red-600 px-2 py-1">Detach</button>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400 mb-4">No cPanel accounts attached.</p>
        @endif

        {{-- Attach accounts (checkboxes) --}}
        @if($availableAccounts->isNotEmpty())
        <div x-data="{ showAttach: false }">
            <button @click="showAttach = !showAttach" class="text-sm text-blue-600 hover:text-blue-800 mb-3" x-text="showAttach ? 'Hide account list' : '+ Attach cPanel Accounts'"></button>

            <div x-show="showAttach" x-cloak>
                <div class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto mb-3">
                    @foreach($availableAccounts as $acct)
                    <label class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-b-0">
                        <input type="checkbox" value="{{ $acct->id }}" class="attach-checkbox rounded text-blue-600">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <span class="text-sm text-gray-800 break-words">{{ $acct->domain }}</span>
                            <span class="text-xs text-gray-400">{{ $acct->username }}</span>
                            <span class="text-xs text-gray-400">{{ $acct->whmServer->hostname ?? '' }}</span>
                            @if($acct->is_reseller)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 font-medium">Reseller ({{ $acct->child_count }})</span>
                            @endif
                        </div>
                    </label>
                    @endforeach
                </div>

                <div class="flex items-center gap-3">
                    <button @click="attachSelected()" :disabled="attaching"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="attaching" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="attaching ? 'Attaching...' : 'Attach Selected'"></span>
                    </button>
                    <label class="text-xs text-gray-500 flex items-center gap-1">
                        <input type="checkbox" x-model="includeChildren" class="rounded text-blue-600">
                        Auto-include reseller child accounts
                    </label>
                </div>
            </div>
        </div>
        @endif

        <div x-show="attachBanner" x-cloak class="mt-3 px-4 py-2 rounded-lg text-sm"
             :class="attachSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
             x-text="attachBanner"></div>
    </div>

    {{-- WordPress Sites (from WP Toolkit) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">WordPress Sites</h3>
            <button @click="scanWordPress()" :disabled="scanning"
                    class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="scanning" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="scanning ? 'Scanning...' : 'Scan WP Toolkit'"></span>
            </button>
        </div>

        <div x-show="scanBanner" x-cloak class="mb-4 px-4 py-2 rounded-lg text-sm"
             :class="scanSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
             x-text="scanBanner"></div>

        {{-- Scan errors --}}
        <template x-if="scanErrors.length > 0">
            <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-600">
                <template x-for="err in scanErrors"><div x-text="err"></div></template>
            </div>
        </template>

        {{-- WordPress installs list --}}
        <div x-show="wpInstalls.length === 0 && !scanning" class="text-sm text-gray-400">
            No WordPress installs found. Attach cPanel accounts and click "Scan WP Toolkit".
        </div>

        <div x-show="wpInstalls.length > 0" class="space-y-3">
            <template x-for="(install, idx) in wpInstalls" :key="idx">
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            {{-- Site URL --}}
                            <div class="flex items-center gap-2 mb-2">
                                <a :href="install.url" target="_blank" class="text-base font-semibold text-blue-600 hover:underline break-words" x-text="install.url"></a>
                                <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                <span class="text-xs px-2 py-0.5 rounded font-medium flex-shrink-0"
                                      :class="install.status === 'active' || install.status === 'Working' ? 'bg-green-100 text-green-700' : install.status === 'error' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'"
                                      x-text="install.status || 'unknown'"></span>
                            </div>

                            {{-- Details grid --}}
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1 text-xs">
                                <div>
                                    <span class="text-gray-400">cPanel:</span>
                                    <span class="text-gray-700 font-medium ml-1" x-text="install.cpanel_user"></span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Domain:</span>
                                    <span class="text-gray-700 ml-1" x-text="install.cpanel_domain"></span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Server:</span>
                                    <span class="text-gray-700 ml-1" x-text="install.server_name"></span>
                                </div>
                                <div>
                                    <span class="text-gray-400">WordPress:</span>
                                    <span class="text-gray-700 font-medium ml-1" x-text="'v' + (install.version || '?')"></span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Path:</span>
                                    <span class="text-gray-600 font-mono ml-1" x-text="install.path"></span>
                                </div>
                                <div>
                                    <span class="text-gray-400">PHP:</span>
                                    <span class="text-gray-700 ml-1" x-text="install.php_version || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-gray-400">ID:</span>
                                    <span class="text-gray-600 font-mono ml-1" x-text="install.id || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Auto-update:</span>
                                    <span class="text-gray-700 ml-1" x-text="install.auto_update || '—'"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Action button --}}
                        <div class="flex-shrink-0">
                            <button @click="selectSite(install)" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-green-700 whitespace-nowrap">
                                + Add as Site
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Campaigns --}}
    @if($campaigns->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Campaigns ({{ $campaigns->count() }})</h3>
        </div>
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Campaign</th>
                    <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Site</th>
                    <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($campaigns as $campaign)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2"><a href="{{ route('publish.campaigns.show', $campaign->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $campaign->name }}</a></td>
                    <td class="px-5 py-2 text-gray-500 break-words">{{ $campaign->site->name ?? '—' }}</td>
                    <td class="px-5 py-2">
                        <span class="text-xs {{ $campaign->status === 'active' ? 'text-green-600' : 'text-gray-400' }}">{{ ucfirst($campaign->status ?? 'draft') }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@push('scripts')
<script>
function userProfile() {
    return {
        attaching: false,
        attachBanner: '',
        attachSuccess: false,
        includeChildren: true,

        scanning: false,
        scanBanner: '',
        scanSuccess: false,
        scanErrors: [],
        wpInstalls: [],

        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',

        async attachSelected() {
            const checked = document.querySelectorAll('.attach-checkbox:checked');
            if (checked.length === 0) {
                this.attachSuccess = false;
                this.attachBanner = 'Select at least one account.';
                return;
            }
            const ids = Array.from(checked).map(cb => parseInt(cb.value));
            this.attaching = true;
            this.attachBanner = '';
            try {
                const res = await fetch('{{ route("publish.accounts.attach", $user->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ hosting_account_ids: ids, include_children: this.includeChildren }),
                });
                const data = await res.json();
                this.attachSuccess = data.success;
                this.attachBanner = data.message;
                if (data.success) setTimeout(() => location.reload(), 800);
            } catch (e) {
                this.attachSuccess = false;
                this.attachBanner = 'Error: ' + e.message;
            } finally {
                this.attaching = false;
            }
        },

        async detachAccount(accountId) {
            if (!confirm('Detach this cPanel account?')) return;
            try {
                const res = await fetch('/publish/users/{{ $user->id }}/detach-account/' + accountId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) location.reload();
                else { this.attachSuccess = false; this.attachBanner = data.message; }
            } catch (e) {
                this.attachSuccess = false;
                this.attachBanner = 'Error: ' + e.message;
            }
        },

        async selectSite(install) {
            try {
                const res = await fetch('/publish/users/{{ $user->id }}/add-site', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        url: install.url,
                        name: install.name || install.url,
                        hosting_account_id: install.hosting_account_id,
                        wordpress_install_id: install.id,
                        path: install.path,
                    }),
                });
                const data = await res.json();
                this.scanSuccess = data.success;
                this.scanBanner = data.message;
                if (data.success) setTimeout(() => location.reload(), 800);
            } catch (e) {
                this.scanSuccess = false;
                this.scanBanner = 'Error: ' + e.message;
            }
        },

        async scanWordPress() {
            this.scanning = true;
            this.scanBanner = '';
            this.scanErrors = [];
            this.wpInstalls = [];
            try {
                const res = await fetch('{{ route("publish.accounts.scan-wp", $user->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                const data = await res.json();
                this.scanSuccess = data.success;
                this.scanBanner = data.message;
                this.wpInstalls = data.installs || [];
                this.scanErrors = data.errors || [];
            } catch (e) {
                this.scanSuccess = false;
                this.scanBanner = 'Error: ' + e.message;
            } finally {
                this.scanning = false;
            }
        },
    };
}
</script>
@endpush
@endsection
