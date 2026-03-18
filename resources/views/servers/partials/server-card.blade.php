{{-- Server card for publish app --}}
@php
    $whmLink = $server->whm_url ?: ('https://' . $server->hostname . ':' . ($server->port ?: 2087));
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="{
    testing: false, testResult: null,
    refreshing: false, refreshResult: null,
    statsUpdatedAt: {{ Js::from($server->server_info_updated_at?->toIso8601String() ?? '') }},
    refreshedAgo: {{ Js::from($server->server_info_updated_at?->diffForHumans() ?? 'never') }},
    lastSyncedAt: {{ Js::from($server->last_synced_at?->toIso8601String() ?? '') }},
    lastSyncedAgo: {{ Js::from($server->last_synced_at?->diffForHumans() ?? 'never') }},
    acctCount: {{ $server->whm_account_count ?? $server->account_count ?? 0 }},
    acctMax: {{ $server->license_max_accounts ?? 0 }},
    ramTotalKb: {{ $server->ram_total_kb ?? 0 }},
    ramAvailKb: {{ $server->ram_available_kb ?? 0 }},
    diskPartitions: {{ Js::from($server->disk_partitions ?? []) }},
    load1: {{ Js::from($server->load_1 ?? '') }},
    load5: {{ Js::from($server->load_5 ?? '') }},
    load15: {{ Js::from($server->load_15 ?? '') }},
    fmtGb(kb) { return (kb / 1048576).toFixed(1); },
    fmtDiskGb(bytes) { return (bytes / 1073741824).toFixed(1); },
    async runTest() {
        this.testing = true; this.testResult = null;
        try {
            const res = await fetch({{ Js::from(route('publish.servers.test', $server)) }}, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
            });
            this.testResult = await res.json();
        } catch (e) { this.testResult = { success: false, message: 'Network error: ' + e.message }; }
        this.testing = false;
        setTimeout(() => this.testResult = null, 8000);
    },
    async refreshStats() {
        this.refreshing = true; this.refreshResult = null;
        try {
            const res = await fetch({{ Js::from(route('publish.servers.refresh-stats', $server)) }}, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success && data.stats) {
                this.acctCount = data.stats.whm_account_count ?? this.acctCount;
                this.acctMax = data.stats.license_max_accounts ?? this.acctMax;
                this.ramTotalKb = data.stats.ram_total_kb ?? this.ramTotalKb;
                this.ramAvailKb = data.stats.ram_available_kb ?? this.ramAvailKb;
                this.diskPartitions = data.stats.disk_partitions ?? this.diskPartitions;
                this.load1 = data.stats.load_1 ?? this.load1;
                this.load5 = data.stats.load_5 ?? this.load5;
                this.load15 = data.stats.load_15 ?? this.load15;
                this.refreshedAgo = data.stats.refreshed_ago ?? this.refreshedAgo;
            }
            this.refreshResult = { success: data.success, message: data.message };
        } catch (e) { this.refreshResult = { success: false, message: 'Network error: ' + e.message }; }
        this.refreshing = false;
        setTimeout(() => this.refreshResult = null, 5000);
    }
}">

    {{-- Server header --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-lg font-semibold text-gray-900">{{ $server->name }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $server->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                {{ $server->is_active ? 'Active' : 'Inactive' }}
            </span>
            @if($server->whm_version)
                <span class="text-xs text-gray-400">cPanel {{ $server->whm_version }}</span>
            @endif
            <span class="text-xs text-gray-400" x-show="lastSyncedAt" x-cloak>Synced <span x-text="lastSyncedAgo"></span></span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="runTest()" :disabled="testing" class="text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200 disabled:opacity-50 flex items-center gap-1.5">
                <svg x-show="testing" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="testing ? 'Testing...' : 'Test'"></span>
            </button>
            <button type="button" @click="refreshStats()" :disabled="refreshing" class="text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded hover:bg-blue-100 disabled:opacity-50 flex items-center gap-1.5">
                <svg x-show="refreshing" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="refreshing ? 'Refreshing...' : 'Refresh Stats'"></span>
            </button>
        </div>
    </div>

    {{-- Result banners --}}
    <template x-for="r in [testResult, refreshResult].filter(Boolean)" :key="r.message">
        <div class="mx-6 mt-3 rounded-lg px-4 py-2 text-xs flex items-center gap-2"
            :class="r.success ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="r.message" class="break-words"></span>
        </div>
    </template>

    {{-- Server details --}}
    <div class="px-6 py-4">
        <div class="divide-y divide-gray-100 text-sm">
            <div class="flex py-2">
                <span class="text-gray-400 font-medium w-40 shrink-0">Hostname</span>
                <span class="font-mono text-gray-800">{{ $server->hostname }}</span>
            </div>
            <div class="flex py-2">
                <span class="text-gray-400 font-medium w-40 shrink-0">WHM Panel</span>
                <a href="{{ $whmLink }}" target="_blank" class="text-blue-600 hover:underline break-words">{{ $whmLink }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
            </div>
            <div class="flex py-2">
                <span class="text-gray-400 font-medium w-40 shrink-0">Accounts</span>
                <div class="flex items-center gap-2">
                    <span class="font-semibold" x-text="acctCount"></span>
                    <span x-show="acctMax > 0">/ <span x-text="acctMax"></span>
                        <span class="text-gray-400 ml-1" x-text="'(' + Math.round((acctCount / acctMax) * 100) + '%)'"></span>
                    </span>
                </div>
            </div>
            <div class="flex py-2">
                <span class="text-gray-400 font-medium w-40 shrink-0">Auth</span>
                <span class="text-gray-700">{{ $server->auth_type }} &middot; {{ $server->username }}</span>
            </div>
            @if($server->cpu_cores)
            <div class="flex py-2">
                <span class="text-gray-400 font-medium w-40 shrink-0">CPU</span>
                <span class="text-gray-800 font-semibold">{{ $server->cpu_cores }} cores</span>
            </div>
            @endif
            <div class="flex py-2" x-show="ramTotalKb > 0" x-cloak>
                <span class="text-gray-400 font-medium w-40 shrink-0">RAM</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <span class="text-gray-800 font-semibold" x-text="fmtGb(ramTotalKb) + ' GB total'"></span>
                        <span x-show="ramAvailKb > 0" class="flex items-center gap-3">
                            <span class="text-gray-400">&middot;</span>
                            <span :class="{
                                'text-red-600': ((ramTotalKb - ramAvailKb) / ramTotalKb * 100) > 90,
                                'text-yellow-600': ((ramTotalKb - ramAvailKb) / ramTotalKb * 100) > 70,
                                'text-green-600': ((ramTotalKb - ramAvailKb) / ramTotalKb * 100) <= 70
                            }" class="font-medium" x-text="fmtGb(ramTotalKb - ramAvailKb) + ' GB used (' + ((ramTotalKb - ramAvailKb) / ramTotalKb * 100).toFixed(1) + '%)'"></span>
                        </span>
                    </div>
                    <div x-show="ramAvailKb > 0" class="w-full bg-gray-100 rounded-full h-1.5 mt-1.5 max-w-md">
                        <div class="h-1.5 rounded-full" :class="{'bg-red-500': ((ramTotalKb - ramAvailKb) / ramTotalKb * 100) > 90, 'bg-yellow-500': ((ramTotalKb - ramAvailKb) / ramTotalKb * 100) > 70, 'bg-green-500': ((ramTotalKb - ramAvailKb) / ramTotalKb * 100) <= 70}" :style="'width: ' + Math.min((ramTotalKb - ramAvailKb) / ramTotalKb * 100, 100) + '%'"></div>
                    </div>
                </div>
            </div>
            <div class="flex py-2" x-show="load1" x-cloak>
                <span class="text-gray-400 font-medium w-40 shrink-0">Load Average</span>
                <span class="text-gray-800">
                    <span class="font-semibold" x-text="load1"></span> <span class="text-gray-400">(1m)</span>
                    &middot; <span class="font-semibold" x-text="load5 || '—'"></span> <span class="text-gray-400">(5m)</span>
                    &middot; <span class="font-semibold" x-text="load15 || '—'"></span> <span class="text-gray-400">(15m)</span>
                </span>
            </div>
            <template x-for="(part, idx) in diskPartitions" :key="idx">
                <div class="flex py-2 border-t border-gray-100">
                    <span class="text-gray-400 font-medium w-40 shrink-0" x-text="(part.mount || '/') === '/' ? 'Disk (/)' : 'Disk (' + (part.mount || '/') + ')'"></span>
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <span class="text-gray-800 font-semibold" x-text="fmtDiskGb(part.total_bytes || 1) + ' GB total'"></span>
                            <span class="text-gray-400">&middot;</span>
                            <span class="font-medium" :class="{'text-red-600': ((part.used_bytes||0)/(part.total_bytes||1)*100)>90, 'text-yellow-600': ((part.used_bytes||0)/(part.total_bytes||1)*100)>70, 'text-gray-600': ((part.used_bytes||0)/(part.total_bytes||1)*100)<=70}" x-text="fmtDiskGb(part.used_bytes||0) + ' GB used (' + ((part.used_bytes||0)/(part.total_bytes||1)*100).toFixed(1) + '%)'"></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5 mt-1.5 max-w-md">
                            <div class="h-1.5 rounded-full" :class="{'bg-red-500': ((part.used_bytes||0)/(part.total_bytes||1)*100)>90, 'bg-yellow-500': ((part.used_bytes||0)/(part.total_bytes||1)*100)>70, 'bg-green-500': ((part.used_bytes||0)/(part.total_bytes||1)*100)<=70}" :style="'width:'+Math.min((part.used_bytes||0)/(part.total_bytes||1)*100,100)+'%'"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
