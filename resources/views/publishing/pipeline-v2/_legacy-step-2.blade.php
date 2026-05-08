    <div class="pipeline-step-card" :class="{ 'ring-2 ring-blue-400': currentStep === 2, 'opacity-50': !isStepAccessible(2) }">
        <button @click="toggleStep(2)" :disabled="!isStepAccessible(2)" class="pipeline-step-toggle disabled:cursor-not-allowed">
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
        <div x-show="openSteps.includes(2)" x-cloak x-collapse class="pipeline-step-panel space-y-4">

            {{-- Article Type --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Article Type</label>
                <div x-show="templatesLoading" class="flex items-center gap-2 text-sm text-blue-500 py-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading...
                </div>
                <select x-show="!templatesLoading" id="article-type-select" x-model="template_overrides.article_type"
                    @change="handleArticleTypeChange()"
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

