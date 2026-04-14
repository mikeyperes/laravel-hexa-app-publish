{{-- Cached site connection status with refresh link --}}
<div x-show="selectedSite" x-cloak class="mt-2">
    <div class="flex items-center gap-2 rounded-lg px-3 py-2" :class="siteConn.status === true ? 'bg-green-50' : (siteConn.status === false ? 'bg-red-50' : 'bg-gray-50')">
        <template x-if="siteConn.testing"><svg class="w-4 h-4 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
        <template x-if="!siteConn.testing && siteConn.status === true"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
        <template x-if="!siteConn.testing && siteConn.status === false"><svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
        <template x-if="!siteConn.testing && siteConn.status === null"><svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
        <span class="text-xs" :class="siteConn.status === true ? 'text-green-700' : (siteConn.status === false ? 'text-red-700' : 'text-gray-500')" x-text="siteConn.testing ? 'Testing connection...' : (siteConn.status === true ? 'Connected' : (siteConn.status === false ? siteConn.message : 'Unknown'))"></span>
        <span class="text-gray-300">|</span>
        <span class="text-xs text-gray-400" x-text="selectedSite.url"></span>
        <span x-show="selectedSite.wp_username" class="text-gray-300">|</span>
        <span x-show="selectedSite.wp_username" class="text-xs text-gray-500" x-text="'Posting as: ' + selectedSite.wp_username"></span>
        <span x-show="selectedSite.connection_type" class="text-xs text-gray-400" x-text="'(' + (selectedSite.connection_type === 'wptoolkit' ? 'SSH' : 'REST API') + ')'"></span>
        <button x-show="!siteConn.testing" @click="refreshSiteConnection()" class="text-xs text-blue-500 hover:text-blue-700 ml-auto flex-shrink-0">Refresh</button>
    </div>
    <div x-show="siteConn.log.length > 0" x-cloak class="mt-2 bg-gray-900 rounded-lg border border-gray-700 p-3 max-h-32 overflow-y-auto">
        <template x-for="(entry, idx) in siteConn.log" :key="idx">
            <div class="flex items-start gap-2 py-0.5 text-xs font-mono">
                <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                <span :class="{ 'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info' }" x-text="entry.message" class="break-words"></span>
            </div>
        </template>
    </div>
</div>
