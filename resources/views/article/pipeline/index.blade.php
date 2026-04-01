{{-- Publish Article Pipeline — 11-step wizard --}}
@extends('layouts.app')
@section('title', 'Publish Article — #' . $draftId)
@section('header', 'Publish Article — #' . $draftId)

@section('content')
<div class="max-w-6xl mx-auto space-y-4" x-data="publishPipeline()">

    {{-- Session ID + Clear button --}}
    <div class="flex items-center justify-between">
        <p class="text-xs font-mono text-gray-400">Article #{{ $draftId }}</p>
        <div x-show="completedSteps.length > 0" x-cloak>
            <button @click="clearPipeline()" class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 border border-red-200 rounded-lg hover:bg-red-50 inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Clear &amp; Start Over
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Progress bar
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-center gap-1">
            <template x-for="(step, idx) in stepLabels" :key="idx">
                <div class="flex items-center gap-1">
                    <button @click="goToStep(idx + 1)"
                            :class="{
                                'bg-green-500 border-green-500 text-white': completedSteps.includes(idx + 1),
                                'bg-blue-600 border-blue-600 text-white': currentStep === idx + 1 && !completedSteps.includes(idx + 1),
                                'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed': currentStep !== idx + 1 && !completedSteps.includes(idx + 1) && !isStepAccessible(idx + 1),
                                'bg-gray-100 border-gray-300 text-gray-600 hover:border-blue-400': currentStep !== idx + 1 && !completedSteps.includes(idx + 1) && isStepAccessible(idx + 1),
                            }"
                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold transition-colors">
                        <template x-if="completedSteps.includes(idx + 1)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="!completedSteps.includes(idx + 1)">
                            <span x-text="idx + 1"></span>
                        </template>
                    </button>
                    <span class="text-xs font-medium hidden sm:inline"
                          :class="currentStep === idx + 1 ? 'text-blue-700' : 'text-gray-500'"
                          x-text="step"></span>
                    <template x-if="idx < stepLabels.length - 1">
                        <svg class="w-3 h-3 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 1: Select User
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="currentStep === 1 ? 'ring-2 ring-blue-400' : ''">
        <button @click="toggleStep(1)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(1) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(1)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(1)"><span>1</span></template>
                </span>
                <span class="font-semibold text-gray-800">Select User</span>
                <span x-show="selectedUser" x-cloak class="text-sm text-green-600" x-text="selectedUser ? selectedUser.name : ''"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(1) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(1)" x-cloak x-collapse class="px-4 pb-4">
            <div class="relative max-w-md">
                <label class="block text-xs text-gray-500 mb-1">Search by name or email</label>
                <div class="relative">
                    <input type="text" x-model="userSearch" @input.debounce.300ms="searchUsers()"
                           placeholder="Type to search users..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                    <svg x-show="userSearching" x-cloak class="absolute right-3 top-2.5 w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>
                <div x-show="userResults.length > 0" x-cloak class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                    <template x-for="user in userResults" :key="user.id">
                        <button type="button" @click="selectUser(user)"
                                class="block w-full text-left px-3 py-2 text-sm hover:bg-blue-50 transition-colors">
                            <span class="font-medium" x-text="user.name"></span>
                            <span class="text-gray-400 ml-1" x-text="'(' + user.email + ')'"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div x-show="selectedUser" x-cloak class="mt-3 flex items-center gap-2 bg-blue-50 rounded-lg px-3 py-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="text-sm font-medium text-blue-800" x-text="selectedUser ? selectedUser.name + ' (' + selectedUser.email + ')' : ''"></span>
                <button @click="clearUser()" class="ml-auto text-red-500 hover:text-red-700 text-xs">Clear</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 2: Select WordPress Template
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 2, 'opacity-50': !isStepAccessible(2) }">
        <button @click="toggleStep(2)" :disabled="!isStepAccessible(2)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(2) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(2)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(2)"><span>2</span></template>
                </span>
                <span class="font-semibold text-gray-800">Select WordPress Template</span>
                <span x-show="selectedPreset" x-cloak class="text-sm text-green-600" x-text="selectedPreset ? selectedPreset.name : ''"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(2) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(2)" x-cloak x-collapse class="px-4 pb-4">
            {{-- Loading indicator --}}
            <div x-show="presetsLoading" class="flex items-center gap-2 text-sm text-gray-500 py-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Loading presets...
            </div>
            <div x-show="!presetsLoading" class="max-w-md">
                <label class="block text-xs text-gray-500 mb-1">User's Presets</label>
                <select x-model="selectedPresetId" @change="selectPreset()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- No preset (configure manually) --</option>
                    <template x-for="p in presets" :key="p.id">
                        <option :value="p.id" x-text="p.name"></option>
                    </template>
                </select>
            </div>
            <div x-show="selectedPreset" x-cloak class="mt-3 bg-gray-50 rounded-lg p-3 text-sm space-y-1">
                <p><span class="text-gray-500">Tone:</span> <span class="font-medium" x-text="selectedPreset?.tone || 'Not set'"></span></p>
                <p><span class="text-gray-500">Format:</span> <span class="font-medium" x-text="selectedPreset?.article_format || 'Not set'"></span></p>
                <p><span class="text-gray-500">Follow Links:</span> <span class="font-medium" x-text="selectedPreset?.follow_links || 'Not set'"></span></p>
                <p><span class="text-gray-500">Publish Action:</span> <span class="font-medium" x-text="selectedPreset?.default_publish_action || 'Not set'"></span></p>
            </div>
            <div class="mt-3">
                <button @click="completeStep(2); openStep(3)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
                    <span x-text="selectedPreset ? 'Continue with Preset' : 'Skip Preset'"></span> &rarr;
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 3: Select Website
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 3, 'opacity-50': !isStepAccessible(3) }">
        <button @click="toggleStep(3)" :disabled="!isStepAccessible(3)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(3) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(3)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(3)"><span>3</span></template>
                </span>
                <span class="font-semibold text-gray-800">Select Website</span>
                <span x-show="selectedSite && siteConnectionStatus === null" x-cloak class="text-sm text-blue-500 inline-flex items-center gap-1">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="selectedSite?.name + ' — Checking...'"></span>
                </span>
                <span x-show="selectedSite && siteConnectionStatus === true" x-cloak class="text-sm text-green-600" x-text="selectedSite?.name + ' ✓'"></span>
                <span x-show="selectedSite && siteConnectionStatus === false" x-cloak class="text-sm text-red-600" x-text="selectedSite?.name + ' ✗ Connection failed'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(3) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(3)" x-cloak x-collapse class="px-4 pb-4">
            <div class="max-w-md">
                <label class="block text-xs text-gray-500 mb-1">WordPress Site</label>
                <select x-model="selectedSiteId" @change="selectSite()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- Select a site --</option>
                    <template x-for="s in sites" :key="s.id">
                        <option :value="String(s.id)" :selected="String(s.id) === selectedSiteId" x-text="s.name + ' (' + s.url + ')'"></option>
                    </template>
                </select>
            </div>
            <div x-show="selectedSite" x-cloak class="mt-3">
                <div class="flex items-center gap-2 rounded-lg px-3 py-2" :class="siteConnectionStatus === true ? 'bg-green-50' : (siteConnectionStatus === false ? 'bg-red-50' : 'bg-gray-50')">
                    <template x-if="siteConnectionStatus === null">
                        <svg class="w-4 h-4 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </template>
                    <template x-if="siteConnectionStatus === true">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                    <template x-if="siteConnectionStatus === false">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                    <span class="text-sm" :class="siteConnectionStatus === true ? 'text-green-800' : (siteConnectionStatus === false ? 'text-red-800' : 'text-gray-600')" x-text="selectedSite ? selectedSite.name + ' — ' + selectedSite.url : ''"></span>
                </div>

                {{-- Connection activity log --}}
                <div x-show="siteConnectionLog.length > 0" x-cloak class="mt-2 bg-gray-900 rounded-lg border border-gray-700 p-3 max-h-32 overflow-y-auto">
                    <template x-for="(entry, idx) in siteConnectionLog" :key="idx">
                        <div class="flex items-start gap-2 py-0.5 text-xs font-mono">
                            <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                            <span :class="{ 'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info' }" x-text="entry.message" class="break-words"></span>
                        </div>
                    </template>
                </div>
            </div>
            <div class="mt-3">
                <button @click="confirmSite()" :disabled="!selectedSite || siteConnectionStatus === null" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Continue &rarr;</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 4: Provide Sources
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 4, 'opacity-50': !isStepAccessible(4) }">
        <button @click="toggleStep(4)" :disabled="!isStepAccessible(4)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(4) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(4)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(4)"><span>4</span></template>
                </span>
                <span class="font-semibold text-gray-800">Provide Sources</span>
                <span x-show="sources.length > 0" x-cloak class="text-sm text-green-600" x-text="sources.length + ' source(s)'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(4) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(4)" x-cloak x-collapse class="px-4 pb-4">
            {{-- Tabs --}}
            <div class="flex border-b border-gray-200 mb-4">
                <button @click="sourceTab = 'paste'" :class="sourceTab === 'paste' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Paste Links</button>
                <button @click="sourceTab = 'search'" :class="sourceTab === 'search' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Search News</button>
                <button @click="sourceTab = 'bookmarks'; loadBookmarks()" :class="sourceTab === 'bookmarks' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Bookmarks</button>
            </div>

            {{-- Paste Links tab --}}
            <div x-show="sourceTab === 'paste'">
                <textarea x-model="pasteText" rows="4" placeholder="Paste URLs here, one per line..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-400"></textarea>
                <button @click="addPastedUrls()" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Add URLs</button>
            </div>

            {{-- Search News tab --}}
            <div x-show="sourceTab === 'search'">
                {{-- Search mode pills --}}
                <div class="flex flex-wrap gap-2 mb-3">
                    <button @click="newsMode = 'keyword'" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'keyword' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Keyword</button>
                    <button @click="newsMode = 'local'" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'local' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Local News</button>
                    <button @click="newsMode = 'trending'; searchNews()" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'trending' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Trending</button>
                    <button @click="newsMode = 'genre'" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'genre' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Genre</button>
                </div>

                {{-- Keyword search --}}
                <div x-show="newsMode === 'keyword'" class="flex gap-2">
                    <input type="text" x-model="newsSearch" @keydown.enter="searchNews()" placeholder="Search for articles..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <button @click="searchNews()" :disabled="newsSearching" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="newsSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Search
                    </button>
                </div>

                {{-- Local news --}}
                <div x-show="newsMode === 'local'" class="flex flex-wrap gap-2">
                    <input type="text" x-model="newsSearch" @keydown.enter="searchNews()" placeholder="City or state name..." class="flex-1 min-w-[200px] border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <select x-model="newsCountry" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="us">United States</option>
                        <option value="gb">United Kingdom</option>
                        <option value="ca">Canada</option>
                        <option value="au">Australia</option>
                        <option value="il">Israel</option>
                        <option value="in">India</option>
                        <option value="de">Germany</option>
                        <option value="fr">France</option>
                    </select>
                    <button @click="searchNews()" :disabled="newsSearching" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="newsSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Search Local
                    </button>
                </div>

                {{-- Trending --}}
                <div x-show="newsMode === 'trending'" class="space-y-2">
                    <div class="flex flex-wrap gap-2">
                        <button @click="newsCategory = ''; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="!newsCategory ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">All</button>
                        <button @click="newsCategory = 'technology'; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsCategory === 'technology' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">Technology</button>
                        <button @click="newsCategory = 'business'; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsCategory === 'business' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">Business</button>
                        <button @click="newsCategory = 'politics'; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsCategory === 'politics' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">Politics</button>
                        <button @click="newsCategory = 'sports'; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsCategory === 'sports' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">Sports</button>
                        <button @click="newsCategory = 'health'; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsCategory === 'health' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">Health</button>
                        <button @click="newsCategory = 'entertainment'; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsCategory === 'entertainment' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">Entertainment</button>
                        <button @click="newsCategory = 'science'; searchNews()" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsCategory === 'science' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">Science</button>
                    </div>
                </div>

                {{-- Genre --}}
                <div x-show="newsMode === 'genre'" class="flex gap-2">
                    <select x-model="newsCategory" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select genre...</option>
                        <option value="technology">Technology</option>
                        <option value="business">Business</option>
                        <option value="health">Health</option>
                        <option value="sports">Sports</option>
                        <option value="science">Science</option>
                        <option value="entertainment">Entertainment</option>
                        <option value="politics">Politics</option>
                        <option value="world">World</option>
                    </select>
                    <input type="text" x-model="newsSearch" @keydown.enter="searchNews()" placeholder="Optional keyword..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <button @click="searchNews()" :disabled="newsSearching" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="newsSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Search
                    </button>
                </div>
                {{-- Loading spinner --}}
                <div x-show="newsSearching" x-cloak class="mt-3 flex items-center gap-2 text-sm text-blue-600">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Searching articles...
                </div>

                <div x-show="newsResults.length > 0 && !newsSearching" x-cloak class="mt-3">
                    <p class="text-xs text-gray-500 mb-2" x-text="newsResults.length + ' article(s) found'"></p>
                    <div class="space-y-2 max-h-[600px] overflow-y-auto">
                        <template x-for="(article, idx) in newsResults" :key="idx">
                            <div class="border rounded-lg p-3" :class="sources.some(s => s.url === article.url) ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-gray-200 hover:bg-gray-50'">
                                <div class="flex items-start gap-3">
                                    <img x-show="article.image" x-cloak :src="article.image" :alt="article.title" loading="lazy" class="rounded object-cover flex-shrink-0 bg-gray-100" style="width:150px;height:100px;" onerror="this.style.display='none'">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 break-words" x-text="article.title"></p>
                                        <p class="text-xs text-gray-500 mt-1 break-words line-clamp-2" x-text="article.description"></p>
                                        <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-400">
                                            <span x-text="article.source_name"></span>
                                            <span x-text="article.published_at"></span>
                                            <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-500" x-text="article.source_api"></span>
                                        </div>
                                        <a :href="article.url" target="_blank" class="text-xs text-blue-500 hover:underline break-all mt-1 block" x-text="article.url + ' &#8599;'"></a>
                                    </div>
                                    <div class="flex-shrink-0 flex flex-col gap-1">
                                        <span x-show="sources.some(s => s.url === article.url)" class="text-xs text-gray-400 font-medium px-2.5 py-1.5">Added</span>
                                        <button x-show="!sources.some(s => s.url === article.url)" @click="addSource(article.url, article.title)" class="text-blue-600 hover:text-blue-800 text-xs font-medium px-2.5 py-1.5 bg-blue-50 rounded hover:bg-blue-100">+ Add</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div x-show="newsResults.length === 0 && !newsSearching && newsSearch" x-cloak class="mt-3 text-sm text-gray-400">No articles found.</div>
            </div>

            {{-- Bookmarks tab --}}
            <div x-show="sourceTab === 'bookmarks'">
                <div x-show="bookmarksLoading" class="flex items-center gap-2 text-sm text-gray-500 py-4">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading bookmarks...
                </div>
                <div x-show="!bookmarksLoading && bookmarks.length === 0" x-cloak class="text-sm text-gray-400 py-4">No bookmarks found for this user.</div>
                <div x-show="bookmarks.length > 0" x-cloak class="space-y-2 max-h-64 overflow-y-auto">
                    <template x-for="bm in bookmarks" :key="bm.id">
                        <div class="flex items-start gap-3 rounded-lg p-3" :class="sources.some(s => s.url === bm.url) ? 'bg-gray-100 opacity-60' : 'bg-gray-50'">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 break-words" x-text="bm.title || bm.url"></p>
                                <p class="text-xs text-gray-500 break-all" x-text="bm.url"></p>
                            </div>
                            <span x-show="sources.some(s => s.url === bm.url)" class="flex-shrink-0 text-xs text-gray-400 font-medium px-2 py-1">Added</span>
                            <button x-show="!sources.some(s => s.url === bm.url)" @click="addSource(bm.url, bm.title)" class="flex-shrink-0 text-blue-600 hover:text-blue-800 text-xs font-medium px-2 py-1 bg-blue-50 rounded">+ Add</button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Source list --}}
            <div x-show="sources.length > 0" x-cloak class="mt-4 border-t border-gray-200 pt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2" x-text="'Selected Sources (' + sources.length + ')'"></h4>
                <div class="space-y-2">
                    <template x-for="(src, idx) in sources" :key="idx">
                        <div class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2">
                            <span class="w-5 h-5 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-xs font-bold" x-text="idx + 1"></span>
                            <div class="flex-1 min-w-0">
                                <p x-show="src.title" class="text-sm font-medium text-gray-800 break-words" x-text="src.title"></p>
                                <p class="text-xs text-gray-500 break-all" x-text="src.url"></p>
                            </div>
                            <button @click="removeSource(idx)" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-3" x-show="sources.length > 0" x-cloak>
                <button @click="completeStep(4); openStep(5)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to Get Articles &rarr;</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 5: Get Articles
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 5, 'opacity-50': !isStepAccessible(5) }">
        <button @click="toggleStep(5)" :disabled="!isStepAccessible(5)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(5) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(5)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(5)"><span>5</span></template>
                </span>
                <span class="font-semibold text-gray-800">Get Articles</span>
                <span x-show="checkResults.length > 0" x-cloak class="text-sm" :class="checkPassCount === sources.length ? 'text-green-600' : 'text-yellow-600'"
                      x-text="checkPassCount + '/' + sources.length + ' extracted'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(5) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(5)" x-cloak x-collapse class="px-4 pb-4">
            {{-- Extraction Options --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Method</label>
                        <select x-model="extractMethod" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="auto">Auto (Recommended)</option>
                            <option value="readability">Readability</option>
                            <option value="css">CSS Selector</option>
                            <option value="regex">Regex</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">User Agent</label>
                        <select x-model="checkUserAgent" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="chrome">Chrome Desktop</option>
                            <option value="firefox">Firefox Desktop</option>
                            <option value="safari">Safari macOS</option>
                            <option value="googlebot">Googlebot</option>
                            <option value="bot">HWS Bot</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Retries</label>
                        <select x-model="extractRetries" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="0">0</option>
                            <option value="1" selected>1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Timeout</label>
                        <select x-model="extractTimeout" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="10">10s</option>
                            <option value="20" selected>20s</option>
                            <option value="30">30s</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Min Words</label>
                        <input type="number" x-model="extractMinWords" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-20" min="10" max="1000">
                    </div>
                    <label class="flex items-center gap-2 text-xs text-gray-600 pb-2">
                        <input type="checkbox" x-model="extractAutoFallback" class="rounded border-gray-300 text-blue-600">
                        Auto-fallback (Googlebot)
                    </label>
                    <button @click="checkAllSources()" :disabled="checking" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="checking" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="checking ? 'Extracting...' : 'Get Articles'"></span>
                    </button>
                </div>
            </div>

            {{-- Extraction Activity Log --}}
            <div x-show="checkLog.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 mb-4 max-h-48 overflow-y-auto">
                <template x-for="(entry, idx) in checkLog" :key="idx">
                    <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span :class="{
                            'text-green-400': entry.type === 'success',
                            'text-red-400': entry.type === 'error',
                            'text-blue-400': entry.type === 'info',
                            'text-gray-400': entry.type === 'step',
                        }" x-text="entry.message" class="break-words"></span>
                    </div>
                </template>
            </div>

            <div x-show="checkResults.length > 0" x-cloak class="space-y-3">
                <template x-for="(result, idx) in checkResults" :key="idx">
                    <div class="rounded-lg border" :class="approvedSources.includes(idx) ? 'border-green-400 bg-green-50 ring-1 ring-green-300' : (discardedSources.includes(idx) ? 'border-gray-200 bg-gray-100 opacity-40' : (result.success ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'))">
                        {{-- Header row — click to expand --}}
                        <div @click="toggleSourceExpand(idx)" class="flex items-start gap-3 px-4 py-3 cursor-pointer">
                            <span class="mt-0.5 flex-shrink-0">
                                <svg x-show="result.success" class="w-7 h-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!result.success" class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p x-show="result.title" class="text-base font-semibold text-gray-900 break-words" x-text="result.title"></p>
                                <a :href="result.url" target="_blank" @click.stop class="text-xs break-all inline-flex items-center gap-1 hover:underline mt-1" :class="result.success ? 'text-blue-600' : 'text-red-600'">
                                    <span x-text="result.url"></span>
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <div x-show="result.success" class="flex items-center gap-3 mt-2">
                                    <span class="text-lg font-bold text-green-700" x-text="result.word_count + ' words'"></span>
                                    <span class="text-xs text-gray-400" x-text="result.message"></span>
                                </div>
                                <p x-show="!result.success" class="text-sm text-red-600 font-medium mt-1">Extraction failed — <button @click.stop="retrySingleSource(idx)" class="underline hover:text-red-800">retry</button></p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button x-show="result.success" @click.stop="approveSource(idx)" class="px-4 py-2 rounded-lg font-semibold text-sm transition-colors" :class="approvedSources.includes(idx) ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200 border border-green-300'">
                                    <svg class="w-5 h-5 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="approvedSources.includes(idx) ? 'Approved' : 'Approve'"></span>
                                </button>
                                <button x-show="!result.success" @click.stop="retrySingleSource(idx)" class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 bg-blue-50 rounded">Retry</button>
                                <svg x-show="result.success" class="w-5 h-5 text-gray-400 transition-transform" :class="expandedSources.includes(idx) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        {{-- Plain text preview (always visible when extracted) --}}
                        <div x-show="result.success && result.text && !expandedSources.includes(idx)" x-cloak class="px-4 pb-3">
                            <p class="text-sm text-gray-600 line-clamp-3 break-words" x-text="result.text.substring(0, 300) + '...'"></p>
                            <button @click.stop="toggleSourceExpand(idx)" class="text-xs text-blue-600 hover:underline mt-1">Read full article</button>
                        </div>

                        {{-- Expanded article content --}}
                        <div x-show="expandedSources.includes(idx) && result.text" x-cloak x-data="{ showRaw: false }" class="border-t border-green-200">
                            <div class="bg-white rounded-b-lg shadow-sm">
                                {{-- Content header with raw toggle --}}
                                <div class="flex items-center justify-between px-6 pt-4 pb-2">
                                    <span class="text-xs text-gray-400">Extracted content</span>
                                    <button @click.stop="showRaw = !showRaw" class="text-xs px-2 py-0.5 rounded border transition-colors" :class="showRaw ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-400 border-gray-200 hover:border-gray-400'" title="Toggle formatted/raw view">
                                        &lt;/&gt;
                                    </button>
                                </div>
                                {{-- Formatted view --}}
                                <div x-show="!showRaw" class="px-6 pb-4 overflow-hidden" style="font-size: 15px; line-height: 1.8; color: #374151;"><div class="prose max-w-none" style="--tw-prose-img-margin-top:0.5em;--tw-prose-img-margin-bottom:0.5em;" x-html="(result.formatted_html || result.text || '').replace(/<img /g, '<img style=\'max-width:100%;height:auto;\' ')"></div></div>
                                {{-- Raw text view --}}
                                <div x-show="showRaw" x-cloak class="px-6 pb-4 overflow-hidden">
                                    <pre class="text-xs text-gray-500 bg-gray-50 rounded-lg p-4 whitespace-pre-wrap break-words font-mono" x-text="result.text"></pre>
                                </div>
                                {{-- Action buttons with separator --}}
                                <div x-show="result.success" class="px-6 py-3 border-t border-gray-100 flex items-center gap-3">
                                    <button @click.stop="approveSource(idx)" class="text-sm font-medium px-4 py-2 rounded-lg inline-flex items-center gap-1.5 transition-colors" :class="approvedSources.includes(idx) ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span x-text="approvedSources.includes(idx) ? 'Approved' : 'Approve'"></span>
                                    </button>
                                    <button @click.stop="discardSource(idx)" class="text-sm font-medium px-4 py-2 rounded-lg inline-flex items-center gap-1.5 transition-colors" :class="discardedSources.includes(idx) ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span x-text="discardedSources.includes(idx) ? 'Discarded' : 'Discard'"></span>
                                    </button>
                                    <button @click.stop class="text-sm font-medium px-4 py-2 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 inline-flex items-center gap-1.5 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Retry
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-3" x-show="checkResults.length > 0 && checkPassCount > 0" x-cloak>
                <button @click="completeStep(5); openStep(6)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue &rarr;</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 6: Select AI Template
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 6, 'opacity-50': !isStepAccessible(6) }">
        <button @click="toggleStep(6)" :disabled="!isStepAccessible(6)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(6) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(6)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(6)"><span>6</span></template>
                </span>
                <span class="font-semibold text-gray-800">Select AI Template</span>
                <span x-show="selectedTemplate" x-cloak class="text-sm text-green-600" x-text="selectedTemplate ? selectedTemplate.name : ''"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(6) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(6)" x-cloak x-collapse class="px-4 pb-4">
            {{-- Loading indicator --}}
            <div x-show="templatesLoading" class="flex items-center gap-2 text-sm text-gray-500 py-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Loading templates...
            </div>
            <div x-show="!templatesLoading" class="max-w-md">
                <label class="block text-xs text-gray-500 mb-1">User's AI Templates</label>
                <select x-model="selectedTemplateId" @change="selectTemplate()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- No template (configure manually) --</option>
                    <template x-for="t in templates" :key="t.id">
                        <option :value="t.id" x-text="t.name"></option>
                    </template>
                </select>
            </div>
            <div x-show="selectedTemplate" x-cloak class="mt-3 bg-gray-50 rounded-lg p-3 text-sm space-y-1">
                <p><span class="text-gray-500">AI Engine:</span> <span class="font-medium" x-text="selectedTemplate?.ai_engine || 'Not set'"></span></p>
                <p><span class="text-gray-500">Tone:</span> <span class="font-medium" x-text="Array.isArray(selectedTemplate?.tone) ? selectedTemplate.tone.join(', ') : (selectedTemplate?.tone || 'Not set')"></span></p>
                <p><span class="text-gray-500">Word Count:</span> <span class="font-medium" x-text="(selectedTemplate?.word_count_min || '—') + ' - ' + (selectedTemplate?.word_count_max || '—')"></span></p>
                <p x-show="selectedTemplate?.description"><span class="text-gray-500">Description:</span> <span class="font-medium" x-text="selectedTemplate?.description"></span></p>
            </div>
            <div class="mt-3">
                <button @click="completeStep(6); openStep(7)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
                    <span x-text="selectedTemplate ? 'Continue with Template' : 'Skip Template'"></span> &rarr;
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 7: Spin Article (model selector + spin combined)
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 7, 'opacity-50': !isStepAccessible(7) }">
        <button @click="toggleStep(7)" :disabled="!isStepAccessible(7)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(7) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(7)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(7)"><span>7</span></template>
                </span>
                <span class="font-semibold text-gray-800">Create Article</span>
                <span x-show="spunContent" x-cloak class="text-sm text-green-600" x-text="spunWordCount + ' words'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(7) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(7)" x-cloak x-collapse class="px-4 pb-4">
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">AI Model</label>
                    <select x-model="aiModel" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach(config('anthropic.models', []) as $model)
                            @if($model['type'] === 'api' || $model['type'] === 'both')
                                <option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <button @click="spinArticle()" :disabled="spinning" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2">
                    <svg x-show="spinning" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="spinning ? 'Spinning...' : (spunContent ? 'Re-spin' : 'Spin Article')"></span>
                </button>
            </div>

            {{-- Author selection --}}
            <div class="mb-4 flex flex-wrap items-end gap-3" x-show="publishAuthor || siteAuthors.length > 0">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Publish As</label>
                    <select x-model="publishAuthor" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Default author —</option>
                        <template x-for="a in siteAuthors" :key="a.user_login">
                            <option :value="a.user_login" x-text="(a.display_name || a.user_login) + ' (' + a.user_login + ')'"></option>
                        </template>
                    </select>
                </div>
            </div>

            {{-- Short description --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Article Description / Excerpt</label>
                <textarea x-model="articleDescription" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Short summary for SEO meta description and excerpt..."></textarea>
            </div>

            {{-- Custom prompt input --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Custom Instructions <span class="text-gray-400">(takes precedence over template/preset)</span></label>
                <textarea x-model="customPrompt" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Write in first person, focus on the financial impact, include expert quotes..."></textarea>
            </div>

            {{-- Prompt preview (expandable) --}}
            <div class="mb-4" x-data="{ showPrompt: false }">
                <button @click="showPrompt = !showPrompt" class="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
                    <svg class="w-3 h-3 transition-transform" :class="showPrompt ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    View Full Prompt
                </button>
                <div x-show="showPrompt" x-cloak class="mt-2 bg-gray-900 text-gray-300 rounded-xl p-4 text-xs font-mono overflow-y-auto break-words whitespace-pre-wrap" style="max-height:500px;">
                    <template x-if="resolvedPrompt">
                        <pre class="text-green-300 whitespace-pre-wrap break-words" x-text="resolvedPrompt"></pre>
                    </template>
                    <template x-if="!resolvedPrompt">
                        <p class="text-gray-500">Prompt will appear here after spinning. This shows the EXACT text sent to AI.</p>
                    </template>
                </div>
            </div>

            {{-- Spin error --}}
            <div x-show="spinError" x-cloak class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-red-700" x-text="spinError"></p>
            </div>

            {{-- Token usage --}}
            <div x-show="tokenUsage" x-cloak class="mb-4 flex gap-4 text-xs text-gray-500">
                <span>Prompt tokens: <span class="font-medium text-gray-700" x-text="tokenUsage?.input_tokens || 0"></span></span>
                <span>Completion tokens: <span class="font-medium text-gray-700" x-text="tokenUsage?.output_tokens || 0"></span></span>
                <span>Total: <span class="font-medium text-gray-700" x-text="(tokenUsage?.input_tokens || 0) + (tokenUsage?.output_tokens || 0)"></span></span>
            </div>

            {{-- Spun content in TinyMCE editor --}}
            <div x-show="spunContent" x-cloak>
                <p class="text-xs text-gray-500 mb-2">Generated article — edit directly below</p>
                {{-- Title textbox --}}
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Article Title</label>
                    <div x-show="metadataLoading && !articleTitle" class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-gray-50 animate-pulse h-[60px]"></div>
                    <textarea x-show="!metadataLoading || articleTitle" x-model="articleTitle" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg font-bold resize-none overflow-hidden" placeholder="Enter article title..." @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
                </div>

                <x-hexa-tinymce name="spin-preview" value="" preset="wordpress" :height="700" id="spin-preview-editor" />

                {{-- Title Options --}}
                <div x-show="suggestedTitles.length > 0" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-3">Title Options</h5>
                    <div class="space-y-2.5">
                        <template x-for="(title, idx) in suggestedTitles" :key="idx">
                            <label class="flex items-center gap-2.5 cursor-pointer" :class="selectedTitleIdx === idx ? 'text-blue-800 font-semibold' : 'text-gray-700'">
                                <input type="radio" name="spin-title" :checked="selectedTitleIdx === idx" @click="selectedTitleIdx = idx; articleTitle = title" class="text-blue-600">
                                <span x-text="title" class="break-words text-base"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- H2 Subtitles --}}
                <div x-show="spunContent && spunContent.includes('<h2')" x-cloak class="mt-3 bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">Article Sections</h5>
                    <div class="space-y-1" x-html="(() => {
                        const tmp = document.createElement('div');
                        tmp.innerHTML = spunContent;
                        const h2s = tmp.querySelectorAll('h2');
                        return Array.from(h2s).map((h, i) => '<p class=\'text-sm text-gray-600\'><span class=\'text-gray-400 mr-2\'>' + (i+1) + '.</span>' + h.textContent + '</p>').join('');
                    })()"></div>
                </div>

                {{-- Section divider --}}
                <div class="mt-6 mb-4 border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Article Metadata</h4>
                </div>

                {{-- Categories & Tags --}}
                <div x-show="suggestedCategories.length > 0 || suggestedTags.length > 0" x-cloak class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div x-show="suggestedCategories.length > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Categories <span class="font-normal text-gray-400" x-text="'(' + selectedCategories.length + ' selected)'"></span></h5>
                        <div class="space-y-1">
                            <template x-for="(cat, idx) in suggestedCategories" :key="idx">
                                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                    <input type="checkbox" :checked="selectedCategories.includes(idx)" @click="toggleSelection(selectedCategories, idx)" class="rounded border-gray-300 text-green-600">
                                    <span x-text="cat"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div x-show="suggestedTags.length > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Tags <span class="font-normal text-gray-400" x-text="'(' + selectedTags.length + ' selected)'"></span></h5>
                        <div class="space-y-1">
                            <template x-for="(tag, idx) in suggestedTags" :key="idx">
                                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                    <input type="checkbox" :checked="selectedTags.includes(idx)" @click="toggleSelection(selectedTags, idx)" class="rounded border-gray-300 text-blue-600">
                                    <span x-text="tag"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Article Links --}}
                <div x-show="suggestedUrls.length > 0" x-cloak class="mt-3 bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">Article Links <span class="font-normal text-gray-400" x-text="'(' + suggestedUrls.length + ')'"></span></h5>
                    <div class="space-y-2">
                        <template x-for="(link, idx) in suggestedUrls" :key="idx">
                            <div class="flex items-center gap-3 text-sm bg-white rounded-lg px-3 py-2 border border-gray-100">
                                <div class="flex-1 min-w-0">
                                    <p class="text-gray-800 font-medium break-words" x-text="link.title"></p>
                                    <a :href="link.url" target="_blank" class="text-blue-600 hover:underline text-xs break-all inline-flex items-center gap-1">
                                        <span x-text="link.url"></span>
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </div>
                                <button @click="link.nofollow = !link.nofollow" class="text-xs px-2 py-1 rounded flex-shrink-0" :class="link.nofollow ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'" x-text="link.nofollow ? 'nofollow' : 'follow'"></button>
                                <button @click="suggestedUrls.splice(idx, 1)" class="text-red-400 hover:text-red-600 flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Loading metadata --}}
                <div x-show="metadataLoading" x-cloak class="mt-3 flex items-center gap-2 text-sm text-gray-500">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Generating titles, categories & tags...
                </div>

                {{-- Featured Image --}}
                <div x-show="featuredImageSearch" x-cloak class="mt-4 bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-purple-800 mb-2">Featured Image</h5>
                    <div class="flex items-start gap-4">
                        <div class="w-48 h-32 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                            <img x-show="featuredPhoto" x-cloak :src="featuredPhoto?.url_large || featuredPhoto?.url_thumb" class="w-full h-full object-cover">
                            <div x-show="!featuredPhoto" class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-purple-600 mb-1">AI suggested search:</p>
                            <div class="flex gap-2 mb-2">
                                <input type="text" x-model="featuredImageSearch" class="flex-1 border border-purple-300 rounded-lg px-3 py-1.5 text-sm">
                                <button @click="searchFeaturedImage()" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-purple-700">Search</button>
                            </div>
                            <p x-show="featuredPhoto" x-cloak class="text-xs text-gray-500" x-text="(featuredPhoto?.width || '?') + 'x' + (featuredPhoto?.height || '?') + ' — ' + (featuredPhoto?.source || '?')"></p>
                        </div>
                    </div>
                </div>

                {{-- Photo Suggestions Panel --}}
                <div class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4" data-photo-section>
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-gray-700">Photos</h5>
                        <span x-show="autoFetchingPhotos" x-cloak class="text-xs text-purple-600 flex items-center gap-1">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Auto-loading photos...
                        </span>
                    </div>

                    {{-- AI photo suggestions — one per row with thumbnail --}}
                    <div x-show="photoSuggestions.length > 0" class="space-y-2 mb-3">
                        <template x-for="(ps, idx) in photoSuggestions" :key="idx">
                            <div x-show="!ps.removed" class="border rounded-lg overflow-hidden" :class="ps.confirmed ? 'border-green-300 bg-green-50' : 'border-purple-200 bg-white'">
                                <div class="flex items-center gap-3 p-3 cursor-pointer" @click="expandedSuggestions.includes(idx) ? expandedSuggestions = expandedSuggestions.filter(i => i !== idx) : expandedSuggestions.push(idx)">
                                    <div class="w-24 h-18 flex-shrink-0 rounded overflow-hidden bg-gray-100" style="width:96px;height:72px;">
                                        <img x-show="ps.autoPhoto" x-cloak :src="ps.autoPhoto?.url_thumb" class="w-full h-full object-cover">
                                        <div x-show="!ps.autoPhoto" class="w-full h-full flex items-center justify-center text-gray-300">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-purple-700 break-words" x-text="ps.search_term"></p>
                                        <p class="text-xs text-gray-500 break-words" x-text="ps.alt_text || ps.caption"></p>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <button @click.stop="confirmPhoto(idx)" x-show="!ps.confirmed && ps.autoPhoto" x-cloak class="p-1.5 rounded hover:bg-green-100 text-green-600" title="Confirm photo">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                        <span x-show="ps.confirmed" x-cloak class="text-xs text-green-600 font-medium px-1">Confirmed</span>
                                        <button @click.stop="expandedSuggestions.includes(idx) ? expandedSuggestions = expandedSuggestions.filter(i => i !== idx) : expandedSuggestions.push(idx)" class="p-1.5 rounded hover:bg-blue-100 text-blue-600" title="Change photo">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>
                                        </button>
                                        <button @click.stop="removePhotoPlaceholder(idx)" class="p-1.5 rounded hover:bg-red-100 text-red-600" title="Remove">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expandedSuggestions.includes(idx) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>
                                <div x-show="expandedSuggestions.includes(idx)" x-cloak class="p-3 pt-0 border-t border-gray-100">
                                    <div class="flex gap-2 mb-2">
                                        <input type="text" :value="ps.search_term" @input="photoSuggestions[idx].search_term = $event.target.value" @keydown.enter="searchPhotosForSuggestion(idx)" class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search...">
                                        <button @click="searchPhotosForSuggestion(idx)" :disabled="ps.searching" class="bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-gray-700 disabled:opacity-50 inline-flex items-center gap-1">
                                            <svg x-show="ps.searching" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span x-text="ps.searching ? 'Searching...' : 'Search'"></span>
                                        </button>
                                    </div>
                                    <div x-show="ps.searchResults && ps.searchResults.length > 0" x-cloak class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
                                        <template x-for="(photo, pidx) in (ps.searchResults || [])" :key="pidx">
                                            <div class="cursor-pointer rounded-lg overflow-hidden border-2 hover:border-blue-400 transition-colors"
                                                 :class="ps.autoPhoto && ps.autoPhoto.url_thumb === photo.url_thumb ? 'border-purple-400' : 'border-gray-200'"
                                                 @click="selectPhotoForSuggestion(idx, photo)">
                                                <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-44 object-cover">
                                            </div>
                                        </template>
                                    </div>
                                    {{-- Inline photo info (alt text, caption, filename) --}}
                                    <div x-show="ps.autoPhoto" x-cloak class="bg-white border border-gray-200 rounded-lg p-3 space-y-2">
                                        <div class="flex items-center gap-2 text-xs text-gray-500">
                                            <span x-text="(ps.autoPhoto?.source || '?') + ' — ' + (ps.autoPhoto?.width || '?') + 'x' + (ps.autoPhoto?.height || '?')"></span>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-0.5">Alt Text</label>
                                            <input type="text" x-model="photoSuggestions[idx].alt_text" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Alt text...">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-0.5">Caption</label>
                                            <input type="text" x-model="photoSuggestions[idx].caption" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Caption...">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-0.5">WordPress Filename</label>
                                            <input type="text" x-model="photoSuggestions[idx].suggestedFilename" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono" placeholder="photo-1.jpg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Manual photo search —  insert at cursor --}}
                    <div class="border-t border-gray-200 pt-3">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs text-gray-500">Manual search — insert at cursor position</p>
                            <button @click="showPhotoPanel = !showPhotoPanel" class="text-xs text-blue-600 hover:text-blue-800" x-text="showPhotoPanel ? 'Hide Search' : 'Show Search'"></button>
                        </div>
                        <div x-show="showPhotoPanel" x-cloak>
                            <div class="flex gap-2 mb-2">
                                <input type="text" x-model="photoSearch" @keydown.enter="searchPhotos()" placeholder="Search photos..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <button @click="searchPhotos()" :disabled="photoSearching" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700 disabled:opacity-50 flex items-center gap-2">
                                    <svg x-show="photoSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Search
                                </button>
                            </div>
                            <div x-show="photoResults.length > 0" x-cloak class="grid grid-cols-3 md:grid-cols-5 gap-2">
                                <template x-for="(photo, pidx) in photoResults" :key="pidx">
                                    <div class="relative group cursor-pointer" @click="insertingPhoto = photo; photoCaption = photo.alt || articleTitle">
                                        <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-20 object-cover rounded-lg border border-gray-200">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 rounded-lg transition-all flex items-center justify-center">
                                            <span class="text-white text-xs font-medium opacity-0 group-hover:opacity-100">Select</span>
                                        </div>
                                        <div class="flex items-center justify-between mt-1">
                                            <span class="text-[10px] text-gray-400" x-text="(photo.width || '?') + 'x' + (photo.height || '?')"></span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium" :class="photo.source === 'pexels' ? 'bg-green-100 text-green-700' : (photo.source === 'unsplash' ? 'bg-gray-100 text-gray-700' : 'bg-yellow-100 text-yellow-700')" x-text="photo.source"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div x-show="insertingPhoto" x-cloak class="mt-3 bg-white border border-blue-200 rounded-lg p-3">
                                <div class="flex items-start gap-3">
                                    <img :src="insertingPhoto?.url_thumb || insertingPhoto?.url_large" class="w-20 h-16 object-cover rounded border">
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">Caption</label>
                                        <input type="text" x-model="photoCaption" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Photo caption...">
                                        <div class="flex gap-2 mt-2">
                                            <button @click="insertPhotoIntoEditor()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-blue-700">Insert at Cursor</button>
                                            <button @click="insertingPhoto = null" class="text-xs text-gray-500 hover:text-gray-700 px-2">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- AI Photo Instructions (subtle expandable) --}}
                    <div class="border-t border-gray-200 pt-3 mt-3" x-data="{ showInstructions: false }">
                        <button @click="showInstructions = !showInstructions" class="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
                            <svg class="w-3 h-3 transition-transform" :class="showInstructions ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            AI Photo Instructions
                        </button>
                        <div x-show="showInstructions" x-cloak class="mt-2 text-xs text-gray-500 bg-gray-100 rounded p-3 break-words space-y-1">
                            <p>AI was instructed to place photo markers at natural breaking points between sections. Search terms are specific and visual. Captions are complete sentences describing the photo in context.</p>
                            <template x-if="selectedPreset?.image_preference">
                                <p>Preset image preference: <span x-text="selectedPreset.image_preference" class="text-gray-700 font-medium"></span></p>
                            </template>
                            <template x-if="selectedTemplate?.photos_per_article">
                                <p>Template photo count: <span x-text="selectedTemplate.photos_per_article" class="text-gray-700 font-medium"></span></p>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Photo Info Modal (centered overlay) --}}
                <div x-show="viewingPhotoIdx !== null && photoSuggestions[viewingPhotoIdx]" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" @click.self="viewingPhotoIdx = null">
                    <div class="fixed inset-0 bg-black bg-opacity-50"></div>
                    <div class="relative bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                        <button @click="viewingPhotoIdx = null" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <template x-if="viewingPhotoIdx !== null && photoSuggestions[viewingPhotoIdx]">
                            <div>
                                <div class="flex items-start gap-5">
                                    <img :src="photoSuggestions[viewingPhotoIdx]?.autoPhoto?.url_large || photoSuggestions[viewingPhotoIdx]?.autoPhoto?.url_thumb" class="w-64 h-auto rounded-lg border border-gray-200 flex-shrink-0">
                                    <div class="flex-1 space-y-3">
                                        <div>
                                            <p class="text-xs text-gray-400">Search Term</p>
                                            <p class="text-sm font-medium text-purple-700" x-text="photoSuggestions[viewingPhotoIdx]?.search_term"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">Source</p>
                                            <p class="text-sm text-gray-700" x-text="(photoSuggestions[viewingPhotoIdx]?.autoPhoto?.source || '?') + ' — ' + (photoSuggestions[viewingPhotoIdx]?.autoPhoto?.width || '?') + 'x' + (photoSuggestions[viewingPhotoIdx]?.autoPhoto?.height || '?')"></p>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Alt Text</label>
                                            <input type="text" x-model="photoSuggestions[viewingPhotoIdx].alt_text" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Caption</label>
                                            <input type="text" x-model="photoSuggestions[viewingPhotoIdx].caption" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Photo caption...">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">WordPress Filename</label>
                                            <input type="text" x-model="photoSuggestions[viewingPhotoIdx].suggestedFilename" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono">
                                        </div>
                                        <div class="flex gap-2 pt-2">
                                            <button @click="confirmPhoto(viewingPhotoIdx); viewingPhotoIdx = null" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 inline-flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                Confirm
                                            </button>
                                            <button @click="changePhoto(viewingPhotoIdx); viewingPhotoIdx = null" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Change</button>
                                            <button @click="removePhotoPlaceholder(viewingPhotoIdx); viewingPhotoIdx = null" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">Remove</button>
                                            <button @click="viewingPhotoIdx = null" class="text-gray-500 hover:text-gray-700 px-3 py-2 text-sm">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Action buttons + Quick links — same row --}}
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button @click="acceptSpin()" class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Accept & Edit
                        </button>
                        <button @click="spinArticle()" :disabled="spinning" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                            <svg x-show="spinning" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Re-spin
                        </button>
                        <button @click="showChangeInput = !showChangeInput; loadSmartEdits()" class="bg-blue-100 text-blue-700 px-5 py-2 rounded-lg text-sm hover:bg-blue-200 inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Request Changes
                        </button>
                        <button @click="saveDraftNow()" :disabled="savingDraft" class="bg-gray-200 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-50 inline-flex items-center gap-2">
                            <svg x-show="savingDraft" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="savingDraft ? 'Saving...' : 'Save Draft'"></span>
                        </button>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-gray-400">
                        <a href="{{ route('publish.templates.index') }}" target="_blank" class="hover:text-blue-600 inline-flex items-center gap-1">AI Templates <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        <a href="{{ route('publish.smart-edits.index') }}" target="_blank" class="hover:text-blue-600 inline-flex items-center gap-1">Smart Edits <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        <a href="{{ route('publish.settings.master') }}" target="_blank" class="hover:text-blue-600 inline-flex items-center gap-1">Settings <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                    </div>
                </div>

                {{-- Change request input with Smart Edit Templates --}}
                <div x-show="showChangeInput" x-cloak class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <label class="block text-sm font-medium text-blue-800 mb-2">Quick templates</label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <template x-for="tpl in smartEditTemplates" :key="tpl.id">
                            <button @click="appendSmartEdit(tpl)" class="text-xs px-3 py-1.5 rounded-full border transition-colors" :class="appliedSmartEdits.includes(tpl.id) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-blue-700 border-blue-300 hover:bg-blue-100'" x-text="tpl.name"></button>
                        </template>
                        <span x-show="smartEditTemplates.length === 0" class="text-xs text-gray-400">No templates configured</span>
                    </div>
                    <label class="block text-sm font-medium text-blue-800 mb-2">What changes do you want?</label>
                    <textarea x-model="spinChangeRequest" rows="4" class="w-full border border-blue-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Make it more formal, add a conclusion paragraph, rewrite the intro..."></textarea>
                    <button @click="requestSpinChanges()" :disabled="spinning || !spinChangeRequest.trim()" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="spinning" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="spinning ? 'Processing...' : 'Send to AI'"></span>
                    </button>
                </div>

                {{-- AI call cost report — very bottom --}}
                <div x-show="lastAiCall" x-cloak class="mt-4 bg-gray-900 text-gray-300 rounded-lg px-4 py-2 text-xs font-mono flex flex-wrap gap-x-4 gap-y-1">
                    <span><span class="text-gray-500">User:</span> <span class="text-white" x-text="lastAiCall?.user_name"></span></span>
                    <span><span class="text-gray-500">Model:</span> <span class="text-purple-400" x-text="lastAiCall?.model"></span></span>
                    <span><span class="text-gray-500">Provider:</span> <span class="text-blue-400" x-text="lastAiCall?.provider"></span></span>
                    <span><span class="text-gray-500">Tokens:</span> <span class="text-green-400" x-text="(lastAiCall?.usage?.input_tokens || 0) + '+' + (lastAiCall?.usage?.output_tokens || 0)"></span></span>
                    <span><span class="text-gray-500">Cost:</span> <span class="text-yellow-400" x-text="'$' + (lastAiCall?.cost || 0).toFixed(4)"></span></span>
                    <span><span class="text-gray-500">IP:</span> <span x-text="lastAiCall?.ip"></span></span>
                    <span><span class="text-gray-500">UTC:</span> <span x-text="lastAiCall?.timestamp_utc"></span></span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 8: Publish
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 8, 'opacity-50': !isStepAccessible(8) }">
        <button @click="toggleStep(8)" :disabled="!isStepAccessible(8)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(8) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(8)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(8)"><span>8</span></template>
                </span>
                <span class="font-semibold text-gray-800">Publish</span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(8) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(8)" x-cloak x-collapse class="px-4 pb-4">
            {{-- Review Section --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4 space-y-4">
                <h5 class="text-base font-semibold text-gray-800">Review Before Publishing</h5>

                {{-- Title --}}
                <div>
                    <p class="text-xs text-gray-400">Title</p>
                    <p class="text-lg font-bold text-gray-900 break-words" x-text="articleTitle || 'No title set'"></p>
                </div>

                {{-- Description --}}
                <div x-show="articleDescription">
                    <p class="text-xs text-gray-400">Description</p>
                    <p class="text-sm text-gray-700 break-words" x-text="articleDescription"></p>
                </div>

                {{-- Key info grid --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-400">Website</p>
                        <p class="text-sm font-medium text-gray-800" x-text="selectedSite ? selectedSite.name : 'Not selected'"></p>
                        <p x-show="selectedSite" class="text-xs text-gray-400 break-all" x-text="selectedSite?.url"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Author</p>
                        <p class="text-sm font-medium text-gray-800" x-text="publishAuthor || 'Default'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Word Count</p>
                        <p class="text-sm font-bold text-gray-800" x-text="spunWordCount + ' words'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">AI Model</p>
                        <p class="text-sm font-mono text-gray-800" x-text="aiModel"></p>
                    </div>
                </div>

                {{-- Categories & Tags --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Categories (<span x-text="suggestedCategories.filter((c,i) => selectedCategories.includes(i)).length"></span>)</p>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="(cat, idx) in suggestedCategories" :key="idx">
                                <span x-show="selectedCategories.includes(idx)" class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-800" x-text="cat"></span>
                            </template>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Tags (<span x-text="suggestedTags.filter((t,i) => selectedTags.includes(i)).length"></span>)</p>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="(tag, idx) in suggestedTags" :key="idx">
                                <span x-show="selectedTags.includes(idx)" class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800" x-text="tag"></span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Photos + Links summary --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-400">Photos</p>
                        <p class="text-sm text-gray-800" x-text="photoSuggestions.filter(p => !p.removed).length + ' photo(s)'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Links</p>
                        <p class="text-sm text-gray-800" x-text="suggestedUrls.length + ' link(s)'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">WP Template</p>
                        <p class="text-sm text-gray-800" x-text="selectedPreset ? selectedPreset.name : 'None'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Link Follow</p>
                        <p class="text-sm text-gray-800" x-text="selectedPreset?.follow_links || 'Default'"></p>
                    </div>
                </div>

                {{-- Sources --}}
                <div>
                    <p class="text-xs text-gray-400 mb-1">Sources (<span x-text="sources.length"></span>)</p>
                    <div class="space-y-1">
                        <template x-for="(s, idx) in sources" :key="idx">
                            <p class="text-xs text-gray-600 break-all" x-text="s.title || s.url"></p>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button @click="finishEditing()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to Prepare &rarr;</button>
                <button @click="saveDraftNow()" :disabled="savingDraft" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-50 flex items-center gap-2">
                    <svg x-show="savingDraft" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="savingDraft ? 'Saving...' : 'Save Draft'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 9: Prepare for WordPress
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 9, 'opacity-50': !isStepAccessible(9) }">
        <button @click="toggleStep(9)" :disabled="!isStepAccessible(9)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(9) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(9)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(9)"><span>9</span></template>
                </span>
                <span class="font-semibold text-gray-800">Prepare for WordPress</span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(9) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(9)" x-cloak x-collapse class="px-4 pb-4">
            {{-- WordPress Publishing Summary --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">Publishing Settings</h5>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                    <div>
                        <span class="text-gray-400 text-xs">Website</span>
                        <p class="font-medium text-gray-800" x-text="selectedSite ? selectedSite.name : 'Not selected'"></p>
                        <p x-show="selectedSite" class="text-xs text-gray-500 break-all" x-text="selectedSite?.url"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">WP Template</span>
                        <p class="font-medium text-gray-800" x-text="selectedPreset ? selectedPreset.name : 'None'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Follow Links</span>
                        <p class="font-medium text-gray-800" x-text="selectedPreset?.follow_links || 'Default'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Publish Action</span>
                        <p class="font-medium text-gray-800" x-text="publishAction === 'publish' ? 'Publish Immediately' : (publishAction === 'draft_wp' ? 'WordPress Draft' : (publishAction === 'draft_local' ? 'Local Draft' : publishAction))"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Categories</span>
                        <p class="font-medium text-gray-800" x-text="suggestedCategories.length ? suggestedCategories.join(', ') : 'None'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Tags</span>
                        <p class="font-medium text-gray-800" x-text="suggestedTags.length ? suggestedTags.join(', ') : 'None'"></p>
                    </div>
                </div>
            </div>

            <button @click="prepareForWp()" :disabled="preparing" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2 mb-4">
                <svg x-show="preparing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="preparing ? 'Preparing...' : 'Prepare for WordPress'"></span>
            </button>

            {{-- Activity Log — dark theme --}}
            <div x-show="prepareLog.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 mb-4 max-h-64 overflow-y-auto" x-ref="prepareLogContainer">
                <template x-for="(entry, idx) in prepareLog" :key="idx">
                    <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span :class="{
                            'text-green-400': entry.type === 'success',
                            'text-red-400': entry.type === 'error',
                            'text-blue-400': entry.type === 'info',
                            'text-yellow-400': entry.type === 'warning',
                            'text-gray-400': entry.type === 'step',
                        }" x-text="entry.message" class="break-words"></span>
                    </div>
                </template>
            </div>

            {{-- Real-time Checklist with spinners --}}
            <div x-show="prepareChecklist.length > 0" x-cloak class="space-y-2 mb-4">
                <template x-for="(item, idx) in prepareChecklist" :key="idx">
                    <div class="flex items-center gap-3 text-sm">
                        <template x-if="item.status === 'running'">
                            <svg class="w-5 h-5 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </template>
                        <template x-if="item.status === 'done'">
                            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="item.status === 'failed'">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </template>
                        <template x-if="item.status === 'skipped'">
                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                        </template>
                        <span :class="{
                            'text-blue-700': item.status === 'running',
                            'text-green-700': item.status === 'done',
                            'text-red-700': item.status === 'failed',
                            'text-gray-500': item.status === 'skipped',
                        }" x-text="item.label"></span>
                        <span x-show="item.detail" class="text-xs text-gray-400" x-text="item.detail"></span>
                    </div>
                </template>
            </div>

            <div class="mt-3" x-show="prepareComplete" x-cloak>
                <button @click="completeStep(9); openStep(10)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to Publish &rarr;</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 10: Publish
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 10, 'opacity-50': !isStepAccessible(10) }">
        <button @click="toggleStep(10)" :disabled="!isStepAccessible(10)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(10) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(10)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(10)"><span>10</span></template>
                </span>
                <span class="font-semibold text-gray-800">Publish</span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(10) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(10)" x-cloak x-collapse class="px-4 pb-4">
            <div class="max-w-md space-y-3 mb-4">
                <label class="block text-xs text-gray-500 mb-1">Publish Action</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="publishAction" value="publish" class="text-blue-600">
                        <span class="text-sm">Publish immediately</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="publishAction" value="draft_local" class="text-blue-600">
                        <span class="text-sm">Save as local draft</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="publishAction" value="draft_wp" class="text-blue-600">
                        <span class="text-sm">Push as WordPress draft</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="publishAction" value="future" class="text-blue-600">
                        <span class="text-sm">Schedule</span>
                    </label>
                </div>

                <div x-show="publishAction === 'future'" x-cloak>
                    <label class="block text-xs text-gray-500 mb-1">Schedule Date & Time</label>
                    <input type="datetime-local" x-model="scheduleDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <button @click="publishArticle()" :disabled="publishing" class="bg-green-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center gap-2">
                <svg x-show="publishing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="publishing ? 'Publishing...' : 'Publish'"></span>
            </button>

            {{-- Publish error --}}
            <div x-show="publishError" x-cloak class="mt-3 bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-sm text-red-700" x-text="publishError"></p>
            </div>

            {{-- Publish result — full post info --}}
            <div x-show="publishResult" x-cloak class="mt-4 bg-green-50 border border-green-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="font-semibold text-green-800 text-lg" x-text="publishResult?.message || 'Published successfully!'"></span>
                </div>

                {{-- Post info grid --}}
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm mb-4">
                    <div>
                        <span class="text-gray-400 text-xs">Title</span>
                        <p class="font-medium text-gray-800 break-words" x-text="articleTitle || 'Untitled'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Word Count</span>
                        <p class="font-medium text-gray-800" x-text="spunWordCount + ' words'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Website</span>
                        <p class="font-medium text-gray-800" x-text="selectedSite?.name || 'Local'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Action</span>
                        <p class="font-medium text-gray-800" x-text="publishAction === 'publish' ? 'Published' : (publishAction === 'draft_wp' ? 'WP Draft' : (publishAction === 'future' ? 'Scheduled' : 'Local Draft'))"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Categories</span>
                        <p class="font-medium text-gray-800 break-words" x-text="suggestedCategories.length ? suggestedCategories.join(', ') : 'None'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Tags</span>
                        <p class="font-medium text-gray-800 break-words" x-text="suggestedTags.length ? suggestedTags.join(', ') : 'None'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Links</span>
                        <p class="font-medium text-gray-800" x-text="suggestedUrls.length + ' link(s)'"></p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">AI Model</span>
                        <p class="font-medium text-gray-800" x-text="aiModel"></p>
                    </div>
                    <div x-show="draftId">
                        <span class="text-gray-400 text-xs">Draft ID</span>
                        <p class="font-medium text-gray-800" x-text="'#' + draftId"></p>
                    </div>
                    <div x-show="publishAuthor">
                        <span class="text-gray-400 text-xs">Author</span>
                        <p class="font-medium text-gray-800" x-text="publishAuthor"></p>
                    </div>
                </div>

                {{-- Links --}}
                <div class="flex flex-wrap gap-3 mb-4">
                    <a x-show="publishResult?.post_url" :href="publishResult?.post_url" target="_blank" class="inline-flex items-center gap-1 text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium">
                        View Post
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <a x-show="selectedSite?.url" :href="selectedSite?.url" target="_blank" class="inline-flex items-center gap-1 text-sm bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 font-medium">
                        <span x-text="selectedSite?.name || 'WordPress Site'"></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <a x-show="draftId" :href="'{{ url('article/drafts') }}/' + draftId" target="_blank" class="inline-flex items-center gap-1 text-sm bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 font-medium">
                        View Draft
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <a x-show="publishResult?.article_url" :href="publishResult?.article_url" target="_blank" class="inline-flex items-center gap-1 text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-medium">
                        Full Article Report
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </div>

                {{-- Activity Log in publish section --}}
                <div x-show="prepareLog.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                    <p class="text-xs text-gray-500 mb-2 font-semibold uppercase">Activity Log</p>
                    <template x-for="(entry, idx) in prepareLog" :key="idx">
                        <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                            <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                            <span :class="{
                                'text-green-400': entry.type === 'success',
                                'text-red-400': entry.type === 'error',
                                'text-blue-400': entry.type === 'info',
                                'text-yellow-400': entry.type === 'warning',
                                'text-gray-400': entry.type === 'step',
                            }" x-text="entry.message" class="break-words"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Global notification banner --}}
    <div x-show="notification.show" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg shadow-lg p-4 flex items-center gap-3"
         :class="notification.type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
        <template x-if="notification.type === 'success'">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </template>
        <template x-if="notification.type === 'error'">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </template>
        <span class="text-sm break-words flex-1" :class="notification.type === 'success' ? 'text-green-800' : 'text-red-800'" x-text="notification.message"></span>
        <button @click="notification.show = false" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>

<script>
function publishPipeline() {
    return {
        // Step tracking
        currentStep: 1,
        openSteps: [1],
        completedSteps: [],
        stepLabels: ['User', 'WP Template', 'Website', 'Sources', 'Get Articles', 'AI Template', 'Create Article', 'Publish'],

        // Step 1 — User
        userSearch: '',
        userSearching: false,
        userResults: [],
        selectedUser: null,

        // Step 2 — Preset
        presets: [],
        presetsLoading: false,
        selectedPresetId: '',
        selectedPreset: null,

        // Step 3 — Website
        sites: @json($sites ?? []),
        selectedSiteId: '',
        selectedSite: null,

        // Step 4 — Sources
        sources: [],
        sourceTab: 'paste',
        pasteText: '',
        newsSearch: '',
        newsSearching: false,
        newsResults: [],
        newsMode: 'keyword',
        newsCategory: '',
        newsCountry: 'us',
        bookmarks: [],
        bookmarksLoading: false,

        // Step 5 — Get Articles
        checking: false,
        checkResults: [],
        checkPassCount: 0,
        checkLog: [],
        checkUserAgent: 'chrome',
        extractMethod: 'auto',
        extractRetries: 1,
        extractTimeout: 20,
        extractMinWords: 50,
        extractAutoFallback: true,
        expandedSources: [],
        approvedSources: [],
        discardedSources: [],

        // Step 6 — AI Template
        templates: [],
        templatesLoading: false,
        selectedTemplateId: '',
        selectedTemplate: null,

        // Step 7 — Model
        aiModel: 'claude-opus-4-6',
        customPrompt: '',

        // Step 7 — Spin
        spinning: false,
        spunContent: '',
        spunWordCount: 0,
        spinChangeRequest: '',
        showChangeInput: false,
        smartEditTemplates: [],
        appliedSmartEdits: [],
        suggestedTitles: [],
        selectedTitleIdx: 0,
        selectedCategories: [],
        selectedTags: [],
        metadataLoading: false,
        suggestedUrls: [],
        photoSuggestions: [],
        photoSearch: '',
        photoSearching: false,
        photoResults: [],
        showPhotoPanel: false,
        insertingPhoto: null,
        photoCaption: '',
        _photoSuggestionIdx: null,
        expandedSuggestions: [],
        autoFetchingPhotos: false,
        viewingPhotoIdx: null,
        lastAiCall: null,
        tokenUsage: null,
        spinError: '',

        // Step 8 — Publish (combined)
        articleTitle: '',
        editorContent: '',
        preparing: false,
        prepareChecklist: [],
        prepareLog: [],
        prepareComplete: false,
        preparedHtml: '',
        preparedCategoryIds: [],
        preparedTagIds: [],

        // Step 10 — Publish
        publishAction: 'draft_local',
        publishAuthor: '',
        scheduleDate: '',
        publishing: false,
        publishResult: null,
        publishError: '',

        // Draft
        draftId: {{ $draftId }},
        uploadedImages: {},
        featuredImageSearch: '',
        featuredPhoto: null,
        resolvedPrompt: '',
        articleDescription: '',
        siteAuthors: [],
        siteConnectionLog: [],
        siteConnectionStatus: null,
        savingDraft: false,

        // Notification
        notification: { show: false, type: 'success', message: '' },

        // CSRF token
        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        // ── State Persistence ────────────────────────────
        _stateVersion: 3,

        init() {
            const saved = localStorage.getItem('publishPipelineState');
            if (saved) {
                try {
                    const state = JSON.parse(saved);
                    // Clear stale state from older versions
                    if (!state._v || state._v < this._stateVersion) {
                        localStorage.removeItem('publishPipelineState');
                        return;
                    }
                    if (state.selectedUser) this.selectedUser = state.selectedUser;
                    if (state.currentStep) this.currentStep = state.currentStep;
                    if (state.openSteps) this.openSteps = state.openSteps;
                    if (state.completedSteps) this.completedSteps = state.completedSteps;
                    if (state.selectedSiteId) {
                        this.selectedSiteId = String(state.selectedSiteId);
                        this.selectedSite = state.selectedSite || null;
                        // Reset connection status — will be re-tested
                        this.siteConnectionStatus = null;
                        // Wait for DOM to render options, then select and test
                        setTimeout(() => {
                            this.selectedSiteId = String(state.selectedSiteId);
                            this.selectSite();
                        }, 100);
                    }
                    if (state.sources) this.sources = state.sources;
                    if (state.checkResults) { this.checkResults = state.checkResults; this.checkPassCount = state.checkResults.filter(r => r.success).length; }
                    if (state.approvedSources) this.approvedSources = state.approvedSources;
                    if (state.discardedSources) this.discardedSources = state.discardedSources;
                    if (state.expandedSources) this.expandedSources = state.expandedSources;
                    if (state.aiModel) this.aiModel = state.aiModel;
                    if (state.articleTitle) this.articleTitle = state.articleTitle;
                    if (state.publishAction) this.publishAction = state.publishAction;
                    if (state.spunContent) { this.spunContent = state.spunContent; this.spunWordCount = state.spunWordCount || 0; this.setSpinEditor(state.spunContent); this.extractArticleLinks(state.spunContent); }
                    if (state.suggestedTitles) this.suggestedTitles = state.suggestedTitles;
                    if (state.suggestedCategories) this.suggestedCategories = state.suggestedCategories;
                    if (state.suggestedTags) this.suggestedTags = state.suggestedTags;
                    if (state.selectedCategories) this.selectedCategories = state.selectedCategories;
                    if (state.selectedTags) this.selectedTags = state.selectedTags;
                    if (state.selectedTitleIdx !== undefined) { this.selectedTitleIdx = state.selectedTitleIdx; if (this.suggestedTitles[this.selectedTitleIdx]) this.articleTitle = this.suggestedTitles[this.selectedTitleIdx]; }
                    if (state.editorContent) this.editorContent = state.editorContent;
                    if (state.tokenUsage) this.tokenUsage = state.tokenUsage;
                    if (state.photoSuggestions) this.photoSuggestions = state.photoSuggestions;
                    if (state.featuredImageSearch) this.featuredImageSearch = state.featuredImageSearch;
                    // Reload presets/templates THEN re-select saved values
                    if (this.selectedUser) {
                        const savedPresetId = state.selectedPresetId;
                        const savedPreset = state.selectedPreset;
                        const savedTemplateId = state.selectedTemplateId;
                        const savedTemplate = state.selectedTemplate;
                        Promise.all([this.loadUserPresets(), this.loadUserTemplates()]).then(() => {
                            if (savedPresetId) {
                                this.selectedPresetId = String(savedPresetId);
                                this.selectedPreset = this.presets.find(p => p.id == savedPresetId) || savedPreset;
                            }
                            if (savedTemplateId) {
                                this.selectedTemplateId = String(savedTemplateId);
                                this.selectedTemplate = this.templates.find(t => t.id == savedTemplateId) || savedTemplate;
                            }
                        });
                    }
                } catch (e) { /* ignore corrupt state */ }
            }

            this.$watch('currentStep', () => this.savePipelineState());
            this.$watch('completedSteps', () => this.savePipelineState());
            this.$watch('selectedUser', () => this.savePipelineState());
            this.$watch('selectedPresetId', () => this.savePipelineState());
            this.$watch('selectedTemplateId', () => this.savePipelineState());
            this.$watch('selectedSiteId', () => this.savePipelineState());
            this.$watch('sources', () => this.savePipelineState());
            this.$watch('checkResults', () => this.savePipelineState());
            this.$watch('aiModel', () => this.savePipelineState());
            this.$watch('articleTitle', () => this.savePipelineState());
            this.$watch('spunContent', () => this.savePipelineState());
            this.$watch('editorContent', () => this.savePipelineState());
            this.$watch('photoSuggestions', () => this.savePipelineState());
        },

        savePipelineState() {
            const state = {
                _v: this._stateVersion,
                selectedUser: this.selectedUser,
                currentStep: this.currentStep,
                openSteps: this.openSteps,
                completedSteps: this.completedSteps,
                selectedPresetId: this.selectedPresetId,
                selectedPreset: this.selectedPreset,
                selectedTemplateId: this.selectedTemplateId,
                selectedTemplate: this.selectedTemplate,
                selectedSiteId: this.selectedSiteId,
                selectedSite: this.selectedSite,
                sources: this.sources,
                checkResults: this.checkResults,
                approvedSources: this.approvedSources,
                discardedSources: this.discardedSources,
                expandedSources: this.expandedSources,
                aiModel: this.aiModel,
                articleTitle: this.articleTitle,
                publishAction: this.publishAction,
                spunContent: this.spunContent,
                spunWordCount: this.spunWordCount,
                suggestedTitles: this.suggestedTitles,
                suggestedCategories: this.suggestedCategories,
                suggestedTags: this.suggestedTags,
                selectedCategories: this.selectedCategories,
                selectedTags: this.selectedTags,
                selectedTitleIdx: this.selectedTitleIdx,
                editorContent: this.editorContent,
                tokenUsage: this.tokenUsage,
                photoSuggestions: this.photoSuggestions,
                featuredImageSearch: this.featuredImageSearch,
            };
            localStorage.setItem('publishPipelineState', JSON.stringify(state));
        },

        clearPipeline() {
            localStorage.removeItem('publishPipelineState');
            this.currentStep = 1;
            this.openSteps = [1];
            this.completedSteps = [];
            this.selectedUser = null;
            this.userSearch = '';
            this.presets = [];
            this.selectedPreset = null;
            this.selectedPresetId = '';
            this.templates = [];
            this.selectedTemplate = null;
            this.selectedTemplateId = '';
            this.selectedSiteId = '';
            this.selectedSite = null;
            this.sources = [];
            this.checkResults = [];
            this.aiModel = 'claude-opus-4-6';
            this.spunContent = '';
            this.articleTitle = '';
            this.editorContent = '';
            this.publishAction = 'draft_local';
            this.publishResult = null;
        },

        // ── Navigation ────────────────────────────────────
        isStepAccessible(step) {
            if (step === 1) return true;
            return this.completedSteps.includes(step - 1) || this.completedSteps.includes(step);
        },

        goToStep(step) {
            if (this.isStepAccessible(step)) {
                this.currentStep = step;
                if (!this.openSteps.includes(step)) {
                    this.openSteps = [step];
                }
            }
        },

        toggleStep(step) {
            if (!this.isStepAccessible(step)) return;
            this.currentStep = step;
            if (this.openSteps.includes(step)) {
                this.openSteps = this.openSteps.filter(s => s !== step);
            } else {
                this.openSteps = [step];
            }
        },

        openStep(step) {
            this.currentStep = step;
            this.openSteps = [step];
        },

        completeStep(step) {
            if (!this.completedSteps.includes(step)) {
                this.completedSteps.push(step);
            }
        },

        // ── Step 1: User Search ───────────────────────────
        async searchUsers() {
            if (this.userSearch.length < 2) { this.userResults = []; this.userSearching = false; return; }
            this.userSearching = true;
            try {
                const resp = await fetch(`{{ route('publish.users.search') }}?q=${encodeURIComponent(this.userSearch)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                this.userResults = await resp.json();
            } catch (e) { this.userResults = []; }
            this.userSearching = false;
        },

        async selectUser(user) {
            this.selectedUser = user;
            this.userSearch = '';
            this.userResults = [];
            this.completeStep(1);
            this.openStep(2);
            await Promise.all([this.loadUserPresets(), this.loadUserTemplates()]);

            // Auto-select default preset
            const defaultPreset = this.presets.find(p => p.is_default);
            if (defaultPreset) {
                this.selectedPresetId = String(defaultPreset.id);
                this.selectPreset();
            }

            // Auto-select default AI template
            const defaultTemplate = this.templates.find(t => t.is_default);
            if (defaultTemplate) {
                this.selectedTemplateId = String(defaultTemplate.id);
                this.selectTemplate();
            }
        },

        clearUser() {
            this.selectedUser = null;
            this.presets = [];
            this.selectedPreset = null;
            this.selectedPresetId = '';
            this.templates = [];
            this.selectedTemplate = null;
            this.selectedTemplateId = '';
            this.completedSteps = [];
            this.openSteps = [1];
            this.currentStep = 1;
        },

        async loadUserPresets() {
            if (!this.selectedUser) return;
            this.presetsLoading = true;
            try {
                const resp = await fetch(`{{ route('publish.presets.index') }}?user_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.presets = data.data || data || [];
            } catch (e) { this.presets = []; }
            this.presetsLoading = false;
        },

        async loadUserTemplates() {
            if (!this.selectedUser) return;
            this.templatesLoading = true;
            try {
                const resp = await fetch(`{{ route('publish.templates.index') }}?account_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.templates = data.data || data || [];
            } catch (e) { this.templates = []; }
            this.templatesLoading = false;
        },

        // ── Step 2: Preset ────────────────────────────────
        selectPreset() {
            if (this.selectedPresetId) {
                this.selectedPreset = this.presets.find(p => p.id == this.selectedPresetId) || null;
                // Auto-fill from preset
                if (this.selectedPreset) {
                    if (this.selectedPreset.default_site_id) {
                        this.selectedSiteId = String(this.selectedPreset.default_site_id);
                        this.selectSite();
                    }
                    if (this.selectedPreset.default_publish_action) {
                        const actionMap = {
                            'auto-publish': 'publish',
                            'draft-local': 'draft_local',
                            'draft-wordpress': 'draft_wp',
                            'review': 'draft_local',
                            'notify': 'draft_local',
                        };
                        this.publishAction = actionMap[this.selectedPreset.default_publish_action] || 'draft_local';
                    }
                }
            } else {
                this.selectedPreset = null;
            }
        },

        // ── Step 3: Website ───────────────────────────────
        selectSite() {
            if (this.selectedSiteId) {
                this.selectedSite = this.sites.find(s => s.id == this.selectedSiteId) || null;
                if (this.selectedSite) {
                    // Fetch authors
                    this.siteAuthors = [];
                    fetch('/publish/sites/' + this.selectedSiteId + '/authors', { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json()).then(d => {
                            this.siteAuthors = d.authors || [];
                            if (d.default_author) this.publishAuthor = d.default_author;
                        }).catch(() => {});

                    // Test connection
                    this.siteConnectionLog = [];
                    this.siteConnectionStatus = null;
                    this._logSiteConnection('info', 'Testing connection to ' + this.selectedSite.name + '...');
                    fetch('/publish/sites/' + this.selectedSiteId + '/test-write', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken }
                    }).then(r => r.json()).then(d => {
                        this.siteConnectionStatus = d.success;
                        this._logSiteConnection(d.success ? 'success' : 'error', d.message || (d.success ? 'Connected — write access confirmed' : 'Failed'));
                        if (d.success) { this.completeStep(3); this.openStep(4); }
                    }).catch(e => {
                        this.siteConnectionStatus = false;
                        this._logSiteConnection('error', 'Connection test failed: ' + (e.message || 'Network error'));
                    });
                }
            } else {
                this.selectedSite = null;
                this.publishAuthor = '';
                this.siteAuthors = [];
                this.siteConnectionLog = [];
                this.siteConnectionStatus = null;
            }
        },

        _logSiteConnection(type, message) {
            const time = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.siteConnectionLog.push({ type, message, time });
        },

        confirmSite() {
            if (this.selectedSite) {
                this.completeStep(3);
                this.openStep(4);
            }
        },

        // ── Step 4: Sources ───────────────────────────────
        addPastedUrls() {
            const urls = this.pasteText.split('\n').map(u => u.trim()).filter(u => u && u.startsWith('http'));
            urls.forEach(url => {
                if (!this.sources.find(s => s.url === url)) {
                    this.sources.push({ url, title: '', status: 'pending', wordCount: 0 });
                }
            });
            this.pasteText = '';
        },

        addSource(url, title) {
            if (!this.sources.find(s => s.url === url)) {
                this.sources.push({ url, title: title || '', status: 'pending', wordCount: 0 });
            }
            this.showNotification('success', 'Source added');
        },

        removeSource(idx) {
            this.sources.splice(idx, 1);
        },

        async searchNews() {
            if (this.newsMode === 'keyword' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'local' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'genre' && !this.newsCategory && !this.newsSearch.trim()) return;
            this.newsSearching = true;
            this.newsResults = [];
            try {
                const resp = await fetch('{{ route('publish.search.articles.post') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        query: this.newsSearch,
                        mode: this.newsMode,
                        category: this.newsCategory,
                        country: this.newsCountry,
                    })
                });
                const data = await resp.json();
                this.newsResults = (data.data && data.data.articles) ? data.data.articles : [];
                if (this.newsResults.length === 0 && data.message) {
                    this.notify(data.message, false);
                }
            } catch (e) { this.newsResults = []; this.notify('Search failed: ' + e.message, false); }
            this.newsSearching = false;
        },

        async loadBookmarks() {
            if (this.bookmarks.length > 0 || !this.selectedUser) return;
            this.bookmarksLoading = true;
            try {
                const resp = await fetch(`{{ route('publish.bookmarks.index') }}?user_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.bookmarks = data.data || data || [];
            } catch (e) { this.bookmarks = []; }
            this.bookmarksLoading = false;
        },

        // ── Step 5: Get Articles ──────────────────────────
        toggleSourceExpand(idx) {
            const pos = this.expandedSources.indexOf(idx);
            if (pos === -1) this.expandedSources.push(idx);
            else this.expandedSources.splice(pos, 1);
        },
        approveSource(idx) {
            if (!this.approvedSources.includes(idx)) this.approvedSources.push(idx);
            this.discardedSources = this.discardedSources.filter(i => i !== idx);
        },
        discardSource(idx) {
            if (!this.discardedSources.includes(idx)) this.discardedSources.push(idx);
            this.approvedSources = this.approvedSources.filter(i => i !== idx);
        },

        _logCheck(type, message) {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.checkLog.push({ type, message, time });
        },

        async checkAllSources() {
            if (this.sources.length === 0) return;
            this.checking = true;
            this.checkResults = [];
            this.checkLog = [];
            this.expandedSources = [];
            this.approvedSources = [];
            this.discardedSources = [];
            this.checkPassCount = 0;

            this._logCheck('info', 'Starting article extraction for ' + this.sources.length + ' source(s)...');
            this.sources.forEach((s, i) => this._logCheck('step', (i + 1) + '. ' + s.url));
            this._logCheck('info', 'Method: ' + this.extractMethod + ' | UA: ' + this.checkUserAgent + ' | Timeout: ' + this.extractTimeout + 's | Min words: ' + this.extractMinWords);
            this._logCheck('info', 'Sending extraction request...');

            try {
                const resp = await fetch('{{ route('publish.pipeline.check') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        urls: this.sources.map(s => s.url),
                        user_agent: this.checkUserAgent,
                        method: this.extractMethod,
                        retries: parseInt(this.extractRetries),
                        timeout: parseInt(this.extractTimeout),
                        min_words: parseInt(this.extractMinWords),
                        auto_fallback: this.extractAutoFallback,
                    })
                });
                const data = await resp.json();
                this.checkResults = data.results || [];
                this.checkPassCount = this.checkResults.filter(r => r.success).length;

                // Auto-expand all successful results
                this.expandedSources = this.checkResults.map((r, i) => r.success ? i : null).filter(i => i !== null);

                this.checkResults.forEach((r, i) => {
                    if (this.sources[i]) {
                        this.sources[i].status = r.success ? 'verified' : 'failed';
                        this.sources[i].wordCount = r.word_count;
                        if (r.title && !this.sources[i].title) {
                            this.sources[i].title = r.title;
                        }
                    }
                    const icon = r.success ? 'success' : 'error';
                    const url = this.sources[i]?.url || 'unknown';
                    const detail = r.success
                        ? r.word_count + ' words extracted' + (r.title ? ' — "' + r.title.substring(0, 60) + '"' : '')
                        : (r.message || 'Extraction failed');
                    this._logCheck(icon, url.substring(0, 80) + ' — ' + detail);
                });

                this._logCheck('success', 'Done. ' + this.checkPassCount + '/' + this.checkResults.length + ' sources extracted successfully.');
            } catch (e) {
                this._logCheck('error', 'Network error: ' + (e.message || 'Request failed'));
                this.showNotification('error', 'Failed to check sources');
            }
            this.checking = false;
        },

        async retrySingleSource(idx) {
            const source = this.sources[idx];
            if (!source) return;

            try {
                const resp = await fetch('{{ route('publish.pipeline.check') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        urls: [source.url],
                        user_agent: 'googlebot',
                        method: 'readability',
                        retries: 2,
                        timeout: 30,
                        min_words: 25,
                        auto_fallback: true,
                    })
                });
                const data = await resp.json();
                if (data.results && data.results[0]) {
                    this.checkResults[idx] = data.results[0];
                    this.sources[idx].status = data.results[0].success ? 'verified' : 'failed';
                    this.sources[idx].wordCount = data.results[0].word_count;
                    this.checkPassCount = this.checkResults.filter(r => r.success).length;
                }
            } catch (e) {
                this.showNotification('error', 'Retry failed');
            }
        },

        // ── Step 6: AI Template ─────────────────────────────
        selectTemplate() {
            if (this.selectedTemplateId) {
                this.selectedTemplate = this.templates.find(t => t.id == this.selectedTemplateId) || null;
                // Auto-fill AI model from template
                if (this.selectedTemplate && this.selectedTemplate.ai_engine) {
                    this.aiModel = this.selectedTemplate.ai_engine;
                }
            } else {
                this.selectedTemplate = null;
            }
        },

        // ── Step 7: Spin ──────────────────────────────────
        async spinArticle() {
            this.spinning = true;
            this.spinError = '';

            // Collect verified source texts
            const sourceTexts = this.checkResults
                .filter(r => r.success && r.text)
                .map(r => r.text);

            if (sourceTexts.length === 0) {
                this.spinError = 'No verified source texts available. Please check sources first.';
                this.spinning = false;
                return;
            }

            try {
                const resp = await fetch('{{ route('publish.pipeline.spin') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        source_texts: sourceTexts,
                        template_id: this.selectedTemplateId || null,
                        preset_id: this.selectedPresetId || null,
                        model: this.aiModel,
                        custom_prompt: this.customPrompt || null,
                    })
                });
                const data = await resp.json();

                if (data.success) {
                    this.spunContent = data.html;
                    this.spunWordCount = data.word_count;
                    this.tokenUsage = data.usage;
                    this.setSpinEditor(data.html);
                    this.lastAiCall = { user_name: data.user_name, model: data.model, provider: data.provider, usage: data.usage, cost: data.cost, ip: data.ip, timestamp_utc: data.timestamp_utc };
                    this.showNotification('success', data.message);
                    this.extractArticleLinks(data.html);

                    // Metadata from single prompt (titles, categories, tags)
                    if (data.metadata) {
                        this.suggestedTitles = data.metadata.titles || [];
                        this.suggestedCategories = data.metadata.categories || [];
                        this.suggestedTags = data.metadata.tags || [];
                        if (data.metadata.description) this.articleDescription = data.metadata.description;
                        this.selectedTitleIdx = 0;
                        if (this.suggestedTitles.length > 0) this.articleTitle = this.suggestedTitles[0];
                        this.selectedCategories = Array.from({length: Math.min(10, this.suggestedCategories.length)}, (_, i) => i);
                        this.selectedTags = Array.from({length: Math.min(10, this.suggestedTags.length)}, (_, i) => i);
                    }

                    // Featured image
                    if (data.featured_image) {
                        this.featuredImageSearch = data.featured_image;
                        // Auto-fetch featured image
                        fetch('{{ route("publish.search.images.post") }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken }, body: JSON.stringify({ query: data.featured_image, per_page: 1 }) })
                            .then(r => r.json()).then(d => { if (d.data?.photos?.[0]) this.featuredPhoto = d.data.photos[0]; }).catch(() => {});
                    }

                    // Resolved prompt for preview
                    if (data.resolved_prompt) this.resolvedPrompt = data.resolved_prompt;

                    if (data.photo_suggestions) {
                        this.photoSuggestions = data.photo_suggestions.map(ps => ({...ps, autoPhoto: null, confirmed: false, removed: false, searchResults: []}));
                    }
                } else {
                    this.spinError = data.message;
                }
            } catch (e) {
                this.spinError = 'Network error during spinning.';
            }
            this.spinning = false;
        },

        acceptSpin() {
            // Read latest content from spin TinyMCE editor
            const spinEditor = tinymce.get('spin-preview-editor');
            this.spunContent = spinEditor ? spinEditor.getContent() : this.spunContent;
            // Extract title from H1 and REMOVE from body
            const tmp = document.createElement('div');
            tmp.innerHTML = this.spunContent;
            const h1 = tmp.querySelector('h1');
            if (h1) {
                if (!this.articleTitle) this.articleTitle = h1.textContent.trim();
                h1.remove();
                this.spunContent = tmp.innerHTML;
            }
            this.editorContent = this.spunContent;
            // Populate categories/tags from selections
            if (this.selectedCategories.length > 0 && this.suggestedCategories.length > 0) {
                this.suggestedCategories = this.suggestedCategories.filter((c, i) => this.selectedCategories.includes(i));
            }
            if (this.selectedTags.length > 0 && this.suggestedTags.length > 0) {
                this.suggestedTags = this.suggestedTags.filter((t, i) => this.selectedTags.includes(i));
            }
            this.completeStep(7);
            this.openStep(8);
            this.autoSaveDraft();
        },

        async requestSpinChanges() {
            if (!this.spinChangeRequest.trim()) return;
            // Read latest from spin TinyMCE
            const spinEditor = tinymce.get('spin-preview-editor');
            const currentContent = spinEditor ? spinEditor.getContent() : this.spunContent;
            if (!currentContent) return;
            this.spinning = true;
            this.spinError = '';
            try {
                const resp = await fetch('{{ route('publish.pipeline.spin') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        source_texts: [currentContent],
                        template_id: this.selectedTemplateId || null,
                        preset_id: this.selectedPresetId || null,
                        model: this.aiModel,
                        change_request: this.spinChangeRequest,
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.spunContent = data.html;
                    this.spunWordCount = data.word_count;
                    this.tokenUsage = data.usage;
                    this.setSpinEditor(data.html);
                    this.lastAiCall = { user_name: data.user_name, model: data.model, provider: data.provider, usage: data.usage, cost: data.cost, ip: data.ip, timestamp_utc: data.timestamp_utc };
                    this.spinChangeRequest = '';
                    this.showChangeInput = false;
                    this.appliedSmartEdits = [];
                    this.showNotification('success', 'Changes applied.');
                } else {
                    this.spinError = data.message;
                }
            } catch (e) {
                this.spinError = 'Network error.';
            }
            this.spinning = false;
        },

        setSpinEditor(html) {
            const self = this;
            this.$nextTick(() => {
                const wait = setInterval(() => {
                    const editor = tinymce.get('spin-preview-editor');
                    if (editor) {
                        clearInterval(wait);
                        editor.setContent(html || '');
                        hexaReinitTinyMCE('spin-preview-editor', {
                            plugins: 'lists link image media table fullscreen wordcount code searchreplace autolink autoresize',
                            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link image media | addPhotoBtn | table | alignleft aligncenter alignright | outdent indent | fullscreen code searchreplace',
                            menubar: true,
                            min_height: 400,
                            autoresize_bottom_margin: 50,
                            extended_valid_elements: 'div[*],span[*],img[*],figure[*],figcaption[*]',
                            custom_elements: '~div',
                            content_style: '.photo-placeholder { cursor: pointer !important; } .photo-placeholder:hover { opacity: 0.9; }',
                            setup: function(ed) {
                                // Custom "Add Photo" toolbar button
                                ed.ui.registry.addButton('addPhotoBtn', {
                                    icon: 'image',
                                    tooltip: 'Add Photo at Cursor',
                                    onAction: function() {
                                        self._photoSuggestionIdx = null;
                                        self.photoSearch = '';
                                        self.showPhotoPanel = true;
                                        self.$nextTick(() => {
                                            document.querySelector('[data-photo-section]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
                                        });
                                    }
                                });

                                ed.on('init', function() {
                                    ed.setContent(html || '');
                                    // Auto-fetch photos AFTER editor is fully initialized
                                    if (self.photoSuggestions.length > 0 && !self.photoSuggestions[0].autoPhoto) {
                                        self.autoFetchPhotos();
                                    }
                                });
                                ed.on('change keyup', function() { self.extractArticleLinks(ed.getContent()); });

                                // Handle clicks on photo placeholders and action buttons
                                ed.on('click', function(e) {
                                    const target = e.target;

                                    // Action button: View
                                    if (target.classList && target.classList.contains('photo-view')) {
                                        e.preventDefault();
                                        const ph = target.closest('.photo-placeholder');
                                        if (ph) self.viewPhotoInfo(parseInt(ph.getAttribute('data-idx')));
                                        return;
                                    }
                                    // Action button: Confirm
                                    if (target.classList && target.classList.contains('photo-confirm')) {
                                        e.preventDefault();
                                        const ph = target.closest('.photo-placeholder');
                                        if (ph) self.confirmPhoto(parseInt(ph.getAttribute('data-idx')));
                                        return;
                                    }
                                    // Action button: Change
                                    if (target.classList && target.classList.contains('photo-change')) {
                                        e.preventDefault();
                                        const ph = target.closest('.photo-placeholder');
                                        if (ph) self.changePhoto(parseInt(ph.getAttribute('data-idx')));
                                        return;
                                    }
                                    // Action button: Remove
                                    if (target.classList && target.classList.contains('photo-remove')) {
                                        e.preventDefault();
                                        const ph = target.closest('.photo-placeholder');
                                        if (ph) self.removePhotoPlaceholder(parseInt(ph.getAttribute('data-idx')));
                                        return;
                                    }

                                    // Clicking placeholder itself — scroll to its suggestion row
                                    const placeholder = target.closest('.photo-placeholder') || (target.classList && target.classList.contains('photo-placeholder') ? target : null);
                                    if (placeholder) {
                                        const idx = parseInt(placeholder.getAttribute('data-idx'));
                                        if (!isNaN(idx)) self.viewPhotoInfo(idx);
                                    }
                                });
                            }
                        });
                    }
                }, 200);
            });
        },

        async generateMetadata(html) {
            this.metadataLoading = true;
            try {
                const resp = await fetch('{{ route("publish.pipeline.metadata") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ article_html: html })
                });
                const data = await resp.json();
                if (data.success) {
                    this.suggestedTitles = data.titles || [];
                    this.suggestedCategories = data.categories || [];
                    this.suggestedTags = data.tags || [];
                    this.suggestedUrls = data.urls || [];
                    this.selectedTitleIdx = 0;
                    if (this.suggestedTitles.length > 0) this.articleTitle = this.suggestedTitles[0];
                    this.selectedCategories = Array.from({length: Math.min(10, this.suggestedCategories.length)}, (_, i) => i);
                    this.selectedTags = Array.from({length: Math.min(10, this.suggestedTags.length)}, (_, i) => i);
                }
            } catch (e) { /* silently fail */ }
            this.metadataLoading = false;
        },

        toggleSelection(arr, idx) {
            const pos = arr.indexOf(idx);
            if (pos === -1) arr.push(idx);
            else arr.splice(pos, 1);
        },

        extractArticleLinks(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            const anchors = tmp.querySelectorAll('a[href]');
            this.suggestedUrls = Array.from(anchors).map(a => ({
                url: a.getAttribute('href'),
                title: a.textContent.trim() || a.getAttribute('href'),
                nofollow: (a.getAttribute('rel') || '').includes('nofollow'),
            })).filter(l => l.url && l.url.startsWith('http'));
        },

        async searchPhotos() {
            if (!this.photoSearch.trim()) return;
            this.photoSearching = true;
            this.photoResults = [];
            try {
                const resp = await fetch('{{ route("publish.search.images.post") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ query: this.photoSearch, per_page: 15 })
                });
                const data = await resp.json();
                this.photoResults = data.data?.photos || [];
            } catch (e) { this.photoResults = []; }
            this.photoSearching = false;
        },

        async searchFeaturedImage() {
            if (!this.featuredImageSearch.trim()) return;
            try {
                const resp = await fetch('{{ route("publish.search.images.post") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ query: this.featuredImageSearch, per_page: 1 })
                });
                const data = await resp.json();
                if (data.data?.photos?.[0]) this.featuredPhoto = data.data.photos[0];
            } catch (e) {}
        },

        insertPhotoIntoEditor() {
            if (!this.insertingPhoto) return;
            const photo = this.insertingPhoto;
            const caption = this.photoCaption || '';
            const imgUrl = photo.url_full || photo.url_large || photo.url_thumb;
            const figureHtml = '<figure class="wp-block-image"><img src="' + imgUrl + '" alt="' + caption.replace(/"/g, '&quot;') + '" style="max-width:100%;height:auto"><figcaption>' + caption + '</figcaption></figure>';
            const editor = tinymce.get('spin-preview-editor');
            if (editor) {
                if (this._photoSuggestionIdx !== null) {
                    const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + this._photoSuggestionIdx + '"]');
                    if (placeholder) {
                        editor.dom.setOuterHTML(placeholder, figureHtml);
                        this.photoSuggestions[this._photoSuggestionIdx].autoPhoto = photo;
                        this.photoSuggestions[this._photoSuggestionIdx].confirmed = true;
                    } else {
                        editor.execCommand('mceInsertContent', false, figureHtml);
                    }
                    this._photoSuggestionIdx = null;
                } else {
                    editor.execCommand('mceInsertContent', false, figureHtml);
                }
            }
            this.insertingPhoto = null;
            this.photoCaption = '';
            this.showPhotoPanel = false;
        },

        // ── Photo Management ─────────────────────────────
        async autoFetchPhotos() {
            if (this.photoSuggestions.length === 0) return;
            this.autoFetchingPhotos = true;
            const promises = this.photoSuggestions.map(async (ps, idx) => {
                try {
                    const resp = await fetch('{{ route("publish.search.images.post") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                        body: JSON.stringify({ query: ps.search_term, per_page: 12 })
                    });
                    const data = await resp.json();
                    const photos = data.data?.photos || [];
                    if (photos.length > 0) {
                        this.photoSuggestions[idx].autoPhoto = photos[0];
                        this.photoSuggestions[idx].searchResults = photos;
                        this.updatePlaceholderInEditor(idx);
                    }
                } catch (e) { /* silently fail */ }
            });
            await Promise.all(promises);
            this.autoFetchingPhotos = false;
        },

        updatePlaceholderInEditor(idx, retries) {
            retries = retries || 0;
            const editor = tinymce.get('spin-preview-editor');
            if (!editor || !editor.getBody()) {
                if (retries < 10) { const self = this; setTimeout(() => self.updatePlaceholderInEditor(idx, retries + 1), 500); }
                return;
            }
            const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
            if (!placeholder) {
                if (retries < 10) { const self = this; setTimeout(() => self.updatePlaceholderInEditor(idx, retries + 1), 500); }
                return;
            }
            const ps = this.photoSuggestions[idx];
            if (!ps || !ps.autoPhoto) return;
            const thumbUrl = ps.autoPhoto.url_large || ps.autoPhoto.url_thumb;
            const altText = ps.alt_text || ps.caption || '';
            const newHtml = '<div class="photo-placeholder" contenteditable="false" data-idx="' + idx + '" data-search="' + this._escHtml(ps.search_term) + '" data-caption="' + this._escHtml(altText) + '" style="border:2px solid #a78bfa;background:#faf5ff;border-radius:8px;padding:12px;margin:16px 0;cursor:pointer;">'
                + '<img src="' + thumbUrl + '" style="width:300px;max-width:100%;height:auto;object-fit:cover;border-radius:6px;display:block;margin-bottom:8px;" />'
                + '<p style="margin:0 0 4px;font-size:12px;color:#7c3aed;font-weight:600;">' + this._escHtml(ps.search_term) + '</p>'
                + '<p style="margin:0 0 8px;font-size:11px;color:#6b7280;">' + this._escHtml(altText) + '</p>'
                + '<span class="photo-view" style="cursor:pointer;display:inline-block;background:#7c3aed;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-right:4px;">View</span>'
                + '<span class="photo-confirm" style="cursor:pointer;display:inline-block;background:#16a34a;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-right:4px;font-weight:600;">Confirm</span>'
                + '<span class="photo-change" style="cursor:pointer;display:inline-block;background:#2563eb;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-right:4px;">Change</span>'
                + '<span class="photo-remove" style="cursor:pointer;display:inline-block;background:#dc2626;color:white;padding:2px 8px;border-radius:4px;font-size:11px;">Remove</span>'
                + '</div>';
            editor.dom.setOuterHTML(placeholder, newHtml);
        },

        _escHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        _slugify(str) {
            return String(str || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 80);
        },

        viewPhotoInfo(idx) {
            this.viewingPhotoIdx = idx;
            const ps = this.photoSuggestions[idx];
            if (!ps) return;
            // Generate suggested filename if not already set
            if (!ps.suggestedFilename) {
                const ext = (ps.autoPhoto?.url_full || '').split('.').pop()?.split('?')[0] || 'jpg';
                ps.suggestedFilename = this._slugify(ps.alt_text || ps.search_term) + '.' + ext;
            }
            this.$nextTick(() => {
                document.querySelector('[data-photo-modal]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
            });
        },

        confirmPhoto(idx) {
            const ps = this.photoSuggestions[idx];
            if (!ps || !ps.autoPhoto) return;
            const editor = tinymce.get('spin-preview-editor');
            if (!editor) return;
            const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
            if (!placeholder) return;
            const photo = ps.autoPhoto;
            const altText = (ps.alt_text || '').replace(/"/g, '&quot;');
            const caption = ps.caption || '';
            const imgUrl = photo.url_full || photo.url_large || photo.url_thumb;
            const figureHtml = '<figure class="wp-block-image"><img src="' + imgUrl + '" alt="' + altText + '" style="max-width:100%;height:auto">' + (caption ? '<figcaption>' + this._escHtml(caption) + '</figcaption>' : '') + '</figure>';
            editor.dom.setOuterHTML(placeholder, figureHtml);
            this.photoSuggestions[idx].confirmed = true;
            this.viewingPhotoIdx = null;
        },

        changePhoto(idx) {
            this._photoSuggestionIdx = idx;
            if (!this.expandedSuggestions.includes(idx)) this.expandedSuggestions.push(idx);
            this.$nextTick(() => {
                document.querySelector('[data-photo-section]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
            });
        },

        removePhotoPlaceholder(idx) {
            const editor = tinymce.get('spin-preview-editor');
            if (editor) {
                const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
                if (placeholder) editor.dom.remove(placeholder);
            }
            this.photoSuggestions[idx].removed = true;
        },

        async searchPhotosForSuggestion(idx) {
            const ps = this.photoSuggestions[idx];
            if (!ps || !ps.search_term.trim()) return;
            this.photoSuggestions[idx].searching = true;
            try {
                const resp = await fetch('{{ route("publish.search.images.post") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ query: ps.search_term, per_page: 20 })
                });
                const data = await resp.json();
                this.photoSuggestions[idx].searchResults = data.data?.photos || [];
            } catch (e) { this.photoSuggestions[idx].searchResults = []; }
            this.photoSuggestions[idx].searching = false;
        },

        selectPhotoForSuggestion(idx, photo) {
            this.photoSuggestions[idx].autoPhoto = photo;
            this.photoSuggestions[idx].confirmed = false;
            this.updatePlaceholderInEditor(idx);
        },

        async loadSmartEdits() {
            if (this.smartEditTemplates.length > 0) return;
            try {
                const resp = await fetch('{{ route("publish.smart-edits.index") }}?format=json', { headers: { 'Accept': 'application/json' } });
                this.smartEditTemplates = await resp.json();
            } catch (e) { this.smartEditTemplates = []; }
        },

        appendSmartEdit(tpl) {
            if (this.appliedSmartEdits.includes(tpl.id)) {
                // Remove it
                this.appliedSmartEdits = this.appliedSmartEdits.filter(id => id !== tpl.id);
                this.spinChangeRequest = this.spinChangeRequest.replace('[' + tpl.name + '] ' + tpl.prompt + '\n', '');
            } else {
                // Append it
                this.appliedSmartEdits.push(tpl.id);
                this.spinChangeRequest = this.spinChangeRequest + '[' + tpl.name + '] ' + tpl.prompt + '\n';
            }
        },

        // ── Step 8: Review → Prepare ────────────────────
        finishEditing() {
            this.completeStep(8);
            this.openStep(9);
            this.autoSaveDraft();
        },

        // ── Step 9: Prepare ──────────────────────────────
        _logPrepare(type, message) {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.prepareLog.push({ type, message, time });
            this.$nextTick(() => {
                const el = this.$refs.prepareLogContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        async prepareForWp() {
            if (!this.selectedSite) {
                this.showNotification('error', 'No WordPress site selected');
                return;
            }
            this.preparing = true;
            this.prepareLog = [];
            this.prepareComplete = false;
            this.prepareChecklist = [];

            try {
                const resp = await fetch('{{ route('publish.pipeline.prepare') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        html: this.editorContent,
                        title: this.articleTitle || 'article',
                        site_id: this.selectedSite.id,
                        categories: this.suggestedCategories,
                        tags: this.suggestedTags,
                        draft_id: this.draftId,
                    })
                });

                // Read SSE stream
                const reader = resp.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop(); // Keep incomplete line in buffer

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            try {
                                const event = JSON.parse(line.substring(6));
                                // Add to activity log
                                this._logPrepare(event.type, event.message);

                                // Track uploaded images for duplicate prevention
                                if (event.wp_image) {
                                    this.uploadedImages[event.wp_image.source_url] = event.wp_image;
                                }

                                // Handle final 'done' event
                                if (event.type === 'done') {
                                    if (event.success) {
                                        this.preparedHtml = event.html || this.editorContent;
                                        this.preparedCategoryIds = event.category_ids || [];
                                        this.preparedTagIds = event.tag_ids || [];
                                        if (event.wp_images) {
                                            event.wp_images.forEach(img => { this.uploadedImages[img.source_url] = img; });
                                        }
                                        this.prepareComplete = true;
                                        this.showNotification('success', 'Content prepared for WordPress');
                                    } else {
                                        this.prepareComplete = false;
                                        this.showNotification('error', event.message || 'Preparation failed');
                                    }
                                }
                            } catch (parseErr) { /* skip malformed lines */ }
                        }
                    }
                }
            } catch (e) {
                this._logPrepare('error', 'Connection error: ' + (e.message || 'Request failed'));
                this.showNotification('error', 'Network error during preparation');
            }
            this.preparing = false;
        },

        // ── Step 10: Publish ──────────────────────────────
        async publishArticle() {
            this.publishing = true;
            this.publishResult = null;
            this.publishError = '';

            // Save local draft only
            if (this.publishAction === 'draft_local') {
                await this.saveDraftNow();
                this.publishResult = { message: 'Saved as local draft.', post_url: null, draft_id: this.draftId };
                this.completeStep(10);
                this.publishing = false;
                return;
            }

            // WP publish
            if (!this.selectedSite) {
                this.publishError = 'No WordPress site selected.';
                this.publishing = false;
                return;
            }

            const wpStatus = this.publishAction === 'future' ? 'future' : (this.publishAction === 'draft_wp' ? 'draft' : 'publish');

            try {
                const resp = await fetch('{{ route('publish.pipeline.publish') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        html: this.preparedHtml || this.editorContent,
                        title: this.articleTitle || 'Untitled',
                        site_id: this.selectedSite.id,
                        category_ids: this.preparedCategoryIds,
                        tag_ids: this.preparedTagIds,
                        status: wpStatus,
                        date: this.publishAction === 'future' ? this.scheduleDate : null,
                        draft_id: this.draftId,
                        draft_id: this.draftId,
                        categories: this.suggestedCategories,
                        tags: this.suggestedTags,
                        wp_images: Object.values(this.uploadedImages),
                        word_count: this.spunWordCount,
                        ai_model: this.aiModel,
                        ai_cost: this.lastAiCall?.cost || null,
                        ai_provider: this.lastAiCall?.provider || null,
                        ai_tokens_input: this.lastAiCall?.usage?.input_tokens || null,
                        ai_tokens_output: this.lastAiCall?.usage?.output_tokens || null,
                        resolved_prompt: this.resolvedPrompt || null,
                        photo_suggestions: this.photoSuggestions || null,
                        featured_image_search: this.featuredImageSearch || null,
                        author: this.publishAuthor || null,
                        sources: this.sources.map(s => ({ url: s.url, title: s.title })),
                        template_id: this.selectedTemplateId || null,
                        preset_id: this.selectedPresetId || null,
                        user_id: this.selectedUser?.id || null,
                    })
                });
                const data = await resp.json();

                if (data.success) {
                    this.publishResult = data;
                    this.completeStep(10);
                    this.showNotification('success', data.message);
                } else {
                    this.publishError = data.message;
                }
            } catch (e) {
                this.publishError = 'Network error during publishing.';
            }
            this.publishing = false;
            this.autoSaveDraft();
        },

        // ── Draft ─────────────────────────────────────────
        async saveDraftNow() {
            this.savingDraft = true;
            try {
                const resp = await fetch('{{ route('publish.pipeline.save-draft') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        title: this.articleTitle || 'Untitled Pipeline Draft',
                        body: this.editorContent,
                        user_id: this.selectedUser?.id || null,
                        site_id: this.selectedSite?.id || null,
                        preset_id: this.selectedPresetId || null,
                        template_id: this.selectedTemplateId || null,
                        ai_model: this.aiModel,
                        sources: this.sources.map(s => ({ url: s.url, title: s.title })),
                        tags: this.suggestedTags,
                        categories: this.suggestedCategories,
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.draftId = data.draft_id;
                    this.showNotification('success', data.message);
                } else {
                    this.showNotification('error', data.message);
                }
            } catch (e) {
                this.showNotification('error', 'Failed to save draft');
            }
            this.savingDraft = false;
        },

        autoSaveDraft() {
            // Fire and forget
            this.saveDraftNow();
        },

        // ── Notifications ─────────────────────────────────
        showNotification(type, message) {
            this.notification = { show: true, type, message };
            // Errors stay permanently, success fades after 8 seconds
            if (type === 'success') {
                setTimeout(() => { this.notification.show = false; }, 8000);
            }
            // Errors stay until user dismisses or next action
        },
    };
}
</script>
@endsection
