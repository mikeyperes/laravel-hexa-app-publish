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

    {{-- ============================================================ --}}
    {{-- cPanel Accounts                                              --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 text-lg mb-4">cPanel Accounts</h3>

        {{-- Sub-section 1: Currently Attached --}}
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-700">Currently Attached (<span x-text="attachedAccounts.length"></span>)</h4>
                <div x-show="attachedAccounts.length > 0" class="flex items-center gap-2">
                    <button @click="detachChecked = attachedAccounts.map(a => a.id)" class="text-xs text-gray-500 hover:text-gray-700">Select All</button>
                    <span class="text-gray-300">|</span>
                    <button @click="detachChecked = []" class="text-xs text-gray-500 hover:text-gray-700">None</button>
                </div>
            </div>

            <template x-if="attachedAccounts.length > 0">
                <div>
                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 mb-3">
                        <template x-for="acct in attachedAccounts" :key="acct.id">
                            <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer border-l-4 border-green-500">
                                <input type="checkbox" :value="acct.id" x-model.number="detachChecked" class="rounded text-red-600">
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <span class="text-sm font-medium text-gray-900 break-words" x-text="acct.domain"></span>
                                    <span class="text-xs text-gray-400" x-text="acct.username"></span>
                                    <span class="text-xs text-gray-400" x-text="acct.hostname"></span>
                                </div>
                            </label>
                        </template>
                    </div>
                    <button @click="detachSelected()" :disabled="detaching || detachChecked.length === 0"
                            class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 border border-red-200 rounded-lg hover:bg-red-50 inline-flex items-center gap-1 disabled:opacity-50">
                        <svg x-show="detaching" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="detaching ? 'Detaching...' : 'Detach Selected (' + detachChecked.length + ')'"></span>
                    </button>
                </div>
            </template>
            <p x-show="attachedAccounts.length === 0" class="text-sm text-gray-400">No cPanel accounts attached.</p>
        </div>

        {{-- Sub-section 2: + Add More Accounts (collapsible) --}}
        <div x-show="availableAccounts.length > 0">
            <button @click="showAttach = !showAttach" class="flex items-center gap-2 w-full text-left py-3 px-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                <svg class="w-4 h-4 text-gray-500 transition-transform" :class="showAttach ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sm font-semibold text-gray-700">+ Add More Accounts</span>
                <span class="text-xs text-gray-400">(<span x-text="availableAccounts.length"></span> available)</span>
            </button>

            <div x-show="showAttach" x-cloak class="mt-3">
                <div class="flex items-center gap-3 mb-2">
                    <input type="text" x-model="attachFilter" placeholder="Filter accounts..." class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm flex-1">
                    <button @click="attachChecked = filteredAvailable.map(a => a.id)" class="text-xs text-gray-500 hover:text-gray-700">Select All</button>
                    <span class="text-gray-300">|</span>
                    <button @click="attachChecked = []" class="text-xs text-gray-500 hover:text-gray-700">None</button>
                </div>
                <div class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto mb-3">
                    <template x-for="acct in filteredAvailable" :key="acct.id">
                        <label class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-b-0">
                            <input type="checkbox" :value="acct.id" x-model.number="attachChecked" class="rounded text-blue-600">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <span class="text-sm text-gray-800 break-words" x-text="acct.domain"></span>
                                <span class="text-xs text-gray-400" x-text="acct.username"></span>
                                <span class="text-xs text-gray-400" x-text="acct.hostname"></span>
                            </div>
                        </label>
                    </template>
                </div>

                <div class="flex items-center gap-3">
                    <button @click="attachSelected()" :disabled="attaching || attachChecked.length === 0"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="attaching" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="attaching ? 'Attaching...' : 'Attach Selected (' + attachChecked.length + ')'"></span>
                    </button>
                    <label class="text-xs text-gray-500 flex items-center gap-1">
                        <input type="checkbox" x-model="includeChildren" class="rounded text-blue-600">
                        Auto-include reseller child accounts
                    </label>
                </div>
            </div>
        </div>

        {{-- Attach/Detach result banner --}}
        <div x-show="attachBanner" x-cloak class="mt-3 px-4 py-2 rounded-lg text-sm inline-flex items-center gap-2"
             :class="attachSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <svg x-show="attachSuccess" class="w-4 h-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <svg x-show="!attachSuccess" class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span x-text="attachBanner"></span>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- WordPress Sites                                              --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 text-lg mb-4">WordPress Sites</h3>

        {{-- Default Website --}}
        <div class="mb-6 pb-4 border-b border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">Default Website</h4>
            <div class="flex items-center gap-3 max-w-md">
                <select x-model="defaultSiteId" @change="saveDefaultSite()" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- No default --</option>
                    <template x-for="s in enabledSites" :key="s.id">
                        <option :value="s.id" x-text="s.name + ' (' + s.url + ')'"></option>
                    </template>
                </select>
                <span x-show="defaultSiteSaved" x-cloak x-transition class="text-xs text-green-600 font-medium">Saved</span>
            </div>
        </div>

        {{-- Sub-section 1: Enabled Sites --}}
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Enabled Sites (<span x-text="enabledSites.length"></span>)</h4>

            <template x-if="enabledSites.length > 0">
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-100">
                    <template x-for="site in enabledSites" :key="site.id">
                        <div class="px-4 py-3 hover:bg-gray-50" x-data="{ testingWrite: false, writeResult: null, loadingAuthors: false, authors: [], selectedAuthor: site.default_author || '', authorSaved: false, authorError: '' }" x-init="
                            loadingAuthors = true;
                            fetch('/publish/sites/' + site.id + '/authors', { headers: { 'Accept': 'application/json' } })
                                .then(r => r.json()).then(d => { authors = d.authors || []; if (d.default_author) selectedAuthor = d.default_author; loadingAuthors = false; })
                                .catch(() => { loadingAuthors = false; });
                        ">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 break-words" x-text="site.name"></p>
                                    <a :href="site.url" target="_blank" class="text-xs text-blue-600 hover:underline break-words inline-flex items-center gap-1">
                                        <span x-text="site.url"></span>
                                        <svg class="w-3 h-3 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                                    <button @click="
                                        testingWrite = true; writeResult = null;
                                        fetch('/publish/sites/' + site.id + '/test-write', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } })
                                            .then(r => r.json()).then(d => { writeResult = d; testingWrite = false; }).catch(() => { writeResult = { success: false, message: 'Network error' }; testingWrite = false; })
                                    " :disabled="testingWrite" class="text-xs px-2 py-1 border rounded inline-flex items-center gap-1" :class="writeResult?.success ? 'border-green-300 text-green-700 bg-green-50' : (writeResult?.success === false ? 'border-red-300 text-red-700 bg-red-50' : 'border-gray-300 text-gray-600 hover:bg-gray-100')">
                                        <svg x-show="testingWrite" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <span x-text="testingWrite ? 'Testing...' : (writeResult?.success ? 'Write OK' : (writeResult?.success === false ? 'Failed' : 'Test Write'))"></span>
                                    </button>
                                    <button @click="removeSite(site.id)" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 border border-red-200 rounded hover:bg-red-50">Remove</button>
                                </div>
                            </div>
                            {{-- Write test error --}}
                            <p x-show="writeResult?.success === false" x-cloak class="text-xs text-red-600 mt-1 break-words" x-text="writeResult?.message"></p>
                            {{-- Default Author dropdown --}}
                            <div class="mt-2 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-purple-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <label class="text-xs text-gray-500 flex-shrink-0">Default Author</label>
                                <svg x-show="loadingAuthors" x-cloak class="w-3 h-3 animate-spin text-purple-500 flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <select x-show="!loadingAuthors && authors.length > 0" x-cloak x-model="selectedAuthor" @change="
                                    authorSaved = false; authorError = '';
                                    fetch('/publish/sites/' + site.id + '/set-author', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }, body: JSON.stringify({ author: selectedAuthor }) })
                                        .then(r => r.json()).then(d => { if (d.success) { authorSaved = true; setTimeout(() => authorSaved = false, 2000); } else { authorError = d.message || 'Save failed'; } })
                                        .catch(e => { authorError = e.message; });
                                " class="border border-gray-300 rounded px-2 py-1 text-xs">
                                    <option value="">— No default —</option>
                                    <template x-for="author in authors" :key="author.user_login">
                                        <option :value="author.user_login" x-text="(author.display_name || author.user_login) + ' (' + author.user_login + ')'"></option>
                                    </template>
                                </select>
                                <span x-show="!loadingAuthors && authors.length === 0" x-cloak class="text-xs text-gray-400">No authors found</span>
                                <span x-show="authorSaved" x-cloak x-transition class="text-xs text-green-600 font-medium">Saved</span>
                                <span x-show="authorError" x-cloak class="text-xs text-red-600" x-text="authorError"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <p x-show="enabledSites.length === 0" class="text-sm text-gray-400">No WordPress sites enabled yet. Scan for sites below.</p>
        </div>

        {{-- Sub-section 2: Scan for WordPress Sites --}}
        <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Scan for WordPress Sites</h4>

            <button @click="scanWordPress()" :disabled="scanning"
                    class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2 mb-3">
                <svg x-show="scanning" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="scanning ? scanStatusText : 'Scan WP Toolkit'"></span>
            </button>

            {{-- Scan activity log (dark themed) --}}
            <div x-show="scanLog.length > 0" x-cloak class="bg-gray-900 rounded-lg border border-gray-700 p-4 mb-4 font-mono text-xs space-y-1 max-h-64 overflow-y-auto">
                <template x-for="(entry, idx) in scanLog" :key="idx">
                    <div class="flex items-start gap-2" :class="{
                        'text-green-400': entry.type === 'success',
                        'text-red-400': entry.type === 'error',
                        'text-gray-400': entry.type === 'info'
                    }">
                        <span x-show="entry.type === 'success'" class="flex-shrink-0">&#10003;</span>
                        <span x-show="entry.type === 'error'" class="flex-shrink-0">&#10007;</span>
                        <span x-show="entry.type === 'info'" class="flex-shrink-0">&#8226;</span>
                        <span x-text="entry.message" class="break-words"></span>
                    </div>
                </template>
            </div>

            {{-- Scan result banner --}}
            <div x-show="scanBanner" x-cloak class="mb-4 px-4 py-2 rounded-lg text-sm inline-flex items-center gap-2"
                 :class="scanSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <svg x-show="scanSuccess" class="w-4 h-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="!scanSuccess" class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span x-text="scanBanner"></span>
            </div>

            {{-- WordPress installs found from scan --}}
            <div x-show="wpInstalls.length === 0 && !scanning && scanLog.length === 0" class="text-sm text-gray-400">
                No WordPress installs found. Attach cPanel accounts above, then click "Scan WP Toolkit".
            </div>

            <div x-show="wpInstalls.length > 0" class="space-y-3">
                <h4 class="text-sm font-semibold text-gray-700" x-text="'Scan Results (' + wpInstalls.length + ' installs found)'"></h4>
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
                                <span x-show="isSiteAdded(install.url)" x-cloak class="text-xs text-green-600 font-medium px-3 py-1.5 inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Added
                                </span>
                                <button x-show="!isSiteAdded(install.url)" @click="addSiteFromScan(idx)" :disabled="addingIdx === idx" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-green-700 whitespace-nowrap disabled:opacity-50 inline-flex items-center gap-1">
                                    <svg x-show="addingIdx === idx" x-cloak class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span x-text="addingIdx === idx ? 'Adding...' : '+ Add as Site'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Presets                                                      --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800 text-lg">Presets ({{ $presets->count() }})</h3>
            <div class="flex items-center gap-3">
                @if(Route::has('publish.presets.index'))
                    <a href="{{ route('publish.presets.index', ['user_id' => $user->id, 'showNew' => 1]) }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">+ New Preset</a>
                    <a href="{{ route('publish.presets.index', ['user_id' => $user->id]) }}" class="text-xs text-gray-500 hover:text-gray-700">View All</a>
                @endif
            </div>
        </div>

        @if($presets->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($presets as $preset)
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                    <h4 class="text-sm font-semibold text-gray-900 break-words mb-2">{{ $preset->name }}</h4>
                    <div class="space-y-1 text-xs text-gray-500">
                        @if($preset->article_format)
                            <p><span class="text-gray-400">Format:</span> {{ $preset->article_format }}</p>
                        @endif
                        @if($preset->tone)
                            <p><span class="text-gray-400">Tone:</span> {{ $preset->tone }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400">No presets created yet.</p>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- Drafted Articles                                             --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 flex items-center justify-between border-b border-gray-200">
            <h3 class="font-semibold text-gray-800 text-lg">Drafted Articles ({{ $drafts->count() }})</h3>
            @if(Route::has('publish.drafts.index'))
                <a href="{{ route('publish.drafts.index') }}" class="text-xs text-gray-500 hover:text-gray-700">View All</a>
            @endif
        </div>

        @if($drafts->isNotEmpty())
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-2 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-6 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-2 text-xs font-medium text-gray-500 uppercase">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($drafts as $draft)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            @if(Route::has('publish.drafts.show'))
                                <a href="{{ route('publish.drafts.show', $draft->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $draft->title ?? 'Untitled' }}</a>
                            @else
                                <span class="text-gray-900 break-words">{{ $draft->title ?? 'Untitled' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <span class="text-xs px-2 py-0.5 rounded font-medium
                                {{ $draft->status === 'draft' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst($draft->status ?? 'draft') }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-xs text-gray-500">{{ $draft->updated_at?->format('M j, Y g:ia') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="px-6 py-4">
                <p class="text-sm text-gray-400">No drafted articles yet.</p>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- Bookmarked Articles                                          --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 flex items-center justify-between border-b border-gray-200">
            <h3 class="font-semibold text-gray-800 text-lg">Bookmarked Articles ({{ $bookmarks->count() }})</h3>
            @if(Route::has('publish.bookmarks.index'))
                <a href="{{ route('publish.bookmarks.index') }}" class="text-xs text-gray-500 hover:text-gray-700">View All</a>
            @endif
        </div>

        @if($bookmarks->isNotEmpty())
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-2 text-xs font-medium text-gray-500 uppercase">URL</th>
                        <th class="px-6 py-2 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-6 py-2 text-xs font-medium text-gray-500 uppercase">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($bookmarks as $bookmark)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <a href="{{ $bookmark->url }}" target="_blank" class="text-blue-600 hover:underline break-words inline-flex items-center gap-1">
                                {{ \Illuminate\Support\Str::limit($bookmark->url, 60) }}
                                <svg class="w-3 h-3 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </td>
                        <td class="px-6 py-3 text-gray-700 break-words">{{ $bookmark->title ?? '—' }}</td>
                        <td class="px-6 py-3 text-xs text-gray-500">{{ $bookmark->created_at?->format('M j, Y g:ia') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="px-6 py-4">
                <p class="text-sm text-gray-400">No bookmarked articles yet.</p>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- Article Templates                                            --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800 text-lg">Article Templates ({{ $templates->count() }})</h3>
            @if(Route::has('publish.templates.index'))
                <a href="{{ route('publish.templates.index') }}" class="text-xs text-gray-500 hover:text-gray-700">View All</a>
            @endif
        </div>

        @if($templates->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($templates as $template)
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                    <h4 class="text-sm font-semibold text-gray-900 break-words">{{ $template->name }}</h4>
                    @if($template->article_type)
                        <p class="text-xs text-gray-500 mt-1">{{ $template->article_type }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400">No article templates created yet.</p>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- Campaigns                                                    --}}
    {{-- ============================================================ --}}
    @if($campaigns->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800 text-lg">Campaigns ({{ $campaigns->count() }})</h3>
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
                    <td class="px-5 py-2"><a href="{{ route('campaigns.show', $campaign->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $campaign->name }}</a></td>
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
        showAttach: false,
        attachFilter: '',
        attachChecked: [],
        detachChecked: [],

        // cPanel accounts (Alpine-driven)
        attachedAccounts: @json($attachedAccountsJson),
        availableAccounts: @json($availableAccountsJson),

        get filteredAvailable() {
            if (!this.attachFilter) return this.availableAccounts;
            const q = this.attachFilter.toLowerCase();
            return this.availableAccounts.filter(a => (a.domain + ' ' + a.username + ' ' + a.hostname).toLowerCase().includes(q));
        },

        // Enabled sites (Alpine-driven)
        enabledSites: @json($sitesJson),
        defaultSiteId: '{{ $defaultSiteId ?? '' }}',
        defaultSiteSaved: false,

        scanning: false,
        scanBanner: '',
        scanSuccess: false,
        scanLog: [],
        scanStatusText: 'Scanning...',
        wpInstalls: [],
        addingIdx: -1,

        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',

        isSiteAdded(url) {
            return this.enabledSites.some(s => s.url === url);
        },

        async attachSelected() {
            if (this.attachChecked.length === 0) {
                this.attachSuccess = false;
                this.attachBanner = 'Select at least one account.';
                return;
            }
            this.attaching = true;
            this.attachBanner = '';
            try {
                const res = await fetch('{{ route("publish.accounts.attach", $user->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ hosting_account_ids: this.attachChecked, include_children: this.includeChildren }),
                });
                const data = await res.json();
                this.attachSuccess = data.success;
                this.attachBanner = data.message;
                if (data.success && data.attached) {
                    data.attached.forEach(a => {
                        this.attachedAccounts.push(a);
                        this.availableAccounts = this.availableAccounts.filter(av => av.id !== a.id);
                    });
                    this.attachChecked = [];
                }
            } catch (e) {
                this.attachSuccess = false;
                this.attachBanner = 'Error: ' + e.message;
            } finally {
                this.attaching = false;
            }
        },

        detaching: false,

        async detachSelected() {
            if (this.detachChecked.length === 0) {
                this.attachSuccess = false;
                this.attachBanner = 'Select accounts to detach.';
                return;
            }
            this.detaching = true;
            this.attachBanner = '';
            const ids = [...this.detachChecked];
            try {
                const results = await Promise.all(ids.map(id =>
                    fetch('/publish/users/{{ $user->id }}/detach-account/' + id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    }).then(r => r.json())
                ));
                const successCount = results.filter(r => r.success).length;
                this.attachSuccess = true;
                this.attachBanner = successCount + ' account(s) detached.';
                // Move detached accounts from attached → available
                ids.forEach(id => {
                    const acct = this.attachedAccounts.find(a => a.id === id);
                    if (acct) this.availableAccounts.push(acct);
                });
                this.attachedAccounts = this.attachedAccounts.filter(a => !ids.includes(a.id));
                this.detachChecked = [];
            } catch (e) {
                this.attachSuccess = false;
                this.attachBanner = 'Error: ' + e.message;
            } finally {
                this.detaching = false;
            }
        },

        async addSiteFromScan(idx) {
            const install = this.wpInstalls[idx];
            if (!install) return;
            this.addingIdx = idx;
            try {
                const res = await fetch('/publish/users/{{ $user->id }}/add-site', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
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
                if (data.success && data.site) {
                    this.enabledSites.push({ id: data.site.id, name: data.site.name, url: data.site.url });
                }
            } catch (e) {
                this.scanSuccess = false;
                this.scanBanner = 'Error: ' + e.message;
            }
            this.addingIdx = -1;
        },

        async removeSite(siteId) {
            try {
                const res = await fetch('/publish/users/{{ $user->id }}/remove-site/' + siteId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.scanSuccess = data.success;
                this.scanBanner = data.message;
                if (data.success) {
                    this.enabledSites = this.enabledSites.filter(s => s.id !== siteId);
                    if (String(this.defaultSiteId) === String(siteId)) {
                        this.defaultSiteId = '';
                    }
                }
            } catch (e) {
                this.scanSuccess = false;
                this.scanBanner = 'Error: ' + e.message;
            }
        },

        async saveDefaultSite() {
            this.defaultSiteSaved = false;
            try {
                const resp = await fetch('{{ route("publish.accounts.update-default-site", $user->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ default_site_id: this.defaultSiteId || null })
                });
                const data = await resp.json();
                if (data.success) {
                    this.defaultSiteSaved = true;
                    setTimeout(() => this.defaultSiteSaved = false, 2000);
                }
            } catch (e) {}
        },

        async scanWordPress() {
            const accounts = this.attachedAccounts;
            if (accounts.length === 0) {
                this.scanSuccess = false;
                this.scanBanner = 'No cPanel accounts attached. Attach accounts first.';
                return;
            }

            this.scanning = true;
            this.scanBanner = '';
            this.scanLog = [];
            this.wpInstalls = [];

            this.scanLog.push({ type: 'info', message: 'Starting WordPress scan for ' + accounts.length + ' account(s)...' });

            let totalFound = 0;
            let allInstalls = [];

            for (let i = 0; i < accounts.length; i++) {
                const acct = accounts[i];
                this.scanStatusText = 'Scanning account ' + (i + 1) + ' of ' + accounts.length + '...';
                this.scanLog.push({ type: 'info', message: 'Scanning ' + acct.username + ' (' + acct.domain + ')...' });

                try {
                    const res = await fetch('{{ route("publish.accounts.scan-wp-single", $user->id) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                        body: JSON.stringify({ hosting_account_id: acct.id }),
                    });
                    const data = await res.json();

                    if (data.success) {
                        const count = (data.installs || []).length;
                        totalFound += count;
                        allInstalls = allInstalls.concat(data.installs || []);
                        this.scanLog.push({ type: 'success', message: acct.username + ': ' + count + ' install(s) found' });
                    } else {
                        this.scanLog.push({ type: 'error', message: acct.username + ': ' + (data.message || 'Unknown error') });
                    }
                } catch (e) {
                    this.scanLog.push({ type: 'error', message: acct.username + ': ' + e.message });
                }
            }

            this.wpInstalls = allInstalls;
            this.scanSuccess = true;
            this.scanBanner = 'Scan complete. ' + totalFound + ' WordPress install(s) found across ' + accounts.length + ' account(s).';
            this.scanLog.push({ type: 'info', message: 'Scan complete. Total installs found: ' + totalFound });
            this.scanning = false;
        },
    };
}
</script>
@endpush
@endsection
