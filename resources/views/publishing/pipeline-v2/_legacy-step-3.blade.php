    <div class="pipeline-step-card" :class="{ 'ring-2 ring-blue-400': currentStep === 3, 'opacity-50': !isStepAccessible(3) }">
        <button @click="toggleStep(3)" :disabled="!isStepAccessible(3)" class="pipeline-step-toggle disabled:cursor-not-allowed">
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
        <div x-show="openSteps.includes(3)" x-cloak x-collapse class="pipeline-step-panel">

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
                <div x-show="currentArticleType === 'expert-article' || currentArticleType === 'pr-full-feature'" class="bg-white border border-gray-200 rounded-xl p-6 flex flex-col gap-4">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-gray-800" x-text="currentArticleType === 'pr-full-feature' ? 'PR Full Feature' : 'Expert Article'"></h4>
                            <p class="text-xs text-gray-400" x-text="currentArticleType === 'pr-full-feature' ? 'Build a polished editorial feature around one or more Notion subjects.' : 'Build a topic-first expert article and weave the Notion subject in as the authority voice.'"></p>
                        </div>
                    </div>

                    <div class="rounded-xl border p-4 space-y-4" :class="prFieldHasError('main_subject') || prFieldHasError('focus_instructions') ? 'border-red-300 bg-red-50/40 ring-1 ring-red-100' : 'border-blue-200 bg-blue-50/60'" style="order: 2;">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900">Article Direction</h5>
                                <p class="text-xs text-gray-600 mt-1" x-show="currentArticleType === 'pr-full-feature'">Select the main subject, define the editorial angle, and explain what the writer must emphasize. The article should market the client through credible feature writing, not overt promotion.</p>
                                <p class="text-xs text-gray-600 mt-1" x-show="currentArticleType === 'expert-article'">Define the topic, the subject's position, and whether the subject should lead visually or appear more subtly. The article should stay topic-first while using the client as the expert authority.</p>
                            </div>
                            <div class="w-full max-w-sm">
                                <x-hexa-tooltip mode="collapse" title="Writer guidance" label="Show writing checklist" emoji="✍️" tone="blue">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Explain the editorial angle, not just the topic.</li>
                                        <li>Name whose perspective should lead the article.</li>
                                        <li>Call out which related records or subjects must be woven in.</li>
                                        <li>Describe how promotional or restrained the tone should feel.</li>
                                        <li>List any must-hit quotes, positioning, or talking points.</li>
                                    </ul>
                                </x-hexa-tooltip>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Main Subject <span class="text-red-500">*</span></label>
                                <select x-model="prArticle.main_subject_id" @change="clearPrValidationError('main_subject'); savePipelineState()" :disabled="selectedPrProfiles.length === 0" class="w-full border rounded-lg px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed" :class="prInputBorderClass('main_subject')">
                                    <option value="">Choose main subject…</option>
                                    <template x-for="profile in selectedPrProfiles" :key="'subject-' + profile.id">
                                        <option :value="profile.id" x-text="profile.name"></option>
                                    </template>
                                </select>
                                <p x-show="prFieldHasError('main_subject')" x-cloak class="mt-1 text-xs font-medium text-red-600">Choose the subject who should anchor the article.</p>
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
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Article Focus Instructions <span class="text-red-500">*</span></label>
                                <textarea x-model="prArticle.focus_instructions" @input="clearPrValidationError('focus_instructions'); savePipelineState()" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm resize-y" :class="prInputBorderClass('focus_instructions')" placeholder="Explain the main angle, what the writer should emphasize, what to mention or avoid, which subject matters most, and how the article should frame the story."></textarea>
                                <p x-show="prFieldHasError('focus_instructions')" x-cloak class="mt-1 text-xs font-medium text-red-600">Tell the writer how to use the chosen context article in the feature.</p>
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

                        <div x-show="currentArticleType === 'expert-article' || currentArticleType === 'pr-full-feature'" x-cloak class="space-y-4">
                            <div x-show="currentArticleType === 'expert-article'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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

                            <div class="rounded-xl border p-4 space-y-4" :class="prCardBorderClass('context_article')">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h6 class="text-sm font-semibold text-gray-800"><span x-text="currentArticleType === 'pr-full-feature' ? 'Editorial Context Article' : 'Topic Context Article'"></span> <span class="text-red-500">*</span></h6>
                                        <p class="text-xs text-gray-500">Choose one real context article. It can come from selected Notion related content below or from the same editorial search/import tools used elsewhere in the pipeline.</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700" x-text="currentPrContextStatusLabel()"></span>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="prContextTab = 'notion'" class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors" :class="prContextTab === 'notion' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Selected Notion</button>
                                    <button type="button" @click="prContextTab = 'ai'" class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors" :class="prContextTab === 'ai' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Search Online</button>
                                    <button type="button" @click="prContextTab = 'search'" class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors" :class="prContextTab === 'search' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Search News</button>
                                    <button type="button" @click="prContextTab = 'bookmarks'; loadBookmarks()" class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors" :class="prContextTab === 'bookmarks' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Bookmarks</button>
                                    <button type="button" @click="prContextTab = 'url'" class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors" :class="prContextTab === 'url' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">Paste Link</button>
                                </div>

                                <div x-show="prContextTab === 'notion'" x-cloak class="space-y-3">
                                    <div x-show="hasSelectedPrContextEntries()" x-cloak class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 space-y-2">
                                        <p class="text-sm font-semibold text-emerald-800">Selected Notion context article(s)</p>
                                        <div class="space-y-2">
                                            <template x-for="item in prSelectedContextEntries()" :key="'pr-context-' + item.profile.id + '-' + item.entry.id">
                                                <div class="rounded-lg border border-emerald-100 bg-white px-3 py-2">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="text-sm font-medium text-gray-900 break-words" x-text="item.entry.title"></p>
                                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700" x-text="item.relation.label || 'Related Content'"></span>
                                                    </div>
                                                    <p class="mt-1 text-xs text-gray-500" x-text="'Selected under ' + (item.profile.name || 'subject')"></p>
                                                    <a x-show="prContextUrlFromEntry(item.entry)" x-cloak :href="prContextUrlFromEntry(item.entry)" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 underline decoration-blue-200 underline-offset-2">
                                                        <span x-text="prContextUrlFromEntry(item.entry)"></span>
                                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                    </a>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="!hasSelectedPrContextEntries()" x-cloak class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-3 text-sm text-gray-500">
                                        Select one of the related Notion articles below and it will count as context automatically.
                                    </div>
                                </div>

                                <div x-show="prContextTab === 'ai'" x-cloak class="space-y-3">
                                    <div class="flex gap-2">
                                        <input type="text" x-model="aiSearchTopic" @keydown.enter="aiSearchArticles()" class="flex-1 border rounded-lg px-3 py-2 text-sm" :class="prInputBorderClass('context_article')" placeholder="Search online for one article to use as context">
                                        <button @click="aiSearchArticles()" :disabled="aiSearching || !aiSearchTopic.trim()" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                                            <svg x-show="aiSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span x-text="aiSearching ? 'Searching…' : 'Find Articles'"></span>
                                        </button>
                                    </div>
                                    <div x-show="aiSearchResults.length > 0" x-cloak class="space-y-2 max-h-72 overflow-y-auto">
                                        <template x-for="(article, idx) in aiSearchResults" :key="'pr-ai-' + idx">
                                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-semibold text-gray-900 break-words" x-text="article.title"></p>
                                                        <a :href="article.url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                                            <span x-text="article.url"></span>
                                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                        </a>
                                                        <p x-show="article.description" x-cloak class="mt-1 text-xs text-gray-500 break-words" x-text="article.description"></p>
                                                    </div>
                                                    <button type="button" @click="usePrContextArticle(article)" class="flex-shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Use as Context</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="prContextTab === 'search'" x-cloak class="space-y-3">
                                    <div class="flex gap-2">
                                        <input type="text" x-model="newsSearch" @keydown.enter="searchNews()" class="flex-1 border rounded-lg px-3 py-2 text-sm" :class="prInputBorderClass('context_article')" placeholder="Search news for one context article">
                                        <button @click="searchNews()" :disabled="newsSearching" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                                            <svg x-show="newsSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span x-text="newsSearching ? 'Searching…' : 'Search News'"></span>
                                        </button>
                                    </div>
                                    <div x-show="newsResults.length > 0 && !newsSearching" x-cloak class="space-y-2 max-h-72 overflow-y-auto">
                                        <template x-for="(article, idx) in newsResults" :key="'pr-news-' + idx">
                                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-semibold text-gray-900 break-words" x-text="article.title"></p>
                                                        <a :href="article.url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                                            <span x-text="article.url"></span>
                                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                        </a>
                                                        <p x-show="article.description" x-cloak class="mt-1 text-xs text-gray-500 break-words" x-text="article.description"></p>
                                                    </div>
                                                    <button type="button" @click="usePrContextArticle(article)" class="flex-shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Use as Context</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="prContextTab === 'bookmarks'" x-cloak class="space-y-2">
                                    <div x-show="bookmarksLoading" x-cloak class="text-sm text-gray-500">Loading bookmarks...</div>
                                    <div x-show="!bookmarksLoading && bookmarks.length === 0" x-cloak class="text-sm text-gray-500">No bookmarks found for this user.</div>
                                    <div x-show="bookmarks.length > 0" x-cloak class="space-y-2 max-h-72 overflow-y-auto">
                                        <template x-for="bm in bookmarks" :key="'pr-bookmark-' + bm.id">
                                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-semibold text-gray-900 break-words" x-text="bm.title || bm.url"></p>
                                                        <a :href="bm.url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                                            <span x-text="bm.url"></span>
                                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                        </a>
                                                    </div>
                                                    <button type="button" @click="usePrContextArticle({ url: bm.url, title: bm.title })" class="flex-shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Use as Context</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="prContextTab === 'url'" x-cloak class="space-y-3">
                                    <div class="flex flex-col lg:flex-row gap-3">
                                        <input type="url" x-model="prArticle.expert_context_url" @input="clearPrValidationError('context_article'); savePipelineState()" class="flex-1 border rounded-lg px-3 py-2 text-sm" :class="prInputBorderClass('context_article')" placeholder="Paste a live article URL to import as context">
                                        <button @click="usePrContextArticle({ url: prArticle.expert_context_url })" type="button" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50" :disabled="prArticleContextImporting || !prArticle.expert_context_url">
                                            <svg x-show="prArticleContextImporting" x-cloak class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span x-text="prArticleContextImporting ? 'Importing…' : 'Import Article Context'"></span>
                                        </button>
                                    </div>
                                </div>

                                <div x-show="prArticle.expert_context_extracted?.title || prArticle.expert_context_extracted?.text" x-cloak class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900" x-text="prArticle.expert_context_extracted?.title || 'Imported context article'"></p>
                                            <p class="text-xs text-gray-500 mt-1" x-text="(prArticle.expert_context_extracted?.word_count || 0) + ' words imported from the context article'"></p>
                                        </div>
                                        <button type="button" @click="prArticle.expert_context_url = ''; prArticle.expert_context_extracted = {}; savePipelineState()" class="text-xs font-medium text-gray-500 hover:text-red-600">Clear</button>
                                    </div>
                                    <p x-show="prArticle.expert_context_extracted?.excerpt" x-cloak class="text-sm text-gray-700 mt-2" x-text="prArticle.expert_context_extracted?.excerpt"></p>
                                </div>

                                <p x-show="prFieldHasError('context_article')" x-cloak class="text-xs font-medium text-red-600">Select a Notion article below or import one from the editorial search tools before continuing.</p>
                            </div>
                        </div>
                    </div>

                    {{-- PR Subject Picker --}}
                    <div class="space-y-3 rounded-xl p-3" :class="prFieldHasError('subjects') ? 'border border-red-300 bg-red-50/30 ring-1 ring-red-100' : ''" style="order: 1;">
                        <label class="text-sm font-medium text-gray-700">Select PR Subjects <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" x-model="prProfileSearch" @input.debounce.300ms="clearPrValidationError('subjects'); searchPrProfiles()" @focus="searchPrProfiles()"
                                placeholder="Search Notion people and companies..."
                                class="w-full border rounded-lg px-4 py-2.5 text-sm" :class="prInputBorderClass('subjects')">
                            <svg x-show="prProfileSearching" x-cloak class="absolute right-3 top-2.5 w-5 h-5 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>

                            {{-- Search results dropdown --}}
                            <div x-show="prProfileResults.length > 0 && prProfileDropdownOpen" x-cloak @click.away="prProfileDropdownOpen = false"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                <template x-for="profile in prProfileResults" :key="profile.id">
                                    <button @click="addPrProfile(profile); prProfileDropdownOpen = false;"
                                        class="w-full text-left px-4 py-3 hover:bg-blue-50 border-b border-gray-100 last:border-b-0 flex items-center gap-3"
                                        :class="selectedPrProfiles.some(p => String(p.id || '') === String(profile.id || profile.local_profile_id || '') || (p.external_source === 'notion' && String(p.external_id || '') === String(profile.external_id || ''))) ? 'opacity-40 cursor-not-allowed' : ''"
                                        :disabled="selectedPrProfiles.some(p => String(p.id || '') === String(profile.id || profile.local_profile_id || '') || (p.external_source === 'notion' && String(p.external_id || '') === String(profile.external_id || '')))">
                                        <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            <img x-show="profile.photo_url" x-cloak :src="profile.photo_url" class="w-full h-full object-cover">
                                            <svg x-show="!profile.photo_url" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="profile.name"></p>
                                            <p x-show="(profile.type && profile.type !== '—') || profile.description" x-cloak class="text-xs text-gray-400" x-text="[profile.type && profile.type !== '—' ? profile.type : '', profile.description ? profile.description.substring(0, 60) : ''].filter(Boolean).join(' — ')"></p>
                                        </div>
                                        <span x-show="selectedPrProfiles.some(p => String(p.id || '') === String(profile.id || profile.local_profile_id || '') || (p.external_source === 'notion' && String(p.external_id || '') === String(profile.external_id || '')))" x-cloak class="text-xs text-gray-400">Added</span>
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
                                            <p x-show="(profile.type && profile.type !== '—') || profile.description" x-cloak class="text-xs text-gray-500" x-text="[profile.type && profile.type !== '—' ? profile.type : '', profile.description ? profile.description.substring(0, 80) : ''].filter(Boolean).join(' — ')"></p>
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
                                        @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                            'heading' => 'Profile Data',
                                            'rowsExpression' => 'prSubjectData[profile.id]?.fields || []',
                                            'rowKey' => "'profile-field-' + (row.key || row.notion_field || rowIdx)",
                                            'labelExpression' => "row.notion_field || row.key || ''",
                                            'sourceExpression' => 'notionProfileFieldSourceLabel(profile, row)',
                                            'valueExpression' => "row.display_value || row.value || ''",
                                            'linkUrls' => true,
                                            'panelClass' => 'bg-white rounded-lg border border-gray-200 p-3',
                                        ])
                                    </div>

                                    <div x-show="prSubjectData[profile.id]?.googleDocs?.length > 0" x-cloak class="px-5 py-3 border-b border-gray-100">
                                        <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Imported Google Docs Context</h5>
                                        <div class="space-y-2">
                                            <template x-for="doc in (prSubjectData[profile.id]?.googleDocs || [])" :key="doc.document_id || doc.url">
                                                <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <p class="text-xs font-semibold text-gray-900" x-text="doc.title || 'Google Doc'"></p>
                                                        <a :href="doc.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[11px] text-blue-600 hover:text-blue-700 underline decoration-blue-200 underline-offset-2">
                                                            Open
                                                            <svg class="h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                        </a>
                                                    </div>
                                                    <p class="mt-1 text-xs text-gray-600 whitespace-pre-wrap" x-text="doc.preview"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Shared media selection --}}
                                    <div x-show="prPhotoCandidates(profile.id).length > 0 || prSubjectData[profile.id]?.driveUrl" x-cloak class="px-5 py-3 border-b border-gray-100">
                                        <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 px-3 py-3 mb-4 text-xs text-gray-700">
                                            <div class="flex flex-wrap items-start gap-3 justify-between">
                                                <div class="space-y-1.5">
                                                    <p class="font-semibold text-gray-900">Photo source audit</p>
                                                    <p>Direct subject photo fields: <span class="font-medium" x-text="prPhotoAudit(profile.id).directFields.length ? prPhotoAudit(profile.id).directFields.join(', ') : 'None detected'"></span></p>
                                                    <p>Google Drive folder media: <span class="font-medium" x-text="prPhotoAudit(profile.id).driveAvailable ? ((prPhotoAudit(profile.id).driveField ? prPhotoAudit(profile.id).driveField + ' • ' : '') + (prPhotoAudit(profile.id).driveLoaded ? 'Loaded and selectable' : 'Available but not loaded yet')) : 'Not configured'"></span></p>
                                                    <a x-show="prPhotoAudit(profile.id).driveUrl" x-cloak :href="prPhotoAudit(profile.id).driveUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 underline decoration-blue-200 underline-offset-2">
                                                        Open Drive folder
                                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                    </a>
                                                    <p x-show="prPhotoAudit(profile.id).featuredLabel" x-cloak>Featured image selected: <span class="font-medium" x-text="prPhotoAudit(profile.id).featuredLabel"></span></p>
                                                    <p x-show="prPhotoAudit(profile.id).featuredSourceLabel" x-cloak>Featured image source: <span class="font-medium" x-text="prPhotoAudit(profile.id).featuredSourceLabel"></span></p>
                                                    <p>Inline photos selected: <span class="font-medium" x-text="prPhotoAudit(profile.id).inlineSelectedCount"></span></p>
                                                    <p class="text-indigo-700">All subject photos are shown once below. Pick one featured image, then choose the inline body photos from the same gallery.</p>
                                                </div>
                                                <button x-show="prSubjectData[profile.id]?.driveUrl && !prPhotoAudit(profile.id).driveLoaded" x-cloak @click="loadDriveFallbackPhotos(profile.id)" :disabled="prSubjectData[profile.id]?.loadingPhotos" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 disabled:opacity-50">
                                                    <svg x-show="prSubjectData[profile.id]?.loadingPhotos" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                    <span x-text="prSubjectData[profile.id]?.loadingPhotos ? 'Loading Drive gallery…' : 'Load Drive gallery photos'"></span>
                                                </button>
                                            </div>
                                        </div>

                                        @include('app-publish::publishing.pipeline.partials.shared-media-picker', [
                                            'title' => 'Photo Assets',
                                            'description' => 'All candidate photos are shown once. Set one featured image and choose multiple inline photos from the same gallery.',
                                            'assetsExpression' => 'prPhotoCandidates(profile.id)',
                                            'assetKeyExpression' => 'asset.id',
                                            'featuredSelectedExpression' => 'isPrFeaturedPhotoSelected(profile.id, asset.id)',
                                            'inlineSelectedExpression' => 'isPrInlinePhotoSelected(profile.id, asset.id)',
                                            'thumbUrlExpression' => 'asset.thumbnailLink || asset.preview_url || asset.thumbnail_url || asset.webContentLink || asset.webViewLink',
                                            'labelExpression' => 'prFriendlyPhotoLabel(profile, asset, assetIdx + 1)',
                                            'sourceLabelExpression' => 'prPhotoOriginAuditLabel(profile, asset, prSubjectData[profile.id]?.driveUrl || "")',
                                            'sourceMetaHtmlExpression' => 'prPhotoSourceMetaHtml(profile, asset, prSubjectData[profile.id])',
                                            'downloadUrlExpression' => 'asset.download_url || asset.webContentLink || asset.webViewLink || asset.thumbnailLink',
                                            'viewUrlExpression' => 'asset.view_url || asset.source_url || asset.webViewLink || asset.webContentLink',
                                            'setFeaturedAction' => 'setPrFeaturedPhoto(profile.id, asset.id)',
                                            'toggleInlineAction' => 'togglePrInlinePhotoSelect(profile.id, asset.id)',
                                            'featuredButtonTextExpression' => 'isPrFeaturedPhotoSelected(profile.id, asset.id) ? "Featured Image" : "Set Featured"',
                                            'inlineButtonTextExpression' => 'isPrFeaturedPhotoSelected(profile.id, asset.id) ? "Featured image" : (isPrInlinePhotoSelected(profile.id, asset.id) ? "Inline Selected" : "Add Inline")',
                                            'inlineButtonDisabledExpression' => 'isPrFeaturedPhotoSelected(profile.id, asset.id)',
                                            'countBadgeExpression' => 'prPhotoCandidates(profile.id).length + " photo(s)"',
                                            'featuredBadgeExpression' => 'prSubjectData[profile.id]?.selectedFeaturedPhotoId ? "Featured selected" : "Featured not set"',
                                            'inlineBadgeExpression' => '"Inline selected: " + Object.keys(prSubjectData[profile.id]?.selectedInlinePhotos || {}).filter((key) => prSubjectData[profile.id]?.selectedInlinePhotos?.[key]).length',
                                            'showSelectAll' => true,
                                            'selectAllAction' => 'selectAllPrInlinePhotos(profile.id)',
                                            'clearInlineAction' => 'clearPrInlinePhotos(profile.id)',
                                        ])

                                        <div x-show="prSubjectData[profile.id]?.loadingPhotos" x-cloak class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            Loading photos...
                                        </div>
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
                                                        <div class="ml-auto flex items-center gap-2 text-[10px] font-medium">
                                                            <button @click="selectAllPrRelationEntries(profile.id, rel.slug)" type="button" class="text-indigo-600 hover:text-indigo-800">Select all</button>
                                                            <button @click="clearPrRelationEntries(profile.id, rel.slug)" type="button" class="text-gray-500 hover:text-gray-700">Select none</button>
                                                            <svg x-show="rel.loading" class="w-3 h-3 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                        </div>
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
                                                                            <template x-for="[k,v] in Object.entries(entry.detail.properties || {})" :key="k">
                                                                                <div class="flex gap-2">
                                                                                    <span class="text-gray-500 w-28 flex-shrink-0" x-text="k"></span>
                                                                                    <span class="text-gray-800 break-words" x-html="linkedValueHtml(typeof v === 'object' ? JSON.stringify(v) : v)"></span>
                                                                                </div>
                                                                            </template>
                                                                            <div x-show="entry.google_docs?.length > 0" x-cloak class="pt-2 space-y-2">
                                                                                <template x-for="doc in (entry.google_docs || [])" :key="doc.document_id || doc.url">
                                                                                    <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                                                                                        <div class="flex items-center justify-between gap-3">
                                                                                            <p class="text-xs font-semibold text-gray-900" x-text="doc.title || 'Google Doc'"></p>
                                                                                            <a :href="doc.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[11px] text-blue-600 hover:text-blue-700 underline decoration-blue-200 underline-offset-2">
                                                                                                Open
                                                                                                <svg class="h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                                                            </a>
                                                                                        </div>
                                                                                        <p class="mt-1 text-xs text-gray-600 whitespace-pre-wrap" x-text="doc.preview"></p>
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

                    <button @click="continuePrArticleStep3()"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700"
                        style="order: 3;">
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
