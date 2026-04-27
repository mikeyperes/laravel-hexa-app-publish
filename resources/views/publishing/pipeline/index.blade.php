{{-- Publish Article Pipeline — 11-step wizard --}}
@extends('layouts.app')
@section('title', 'Publish Article — #' . $draftId)
@section('header', 'Publish Article — #' . $draftId)

@section('content')
<div class="space-y-4" x-data="publishPipeline()" data-publish-draft-id="{{ $draftId }}"
     @hexa-form-changed.window="
        if ($event.detail.component_id === 'article-preset-form') { $data.template_overrides[$event.detail.field] = $event.detail.value; $data.template_dirty[$event.detail.field] = true; $data.savePipelineState(); }
     ">

    {{-- Session ID + Clear button --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-mono text-gray-400">Article #{{ $draftId }}</p>
        <div class="flex items-center gap-2">
            <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}"
               target="_blank"
               rel="noopener"
               class="text-xs text-blue-600 hover:text-blue-800 px-3 py-1.5 border border-blue-200 rounded-lg hover:bg-blue-50 inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-2M16 3h5m0 0v5m0-5L10 14"/></svg>
                Open Isolated Draft In New Tab
            </a>
        <div x-show="completedSteps.length > 0" x-cloak>
            <button @click="clearPipeline()" class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 border border-red-200 rounded-lg hover:bg-red-50 inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Clear &amp; Start Over
            </button>
        </div>
        </div>
    </div>

    <div x-show="draftSessionConflict" x-cloak class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="text-sm font-semibold text-amber-900">Draft locked by another active tab</p>
                <p class="text-sm text-amber-800" x-text="draftSessionConflict?.message || 'Autosave and pipeline-state writes are paused in this tab to avoid overwriting the active draft session.'"></p>
                <p class="text-xs text-amber-700" x-show="draftSessionConflict?.last_seen_at || draftSessionConflict?.tab_id" x-text="'Active tab: ' + (draftSessionConflict?.tab_id || 'unknown') + (draftSessionConflict?.last_seen_at ? ' • last seen ' + draftSessionConflict.last_seen_at : '')"></p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}"
                   target="_blank"
                   rel="noopener"
                   class="text-xs text-amber-900 hover:text-amber-950 px-3 py-1.5 border border-amber-300 rounded-lg hover:bg-amber-100 inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-2M16 3h5m0 0v5m0-5L10 14"/></svg>
                    Open Isolated Draft
                </a>
                <button @click="_clearDraftSessionConflict()"
                        type="button"
                        class="text-xs text-amber-800 hover:text-amber-900 px-3 py-1.5 border border-amber-300 rounded-lg hover:bg-amber-100">
                    Dismiss
                </button>
            </div>
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
                 @hexa-search-selected.window="if ($event.detail.component_id === 'pipeline-user' && !_restoring) selectUser($event.detail.item)"
                 @hexa-search-cleared.window="if ($event.detail.component_id === 'pipeline-user') clearUser()">
                @php
                    $pipelineSelectedUser = isset($draftUser) && $draftUser ? ['id' => $draftUser->id, 'name' => $draftUser->name] : null;
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
                <span x-show="selectedSite" x-cloak class="text-sm text-green-600" x-text="selectedSite?.name"></span>
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
                    <template x-for="s in (selectedUser ? sites.filter(st => !st.user_id || st.user_id === selectedUser.id) : sites)" :key="s.id">
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
                    <select x-show="!templatesLoading" x-model="selectedTemplateId" @change="selectTemplate(); editingTemplate = false" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- No preset --</option>
                        <template x-for="t in templates.filter(t => !currentArticleType || !t.article_type || t.article_type === currentArticleType)" :key="t.id">
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
                        :fields-json="json_encode($articlePresetFields)"
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
                            <p class="text-xs text-gray-400" x-text="currentArticleType === 'pr-full-feature' ? 'Build a polished editorial feature around one or more Notion subjects.' : 'Build a topic-first expert article and weave the Notion subject in as the authority voice.'"></p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 space-y-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900">Article Direction</h5>
                                <p class="text-xs text-gray-600 mt-1" x-show="currentArticleType === 'pr-full-feature'">Select the main subject, define the editorial angle, and explain what the writer must emphasize. The article should market the client through credible feature writing, not overt promotion.</p>
                                <p class="text-xs text-gray-600 mt-1" x-show="currentArticleType === 'expert-article'">Define the topic, the subject's position, and whether the subject should lead visually or appear more subtly. The article should stay topic-first while using the client as the expert authority.</p>
                            </div>
                            <div class="text-[11px] text-gray-500 bg-white border border-blue-200 rounded-lg px-3 py-2 max-w-xs">
                                <strong class="text-gray-800 block mb-1">Writer guidance</strong>
                                Include what to focus on, whose view matters most, which related records matter, how promotional the tone should feel, and what quotes or positioning must show up.
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Main Subject</label>
                                <select x-model="prArticle.main_subject_id" @change="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                                    <option value="">Choose main subject…</option>
                                    <template x-for="profile in selectedPrProfiles" :key="'subject-' + profile.id">
                                        <option :value="profile.id" x-text="profile.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Promotional Level</label>
                                <select x-model="prArticle.promotional_level" @change="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                                    <option value="editorial-subtle">Editorial / subtle marketing</option>
                                    <option value="balanced-feature">Balanced feature</option>
                                    <option value="high-visibility">High visibility, still journalistic</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Tone</label>
                                <select x-model="prArticle.tone" @change="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                                    <option value="journalistic">Journalistic</option>
                                    <option value="authoritative">Authoritative</option>
                                    <option value="expert-analytical">Expert / analytical</option>
                                    <option value="warm-editorial">Warm editorial</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Quote Plan</label>
                                <select x-model="prArticle.quote_count" @change="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                                    <template x-if="currentArticleType === 'pr-full-feature'">
                                        <optgroup label="PR Full Feature">
                                            <option value="1">1 quote</option>
                                            <option value="2">2 quotes</option>
                                            <option value="3">3 quotes</option>
                                        </optgroup>
                                    </template>
                                    <template x-if="currentArticleType === 'expert-article'">
                                        <optgroup label="Expert Article">
                                            <option value="3">3 quotes</option>
                                            <option value="4">4 quotes</option>
                                            <option value="5">5 quotes</option>
                                        </optgroup>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Article Focus Instructions</label>
                                <textarea x-model="prArticle.focus_instructions" @change="savePipelineState()" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 resize-y" placeholder="Explain the main angle, what the writer should emphasize, what to mention or avoid, which subject matters most, and how the article should frame the story."></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Quote Guidance</label>
                                <textarea x-model="prArticle.quote_guidance" @change="savePipelineState()" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 resize-y" placeholder="Add any guidance for fabricated or supplied quotes, viewpoints, or lines the subject should support in the article."></textarea>
                            </div>
                        </div>

                        <div x-show="currentArticleType === 'pr-full-feature'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                <input type="checkbox" class="rounded border-gray-300 text-blue-600" x-model="prArticle.include_subject_name_in_title" @change="savePipelineState()">
                                <span class="text-sm text-gray-700">Include the main subject name in the headline.</span>
                            </label>
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600">
                                Real subject photos selected below will be used for the featured image and inline images when available.
                            </div>
                        </div>

                        <div x-show="currentArticleType === 'expert-article'" x-cloak class="space-y-4">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Subject Position On The Topic</label>
                                    <textarea x-model="prArticle.subject_position" @change="savePipelineState()" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 resize-y" placeholder="Explain the subject's thesis, position, or interpretation so the article aligns with their view."></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Photo Mode</label>
                                    <select x-model="prArticle.feature_photo_mode" @change="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                                        <option value="featured_and_inline">Use the subject as featured image + inline photos</option>
                                        <option value="inline_only">Inline subject photos only</option>
                                    </select>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h6 class="text-sm font-semibold text-gray-800">Topic Context</h6>
                                        <p class="text-xs text-gray-500">You can import a specific live article for context, or provide topic keywords if you want the AI to build the expert article around a broader issue.</p>
                                    </div>
                                    <select x-model="prArticle.expert_source_mode" @change="savePipelineState()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="keywords">Keywords / topic</option>
                                        <option value="url">Specific article URL</option>
                                        <option value="none">No external topic source</option>
                                    </select>
                                </div>

                                <div x-show="prArticle.expert_source_mode === 'keywords'" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Topic Keywords</label>
                                    <textarea x-model="prArticle.expert_keywords" @change="savePipelineState()" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 resize-y" placeholder="Add the keywords, issue, or thesis the article should focus on. Example: AI chip export controls, Nvidia, semiconductor supply chain, geopolitical risk"></textarea>
                                </div>

                                <div x-show="prArticle.expert_source_mode === 'url'" x-cloak class="space-y-3">
                                    <div class="flex flex-col lg:flex-row gap-3">
                                        <input type="url" x-model="prArticle.expert_context_url" @change="savePipelineState()" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="Paste a live article URL to import as the issue context">
                                        <button @click="importPrArticleContextUrl()" type="button" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50" :disabled="prArticleContextImporting || !prArticle.expert_context_url">
                                            <svg x-show="prArticleContextImporting" x-cloak class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span x-text="prArticleContextImporting ? 'Importing…' : 'Import Article Context'"></span>
                                        </button>
                                    </div>
                                    <div x-show="prArticle.expert_context_extracted?.title || prArticle.expert_context_extracted?.text" x-cloak class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-3">
                                        <p class="text-sm font-semibold text-gray-900" x-text="prArticle.expert_context_extracted?.title || 'Imported topic context'"></p>
                                        <p class="text-xs text-gray-500 mt-1" x-text="(prArticle.expert_context_extracted?.word_count || 0) + ' words imported from the context article'"></p>
                                        <p x-show="prArticle.expert_context_extracted?.excerpt" x-cloak class="text-sm text-gray-700 mt-2" x-text="prArticle.expert_context_extracted?.excerpt"></p>
                                    </div>
                                </div>
                            </div>
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
                                            <button @click="removePrProfile(pidx, profile.id)" class="text-red-400 hover:text-red-600" title="Remove">
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

                    <button @click="continuePrArticleStep3()" :disabled="selectedPrProfiles.length === 0"
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
                                <p>The selected AI model searches the web in real-time to find recent news articles on your topic. Multiple sources on the same topic are intentionally selected so the spinner has enough material for a stronger rewrite.</p>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" x-model="aiSearchTopic" @keydown.enter="aiSearchArticles()" placeholder="e.g. cryptocurrency regulations 2026" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <button @click="aiSearchArticles()" :disabled="aiSearching || !aiSearchTopic.trim()" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2">
                            <svg x-show="aiSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="aiSearching ? 'Searching...' : 'Find Articles'"></span>
                        </button>
                    </div>
                    <div class="mt-2">
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-1">Search Agent</label>
                        <select x-model="aiSearchModel" class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                            @foreach(($aiSearchGroups ?? $aiModelGroups ?? []) as $company => $models)
                            <optgroup label="{{ $company }}">
                                @foreach($models as $model)
                                    <option value="{{ $model['id'] }}">{{ $model['label'] }}</option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                    </div>

                    {{-- Recent Searches --}}
                    <div x-show="aiSearchHistory.length > 0" x-cloak class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="text-[10px] text-gray-400 uppercase tracking-wide">Recent:</span>
                        <template x-for="(term, hi) in aiSearchHistory.slice(0, 8)" :key="hi">
                            <button type="button" @click="aiSearchTopic = term" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 hover:bg-purple-100 hover:text-purple-700 transition-colors truncate max-w-[200px]" x-text="term"></button>
                        </template>
                        <button type="button" @click="aiSearchHistory = []; localStorage.removeItem('hws_search_history')" class="text-[10px] text-gray-400 hover:text-red-500 ml-1">clear</button>
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
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 mb-4">
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Method</label>
                        <select x-model="extractMethod" class="border border-gray-300 rounded px-2 py-1 text-xs">
                            <option value="auto">Auto</option>
                            <option value="readability">Readability</option>
                            <option value="structured">Structured</option>
                            <option value="heuristic">Heuristic</option>
                            <option value="css">CSS Selector</option>
                            <option value="regex">Regex</option>
                            <option value="jina">Jina Reader</option>
                            <option value="claude">Claude AI</option>
                            <option value="gpt">GPT</option>
                            <option value="grok">Grok</option>
                            <option value="gemini">Gemini</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">User Agent</label>
                        <select x-model="checkUserAgent" class="border border-gray-300 rounded px-2 py-1 text-xs">
                            <option value="chrome">Chrome Desktop</option>
                            <option value="firefox">Firefox</option>
                            <option value="safari">Safari</option>
                            <option value="mobile">Mobile</option>
                            <option value="googlebot">Googlebot</option>
                            <option value="bingbot">Bingbot</option>
                            <option value="bot">HWS Bot</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Retries</label>
                        <select x-model="extractRetries" class="border border-gray-300 rounded px-2 py-1 text-xs w-14">
                            <option value="0">0</option>
                            <option value="1" selected>1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Timeout</label>
                        <select x-model="extractTimeout" class="border border-gray-300 rounded px-2 py-1 text-xs w-16">
                            <option value="10">10s</option>
                            <option value="20" selected>20s</option>
                            <option value="30">30s</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Min Words</label>
                        <input type="number" x-model="extractMinWords" class="border border-gray-300 rounded px-2 py-1 text-xs w-16" min="10" max="1000">
                    </div>
                    <label class="flex items-center gap-1 text-xs text-gray-500 pb-0.5">
                        <input type="checkbox" x-model="extractAutoFallback" class="rounded border-gray-300 text-blue-600 w-3.5 h-3.5">
                        Auto-fallback
                    </label>
                    <button @click.stop="checkAllSources()" :disabled="checking" class="bg-blue-600 text-white px-4 py-1 rounded text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-1.5 ml-auto">
                        <svg x-show="checking" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
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
                        <span class="text-white font-medium" x-text="extractMethod === 'auto' ? 'Auto (Readability + Structured + Heuristic + Jina)' : extractMethod === 'readability' ? 'Readability' : extractMethod === 'structured' ? 'Structured Data' : extractMethod === 'heuristic' ? 'DOM Heuristic' : extractMethod === 'css' ? 'CSS Selector' : extractMethod === 'regex' ? 'Regex' : extractMethod === 'jina' ? 'Jina Reader' : extractMethod === 'claude' ? 'Claude AI' : extractMethod === 'gpt' ? 'GPT' : extractMethod === 'grok' ? 'Grok' : extractMethod === 'gemini' ? 'Gemini' : extractMethod"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">User Agent</span>
                        <span class="text-white font-medium" x-text="checkUserAgent === 'chrome' ? 'Chrome Desktop' : checkUserAgent === 'firefox' ? 'Firefox Desktop' : checkUserAgent === 'safari' ? 'Safari macOS' : checkUserAgent === 'mobile' ? 'Mobile Chrome' : checkUserAgent === 'googlebot' ? 'Googlebot' : checkUserAgent === 'bingbot' ? 'Bingbot' : 'HWS Bot'"></span>
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
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <button x-show="!result.success" @click.stop="markFailedSource(idx)" :disabled="_markingBrokenIdx === idx" class="text-xs text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 px-2.5 py-1 rounded inline-flex items-center gap-1">
                                    <svg x-show="_markingBrokenIdx === idx" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Mark Broken
                                </button>
                                <button x-show="!result.success" @click.stop="searchFailedSource(idx)" class="text-xs text-blue-700 hover:text-blue-900 px-2.5 py-1 bg-blue-50 hover:bg-blue-100 rounded">Search Title</button>
                                <button x-show="!result.success" @click.stop="markAndSearchFailedSource(idx)" :disabled="_markingBrokenIdx === idx" class="text-xs text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 px-2.5 py-1 rounded inline-flex items-center gap-1">
                                    <svg x-show="_markingBrokenIdx === idx" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Broken + Search
                                </button>
                                <button x-show="!result.success" @click.stop="removeSource(idx)" class="text-xs text-gray-500 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50">Remove</button>
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
                <span x-show="Number(spunWordCount || 0) > 0" x-cloak class="text-sm text-green-600" x-text="spunWordCount + ' words'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(5) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(5)" x-cloak x-collapse class="px-4 pb-4">

            {{-- Article Preset moved to Step 2 --}}
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">AI Model</label>
                    <select x-model="aiModel" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach(($aiModelGroups ?? []) as $company => $models)
                        <optgroup label="{{ $company }}">
                            @foreach($models as $model)
                                <option value="{{ $model['id'] }}">{{ $model['label'] }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                </div>
                <button @click="spinArticle()" :disabled="spinning" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2">
                    <svg x-show="spinning" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="spinning ? (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Polishing...' : 'Spinning...') : (_hasSpunThisSession ? (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Re-polish' : 'Re-spin') : (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Polish Content' : 'Spin Article'))"></span>
                </button>
                <p x-show="_hasSpunThisSession && !spinning" x-cloak class="text-sm text-green-600 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Article spun successfully<span x-show="Number(spunWordCount || 0) > 0"> — <span x-text="spunWordCount + ' words'"></span></span>
                </p>
            </div>

            {{-- Custom prompt input — live-updates resolved prompt --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Custom Instructions <span class="text-gray-400">(takes precedence over template/preset)</span></label>
                <textarea x-model.debounce.300ms="customPrompt" @input.debounce.300ms="invalidatePromptPreview('custom_prompt', { fetch: true })" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Write in first person, focus on the financial impact, include expert quotes..."></textarea>
            </div>

            {{-- Web research toggle --}}
            <div class="mb-4">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="spinWebResearch" @change="invalidatePromptPreview('web_research_toggle', { fetch: true })" class="rounded border-gray-300 text-purple-600">
                    <span class="text-sm text-gray-700">Search online for additional supporting points</span>
                </label>
                <p x-show="spinWebResearch" x-cloak class="text-xs text-gray-400 mt-1 ml-6">The AI will search the web for real-time data, statistics, and expert opinions to strengthen the article.</p>
                <div x-show="spinWebResearch" x-cloak class="mt-2 ml-6">
                    <label class="block text-xs text-gray-500 mb-1">Supporting URL Type</label>
                    <select x-model="supportingUrlType" @change="invalidatePromptPreview('supporting_url_type', { fetch: true })" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
                        @foreach(config('hws-publish.supporting_url_types', []) as $optionKey => $option)
                            <option value="{{ $optionKey }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1" x-text="supportingUrlTypeDescription()"></p>
                </div>
            </div>

            {{-- Resolved Prompt — always visible, live-updating --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-500 font-medium">Resolved Prompt</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="refreshPromptPreview({ force: true, reason: 'manual_button' })" :disabled="promptLoading" class="text-[11px] text-blue-500 hover:text-blue-700 disabled:opacity-50">Refresh</button>
                        <span x-show="promptLoading" x-cloak class="text-xs text-blue-400 inline-flex items-center gap-1">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Updating...
                        </span>
                    </div>
                </div>
                <div class="bg-gray-900 text-gray-300 rounded-xl p-4 text-xs font-mono overflow-y-auto break-words whitespace-pre-wrap" style="max-height:400px;">
                    <pre x-show="resolvedPrompt" class="text-green-300 whitespace-pre-wrap break-words" x-text="resolvedPrompt"></pre>
                    <p x-show="!resolvedPrompt && promptLoading" x-cloak class="text-blue-400">Loading prompt...</p>
                    <p x-show="!resolvedPrompt && !promptLoading && promptPreviewDirty" x-cloak class="text-gray-500">Prompt preview is deferred until AI &amp; Spin is active or you refresh it manually.</p>
                    <p x-show="!resolvedPrompt && !promptLoading && !promptPreviewDirty" x-cloak class="text-gray-500">Prompt will load when a template or preset is selected.</p>
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

@include('app-publish::publishing.pipeline.partials.master-activity-log')

@include('app-publish::publishing.pipeline.partials.overlays')

@include('app-publish::partials.site-connection-mixin')
@include('app-publish::partials.preset-fields-mixin')
@include('app-publish::publishing.pipeline.partials.press-release-workflow-script')
@php
    $pipelinePayloadSanitized = json_decode(json_encode($pipelinePayload ?? []), true) ?: [];
    if (isset($pipelinePayloadSanitized['selectedUser']) && is_array($pipelinePayloadSanitized['selectedUser'])) {
        unset($pipelinePayloadSanitized['selectedUser']['email']);
    }
    $pipelineDraftState = json_decode(json_encode($draftState ?? []), true) ?: [];
    if (isset($pipelineDraftState['selectedUser']) && is_array($pipelineDraftState['selectedUser'])) {
        unset($pipelineDraftState['selectedUser']['email']);
    }
@endphp
<script>
function publishPipeline() {
    return {
        ...pressReleaseWorkflowMixin({
            workflowDefinitions: @json($workflowDefinitions ?? []),
            pipelinePayload: @json($pipelinePayloadSanitized ?? []),
            pressReleaseDefaultState: @json($pressReleaseDefaultState ?? []),
        }),
        prArticleDefaultState: @json($prArticleDefaultState ?? []),
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

        // Step 1 — User (preloaded from draft if user_id is set so Step 2 unlocks without a manual re-select)
        selectedUser: @json(isset($draftUser) && $draftUser ? ['id' => $draftUser->id, 'name' => $draftUser->name] : null),

        // Step 2 — Preset + Website
        presets: [],
        initialUserPresets: @json($initialUserPresets ?? []),
        initialUserPresetUserId: @json(isset($draftUser) && $draftUser ? (string) $draftUser->id : ''),
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
        prArticle: @json($prArticleDefaultState ?? []),
        prArticleContextImporting: false,
        selectedPrProfiles: [],
        prSubjectData: {},

        // Step 3 — Website
        sites: @json($sites ?? []),
        prSourceSites: @json($prSourceSites ?? []),
        draftState: @json($pipelineDraftState ?? []),
        latestCompletedPrepareHtml: @json($latestCompletedPrepareHtml ?? ''),
        selectedSiteId: '',
        selectedSite: null,
        initialUserTemplates: @json($initialUserTemplates ?? []),
        initialUserTemplateUserId: @json(isset($draftUser) && $draftUser ? (string) $draftUser->id : ''),

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
        aiSearchHistory: JSON.parse(localStorage.getItem('hws_search_history') || '[]'),
        aiSearchOptionLabels: @json(($aiSearchOptionLabels ?? [])),
        aiSearchModel: @json(($pipelineDefaults['search_model'] ?? null)),
        aiSearchCount: 4,
        aiSearching: false,
        aiSearchResults: [],
        aiSearchError: '',
        aiHasSearched: false,
        aiLog: [],
        _markingBrokenIdx: null,
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
        aiModel: @json(($pipelineDefaults['spin_model'] ?? null)),
        customPrompt: '',
        supportingUrlType: 'matching_content_type',

        // Step 7 — Spin
        spinning: false,
        _hasSpunThisSession: false,
        spinWebResearch: true,
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
        customCategoryInput: '',
        customTagInput: '',
        metadataLoading: false,
        suggestedUrls: [],
        checkingAllArticleLinks: false,
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
        _inlinePhotoAutoHydrationTimer: null,
        _lastInlinePhotoHydrationSignature: '',
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
        lastNonEmptyDraftBody: '',
        preparing: false,
        prepareOperationId: null,
        prepareOperationStatus: '',
        prepareOperationTransport: '',
        prepareOperationClientTrace: '',
        prepareOperationLastSequence: 0,
        prepareOperationPollTimer: null,
        prepareOperationStreamController: null,
        prepareOperationStreamReconnectTimer: null,
        _streamingPrepareOperation: false,
        _pollingPrepareOperation: false,
        prepareChecklist: [],
        prepareLog: [],
        prepareTraceId: '',
        prepareLastEventAt: 0,
        prepareLastStage: '',
        prepareLastMessage: '',
        prepareLastErrorMessage: '',
        prepareWatchdogTimer: null,
        _lastPrepareWatchdogNoticeAt: 0,
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
        existingWpPostId: null,
        existingWpStatus: '',
        existingWpPostUrl: '',
        existingWpAdminUrl: '',
        scheduleDate: '',
        publishing: false,
        publishOperationId: null,
        publishOperationStatus: '',
        publishOperationTransport: '',
        publishOperationClientTrace: '',
        publishOperationLastSequence: 0,
        publishOperationPollTimer: null,
        publishOperationStreamController: null,
        publishOperationStreamReconnectTimer: null,
        _streamingPublishOperation: false,
        _pollingPublishOperation: false,
        publishResult: null,
        publishError: '',

        // Draft
        draftId: {{ $draftId }},
        uploadedImages: {},
        orphanedMedia: [],
        _previousUploadedImages: null,
        featuredImageSearch: '',
        featuredPhoto: null,
        featuredResults: [],
        featuredSearchPending: false,
        featuredSearching: false,
        featuredLoadError: '',
        featuredThumbLoading: false,
        featuredThumbError: '',
        _featuredAutoHydrationTimer: null,
        _lastFeaturedAutoHydrationSignature: '',
        featuredUrlImport: '',
        pipelineOperationLiveStreamEnabled: @json(!app()->runningUnitTests() && !(app()->environment('local') && php_sapi_name() === 'cli-server')),
        innerPhotoUrlImport: '',
        featuredAlt: '',
        featuredCaption: '',
        featuredFilename: '',
        featuredRefreshingMeta: false,
        featuredMetaGenerator: '',
        resolvedPrompt: '',
        promptLog: [],
        promptLogOpen: false,
        promptLoading: false,
        promptPreviewDirty: true,
        articleDescription: '',
        spinLog: [],
        photoSuggestionsPending: false,

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
        pipelineDebugEnabled: new URLSearchParams(window.location.search).get('debug') === '1'
            || localStorage.getItem('publishPipelineDebug') === 'true',
        masterActivityLog: [],
        masterActivityLogOpen: false,
        masterActivityAutoScroll: true,
        activityRunHistory: [],
        activityRunHistoryLoading: false,
        selectedActivityRunTrace: '',
        activityRunPreviewEntries: [],
        crossDraftActivityRuns: [],
        publishTraceId: '',
        _masterActivitySeq: 0,
        _clientSessionTraceId: '',
        _tabInstanceId: '',
        _lastLocalPipelineStateSignature: '',
        _lastServerPipelineStateSignature: '',
        _pendingServerPipelineStateSave: false,
        _lastDraftPayloadSignature: '',
        _lastPromptPreviewSignature: '',
        _pendingDraftSave: false,
        _pendingDraftSilent: true,
        _pipelineStateTimer: null,
        _masterActivityPersistTimer: null,
        _serverActivitySyncTimer: null,
        _syncingMasterActivityServer: false,
        _pendingMasterActivitySync: false,
        _masterActivitySyncBatchSize: 200,
        _serverActivityLogLoading: false,
        _serverActivityLogRestored: false,
        _activityRunHistoryRestored: false,
        _crossDraftActivityRunsRestored: false,
        _thumbReconcileTimers: [],
        _bootstrappedPresetUserId: '',
        _bootstrappedTemplateUserId: '',
        _suspendPipelineStateSave: false,
        _suspendDraftAutoSave: false,
        _pendingPostSuspendPipelineStateSave: false,
        _pendingPostSuspendDraftSave: false,
        draftSessionConflict: null,
        _draftSessionConflictActive: false,
        _spinEditorConfigured: false,
        _spinEditorConfiguring: false,
        _pendingSpinEditorContent: '',
        _pipelineOperationsRestored: false,
        _pageUnloading: false,

        // Flag to suppress step auto-navigation during state restore
        _restoring: false,

        // CSRF token
        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        requestHeaders(extra = {}) {
            if (typeof window.hexaRequestHeaders === 'function') {
                return window.hexaRequestHeaders(extra);
            }

            return {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {}),
                ...extra,
            };
        },

        get pipelineStateKey() {
            return 'publishPipelineState:' + String(this.draftId || 'new') + ':' + String(this._tabInstanceId || 'tab');
        },

        get legacyPipelineStateKey() {
            return 'publishPipelineState:' + String(this.draftId || 'new');
        },

        @include('app-publish::publishing.pipeline.partials.activity-log-script')
        @include('app-publish::publishing.pipeline.partials.state-persistence-script')
        @include('app-publish::publishing.pipeline.partials.workflow-setup-script')
        @include('app-publish::publishing.pipeline.partials.spin-workflow-script')
        @include('app-publish::publishing.pipeline.partials.draft-notification-script')
    };
}
</script>
@endsection
