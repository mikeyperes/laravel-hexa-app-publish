{{-- Publish Article Pipeline — 11-step wizard --}}
@extends('layouts.app')
@section('title', 'Publish Article — #' . $draftId)
@section('header', 'Publish Article — #' . $draftId)

@section('content')
<div class="max-w-6xl mx-auto space-y-4" x-data="publishPipeline()"
     @hexa-form-changed.window="
        if ($event.detail.component_id === 'article-preset-form') { template_overrides[$event.detail.field] = $event.detail.value; template_dirty[$event.detail.field] = true; }
        if ($event.detail.component_id === 'wp-preset-form') { preset_overrides[$event.detail.field] = $event.detail.value; preset_dirty[$event.detail.field] = true; }
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
         Step 2: Website & Template
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 2, 'opacity-50': !isStepAccessible(2) }">
        <button @click="toggleStep(2)" :disabled="!isStepAccessible(2)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(2) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(2)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(2)"><span>2</span></template>
                </span>
                <span class="font-semibold text-gray-800">Website & Template</span>
                <span x-show="selectedSite && siteConn.status === true" x-cloak class="text-sm text-green-600" x-text="selectedSite?.name"></span>
                <span x-show="selectedSite && siteConn.status === null && siteConn.testing" x-cloak class="text-sm text-blue-500 inline-flex items-center gap-1">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Connecting...
                </span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(2) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(2)" x-cloak x-collapse class="px-4 pb-4 space-y-4">

            {{-- WP Template (auto-loaded, edit to change) --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="text-sm font-semibold text-gray-700">WordPress Template</h5>
                    <div class="flex items-center gap-2">
                        <a x-show="selectedPreset" x-cloak :href="'/publish/presets/' + selectedPresetId + '/edit'" target="_blank" class="text-xs text-gray-400 hover:text-blue-600 inline-flex items-center gap-0.5">Edit on preset page <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        <button @click="editingPreset = !editingPreset" class="text-xs text-blue-600 hover:text-blue-800" x-text="editingPreset ? 'Cancel' : 'Edit'"></button>
                    </div>
                </div>
                {{-- Loading spinner --}}
                <div x-show="presetsLoading" x-cloak class="flex items-center gap-2 text-sm text-blue-500 py-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading WordPress presets...
                </div>
                {{-- Display current preset --}}
                <div x-show="!presetsLoading && !editingPreset && selectedPreset" class="text-sm space-y-1">
                    <p class="font-medium text-gray-800" x-text="selectedPreset?.name || 'None'"></p>
                    <p class="text-xs text-gray-500"><span class="text-gray-400">Tone:</span> <span x-text="selectedPreset?.tone || '—'"></span> &middot; <span class="text-gray-400">Format:</span> <span x-text="selectedPreset?.article_format || '—'"></span></p>
                </div>
                <div x-show="!presetsLoading && !editingPreset && !selectedPreset" class="text-xs text-gray-400">No template selected — using defaults.</div>
                {{-- Edit mode --}}
                <div x-show="editingPreset" x-cloak class="mt-2">
                    <div x-show="presetsLoading" class="flex items-center gap-2 text-sm text-gray-500 py-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading...
                    </div>
                    <select x-show="!presetsLoading" x-model="selectedPresetId" @change="selectPreset(); editingPreset = false; refreshPromptPreview()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- No preset --</option>
                        <template x-for="p in presets" :key="p.id">
                            <option :value="p.id" x-text="p.name"></option>
                        </template>
                    </select>
                </div>
                <div x-show="selectedPreset" x-cloak>
                    <x-hexa-reactive-form
                        :fields-json="json_encode($wpPresetForm->toClientPayload('pipeline'))"
                        values="{}"
                        id="wp-preset-form"
                        label="WordPress Preset Settings"
                        />
                </div>
            </div>

            {{-- Article Preset (beside WordPress Template) --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="text-sm font-semibold text-gray-700">Article Preset</h5>
                    <div class="flex items-center gap-2">
                        <a x-show="selectedTemplate" x-cloak :href="'/publish/article-presets/' + selectedTemplateId + '/edit'" target="_blank" class="text-xs text-gray-400 hover:text-blue-600 inline-flex items-center gap-0.5">Edit on preset page <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        <button @click="editingTemplate = !editingTemplate" class="text-xs text-blue-600 hover:text-blue-800" x-text="editingTemplate ? 'Cancel' : 'Edit'"></button>
                    </div>
                </div>
                {{-- Loading spinner --}}
                <div x-show="templatesLoading" x-cloak class="flex items-center gap-2 text-sm text-blue-500 py-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading article presets...
                </div>
                <div x-show="!templatesLoading && !editingTemplate && selectedTemplate" class="text-sm space-y-1">
                    <p class="font-medium text-gray-800" x-text="selectedTemplate?.name || 'None'"></p>
                    <p class="text-xs text-gray-500"><span class="text-gray-400">Engine:</span> <span x-text="selectedTemplate?.ai_engine || '—'"></span> &middot; <span class="text-gray-400">Tone:</span> <span x-text="Array.isArray(selectedTemplate?.tone) ? selectedTemplate.tone.join(', ') : (selectedTemplate?.tone || '—')"></span> &middot; <span class="text-gray-400">Words:</span> <span x-text="(selectedTemplate?.word_count_min || '—') + '-' + (selectedTemplate?.word_count_max || '—')"></span></p>
                </div>
                <div x-show="!templatesLoading && !editingTemplate && !selectedTemplate" class="text-xs text-gray-400">No article preset selected — using defaults.</div>
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
                <div x-show="selectedTemplate" x-cloak>
                    <x-hexa-reactive-form
                        :fields-json="json_encode($articlePresetForm->toClientPayload('pipeline'))"
                        values="{}"
                        id="article-preset-form"
                        label="Article Preset Settings"
                        />
                </div>
            </div>

            {{-- Website selection --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">WordPress Site</label>
                <select x-model="selectedSiteId" @change="selectSite()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                    <option value="">-- Select a site --</option>
                    <template x-for="s in sites" :key="s.id">
                        <option :value="String(s.id)" :selected="String(s.id) === selectedSiteId" x-text="s.name + ' (' + s.url + ')'"></option>
                    </template>
                </select>
            </div>

            {{-- Connection status --}}
            <div x-show="selectedSite" x-cloak>
                <div class="flex items-center gap-2 rounded-lg px-3 py-2" :class="siteConn.status === true ? 'bg-green-50' : (siteConn.status === false ? 'bg-red-50' : 'bg-gray-50')">
                    <template x-if="siteConn.testing"><svg class="w-4 h-4 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    <template x-if="!siteConn.testing && siteConn.status === true"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                    <template x-if="!siteConn.testing && siteConn.status === false"><svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                    <template x-if="!siteConn.testing && siteConn.status === null"><svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                    <span class="text-sm" :class="siteConn.status === true ? 'text-green-800' : (siteConn.status === false ? 'text-red-800' : 'text-gray-600')" x-text="selectedSite ? selectedSite.name + ' — ' + selectedSite.url : ''"></span>
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
                <span class="font-semibold text-gray-800" x-text="isGenerateMode ? 'Generate Content' : 'Find Articles'"></span>
                <span x-show="sources.length > 0" x-cloak class="text-sm text-green-600" x-text="sources.length + ' source(s)'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(3) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(3)" x-cloak x-collapse class="px-4 pb-4">

            {{-- ═══ Generate Content mode ═══ --}}
            <div x-show="isGenerateMode" x-cloak>

                {{-- Press Release --}}
                <div x-show="currentArticleType === 'press-release'" class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-gray-800">Press Release</h4>
                            <p class="text-xs text-gray-400">Generate a press release article from provided details</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-500">
                        <p class="font-medium text-gray-600 mb-2">Workflow — pending implementation</p>
                        <ul class="list-disc list-inside space-y-1 text-xs text-gray-400">
                            <li>Company/organization name and details</li>
                            <li>Press release subject and key points</li>
                            <li>Quotes and spokesperson information</li>
                            <li>Contact information and boilerplate</li>
                            <li>AI generates formatted press release</li>
                        </ul>
                    </div>
                    <button @click="completeStep(3); completeStep(4); openStep(5)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to AI & Spin &rarr;</button>
                </div>

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
                            <p class="text-xs text-gray-400">Generate an authoritative article from expert input</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-500">
                        <p class="font-medium text-gray-600 mb-2">Workflow — pending implementation</p>
                        <ul class="list-disc list-inside space-y-1 text-xs text-gray-400">
                            <li>Expert/author profile and credentials</li>
                            <li>Topic expertise and key insights</li>
                            <li>Supporting data and references</li>
                            <li>Desired angle and audience</li>
                            <li>AI generates expert-level feature article</li>
                        </ul>
                    </div>
                    <button @click="completeStep(3); completeStep(4); openStep(5)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to AI & Spin &rarr;</button>
                </div>
            </div>

            {{-- Find Articles mode (for editorial, opinion, news-report, local-news) --}}
            <div x-show="!isGenerateMode">
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
                        <button @click="selectTrendingCategory('')" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsTrendingSelected && !newsCategory ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">All</button>
                        @foreach($newsCategories as $cat)
                        <button @click="selectTrendingCategory('{{ $cat }}')" :disabled="newsSearching" class="px-3 py-1 rounded-full text-xs font-medium disabled:opacity-50" :class="newsTrendingSelected && newsCategory === '{{ $cat }}' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">{{ ucfirst($cat) }}</button>
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
                            <div class="border rounded-lg p-3" :class="sources.some(s => s.url === article.url) ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-gray-200 hover:bg-gray-50'">
                                <div class="flex items-start gap-3">
                                    <img x-show="article.image" x-cloak :src="article.image" :alt="article.title" loading="lazy" class="rounded object-cover flex-shrink-0 bg-gray-100" style="width:150px;height:100px;" onerror="this.style.display='none'">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-base font-semibold text-gray-900 break-words" x-text="article.title"></p>
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 4, 'opacity-50': !isStepAccessible(4) }">
        <button @click="toggleStep(4)" :disabled="!isStepAccessible(4)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(4) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(4)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(4)"><span>4</span></template>
                </span>
                <span class="font-semibold text-gray-800">Fetch Articles from Source</span>
                <span x-show="checkResults.length > 0" x-cloak class="text-sm" :class="checkPassCount === sources.length ? 'text-green-600' : 'text-yellow-600'"
                      x-text="checkPassCount + '/' + sources.length + ' extracted'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(4) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(4)" x-cloak x-collapse class="px-4 pb-4">
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
                                <p x-show="!result.success" class="text-sm text-red-600 font-medium mt-1" x-text="'Extraction failed — ' + (result.message || 'unknown error')"></p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button x-show="result.success" @click.stop="approveSource(idx)" class="px-4 py-2 rounded-lg font-semibold text-sm transition-colors" :class="approvedSources.includes(idx) ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200 border border-green-300'">
                                    <svg class="w-5 h-5 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="approvedSources.includes(idx) ? 'Approved' : 'Approve'"></span>
                                </button>
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
                <button @click="completeStep(4); openStep(5)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Continue to AI & Spin &rarr;</button>
            </div>
        </div>
    </div>

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
                <span class="font-semibold text-gray-800">AI & Spin</span>
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

            {{-- Custom prompt input — live-updates resolved prompt --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Custom Instructions <span class="text-gray-400">(takes precedence over template/preset)</span></label>
                <textarea x-model="customPrompt" @input.debounce.500ms="refreshPromptPreview()" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Write in first person, focus on the financial impact, include expert quotes..."></textarea>
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

                    {{-- Waiting state --}}
                    <div x-show="!aiDetectionRan && !aiDetecting" x-cloak class="text-xs text-gray-500 text-center py-2">
                        Detection runs automatically after spin completes.
                    </div>
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

        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 6: Create Article
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 6, 'opacity-50': !isStepAccessible(6) }">
        <button @click="toggleStep(6)" :disabled="!isStepAccessible(6)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(6) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(6)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(6)"><span>6</span></template>
                </span>
                <span class="font-semibold text-gray-800">Create Article</span>
                <span x-show="spunContent" x-cloak class="text-sm text-green-600" x-text="spunWordCount + ' words'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(6) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(6)" x-cloak x-collapse class="px-4 pb-4">

            {{-- Spun content in TinyMCE editor --}}
            <div x-show="spunContent" x-cloak>
                <p class="text-xs text-gray-500 mb-2">Generated article — edit directly below</p>
                {{-- Title textbox --}}
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Article Title</label>
                    <div x-show="metadataLoading && !articleTitle" class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-gray-50 animate-pulse h-[60px]"></div>
                    <textarea x-show="!metadataLoading || articleTitle" x-model="articleTitle" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg font-bold resize-none overflow-hidden" placeholder="Enter article title..." @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
                </div>

                {{-- Article Description / Excerpt --}}
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Article Description / Excerpt</label>
                    <textarea x-model="articleDescription" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Short summary for SEO meta description and excerpt..."></textarea>
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

                {{-- Featured Image — photo + metadata in one card --}}
                <div x-show="featuredImageSearch" x-cloak class="mt-4 bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-purple-800 mb-3">Featured Image</h5>
                    <div class="flex items-start gap-4">
                        {{-- Thumbnail --}}
                        <div class="w-48 h-36 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                            <img x-show="featuredPhoto" x-cloak :src="featuredPhoto?.url_large || featuredPhoto?.url_thumb" class="w-full h-full object-cover">
                            <div x-show="!featuredPhoto && featuredSearching" x-cloak class="w-full h-full flex items-center justify-center bg-purple-100">
                                <svg class="w-6 h-6 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </div>
                            <div x-show="!featuredPhoto && !featuredSearching" class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        {{-- Info + metadata --}}
                        <div class="flex-1 space-y-1.5">
                            <p x-show="featuredPhoto" x-cloak class="text-[11px] text-gray-400" x-text="(featuredPhoto?.source || '') + ' — ' + (featuredPhoto?.width || '') + 'x' + (featuredPhoto?.height || '')"></p>
                            <div x-show="featuredPhoto" x-cloak class="space-y-1">
                                <div><label class="text-[10px] text-gray-400 uppercase">Alt Text</label><input type="text" x-model="featuredAlt" class="w-full border border-purple-200 rounded px-2 py-1 text-xs bg-white" placeholder="Alt text..."></div>
                                <div><label class="text-[10px] text-gray-400 uppercase">Caption</label><input type="text" x-model="featuredCaption" class="w-full border border-purple-200 rounded px-2 py-1 text-xs bg-white" placeholder="Caption..."></div>
                                <div><label class="text-[10px] text-gray-400 uppercase">WordPress Filename</label><p class="text-xs font-mono text-gray-600 bg-white rounded px-2 py-1 border border-purple-100" x-text="featuredFilename || 'auto'"></p></div>
                            </div>
                            <div class="flex items-center gap-3 pt-1">
                                <button x-show="featuredPhoto" x-cloak @click.stop="refreshFeaturedMeta()" :disabled="featuredRefreshingMeta" class="text-[11px] text-purple-500 hover:text-purple-700 inline-flex items-center gap-1 disabled:opacity-50">
                                    <svg class="w-3 h-3" :class="featuredRefreshingMeta ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span x-text="featuredRefreshingMeta ? 'Generating...' : 'AI Refresh Metadata'"></span>
                                </button>
                                <button x-show="featuredPhoto" x-cloak @click="featuredPhoto = null; featuredAlt = ''; featuredCaption = ''; featuredFilename = ''" class="text-[11px] text-red-500 hover:text-red-700">Remove</button>
                            </div>
                        </div>
                    </div>
                    {{-- Search + results below --}}
                    <div class="mt-3">
                        <div class="flex gap-2 mb-2">
                            <input type="text" x-model="featuredImageSearch" class="flex-1 border border-purple-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search for featured image...">
                            <button @click="searchFeaturedImage()" :disabled="featuredSearching" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-1">
                                <svg x-show="featuredSearching" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Search
                            </button>
                        </div>
                        <div x-show="featuredResults.length > 1" x-cloak class="grid grid-cols-4 gap-2">
                            <template x-for="(photo, fidx) in featuredResults" :key="fidx">
                                <div @click="featuredPhoto = photo" class="cursor-pointer rounded-lg overflow-hidden border-2 transition-colors"
                                     :class="featuredPhoto && featuredPhoto.url_thumb === photo.url_thumb ? 'border-purple-500' : 'border-gray-200 hover:border-purple-300'">
                                    <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-60 object-cover" loading="lazy">
                                </div>
                            </template>
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

                    {{-- AI photo suggestions — photo + metadata in one card --}}
                    <div x-show="photoSuggestions.length > 0" class="space-y-3 mb-3">
                        <template x-for="(ps, idx) in photoSuggestions" :key="idx">
                            <div x-show="!ps.removed" class="border rounded-lg overflow-hidden" :class="ps.confirmed ? 'border-green-300 bg-green-50' : 'border-purple-200 bg-white'">
                                {{-- Photo + info + metadata — all in one section --}}
                                <div class="p-3">
                                    <div class="flex items-start gap-3">
                                        {{-- Thumbnail --}}
                                        <div class="w-40 h-28 flex-shrink-0 rounded overflow-hidden bg-gray-100">
                                            <img x-show="ps.autoPhoto" x-cloak :src="ps.autoPhoto?.url_thumb" class="w-full h-full object-cover">
                                            <div x-show="!ps.autoPhoto && autoFetchingPhotos" x-cloak class="w-full h-full flex items-center justify-center bg-purple-50">
                                                <svg class="w-5 h-5 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            </div>
                                            <div x-show="!ps.autoPhoto && !autoFetchingPhotos" class="w-full h-full flex items-center justify-center text-gray-300">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        </div>
                                        {{-- Info + metadata --}}
                                        <div class="flex-1 min-w-0 space-y-1.5">
                                            <p class="text-sm font-medium text-purple-700 break-words" x-text="ps.search_term"></p>
                                            <p x-show="ps.autoPhoto" x-cloak class="text-[11px] text-gray-400" x-text="(ps.autoPhoto?.source || '') + ' — ' + (ps.autoPhoto?.width || '') + 'x' + (ps.autoPhoto?.height || '')"></p>
                                            {{-- Metadata fields --}}
                                            <div x-show="ps.autoPhoto" x-cloak class="space-y-1">
                                                <div><label class="text-[10px] text-gray-400 uppercase">Alt Text</label><input type="text" x-model="photoSuggestions[idx].alt_text" class="w-full border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Alt text..."></div>
                                                <div><label class="text-[10px] text-gray-400 uppercase">Caption</label><input type="text" x-model="photoSuggestions[idx].caption" class="w-full border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Caption..."></div>
                                                <div><label class="text-[10px] text-gray-400 uppercase">WordPress Filename</label><p class="text-xs font-mono text-gray-600 bg-gray-50 rounded px-2 py-1" x-text="photoSuggestions[idx].suggestedFilename || 'auto'"></p></div>
                                                <button @click.stop="refreshPhotoMeta(idx)" :disabled="ps.refreshingMeta" class="text-[11px] text-purple-500 hover:text-purple-700 inline-flex items-center gap-1 disabled:opacity-50 mt-0.5">
                                                    <svg class="w-3 h-3" :class="ps.refreshingMeta ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                    <span x-text="ps.refreshingMeta ? 'Generating...' : 'AI Refresh Metadata'"></span>
                                                </button>
                                            </div>
                                        </div>
                                        {{-- Action buttons --}}
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            <button @click.stop="confirmPhoto(idx)" x-show="!ps.confirmed && ps.autoPhoto" x-cloak class="p-1.5 rounded hover:bg-green-100 text-green-600" title="Confirm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>
                                            <span x-show="ps.confirmed" x-cloak class="text-xs text-green-600 font-medium px-1">Confirmed</span>
                                            <button @click.stop="expandedSuggestions.includes(idx) ? expandedSuggestions = expandedSuggestions.filter(i => i !== idx) : expandedSuggestions.push(idx)" class="p-1.5 rounded hover:bg-blue-100 text-blue-600" title="Change"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg></button>
                                            <button @click.stop="removePhotoPlaceholder(idx)" class="p-1.5 rounded hover:bg-red-100 text-red-600" title="Remove"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expandedSuggestions.includes(idx) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </div>
                                    </div>
                                </div>
                                {{-- Expanded: search + results (only for changing photo) --}}
                                <div x-show="expandedSuggestions.includes(idx)" x-cloak class="p-3 pt-0 border-t border-gray-100">
                                    <div class="flex gap-2 mb-2">
                                        <input type="text" :value="ps.search_term" @input="photoSuggestions[idx].search_term = $event.target.value" @keydown.enter="searchPhotosForSuggestion(idx)" class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search...">
                                        <button @click="searchPhotosForSuggestion(idx)" :disabled="ps.searching" class="bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-gray-700 disabled:opacity-50 inline-flex items-center gap-1">
                                            <svg x-show="ps.searching" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span x-text="ps.searching ? 'Searching...' : 'Search'"></span>
                                        </button>
                                    </div>
                                    <div x-show="ps.searchResults && ps.searchResults.length > 0" x-cloak class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <template x-for="(photo, pidx) in (ps.searchResults || [])" :key="pidx">
                                            <div class="cursor-pointer rounded-lg overflow-hidden border-2 hover:border-blue-400 transition-colors"
                                                 :class="ps.autoPhoto && ps.autoPhoto.url_thumb === photo.url_thumb ? 'border-purple-400' : 'border-gray-200'"
                                                 @click="selectPhotoForSuggestion(idx, photo)">
                                                <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-44 object-cover">
                                            </div>
                                        </template>
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
                                        <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-60 object-cover rounded-lg border border-gray-200">
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

                {{-- Author selection --}}
                <div class="mt-4 flex flex-wrap items-center gap-3" x-show="selectedSite">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Publish As</label>
                        <div class="flex items-center gap-2">
                            <select x-model="publishAuthor" @change="publishAuthorSource = 'manual'; autoSaveDraft()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" x-effect="$nextTick(() => { if (publishAuthor) $el.value = publishAuthor; })">
                                <option value="">— Default author —</option>
                                <template x-if="publishAuthor && !siteConn.authors.some(a => a.user_login === publishAuthor)">
                                    <option :value="publishAuthor" x-text="publishAuthor"></option>
                                </template>
                                <template x-for="a in siteConn.authors" :key="a.user_login">
                                    <option :value="a.user_login" x-text="(a.display_name || a.user_login) + ' (' + a.user_login + ')'"></option>
                                </template>
                            </select>
                            <svg x-show="siteConn.testing || authorsLoading" x-cloak class="w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-show="authorsLoading" x-cloak class="text-xs text-blue-500">Loading authors...</span>
                            <span x-show="!siteConn.testing && !authorsLoading && siteConn.authors.length > 0" x-cloak class="text-xs text-gray-400" x-text="siteConn.authors.length + ' authors'"></span>
                            <a x-show="publishAuthor && selectedSite" x-cloak :href="(selectedSite?.url || '').replace(/\/$/, '') + '/author/' + publishAuthor + '/'" target="_blank" class="text-xs text-blue-500 hover:text-blue-700 inline-flex items-center gap-0.5" title="View author on WordPress">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </div>
                        <p x-show="publishAuthorSource === 'profile' && publishAuthor" x-cloak class="text-[11px] text-gray-400 mt-0.5">Default author from user profile</p>
                    </div>
                </div>

                {{-- Create Article Activity Log (collapsible at bottom) --}}
                <div x-show="$root.spinLog && $root.spinLog.length > 0" x-cloak class="mt-4" x-data="{ showLog: false }">
                    <button @click="showLog = !showLog" class="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
                        <svg class="w-3 h-3 transition-transform" :class="showLog ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        Activity Log (<span x-text="($root.spinLog || []).length"></span> entries)
                    </button>
                    <div x-show="showLog" x-cloak class="mt-1 bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                        <template x-for="(entry, idx) in $root.spinLog" :key="idx">
                            <div class="flex items-start gap-2 py-0.5 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                                <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                                <span :class="{ 'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info', 'text-gray-400': entry.type === 'step', 'text-yellow-400': entry.type === 'warning' }" x-text="entry.message" class="break-words"></span>
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
                        <a href="{{ route('publish.templates.index') }}" target="_blank" class="hover:text-blue-600 inline-flex items-center gap-1">Article Presets <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
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
         Step 7: Review & Publish
         ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" :class="{ 'ring-2 ring-blue-400': currentStep === 7, 'opacity-50': !isStepAccessible(7) }">
        <button @click="toggleStep(7)" :disabled="!isStepAccessible(7)" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(7) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(7)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(7)"><span>7</span></template>
                </span>
                <span class="font-semibold text-gray-800">Review & Publish</span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(7) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(7)" x-cloak x-collapse class="px-4 pb-4">

            {{-- ═══ Review Section (row layout) ═══ --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4 space-y-3">
                <h5 class="text-base font-semibold text-gray-800 mb-2">Review</h5>

                {{-- Article Info --}}
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Title</span><p class="text-sm font-bold text-gray-900 break-words" x-text="articleTitle || 'No title set'"></p></div>
                <div x-show="articleDescription" class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Description</span><p class="text-sm text-gray-700 break-words" x-text="articleDescription"></p></div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Word Count</span><p class="text-sm font-bold text-gray-800" x-text="spunWordCount + ' words'"></p></div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Draft ID</span><p class="text-sm font-mono text-gray-800" x-text="'#' + draftId"></p></div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Draft URL</span><a :href="'/article/publish?id=' + draftId" class="text-sm text-blue-600 hover:underline break-all" x-text="'/article/publish?id=' + draftId"></a></div>

                {{-- Publishing Info --}}
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100">
                    <span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Website</span>
                    <div class="text-sm text-gray-800">
                        <span x-text="selectedSite ? selectedSite.name : 'Not selected'"></span>
                        <a x-show="selectedSite" :href="selectedSite?.url" target="_blank" class="text-xs text-blue-500 hover:text-blue-700 ml-1 inline-flex items-center gap-0.5">
                            <span x-text="selectedSite?.url" class="break-all"></span>
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                </div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100">
                    <span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Author</span>
                    <div class="text-sm text-gray-800 inline-flex items-center gap-1">
                        <span x-text="publishAuthor || 'Default'"></span>
                        <a x-show="publishAuthor && selectedSite" x-cloak :href="(selectedSite?.url || '').replace(/\/$/, '') + '/author/' + publishAuthor + '/'" target="_blank" class="text-blue-500 hover:text-blue-700">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                        <span x-show="publishAuthorSource === 'profile'" x-cloak class="text-[10px] text-gray-400">(from profile)</span>
                    </div>
                </div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Publish Action</span><p class="text-sm text-gray-800" x-text="publishAction === 'publish' ? 'Publish immediately' : (publishAction === 'draft_wp' ? 'WordPress draft' : (publishAction === 'future' ? 'Scheduled' : 'Local draft'))"></p></div>

                {{-- Template & Preset --}}
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Article Preset</span><p class="text-sm text-gray-800" x-text="selectedTemplate ? selectedTemplate.name : 'Default'"></p></div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">WP Preset</span><p class="text-sm text-gray-800" x-text="selectedPreset ? selectedPreset.name : 'None'"></p></div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">AI Model</span><p class="text-sm font-mono text-gray-800" x-text="aiModel"></p></div>

                {{-- Content Stats --}}
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Photos</span><p class="text-sm text-gray-800" x-text="photoSuggestions.filter(p => !p.removed).length + ' photo(s)' + (featuredPhoto ? ' + featured' : '')"></p></div>
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Links</span><p class="text-sm text-gray-800" x-text="suggestedUrls.length + ' link(s)'"></p></div>
                <div x-show="tokenUsage" class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Token Usage</span><p class="text-sm font-mono text-gray-800" x-text="(tokenUsage?.input_tokens || 0) + ' in / ' + (tokenUsage?.output_tokens || 0) + ' out'"></p></div>

                {{-- AI Detection Summary --}}
                <div x-show="aiDetectionRan" class="flex items-start gap-3 py-1.5 border-b border-gray-100">
                    <span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">AI Detection</span>
                    <div class="text-sm">
                        <span x-show="aiDetectionAllPass" class="text-green-600 font-medium">All Pass</span>
                        <span x-show="!aiDetectionAllPass" class="text-red-600 font-medium">Flagged</span>
                        <template x-for="(det, key) in aiDetectionResults" :key="key">
                            <span class="text-xs text-gray-500 ml-2" x-text="key + ': ' + (det.score !== undefined ? det.score + '%' : (det.error ? 'Error' : '—'))"></span>
                        </template>
                    </div>
                </div>

                {{-- Featured Image --}}
                <div x-show="featuredPhoto" class="flex items-start gap-3 py-1.5 border-b border-gray-100">
                    <span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Featured Image</span>
                    <div class="flex items-center gap-3">
                        <img x-show="featuredPhoto?.url_thumb" :src="featuredPhoto?.url_thumb" class="w-16 h-12 object-cover rounded flex-shrink-0">
                        <div class="text-xs text-gray-600">
                            <p x-text="featuredAlt || 'No alt text'" class="break-words"></p>
                            <p class="font-mono text-gray-400" x-text="featuredFilename || 'auto'"></p>
                        </div>
                    </div>
                </div>

                {{-- Categories --}}
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100">
                    <span class="text-xs text-gray-400 w-24 flex-shrink-0 pt-0.5">Categories</span>
                    <div class="flex flex-wrap gap-1">
                        <template x-for="(cat, idx) in suggestedCategories" :key="idx">
                            <span x-show="selectedCategories.includes(idx)" class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-800" x-text="cat"></span>
                        </template>
                    </div>
                </div>

                {{-- Tags --}}
                <div class="flex items-start gap-3 py-1.5 border-b border-gray-100">
                    <span class="text-xs text-gray-400 w-24 flex-shrink-0 pt-0.5">Tags</span>
                    <div class="flex flex-wrap gap-1">
                        <template x-for="(tag, idx) in suggestedTags" :key="idx">
                            <span x-show="selectedTags.includes(idx)" class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800" x-text="tag"></span>
                        </template>
                    </div>
                </div>

                {{-- Sources --}}
                <div class="flex items-start gap-3 py-1.5">
                    <span class="text-xs text-gray-400 w-24 flex-shrink-0 pt-0.5">Sources</span>
                    <div class="space-y-0.5">
                        <template x-for="(s, idx) in sources" :key="idx">
                            <p class="text-xs text-gray-600 break-all" x-text="s.title || s.url"></p>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ═══ SEO Preview ═══ --}}
            <div x-show="articleDescription || articleTitle" class="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-4 space-y-3">
                <h5 class="text-sm font-semibold text-gray-700">SEO Preview</h5>
                <div class="flex items-start gap-3 py-1 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Meta Title</span><p class="text-sm text-blue-700 font-medium break-words" x-text="articleTitle || ''"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Meta Description</span><p class="text-sm text-gray-600 break-words" x-text="articleDescription || ''"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">OG Title</span><p class="text-sm text-gray-700 break-words" x-text="articleTitle || ''"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">OG Description</span><p class="text-sm text-gray-600 break-words" x-text="articleDescription || ''"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">OG Type</span><p class="text-sm text-gray-600">article</p></div>
                <div class="flex items-start gap-3 py-1 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Twitter Card</span><p class="text-sm text-gray-600">summary_large_image</p></div>
                <div x-show="featuredPhoto" class="flex items-start gap-3 py-1"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">OG Image</span><p class="text-sm text-gray-600 break-all" x-text="featuredPhoto?.url_large || featuredPhoto?.url_thumb || ''"></p></div>
            </div>

            {{-- ═══ Prepare for WordPress (only for WP actions) ═══ --}}
            <div x-show="publishAction === 'publish' || publishAction === 'draft_wp' || publishAction === 'future'" x-cloak class="border border-gray-200 rounded-xl p-5 mb-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">Prepare for WordPress</h5>

                <button @click="prepareForWp()" :disabled="preparing || prepareComplete" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2 mb-4">
                    <svg x-show="preparing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="preparing ? 'Preparing...' : (prepareComplete ? 'Prepared' : 'Prepare for WordPress')"></span>
                </button>

                {{-- Checklist --}}
                <div x-show="prepareChecklist.length > 0" x-cloak class="space-y-2 mb-4">
                    <template x-for="(item, idx) in prepareChecklist" :key="idx">
                        <div class="flex items-center gap-3 text-sm">
                            <template x-if="item.status === 'running'"><svg class="w-5 h-5 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                            <template x-if="item.status === 'done'"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></template>
                            <template x-if="item.status === 'failed'"><svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></template>
                            <template x-if="item.status === 'skipped'"><svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></template>
                            <span :class="{'text-blue-700': item.status === 'running', 'text-green-700': item.status === 'done', 'text-red-700': item.status === 'failed', 'text-gray-500': item.status === 'skipped'}" x-text="item.label"></span>
                            <span x-show="item.detail" class="text-xs text-gray-400" x-text="item.detail"></span>
                        </div>
                    </template>
                </div>

                {{-- Uploaded photos report --}}
                <div x-show="uploadedImages && Object.keys(uploadedImages).length > 0" x-cloak class="mt-4">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase mb-2">Uploaded Photos</h6>
                    <div class="space-y-3">
                        <template x-for="(img, imgKey) in uploadedImages" :key="imgKey">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs space-y-1">
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Filename</span><span class="font-mono text-gray-800 break-all" x-text="img.filename || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Media ID</span><span class="text-gray-800" x-text="img.media_id || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Path</span><span class="font-mono text-gray-600 break-all" x-text="img.file_path || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Size</span><span class="text-gray-800" x-text="img.file_size ? (Math.round(img.file_size / 1024) + ' KB') : '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Alt Text</span><span class="text-gray-700 break-words" x-text="img.alt_text || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Caption</span><span class="text-gray-700 break-words" x-text="img.caption || '—'"></span></div>
                                <div x-show="img.sizes" class="mt-1 pt-1 border-t border-gray-200">
                                    <p class="text-gray-400 mb-1">WordPress Sizes:</p>
                                    <template x-for="(url, sizeName) in (img.sizes || {})" :key="sizeName">
                                        <div class="flex items-start gap-3 py-0.5"><span class="text-gray-400 w-20 flex-shrink-0" x-text="sizeName"></span><a :href="url" target="_blank" class="text-blue-600 hover:underline break-all inline-flex items-center gap-1" x-text="url"><svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>

            {{-- ═══ Publish Action ═══ --}}
            <div class="border border-gray-200 rounded-xl p-5 mb-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">Publish</h5>

                <div class="max-w-md space-y-3 mb-4">
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" x-model="publishAction" value="publish" class="text-blue-600"><span class="text-sm">Publish immediately</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" x-model="publishAction" value="draft_local" class="text-blue-600"><span class="text-sm">Save as local draft</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" x-model="publishAction" value="draft_wp" class="text-blue-600"><span class="text-sm">Push as WordPress draft</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" x-model="publishAction" value="future" class="text-blue-600"><span class="text-sm">Schedule</span></label>
                    </div>
                    <div x-show="publishAction === 'future'" x-cloak>
                        <label class="block text-xs text-gray-500 mb-1">Schedule Date & Time</label>
                        <input type="datetime-local" x-model="scheduleDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button @click="publishArticle()" :disabled="publishing" class="bg-green-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="publishing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="publishing ? 'Publishing...' : 'Publish'"></span>
                    </button>
                    <button @click="saveDraftNow()" :disabled="savingDraft" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="savingDraft" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="savingDraft ? 'Saving...' : 'Save Draft'"></span>
                    </button>
                </div>

                {{-- Publish error --}}
                <div x-show="publishError" x-cloak class="mt-3 bg-red-50 border border-red-200 rounded-lg p-3">
                    <p class="text-sm text-red-700" x-text="publishError"></p>
                </div>
            </div>

            {{-- ═══ Publish Result — full post + photo report ═══ --}}
            <div x-show="publishResult" x-cloak class="bg-green-50 border border-green-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="font-semibold text-green-800 text-lg" x-text="publishResult?.message || 'Published successfully!'"></span>
                </div>

                {{-- Post details (row layout) --}}
                <div class="space-y-2 mb-4">
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Title</span><p class="text-sm font-medium text-gray-800 break-words" x-text="articleTitle || 'Untitled'"></p></div>
                    <div x-show="publishResult?.post_url" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Post URL</span><a :href="publishResult?.post_url" target="_blank" class="text-sm text-blue-600 hover:underline break-all inline-flex items-center gap-1" x-text="publishResult?.post_url"></a></div>
                    <div x-show="publishResult?.post_id" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">WP Post ID</span><p class="text-sm font-mono text-gray-800" x-text="publishResult?.post_id"></p></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Post Type</span><p class="text-sm text-gray-800" x-text="publishAction === 'publish' ? 'Published' : (publishAction === 'draft_wp' ? 'WP Draft' : (publishAction === 'future' ? 'Scheduled' : 'Local Draft'))"></p></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Author</span><p class="text-sm text-gray-800" x-text="publishAuthor || 'Default'"></p></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Website</span><p class="text-sm text-gray-800" x-text="selectedSite?.name || 'Local'"></p></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Word Count</span><p class="text-sm text-gray-800" x-text="spunWordCount + ' words'"></p></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">AI Model</span><p class="text-sm font-mono text-gray-800" x-text="aiModel"></p></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Draft ID</span><p class="text-sm text-gray-800" x-text="'#' + draftId"></p></div>
                    <div x-show="featuredPhoto?.url_large" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Featured Image</span><a :href="featuredPhoto?.url_large" target="_blank" class="text-sm text-blue-600 hover:underline break-all" x-text="featuredPhoto?.url_large"></a></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Date Created</span><p class="text-sm text-gray-800" x-text="new Date().toLocaleString()"></p></div>
                    <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Categories</span><p class="text-sm text-gray-800 break-words" x-text="suggestedCategories.length ? suggestedCategories.join(', ') : 'None'"></p></div>
                    <div class="flex items-start gap-3 py-1"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Tags</span><p class="text-sm text-gray-800 break-words" x-text="suggestedTags.length ? suggestedTags.join(', ') : 'None'"></p></div>
                </div>

                {{-- Uploaded photos full report --}}
                <div x-show="uploadedImages && Object.keys(uploadedImages).length > 0" x-cloak>
                    <h6 class="text-sm font-semibold text-gray-700 mb-2">Photos</h6>
                    <div class="space-y-3">
                        <template x-for="(img, imgKey) in uploadedImages" :key="imgKey">
                            <div class="bg-white border border-gray-200 rounded-lg p-3 text-xs space-y-1">
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Filename</span><span class="font-mono text-gray-800 break-all" x-text="img.filename || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Media ID</span><span class="text-gray-800" x-text="img.media_id || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Path</span><span class="font-mono text-gray-600 break-all" x-text="img.file_path || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Size</span><span class="text-gray-800" x-text="img.file_size ? (Math.round(img.file_size / 1024) + ' KB') : '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Alt Text</span><span class="text-gray-700 break-words" x-text="img.alt_text || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Caption</span><span class="text-gray-700 break-words" x-text="img.caption || '—'"></span></div>
                                <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Description</span><span class="text-gray-700 break-words" x-text="img.description || '—'"></span></div>
                                <div x-show="img.sizes" class="mt-1 pt-1 border-t border-gray-200">
                                    <p class="text-gray-400 mb-1">WordPress Sizes:</p>
                                    <template x-for="(url, sizeName) in (img.sizes || {})" :key="sizeName">
                                        <div class="flex items-start gap-3 py-0.5"><span class="text-gray-400 w-20 flex-shrink-0" x-text="sizeName"></span><a :href="url" target="_blank" class="text-blue-600 hover:underline break-all" x-text="url"></a></div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Activity Log --}}
                <div x-show="prepareLog.length > 0" x-cloak class="mt-4 bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto" x-ref="prepareLogContainer">
                    <p class="text-xs text-gray-500 mb-2 font-semibold uppercase">Activity Log</p>
                    <template x-for="(entry, idx) in prepareLog" :key="idx">
                        <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                            <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                            <span :class="{'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info', 'text-yellow-400': entry.type === 'warning', 'text-gray-400': entry.type === 'step'}" x-text="entry.message" class="break-words"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload Portal Modal (triggered from TinyMCE toolbar) --}}
    <div x-show="showUploadPortal" x-cloak>
        @include('upload-portal::components.upload-portal', ['context' => 'article', 'contextId' => $draftId, 'multi' => true])
    </div>

    {{-- Photo Search Overlay (triggered from TinyMCE toolbar) --}}
    <div x-show="showPhotoOverlay" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-0 m-0" style="top:0;left:0;right:0;bottom:0;" @click.self="showPhotoOverlay = false" @keydown.escape.window="showPhotoOverlay = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="font-semibold text-gray-800">Search & Insert Photo</h3>
                <button @click="showPhotoOverlay = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6">
                <div class="flex gap-2 mb-4">
                    <input type="text" x-model="photoSearch" @keydown.enter="searchPhotos()" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Search for photos...">
                    <button @click="searchPhotos()" :disabled="photoSearching" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-1">
                        <svg x-show="photoSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Search
                    </button>
                </div>
                <div x-show="photoResults.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <template x-for="(photo, pidx) in photoResults" :key="pidx">
                        <div class="cursor-pointer rounded-lg overflow-hidden border-2 hover:border-purple-400 transition-colors" :class="insertingPhoto === photo ? 'border-purple-500 ring-2 ring-purple-200' : 'border-gray-200'" @click="insertingPhoto = photo; photoCaption = photo.alt || articleTitle; overlayPhotoAlt = 'Click Get Metadata to generate'; overlayPhotoCaption = 'Click Get Metadata to generate'; overlayPhotoFilename = 'auto'; overlayMetaGenerated = false;">
                            <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-48 object-cover">
                            <p class="text-[10px] text-gray-400 px-2 py-1 truncate" x-text="(photo.source || '') + ' — ' + (photo.width || '') + 'x' + (photo.height || '')"></p>
                        </div>
                    </template>
                </div>
                {{-- Selected photo details + metadata --}}
                <div x-show="insertingPhoto" x-cloak class="mt-4 border-t border-gray-200 pt-4">
                    <div class="flex items-start gap-4 mb-4">
                        <img :src="insertingPhoto?.url_thumb" class="w-32 h-24 object-cover rounded-lg flex-shrink-0">
                        <div class="flex-1 space-y-2">
                            <p class="text-xs text-gray-400" x-text="(insertingPhoto?.source || '') + ' — ' + (insertingPhoto?.width || '') + 'x' + (insertingPhoto?.height || '')"></p>
                            <div><label class="text-[10px] text-gray-400 uppercase">Alt Text</label><input type="text" x-model="overlayPhotoAlt" class="w-full border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Alt text..."></div>
                            <div><label class="text-[10px] text-gray-400 uppercase">Caption</label><input type="text" x-model="overlayPhotoCaption" class="w-full border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Caption..."></div>
                            <div><label class="text-[10px] text-gray-400 uppercase">Filename</label><p class="text-xs font-mono text-gray-600 bg-gray-50 rounded px-2 py-1" x-text="overlayPhotoFilename || 'auto'"></p></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="getOverlayPhotoMeta()" :disabled="overlayMetaLoading" class="text-xs text-purple-600 hover:text-purple-800 inline-flex items-center gap-1 disabled:opacity-50 border border-purple-200 px-3 py-1.5 rounded-lg">
                            <svg class="w-3 h-3" :class="overlayMetaLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span x-text="overlayMetaLoading ? 'Generating...' : 'Get Metadata'"></span>
                        </button>
                        <button @click="photoCaption = overlayPhotoAlt || photoCaption; insertPhotoIntoEditor(); showPhotoOverlay = false" :disabled="!overlayMetaGenerated" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Insert at Cursor
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

@include('app-publish::partials.site-connection-mixin')
@include('app-publish::partials.preset-fields-mixin')
<script>
function publishPipeline() {
    return {
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
            return ['User', 'Website & Template', this.isGenerateMode ? 'Generate Content' : 'Find Articles', 'Fetch Articles from Source', 'AI & Spin', 'Create Article', 'Review & Publish'];
        },

        // Step 1 — User
        selectedUser: null,

        // Step 2 — Preset + Website
        presets: [],
        presetsLoading: false,
        selectedPresetId: '',
        selectedPreset: null,
        editingPreset: false,

        // Step 3 — Website
        sites: @json($sites ?? []),
        draftState: @json($draftState ?? []),
        selectedSiteId: '',
        selectedSite: null,

        // Step 4 — Sources
        sources: [],
        sourceTab: 'paste',
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
        featuredAlt: '',
        featuredCaption: '',
        featuredFilename: '',
        featuredRefreshingMeta: false,
        resolvedPrompt: '',
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

        init() {
            const draftState = this.draftState || {};
            const saved = localStorage.getItem(this.pipelineStateKey);
            const legacySaved = !saved && !draftState.body ? localStorage.getItem('publishPipelineState') : null;
            const savedState = saved || legacySaved;
            let state = null;
            let shouldPersistRestoredDraftState = false;

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
            }

            if (state) {
                if (state.selectedUser) this.selectedUser = state.selectedUser;
                if (state.currentStep) this.currentStep = state.currentStep;
                if (state.openSteps) this.openSteps = Array.isArray(state.openSteps) ? state.openSteps : Object.values(state.openSteps);
                if (state.completedSteps) this.completedSteps = Array.isArray(state.completedSteps) ? state.completedSteps : Object.values(state.completedSteps);
                if (state.selectedSiteId) {
                    this.selectedSiteId = String(state.selectedSiteId);
                    this.selectedSite = state.selectedSite || null;
                    this.authorsLoading = true;
                    this.siteConn.status = state.siteConnStatus ?? null;
                    this.siteConn.message = state.siteConnMessage || '';
                    if (state.siteConnLog) this.siteConn.log = state.siteConnLog;
                    if (state.siteConnAuthors) this.siteConn.authors = state.siteConnAuthors;
                    this.$nextTick(() => {
                        this.selectedSiteId = String(state.selectedSiteId);
                        this.selectedSite = this.sites.find(s => s.id == state.selectedSiteId) || state.selectedSite || null;
                    });
                }
                if (state.sources) this.sources = state.sources;
                if (state.checkResults) { this.checkResults = state.checkResults; this.checkPassCount = state.checkResults.filter(r => r.success).length; }
                if (state.approvedSources) this.approvedSources = state.approvedSources;
                if (state.discardedSources) this.discardedSources = state.discardedSources;
                if (state.expandedSources) this.expandedSources = state.expandedSources;
                if (state.aiModel) this.aiModel = state.aiModel;
                if (state.articleTitle) this.articleTitle = state.articleTitle;
                if (state.publishAction) this.publishAction = state.publishAction;
                if (state.publishAuthor) this.publishAuthor = state.publishAuthor;
                if (state.publishAuthorSource) this.publishAuthorSource = state.publishAuthorSource;
                if (state.spunContent || state.editorContent) {
                    const restoredEditorHtml = state.editorContent || state.spunContent;
                    this.spunContent = restoredEditorHtml;
                    this.spunWordCount = state.spunWordCount || this.countWordsFromHtml(restoredEditorHtml);
                    this.setSpinEditor(restoredEditorHtml);
                    this.extractArticleLinks(restoredEditorHtml);
                }
                if (state.suggestedTitles) this.suggestedTitles = state.suggestedTitles;
                if (state.suggestedCategories) this.suggestedCategories = state.suggestedCategories;
                if (state.suggestedTags) this.suggestedTags = state.suggestedTags;
                if (state.selectedCategories) this.selectedCategories = state.selectedCategories;
                if (state.selectedTags) this.selectedTags = state.selectedTags;
                if (state.selectedTitleIdx !== undefined) { this.selectedTitleIdx = state.selectedTitleIdx; if (this.suggestedTitles[this.selectedTitleIdx]) this.articleTitle = this.suggestedTitles[this.selectedTitleIdx]; }
                if (state.editorContent) this.editorContent = state.editorContent;
                if (state.tokenUsage) this.tokenUsage = state.tokenUsage;
                if (state.photoSuggestions) {
                    this.photoSuggestions = state.photoSuggestions.map((ps, idx) => ({ ...ps, refreshingMeta: false, searching: false, suggestedFilename: this.buildFilename(ps.search_term, idx + 1) }));
                }
                if (state.featuredImageSearch) this.featuredImageSearch = state.featuredImageSearch;
                if (state.featuredPhoto) {
                    this.featuredPhoto = state.featuredPhoto;
                    this.featuredResults = [state.featuredPhoto];
                }
                if (state.featuredAlt) this.featuredAlt = state.featuredAlt;
                if (state.featuredCaption) this.featuredCaption = state.featuredCaption;
                if (state.featuredFilename) this.featuredFilename = state.featuredFilename;
                if (state.resolvedPrompt) this.resolvedPrompt = state.resolvedPrompt;
                if (state.articleDescription) this.articleDescription = state.articleDescription;
                if (state.aiDetectionResults) {
                    const restored = state.aiDetectionResults;
                    Object.keys(restored).forEach(k => { restored[k].loading = false; });
                    this.aiDetectionResults = restored;
                }
                if (state.aiDetectionRan) this.aiDetectionRan = state.aiDetectionRan;
                if (state.aiDetectionAllPass !== undefined) this.aiDetectionAllPass = state.aiDetectionAllPass;
                // Find Articles state
                if (state.sourceTab) this.sourceTab = state.sourceTab;
                if (state.newsMode) this.newsMode = state.newsMode;
                if (state.newsCategory) this.newsCategory = state.newsCategory;
                if (state.newsTrendingSelected) this.newsTrendingSelected = state.newsTrendingSelected;
                if (state.newsSearch) this.newsSearch = state.newsSearch;
                if (state.newsResults) this.newsResults = state.newsResults;
                if (state.newsHasSearched) this.newsHasSearched = state.newsHasSearched;

                shouldPersistRestoredDraftState = !draftState.selectedSiteId && (
                    !!state.selectedSiteId ||
                    !!state.selectedUser ||
                    !!state.selectedPresetId ||
                    !!state.selectedTemplateId ||
                    !!state.publishAuthor
                );
            }

            // Database-backed draft state is authoritative for site/user/template/preset restore.
            if (draftState.selectedUser) this.selectedUser = draftState.selectedUser;
            if (draftState.selectedSiteId) {
                this.selectedSiteId = String(draftState.selectedSiteId);
                this.selectedSite = this.sites.find(s => s.id == draftState.selectedSiteId) || draftState.selectedSite || null;
                this.authorsLoading = true;
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
            if (this.sources.length > 0 && !this.completedSteps.includes(3)) this.completedSteps.push(3);
            if (this.checkResults.length > 0 && !this.completedSteps.includes(4)) this.completedSteps.push(4);
            if (this.spunContent && !this.completedSteps.includes(5)) this.completedSteps.push(5);
            if (this.spunContent && !this.completedSteps.includes(6)) this.completedSteps.push(6);

            const finishRestore = () => {
                this._restoring = false;
                if (shouldPersistRestoredDraftState) this.autoSaveDraft();
                // Auto-load authors and default author for selected site
                if (this.selectedSiteId) {
                    this.authorsLoading = true;
                    fetch('/publish/sites/' + this.selectedSiteId + '/authors', {
                        headers: { 'Accept': 'application/json' }
                    }).then(r => r.json()).then(d => {
                        if (d.authors) this.siteConn.authors = d.authors;
                        if (d.default_author && this.publishAuthorSource !== 'manual') {
                            this.publishAuthor = d.default_author;
                            this.publishAuthorSource = 'profile';
                        }
                        this.authorsLoading = false;
                    }).catch(() => { this.authorsLoading = false; });
                }
                // Always load prompt preview — default prompt exists even without template/preset
                this.refreshPromptPreview();
                if (this.featuredImageSearch && !this.featuredPhoto) {
                    this.searchFeaturedImage();
                }
            };

            if (this.selectedUser) {
                this.presetsLoading = true;
                this.templatesLoading = true;
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
                    if (this.selectedPreset) this.loadPresetFields('preset', this.selectedPreset);

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
                    if (this.selectedTemplate) this.loadPresetFields('template', this.selectedTemplate);

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

            this.$watch('currentStep', () => this.savePipelineState());
            this.$watch('completedSteps', () => this.savePipelineState());
            this.$watch('selectedUser', () => this.savePipelineState());
            this.$watch('selectedPresetId', () => this.savePipelineState());
            this.$watch('selectedTemplateId', () => this.savePipelineState());
            this.$watch('selectedSiteId', () => this.savePipelineState());
            this.$watch('publishAuthor', () => this.savePipelineState());
            this.$watch('sources', () => this.savePipelineState());
            this.$watch('checkResults', () => this.savePipelineState());
            this.$watch('aiModel', () => this.savePipelineState());
            this.$watch('articleTitle', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('spunContent', () => this.savePipelineState());
            this.$watch('editorContent', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('photoSuggestions', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('featuredImageSearch', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('featuredPhoto', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('featuredAlt', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('featuredCaption', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('featuredFilename', () => { this.savePipelineState(); if (!this._restoring) this.queueAutoSaveDraft(); });
            this.$watch('siteConn.status', () => this.savePipelineState());
            this.$watch('siteConn.authors', () => this.savePipelineState());
            this.$watch('newsResults', () => this.savePipelineState());
            // Auto-refresh prompt preview on any prompt-affecting change
            this.$watch('selectedTemplateId', () => { if (!this._restoring) this._queuePromptRefresh(); });
            this.$watch('selectedPresetId', () => { if (!this._restoring) this._queuePromptRefresh(); });
            this.$watch('customPrompt', () => { if (!this._restoring) this._queuePromptRefresh(); });
            this.$watch('newsMode', () => this.savePipelineState());
            this.$watch('newsCategory', () => this.savePipelineState());
        },

        savePipelineState() {
            const state = {
                _v: this._stateVersion,
                draftId: this.draftId,
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
                siteConnStatus: this.siteConn.status,
                siteConnMessage: this.siteConn.message,
                siteConnLog: this.siteConn.log,
                siteConnAuthors: this.siteConn.authors,
                sources: this.sources,
                checkResults: this.checkResults,
                approvedSources: this.approvedSources,
                discardedSources: this.discardedSources,
                expandedSources: this.expandedSources,
                aiModel: this.aiModel,
                articleTitle: this.articleTitle,
                publishAction: this.publishAction,
                publishAuthor: this.publishAuthor,
                publishAuthorSource: this.publishAuthorSource,
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
                featuredPhoto: this.featuredPhoto,
                featuredAlt: this.featuredAlt,
                featuredCaption: this.featuredCaption,
                featuredFilename: this.featuredFilename,
                resolvedPrompt: this.resolvedPrompt,
                articleDescription: this.articleDescription,
                aiDetectionResults: this.aiDetectionResults,
                aiDetectionRan: this.aiDetectionRan,
                aiDetectionAllPass: this.aiDetectionAllPass,
                // Find Articles state
                sourceTab: this.sourceTab,
                newsMode: this.newsMode,
                newsCategory: this.newsCategory,
                newsTrendingSelected: this.newsTrendingSelected,
                newsSearch: this.newsSearch,
                newsResults: this.newsResults,
                newsHasSearched: this.newsHasSearched,
            };
            localStorage.setItem(this.pipelineStateKey, JSON.stringify(state));
            localStorage.removeItem('publishPipelineState');
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
                this.selectedSite = this.sites.find(s => s.id == this.selectedSiteId) || null;
                if (this.selectedSite) {
                    if (!this._restoring) this.autoSaveDraft();
                    if (this._restoring) return;

                    this.testSiteConnection(this.selectedSiteId, this.csrfToken, {
                        onAuthorsLoaded: (authors) => {
                            // Authors loaded
                        },
                        onSuccess: (d) => {
                            if (d.default_author) { this.publishAuthor = d.default_author; this.publishAuthorSource = 'profile'; }
                            this.completeStep(2);
                            this.autoSaveDraft();
                            if (!this._restoring) this.openStep(3);
                        },
                    });
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

        // ── Step 3: Sources ───────────────────────────────
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

        async searchNews() {
            if (this.newsMode === 'keyword' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'local' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'genre' && !this.newsCategory && !this.newsSearch.trim()) return;
            if (this.newsMode === 'trending' && !this.newsTrendingSelected) return;
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
                    })
                });
                const data = await resp.json();
                this.newsResults = (data.data && data.data.articles) ? data.data.articles : [];
                this.newsHasSearched = true;
            } catch (e) {
                this.newsResults = [];
                this.newsHasSearched = true;
                this.showNotification('error', 'Search failed: ' + e.message);
            } finally {
                this.newsSearching = false;
            }
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
            try {
                await fetch('{{ route("publish.failed-sources.store") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        url: source.url,
                        title: source.title || result?.title || '',
                        error_message: result?.message || 'Extraction failed',
                        source_api: result?.source_api || '',
                    })
                });
            } catch(e) {}
            this.removeSource(idx);
            this.showNotification('success', 'Saved to failed list and removed.');
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
                const sourceTexts = this.approvedSources.length > 0
                    ? this.approvedSources.map(i => this.checkResults[i]?.text || '').filter(Boolean)
                    : ['[Source articles will be inserted here]'];
                const resp = await fetch('{{ route("publish.pipeline.preview-prompt") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        source_texts: sourceTexts,
                        template_id: this.selectedTemplateId || null,
                        preset_id: this.selectedPresetId || null,
                        custom_prompt: this.customPrompt || null,
                    })
                });
                const data = await resp.json();
                if (data.success) this.resolvedPrompt = data.prompt;
            } catch (e) { /* silent */ }
            this.promptLoading = false;
        },

        async spinArticle() {
            this.spinning = true;
            this.spinError = '';
            this.spinLog = [];
            this._logSpin('info', 'Starting article spin...');
            this._logSpin('step', 'Model: ' + this.aiModel);
            this._logSpin('step', 'Sources: ' + this.checkResults.filter(r => r.success).length);
            if (this.customPrompt) this._logSpin('step', 'Custom instructions: ' + this.customPrompt.substring(0, 100));
            if (this.selectedTemplate) this._logSpin('step', 'Template: ' + this.selectedTemplate.name);
            if (this.selectedPreset) this._logSpin('step', 'Preset: ' + this.selectedPreset.name);

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
                        this.photoSuggestions = data.photo_suggestions.map(ps => ({...ps, autoPhoto: null, confirmed: false, removed: false, searchResults: []}));
                    }

                    this.queueAutoSaveDraft(300);

                    // Auto-run AI detection after spin
                    this.$nextTick(() => this.runAiDetection());
                    // Auto-advance to Create Article step
                    this.completeStep(5);
                    this.openStep(6);
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
                        photo_meta: this.photoSuggestions.filter(p => !p.removed && p.autoPhoto).map((p, i) => ({
                            alt_text: p.alt_text || '',
                            caption: p.caption || '',
                            filename: p.suggestedFilename || this.buildFilename(p.search_term, i + 1),
                        })),
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
                    this.completeStep(7);
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
                        user_id: this.selectedUser?.id || null,
                        site_id: this.selectedSite?.id || null,
                        preset_id: this.selectedPresetId || null,
                        template_id: this.selectedTemplateId || null,
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
