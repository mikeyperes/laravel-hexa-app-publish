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
                    <span x-text="publishAuthor || 'Not set'" :class="!publishAuthor ? 'text-orange-500' : ''"></span>
                    <a x-show="publishAuthor && selectedSite" x-cloak :href="(selectedSite?.url || '').replace(/\/$/, '') + '/author/' + publishAuthor + '/'" target="_blank" class="text-blue-500 hover:text-blue-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <span x-show="publishAuthorSource === 'profile'" x-cloak class="text-[10px] text-gray-400">(from site profile)</span>
                    <a x-show="!publishAuthor && selectedSite" x-cloak :href="'/publish/sites/' + selectedSite.id" target="_blank" class="text-xs text-orange-500 hover:text-orange-700">Set in site settings</a>
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
            <div x-show="currentArticleType !== 'press-release'" class="flex items-start gap-3 py-1.5">
                <span class="text-xs text-gray-400 w-24 flex-shrink-0 pt-0.5">Sources</span>
                <div class="space-y-0.5">
                    <template x-for="(s, idx) in sources" :key="idx">
                        <p class="text-xs text-gray-600 break-all" x-text="s.title || s.url"></p>
                    </template>
                </div>
            </div>
            <div x-show="currentArticleType === 'press-release'" x-cloak class="flex items-start gap-3 py-1.5">
                <span class="text-xs text-gray-400 w-24 flex-shrink-0 pt-0.5">Source</span>
                <div class="space-y-1">
                    <p class="text-xs text-gray-600" x-text="pressRelease.resolved_source_label || pressRelease.submit_method"></p>
                    <p x-show="pressRelease.public_url" class="text-xs text-gray-500 break-all" x-text="pressRelease.public_url"></p>
                    <p x-show="pressRelease.document_files.length > 0" class="text-xs text-gray-500" x-text="pressRelease.document_files.length + ' uploaded document(s)'"></p>
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
        <div x-show="!publishResult && (publishAction === 'publish' || publishAction === 'draft_wp' || publishAction === 'future')" x-cloak class="border border-gray-200 rounded-xl p-5 mb-4">
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
        <div x-show="!publishResult" x-cloak class="border border-gray-200 rounded-xl p-5 mb-4">
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
                <button @click="publishArticle()" :disabled="publishing || ((publishAction === 'publish' || publishAction === 'draft_wp' || publishAction === 'future') && !prepareComplete)" class="bg-green-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center gap-2">
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
                <div x-show="publishResult?.post_url" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Permalink</span><a :href="publishResult?.post_url" target="_blank" class="text-sm text-blue-600 hover:underline break-all inline-flex items-center gap-1" x-text="publishResult?.post_url"><svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></div>
                <div x-show="publishResult?.post_id && selectedSite?.url" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Edit URL</span><a :href="selectedSite?.url + '/?p=' + publishResult?.post_id" target="_blank" class="text-sm text-blue-600 hover:underline break-all inline-flex items-center gap-1" x-text="selectedSite?.url + '/?p=' + publishResult?.post_id"><svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></div>
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
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Tags</span><p class="text-sm text-gray-800 break-words" x-text="suggestedTags.length ? suggestedTags.join(', ') : 'None'"></p></div>
                {{-- Integrity check results --}}
                <div class="flex items-start gap-3 py-1">
                    <span class="text-xs text-gray-500 w-24 flex-shrink-0">Integrity</span>
                    <div class="text-sm">
                        <template x-if="!prepareIntegrityIssues || prepareIntegrityIssues.length === 0">
                            <span class="text-green-600 inline-flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                All checks passed
                            </span>
                        </template>
                        <template x-if="prepareIntegrityIssues && prepareIntegrityIssues.length > 0">
                            <div>
                                <span class="text-orange-600 font-medium" x-text="prepareIntegrityIssues.length + ' issue(s) auto-fixed'"></span>
                                <ul class="mt-1 space-y-0.5">
                                    <template x-for="issue in prepareIntegrityIssues" :key="issue">
                                        <li class="text-xs text-orange-500" x-text="'• ' + issue"></li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Quick access buttons --}}
            <div x-show="selectedSite?.url" class="flex flex-wrap gap-2 mb-4">
                <a :href="publishResult?.post_url || (selectedSite?.url + '/?p=' + publishResult?.post_id)" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    View Post
                </a>
                <a :href="selectedSite?.url + '/wp-admin/post.php?post=' + publishResult?.post_id + '&action=edit'" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-700 text-white text-xs font-medium rounded-lg hover:bg-gray-800">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    WP Admin Edit
                </a>
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
