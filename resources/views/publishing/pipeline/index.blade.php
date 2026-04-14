{{-- Publish Article Pipeline — 11-step wizard --}}
@extends('layouts.app')
@section('title', 'Publish Article — #' . $draftId)
@section('header', 'Publish Article — #' . $draftId)

@section('content')
<div class="space-y-4" x-data="publishPipeline()"
     @hexa-form-changed.window="
        if ($event.detail.component_id === 'article-preset-form') { $data.template_overrides[$event.detail.field] = $event.detail.value; $data.template_dirty[$event.detail.field] = true; $data.savePipelineState(); }
     ">

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
                <div class="flex items-center gap-1" x-show="!(currentArticleType === 'press-release' && idx === 3)">
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
            <div class="max-w-md"
                 @hexa-search-selected.window="if ($event.detail.component_id === 'pipeline-user') selectUser($event.detail.item)"
                 @hexa-search-cleared.window="if ($event.detail.component_id === 'pipeline-user') clearUser()">
                @php
                    $pipelineSelectedUser = isset($draftUser) && $draftUser ? ['id' => $draftUser->id, 'name' => $draftUser->name, 'email' => $draftUser->email] : null;
                @endphp
                <x-hexa-smart-search
                    url="{{ route('api.search.users') }}"
                    name="user_id"
                    label="Search by name or email"
                    placeholder="Type to search users..."
                    display-field="name"
                    subtitle-field="email"
                    value-field="id"
                    id="pipeline-user"
                    show-id
                    :selected="$pipelineSelectedUser"
                />
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 2: Article Configuration
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 2, 'opacity-50': !isStepAccessible(2) }">
        <button @click="toggleStep(2)" :disabled="!isStepAccessible(2)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(2) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(2)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(2)"><span>2</span></template>
                </span>
                <span class="font-semibold text-gray-800">Article Configuration</span>
                <span x-show="selectedSite && siteConn.status === true" x-cloak class="text-sm text-green-600" x-text="selectedSite?.name"></span>
                <span x-show="selectedSite && siteConn.status === null && siteConn.testing" x-cloak class="text-sm text-blue-500 inline-flex items-center gap-1">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Connecting...
                </span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(2) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(2)" x-cloak x-collapse class="px-4 pb-4 space-y-4">

            {{-- Article Type --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Article Type</label>
                <div x-show="templatesLoading" class="flex items-center gap-2 text-sm text-blue-500 py-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading...
                </div>
                <select x-show="!templatesLoading" id="article-type-select" x-model="template_overrides.article_type"
                    @change="$data.template_dirty.article_type = true; autoSelectPrSource()"
                    class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select article type —</option>
                    @foreach(config('hws-publish.article_types', []) as $type)
                        <option value="{{ $type }}">{{ ucwords(str_replace('-', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Publishing Site --}}
            <div x-show="currentArticleType && currentArticleType !== 'press-release'" x-cloak>
                <label class="block text-xs text-gray-500 mb-1">Publishing Site</label>
                <select x-model="selectedSiteId" @change="selectSite()" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select a site —</option>
                    <template x-for="s in sites" :key="s.id">
                        <option :value="String(s.id)" :selected="String(s.id) === selectedSiteId" x-text="s.name + ' (' + s.url + ')'"></option>
                    </template>
                </select>
                @include('app-publish::publishing.pipeline.partials.site-connection-status')
            </div>

            {{-- Press Release Destination --}}
            <div x-show="currentArticleType === 'press-release' && !templatesLoading" x-cloak>
                <label class="block text-xs text-gray-500 mb-1">Press Release Destination</label>
                <template x-if="prSourceSites.length > 0">
                    <select x-model="selectedSiteId" @change="selectSite()" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Select press release site —</option>
                        <template x-for="s in prSourceSites" :key="s.id">
                            <option :value="String(s.id)" :selected="String(s.id) === selectedSiteId" x-text="s.name + ' (' + s.url + ')'"></option>
                        </template>
                    </select>
                </template>
                <div x-show="prSourceSites.length === 0" x-cloak class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                    No press release destinations configured. <a href="{{ route('publish.settings.master') }}" target="_blank" class="text-blue-600 hover:underline font-medium">Set up in Publishing Settings</a>.
                </div>
                @include('app-publish::publishing.pipeline.partials.site-connection-status')
            </div>

            {{-- Article Preset (includes WordPress publishing fields) --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-200">
                    <div>
                        <h5 class="text-sm font-bold text-gray-800">Article Preset</h5>
                        <div x-show="selectedTemplate && !editingTemplate" x-cloak class="mt-1">
                            <p class="text-base font-semibold text-gray-900" x-text="selectedTemplate?.name || 'None'"></p>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700" x-text="selectedTemplate?.ai_engine || '—'"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-700" x-text="'Tone: ' + (Array.isArray(selectedTemplate?.tone) ? selectedTemplate.tone.join(', ') : (selectedTemplate?.tone || '—'))"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600" x-text="(selectedTemplate?.word_count_min || '—') + '–' + (selectedTemplate?.word_count_max || '—') + ' words'"></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a x-show="selectedTemplate" x-cloak :href="'/publish/article-presets/' + selectedTemplateId + '/edit'" target="_blank" class="text-xs text-gray-400 hover:text-blue-600 inline-flex items-center gap-0.5">Edit on preset page <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        <button @click="editingTemplate = !editingTemplate" class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 border border-blue-200 rounded hover:bg-blue-50" x-text="editingTemplate ? 'Cancel' : 'Change'"></button>
                    </div>
                </div>
                {{-- Loading spinner --}}
                <div x-show="templatesLoading" x-cloak class="flex items-center gap-2 text-sm text-blue-500 py-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading article presets...
                </div>
                <div x-show="!templatesLoading && !editingTemplate && !selectedTemplate" class="text-xs text-gray-400 py-2">No article preset selected — using defaults.</div>
                <div x-show="editingTemplate" x-cloak class="mt-2">
                    <div x-show="templatesLoading" class="flex items-center gap-2 text-sm text-gray-500 py-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading...
                    </div>
                    <select x-show="!templatesLoading" x-model="selectedTemplateId" @change="selectTemplate(); editingTemplate = false; refreshPromptPreview()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- No preset --</option>
                        <template x-for="t in templates" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                </div>
                {{-- Loading spinner while templates are being fetched --}}
                <div x-show="templatesLoading" x-cloak class="flex items-center justify-center gap-2 py-6 text-sm text-blue-500">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading preset settings...
                </div>
                <div x-show="selectedTemplate && !templatesLoading" x-cloak>
                    <x-hexa-reactive-form
                        :fields-json="json_encode($articlePresetForm->toClientPayload('pipeline'))"
                        values="{}"
                        id="article-preset-form"
                        label="Article Preset Settings"
                        />
                </div>
            </div>


            {{-- Next button — always visible when site selected --}}
            <div x-show="selectedSite" class="mt-3">
                <button @click="completeStep(2); openStep(3)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Next &rarr;</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 3: Find Articles / Generate Content
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 3, 'opacity-50': !isStepAccessible(3) }">
        <button @click="toggleStep(3)" :disabled="!isStepAccessible(3)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(3) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(3)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(3)"><span>3</span></template>
                </span>
                <span class="font-semibold text-gray-800" x-text="stepLabels[2] || (isGenerateMode ? 'Generate Content' : 'Find Articles')"></span>
                <span x-show="sources.length > 0" x-cloak class="text-sm text-green-600" x-text="sources.length + ' source(s)'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(3) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(3)" x-cloak x-collapse class="px-4 pb-4">

            {{-- ═══ Generate Content mode ═══ --}}
            <div x-show="isGenerateMode" x-cloak>

                @include('app-publish::publishing.pipeline.partials.press-release-submit-step')

                {{-- Listicle --}}
                <div x-show="currentArticleType === 'listicle'" class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-gray-800">Listicle</h4>
                            <p class="text-xs text-gray-400">Generate a list-based article from a topic</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-500">
                        <p class="font-medium text-gray-600 mb-2">Workflow — pending implementation</p>
                        <ul class="list-disc list-inside space-y-1 text-xs text-gray-400">
                            <li>Topic and list theme</li>
                            <li>Number of items</li>
                            <li>Item depth and detail level</li>
                            <li>AI generates structured listicle</li>
                        </ul>
                    </div>
                    <button @click="completeStep(3); completeStep(4); openStep(5)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to AI & Spin &rarr;</button>
                </div>

                {{-- Expert Article / PR Full Feature (shared section) --}}
                <div x-show="currentArticleType === 'expert-article' || currentArticleType === 'pr-full-feature'" class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-gray-800" x-text="currentArticleType === 'pr-full-feature' ? 'PR Full Feature' : 'Expert Article'"></h4>
                            <p class="text-xs text-gray-400">Select profiles to feature in this article</p>
                        </div>
                    </div>

                    {{-- PR Subject Picker --}}
                    <div class="space-y-3">
                        <label class="text-sm font-medium text-gray-700">Select PR Subjects</label>
                        <div class="relative">
                            <input type="text" x-model="prProfileSearch" @input.debounce.300ms="searchPrProfiles()" @focus="searchPrProfiles()"
                                placeholder="Search profiles by name..."
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                            <svg x-show="prProfileSearching" x-cloak class="absolute right-3 top-2.5 w-5 h-5 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>

                            {{-- Search results dropdown --}}
                            <div x-show="prProfileResults.length > 0 && prProfileDropdownOpen" x-cloak @click.away="prProfileDropdownOpen = false"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                <template x-for="profile in prProfileResults" :key="profile.id">
                                    <button @click="addPrProfile(profile); prProfileDropdownOpen = false;"
                                        class="w-full text-left px-4 py-3 hover:bg-blue-50 border-b border-gray-100 last:border-b-0 flex items-center gap-3"
                                        :class="selectedPrProfiles.some(p => p.id === profile.id) ? 'opacity-40 cursor-not-allowed' : ''"
                                        :disabled="selectedPrProfiles.some(p => p.id === profile.id)">
                                        <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            <img x-show="profile.photo_url" x-cloak :src="profile.photo_url" class="w-full h-full object-cover">
                                            <svg x-show="!profile.photo_url" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="profile.name"></p>
                                            <p class="text-xs text-gray-400" x-text="profile.type + (profile.description ? ' — ' + profile.description.substring(0, 60) : '')"></p>
                                        </div>
                                        <span x-show="selectedPrProfiles.some(p => p.id === profile.id)" x-cloak class="text-xs text-gray-400">Added</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Selected profiles — full person cards --}}
                        <div x-show="selectedPrProfiles.length > 0" x-cloak class="space-y-4">
                            <p class="text-xs text-gray-500 font-medium" x-text="selectedPrProfiles.length + ' subject(s) selected'"></p>
                            <template x-for="(profile, pidx) in selectedPrProfiles" :key="profile.id">
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                    {{-- Card header --}}
                                    <div class="flex items-center gap-3 px-5 py-4 bg-gradient-to-r from-blue-50 to-white border-b border-gray-100">
                                        <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden ring-2 ring-blue-200">
                                            <img x-show="profile.photo_url" x-cloak :src="profile.photo_url" class="w-full h-full object-cover">
                                            <svg x-show="!profile.photo_url" class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-base font-bold text-gray-900" x-text="profile.name"></p>
                                            <p class="text-xs text-gray-500" x-text="profile.type + (profile.description ? ' — ' + profile.description.substring(0, 80) : '')"></p>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <button @click="loadProfileData(profile)" :disabled="prSubjectData[profile.id]?.loading" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 disabled:opacity-50">
                                                <svg x-show="prSubjectData[profile.id]?.loading" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                <span x-text="prSubjectData[profile.id]?.loaded ? 'Refresh' : (prSubjectData[profile.id]?.loading ? 'Loading...' : 'Load Details')"></span>
                                            </button>
                                            <a x-show="prSubjectData[profile.id]?.notionUrl || (profile.external_source === 'notion' && profile.external_id)" x-cloak
                                                :href="prSubjectData[profile.id]?.notionUrl || ('https://notion.so/' + (profile.external_id || '').replace(/-/g, ''))"
                                                target="_blank" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700" title="Open in Notion">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L18.39 2.33c-.42-.326-.98-.7-2.055-.607L3.01 2.87c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.84-.046.933-.56.933-1.167V6.354c0-.606-.233-.933-.746-.886l-15.177.887c-.56.046-.747.326-.747.933zm14.337.745c.093.42 0 .84-.42.886l-.7.14v10.264c-.607.327-1.167.514-1.634.514-.746 0-.933-.234-1.493-.933l-4.572-7.186v6.952l1.446.327s0 .84-1.167.84l-3.22.186c-.093-.186 0-.653.327-.746l.84-.233V9.854L7.326 9.76c-.094-.42.14-1.026.793-1.073l3.453-.233 4.759 7.278v-6.44l-1.213-.14c-.094-.513.28-.886.746-.933zM2.778 1.474l13.728-1.027c1.68-.14 2.1.094 2.8.607l3.874 2.753c.467.374.607.7.607 1.167v16.843c0 1.027-.374 1.634-1.68 1.727L6.937 24.22c-.98.047-1.447-.093-1.96-.747l-3.08-4.012c-.56-.747-.793-1.307-.793-1.96V3.107c0-.84.373-1.54 1.68-1.633z"/></svg>
                                                Notion
                                            </a>
                                            <a :href="'/profiles/' + profile.id" target="_blank" class="text-blue-500 hover:text-blue-700" title="View profile">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                            <button @click="selectedPrProfiles.splice(pidx, 1); delete prSubjectData[profile.id]; savePipelineState();" class="text-red-400 hover:text-red-600" title="Remove">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Loading indicator --}}
                                    <div x-show="prSubjectData[profile.id]?.loading" x-cloak class="px-5 py-4 flex items-center gap-2 text-sm text-gray-500">
                                        <svg class="w-4 h-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Loading Notion data...
                                    </div>

                                    {{-- Fields section --}}
                                    <div x-show="prSubjectData[profile.id]?.fields?.length > 0" x-cloak class="px-5 py-3 border-b border-gray-100">
                                        <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Profile Data</h5>
                                        <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                                            <template x-for="field in (prSubjectData[profile.id]?.fields || [])" :key="field.key">
                                                <div class="flex items-start px-3 py-2 text-xs">
                                                    <span class="text-gray-500 font-medium w-36 flex-shrink-0 pt-0.5" x-text="field.notion_field"></span>
                                                    <span class="text-gray-800 break-words flex-1" x-text="field.display_value || field.value || '—'"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Photos section with checkboxes --}}
                                    <div x-show="prSubjectData[profile.id]?.photos?.length > 0" x-cloak class="px-5 py-3 border-b border-gray-100">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                                Photos
                                                <span class="text-gray-300 font-normal ml-1" x-text="'(' + (prSubjectData[profile.id]?.photos?.length || 0) + ')'"></span>
                                            </h5>
                                            <span class="text-[10px] text-gray-400">Check photos to include in article</span>
                                        </div>
                                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                            <template x-for="photo in (prSubjectData[profile.id]?.photos || [])" :key="photo.id">
                                                <label class="relative rounded-lg overflow-hidden border-2 aspect-square bg-gray-100 cursor-pointer transition-all"
                                                    :class="prSubjectData[profile.id]?.selectedPhotos?.[photo.id] ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200 hover:border-gray-300'">
                                                    <input type="checkbox" class="sr-only" @change="togglePrPhotoSelect(profile.id, photo.id)" :checked="prSubjectData[profile.id]?.selectedPhotos?.[photo.id]">
                                                    <img :src="photo.thumbnailLink || photo.webContentLink" :alt="photo.name" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                                                    <div x-show="prSubjectData[profile.id]?.selectedPhotos?.[photo.id]" class="absolute top-1 right-1 w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                    </div>
                                                    <div class="absolute inset-x-0 bottom-0 bg-black/50 px-1.5 py-1">
                                                        <p class="text-[9px] text-white truncate" x-text="photo.name"></p>
                                                    </div>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="prSubjectData[profile.id]?.loadingPhotos" x-cloak class="px-5 py-3 border-b border-gray-100 flex items-center gap-2 text-xs text-gray-500">
                                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Loading photos...
                                    </div>

                                    {{-- Related Notion Content with checkboxes --}}
                                    <div x-show="prSubjectData[profile.id]?.relations?.length > 0" x-cloak class="px-5 py-3 border-b border-gray-100">
                                        <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Related Content</h5>
                                        <div class="space-y-2">
                                            <template x-for="rel in (prSubjectData[profile.id]?.relations || [])" :key="rel.slug">
                                                <div class="rounded-lg border border-gray-200 overflow-hidden">
                                                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 text-xs font-semibold text-gray-700">
                                                        <span x-text="rel.label"></span>
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-600" x-text="rel.count || 0"></span>
                                                        <svg x-show="rel.loading" class="w-3 h-3 animate-spin ml-auto text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                    </div>
                                                    <div x-show="rel.entries?.length > 0" class="divide-y divide-gray-100">
                                                        <template x-for="entry in (rel.entries || [])" :key="entry.id">
                                                            <div>
                                                                <div class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 transition-colors">
                                                                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5" @change="togglePrEntrySelect(profile.id, entry.id)" :checked="prSubjectData[profile.id]?.selectedEntries?.[entry.id]">
                                                                    <button type="button" @click="togglePrEntry(profile.id, rel.slug, entry.id)" class="flex-1 text-left text-xs text-gray-800 hover:text-indigo-600 break-words" x-text="entry.title"></button>
                                                                    <svg class="w-3 h-3 text-gray-300 flex-shrink-0 transition-transform" :class="entry.open ? 'rotate-90 text-indigo-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                                </div>
                                                                <div x-show="entry.open" x-cloak class="mx-3 mb-2 p-3 rounded-lg border border-indigo-200 bg-indigo-50/30 text-xs">
                                                                    <div x-show="entry.loading" class="flex items-center gap-2 text-gray-500 py-1">
                                                                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                                        Loading...
                                                                    </div>
                                                                    <template x-if="entry.detail">
                                                                        <div class="space-y-1">
                                                                            <template x-for="[k,v] in Object.entries(entry.detail.properties || {}).slice(0, 10)" :key="k">
                                                                                <div class="flex gap-2">
                                                                                    <span class="text-gray-500 w-28 flex-shrink-0" x-text="k"></span>
                                                                                    <span class="text-gray-800 break-words" x-text="typeof v === 'object' ? JSON.stringify(v) : v"></span>
                                                                                </div>
                                                                            </template>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Context textarea --}}
                                    <div class="px-5 py-4">
                                        <div class="flex items-center gap-2 mb-2">
                                            <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Context within article</label>
                                            <div class="relative group">
                                                <span class="w-4 h-4 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-[10px] font-bold cursor-help">?</span>
                                                <div class="absolute left-6 top-1/2 -translate-y-1/2 w-72 bg-gray-900 text-gray-200 text-xs rounded-lg p-3 shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50">
                                                    <p class="font-semibold text-white mb-1">How to use this</p>
                                                    <p>Describe how this person should appear in the article. You can mention: what aspects of their work to highlight, their role in the story, quotes or talking points to include, and the tone to use when writing about them. This is optional — you can also specify context in the general prompt when drafting.</p>
                                                    <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <textarea x-model="profile.context" @change="savePipelineState()" rows="3" placeholder="e.g. Focus on their recent achievements in AI research. Mention their role as CTO. Use a professional tone..."
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 resize-y"></textarea>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p x-show="selectedPrProfiles.length === 0" class="text-sm text-gray-400">No subjects selected. Search and add profiles above.</p>
                    </div>

                    <button @click="completeStep(3); completeStep(4); openStep(5)" :disabled="selectedPrProfiles.length === 0"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Continue to AI & Spin &rarr;
                    </button>
                </div>
            </div>

            {{-- Find Articles mode (for editorial, opinion, news-report, local-news) --}}
            <div x-show="!isGenerateMode">
            {{-- Tabs --}}
            <div class="flex border-b border-gray-200 mb-4">
                <button @click="sourceTab = 'ai'" :class="sourceTab === 'ai' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Search Online</button>
                <button @click="sourceTab = 'upload'" :class="sourceTab === 'upload' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Upload</button>
                <button @click="sourceTab = 'paste'" :class="sourceTab === 'paste' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Paste Links</button>
                <button @click="sourceTab = 'search'" :class="sourceTab === 'search' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Search News</button>
                <button @click="sourceTab = 'bookmarks'; loadBookmarks()" :class="sourceTab === 'bookmarks' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">Bookmarks</button>
            </div>

            {{-- Upload tab --}}
            <div x-show="sourceTab === 'upload'">
                <p class="text-sm text-gray-600 mb-3">Upload a document or paste content directly. Supported formats: <strong>.doc, .docx, .pdf</strong></p>

                {{-- File upload --}}
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Upload Document</p>
                            <p class="text-xs text-gray-500 mt-1">Accepted: .doc, .docx, .pdf — content will be extracted as source text</p>
                        </div>
                        <label class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 cursor-pointer">
                            <svg x-show="uploadingSourceDoc" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="uploadingSourceDoc ? 'Uploading...' : 'Choose File'"></span>
                            <input type="file" class="hidden" accept=".doc,.docx,.pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf" @change="uploadSourceDocument($event.target.files); $event.target.value = null">
                        </label>
                    </div>
                </div>

                {{-- Uploaded file display --}}
                <div x-show="uploadedSourceDoc" x-cloak class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-green-800 break-words" x-text="uploadedSourceDoc?.name || ''"></p>
                            <p class="text-xs text-green-600 mt-0.5" x-text="(uploadedSourceDoc?.word_count || 0) + ' words extracted'"></p>
                        </div>
                        <button @click="uploadedSourceDoc = null; uploadedSourceText = ''" class="text-xs text-red-500 hover:text-red-700 flex-shrink-0">Remove</button>
                    </div>
                </div>

                {{-- OR separator --}}
                <div class="flex items-center gap-3 my-4">
                    <div class="flex-1 border-t border-gray-200"></div>
                    <span class="text-xs text-gray-400 font-medium">OR PASTE CONTENT DIRECTLY</span>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>

                {{-- Paste content area --}}
                <textarea x-model="uploadedSourceText" rows="8" placeholder="Paste your article content, press release text, or any raw content here..." class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm leading-relaxed" style="min-height: 150px; resize: vertical;"></textarea>
                <p class="text-xs text-gray-400 mt-1" x-show="uploadedSourceText" x-cloak x-text="uploadedSourceText.split(/\s+/).filter(Boolean).length + ' words'"></p>

                {{-- Action buttons --}}
                <div class="flex flex-wrap gap-3 mt-4">
                    <button @click="useUploadedContent()" :disabled="!uploadedSourceText && !uploadedSourceDoc" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                        Use as Source &amp; Continue to Spin
                    </button>
                    <button @click="skipSpinPublishAsIs()" :disabled="!uploadedSourceText && !uploadedSourceDoc" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-50 inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        Skip Spinning — Clean Up &amp; Publish As Is
                    </button>
                </div>
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
                    <button @click="setNewsMode('keyword')" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'keyword' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Keyword</button>
                    <button @click="setNewsMode('local')" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'local' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Local News</button>
                    <button @click="setNewsMode('trending')" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'trending' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Trending</button>
                    <button @click="setNewsMode('genre')" class="px-3 py-1 rounded-full text-xs font-medium transition-colors" :class="newsMode === 'genre' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Genre</button>
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
                        <button @click="selectTrendingCategory('')" class="px-3 py-1 rounded-full text-xs font-medium" :class="newsTrendingSelected && !newsCategory ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">All</button>
                        @foreach($newsCategories as $cat)
                        <button @click="selectTrendingCategory('{{ $cat }}')" class="px-3 py-1 rounded-full text-xs font-medium" :class="newsTrendingSelected && newsCategory === '{{ $cat }}' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">{{ ucfirst($cat) }}</button>
                        @endforeach
                    </div>
                    <p x-show="!newsTrendingSelected && !newsSearching" x-cloak class="text-xs text-gray-500">Choose a trending category to load articles.</p>
                </div>

                {{-- Genre --}}
                <div x-show="newsMode === 'genre'" class="flex gap-2">
                    <select x-model="newsCategory" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select genre...</option>
                        @foreach($newsCategories as $cat)
                        <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                        @endforeach
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
                            <div class="border rounded-lg p-4" :class="sources.some(s => s.url === article.url) ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-gray-200 hover:bg-gray-50'">
                                <div class="flex items-start gap-4">
                                    <img x-show="article.image" x-cloak :src="article.image" :alt="article.title" loading="lazy" class="rounded-lg object-cover flex-shrink-0 bg-gray-100" style="width:195px;height:130px;" onerror="this.style.display='none'">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-base font-semibold text-gray-900 break-words leading-snug" x-text="article.title"></p>
                                        <p class="text-xs text-gray-500 mt-0.5" x-text="(() => { try { return new URL(article.url).hostname; } catch { return ''; } })()"></p>
                                        <p class="text-xs text-gray-400 mt-0.5" x-text="article.source_name"></p>
                                        <p class="text-sm text-gray-600 mt-1.5 break-words line-clamp-3" x-text="article.description"></p>
                                        <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
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
                <div x-show="newsResults.length === 0 && !newsSearching && newsHasSearched" x-cloak class="mt-3 text-sm text-gray-400">No articles found.</div>
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

            {{-- AI Article Finder tab --}}
            <div x-show="sourceTab === 'ai'">
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <p class="text-sm text-gray-600">Find top articles on any topic using AI web search</p>
                        <div class="relative group">
                            <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xs font-bold cursor-help">?</span>
                            <div class="absolute bottom-7 left-1/2 -translate-x-1/2 w-72 bg-gray-900 text-gray-200 text-xs rounded-lg p-3 shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50">
                                <p class="font-semibold text-white mb-1">Search Online</p>
                                <p>Claude AI searches the web in real-time to find recent news articles on your topic. Multiple sources on the same topic are intentionally selected — this gives the AI spinner more material to produce a unique, high-quality rewrite.</p>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" x-model="aiSearchTopic" @keydown.enter="aiSearchArticles()" placeholder="e.g. cryptocurrency regulations 2026" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <select x-model="aiSearchModel" class="border border-gray-300 rounded-lg px-2 py-2 text-xs w-auto">
                            <optgroup label="Claude">
                                @foreach(config('anthropic.models', []) as $m)
                                    @if(($m['type'] ?? '') === 'api' || ($m['type'] ?? '') === 'both')
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                            @if(config('chatgpt.models'))
                            <optgroup label="GPT">
                                @foreach(config('chatgpt.models', []) as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                @endforeach
                            </optgroup>
                            @endif
                            @if(!empty($grokModels))
                            <optgroup label="Grok">
                                @foreach($grokModels as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                @endforeach
                            </optgroup>
                            @endif
                        </select>
                        <button @click="aiSearchArticles()" :disabled="aiSearching || !aiSearchTopic.trim()" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2">
                            <svg x-show="aiSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="aiSearching ? 'Searching...' : 'Find Articles'"></span>
                        </button>
                    </div>

                    {{-- Activity Log --}}
                    <div x-show="aiLog.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                        <template x-for="(entry, idx) in aiLog" :key="idx">
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

                    {{-- Cost Summary --}}
                    <div x-show="aiSearchCost && !aiSearching" x-cloak class="bg-gray-900 rounded-lg border border-gray-700 px-4 py-3">
                        <div class="flex flex-wrap gap-6 text-xs">
                            <div>
                                <span class="text-gray-500">Model</span>
                                <p class="font-medium text-white" x-text="aiSearchCost?.model || '—'"></p>
                            </div>
                            <div>
                                <span class="text-gray-500">Cost</span>
                                <p class="font-medium text-green-400" x-text="'$' + (aiSearchCost?.cost || 0).toFixed(4)"></p>
                            </div>
                            <div>
                                <span class="text-gray-500">Tokens</span>
                                <p class="font-medium text-white" x-text="(aiSearchCost?.usage?.input_tokens || 0) + ' in / ' + (aiSearchCost?.usage?.output_tokens || 0) + ' out'"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Results --}}
                    <div x-show="aiSearchResults.length > 0 && !aiSearching" x-cloak>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-3 text-sm text-blue-700 flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Multiple sources featuring the same topic are selected for a unique quality spin.
                        </div>

                        <p class="text-xs text-gray-500 mb-2" x-text="aiSearchResults.length + ' article(s) found — click + Add to select sources'"></p>
                        <div class="space-y-2">
                            <template x-for="(article, idx) in aiSearchResults" :key="idx">
                                <div class="border rounded-lg p-3" :class="sources.some(s => s.url === article.url) ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 break-words" x-text="article.title"></p>
                                            <a :href="article.url" target="_blank" @click.stop class="text-xs text-blue-500 hover:underline break-all mt-0.5 inline-flex items-center gap-1">
                                                <span x-text="article.url"></span>
                                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                            <p x-show="article.description" x-cloak class="text-xs text-gray-500 mt-1 break-words" x-text="article.description"></p>
                                        </div>
                                        <div class="flex-shrink-0 flex flex-col gap-1">
                                            <span x-show="sources.some(s => s.url === article.url)" class="text-xs text-green-600 font-medium px-2 py-1">Added</span>
                                            <button x-show="sources.some(s => s.url === article.url)" @click="removeSource(sources.findIndex(s => s.url === article.url))" class="text-red-500 hover:text-red-700 text-xs font-medium px-2 py-1">Remove</button>
                                            <button x-show="!sources.some(s => s.url === article.url)" @click="addSource(article.url, article.title)" class="text-blue-600 hover:text-blue-800 text-xs font-medium px-2 py-1 bg-blue-50 rounded hover:bg-blue-100">+ Add</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <button @click="aiSearchArticles()" :disabled="aiSearching" class="mt-3 text-sm text-purple-600 hover:text-purple-800 font-medium flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Search Again
                        </button>
                    </div>

                    <div x-show="aiSearchError" x-cloak class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 break-words" x-text="aiSearchError"></div>
                    <div x-show="aiSearchResults.length === 0 && !aiSearching && aiHasSearched && !aiSearchError" x-cloak class="text-sm text-gray-400">No articles found for this topic. Try a different query.</div>
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
                <button @click="completeStep(3); openStep(4)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to Fetch &rarr;</button>
            </div>
            </div>{{-- end !isGenerateMode --}}
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 4: Fetch Articles from Source
         ══════════════════════════════════════════════════════════════ --}}
    <template x-if="currentArticleType !== 'press-release'">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 4, 'opacity-50': !isStepAccessible(4) }">
        <button @click="toggleStep(4)" :disabled="!isStepAccessible(4)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(4) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(4)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(4)"><span>4</span></template>
                </span>
                <span class="font-semibold text-gray-800" x-text="stepLabels[3] || 'Fetch Articles from Source'"></span>
                <span x-show="currentArticleType !== 'press-release' && checkResults.length > 0" x-cloak class="text-sm" :class="checkPassCount === sources.length ? 'text-green-600' : 'text-yellow-600'"
                      x-text="checkPassCount + '/' + sources.length + ' extracted'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(4) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(4)" x-cloak x-collapse class="px-4 pb-4">
            @include('app-publish::publishing.pipeline.partials.press-release-validate-step')
            <div x-show="currentArticleType !== 'press-release'" x-cloak>
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
                            <option value="claude">Claude AI</option>
                            <option value="gpt">GPT</option>
                            <option value="grok">Grok</option>
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
                    <button @click.stop="checkAllSources()" :disabled="checking" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="checking" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="checking ? 'Extracting...' : 'Get Articles'"></span>
                    </button>
                </div>
            </div>

            {{-- Fetch Configuration Summary --}}
            <div class="bg-gray-900 rounded-lg border border-gray-700 p-4 mb-4">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Fetch Configuration</div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Method</span>
                        <span class="text-white font-medium" x-text="extractMethod === 'auto' ? 'Auto (Readability + Regex)' : extractMethod === 'readability' ? 'Readability' : extractMethod === 'css' ? 'CSS Selector' : extractMethod === 'regex' ? 'Regex' : extractMethod === 'claude' ? 'Claude AI' : extractMethod === 'gpt' ? 'GPT' : extractMethod === 'grok' ? 'Grok' : extractMethod"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">User Agent</span>
                        <span class="text-white font-medium" x-text="checkUserAgent === 'chrome' ? 'Chrome Desktop' : checkUserAgent === 'firefox' ? 'Firefox Desktop' : checkUserAgent === 'safari' ? 'Safari macOS' : checkUserAgent === 'googlebot' ? 'Googlebot' : 'HWS Bot'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Retries</span>
                        <span class="text-white font-medium" x-text="extractRetries + (extractRetries == 1 ? ' attempt' : ' attempts')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Timeout</span>
                        <span class="text-white font-medium" x-text="extractTimeout + 's per request'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Min Words</span>
                        <span class="text-white font-medium" x-text="extractMinWords + ' words'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Auto-fallback</span>
                        <span class="font-medium" :class="extractAutoFallback ? 'text-green-400' : 'text-red-400'" x-text="extractAutoFallback ? 'On — Googlebot retry' : 'Off'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Redirects</span>
                        <span class="text-green-400 font-medium">Follow</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Retry Delay</span>
                        <span class="text-white font-medium">Escalating (1s, 2s, 3s)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Max Wait</span>
                        <span class="text-white font-medium" x-text="(() => { let t = parseInt(extractTimeout); let r = parseInt(extractRetries); let wait = t; for(let i=1;i<=r;i++) wait += t + i; return wait + 's worst case'; })()"></span>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-700">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">UA String</span>
                        <span class="text-gray-500 font-mono break-all text-right" x-text="checkUserAgent === 'chrome' ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0' : checkUserAgent === 'firefox' ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Firefox/134.0' : checkUserAgent === 'safari' ? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7_4) Safari/605.1.15' : checkUserAgent === 'googlebot' ? 'Mozilla/5.0 (compatible; Googlebot/2.1)' : 'Mozilla/5.0 (compatible; HWSPublishBot/1.0)'"></span>
                    </div>
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
                                <p x-show="!result.success" class="text-sm text-red-600 font-medium mt-1" x-text="'Extraction failed — ' + (result.message || 'unknown error')"></p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button x-show="!result.success" @click.stop="saveFailedSource(idx)" class="text-xs text-orange-600 hover:text-orange-800 px-2 py-1 bg-orange-50 rounded">Find Another</button>
                                <button x-show="!result.success" @click.stop="removeSource(idx)" class="text-xs text-red-600 hover:text-red-800 px-2 py-1 bg-red-50 rounded">Remove</button>
                                <svg x-show="result.success" class="w-5 h-5 text-gray-400 transition-transform" :class="expandedSources.includes(idx) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        {{-- Extracted article content — expand/collapse, default EXPANDED --}}
                        <div x-show="result.success && result.text" x-cloak class="border-t border-green-200">
                            <div class="bg-white rounded-b-lg shadow-sm">
                                <div class="flex items-center justify-between px-6 pt-4 pb-2">
                                    <button @click.stop="expandedSources.includes(idx) ? expandedSources = expandedSources.filter(i => i !== idx) : expandedSources.push(idx)" class="text-xs text-gray-400 hover:text-gray-600 inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 transition-transform" :class="expandedSources.includes(idx) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        <span x-text="expandedSources.includes(idx) ? 'Extracted content' : 'Show extracted content'"></span>
                                    </button>
                                </div>
                                {{-- Full content — NO scroll, NO iframe, NO max-height --}}
                                <div x-show="expandedSources.includes(idx)" class="px-6 pb-4" style="font-size:15px;line-height:1.8;color:#374151;">
                                    <div class="prose max-w-none break-words" x-html="(result.formatted_html || result.text || '').replace(/<img[^>]*>/gi, function(m) { return m.replace(/<img /i, '<img style=\'max-width:100%;height:auto;\' '); })"></div>
                                </div>
                                {{-- Action buttons with separator --}}
                                <div x-show="result.success" class="px-6 py-3 border-t border-gray-100 flex items-center gap-3">
                                    <button @click.stop="discardSource(idx)" class="text-sm font-medium px-4 py-2 rounded-lg inline-flex items-center gap-1.5 transition-colors" :class="discardedSources.includes(idx) ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span x-text="discardedSources.includes(idx) ? 'Discarded' : 'Discard'"></span>
                                    </button>
                                    <button @click.stop="flagAsBroken(idx)" class="text-sm font-medium px-4 py-2 rounded-lg inline-flex items-center gap-1.5 bg-orange-100 text-orange-700 hover:bg-orange-200 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        Flag as Broken
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-3" x-show="checkResults.length > 0 && checkPassCount > 0" x-cloak>
                <button @click="completeStep(4); openStep(5)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to AI & Spin &rarr;</button>
            </div>
            </div>
        </div>
    </div>
    </template>

    {{-- ══════════════════════════════════════════════════════════════
         Step 5: AI & Spin
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 5, 'opacity-50': !isStepAccessible(5) }">
        <button @click="toggleStep(5)" :disabled="!isStepAccessible(5)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(5) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(5)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(5)"><span>5</span></template>
                </span>
                <span class="font-semibold text-gray-800" x-text="stepLabels[4] || 'AI & Spin'"></span>
                <span x-show="selectedTemplate" x-cloak class="text-sm text-green-600" x-text="selectedTemplate?.name || ''"></span>
                <span x-show="spunContent" x-cloak class="text-sm text-green-600" x-text="spunWordCount + ' words'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(5) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(5)" x-cloak x-collapse class="px-4 pb-4">

            {{-- Article Preset moved to Step 2 --}}
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">AI Model</label>
                    <select x-model="aiModel" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <optgroup label="Claude">
                            @foreach(config('anthropic.models', []) as $model)
                                @if($model['type'] === 'api' || $model['type'] === 'both')
                                    <option value="{{ $model['id'] }}">Claude — {{ $model['name'] }}</option>
                                @endif
                            @endforeach
                        </optgroup>
                        @if(config('chatgpt.models'))
                        <optgroup label="GPT">
                            @foreach(config('chatgpt.models', []) as $model)
                                <option value="{{ $model['id'] }}">GPT — {{ $model['name'] }}</option>
                            @endforeach
                        </optgroup>
                        @endif
                        @if(!empty($grokModels))
                        <optgroup label="Grok">
                            @foreach($grokModels as $model)
                                <option value="{{ $model['id'] }}">Grok — {{ $model['name'] }}</option>
                            @endforeach
                        </optgroup>
                        @endif
                    </select>
                </div>
                <button @click="spinArticle()" :disabled="spinning" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2">
                    <svg x-show="spinning" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="spinning ? (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Polishing...' : 'Spinning...') : (spunContent ? (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Re-polish' : 'Re-spin') : (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Polish Content' : 'Spin Article'))"></span>
                </button>
                <p x-show="spunContent && !spinning" x-cloak class="text-sm text-green-600 mt-1 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Article spun successfully — <span x-text="spunWordCount + ' words'"></span>
                </p>
            </div>

            {{-- Custom prompt input — live-updates resolved prompt --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Custom Instructions <span class="text-gray-400">(takes precedence over template/preset)</span></label>
                <textarea x-model.debounce.300ms="customPrompt" @input.debounce.300ms="refreshPromptPreview()" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Write in first person, focus on the financial impact, include expert quotes..."></textarea>
            </div>

            {{-- Web research toggle --}}
            <div class="mb-4">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="spinWebResearch" @change="refreshPromptPreview()" class="rounded border-gray-300 text-purple-600">
                    <span class="text-sm text-gray-700">Search online for additional supporting points</span>
                </label>
                <p x-show="spinWebResearch" x-cloak class="text-xs text-gray-400 mt-1 ml-6">The AI will search the web for real-time data, statistics, and expert opinions to strengthen the article.</p>
            </div>

            {{-- Resolved Prompt — always visible, live-updating --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-500 font-medium">Resolved Prompt</span>
                    <span x-show="promptLoading" x-cloak class="text-xs text-blue-400 inline-flex items-center gap-1">
                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Updating...
                    </span>
                </div>
                <div class="bg-gray-900 text-gray-300 rounded-xl p-4 text-xs font-mono overflow-y-auto break-words whitespace-pre-wrap" style="max-height:400px;">
                    <pre x-show="resolvedPrompt" class="text-green-300 whitespace-pre-wrap break-words" x-text="resolvedPrompt"></pre>
                    <p x-show="!resolvedPrompt && promptLoading" x-cloak class="text-blue-400">Loading prompt...</p>
                    <p x-show="!resolvedPrompt && !promptLoading" x-cloak class="text-gray-500">Prompt will load when a template or preset is selected.</p>
                </div>
            </div>

            {{-- ═══ Prompt Injections Log ═══ --}}
            <div x-show="promptLog.length > 0" x-cloak class="mb-4">
                <button @click="promptLogOpen = !promptLogOpen" class="w-full flex items-center justify-between bg-gray-800 hover:bg-gray-750 border border-gray-700 rounded-lg px-4 py-2.5 text-left transition-colors">
                    <span class="text-xs font-semibold text-gray-300 uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        Prompt Injections
                        <span class="text-gray-500 font-normal normal-case" x-text="'(' + promptLog.length + ' resolved)'"></span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="promptLogOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="promptLogOpen" x-cloak x-transition class="bg-gray-900 border border-gray-700 border-t-0 rounded-b-lg overflow-hidden">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-800">
                                <th class="text-left px-3 py-2 text-gray-500 font-medium w-40">Shortcode</th>
                                <th class="text-left px-3 py-2 text-gray-500 font-medium">Source</th>
                                <th class="text-left px-3 py-2 text-gray-500 font-medium">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(entry, idx) in promptLog" :key="idx">
                                <tr class="border-b border-gray-800/50 last:border-b-0 hover:bg-gray-800/30">
                                    <td class="px-3 py-1.5 align-top"><code class="text-blue-400 font-mono" x-text="entry.shortcode"></code></td>
                                    <td class="px-3 py-1.5 align-top text-gray-400" x-text="entry.source"></td>
                                    <td class="px-3 py-1.5 align-top">
                                        <span x-show="entry.value && entry.value !== ''" class="text-green-300 font-mono break-words" x-text="entry.value.length > 80 ? entry.value.substring(0, 80) + '...' : entry.value"></span>
                                        <span x-show="!entry.value || entry.value === ''" class="text-gray-600 italic">empty</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ═══ AI Detection Scan ═══ --}}
            <div class="mb-4 bg-gray-900 rounded-xl border border-gray-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h5 class="text-sm font-semibold text-white uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        AI Detection Scan
                    </h5>
                    <div class="flex items-center gap-3">
                        {{-- Enable/disable toggle (persisted) --}}
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400" x-text="aiDetectionEnabled ? 'Enabled' : 'Disabled'"></span>
                            <button @click="toggleAiDetection()" type="button"
                                class="relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="aiDetectionEnabled ? 'bg-purple-500' : 'bg-gray-600'"
                                role="switch" :aria-checked="aiDetectionEnabled">
                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="aiDetectionEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>
                        <span x-show="aiDetectionEnabled && aiDetectionThreshold !== null" x-cloak class="text-xs text-gray-400">Max AI: <span class="text-white" x-text="aiDetectionThreshold + '%'"></span></span>
                        <button x-show="aiDetectionEnabled && !aiDetecting && spunContent" @click="runAiDetection()" class="text-xs text-purple-400 hover:text-purple-300 inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Re-scan
                        </button>
                    </div>
                </div>

                {{-- Disabled state --}}
                <div x-show="!aiDetectionEnabled" x-cloak class="text-sm text-gray-500 text-center py-3">
                    AI detection is disabled. Toggle on to scan articles after spinning.
                </div>

                {{-- Enabled content --}}
                <div x-show="aiDetectionEnabled" x-cloak>
                    {{-- Detector rows --}}
                    <div class="space-y-2">
                        <template x-for="(det, key) in aiDetectionResults" :key="key">
                            <div class="bg-gray-800 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <template x-if="det.loading">
                                            <svg class="w-5 h-5 text-purple-400 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </template>
                                        <template x-if="!det.loading && det.success && det.passes">
                                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </template>
                                        <template x-if="!det.loading && det.success && !det.passes">
                                            <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </template>
                                        <template x-if="!det.loading && !det.success">
                                            <svg class="w-5 h-5 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        </template>
                                        <span class="text-sm font-medium text-white" x-text="det.name"></span>
                                        <span x-show="det.debug_mode" x-cloak class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-900 text-yellow-300">DEBUG</span>
                                        <a :href="'/' + key + '/settings'" target="_blank" class="text-gray-600 hover:text-gray-400" title="Settings">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        </a>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <template x-if="!det.loading && det.ai_score !== null && det.ai_score !== undefined">
                                            <span class="text-lg font-bold" :class="det.passes ? 'text-green-400' : 'text-red-400'" x-text="det.ai_score + '% AI'"></span>
                                        </template>
                                        <template x-if="!det.loading && (det.ai_score === null || det.ai_score === undefined) && !det.success">
                                            <span class="text-xs text-yellow-400">Error</span>
                                        </template>
                                        <template x-if="det.loading">
                                            <span class="text-xs text-gray-500">Scanning...</span>
                                        </template>
                                        <template x-if="!det.loading && det.success">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium" :class="det.passes ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'" x-text="det.passes ? 'PASS' : 'FAIL'"></span>
                                        </template>
                                        <button x-show="!det.loading && det.raw" @click="det.showRaw = !det.showRaw" class="text-xs text-gray-500 hover:text-gray-300" title="View raw response">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <p x-show="!det.loading && !det.success && det.message" x-cloak class="text-xs text-yellow-400 mt-1 break-words" x-text="det.message"></p>
                                {{-- Flagged sentences with checkboxes --}}
                                <div x-show="!det.loading && det.sentences && det.sentences.length > 0" x-cloak class="mt-2">
                                    <p class="text-xs text-red-400 mb-1">Flagged sentences:</p>
                                    <div class="space-y-1">
                                        <template x-for="(s, si) in (det.sentences || [])" :key="key + '_' + si">
                                            <label class="flex items-start gap-2 text-xs text-gray-400 bg-gray-900 rounded px-2 py-1.5 cursor-pointer hover:bg-gray-800">
                                                <input type="checkbox" :checked="selectedFlaggedSentences.includes(key + '_' + si)" @change="toggleFlaggedSentence(key + '_' + si, s)" class="mt-0.5 rounded border-gray-600 text-red-500 bg-gray-800">
                                                <span class="break-words" x-text="s"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                                <div x-show="det.showRaw" x-cloak class="mt-2 bg-gray-900 rounded-lg p-3 text-xs text-gray-400 font-mono break-words whitespace-pre-wrap" x-text="JSON.stringify(det.raw, null, 2)"></div>
                            </div>
                        </template>
                    </div>

                    {{-- Overall verdict --}}
                    <div x-show="!aiDetecting && Object.keys(aiDetectionResults).length > 0" x-cloak class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <template x-if="aiDetectionAllPass">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="text-sm font-semibold text-green-400">All detectors passed</span>
                                </div>
                            </template>
                            <template x-if="!aiDetectionAllPass">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    <span class="text-sm font-semibold text-red-400">Article flagged -- above AI threshold</span>
                                </div>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <button x-show="!aiDetectionAllPass" @click="processDetectionRespin()" :disabled="spinning" class="bg-red-600 text-white px-4 py-2 rounded-lg text-xs hover:bg-red-700 disabled:opacity-50 inline-flex items-center gap-2">
                                <svg x-show="spinning" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Process Changes & Respin
                            </button>
                            <button x-show="!aiDetectionAllPass" @click="ignoreDetection()" class="text-gray-400 hover:text-gray-200 px-3 py-2 text-xs">Ignore & Continue</button>
                        </div>
                    </div>

                    {{-- No detectors enabled --}}
                    <div x-show="!aiDetecting && Object.keys(aiDetectionResults).length === 0 && aiDetectionRan" x-cloak class="text-sm text-gray-500 text-center py-2">
                        No AI detectors are enabled. Configure in <a href="{{ route('publish.settings.master') }}" class="text-purple-400 hover:text-purple-300">Settings</a>.
                    </div>

                    {{-- Loading state --}}
                    <div x-show="aiDetecting" x-cloak class="text-xs text-purple-400 text-center py-2 inline-flex items-center justify-center gap-2 w-full">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Running AI detection scan...
                    </div>

                    {{-- Detector status — always visible before scan runs --}}
                    <div x-show="!aiDetectionRan && !aiDetecting" x-cloak class="space-y-2 py-2">
                        <p class="text-xs text-gray-500 mb-2">Detection runs automatically after spin completes.</p>
                        @foreach($aiDetectors as $key => $det)
                        <div class="flex items-center gap-3 bg-gray-800 rounded-lg px-3 py-2">
                            @if($det['enabled'] && $det['has_key'])
                                <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif(!$det['installed'])
                                <svg class="w-4 h-4 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            @else
                                <svg class="w-4 h-4 text-yellow-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            @endif
                            <span class="text-sm text-white font-medium">{{ $det['name'] }}</span>
                            @if(!$det['installed'])
                                <span class="text-[10px] text-gray-500">Not installed</span>
                            @elseif(!$det['enabled'])
                                <span class="text-[10px] text-gray-500">Disabled</span>
                            @elseif(!$det['has_key'])
                                <span class="text-[10px] text-yellow-400">No API key</span>
                            @else
                                <span class="text-[10px] text-green-400">Ready</span>
                            @endif
                            @if($det['debug_mode'])
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-900 text-yellow-300" title="Debug mode uses mock/test responses without consuming API credits">DEBUG</span>
                            @endif
                        </div>
                        @endforeach
                        @if(collect($aiDetectors)->where('enabled', true)->where('has_key', true)->isEmpty())
                            <p class="text-[11px] text-gray-500 mt-1">No detectors are ready. Configure API keys in <a href="{{ route('publish.settings.master') }}" class="text-purple-400 hover:text-purple-300">Settings</a>.</p>
                        @endif
                        <p class="text-[10px] text-gray-600 mt-1">Debug mode: uses mock/test responses without consuming API credits. Scores are simulated.</p>
                    </div>
                </div>
            </div>


            {{-- Spin error --}}
            <div x-show="spinError" x-cloak class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-red-700" x-text="spinError"></p>
            </div>

            {{-- AI Call Report --}}
            <div x-show="lastAiCall" x-cloak class="mb-4 bg-gray-900 rounded-xl border border-gray-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">AI Call Report</h5>
                    <span class="text-xs text-gray-500" x-text="lastAiCall?.timestamp_utc || ''"></span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="text-gray-500">Provider</span>
                        <p class="font-medium text-white" x-text="lastAiCall?.provider || '—'"></p>
                    </div>
                    <div>
                        <span class="text-gray-500">Model</span>
                        <p class="font-medium text-white" x-text="lastAiCall?.model || '—'"></p>
                    </div>
                    <div>
                        <span class="text-gray-500">Cost</span>
                        <p class="font-medium text-green-400" x-text="'$' + (lastAiCall?.cost || 0).toFixed(4)"></p>
                    </div>
                    <div>
                        <span class="text-gray-500">Tokens</span>
                        <p class="font-medium text-white" x-text="(lastAiCall?.usage?.input_tokens || 0) + ' in / ' + (lastAiCall?.usage?.output_tokens || 0) + ' out'"></p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('app-publish::publishing.pipeline.partials.create-article-step')

@include('app-publish::publishing.pipeline.partials.review-publish-step')

@include('app-publish::publishing.pipeline.partials.overlays')

@include('app-publish::partials.site-connection-mixin')
@include('app-publish::partials.preset-fields-mixin')
@include('app-publish::publishing.pipeline.partials.press-release-workflow-script')
<script>
function publishPipeline() {
    return {
        ...pressReleaseWorkflowMixin({
            workflowDefinitions: @json($workflowDefinitions ?? []),
            pipelinePayload: @json($pipelinePayload ?? []),
            pressReleaseDefaultState: @json($pressReleaseDefaultState ?? []),
        }),
        // Step tracking
        currentStep: 1,
        openSteps: [1],
        completedSteps: [],
        get isGenerateMode() {
            const genTypes = ['press-release', 'listicle', 'expert-article', 'pr-full-feature'];
            // Check overrides first (user changed article_type in the form), then template default
            const articleType = this.template_overrides?.article_type ?? this.selectedTemplate?.article_type;
            return genTypes.includes(articleType);
        },
        get currentArticleType() {
            return this.template_overrides?.article_type ?? this.selectedTemplate?.article_type ?? null;
        },
        get stepLabels() {
            return this.currentStepLabels();
        },

        // Step 1 — User
        selectedUser: null,

        // Step 2 — Preset + Website
        presets: [],
        presetsLoading: false,
        selectedPresetId: '',
        selectedPreset: null,
        editingPreset: false,

        // Step 3 — PR Subject Profiles
        prProfileSearch: '',
        prProfileResults: [],
        prProfileSearching: false,
        prProfileDropdownOpen: false,
        prSubmitCard: 'content',
        selectedPrProfiles: [],
        prSubjectData: {},

        // Step 3 — Website
        sites: @json($sites ?? []),
        prSourceSites: @json($prSourceSites ?? []),
        draftState: @json($draftState ?? []),
        selectedSiteId: '',
        selectedSite: null,

        // Step 4 — Sources
        sources: [],
        sourceTab: 'ai',
        pasteText: '',
        newsSearch: '',
        newsSearching: false,
        newsResults: [],
        newsHasSearched: false,
        newsMode: 'keyword',
        newsCategory: '',
        newsTrendingSelected: false,
        newsCountry: 'us',
        bookmarks: [],
        bookmarksLoading: false,
        uploadingSourceDoc: false,
        uploadedSourceDoc: null,
        uploadedSourceText: '',
        aiSearchTopic: '',
        aiSearchModel: 'claude-haiku-4-5-20251001',
        aiSearchCount: 4,
        aiSearching: false,
        aiSearchResults: [],
        aiSearchError: '',
        aiHasSearched: false,
        aiLog: [],
        aiSearchCost: null,

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

        // Step 5 — AI Template + Config
        templates: [],
        templatesLoading: false,
        selectedTemplateId: '',
        selectedTemplate: null,
        editingTemplate: false,

        // Step 7 — Model
        aiModel: 'claude-opus-4-6',
        customPrompt: '',

        // Step 7 — Spin
        spinning: false,
        spinWebResearch: false,
        spunContent: '',
        spunWordCount: 0,
        spinChangeRequest: '',
        showChangeInput: false,
        smartEditTemplates: [],
        appliedSmartEdits: [],
        suggestedTitles: [],
        suggestedCategories: [],
        suggestedTags: [],
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
        showPhotoOverlay: false,
        showUploadPortal: false,
        insertingPhoto: null,
        photoCaption: '',
        overlayPhotoAlt: '',
        overlayPhotoCaption: '',
        overlayPhotoFilename: '',
        overlayMetaLoading: false,
        overlayMetaGenerated: false,
        _photoSuggestionIdx: null,
        expandedSuggestions: [],
        autoFetchingPhotos: false,
        viewingPhotoIdx: null,
        lastAiCall: null,
        tokenUsage: null,
        spinError: '',

        // Step 8 — Publish (combined)
        syndicationCategories: [],
        selectedSyndicationCats: [],
        loadingSyndicationCats: false,
        _previousSiteId: null,
        articleTitle: '',
        editorContent: '',
        preparing: false,
        prepareChecklist: [],
        prepareLog: [],
        prepareComplete: false,
        prepareIntegrityIssues: [],
        preparedHtml: '',
        preparedCategoryIds: [],
        preparedTagIds: [],
        preparedFeaturedMediaId: null,
        preparedFeaturedWpUrl: null,

        // Step 10 — Publish
        publishAction: 'draft_local',
        publishAuthor: '',
        publishAuthorSource: '',
        authorsLoading: false,
        scheduleDate: '',
        publishing: false,
        publishResult: null,
        publishError: '',

        // Draft
        draftId: {{ $draftId }},
        uploadedImages: {},
        featuredImageSearch: '',
        featuredPhoto: null,
        featuredResults: [],
        featuredSearching: false,
        featuredUrlImport: '',
        innerPhotoUrlImport: '',
        featuredAlt: '',
        featuredCaption: '',
        featuredFilename: '',
        featuredRefreshingMeta: false,
        resolvedPrompt: '',
        promptLog: [],
        promptLogOpen: false,
        promptLoading: false,
        articleDescription: '',
        spinLog: [],

        // AI Detection
        aiDetecting: false,
        aiDetectionResults: {},
        aiDetectionThreshold: 10,
        aiDetectionAllPass: false,
        aiDetectionRan: false,
        aiDetectionEnabled: localStorage.getItem('aiDetectionEnabled') !== 'false',
        selectedFlaggedSentences: [],
        selectedFlaggedTexts: {},

        ...siteConnectionMixin(),
        ...presetFieldsMixin('template'),
        ...presetFieldsMixin('preset'),
        template_schema: @json($templateSchema ?? []),
        preset_schema: @json($presetSchema ?? []),
        ...presetFieldsMethods,
        savingDraft: false,
        _draftSaveTimer: null,
        filenamePattern: @json($filenamePattern ?? 'hexa_{draft_id}_{seo_name}'),

        // Notification
        notification: { show: false, type: 'success', message: '' },

        // Flag to suppress step auto-navigation during state restore
        _restoring: false,

        // CSRF token
        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        get pipelineStateKey() {
            return 'publishPipelineState:' + String(this.draftId || 'new');
        },

        // ── State Persistence ────────────────────────────
        _stateVersion: 3,
        shouldWarmPromptPreview() {
            return this.completedSteps.includes(2) || this.currentStep >= 5 || this.promptLogOpen || !!this.spunContent || !!this.editorContent;
        },

        init() {
            const draftState = this.draftState || {};
            const serverState = this.pipelinePayload && Object.keys(this.pipelinePayload).length > 0
                ? { ...this.pipelinePayload, draftId: this.draftId, _v: this.pipelinePayload._v || this._stateVersion }
                : null;
            const saved = localStorage.getItem(this.pipelineStateKey);
            const legacySaved = !saved && !draftState.body ? localStorage.getItem('publishPipelineState') : null;
            const savedState = saved || legacySaved;
            let state = null;
            let shouldPersistRestoredDraftState = false;
            let restoredFromLocalState = false;

            this._restoring = true;

            if (savedState) {
                try {
                    state = JSON.parse(savedState);
                    if (
                        !state._v ||
                        state._v < this._stateVersion ||
                        (state.draftId && String(state.draftId) !== String(this.draftId))
                    ) {
                        localStorage.removeItem(this.pipelineStateKey);
                        state = null;
                    }
                } catch (e) {
                    state = null;
                }
                restoredFromLocalState = !!state;
            }

            if (!state && serverState) {
                state = serverState;
            }

            if (state) {
                // ── Declarative restore — all persistentFields auto-restore ──
                for (const key of this.persistentFields) {
                    if (state[key] !== undefined && state[key] !== null) {
                        this[key] = state[key];
                    }
                }

                // ── Special handling for fields that need post-processing ──
                // Arrays that may serialize as objects in Alpine proxy
                if (state.openSteps) this.openSteps = Array.isArray(state.openSteps) ? state.openSteps : Object.values(state.openSteps);
                if (state.completedSteps) this.completedSteps = Array.isArray(state.completedSteps) ? state.completedSteps : Object.values(state.completedSteps);
                this.pressRelease = this.restorePressReleaseStateFromLegacy(state);

                // Site connection (nested object)
                if (state.selectedSiteId) {
                    this.selectedSiteId = String(state.selectedSiteId);
                    this.siteConn.status = state.siteConnStatus ?? null;
                    this.siteConn.message = state.siteConnMessage || '';
                    if (state.siteConnLog) this.siteConn.log = state.siteConnLog;
                    if (state.siteConnAuthors) this.siteConn.authors = state.siteConnAuthors;
                    this.$nextTick(() => {
                        this.selectedSiteId = String(state.selectedSiteId);
                        this.selectedSite = this.sites.find(s => s.id == state.selectedSiteId) || state.selectedSite || null;
                    });
                }

                // Editor content needs TinyMCE sync
                if (state.spunContent || state.editorContent) {
                    const restoredEditorHtml = state.editorContent || state.spunContent;
                    this.spunContent = restoredEditorHtml;
                    this.spunWordCount = state.spunWordCount || this.countWordsFromHtml(restoredEditorHtml);
                    this.setSpinEditor(restoredEditorHtml);
                    this.extractArticleLinks(restoredEditorHtml);
                }

                // Title from suggested titles
                if (state.selectedTitleIdx !== undefined && this.suggestedTitles[this.selectedTitleIdx]) {
                    this.articleTitle = this.suggestedTitles[this.selectedTitleIdx];
                }

                // Check results count
                if (state.checkResults) {
                    this.checkPassCount = state.checkResults.filter(r => r.success).length;
                }

                // Photo suggestions — reset loading flags + rebuild filenames
                if (state.photoSuggestions) {
                    this.photoSuggestions = state.photoSuggestions.map((ps, idx) => ({ ...ps, refreshingMeta: false, searching: false, suggestedFilename: this.buildFilename(ps.search_term, idx + 1) }));
                }

                // Featured photo needs results array
                if (state.featuredPhoto) {
                    this.featuredResults = [state.featuredPhoto];
                }

                // AI detection — reset loading flags
                if (state.aiDetectionResults) {
                    Object.keys(this.aiDetectionResults).forEach(k => { this.aiDetectionResults[k].loading = false; });
                }

                shouldPersistRestoredDraftState = !draftState.selectedSiteId && (
                    !!state.selectedSiteId ||
                    !!state.selectedUser ||
                    !!state.selectedPresetId ||
                    !!state.selectedTemplateId ||
                    !!state.publishAuthor
                );
            }

            if (serverState && restoredFromLocalState) {
                if (!this.pressRelease || Object.keys(this.pressRelease).length === 0) {
                    this.pressRelease = this.restorePressReleaseStateFromLegacy(serverState);
                }
                if (!Array.isArray(this.pressRelease.photo_files) || this.pressRelease.photo_files.length === 0) {
                    this.pressRelease.photo_files = this.normalizePressReleaseState(serverState.pressRelease || {}).photo_files || [];
                }
                if (!Array.isArray(this.pressRelease.document_files) || this.pressRelease.document_files.length === 0) {
                    this.pressRelease.document_files = this.normalizePressReleaseState(serverState.pressRelease || {}).document_files || [];
                }
            }

            // Database-backed draft state is authoritative for site/user/template/preset restore.
            if (draftState.selectedUser) this.selectedUser = draftState.selectedUser;
            if (draftState.selectedSiteId) {
                this.selectedSiteId = String(draftState.selectedSiteId);
                this.selectedSite = this.sites.find(s => s.id == draftState.selectedSiteId) || draftState.selectedSite || null;
                this.siteConn.status = draftState.siteConnStatus ?? this.siteConn.status;
                if (draftState.siteConnStatus === true) {
                    this.siteConn.message = 'Loaded from saved draft.';
                }
            }
            if (draftState.publishAuthor) this.publishAuthor = draftState.publishAuthor;
            if (!this.articleTitle && draftState.articleTitle) this.articleTitle = draftState.articleTitle;
            if (!this.aiModel && draftState.aiModel) this.aiModel = draftState.aiModel;
            if (!this.spunContent && draftState.body) {
                this.spunContent = draftState.body;
                this.editorContent = draftState.body;
                this.spunWordCount = draftState.wordCount || this.countWordsFromHtml(draftState.body);
                this.extractArticleLinks(draftState.body);
                this.$nextTick(() => this.setSpinEditor(draftState.body));
            }
            if ((!Array.isArray(this.photoSuggestions) || this.photoSuggestions.length === 0) && Array.isArray(draftState.photoSuggestions)) {
                this.photoSuggestions = draftState.photoSuggestions.map((ps, idx) => ({ ...ps, refreshingMeta: false, searching: false, suggestedFilename: this.buildFilename(ps.search_term, idx + 1) }));
            }
            if (!this.featuredImageSearch && draftState.featuredImageSearch) {
                this.featuredImageSearch = draftState.featuredImageSearch;
            }
            if ((this.spunContent || draftState.body) && !state?.currentStep) {
                this.currentStep = 6;
                this.openSteps = [6];
                this.completedSteps = Array.from(new Set([...(this.completedSteps || []), 1, 2, 3, 4, 5]));
            }

            // Auto-complete steps based on restored data
            if (this.selectedUser && !this.completedSteps.includes(1)) this.completedSteps.push(1);
            if (this.selectedSiteId && !this.completedSteps.includes(2)) this.completedSteps.push(2);
            if ((this.currentArticleType === 'press-release' ? this.hasPressReleaseSubmittedContent() : this.sources.length > 0) && !this.completedSteps.includes(3)) this.completedSteps.push(3);
            if ((this.currentArticleType === 'press-release' ? this.hasPressReleaseValidationData() : this.checkResults.length > 0) && !this.completedSteps.includes(4)) this.completedSteps.push(4);
            if (this.spunContent && !this.completedSteps.includes(5)) this.completedSteps.push(5);
            if (this.spunContent && !this.completedSteps.includes(6)) this.completedSteps.push(6);

            const finishRestore = () => {
                this._restoring = false;
                if (shouldPersistRestoredDraftState) this.autoSaveDraft();
                // Auto-select PR source if press-release type and only one source
                this.autoSelectPrSource();
                if (this.shouldWarmPromptPreview()) {
                    this.refreshPromptPreview();
                }
                if (this.currentStep >= 6 && this.featuredImageSearch && !this.featuredPhoto) {
                    this.searchFeaturedImage();
                }
                // If there's already spun content, ensure Create Article is accessible
                if (this.spunContent || this.editorContent) {
                    if (!this.completedSteps.includes(5)) this.completedSteps.push(5);
                    if (this.currentStep <= 5) {
                        this.currentStep = 6;
                        this.openSteps = [6];
                    }
                }
                // Restore step from URL query string (overrides saved state)
                const urlStep = new URLSearchParams(window.location.search).get('step');
                if (urlStep) {
                    const s = parseInt(urlStep);
                    if (s >= 1 && s <= 7 && this.isStepAccessible(s)) {
                        this.currentStep = s;
                        this.openSteps = [s];
                    }
                }
            };

            if (this.selectedUser) {
                this.presetsLoading = true;
                this.templatesLoading = true;
                window.dispatchEvent(new CustomEvent('hexa-form-loading', { detail: { component_id: 'article-preset-form', loading: true } }));
                const restoredPresetId = draftState.selectedPresetId || state?.selectedPresetId || '';
                const restoredPreset = state?.selectedPreset || null;
                const restoredTemplateId = draftState.selectedTemplateId || state?.selectedTemplateId || '';
                const restoredTemplate = state?.selectedTemplate || null;

                Promise.all([this.loadUserPresets(), this.loadUserTemplates()]).then(() => {
                    // Restore saved preset or auto-select default
                    if (restoredPresetId) {
                        this.selectedPresetId = String(restoredPresetId);
                        this.selectedPreset = this.presets.find(p => p.id == restoredPresetId) || restoredPreset;
                    } else {
                        const defaultPreset = this.presets.find(p => p.is_default);
                        if (defaultPreset) {
                            this.selectedPresetId = String(defaultPreset.id);
                            this.selectedPreset = defaultPreset;
                        }
                    }
                    if (this.selectedPreset) {
                        this.loadPresetFields('preset', this.selectedPreset, null, state?.preset_overrides || null);
                    }
                    if (state?.preset_overrides) {
                        Object.assign(this.preset_overrides, state.preset_overrides);
                        if (state.preset_dirty) Object.assign(this.preset_dirty, state.preset_dirty);
                    }

                    // Restore saved template or auto-select default
                    if (restoredTemplateId) {
                        this.selectedTemplateId = String(restoredTemplateId);
                        this.selectedTemplate = this.templates.find(t => t.id == restoredTemplateId) || restoredTemplate;
                    } else {
                        const defaultTemplate = this.templates.find(t => t.is_default);
                        if (defaultTemplate) {
                            this.selectedTemplateId = String(defaultTemplate.id);
                            this.selectedTemplate = defaultTemplate;
                        }
                    }
                    if (this.selectedTemplate) {
                        // Pass saved overrides directly so loadPresetFields sends merged values to the form
                        this.loadPresetFields('template', this.selectedTemplate, null, state?.template_overrides || null);
                    }
                    // Re-apply saved overrides to the pipeline override layer
                    if (state?.template_overrides) {
                        Object.assign(this.template_overrides, state.template_overrides);
                        if (state.template_dirty) Object.assign(this.template_dirty, state.template_dirty);
                    }
                    // Sync article_type to standalone dropdown
                    if (this.selectedTemplate?.article_type && !this.template_overrides?.article_type) {
                        this.template_overrides.article_type = this.selectedTemplate.article_type;
                    }

                    // Auto-select site from default preset if no site saved
                    if (this.selectedPreset?.default_site_id && !this.selectedSiteId) {
                        this.selectedSiteId = String(this.selectedPreset.default_site_id);
                        this.selectedSite = this.sites.find(s => s.id == this.selectedPreset.default_site_id) || null;
                    }

                    finishRestore();
                });
            } else {
                finishRestore();
            }

            // ── Auto-watch ALL persistent fields for save ──
            const draftTriggers = ['articleTitle', 'editorContent', 'photoSuggestions', 'featuredImageSearch', 'featuredPhoto', 'featuredAlt', 'featuredCaption', 'featuredFilename'];
            for (const field of this.persistentFields) {
                this.$watch(field, () => {
                    this.savePipelineState();
                    if (!this._restoring && draftTriggers.includes(field)) {
                        this.queueAutoSaveDraft();
                    }
                });
            }
            // Site connection (nested, not in persistentFields)
            this.$watch('siteConn.status', () => this.savePipelineState());
            this.$watch('siteConn.authors', () => this.savePipelineState());
            // Prompt preview refresh
            this.$watch('selectedTemplateId', () => { if (!this._restoring && this.shouldWarmPromptPreview()) this.refreshPromptPreview(); });
            this.$watch('selectedPresetId', () => { if (!this._restoring && this.shouldWarmPromptPreview()) this.refreshPromptPreview(); });
            // customPrompt refresh handled by @input.debounce on the textarea
            this.$watch('currentStep', step => {
                if (this._restoring) return;
                if (step >= 5 && !this.promptLoading && !this.resolvedPrompt) {
                    this._queuePromptRefresh();
                }
                if (step >= 6 && this.featuredImageSearch && !this.featuredPhoto && !this.featuredSearching && this.featuredResults.length === 0) {
                    this.searchFeaturedImage();
                }
            });
            this.$watch('promptLogOpen', open => {
                if (!this._restoring && open && !this.promptLoading && !this.resolvedPrompt) {
                    this._queuePromptRefresh();
                }
            });
        },

        // ── Declarative persistent fields ─────────────────────
        // Add a field name here = auto-saved AND auto-restored.
        get persistentFields() {
            return [
                // Step tracking
                'currentStep', 'openSteps', 'completedSteps',
                // Step 1 — User
                'selectedUser',
                // Step 2 — Presets + Site
                'selectedPresetId', 'selectedPreset', 'selectedTemplateId', 'selectedTemplate',
                'selectedSiteId', 'selectedSite',
                'template_overrides', 'template_dirty', 'preset_overrides', 'preset_dirty',
                // Step 3 — Sources
                'sources', 'sourceTab', 'pasteText', 'newsMode', 'newsCategory', 'newsTrendingSelected',
                'newsSearch', 'newsResults', 'newsHasSearched',
                'aiSearchTopic', 'aiSearchResults', 'aiHasSearched',
                // Step 4 — Fetch
                'checkResults', 'approvedSources', 'discardedSources', 'expandedSources',
                // Step 5 — AI Spin
                'aiModel', 'spunContent', 'spunWordCount', 'suggestedTitles',
                'suggestedCategories', 'suggestedTags', 'selectedCategories', 'selectedTags',
                'selectedTitleIdx', 'tokenUsage', 'resolvedPrompt',
                // Step 6 — Create Article
                'articleTitle', 'articleDescription', 'editorContent',
                'photoSuggestions', 'featuredImageSearch', 'featuredPhoto',
                'featuredAlt', 'featuredCaption', 'featuredFilename',
                // Step 7 — Publish
                'publishAction', 'publishAuthor', 'publishAuthorSource',
                // AI Detection
                'aiDetectionResults', 'aiDetectionRan', 'aiDetectionAllPass',
                // Press Release workflow state
                'pressRelease',
                // PR Full Feature — profile subjects
                'selectedPrProfiles',
            ];
        },

        savePipelineState() {
            const state = this.buildPipelineStateSnapshot();
            localStorage.setItem(this.pipelineStateKey, JSON.stringify(state));
            localStorage.removeItem('publishPipelineState');
            if (!this._restoring) {
                this.queueServerPipelineStateSave();
            }
        },

        clearPipeline() {
            localStorage.removeItem(this.pipelineStateKey);
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
            this.photoSuggestions = [];
            this.featuredImageSearch = '';
            this.featuredPhoto = null;
            this.pressRelease = this.normalizePressReleaseState({});
            this.selectedPrProfiles = [];
        },

        // ── Navigation ────────────────────────────────────
        isStepAccessible(step) {
            if (step === 1) return true;
            // Step 4 (Fetch) requires at least one source selected
            if (step === 4 && this.sources.length === 0 && !this.isGenerateMode) return false;
            return this.completedSteps.includes(step - 1) || this.completedSteps.includes(step);
        },

        _syncStepToUrl() {
            const url = new URL(window.location);
            url.searchParams.set('step', this.currentStep);
            history.replaceState(null, '', url.toString());
        },

        goToStep(step) {
            if (this.isStepAccessible(step)) {
                this.currentStep = step;
                if (!this.openSteps.includes(step)) {
                    this.openSteps = [step];
                }
                this._syncStepToUrl();
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
            this._syncStepToUrl();
        },

        openStep(step) {
            this.currentStep = step;
            this.openSteps = [step];
            if (!this._restoring) this._syncStepToUrl();
        },

        completeStep(step) {
            if (!this.completedSteps.includes(step)) {
                this.completedSteps.push(step);
            }
        },

        // ── Step 1: User Selection ───────────────────────────
        async selectUser(user) {
            this.selectedUser = user;
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

            if (!this._restoring) this.autoSaveDraft();
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
                const resp = await fetch(`{{ route('publish.templates.index') }}?user_id=${this.selectedUser.id}&format=json`, {
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
                    if (this.selectedPreset.default_site_id && (!this._restoring || !this.selectedSiteId)) {
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
                    this.loadPresetFields('preset', this.selectedPreset);
                }
            } else {
                this.selectedPreset = null;
                this.loadPresetFields('preset', null);
            }

            if (!this._restoring) this.autoSaveDraft();
        },

        // ── Step 3: Website ───────────────────────────────
        selectSite() {
            if (this.selectedSiteId) {
                this.selectedSite = this.sites.find(s => s.id == this.selectedSiteId) || this.prSourceSites.find(s => s.id == this.selectedSiteId) || null;
                if (this.selectedSite) {
                    // Use cached connection status from DB instead of live-testing
                    this.siteConn.log = [];
                    this.siteConn.testing = false;
                    // Always reset author to this site's default (or clear it)
                    this.publishAuthor = this.selectedSite.default_author || '';
                    this.publishAuthorSource = this.selectedSite.default_author ? 'profile' : '';
                    if (this.selectedSite.status === 'connected') {
                        this.siteConn.status = true;
                        this.siteConn.message = 'Connected';
                        this.completeStep(2);
                    } else {
                        this.siteConn.status = false;
                        this.siteConn.message = this.selectedSite.status === 'error' ? 'Connection error' : 'Not connected';
                    }
                    if (!this._restoring) this.autoSaveDraft();
                }
            } else {
                this.selectedSite = null;
                this.publishAuthor = '';
                this.siteConn.authors = [];
                this.siteConn.log = [];
                this.siteConn.status = null;
                this.siteConn.message = '';
            }
        },

        refreshSiteConnection() {
            if (!this.selectedSiteId) return;
            this.testSiteConnection(this.selectedSiteId, this.csrfToken, {
                onSuccess: (d) => {
                    if (d.default_author) { this.publishAuthor = d.default_author; this.publishAuthorSource = 'profile'; }
                    this.completeStep(2);
                    this.autoSaveDraft();
                },
            });
        },

        autoSelectPrSource() {
            if (this.template_overrides?.article_type === 'press-release') {
                if (this.prSourceSites.length === 0) return;
                // Save current non-PR site before switching
                const currentInPr = this.prSourceSites.find(s => String(s.id) === this.selectedSiteId);
                if (!currentInPr) {
                    if (this.selectedSiteId) this._previousSiteId = this.selectedSiteId;
                    this.selectedSiteId = String(this.prSourceSites[0].id);
                    this.selectSite();
                }
            } else {
                // Switching away from press-release — restore previous site if it was swapped
                if (this._previousSiteId) {
                    const prevSite = this.sites.find(s => String(s.id) === this._previousSiteId);
                    if (prevSite) {
                        this.selectedSiteId = this._previousSiteId;
                        this.selectSite();
                    }
                    this._previousSiteId = null;
                }
            }
        },

        // ── Step 3: Sources ───────────────────────────────
        async uploadSourceDocument(files) {
            if (!files || !files.length) return;
            this.uploadingSourceDoc = true;
            const formData = new FormData();
            formData.append('file', files[0]);
            formData.append('draft_id', this.draftId);
            try {
                const resp = await fetch('{{ route("publish.pipeline.upload-source-doc") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: formData,
                });
                const data = await resp.json();
                if (data.success) {
                    this.uploadedSourceDoc = { name: files[0].name, word_count: data.word_count };
                    this.uploadedSourceText = data.text;
                    this.showNotification('success', 'Document uploaded — ' + data.word_count + ' words extracted');
                } else {
                    this.showNotification('error', data.message || 'Upload failed');
                }
            } catch (e) {
                this.showNotification('error', 'Upload error: ' + e.message);
            }
            this.uploadingSourceDoc = false;
        },

        useUploadedContent() {
            const text = this.uploadedSourceText.trim();
            if (!text) return;
            // Add as a virtual source
            this.sources.push({ url: 'upload://' + (this.uploadedSourceDoc?.name || 'pasted-content'), title: this.uploadedSourceDoc?.name || 'Uploaded Content', status: 'ready', wordCount: text.split(/\s+/).filter(Boolean).length });
            // Store the text so the spin step can use it
            this.checkResults.push({ url: 'upload://', success: true, text: text, word_count: text.split(/\s+/).filter(Boolean).length, formatted_html: '<p>' + text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>' });
            this.approvedSources.push(this.checkResults.length - 1);
            this.completeStep(3);
            this.completeStep(4);
            this.openStep(5);
            this.showNotification('success', 'Content added as source — proceed to AI & Spin');
        },

        skipSpinPublishAsIs() {
            const text = this.uploadedSourceText.trim();
            if (!text) return;
            // Set as editor content directly, skip spinning
            const html = '<p>' + text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
            this.editorContent = html;
            this.spunContent = html;
            this.spunWordCount = text.split(/\s+/).filter(Boolean).length;
            this.articleTitle = this.uploadedSourceDoc?.name?.replace(/\.(docx?|pdf)$/i, '') || 'Untitled';
            this.completeStep(3);
            this.completeStep(4);
            this.completeStep(5);
            this.openStep(6);
            this.showNotification('success', 'Content loaded — skipped spinning, go to Create Article');
        },

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

        setNewsMode(mode) {
            this.newsMode = mode;
            this.newsResults = [];
            this.newsHasSearched = false;
            this.newsSearching = false;

            if (mode === 'trending') {
                this.newsSearch = '';
                this.newsCategory = '';
                this.newsTrendingSelected = false;
                return;
            }

            this.newsTrendingSelected = false;
            if (mode !== 'genre') {
                this.newsCategory = '';
            }
        },

        selectTrendingCategory(category = '') {
            this.newsMode = 'trending';
            this.newsSearch = '';
            this.newsCategory = category;
            this.newsTrendingSelected = true;
            this.newsResults = [];
            this.newsHasSearched = false;
            this.searchNews();
        },

        _newsAbortController: null,

        async searchNews() {
            if (this.newsMode === 'keyword' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'local' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'genre' && !this.newsCategory && !this.newsSearch.trim()) return;
            if (this.newsMode === 'trending' && !this.newsTrendingSelected) return;
            // Abort any in-flight search
            if (this._newsAbortController) { try { this._newsAbortController.abort(); } catch {} }
            this._newsAbortController = new AbortController();
            const signal = this._newsAbortController.signal;
            this.newsSearching = true;
            this.newsResults = [];
            this.newsHasSearched = false;
            try {
                const resp = await fetch('{{ route('publish.search.articles.post') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        query: this.newsSearch,
                        mode: this.newsMode,
                        category: this.newsCategory,
                        country: this.newsCountry,
                    }),
                    signal,
                });
                const data = await resp.json();
                this.newsResults = (data.data && data.data.articles) ? data.data.articles : [];
                this.newsHasSearched = true;
            } catch (e) {
                if (e.name === 'AbortError') return; // Cancelled by new search — don't update state
                this.newsResults = [];
                this.newsHasSearched = true;
                this.showNotification('error', 'Search failed: ' + e.message);
            } finally {
                if (!signal.aborted) this.newsSearching = false;
            }
        },

        _logAi(type, message) {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false });
            this.aiLog.push({ type, message, time });
        },

        async aiSearchArticles() {
            if (!this.aiSearchTopic.trim()) return;
            this.aiSearching = true;
            this.aiSearchResults = [];
            this.aiSearchError = '';
            this.aiSearchCost = null;
            this.aiHasSearched = false;
            this.aiLog = [];

            this._logAi('info', 'Starting AI article search for: ' + this.aiSearchTopic);
            this._logAi('info', 'Requesting top 10 articles via ' + this.aiSearchModel + ' with web search...');

            try {
                const resp = await fetch('{{ route("publish.pipeline.ai-search") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        topic: this.aiSearchTopic,
                        count: 10,
                        model: this.aiSearchModel,
                    }),
                });
                const data = await resp.json();
                if (data.success && data.data && data.data.articles) {
                    this._logAi('success', 'Found ' + data.data.articles.length + ' article(s)');
                    this.aiSearchResults = data.data.articles;

                    // Log each article
                    data.data.articles.forEach((article, i) => {
                        this._logAi('step', (i + 1) + '. ' + article.title + ' — ' + article.url.substring(0, 80));
                    });

                    this._logAi('info', data.data.articles.length + ' articles ready — select the ones you want as sources');

                    // Cost info
                    if (data.data.usage) {
                        this.aiSearchCost = { model: data.data.model || 'claude-haiku-4-5-20251001', cost: data.data.cost || 0, usage: data.data.usage };
                        this._logAi('info', 'Cost: $' + (data.data.cost || 0).toFixed(4) + ' | Tokens: ' + (data.data.usage.input_tokens || 0) + '+' + (data.data.usage.output_tokens || 0) + ' | Model: ' + (data.data.model || 'claude-haiku-4-5-20251001'));
                    }

                    this.showNotification('success', data.data.articles.length + ' article(s) found — select sources below.');
                } else {
                    this._logAi('error', data.message || 'No articles found.');
                    this.aiSearchError = data.message || 'No articles found.';
                }
                this.aiHasSearched = true;
            } catch (e) {
                this._logAi('error', 'Search failed: ' + e.message);
                this.aiSearchError = 'Search failed: ' + e.message;
                this.aiHasSearched = true;
            }
            this.aiSearching = false;
        },

        async searchPrProfiles() {
            this.prProfileSearching = true;
            this.prProfileDropdownOpen = true;
            try {
                const params = new URLSearchParams({ q: this.prProfileSearch || '' });
                const resp = await fetch('{{ route("publish.profiles.search") }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                this.prProfileResults = await resp.json();
            } catch (e) {
                this.prProfileResults = [];
            }
            this.prProfileSearching = false;
        },

        addPrProfile(profile) {
            if (this.selectedPrProfiles.some(p => p.id === profile.id)) return;
            profile.context = '';
            this.selectedPrProfiles.push(profile);
            this.prProfileSearch = '';
            this.prProfileResults = [];
            this.savePipelineState();
            // Auto-load Notion data + relations + photos
            this.loadProfileData(profile);
        },

        async loadProfileData(profile) {
            if (!this.prSubjectData[profile.id]) {
                this.prSubjectData[profile.id] = {
                    loading: false, loaded: false,
                    fields: [], driveUrl: '', photos: [], loadingPhotos: false,
                    notionUrl: '', relations: [], selectedEntries: {}, selectedPhotos: {},
                };
            }
            const pd = this.prSubjectData[profile.id];
            pd.loading = true;
            pd.fields = [];
            pd.relations = [];

            // Local fields fallback
            if (profile.fields && Object.keys(profile.fields).length > 0) {
                for (const [key, val] of Object.entries(profile.fields)) {
                    if (val) pd.fields.push({ key, notion_field: key, value: val, display_value: val });
                }
            }

            // Notion-linked: use context endpoint for fields + relations
            if (profile.external_source === 'notion' && profile.external_id) {
                pd.notionUrl = 'https://notion.so/' + profile.external_id.replace(/-/g, '');
                try {
                    const resp = await fetch('/notion/profile/' + profile.id + '/context', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ fresh: false }),
                    });
                    const data = await resp.json();
                    if (data.success) {
                        if (data.fields?.length) pd.fields = data.fields;
                        pd.driveUrl = data.profile?.drive_url || '';
                        pd.notionUrl = data.profile?.notion_url || pd.notionUrl;
                        pd.relations = (data.relations || []).map(r => ({
                            ...r, open: true, loading: false, loaded: false, entries: [],
                        }));
                        // Auto-load all relation entries
                        for (const rel of pd.relations) {
                            this.loadPrRelation(profile.id, rel.slug);
                        }
                        // Auto-load photos
                        if (pd.driveUrl) this.loadProfilePhotos(profile);
                    }
                } catch (e) {}
            }
            pd.loaded = true;
            pd.loading = false;
        },

        async loadPrRelation(profileId, slug) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = pd.relations.find(r => r.slug === slug);
            if (!rel) return;
            rel.loading = true;
            try {
                const resp = await fetch('/notion/profile/' + profileId + '/relations/' + slug, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ fresh: false }),
                });
                const data = await resp.json();
                if (data.success) {
                    rel.entries = (data.entries || []).map(e => ({ ...e, open: false, loading: false, detail: null }));
                    rel.count = data.total || rel.entries.length;
                    rel.loaded = true;
                }
            } catch (e) {}
            rel.loading = false;
        },

        async loadPrEntryDetail(profileId, slug, entryId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = pd.relations.find(r => r.slug === slug);
            if (!rel) return;
            const entry = rel.entries.find(e => e.id === entryId);
            if (!entry) return;
            if (entry.detail) { entry.open = !entry.open; return; }
            entry.open = true;
            entry.loading = true;
            try {
                const resp = await fetch('/notion/page/' + entryId + '/detail', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ fresh: false }),
                });
                const data = await resp.json();
                if (data.success) entry.detail = data.page || null;
            } catch (e) {}
            entry.loading = false;
        },

        togglePrEntry(profileId, slug, entryId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = pd.relations.find(r => r.slug === slug);
            if (!rel) return;
            const entry = rel.entries.find(e => e.id === entryId);
            if (!entry) return;
            if (!entry.detail) { this.loadPrEntryDetail(profileId, slug, entryId); return; }
            entry.open = !entry.open;
        },

        togglePrEntrySelect(profileId, entryId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            if (!pd.selectedEntries) pd.selectedEntries = {};
            pd.selectedEntries[entryId] = !pd.selectedEntries[entryId];
        },

        togglePrPhotoSelect(profileId, photoId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            if (!pd.selectedPhotos) pd.selectedPhotos = {};
            pd.selectedPhotos[photoId] = !pd.selectedPhotos[photoId];
        },

        buildPrSubjectContext() {
            if (!this.selectedPrProfiles?.length) return null;
            const parts = [];
            for (const profile of this.selectedPrProfiles) {
                const pd = this.prSubjectData[profile.id];
                if (!pd?.loaded) continue;
                let section = '--- SUBJECT: ' + profile.name + ' ---\n';
                // User instructions
                if (profile.context) section += 'INSTRUCTIONS: ' + profile.context + '\n\n';
                // Profile fields
                if (pd.fields?.length) {
                    section += 'PROFILE DATA:\n';
                    for (const f of pd.fields) {
                        if (f.display_value || f.value) section += '- ' + f.notion_field + ': ' + (f.display_value || f.value) + '\n';
                    }
                    section += '\n';
                }
                // Selected relation entries
                const selectedIds = Object.keys(pd.selectedEntries || {}).filter(id => pd.selectedEntries[id]);
                if (selectedIds.length > 0 && pd.relations?.length) {
                    for (const rel of pd.relations) {
                        const selected = (rel.entries || []).filter(e => selectedIds.includes(e.id));
                        if (selected.length === 0) continue;
                        section += rel.label.toUpperCase() + ':\n';
                        for (const entry of selected) {
                            section += '  - ' + entry.title + '\n';
                            if (entry.detail?.properties) {
                                for (const [k, v] of Object.entries(entry.detail.properties)) {
                                    if (v && typeof v === 'string' && v.length > 0) section += '    ' + k + ': ' + v + '\n';
                                }
                            }
                        }
                        section += '\n';
                    }
                }
                // Selected photos
                const selectedPhotoIds = Object.keys(pd.selectedPhotos || {}).filter(id => pd.selectedPhotos[id]);
                if (selectedPhotoIds.length > 0) {
                    section += 'SELECTED PHOTOS (' + selectedPhotoIds.length + '):\n';
                    for (const photo of (pd.photos || [])) {
                        if (selectedPhotoIds.includes(photo.id)) {
                            section += '- ' + photo.name + ' [URL: ' + (photo.webViewLink || photo.webContentLink || '') + ']\n';
                        }
                    }
                    section += 'Use [PHOTO: filename] markers in the article body where each selected photo should be placed.\n\n';
                }
                parts.push(section);
            }
            return parts.length ? parts.join('\n') : null;
        },

        async loadProfilePhotos(profile) {
            const data = this.prSubjectData[profile.id];
            if (!data || !data.driveUrl) return;
            data.loadingPhotos = true;
            try {
                const resp = await fetch('{{ route("notion.profile.fetch-photos") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ drive_url: data.driveUrl }),
                });
                const result = await resp.json();
                if (result.success) {
                    data.photos = result.photos || [];
                }
            } catch (e) {}
            data.loadingPhotos = false;
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
        async flagAsBroken(idx) {
            const result = this.checkResults[idx];
            const source = this.sources[idx];
            if (!source) return;
            try {
                await fetch('/publish/scrape-activity/ban', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        domain: new URL(source.url).hostname,
                        reason: 'Flagged as broken from pipeline — ' + (result?.message || 'user flagged'),
                    })
                });
            } catch(e) {}
            this.discardSource(idx);
            this.showNotification('warning', 'Source flagged as broken and domain banned');
        },
        removeSource(idx) {
            this.sources.splice(idx, 1);
            this.checkResults.splice(idx, 1);
            this.approvedSources = this.approvedSources.filter(i => i !== idx).map(i => i > idx ? i - 1 : i);
            this.discardedSources = this.discardedSources.filter(i => i !== idx).map(i => i > idx ? i - 1 : i);
            this.expandedSources = this.expandedSources.filter(i => i !== idx).map(i => i > idx ? i - 1 : i);
            this.savePipelineState();
        },
        async saveFailedSource(idx) {
            const result = this.checkResults[idx];
            const source = this.sources[idx];
            if (!source) return;

            // Extract keywords from URL for search
            const url = source.url || '';
            const title = source.title || '';
            let keywords = title;
            if (!keywords) {
                // Parse URL path into keywords: /world/2024/04/05/taiwan-president-lai-female-vice -> taiwan president lai female vice
                try {
                    const path = new URL(url).pathname;
                    keywords = path.split('/').pop().replace(/[-_]/g, ' ').replace(/\.(html?|php|aspx?)$/i, '').replace(/\d{4,}/g, '').trim();
                } catch(e) { keywords = ''; }
            }

            try {
                await fetch('{{ route("publish.failed-sources.store") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        url: source.url,
                        title: title,
                        error_message: result?.message || 'Extraction failed',
                        source_api: result?.source_api || '',
                    })
                });
            } catch(e) {}
            this.removeSource(idx);

            // Switch to Search Online tab with extracted keywords
            if (keywords) {
                this.sourceTab = 'ai';
                this.aiSearchTopic = keywords;
                this.showNotification('info', 'Searching for alternative sources: ' + keywords);
            } else {
                this.showNotification('success', 'Saved to failed list and removed.');
            }
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
            // Keep step 4 open during extraction
            if (!this.openSteps.includes(4)) this.openSteps.push(4);

            const methodNames = { auto: 'Auto (Readability + Regex fallback)', readability: 'Readability (structured)', regex: 'Regex (raw HTML parsing)', trafilatura: 'Trafilatura (Python)', jina: 'Jina Reader API' };
            const uaNames = { chrome: 'Chrome Desktop', googlebot: 'Googlebot', bingbot: 'Bingbot', mobile: 'Mobile Chrome', curl: 'cURL' };
            this._logCheck('info', 'Starting article extraction for ' + this.sources.length + ' source(s)...');
            this.sources.forEach((s, i) => this._logCheck('step', (i + 1) + '. ' + s.url));
            this._logCheck('info', 'Method: ' + (methodNames[this.extractMethod] || this.extractMethod));
            this._logCheck('info', 'User Agent: ' + (uaNames[this.checkUserAgent] || this.checkUserAgent));
            this._logCheck('info', 'Timeout: ' + this.extractTimeout + 's per request | Retries: ' + this.extractRetries + ' | Min words: ' + this.extractMinWords);
            this._logCheck('info', 'Auto-fallback (Googlebot): ' + (this.extractAutoFallback ? 'On' : 'Off'));
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
                    const url = this.sources[i]?.url || 'unknown';
                    const domain = url.replace(/^https?:\/\//, '').split('/')[0];
                    if (r.success) {
                        this._logCheck('success', domain + ' — ' + r.word_count + ' words' + (r.title ? ' — "' + r.title.substring(0, 60) + '"' : ''));
                        if (r.method_used) this._logCheck('step', '  Method: ' + (methodNames[r.method_used] || r.method_used));
                        if (r.response_time) this._logCheck('step', '  Response time: ' + r.response_time + 'ms');
                        if (r.http_status) this._logCheck('step', '  HTTP: ' + r.http_status);
                    } else {
                        this._logCheck('error', domain + ' — ' + (r.message || 'Extraction failed'));
                        if (r.http_status) this._logCheck('step', '  HTTP: ' + r.http_status);
                        if (r.method_used) this._logCheck('step', '  Method tried: ' + (methodNames[r.method_used] || r.method_used));
                        if (r.fallback_tried) this._logCheck('step', '  Fallback attempted: ' + r.fallback_tried);
                    }
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
                if (this.selectedTemplate) {
                    if (this.selectedTemplate.ai_engine) this.aiModel = this.selectedTemplate.ai_engine;
                    this.loadPresetFields('template', this.selectedTemplate);
                }
            } else {
                this.selectedTemplate = null;
                this.loadPresetFields('template', null);
            }

            if (!this._restoring) this.autoSaveDraft();
        },

        // ── Step 7: Spin ──────────────────────────────────
        _logSpin(type, message) {
            const time = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.spinLog.push({ type, message, time });
        },

        _promptRefreshTimer: null,
        _queuePromptRefresh() {
            clearTimeout(this._promptRefreshTimer);
            this._promptRefreshTimer = setTimeout(() => this.refreshPromptPreview(), 500);
        },

        async refreshPhotoMeta(idx) {
            const ps = this.photoSuggestions[idx];
            if (!ps) return;
            const oldAlt = ps.alt_text || '';
            const oldCaption = ps.caption || '';
            const oldFilename = ps.suggestedFilename || '';
            this.photoSuggestions[idx].refreshingMeta = true;
            try {
                const articleText = (this.spunContent || '').replace(/<[^>]*>/g, '').substring(0, 2000);
                const resp = await fetch('{{ route("publish.pipeline.photo-meta") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        search_term: ps.search_term,
                        article_title: this.articleTitle || '',
                        article_text: articleText,
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.photoSuggestions[idx].alt_text = data.alt;
                    this.photoSuggestions[idx].caption = data.caption;
                    // Filename comes from settings pattern, not AI
                    this.photoSuggestions[idx].suggestedFilename = this.buildFilename(ps.search_term, idx + 1);
                    console.log('[Photo Meta #' + idx + '] OLD: alt="' + oldAlt + '" caption="' + oldCaption + '"');
                    console.log('[Photo Meta #' + idx + '] NEW: alt="' + data.alt + '" caption="' + data.caption + '" file="' + this.photoSuggestions[idx].suggestedFilename + '"');
                    this.showNotification('success', 'Metadata refreshed for photo #' + (idx + 1));
                } else {
                    console.error('[Photo Meta #' + idx + '] Error:', data.message || 'Unknown error');
                    this.showNotification('error', 'Failed to refresh metadata: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('[Photo Meta #' + idx + '] Exception:', e);
                this.showNotification('error', 'Failed to refresh metadata: ' + e.message);
            }
            this.photoSuggestions[idx].refreshingMeta = false;
        },

        async refreshFeaturedMeta() {
            const oldAlt = this.featuredAlt || '';
            const oldCaption = this.featuredCaption || '';
            const oldFilename = this.featuredFilename || '';
            this.featuredRefreshingMeta = true;
            try {
                const articleText = (this.spunContent || '').replace(/<[^>]*>/g, '').substring(0, 2000);
                const resp = await fetch('{{ route("publish.pipeline.photo-meta") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        search_term: this.featuredImageSearch,
                        article_title: this.articleTitle || '',
                        article_text: articleText,
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.featuredAlt = data.alt;
                    this.featuredCaption = data.caption;
                    // Filename comes from settings pattern, not AI
                    this.featuredFilename = this.buildFilename(this.featuredImageSearch, 0);
                    console.log('[Featured Meta] OLD: alt="' + oldAlt + '" caption="' + oldCaption + '"');
                    console.log('[Featured Meta] NEW: alt="' + data.alt + '" caption="' + data.caption + '" file="' + this.featuredFilename + '"');
                    this.showNotification('success', 'Featured image metadata refreshed');
                } else {
                    console.error('[Featured Meta] Error:', data.message || 'Unknown error');
                    this.showNotification('error', 'Failed to refresh metadata: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('[Featured Meta] Exception:', e);
                this.showNotification('error', 'Failed to refresh metadata: ' + e.message);
            }
            this.featuredRefreshingMeta = false;
        },

        async getOverlayPhotoMeta() {
            if (!this.insertingPhoto) return;
            this.overlayMetaLoading = true;
            try {
                const articleText = (this.spunContent || '').replace(/<[^>]*>/g, '').substring(0, 2000);
                const resp = await fetch('{{ route("publish.pipeline.photo-meta") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        search_term: this.photoSearch || this.insertingPhoto.alt || '',
                        article_title: this.articleTitle || '',
                        article_text: articleText,
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.overlayPhotoAlt = data.alt;
                    this.overlayPhotoCaption = data.caption;
                    // Filename comes from settings pattern, not AI
                    this.overlayPhotoFilename = this.buildFilename(this.photoSearch || this.insertingPhoto?.alt, 0);
                    this.overlayMetaGenerated = true;
                    console.log('[Overlay Meta] NEW: alt="' + data.alt + '" caption="' + data.caption + '" file="' + this.overlayPhotoFilename + '"');
                    this.showNotification('success', 'Photo metadata generated');
                } else {
                    console.error('[Overlay Meta] Error:', data.message || 'Unknown error');
                    this.showNotification('error', 'Failed to get metadata: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('[Overlay Meta] Exception:', e);
                this.showNotification('error', 'Failed to get metadata: ' + e.message);
            }
            this.overlayMetaLoading = false;
        },

        async refreshPromptPreview() {
            this.promptLoading = true;
            try {
                const sourceTexts = this.currentArticleType === 'press-release'
                    ? [this.buildPressReleaseSourceText(true)]
                    : (
                        this.approvedSources.length > 0
                            ? this.approvedSources.map(i => this.checkResults[i]?.text || '').filter(Boolean)
                            : ['[Source articles will be inserted here]']
                    );
                const resp = await fetch('{{ route("publish.pipeline.preview-prompt") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        source_texts: sourceTexts,
                        template_id: this.selectedTemplateId || null,
                        preset_id: this.selectedPresetId || null,
                        prompt_slug: this.currentPressReleasePromptSlug(),
                        custom_prompt: this.customPrompt || null,
                        web_research: this.spinWebResearch,
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.resolvedPrompt = data.prompt;
                    this.promptLog = data.log || [];
                }
            } catch (e) { /* silent */ }
            this.promptLoading = false;
        },

        async spinArticle() {
            this.spinning = true;
            this.spinError = '';
            this.spinLog = [];
            this._logSpin('info', 'Starting article spin...');
            this._logSpin('step', 'Model: ' + this.aiModel);
            this._logSpin('step', this.currentArticleType === 'press-release'
                ? 'Press release source: ' + (this.pressRelease.resolved_source_label || this.pressRelease.submit_method)
                : 'Sources: ' + this.checkResults.filter(r => r.success).length);
            if (this.customPrompt) this._logSpin('step', 'Custom instructions: ' + this.customPrompt.substring(0, 100));
            if (this.selectedTemplate) this._logSpin('step', 'Template: ' + this.selectedTemplate.name);
            if (this.selectedPreset) this._logSpin('step', 'Preset: ' + this.selectedPreset.name);

            // Collect verified source texts
            const sourceTexts = this.currentArticleType === 'press-release'
                ? [this.buildPressReleaseSourceText(false)].filter(Boolean)
                : this.checkResults
                    .filter(r => r.success && r.text)
                    .map(r => r.text);

            if (sourceTexts.length === 0) {
                this.spinError = this.currentArticleType === 'press-release'
                    ? 'No submitted press release source text is available yet. Detect fields or provide content first.'
                    : 'No verified source texts available. Please check sources first.';
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
                        prompt_slug: this.currentPressReleasePromptSlug(),
                        model: this.aiModel,
                        custom_prompt: this.customPrompt || null,
                        pr_subject_context: this.buildPrSubjectContext(),
                        web_research: this.spinWebResearch,
                    })
                });
                const data = await resp.json();

                if (data.success) {
                    this.spunContent = data.html;
                    this.editorContent = data.html;
                    this.spunWordCount = data.word_count;
                    this.tokenUsage = data.usage;
                    this.setSpinEditor(data.html);
                    this.lastAiCall = { user_name: data.user_name, model: data.model, provider: data.provider, usage: data.usage, cost: data.cost, ip: data.ip, timestamp_utc: data.timestamp_utc };
                    this.showNotification('success', data.message);
                    this._logSpin('success', 'Article generated — ' + data.word_count + ' words');
                    this._logSpin('info', 'Model: ' + (data.model || this.aiModel) + ' | Cost: $' + (data.cost || 0).toFixed(4) + ' | Tokens: ' + ((data.usage?.input_tokens || 0) + '+' + (data.usage?.output_tokens || 0)));
                    this.extractArticleLinks(data.html);

                    // Metadata from single prompt (titles, categories, tags)
                    if (data.metadata) {
                        this._logSpin('success', 'Metadata: ' + (data.metadata.titles?.length || 0) + ' titles, ' + (data.metadata.categories?.length || 0) + ' categories, ' + (data.metadata.tags?.length || 0) + ' tags');
                        this.suggestedTitles = data.metadata.titles || [];
                        this.suggestedCategories = data.metadata.categories || [];
                        this.suggestedTags = data.metadata.tags || [];
                        if (data.metadata.description) this.articleDescription = data.metadata.description;
                        this.selectedTitleIdx = 0;
                        if (this.suggestedTitles.length > 0) this.articleTitle = this.suggestedTitles[0];
                        this.selectedCategories = Array.from({length: Math.min(10, this.suggestedCategories.length)}, (_, i) => i);
                        this.selectedTags = Array.from({length: Math.min(10, this.suggestedTags.length)}, (_, i) => i);
                    }

                    // Featured image — auto-fetch with results
                    if (data.featured_image) {
                        this.featuredImageSearch = data.featured_image;
                        this.searchFeaturedImage();
                    }
                    if (data.featured_meta) {
                        this.featuredAlt = data.featured_meta.alt || '';
                        this.featuredCaption = data.featured_meta.caption || '';
                        this.featuredFilename = (data.featured_meta.filename || 'featured') + '.jpg';
                    }

                    // Resolved prompt for preview
                    if (data.resolved_prompt) this.resolvedPrompt = data.resolved_prompt;

                    if (data.photo_suggestions) {
                        this.photoSuggestions = data.photo_suggestions.map(ps => ({...ps, autoPhoto: null, confirmed: false, removed: false, refreshingMeta: false, searchResults: []}));
                    }

                    this.queueAutoSaveDraft(300);

                    // Always advance to Create Article after spin
                    this.completeStep(5);
                    // Open both AI & Spin and Create Article
                    this.currentStep = 6;
                    this.openSteps = [5, 6];
                    if (!this._restoring) this._syncStepToUrl();
                    // Run AI detection in background (non-blocking)
                    if (this.aiDetectionEnabled) {
                        this.$nextTick(() => this.runAiDetection());
                    }
                } else {
                    this.spinError = data.message;
                    this._logSpin('error', 'Spin failed: ' + data.message);
                }
            } catch (e) {
                this.spinError = 'Network error during spinning.';
                this._logSpin('error', 'Network error: ' + (e.message || 'Request failed'));
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
            this.completeStep(6);
            this.openStep(7);
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
                    this.editorContent = data.html;
                    this.spunWordCount = data.word_count;
                    this.tokenUsage = data.usage;
                    this.setSpinEditor(data.html);
                    this.lastAiCall = { user_name: data.user_name, model: data.model, provider: data.provider, usage: data.usage, cost: data.cost, ip: data.ip, timestamp_utc: data.timestamp_utc };
                    this.spinChangeRequest = '';
                    this.showChangeInput = false;
                    this.appliedSmartEdits = [];
                    this.showNotification('success', 'Changes applied.');
                    this.queueAutoSaveDraft(300);

                    // Re-run AI detection after changes
                    this.$nextTick(() => this.runAiDetection());
                } else {
                    this.spinError = data.message;
                }
            } catch (e) {
                this.spinError = 'Network error.';
            }
            this.spinning = false;
        },

        // ── AI Detection ──────────────────────────────────
        toggleAiDetection() {
            this.aiDetectionEnabled = !this.aiDetectionEnabled;
            localStorage.setItem('aiDetectionEnabled', this.aiDetectionEnabled ? 'true' : 'false');
        },

        toggleFlaggedSentence(id, text) {
            const idx = this.selectedFlaggedSentences.indexOf(id);
            if (idx === -1) {
                this.selectedFlaggedSentences.push(id);
                this.selectedFlaggedTexts[id] = text;
            } else {
                this.selectedFlaggedSentences.splice(idx, 1);
                delete this.selectedFlaggedTexts[id];
            }
        },

        async runAiDetection() {
            if (!this.spunContent || !this.aiDetectionEnabled) return;
            this.aiDetecting = true;
            this.aiDetectionRan = true;
            this.aiDetectionAllPass = false;
            this.selectedFlaggedSentences = [];
            this.selectedFlaggedTexts = {};

            // Initialize all detectors with loading state
            this.aiDetectionResults = {
                gptzero: { name: 'GPTZero', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
                copyleaks: { name: 'Copyleaks', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
                zerogpt: { name: 'ZeroGPT', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
                originality: { name: 'Originality', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
            };

            this._logSpin('info', 'Running AI detection scan...');

            const spinEditor = tinymce.get('spin-preview-editor');
            const html = spinEditor ? spinEditor.getContent() : this.spunContent;
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            const plainText = tmp.textContent || tmp.innerText;

            try {
                const resp = await fetch('{{ route("publish.pipeline.detect-ai") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ text: plainText, article_id: this.draftId })
                });
                const data = await resp.json();

                if (data.success) {
                    this.aiDetectionThreshold = data.threshold;
                    this.aiDetectionAllPass = data.all_pass;

                    const updatedResults = {};
                    for (const [key, result] of Object.entries(data.results)) {
                        updatedResults[key] = { ...result, loading: false, showRaw: false };
                    }
                    this.aiDetectionResults = updatedResults;

                    const passCount = Object.values(data.results).filter(r => r.passes).length;
                    const totalCount = Object.values(data.results).length;
                    this._logSpin(data.all_pass ? 'success' : 'warning', 'AI Detection: ' + passCount + '/' + totalCount + ' passed (max ' + data.threshold + '% AI)');
                } else {
                    this._logSpin('error', 'AI detection failed: ' + (data.message || 'Unknown error'));
                    this.aiDetectionResults = {};
                }
            } catch (e) {
                this._logSpin('error', 'AI detection network error: ' + (e.message || 'Request failed'));
                const failed = {};
                for (const key of Object.keys(this.aiDetectionResults)) {
                    failed[key] = { ...this.aiDetectionResults[key], loading: false, success: false, message: 'Network error' };
                }
                this.aiDetectionResults = failed;
            }
            this.aiDetecting = false;
        },

        processDetectionRespin() {
            // Use selected checkboxes if any, otherwise all flagged from failing detectors
            const selected = Object.values(this.selectedFlaggedTexts);
            let flagged = [];

            if (selected.length > 0) {
                flagged = selected;
            } else {
                for (const [key, det] of Object.entries(this.aiDetectionResults)) {
                    if (!det.passes && det.sentences && det.sentences.length > 0) {
                        flagged.push(...det.sentences);
                    }
                }
            }

            const humanizePrompt = flagged.length > 0
                ? 'The following sentences were flagged as AI-generated. Rewrite them to sound more natural and human:\n\n' + flagged.map(s => '- ' + s).join('\n') + '\n\nMake the writing more conversational, vary sentence length, use natural transitions, and avoid formulaic patterns.'
                : 'This article was flagged as AI-generated. Rewrite it to sound more natural and human. Vary sentence length, use natural transitions, and avoid formulaic patterns.';

            this.spinChangeRequest = humanizePrompt;
            this.showChangeInput = true;
            this.loadSmartEdits();
            this.$nextTick(() => {
                document.querySelector('[x-show="showChangeInput"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },

        ignoreDetection() {
            this.acceptSpin();
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
                            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link image media | addPhotoBtn uploadPhotoBtn | table | alignleft aligncenter alignright | outdent indent | fullscreen code searchreplace',
                            menubar: true,
                            min_height: 400,
                            autoresize_bottom_margin: 50,
                            extended_valid_elements: 'div[*],span[*],img[*],figure[*],figcaption[*]',
                            custom_elements: '~div',
                            content_style: '.photo-placeholder { cursor: pointer !important; } .photo-placeholder:hover { opacity: 0.9; }',
                            setup: function(ed) {
                                // Custom "Add Photo" toolbar button
                                ed.ui.registry.addButton('addPhotoBtn', {
                                    icon: 'gallery',
                                    tooltip: 'Search & Insert Photo',
                                    onAction: function() {
                                        self._photoSuggestionIdx = null;
                                        self.photoSearch = '';
                                        self.photoResults = [];
                                        self.insertingPhoto = null;
                                        self.showPhotoOverlay = true;
                                    }
                                });

                                // Custom "Upload Photo" toolbar button
                                ed.ui.registry.addButton('uploadPhotoBtn', {
                                    icon: 'upload',
                                    tooltip: 'Upload Photo from Device',
                                    onAction: function() {
                                        self.showUploadPortal = true;
                                    }
                                });

                                ed.on('init', function() {
                                    ed.setContent(html || '');
                                    // Auto-fetch photos AFTER editor is fully initialized
                                    if (self.photoSuggestions.length > 0 && !self.photoSuggestions[0].autoPhoto) {
                                        self.autoFetchPhotos();
                                    }
                                    self.syncEditorStateFromEditor();
                                });
                                ed.on('change keyup input SetContent Undo Redo', function() {
                                    self.syncEditorStateFromEditor();
                                });

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

        async loadSyndicationCategories() {
            if (!this.selectedSite?.id) return;
            this.loadingSyndicationCats = true;
            try {
                const resp = await fetch('/publish/sites/' + this.selectedSite.id + '/categories', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                const data = await resp.json();
                if (data.success && data.categories) {
                    this.syndicationCategories = data.categories;
                    // Auto-select all by default
                    this.selectedSyndicationCats = data.categories.map(c => c.id);
                } else {
                    this.showNotification('error', data.message || 'Failed to load categories');
                }
            } catch (e) {
                this.showNotification('error', 'Error loading categories: ' + e.message);
            }
            this.loadingSyndicationCats = false;
        },

        toggleSyndicationCat(id) {
            const idx = this.selectedSyndicationCats.indexOf(id);
            if (idx > -1) this.selectedSyndicationCats.splice(idx, 1);
            else this.selectedSyndicationCats.push(id);
        },

        removeArticleLink(idx) {
            const link = this.suggestedUrls[idx];
            if (!link) return;

            // Remove from the TinyMCE editor content
            const editor = typeof tinymce !== 'undefined' ? tinymce.activeEditor : null;
            if (editor) {
                let html = editor.getContent();
                // Find and remove <a> tags matching this URL — replace with just the link text
                const tmp = document.createElement('div');
                tmp.innerHTML = html;
                const anchors = tmp.querySelectorAll('a[href]');
                for (const a of anchors) {
                    if (a.getAttribute('href') === link.url) {
                        a.replaceWith(document.createTextNode(a.textContent));
                    }
                }
                editor.setContent(tmp.innerHTML);
                this.editorContent = tmp.innerHTML;
                this.spunContent = tmp.innerHTML;
            }

            // Remove from the array
            this.suggestedUrls.splice(idx, 1);
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
            this.featuredSearching = true;
            this.featuredResults = [];
            try {
                const resp = await fetch('{{ route("publish.search.images.post") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ query: this.featuredImageSearch, per_page: 8 })
                });
                const data = await resp.json();
                const photos = data.data?.photos || [];
                this.featuredResults = photos;
                if (photos.length > 0 && !this.featuredPhoto) this.featuredPhoto = photos[0];
            } catch (e) {}
            this.featuredSearching = false;
        },

        importFeaturedFromUrl() {
            const url = this.featuredUrlImport.trim();
            if (!url) return;
            this.featuredPhoto = { url_large: url, url_thumb: url, url: url, source: 'url-import', alt: '', width: 0, height: 0 };
            this.featuredUrlImport = '';
            this.showNotification('success', 'Featured image set from URL');
        },

        async uploadFeaturedPhoto(files) {
            if (!files || !files.length) return;
            const file = files[0];
            const url = URL.createObjectURL(file);
            // Upload to server temp storage
            const formData = new FormData();
            formData.append('file', file);
            formData.append('draft_id', this.draftId);
            formData.append('type', 'featured');
            try {
                const resp = await fetch('{{ route("publish.pipeline.upload-photo") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: formData,
                });
                const data = await resp.json();
                if (data.success) {
                    this.featuredPhoto = { url_large: data.url, url_thumb: data.url, url: data.url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: data.width || 0, height: data.height || 0 };
                    this.showNotification('success', 'Featured image uploaded');
                } else {
                    this.showNotification('error', data.message || 'Upload failed');
                }
            } catch (e) {
                // Fallback: use local blob URL
                this.featuredPhoto = { url_large: url, url_thumb: url, url: url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: 0, height: 0 };
                this.showNotification('info', 'Using local preview — will upload during prepare');
            }
        },

        importInnerPhotoFromUrl() {
            const url = this.innerPhotoUrlImport.trim();
            if (!url) return;
            this.insertingPhoto = { url_large: url, url_thumb: url, url: url, source: 'url-import', alt: '', width: 0, height: 0 };
            this.overlayPhotoAlt = 'Click Get Metadata to generate';
            this.overlayPhotoCaption = 'Click Get Metadata to generate';
            this.overlayPhotoFilename = 'auto';
            this.overlayMetaGenerated = false;
            this.innerPhotoUrlImport = '';
        },

        async uploadInnerPhoto(files) {
            if (!files || !files.length) return;
            const file = files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('draft_id', this.draftId);
            formData.append('type', 'inner');
            try {
                const resp = await fetch('{{ route("publish.pipeline.upload-photo") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: formData,
                });
                const data = await resp.json();
                if (data.success) {
                    this.insertingPhoto = { url_large: data.url, url_thumb: data.url, url: data.url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: data.width || 0, height: data.height || 0 };
                    this.overlayPhotoAlt = file.name.replace(/\.[^.]+$/, '');
                    this.overlayPhotoCaption = '';
                    this.overlayPhotoFilename = file.name.replace(/\.[^.]+$/, '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
                    this.overlayMetaGenerated = false;
                } else {
                    this.showNotification('error', data.message || 'Upload failed');
                }
            } catch (e) {
                const url = URL.createObjectURL(file);
                this.insertingPhoto = { url_large: url, url_thumb: url, url: url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: 0, height: 0 };
                this.overlayPhotoAlt = file.name.replace(/\.[^.]+$/, '');
                this.overlayPhotoCaption = '';
                this.overlayPhotoFilename = 'auto';
                this.overlayMetaGenerated = false;
            }
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
            this.syncEditorStateFromEditor();
            this.queueAutoSaveDraft(300);
            this.insertingPhoto = null;
            this.photoCaption = '';
            this.showPhotoPanel = false;
        },

        // ── Photo Management ─────────────────────────────
        async autoFetchPhotos() {
            if (this.photoSuggestions.length === 0) return;
            this.autoFetchingPhotos = true;
            // Sequential (not parallel) to avoid blocking single-threaded server
            for (let idx = 0; idx < this.photoSuggestions.length; idx++) {
                const ps = this.photoSuggestions[idx];
                if (ps.autoPhoto) continue; // Already loaded
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
                        this.photoSuggestions[idx].suggestedFilename = this.buildFilename(ps.search_term, idx + 1);
                        this.updatePlaceholderInEditor(idx);
                    } else {
                        this.updatePlaceholderError(idx, 'No photos found');
                    }
                } catch (e) {
                    this.updatePlaceholderError(idx, 'Search failed');
                }
            }
            this.autoFetchingPhotos = false;
            this.syncEditorStateFromEditor();
            this.queueAutoSaveDraft(300);
        },

        updatePlaceholderError(idx, message) {
            const editor = tinymce.get('spin-preview-editor');
            if (!editor || !editor.getBody()) return;
            const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
            if (!placeholder) return;
            const ps = this.photoSuggestions[idx];
            const newHtml = '<div class="photo-placeholder" contenteditable="false" data-idx="' + idx + '" style="border:2px dashed #ef4444;background:#fef2f2;border-radius:8px;padding:12px 16px;margin:16px 0;text-align:center;color:#dc2626;font-size:13px;">'
                + '<span>' + message + ' for: ' + this._escHtml(ps?.search_term || '') + '</span><br>'
                + '<span class="photo-change" style="cursor:pointer;display:inline-block;background:#2563eb;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-top:6px;">Search Manually</span>'
                + '</div>';
            editor.dom.setOuterHTML(placeholder, newHtml);
            this.syncEditorStateFromEditor();
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
            this.syncEditorStateFromEditor();
        },

        _escHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        _slugify(str) {
            return String(str || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 80);
        },

        buildFilename(searchTerm, index) {
            const slug = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 60);
            const now = new Date();
            const dateStr = now.getFullYear().toString() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
            return (this.filenamePattern || 'hexa_{draft_id}_{seo_name}')
                .replace('{draft_id}', this.draftId || '0')
                .replace('{seo_name}', slug(searchTerm))
                .replace('{index}', String(index || 1))
                .replace('{article_slug}', slug(this.articleTitle))
                .replace('{date}', dateStr)
                .replace('{post_id}', '0');
        },

        countWordsFromHtml(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html || '';
            const text = (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
            return text ? text.split(' ').length : 0;
        },

        syncEditorStateFromEditor() {
            const editor = tinymce.get('spin-preview-editor');
            if (!editor) return;
            const content = editor.getContent() || '';
            this.spunContent = content;
            this.editorContent = content;
            this.spunWordCount = this.countWordsFromHtml(content);
            this.extractArticleLinks(content);
        },

        queueAutoSaveDraft(delay) {
            if (this._restoring) return;
            if (this._draftSaveTimer) clearTimeout(this._draftSaveTimer);
            this._draftSaveTimer = setTimeout(() => {
                this._draftSaveTimer = null;
                if (this.savingDraft) {
                    this.queueAutoSaveDraft(300);
                    return;
                }
                this.autoSaveDraft();
            }, delay || 800);
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
            this.syncEditorStateFromEditor();
            this.queueAutoSaveDraft(300);
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
            this.syncEditorStateFromEditor();
            this.queueAutoSaveDraft(300);
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
            this.queueAutoSaveDraft(300);
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

        // ── Step 8: Review & Publish ────────────────────
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
            this.syncEditorStateFromEditor();
            this.preparing = true;
            this.prepareLog = [];
            this.prepareComplete = false;
            this.prepareIntegrityIssues = [];

            // Show master checklist immediately
            const imgCount = (this.editorContent.match(/<img[^>]+src/gi) || []).length;
            this.prepareChecklist = [
                { label: 'Verify author on WordPress', status: 'running', detail: this.publishAuthor || 'default' },
                { label: 'Clean HTML for WordPress', status: 'pending', detail: '' },
                { label: 'Upload ' + imgCount + ' image(s) to WordPress', status: 'pending', detail: '' },
                { label: 'Upload featured image', status: 'pending', detail: this.featuredPhoto ? 'yes' : 'none' },
                { label: 'Create categories', status: 'pending', detail: this.suggestedCategories.length + ' category(s)' },
                { label: 'Create tags', status: 'pending', detail: this.suggestedTags.length + ' tag(s)' },
                { label: 'Integrity check', status: 'pending', detail: '' },
            ];
            this._logPrepare('info', 'Starting WordPress preparation for ' + this.selectedSite.name + '...');
            this._logPrepare('info', 'Site: ' + this.selectedSite.url + ' | Connection: ' + (this.selectedSite.connection_type === 'wptoolkit' ? 'SSH' : 'REST API'));
            if (this.publishAuthor) this._logPrepare('info', 'Author: ' + this.publishAuthor);
            this._logPrepare('info', 'Images: ' + imgCount + ' inner + ' + (this.featuredPhoto ? '1 featured' : 'no featured'));
            this._logPrepare('info', 'Categories: ' + this.suggestedCategories.join(', '));
            this._logPrepare('info', 'Tags: ' + this.suggestedTags.join(', '));

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
                        photo_meta: this.photoSuggestions.filter(p => !p.removed && p.autoPhoto).map((p, i) => ({
                            alt_text: p.alt_text || '',
                            caption: p.caption || '',
                            filename: p.suggestedFilename || this.buildFilename(p.search_term, i + 1),
                        })),
                        featured_url: this.featuredPhoto?.url_large || this.featuredPhoto?.url || null,
                        featured_meta: this.featuredPhoto ? {
                            alt_text: this.featuredAlt || '',
                            caption: this.featuredCaption || '',
                            filename: this.featuredFilename || this.buildFilename(this.featuredImageSearch, 0),
                        } : null,
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

                                // Update master checklist status based on SSE messages
                                const msg = event.message?.toLowerCase() || '';
                                if (msg.includes('author verified') || msg.includes('author not found') || msg.includes('no default author')) {
                                    this.prepareChecklist[0].status = event.type === 'success' ? 'done' : (event.type === 'warning' ? 'skipped' : 'failed');
                                    this.prepareChecklist[0].detail = event.message;
                                }
                                if (msg.includes('html clean') || msg.includes('html valid')) {
                                    this.prepareChecklist[1].status = 'done';
                                    if (this.prepareChecklist[2].status === 'pending') this.prepareChecklist[2].status = 'running';
                                }
                                if (msg.includes('uploading photo') || msg.includes('uploading') && msg.includes('image')) {
                                    this.prepareChecklist[2].status = 'running';
                                }
                                if (msg.includes('featured image uploaded')) {
                                    this.prepareChecklist[3].status = 'done';
                                    this.prepareChecklist[3].detail = event.message;
                                }
                                if (msg.includes('featured image upload failed') || msg.includes('no featured image')) {
                                    this.prepareChecklist[3].status = event.type === 'warning' ? 'skipped' : 'failed';
                                }
                                if (msg.includes('categor') && (event.type === 'success' || event.type === 'info')) {
                                    this.prepareChecklist[4].status = event.type === 'success' ? 'done' : 'running';
                                    if (event.type === 'success') this.prepareChecklist[4].detail = event.message;
                                }
                                if (msg.includes('tag') && (event.type === 'success' || event.type === 'info') && !msg.includes('categor')) {
                                    this.prepareChecklist[5].status = event.type === 'success' ? 'done' : 'running';
                                    if (event.type === 'success') this.prepareChecklist[5].detail = event.message;
                                }
                                if (msg.includes('integrity')) {
                                    this.prepareChecklist[6].status = event.type === 'success' ? 'done' : (event.type === 'warning' ? 'done' : 'failed');
                                    this.prepareChecklist[6].detail = event.message;
                                }

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
                                        this.preparedFeaturedMediaId = event.featured_media_id || null;
                                        this.preparedFeaturedWpUrl = event.featured_wp_url || null;
                                        if (event.wp_images) {
                                            event.wp_images.forEach(img => { this.uploadedImages[img.source_url] = img; });
                                        }
                                        this.prepareIntegrityIssues = event.integrity_issues || [];
                                        // Mark all remaining checklist items as done
                                        this.prepareChecklist.forEach(c => { if (c.status === 'pending' || c.status === 'running') c.status = 'done'; });
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
            this.syncEditorStateFromEditor();
            this.publishing = true;
            this.publishResult = null;
            this.publishError = '';

            // Save local draft only
            if (this.publishAction === 'draft_local') {
                await this.saveDraftNow();
                this.publishResult = { message: 'Saved as local draft.', post_url: null, draft_id: this.draftId };
                this.completeStep(7);
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

            this._logPrepare('step', 'Starting publish to ' + this.selectedSite.name + '...');

            try {
                const resp = await fetch('{{ route('publish.pipeline.publish') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        html: this.preparedHtml || this.editorContent,
                        title: this.articleTitle || 'Untitled',
                        site_id: this.selectedSite.id,
                        category_ids: this.preparedCategoryIds,
                        tag_ids: this.preparedTagIds,
                        featured_media_id: this.preparedFeaturedMediaId || null,
                        status: wpStatus,
                        date: this.publishAction === 'future' ? this.scheduleDate : null,
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
                        article_type: this.currentArticleType || null,
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
                    buffer = lines.pop();

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            try {
                                const event = JSON.parse(line.substring(6));
                                this._logPrepare(event.type, event.message);

                                if (event.type === 'done') {
                                    if (event.success) {
                                        this.publishResult = event;
                                        this.completeStep(7);
                                        this.showNotification('success', event.message);
                                    } else {
                                        this.publishError = event.message;
                                        this.showNotification('error', event.message || 'Publish failed');
                                    }
                                }
                            } catch (parseErr) { /* skip malformed */ }
                        }
                    }
                }
            } catch (e) {
                this._logPrepare('error', 'Connection error: ' + (e.message || 'Request failed'));
                this.publishError = 'Network error during publishing.';
            }
            this.publishing = false;
            this.autoSaveDraft();
        },

        // ── Draft ─────────────────────────────────────────
        async saveDraftNow(silent = false) {
            if (this.savingDraft) return;
            if (this._draftSaveTimer) {
                clearTimeout(this._draftSaveTimer);
                this._draftSaveTimer = null;
            }
            this.savingDraft = true;
            this.syncEditorStateFromEditor();
            try {
                const resp = await fetch('{{ route('publish.pipeline.save-draft') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        title: this.articleTitle || 'Untitled Pipeline Draft',
                        body: this.editorContent,
                        excerpt: this.articleDescription || null,
                        user_id: this.selectedUser?.id || null,
                        site_id: this.selectedSite?.id || null,
                        preset_id: this.selectedPresetId || null,
                        template_id: this.selectedTemplateId || null,
                        article_type: this.currentArticleType || null,
                        ai_model: this.aiModel,
                        author: this.publishAuthor || null,
                        sources: this.sources.map(s => ({ url: s.url, title: s.title })),
                        tags: this.suggestedTags,
                        categories: this.suggestedCategories,
                        photo_suggestions: this.photoSuggestions || null,
                        featured_image_search: this.featuredImageSearch || null,
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.draftId = data.draft_id;
                    if (!silent) this.showNotification('success', data.message);
                } else {
                    if (!silent) this.showNotification('error', data.message);
                }
            } catch (e) {
                if (!silent) this.showNotification('error', 'Failed to save draft');
            }
            this.savingDraft = false;
        },

        autoSaveDraft() {
            if (this.savingDraft) return;
            this.saveDraftNow(true);
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
