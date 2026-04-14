{{-- Add WordPress Site --}}
@extends('layouts.app')
@section('title', 'Add Site — ' . config('hws.app_name'))
@section('header', 'Add WordPress Site')

@section('content')
<div class="max-w-2xl" x-data="siteForm()">

    {{-- Connection Method --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="font-semibold text-gray-800 mb-3">Connection Method</h3>
        <div class="grid grid-cols-2 gap-3">
            <button type="button" @click="method = 'wptoolkit'" class="rounded-xl border-2 p-4 text-left transition-colors" :class="method === 'wptoolkit' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300'">
                <p class="text-sm font-semibold text-gray-800">cPanel + WP Toolkit</p>
                <p class="text-xs text-gray-500 mt-1">Select a cPanel account and auto-discover WordPress installs.</p>
            </button>
            <button type="button" @click="method = 'wp_rest_api'" class="rounded-xl border-2 p-4 text-left transition-colors" :class="method === 'wp_rest_api' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300'">
                <p class="text-sm font-semibold text-gray-800">WordPress REST API</p>
                <p class="text-xs text-gray-500 mt-1">Manual setup with username + application password.</p>
            </button>
        </div>
    </div>

    {{-- WP Toolkit Flow --}}
    <div x-show="method === 'wptoolkit'" x-cloak class="space-y-4 mb-6">
        {{-- Step 1: Select cPanel Account --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-1">1. Select cPanel Account</h3>
            <p class="text-xs text-gray-500 mb-3">Choose a hosting account to scan for WordPress installs.</p>
            <div class="flex gap-3">
                <select x-model="selectedAccountId" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select account...</option>
                    @foreach($hostingAccounts as $ha)
                        <option value="{{ $ha->id }}">{{ $ha->username }} — {{ $ha->domain }}</option>
                    @endforeach
                </select>
                <button type="button" @click="scanInstalls()" :disabled="!selectedAccountId || scanning" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                    <svg x-show="scanning" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="scanning ? 'Scanning...' : 'Scan'"></span>
                </button>
            </div>

            {{-- Scan log --}}
            <div x-show="scanLog.length > 0" x-cloak class="mt-3 bg-gray-900 rounded-lg border border-gray-700 p-3 max-h-32 overflow-y-auto">
                <template x-for="(entry, idx) in scanLog" :key="idx">
                    <div class="text-xs font-mono py-0.5" :class="{ 'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info' }" x-text="entry.message"></div>
                </template>
            </div>
        </div>

        {{-- Step 2: Select WordPress Install --}}
        <div x-show="installs.length > 0" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-1">2. Select WordPress Site</h3>
            <p class="text-xs text-gray-500 mb-3" x-text="installs.length + ' install(s) found. Click to select.'"></p>
            <div class="space-y-2">
                <template x-for="(install, idx) in installs" :key="idx">
                    <button type="button" @click="selectInstall(install)" class="w-full text-left p-3 rounded-lg border-2 transition-colors" :class="selectedInstall?.id === install.id ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-blue-300'">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-800" x-text="install.url"></p>
                                <p class="text-xs text-gray-500" x-text="(install.path || '/') + ' • WP ' + (install.version || '?')"></p>
                            </div>
                            <svg x-show="selectedInstall?.id === install.id" class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Manual REST API Flow --}}
    <div x-show="method === 'wp_rest_api'" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Site URL <span class="text-red-500">*</span></label>
            <input type="url" x-model="form.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://example.com">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">WordPress Username</label>
            <input type="text" x-model="form.wp_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="admin">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Application Password</label>
            <input type="text" x-model="form.wp_application_password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="xxxx xxxx xxxx xxxx">
            <p class="text-xs text-gray-400 mt-1">WordPress admin → Users → Profile → Application Passwords</p>
        </div>
    </div>

    {{-- Common fields --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 space-y-4" x-show="selectedInstall || method === 'wp_rest_api'">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account <span class="text-red-500">*</span></label>
            <select x-model="form.publish_account_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Select account...</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Site Name <span class="text-red-500">*</span></label>
            <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Hexa PR Wire">
        </div>
        <div x-show="method === 'wptoolkit'">
            <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
            <input type="url" x-model="form.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea x-model="form.notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Optional notes"></textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="button" @click="submitSite()" :disabled="saving || !form.name || !form.url || !form.publish_account_id"
                class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2 font-medium">
                <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="saving ? 'Adding...' : 'Add Site'"></span>
            </button>
            <a href="{{ route('publish.sites.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="result" x-cloak class="p-3 rounded-lg text-sm" :class="resultSuccess ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'" x-text="result"></div>
    </div>
</div>

@push('scripts')
<script>
function siteForm() {
    return {
        method: 'wptoolkit',
        selectedAccountId: '',
        scanning: false,
        scanLog: [],
        installs: [],
        selectedInstall: null,
        form: {
            publish_account_id: '{{ $preselected_account_id ?? '' }}',
            name: '',
            url: '',
            connection_type: 'wptoolkit',
            wp_username: '',
            wp_application_password: '',
            hosting_account_id: null,
            wordpress_install_id: null,
            notes: '',
        },
        saving: false,
        result: '',
        resultSuccess: false,

        async scanInstalls() {
            this.scanning = true;
            this.installs = [];
            this.selectedInstall = null;
            this.scanLog = [];
            this.scanLog.push({ type: 'info', message: 'Scanning for WordPress installs...' });

            try {
                const resp = await fetch('{{ route("publish.sites.scan-installs") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ hosting_account_id: parseInt(this.selectedAccountId) }),
                });
                const data = await resp.json();
                if (data.success) {
                    this.installs = data.installs || [];
                    this.scanLog.push({ type: 'success', message: data.message });
                    this.installs.forEach((inst, i) => {
                        this.scanLog.push({ type: 'info', message: (i + 1) + '. ' + inst.url + ' (' + (inst.path || '/') + ')' });
                    });
                } else {
                    this.scanLog.push({ type: 'error', message: data.message || 'Scan failed.' });
                }
            } catch (e) {
                this.scanLog.push({ type: 'error', message: 'Error: ' + e.message });
            }
            this.scanning = false;
        },

        selectInstall(install) {
            this.selectedInstall = install;
            this.form.url = install.url;
            this.form.name = install.name || install.url.replace(/^https?:\/\//, '').replace(/\/$/, '');
            this.form.hosting_account_id = install.hosting_account_id;
            this.form.wordpress_install_id = install.id;
            this.form.connection_type = 'wptoolkit';
        },

        async submitSite() {
            this.saving = true;
            this.result = '';
            this.form.connection_type = this.method;

            try {
                const resp = await fetch('{{ route("publish.sites.store") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await resp.json();
                this.resultSuccess = data.success;
                this.result = data.message;
                if (data.redirect) {
                    setTimeout(() => { window.location.href = data.redirect; }, 1000);
                }
            } catch (e) {
                this.resultSuccess = false;
                this.result = 'Error: ' + e.message;
            }
            this.saving = false;
        },
    };
}
</script>
@endpush
@endsection
