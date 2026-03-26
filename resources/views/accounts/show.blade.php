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
                <h4 class="text-sm font-semibold text-gray-700">Currently Attached ({{ $attachedAccounts->count() }})</h4>
                @if($attachedAccounts->isNotEmpty())
                    <div class="flex items-center gap-2">
                        <button @click="document.querySelectorAll('.detach-checkbox').forEach(c => c.checked = true)" class="text-xs text-gray-500 hover:text-gray-700">Select All</button>
                        <span class="text-gray-300">|</span>
                        <button @click="document.querySelectorAll('.detach-checkbox').forEach(c => c.checked = false)" class="text-xs text-gray-500 hover:text-gray-700">None</button>
                    </div>
                @endif
            </div>

            @if($attachedAccounts->isNotEmpty())
                <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 mb-3">
                    @foreach($attachedAccounts as $acct)
                    <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer border-l-4 border-green-500">
                        <input type="checkbox" value="{{ $acct->id }}" class="detach-checkbox rounded text-red-600">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <span class="text-sm font-medium text-gray-900 break-words">{{ $acct->domain }}</span>
                            <span class="text-xs text-gray-400">{{ $acct->username }}</span>
                            <span class="text-xs text-gray-400">{{ $acct->whmServer->hostname ?? '' }}</span>
                            @if($acct->is_reseller)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 font-medium">Reseller ({{ $acct->child_count }} accounts)</span>
                            @endif
                        </div>
                    </label>
                    @endforeach
                </div>
                <button @click="detachSelected()" :disabled="detaching"
                        class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 border border-red-200 rounded-lg hover:bg-red-50 inline-flex items-center gap-1">
                    <svg x-show="detaching" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="detaching ? 'Detaching...' : 'Detach Selected'"></span>
                </button>
            @else
                <p class="text-sm text-gray-400">No cPanel accounts attached.</p>
            @endif
        </div>

        {{-- Sub-section 2: + Add More Accounts (collapsible) --}}
        @if($availableAccounts->isNotEmpty())
        <div x-data="{ showAttach: false }">
            <button @click="showAttach = !showAttach" class="flex items-center gap-2 w-full text-left py-3 px-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                <svg class="w-4 h-4 text-gray-500 transition-transform" :class="showAttach ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sm font-semibold text-gray-700">+ Add More Accounts</span>
                <span class="text-xs text-gray-400">({{ $availableAccounts->count() }} available)</span>
            </button>

            <div x-show="showAttach" x-cloak class="mt-3">
                <div class="flex items-center gap-3 mb-2">
                    <input type="text" placeholder="Filter accounts..." class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm flex-1" oninput="document.querySelectorAll('.attach-row').forEach(r => r.style.display = r.textContent.toLowerCase().includes(this.value.toLowerCase()) ? '' : 'none')">
                    <button @click="document.querySelectorAll('.attach-checkbox').forEach(c => c.checked = true)" class="text-xs text-gray-500 hover:text-gray-700">Select All</button>
                    <span class="text-gray-300">|</span>
                    <button @click="document.querySelectorAll('.attach-checkbox').forEach(c => c.checked = false)" class="text-xs text-gray-500 hover:text-gray-700">None</button>
                </div>
                <div class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto mb-3">
                    @foreach($availableAccounts as $acct)
                    <label class="attach-row flex items-center gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-b-0">
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
                        <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 break-words" x-text="site.name"></p>
                                <a :href="site.url" target="_blank" class="text-xs text-blue-600 hover:underline break-words inline-flex items-center gap-1">
                                    <span x-text="site.url"></span>
                                    <svg class="w-3 h-3 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </div>
                            <button @click="removeSite(site.id)" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 border border-red-200 rounded hover:bg-red-50 flex-shrink-0 ml-3">Remove</button>
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
                                <template x-if="isSiteAdded(install.url)">
                                    <span class="text-xs text-green-600 font-medium px-3 py-1.5 inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Added
                                    </span>
                                </template>
                                <template x-if="!isSiteAdded(install.url)">
                                    <button @click="selectSite(install)" :disabled="install.adding" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-green-700 whitespace-nowrap disabled:opacity-50 inline-flex items-center gap-1">
                                        <svg x-show="install.adding" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <span x-text="install.adding ? 'Adding...' : '+ Add as Site'"></span>
                                    </button>
                                </template>
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

        // Enabled sites (Alpine-driven, no reload needed)
        enabledSites: @json($sites->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'url' => $s->url])),
        defaultSiteId: '{{ $defaultSiteId ?? '' }}',
        defaultSiteSaved: false,

        scanning: false,
        scanBanner: '',
        scanSuccess: false,
        scanLog: [],
        scanStatusText: 'Scanning...',
        wpInstalls: [],

        // Attached accounts data for per-account scanning
        attachedAccountsData: @json($attachedAccounts->map(fn($a) => ['id' => $a->id, 'username' => $a->username, 'domain' => $a->domain])),

        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',

        isSiteAdded(url) {
            return this.enabledSites.some(s => s.url === url);
        },

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
                if (data.success && data.attached) {
                    data.attached.forEach(a => {
                        this.attachedAccountsData.push({ id: a.id, username: a.username, domain: a.domain });
                    });
                    this.attachBanner += ' Reload to see updated account list.';
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
            const checked = document.querySelectorAll('.detach-checkbox:checked');
            if (checked.length === 0) {
                this.attachSuccess = false;
                this.attachBanner = 'Select accounts to detach.';
                return;
            }
            this.detaching = true;
            this.attachBanner = '';
            const ids = Array.from(checked).map(cb => parseInt(cb.value));
            try {
                const results = await Promise.all(ids.map(id =>
                    fetch('/publish/users/{{ $user->id }}/detach-account/' + id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    }).then(r => r.json())
                ));
                const successCount = results.filter(r => r.success).length;
                this.attachSuccess = true;
                this.attachBanner = successCount + ' account(s) detached. Reload to see updated account list.';
                // Remove from scan data so future scans don't include them
                const detachedIds = ids;
                this.attachedAccountsData = this.attachedAccountsData.filter(a => !detachedIds.includes(a.id));
            } catch (e) {
                this.attachSuccess = false;
                this.attachBanner = 'Error: ' + e.message;
            } finally {
                this.detaching = false;
            }
        },

        async selectSite(install) {
            install.adding = true;
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
            install.adding = false;
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
            const accounts = this.attachedAccountsData;
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
