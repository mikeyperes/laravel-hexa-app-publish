{{-- Publish Article Pipeline — 11-step wizard --}}
@extends('layouts.app')
@section('title', 'Publish Article')
@section('header', 'Publish Article')

@section('content')
<div class="max-w-5xl mx-auto space-y-4" x-data="publishPipeline()">

    {{-- Clear button --}}
    <div x-show="completedSteps.length > 0" x-cloak class="flex justify-end">
        <button @click="clearPipeline()" class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 border border-red-200 rounded-lg hover:bg-red-50 inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Clear &amp; Start Over
        </button>
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
                <input type="text" x-model="userSearch" @input.debounce.300ms="searchUsers()"
                       placeholder="Type to search users..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
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
            <div class="max-w-md">
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
                <span x-show="selectedSite" x-cloak class="text-sm text-green-600" x-text="selectedSite ? selectedSite.name : ''"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(3) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(3)" x-cloak x-collapse class="px-4 pb-4">
            <div class="max-w-md">
                <label class="block text-xs text-gray-500 mb-1">WordPress Site</label>
                <select x-model="selectedSiteId" @change="selectSite()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- Select a site --</option>
                    <template x-for="s in sites" :key="s.id">
                        <option :value="s.id" x-text="s.name + ' (' + s.url + ')'"></option>
                    </template>
                </select>
            </div>
            <div x-show="selectedSite" x-cloak class="mt-3 flex items-center gap-2 bg-green-50 rounded-lg px-3 py-2">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                <span class="text-sm text-green-800" x-text="selectedSite ? selectedSite.name + ' - ' + selectedSite.url : ''"></span>
            </div>
            <div class="mt-3">
                <button @click="confirmSite()" :disabled="!selectedSite" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Continue &rarr;</button>
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
                <div class="flex gap-2">
                    <input type="text" x-model="newsSearch" @keydown.enter="searchNews()" placeholder="Search for articles..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <button @click="searchNews()" :disabled="newsSearching" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="newsSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Search
                    </button>
                </div>
                <div x-show="newsResults.length > 0" x-cloak class="mt-3">
                    <p class="text-xs text-gray-500 mb-2" x-text="newsResults.length + ' article(s) found'"></p>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        <template x-for="(article, idx) in newsResults" :key="idx">
                            <div class="border rounded-lg p-3" :class="sources.some(s => s.url === article.url) ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-gray-200 hover:bg-gray-50'">
                                <div class="flex items-start justify-between gap-3">
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
            <div class="flex items-center gap-3 mb-4">
                <button @click="checkAllSources()" :disabled="checking" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                    <svg x-show="checking" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="checking ? 'Extracting...' : 'Get Articles'"></span>
                </button>
                <select x-model="checkUserAgent" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="chrome">Chrome UA</option>
                    <option value="firefox">Firefox UA</option>
                    <option value="safari">Safari UA</option>
                    <option value="googlebot">Googlebot UA</option>
                    <option value="bot">HWS Bot UA</option>
                </select>
            </div>

            <div x-show="checkResults.length > 0" x-cloak class="space-y-3">
                <template x-for="(result, idx) in checkResults" :key="idx">
                    <div class="rounded-lg border" :class="result.success ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'">
                        {{-- Header row — click to expand --}}
                        <div @click="result._expanded = !result._expanded" class="flex items-start gap-3 px-3 py-2 cursor-pointer hover:bg-opacity-80">
                            <span :class="result.success ? 'text-green-500' : 'text-red-500'" class="mt-0.5 flex-shrink-0">
                                <svg x-show="result.success" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <svg x-show="!result.success" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <a :href="result.url" target="_blank" @click.stop class="text-sm font-medium break-all inline-flex items-center gap-1 hover:underline" :class="result.success ? 'text-green-800' : 'text-red-800'">
                                    <span x-text="result.url"></span>
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <p x-show="result.title" class="text-xs text-gray-600 font-medium mt-0.5" x-text="result.title"></p>
                                <p class="text-xs" :class="result.success ? 'text-green-600' : 'text-red-600'" x-text="result.message"></p>
                                <p x-show="result.success" class="text-xs text-green-600" x-text="result.word_count + ' words — click to expand'"></p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button x-show="!result.success" @click.stop="retrySingleSource(idx)" class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 bg-blue-50 rounded">Retry</button>
                                <svg x-show="result.success" class="w-4 h-4 text-gray-400 transition-transform" :class="result._expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        {{-- Expanded article text --}}
                        <div x-show="result._expanded && result.text" x-cloak class="px-3 pb-3 border-t" :class="result.success ? 'border-green-200' : 'border-red-200'">
                            <div class="mt-2 bg-white rounded-lg border border-gray-200 p-3 text-sm text-gray-700 break-words whitespace-pre-wrap max-h-96 overflow-y-auto" x-text="result.text"></div>
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
            <div class="max-w-md">
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
                <span class="font-semibold text-gray-800">Spin Article</span>
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

            {{-- Preview --}}
            <div x-show="spunContent" x-cloak class="border border-gray-200 rounded-lg p-4 bg-gray-50 prose prose-sm max-w-none break-words" x-html="spunContent"></div>

            <div class="mt-3" x-show="spunContent" x-cloak>
                <button @click="acceptSpin()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Accept & Edit &rarr;</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 8: Editor
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 8, 'opacity-50': !isStepAccessible(9) }">
        <button @click="toggleStep(8)" :disabled="!isStepAccessible(9)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(8) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(8)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(8)"><span>9</span></template>
                </span>
                <span class="font-semibold text-gray-800">Editor</span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(8) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(8)" x-cloak x-collapse class="px-4 pb-4">
            {{-- Title --}}
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Article Title</label>
                <input type="text" x-model="articleTitle" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold" placeholder="Enter article title...">
            </div>

            {{-- TinyMCE editor via core component --}}
            <div class="mb-4">
                <x-hexa-tinymce name="pipeline-body" value="" preset="wordpress" :height="500" id="pipeline-editor" />
            </div>

            {{-- Photos panel --}}
            <div class="border border-gray-200 rounded-lg p-3 mb-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-2">Insert Photos</h5>
                <div class="flex gap-2 mb-2">
                    <input type="text" x-model="photoSearch" @keydown.enter="searchPhotos()" placeholder="Search photos..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <button @click="searchPhotos()" :disabled="photoSearching" class="bg-gray-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-gray-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="photoSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Search
                    </button>
                </div>
                <div x-show="photoResults.length > 0" x-cloak class="flex flex-wrap gap-2 max-h-48 overflow-y-auto">
                    <template x-for="(photo, idx) in photoResults" :key="idx">
                        <button @click="insertPhotoAtCursor(photo)" class="w-20 h-20 rounded border border-gray-200 overflow-hidden hover:ring-2 hover:ring-blue-400 flex-shrink-0">
                            <img :src="photo.thumbnail || photo.url" :alt="photo.alt || 'Photo'" class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>
            </div>

            {{-- Tags --}}
            <div class="mb-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-2">Tags</h5>
                <div class="flex flex-wrap gap-2 mb-2">
                    <template x-for="(tag, idx) in suggestedTags" :key="idx">
                        <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                            <span x-text="tag"></span>
                            <button @click="suggestedTags.splice(idx, 1)" class="text-blue-500 hover:text-blue-700">&times;</button>
                        </span>
                    </template>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="newTag" @keydown.enter.prevent="addTag()" placeholder="Add tag..." class="border border-gray-300 rounded-lg px-3 py-1 text-sm w-48">
                    <button @click="addTag()" class="text-sm text-blue-600 hover:text-blue-800">+ Add</button>
                </div>
            </div>

            {{-- Categories --}}
            <div class="mb-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-2">Categories</h5>
                <div class="flex flex-wrap gap-2 mb-2">
                    <template x-for="(cat, idx) in suggestedCategories" :key="idx">
                        <span class="inline-flex items-center gap-1 bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                            <span x-text="cat"></span>
                            <button @click="suggestedCategories.splice(idx, 1)" class="text-green-500 hover:text-green-700">&times;</button>
                        </span>
                    </template>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="newCategory" @keydown.enter.prevent="addCategory()" placeholder="Add category..." class="border border-gray-300 rounded-lg px-3 py-1 text-sm w-48">
                    <button @click="addCategory()" class="text-sm text-green-600 hover:text-green-800">+ Add</button>
                </div>
            </div>

            {{-- Links panel --}}
            <div class="mb-4" x-show="articleLinks.length > 0" x-cloak>
                <h5 class="text-sm font-semibold text-gray-700 mb-2">Article Links</h5>
                <div class="space-y-2">
                    <template x-for="(link, idx) in articleLinks" :key="idx">
                        <div class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-500 break-all" x-text="link.href"></p>
                                <p class="text-xs text-gray-700" x-text="'Text: ' + link.text"></p>
                            </div>
                            <button @click="toggleLinkFollow(idx)"
                                    :class="link.nofollow ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                                    class="text-xs px-2 py-1 rounded" x-text="link.nofollow ? 'nofollow' : 'follow'"></button>
                        </div>
                    </template>
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 9, 'opacity-50': !isStepAccessible(10) }">
        <button @click="toggleStep(9)" :disabled="!isStepAccessible(10)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(9) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(9)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(9)"><span>10</span></template>
                </span>
                <span class="font-semibold text-gray-800">Prepare for WordPress</span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(9) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(9)" x-cloak x-collapse class="px-4 pb-4">
            <button @click="prepareForWp()" :disabled="preparing" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2 mb-4">
                <svg x-show="preparing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="preparing ? 'Preparing...' : 'Prepare for WordPress'"></span>
            </button>

            {{-- Checklist --}}
            <div x-show="prepareChecklist.length > 0" x-cloak class="space-y-2">
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 10, 'opacity-50': !isStepAccessible(11) }">
        <button @click="toggleStep(10)" :disabled="!isStepAccessible(11)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(10) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(10)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(10)"><span>11</span></template>
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

            {{-- Publish result --}}
            <div x-show="publishResult" x-cloak class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="font-semibold text-green-800" x-text="publishResult?.message || 'Published successfully!'"></span>
                </div>
                <div x-show="publishResult?.post_url" class="mt-2">
                    <a :href="publishResult?.post_url" target="_blank" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 font-medium">
                        View Post
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
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
        <span class="text-sm break-words" :class="notification.type === 'success' ? 'text-green-800' : 'text-red-800'" x-text="notification.message"></span>
    </div>
</div>

<script>
function publishPipeline() {
    return {
        // Step tracking
        currentStep: 1,
        openSteps: [1],
        completedSteps: [],
        stepLabels: ['User', 'WP Template', 'Website', 'Sources', 'Get Articles', 'AI Template', 'Spin', 'Editor', 'Prepare', 'Publish'],

        // Step 1 — User
        userSearch: '',
        userResults: [],
        selectedUser: null,

        // Step 2 — Preset
        presets: [],
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
        bookmarks: [],
        bookmarksLoading: false,

        // Step 5 — Check
        checking: false,
        checkResults: [],
        checkPassCount: 0,
        checkUserAgent: 'chrome',

        // Step 6 — AI Template
        templates: [],
        selectedTemplateId: '',
        selectedTemplate: null,

        // Step 7 — Model
        aiModel: 'claude-opus-4-6',

        // Step 7 — Spin
        spinning: false,
        spunContent: '',
        spunWordCount: 0,
        tokenUsage: null,
        spinError: '',

        // Step 8 — Editor
        articleTitle: '',
        editorContent: '',
        editorInstance: null,
        photoSearch: '',
        photoSearching: false,
        photoResults: [],
        suggestedTags: [],
        suggestedCategories: [],
        newTag: '',
        newCategory: '',
        articleLinks: [],

        // Step 9 — Prepare
        preparing: false,
        prepareChecklist: [],
        prepareComplete: false,
        preparedHtml: '',
        preparedCategoryIds: [],
        preparedTagIds: [],

        // Step 10 — Publish
        publishAction: 'draft_local',
        scheduleDate: '',
        publishing: false,
        publishResult: null,
        publishError: '',

        // Draft
        draftId: null,
        savingDraft: false,

        // Notification
        notification: { show: false, type: 'success', message: '' },

        // CSRF token
        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        // ── State Persistence ────────────────────────────
        init() {
            const saved = localStorage.getItem('publishPipelineState');
            if (saved) {
                try {
                    const state = JSON.parse(saved);
                    if (state.selectedUser) this.selectedUser = state.selectedUser;
                    if (state.currentStep) this.currentStep = state.currentStep;
                    if (state.openSteps) this.openSteps = state.openSteps;
                    if (state.completedSteps) this.completedSteps = state.completedSteps;
                    if (state.selectedPresetId) this.selectedPresetId = state.selectedPresetId;
                    if (state.selectedPreset) this.selectedPreset = state.selectedPreset;
                    if (state.selectedTemplateId) this.selectedTemplateId = state.selectedTemplateId;
                    if (state.selectedTemplate) this.selectedTemplate = state.selectedTemplate;
                    if (state.selectedSiteId) this.selectedSiteId = state.selectedSiteId;
                    if (state.selectedSite) this.selectedSite = state.selectedSite;
                    if (state.sources) this.sources = state.sources;
                    if (state.checkResults) { this.checkResults = state.checkResults; this.checkPassCount = state.checkResults.filter(r => r.success).length; }
                    if (state.aiModel) this.aiModel = state.aiModel;
                    if (state.articleTitle) this.articleTitle = state.articleTitle;
                    if (state.publishAction) this.publishAction = state.publishAction;
                    if (state.spunContent) { this.spunContent = state.spunContent; this.spunWordCount = state.spunWordCount || 0; }
                    if (state.editorContent) this.editorContent = state.editorContent;
                    if (state.tokenUsage) this.tokenUsage = state.tokenUsage;
                    // Reload presets/templates if user was selected
                    if (this.selectedUser) {
                        this.loadUserPresets();
                        this.loadUserTemplates();
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
        },

        savePipelineState() {
            const state = {
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
                aiModel: this.aiModel,
                articleTitle: this.articleTitle,
                publishAction: this.publishAction,
                spunContent: this.spunContent,
                spunWordCount: this.spunWordCount,
                editorContent: this.editorContent,
                tokenUsage: this.tokenUsage,
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
            if (this.userSearch.length < 2) { this.userResults = []; return; }
            try {
                const resp = await fetch(`{{ route('publish.users.search') }}?q=${encodeURIComponent(this.userSearch)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                this.userResults = await resp.json();
            } catch (e) { this.userResults = []; }
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
            try {
                const resp = await fetch(`{{ route('publish.presets.index') }}?user_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.presets = data.data || data || [];
            } catch (e) { this.presets = []; }
        },

        async loadUserTemplates() {
            if (!this.selectedUser) return;
            try {
                const resp = await fetch(`{{ route('publish.templates.index') }}?account_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.templates = data.data || data || [];
            } catch (e) { this.templates = []; }
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
            } else {
                this.selectedSite = null;
            }
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
            if (!this.newsSearch.trim()) return;
            this.newsSearching = true;
            this.newsResults = [];
            try {
                const resp = await fetch('{{ route('publish.search.articles.post') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ query: this.newsSearch })
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
        async checkAllSources() {
            if (this.sources.length === 0) return;
            this.checking = true;
            this.checkResults = [];
            this.checkPassCount = 0;

            try {
                const resp = await fetch('{{ route('publish.pipeline.check') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        urls: this.sources.map(s => s.url),
                        user_agent: this.checkUserAgent,
                    })
                });
                const data = await resp.json();
                this.checkResults = data.results || [];
                this.checkPassCount = this.checkResults.filter(r => r.success).length;

                // Update sources with check info
                this.checkResults.forEach((r, i) => {
                    if (this.sources[i]) {
                        this.sources[i].status = r.success ? 'verified' : 'failed';
                        this.sources[i].wordCount = r.word_count;
                        if (r.title && !this.sources[i].title) {
                            this.sources[i].title = r.title;
                        }
                    }
                });
            } catch (e) {
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
                        user_agent: this.checkUserAgent === 'chrome' ? 'googlebot' : 'chrome',
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
                    })
                });
                const data = await resp.json();

                if (data.success) {
                    this.spunContent = data.html;
                    this.spunWordCount = data.word_count;
                    this.tokenUsage = data.usage;
                    this.showNotification('success', data.message);
                } else {
                    this.spinError = data.message;
                }
            } catch (e) {
                this.spinError = 'Network error during spinning.';
            }
            this.spinning = false;
        },

        acceptSpin() {
            this.editorContent = this.spunContent;
            this.completeStep(7);
            this.openStep(8);
            // Init TinyMCE after a tick so the DOM is visible
            this.$nextTick(() => this.initEditor());
            // Auto-save draft
            this.autoSaveDraft();
        },

        // ── Step 8: Editor ────────────────────────────────
        initEditor() {
            const self = this;
            // Core component auto-inits. Set content and attach change listener.
            const waitForEditor = setInterval(() => {
                const editor = tinymce.get('pipeline-editor');
                if (editor) {
                    clearInterval(waitForEditor);
                    self.editorInstance = editor;
                    editor.setContent(self.editorContent);
                    editor.on('change keyup', () => {
                        self.editorContent = editor.getContent();
                        self.extractLinks();
                    });
                }
            }, 200);
        },

        extractLinks() {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = this.editorContent;
            const anchors = tempDiv.querySelectorAll('a[href]');
            this.articleLinks = Array.from(anchors).map(a => ({
                href: a.getAttribute('href'),
                text: a.textContent,
                nofollow: (a.getAttribute('rel') || '').includes('nofollow'),
            }));
        },

        toggleLinkFollow(idx) {
            this.articleLinks[idx].nofollow = !this.articleLinks[idx].nofollow;
            // Update the HTML
            if (this.editorInstance) {
                let html = this.editorInstance.getContent();
                const link = this.articleLinks[idx];
                const hrefEscaped = link.href.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                if (link.nofollow) {
                    html = html.replace(
                        new RegExp(`(<a[^>]*href=["']${hrefEscaped}["'][^>]*)>`, 'gi'),
                        (match, p1) => {
                            if (p1.includes('rel=')) {
                                return p1.replace(/rel=["'][^"']*["']/, 'rel="nofollow"') + '>';
                            }
                            return p1 + ' rel="nofollow">';
                        }
                    );
                } else {
                    html = html.replace(
                        new RegExp(`(<a[^>]*href=["']${hrefEscaped}["'][^>]*)\s*rel=["']nofollow["']([^>]*)>`, 'gi'),
                        '$1$2>'
                    );
                }
                this.editorInstance.setContent(html);
                this.editorContent = html;
            }
        },

        async searchPhotos() {
            if (!this.photoSearch.trim()) return;
            this.photoSearching = true;
            this.photoResults = [];
            try {
                const resp = await fetch(`{{ route('publish.photos.search') }}?query=${encodeURIComponent(this.photoSearch)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.photoResults = (data.results || []).map(p => ({
                    url: p.url || p.src?.medium || p.webformatURL || '',
                    thumbnail: p.thumbnail || p.src?.tiny || p.previewURL || p.url || '',
                    alt: p.alt || p.description || p.tags || '',
                }));
            } catch (e) { this.photoResults = []; }
            this.photoSearching = false;
        },

        insertPhotoAtCursor(photo) {
            if (this.editorInstance) {
                this.editorInstance.insertContent(`<img src="${photo.url}" alt="${photo.alt || ''}" style="max-width:100%;height:auto;" />`);
                this.editorContent = this.editorInstance.getContent();
            }
        },

        addTag() {
            const tag = this.newTag.trim();
            if (tag && !this.suggestedTags.includes(tag)) {
                this.suggestedTags.push(tag);
            }
            this.newTag = '';
        },

        addCategory() {
            const cat = this.newCategory.trim();
            if (cat && !this.suggestedCategories.includes(cat)) {
                this.suggestedCategories.push(cat);
            }
            this.newCategory = '';
        },

        finishEditing() {
            if (this.editorInstance) {
                this.editorContent = this.editorInstance.getContent();
            }
            this.completeStep(8);
            this.openStep(9);
            this.autoSaveDraft();
        },

        // ── Step 9: Prepare ──────────────────────────────
        async prepareForWp() {
            if (!this.selectedSite) {
                this.showNotification('error', 'No WordPress site selected');
                return;
            }
            this.preparing = true;
            this.prepareChecklist = [
                { step: 'upload_images', label: 'Uploading images to WordPress media library...', status: 'running', detail: '' },
                { step: 'replace_urls', label: 'Replacing image URLs...', status: 'running', detail: '' },
                { step: 'create_categories', label: 'Creating categories on WordPress...', status: 'running', detail: '' },
                { step: 'create_tags', label: 'Creating tags on WordPress...', status: 'running', detail: '' },
                { step: 'validate_html', label: 'Validating HTML...', status: 'running', detail: '' },
            ];
            this.prepareComplete = false;

            try {
                const resp = await fetch('{{ route('publish.pipeline.prepare') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        html: this.editorContent,
                        site_id: this.selectedSite.id,
                        categories: this.suggestedCategories,
                        tags: this.suggestedTags,
                    })
                });
                const data = await resp.json();

                if (data.checklist) {
                    this.prepareChecklist = data.checklist;
                }

                this.preparedHtml = data.html || this.editorContent;
                this.preparedCategoryIds = data.category_ids || [];
                this.preparedTagIds = data.tag_ids || [];
                this.prepareComplete = data.success;

                if (data.success) {
                    this.showNotification('success', 'Content prepared for WordPress');
                } else {
                    this.showNotification('error', data.message || 'Preparation failed');
                }
            } catch (e) {
                this.prepareChecklist.forEach(item => { if (item.status === 'running') item.status = 'failed'; });
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
                this.publishResult = { message: 'Saved as local draft.', post_url: null };
                this.completeStep(10);
                this.publishing = false;
                this.autoSaveDraft();
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
            if (this.editorInstance) {
                this.editorContent = this.editorInstance.getContent();
            }
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
            setTimeout(() => { this.notification.show = false; }, 4000);
        },
    };
}
</script>
@endsection
