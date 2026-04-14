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

            {{-- Press Release Syndication Categories (hexaprwire only) --}}
            <div x-show="currentArticleType === 'press-release' && selectedSite?.is_press_release_source" x-cloak class="mt-3">
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-purple-800">Syndication Categories</h5>
                        <button x-show="!syndicationCategories.length && !loadingSyndicationCats" @click="loadSyndicationCategories()" class="text-xs text-purple-600 hover:text-purple-800 inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Load from WordPress
                        </button>
                        <span x-show="loadingSyndicationCats" x-cloak class="text-xs text-purple-500 inline-flex items-center gap-1">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Loading...
                        </span>
                    </div>
                    <p class="text-xs text-purple-600 mb-3">Select categories to determine where this press release will be syndicated.</p>
                    <div x-show="syndicationCategories.length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-1">
                        <template x-for="cat in syndicationCategories" :key="cat.id">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 py-1">
                                <input type="checkbox" :value="cat.id" :checked="selectedSyndicationCats.includes(cat.id)" @click="toggleSyndicationCat(cat.id)" class="rounded border-gray-300 text-purple-600">
                                <span x-text="cat.name"></span>
                            </label>
                        </template>
                    </div>
                    <p x-show="!syndicationCategories.length && !loadingSyndicationCats" class="text-xs text-gray-400">Click "Load from WordPress" to fetch available syndication categories.</p>
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
                            <button @click="removeArticleLink(idx)" class="text-red-400 hover:text-red-600 flex-shrink-0">
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

            <div x-show="currentArticleType === 'press-release' && pressReleasePhotoAssets.length > 0" x-cloak class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h5 class="text-sm font-semibold text-blue-800">Press Release Photo Assets</h5>
                    <span class="text-xs text-blue-600" x-text="pressReleasePhotoAssets.length + ' asset(s)'"></span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <template x-for="asset in pressReleasePhotoAssets" :key="asset.key">
                        <div class="rounded-lg overflow-hidden border border-blue-100 bg-white">
                            <img :src="asset.thumbnail_url || asset.url" class="w-full h-36 object-cover" loading="lazy">
                            <div class="p-3 space-y-2">
                                <div>
                                    <p class="text-xs font-medium text-gray-800 break-words" x-text="asset.label"></p>
                                    <p class="text-[11px] text-gray-400 break-words" x-text="asset.source_label"></p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button @click="setPressReleaseFeaturedPhoto(asset)" type="button" class="text-[11px] text-blue-700 hover:text-blue-900">Set Featured</button>
                                    <button @click="insertPressReleaseAssetIntoEditor(asset)" type="button" class="text-[11px] text-green-700 hover:text-green-900">Insert</button>
                                    <a :href="asset.url" target="_blank" class="text-[11px] text-gray-500 hover:text-gray-700">Open</a>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Featured Image — same card layout as inline photos --}}
            <div x-show="featuredImageSearch" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4" x-data="{ featuredExpanded: false }">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">Featured Image</h5>
                <div class="border rounded-lg overflow-hidden" :class="featuredPhoto ? 'border-green-300 bg-green-50' : 'border-purple-200 bg-white'">
                    <div class="p-3">
                        <div class="flex items-start gap-3">
                            {{-- Thumbnail --}}
                            <div class="w-40 h-28 flex-shrink-0 rounded overflow-hidden bg-gray-100">
                                <img x-show="featuredPhoto" x-cloak :src="featuredPhoto?.url_large || featuredPhoto?.url_thumb" class="w-full h-full object-cover">
                                <div x-show="!featuredPhoto" class="w-full h-full flex items-center justify-center text-gray-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            </div>
                            {{-- Info + metadata --}}
                            <div class="flex-1 min-w-0 space-y-1.5">
                                <p class="text-sm font-medium text-purple-700 break-words" x-text="featuredImageSearch"></p>
                                <p x-show="featuredPhoto" x-cloak class="text-[11px] text-gray-400" x-text="(featuredPhoto?.source || '') + ' — ' + (featuredPhoto?.width || '') + 'x' + (featuredPhoto?.height || '')"></p>
                                <div x-show="featuredPhoto" x-cloak class="space-y-0.5 max-w-lg">
                                    <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Alt</label><input type="text" x-model="featuredAlt" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Alt text..."></div>
                                    <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Caption</label><input type="text" x-model="featuredCaption" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Caption..."></div>
                                    <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Filename</label><p class="text-[11px] font-mono text-gray-500" x-text="featuredFilename || 'auto'"></p></div>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <button @click.stop="refreshFeaturedMeta()" :disabled="featuredRefreshingMeta" class="text-[11px] text-purple-500 hover:text-purple-700 inline-flex items-center gap-1 disabled:opacity-50">
                                        <svg class="w-3 h-3" :class="featuredRefreshingMeta ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        <span x-text="featuredRefreshingMeta ? 'Generating...' : 'AI Refresh Metadata'"></span>
                                    </button>
                                </div>
                                <div x-show="featuredPhoto" x-cloak class="mt-1">
                                    <span class="text-[10px] text-gray-400" x-text="(featuredPhoto?.source || 'unknown')"></span>
                                    <a x-show="featuredPhoto?.source_url || featuredPhoto?.url_large" :href="featuredPhoto?.source_url || featuredPhoto?.url_large" target="_blank" class="text-[10px] text-blue-500 hover:text-blue-700 break-all" x-text="(featuredPhoto?.source_url || featuredPhoto?.url_large || '').substring(0, 80)"></a>
                                </div>
                                {{-- Change Photo / Import URL / Upload --}}
                                <div class="mt-2" x-data="{ showFeaturedUrl: false, featuredUrlVal: '' }">
                                    <div class="flex items-center gap-1.5">
                                        <button @click.stop="
                                            if (!featuredExpanded) {
                                                featuredExpanded = true;
                                                setTimeout(() => {
                                                    const card = $el.closest('.rounded-lg');
                                                    const picker = card?.querySelector('[data-photo-picker]');
                                                    if (picker) Alpine.$data(picker).loadTerm(featuredImageSearch);
                                                }, 300);
                                            } else { featuredExpanded = false; }
                                        " class="text-[11px] text-blue-500 hover:text-blue-700 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>
                                            Change Photo
                                        </button>
                                        <span class="text-gray-300">|</span>
                                        <button @click.stop="showFeaturedUrl = !showFeaturedUrl" class="text-[11px] text-blue-500 hover:text-blue-700">Import URL</button>
                                        <span class="text-gray-300">|</span>
                                        <label class="text-[11px] text-gray-500 hover:text-gray-700 cursor-pointer">
                                            Upload
                                            <input type="file" class="hidden" accept="image/*" @change="uploadFeaturedPhoto($event.target.files); $event.target.value = null">
                                        </label>
                                        <span class="text-gray-300">|</span>
                                        <button x-show="featuredPhoto" x-cloak @click="featuredPhoto = null; featuredAlt = ''; featuredCaption = ''; featuredFilename = ''" class="text-[11px] text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                    <div x-show="showFeaturedUrl" x-cloak class="flex gap-1.5 mt-1.5">
                                        <input type="text" x-model="featuredUrlVal" class="flex-1 border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Paste image URL...">
                                        <button @click.stop="if(featuredUrlVal.trim()){featuredPhoto={url_large:featuredUrlVal.trim(),url_thumb:featuredUrlVal.trim(),source:'url-import',alt:'',width:0,height:0};featuredAlt='';featuredCaption='';featuredFilename='auto';featuredUrlVal='';showFeaturedUrl=false;}" class="text-[11px] bg-blue-600 text-white px-2 py-1 rounded">Import</button>
                                    </div>
                                </div>
                            </div>
                            {{-- Action buttons --}}
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <span x-show="featuredPhoto" x-cloak class="text-xs text-green-600 font-medium px-1">Featured</span>
                            </div>
                        </div>
                    </div>
                    {{-- Expanded: photo picker --}}
                    <div x-show="featuredExpanded" x-cloak class="p-3 pt-2 border-t border-gray-100">
                        @include('app-publish::publishing.pipeline.partials.photo-picker', [
                            'pickerId' => 'featured-picker',
                            'searchQuery' => '',
                            'onSelect' => 'function(photo) { featuredPhoto = photo; featuredAlt = photo.alt || \'\'; featuredCaption = \'\'; featuredFilename = \'auto\'; }',
                        ])
                    </div>
                </div>
            </div>

            {{-- Photo Suggestions Panel --}}
            <div class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4" data-photo-section>
                <div class="flex items-center justify-between mb-3">
                    <h5 class="text-sm font-semibold text-gray-700">Inline Article Photos</h5>
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
                                        <div x-show="ps.autoPhoto || ps.alt_text || ps.caption" x-cloak class="space-y-0.5 max-w-lg">
                                            <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Alt</label><input type="text" x-model="photoSuggestions[idx].alt_text" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Alt text..."></div>
                                            <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Caption</label><input type="text" x-model="photoSuggestions[idx].caption" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Caption..."></div>
                                            <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Filename</label><p class="text-[11px] font-mono text-gray-500" x-text="photoSuggestions[idx].suggestedFilename || 'auto'"></p></div>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <button @click.stop="refreshPhotoMeta(idx)" :disabled="ps.refreshingMeta" class="text-[11px] text-purple-500 hover:text-purple-700 inline-flex items-center gap-1 disabled:opacity-50">
                                                <svg class="w-3 h-3" :class="ps.refreshingMeta ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                <span x-text="ps.refreshingMeta ? 'Generating...' : 'AI Refresh Metadata'"></span>
                                            </button>
                                        </div>
                                        {{-- Source URL display (when imported from URL) --}}
                                        <div x-show="ps.autoPhoto?.source === 'url-import'" x-cloak class="mt-1">
                                            <span class="text-[10px] text-gray-400">Source:</span>
                                            <a :href="ps.autoPhoto?.url_large" target="_blank" class="text-[10px] text-blue-500 hover:text-blue-700 break-all inline-flex items-center gap-0.5">
                                                <span x-text="(ps.autoPhoto?.url_large || '').substring(0, 80)"></span>
                                                <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        </div>
                                        {{-- Import URL / Upload for this photo --}}
                                        <div class="mt-2" x-data="{ showUrlInput: false, photoUrlVal: '' }">
                                            <div class="flex items-center gap-1.5">
                                                <button @click.stop="
                                                    if (!expandedSuggestions.includes(idx)) {
                                                        expandedSuggestions.push(idx);
                                                        setTimeout(() => {
                                                            const card = $el.closest('.rounded-lg');
                                                            const picker = card?.querySelector('[data-photo-picker]');
                                                            if (picker) Alpine.$data(picker).loadTerm(ps.search_term);
                                                        }, 300);
                                                    } else {
                                                        expandedSuggestions = expandedSuggestions.filter(i => i !== idx);
                                                    }
                                                " class="text-[11px] text-blue-500 hover:text-blue-700 inline-flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>
                                                    Change Photo
                                                </button>
                                                <span class="text-gray-300">|</span>
                                                <button @click.stop="showUrlInput = !showUrlInput" class="text-[11px] text-blue-500 hover:text-blue-700">Import URL</button>
                                                <span class="text-gray-300">|</span>
                                                <label class="text-[11px] text-gray-500 hover:text-gray-700 cursor-pointer">
                                                    Upload
                                                    <input type="file" class="hidden" accept="image/*" @change.stop="
                                                        const file = $event.target.files[0]; if (!file) return;
                                                        const url = URL.createObjectURL(file);
                                                        photoSuggestions[idx].autoPhoto = { url_large: url, url_thumb: url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: 0, height: 0 };
                                                        photoSuggestions[idx].confirmed = false;
                                                        $event.target.value = null;
                                                    ">
                                                </label>
                                            </div>
                                            <div x-show="showUrlInput" x-cloak class="flex gap-1.5 mt-1.5">
                                                <input type="text" x-model="photoUrlVal" class="flex-1 border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Paste image URL...">
                                                <button @click.stop="
                                                    if (photoUrlVal.trim()) {
                                                        photoSuggestions[idx].autoPhoto = { url_large: photoUrlVal.trim(), url_thumb: photoUrlVal.trim(), source: 'url-import', alt: '', width: 0, height: 0 };
                                                        photoSuggestions[idx].confirmed = false;
                                                        photoUrlVal = '';
                                                        showUrlInput = false;
                                                    }
                                                " class="text-[11px] bg-blue-600 text-white px-2 py-1 rounded">Import</button>
                                            </div>
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
                            {{-- Expanded: photo picker (stock + Google) --}}
                            <div x-show="expandedSuggestions.includes(idx)" x-cloak class="p-3 pt-2 border-t border-gray-100">
                                @include('app-publish::publishing.pipeline.partials.photo-picker', [
                                    'pickerId' => 'inline-picker',
                                    'searchQuery' => '',
                                    'autoLoadStock' => true,
                                    'onSelect' => 'function(photo) { selectPhotoForSuggestion(idx, photo); expandedSuggestions = expandedSuggestions.filter(i => i !== idx); }',
                                ])
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
