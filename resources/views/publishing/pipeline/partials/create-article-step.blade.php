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
            <span x-show="Number(spunWordCount || 0) > 0" x-cloak class="text-sm text-green-600" x-text="spunWordCount + ' words'"></span>
        </div>
        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(6) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="openSteps.includes(6)" x-cloak x-collapse class="px-4 pb-4">

        {{-- Spun content in TinyMCE editor --}}
        <div x-show="spunContent || editorContent || isStepAccessible(6)" x-cloak>
            <p x-show="!spunContent && !editorContent" x-cloak class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                This draft body is currently blank. The editor is still available so the draft can be repaired instead of collapsing into an empty card.
            </p>
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
            <div x-show="spunContent || editorContent" x-cloak class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">Categories <span class="font-normal text-gray-400" x-text="'(' + selectedCategories.length + ' selected of ' + suggestedCategories.length + ')'"></span></h5>
                    <div class="space-y-1">
                        <template x-for="(cat, idx) in suggestedCategories" :key="idx">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                <input type="checkbox" :checked="selectedCategories.includes(idx)" @click="toggleSelection(selectedCategories, idx)" class="rounded border-gray-300 text-green-600">
                                <span x-text="cat"></span>
                            </label>
                        </template>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <input type="text" x-model="customCategoryInput" @keydown.enter.prevent="addCustomCategory()" class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Add category manually">
                        <button @click="addCustomCategory()" type="button" class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs hover:bg-green-700">Add</button>
                    </div>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">Tags <span class="font-normal text-gray-400" x-text="'(' + selectedTags.length + ' selected of ' + suggestedTags.length + ')'"></span></h5>
                    <div class="space-y-1">
                        <template x-for="(tag, idx) in suggestedTags" :key="idx">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                <input type="checkbox" :checked="selectedTags.includes(idx)" @click="toggleSelection(selectedTags, idx)" class="rounded border-gray-300 text-blue-600">
                                <span x-text="tag"></span>
                            </label>
                        </template>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <input type="text" x-model="customTagInput" @keydown.enter.prevent="addCustomTag()" class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Add tag manually">
                        <button @click="addCustomTag()" type="button" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs hover:bg-blue-700">Add</button>
                    </div>
                </div>
            </div>
            {{-- Press Release Publication Taxonomy (hexaprwire only) --}}
            <div x-show="currentArticleType === 'press-release' && selectedSite?.is_press_release_source" x-cloak class="mt-3">
                <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 space-y-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h5 class="text-sm font-semibold text-purple-900">Publication Syndication</h5>
                            <p class="text-xs text-purple-700 mt-1">This uses the live hierarchical <span class="font-semibold">publication</span> taxonomy from Hexa PR Wire. All publications are selected by default.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span x-show="syndicationCategoriesCacheMeta?.age_human && syndicationCategories.length > 0" x-cloak class="inline-flex items-center gap-1 rounded-full bg-white border border-purple-200 px-2.5 py-1 text-purple-700">
                                <span>Cached</span>
                                <span x-text="syndicationCategoriesCacheMeta?.age_human || 'just now'"></span>
                            </span>
                            <button x-show="syndicationCategories.length > 0 && !loadingSyndicationCats" x-cloak @click="selectAllSyndicationCats()" type="button" class="rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-purple-700 hover:bg-purple-100">Select all</button>
                            <button x-show="syndicationCategories.length > 0 && !loadingSyndicationCats" x-cloak @click="clearSyndicationCats()" type="button" class="rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-purple-700 hover:bg-purple-100">Select none</button>
                            <button @click="syndicationCategories.length ? resyncSyndicationCategories() : loadSyndicationCategories()" :disabled="loadingSyndicationCats" type="button" class="rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-purple-700 hover:bg-purple-100 disabled:opacity-50 inline-flex items-center gap-1">
                                <svg x-show="loadingSyndicationCats" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="loadingSyndicationCats ? 'Syncing…' : (syndicationCategories.length ? 'Resync Source' : 'Load Taxonomy')"></span>
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-purple-600">Select the publications this release should syndicate to. Parent labels come directly from WordPress.</p>
                    <div x-show="loadingSyndicationCats" x-cloak class="rounded-lg border border-purple-200 bg-white px-3 py-3 text-sm text-purple-700 inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading publication taxonomy…
                    </div>
                    <div x-show="syndicationCategories.length > 0 && !loadingSyndicationCats" x-cloak class="space-y-1">
                        <template x-for="cat in syndicationCategories" :key="cat.id">
                            <label class="flex items-start gap-2 cursor-pointer rounded-lg px-3 py-2 text-sm transition-colors" :class="selectedSyndicationCats.includes(cat.id) ? 'border border-purple-200 bg-white text-gray-900' : 'text-gray-700 hover:bg-white/70'">
                                <input type="checkbox" :value="cat.id" :checked="selectedSyndicationCats.includes(cat.id)" @click="toggleSyndicationCat(cat.id)" class="mt-0.5 rounded border-gray-300 text-purple-600">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span :class="cat.is_parent ? 'font-semibold text-gray-900' : 'text-gray-700'" x-text="cat.label || cat.name"></span>
                                        <span x-show="cat.is_parent" x-cloak class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-purple-700">Parent</span>
                                    </div>
                                    <p class="text-[11px] text-gray-400 mt-0.5" x-text="cat.slug || ''"></p>
                                </div>
                            </label>
                        </template>
                    </div>
                    <p x-show="!syndicationCategories.length && !loadingSyndicationCats" x-cloak class="text-xs text-gray-500">Load the live publication taxonomy from WordPress, then confirm or trim the default all-publication selection.</p>
                </div>
            </div>

            {{-- Article Links --}}
            <div x-show="suggestedUrls.length > 0" x-cloak class="mt-3 bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <h5 class="text-sm font-semibold text-gray-700">Article Links <span class="font-normal text-gray-400" x-text="'(' + suggestedUrls.length + ')'"></span></h5>
                    <button @click="checkAllArticleLinks()" type="button" class="text-xs px-2.5 py-1 rounded border border-amber-300 text-amber-700 hover:bg-amber-50 disabled:opacity-50 inline-flex items-center gap-1" :disabled="checkingAllArticleLinks">
                        <svg x-show="checkingAllArticleLinks" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="checkingAllArticleLinks ? 'Checking…' : 'Test All 404s'"></span>
                    </button>
                </div>
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
                            <span x-show="link.status_text" x-cloak class="text-[10px] px-2 py-1 rounded font-medium flex-shrink-0" :class="link.status_tone === 'green' ? 'bg-green-100 text-green-700' : (link.status_tone === 'red' ? 'bg-red-100 text-red-700' : (link.status_tone === 'amber' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'))" x-text="link.status_text"></span>
                            <button @click="link.nofollow = !link.nofollow" class="text-xs px-2 py-1 rounded flex-shrink-0" :class="link.nofollow ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'" x-text="link.nofollow ? 'nofollow' : 'follow'"></button>
                            <button @click="checkArticleLinkStatus(idx)" type="button" class="text-xs px-2 py-1 rounded border border-amber-300 text-amber-700 hover:bg-amber-50 flex-shrink-0 inline-flex items-center gap-1" :disabled="link.checking">
                                <svg x-show="link.checking" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="link.checking ? 'Checking…' : 'Test 404'"></span>
                            </button>
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

            {{-- Main Search Terms — key people, places, things from the article --}}
            <div class="mt-4 bg-white border border-gray-200 rounded-lg p-4" x-show="suggestedTags.length > 0 || photoSuggestions.length > 0" x-cloak>
                <h5 class="text-sm font-semibold text-gray-700 mb-2">Main Search Terms</h5>
                <p class="text-xs text-gray-400 mb-3">Click to copy — use these to search for photos</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="term in [...new Set([...suggestedTags, ...photoSuggestions.map(p => p.search_term)].filter(Boolean))]" :key="term">
                        <button type="button" @click.stop="
                            navigator.clipboard.writeText(term);
                            $el.classList.add('bg-green-100', 'text-green-700', 'border-green-300');
                            $el.querySelector('span').textContent = 'Copied!';
                            setTimeout(() => { $el.classList.remove('bg-green-100', 'text-green-700', 'border-green-300'); $el.querySelector('span').textContent = term; }, 1500);
                        " class="px-3 py-1 border border-gray-200 rounded-full text-xs text-gray-700 hover:bg-gray-100 hover:border-gray-300 transition-colors inline-flex items-center gap-1">
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10"/></svg>
                            <span x-text="term"></span>
                        </button>
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
                            <div class="w-40 h-28 flex-shrink-0 rounded overflow-hidden bg-gray-100 relative">
                                <img x-show="featuredPhoto && !featuredThumbError" x-cloak data-featured-thumb :src="resolvePhotoThumbUrl(featuredPhoto)" class="w-full h-full object-cover transition-opacity" :class="featuredThumbLoading ? 'opacity-0' : 'opacity-100'" x-on:load="featuredThumbLoading = false; featuredThumbError = ''" x-on:error="featuredThumbLoading = false; featuredThumbError = 'Featured thumbnail failed to load'">
                                <div x-show="featuredPhoto && featuredThumbLoading" x-cloak class="absolute inset-0 w-full h-full flex items-center justify-center bg-purple-50/90">
                                    <svg class="w-5 h-5 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                                <div x-show="!featuredPhoto && featuredSearching" x-cloak class="w-full h-full flex items-center justify-center bg-purple-50">
                                    <svg class="w-5 h-5 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                                <div x-show="featuredPhoto && featuredThumbError" x-cloak class="w-full h-full flex flex-col items-center justify-center text-red-400 bg-red-50 px-2 text-center">
                                    <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86A2.07 2.07 0 0020.73 16L13.8 4a2.07 2.07 0 00-3.6 0L3.27 16A2.07 2.07 0 005.07 19z"/></svg>
                                    <span class="text-[10px] font-medium">Thumbnail failed</span>
                                </div>
                                <div x-show="!featuredPhoto && !featuredSearching" class="w-full h-full flex items-center justify-center text-gray-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            </div>
                            {{-- Info + metadata --}}
                            <div class="flex-1 min-w-0 space-y-1.5">
                                <p class="text-sm font-medium text-purple-700 break-words" x-text="featuredImageSearch"></p>
                                <p x-show="!featuredPhoto && featuredSearching" x-cloak class="text-[11px] text-amber-600">Loading suggestions...</p>
                                <p x-show="!featuredPhoto && !featuredSearching && featuredSearchPending" x-cloak class="text-[11px] text-amber-600">Suggestions load automatically while Create Article is open.</p>
                                <p x-show="featuredLoadError" x-cloak class="text-[11px] text-red-500" x-text="featuredLoadError"></p>
                                <p x-show="featuredThumbError" x-cloak class="text-[11px] text-red-500" x-text="featuredThumbError"></p>
                                <div x-show="featuredPhoto" x-cloak class="flex items-center gap-2 text-[11px]">
                                    <span class="text-gray-400" x-text="(featuredPhoto?.source || '') + ' — ' + (featuredPhoto?.width || '') + 'x' + (featuredPhoto?.height || '')"></span>
                                    <template x-if="featuredPhoto?.width && featuredPhoto?.height">
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium"
                                            :class="(() => {
                                                const r = featuredPhoto.width / featuredPhoto.height;
                                                if (r >= 1.3 && r <= 2.0) return 'bg-green-100 text-green-700';
                                                if (r >= 1.0 && r < 1.3) return 'bg-yellow-100 text-yellow-700';
                                                return 'bg-red-100 text-red-700';
                                            })()"
                                            x-text="(() => {
                                                const r = featuredPhoto.width / featuredPhoto.height;
                                                const label = r.toFixed(2) + ':1';
                                                if (r >= 1.3 && r <= 2.0) return label + ' Landscape';
                                                if (r >= 1.0 && r < 1.3) return label + ' Square';
                                                if (r < 1.0) return label + ' Portrait';
                                                return label + ' Ultra-wide';
                                            })()">
                                        </span>
                                    </template>
                                    <template x-if="featuredPhoto?.width && featuredPhoto?.height && (featuredPhoto.width / featuredPhoto.height) < 1.3">
                                        <span class="text-[10px] text-red-500">Bad for featured</span>
                                    </template>
                                </div>
                                <div x-show="featuredPhoto" x-cloak class="space-y-0.5 max-w-lg">
                                    <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Alt</label><input type="text" x-model="featuredAlt" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Alt text..."></div>
                                    <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Caption</label><input type="text" x-model="featuredCaption" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Caption..."></div>
                                    <div class="flex items-center gap-2"><label class="text-[10px] uppercase w-16 flex-shrink-0" :class="!featuredFilename || featuredFilename === 'auto' ? 'text-red-400 font-semibold' : 'text-gray-400'">Filename</label><p class="text-[11px] font-mono" :class="!featuredFilename || featuredFilename === 'auto' ? 'text-red-500' : 'text-gray-500'" x-text="featuredFilename || 'auto'"></p></div>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <button @click.stop="refreshFeaturedMeta()" :disabled="featuredRefreshingMeta" class="text-[11px] inline-flex items-center gap-1 disabled:opacity-50" :class="featuredPhotoNeedsMetadata() ? 'text-red-500 hover:text-red-700 font-semibold' : 'text-purple-500 hover:text-purple-700'">
                                        <svg class="w-3 h-3" :class="featuredRefreshingMeta ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        <span x-text="featuredRefreshingMeta ? 'Generating...' : 'Refresh Metadata'"></span>
                                    </button>
                                    <span x-show="featuredMetaGenerator" x-cloak class="text-[9px] px-1.5 py-0.5 rounded" :class="featuredMetaGenerator === 'local' ? 'bg-gray-100 text-gray-500' : 'bg-purple-100 text-purple-600'" x-text="featuredMetaGenerator === 'local' ? 'PHP' : 'AI'"></span>
                                    <template x-if="featuredPhotoNeedsMetadata()">
                                        <span class="text-[10px] text-red-500 font-medium">Needs metadata</span>
                                    </template>
                                </div>
                                {{-- Source info — compact block with provider + URLs --}}
                                <div x-show="featuredPhoto" x-cloak class="text-[10px] mt-1.5 bg-gray-50 border border-gray-100 rounded px-2 py-1.5 space-y-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span x-show="featuredPhoto?.source === 'google'" class="text-green-600 font-medium">via Google (SerpAPI)</span>
                                        <span x-show="featuredPhoto?.source === 'google-cse'" class="text-blue-600 font-medium">via Google (CSE)</span>
                                        <span x-show="featuredPhoto?.source === 'pexels'" class="text-teal-600 font-medium">Pexels</span>
                                        <span x-show="featuredPhoto?.source === 'unsplash'" class="text-gray-600 font-medium">Unsplash</span>
                                        <span x-show="featuredPhoto?.source === 'pixabay'" class="text-yellow-600 font-medium">Pixabay</span>
                                        <span x-show="featuredPhoto?.source === 'url-import'" class="text-orange-600 font-medium">Import URL</span>
                                        <span x-show="featuredPhoto?.source === 'upload'" class="text-purple-600 font-medium">Upload</span>
                                        <a :href="featuredPhoto?.url_large" target="_blank" class="text-blue-500 hover:underline truncate max-w-xs inline-flex items-center gap-0.5" :title="featuredPhoto?.url_large">
                                            <strong class="text-blue-700" x-text="(() => { try { return new URL(featuredPhoto?.url_large || '').hostname; } catch(e) { return ''; } })()"></strong><span class="truncate" x-text="(() => { try { const u = new URL(featuredPhoto?.url_large || ''); const p = u.pathname; return p.length > 40 ? p.substring(0, 20) + '...' + p.substring(p.length - 18) : p; } catch(e) { return ''; } })()"></span>
                                            <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </div>
                                    <div x-show="featuredPhoto?.source_url && featuredPhoto?.source_url !== featuredPhoto?.url_large" x-cloak class="flex items-center gap-1.5">
                                        <span class="text-gray-400">Found on:</span>
                                        <a :href="featuredPhoto?.source_url" target="_blank" class="text-blue-500 hover:underline inline-flex items-center gap-0.5" :title="featuredPhoto?.source_url">
                                            <strong class="text-blue-700" x-text="(() => { try { return new URL(featuredPhoto?.source_url || '').hostname; } catch(e) { return ''; } })()"></strong>
                                            <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </div>
                                </div>
                                {{-- Change Photo / Import URL / Upload --}}
                                <div class="mt-2" x-data="{ showFeaturedUrl: false, featuredUrlVal: '' }">
                                    <div class="flex items-center gap-1.5">
                                        <button @click.stop="featuredExpanded = !featuredExpanded; if (featuredExpanded) { featuredSearchPending = false; }" class="text-[11px] text-blue-500 hover:text-blue-700 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>
                                            <span x-text="featuredPhoto ? 'Change Photo' : (featuredSearchPending ? 'Loading Suggestions…' : 'Find Photo')"></span>
                                        </button>
                                        <span class="text-gray-300">|</span>
                                        <button @click.stop="showFeaturedUrl = !showFeaturedUrl" class="text-[11px] text-blue-500 hover:text-blue-700">Import URL</button>
                                        <span class="text-gray-300">|</span>
                                        <label class="text-[11px] text-gray-500 hover:text-gray-700 cursor-pointer">
                                            Upload
                                            <input type="file" class="hidden" accept="image/*" @change="uploadFeaturedPhoto($event.target.files); $event.target.value = null">
                                        </label>
                                        <span class="text-gray-300">|</span>
                                        <button x-show="featuredPhoto" x-cloak @click="resetFeaturedPhotoSelection()" class="text-[11px] text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                    <div x-show="showFeaturedUrl" x-cloak class="flex gap-1.5 mt-1.5">
                                        <input type="text" x-model="featuredUrlVal" class="flex-1 border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Paste image URL...">
                                        <button @click.stop="if(featuredUrlVal.trim()){applyFeaturedPhotoSelection({url_large:featuredUrlVal.trim(),url_thumb:featuredUrlVal.trim(),source:'url-import',alt:'',width:0,height:0});featuredUrlVal='';showFeaturedUrl=false;}" class="text-[11px] bg-blue-600 text-white px-2 py-1 rounded">Import</button>
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
                    <div x-show="featuredExpanded" x-cloak class="p-3 pt-2 border-t border-gray-100"
                         x-effect="if (featuredExpanded) { $nextTick(() => { const p = $el.querySelector('[data-photo-picker]'); if (p) Alpine.$data(p).loadTerm(featuredImageSearch); }); }">
                        @include('app-publish::publishing.pipeline.partials.photo-picker', [
                            'pickerId' => 'featured-picker',
                            'searchQuery' => '',
                            'onSelect' => 'function(photo) { const dup = photoSuggestions.find(p => p.autoPhoto && p.autoPhoto.url_large === photo.url_large); if (dup) { showNotification(\'warning\', \'This photo is already used as inline photo: \' + dup.search_term); } applyFeaturedPhotoSelection(photo); }',
                        ])
                    </div>
                </div>
            </div>

            {{-- Photo Suggestions Panel --}}
            <div class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4" data-photo-section>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700">Inline Article Photos</h5>
                        <p x-show="hasUnresolvedPhotoSuggestions() && !autoFetchingPhotos" x-cloak class="text-[11px] text-amber-600 mt-1" x-text="photoSuggestionsPending ? 'Suggestions load automatically while Create Article is open.' : 'Some image suggestions still need loading.'"></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button x-show="hasUnresolvedPhotoSuggestions() && !autoFetchingPhotos" x-cloak type="button" @click="loadPendingPhotoSuggestions()" class="text-xs text-blue-600 hover:text-blue-800" x-text="photoSuggestionsPending ? 'Load Suggestions' : 'Retry Loading'"></button>
                        <span x-show="autoFetchingPhotos" x-cloak class="text-xs text-purple-600 flex items-center gap-1">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Loading photos...
                        </span>
                    </div>
                </div>

                {{-- AI photo suggestions — photo + metadata in one card --}}
                <div x-show="photoSuggestions.length > 0" class="space-y-3 mb-3">
                    <template x-for="(ps, idx) in photoSuggestions" :key="idx">
                        <div x-show="!ps.removed" class="border rounded-lg overflow-hidden" :class="ps.confirmed ? 'border-green-300 bg-green-50' : 'border-purple-200 bg-white'">
                            {{-- Photo + info + metadata — all in one section --}}
                            <div class="p-3">
                                <div class="flex items-start gap-3">
                                    {{-- Thumbnail --}}
                                    <div class="w-40 h-28 flex-shrink-0 rounded overflow-hidden bg-gray-100 relative">
                                        <img x-show="ps.autoPhoto && !ps.thumbError" x-cloak :data-inline-thumb-index="idx" :src="resolvePhotoThumbUrl(ps.autoPhoto)" class="w-full h-full object-cover transition-opacity" :class="ps.thumbLoading ? 'opacity-0' : 'opacity-100'" x-on:load="photoSuggestions[idx].thumbLoading = false; photoSuggestions[idx].thumbError = ''" x-on:error="photoSuggestions[idx].thumbLoading = false; photoSuggestions[idx].thumbError = 'Thumbnail failed to load'">
                                        <div x-show="ps.autoPhoto && ps.thumbLoading" x-cloak class="absolute inset-0 w-full h-full flex items-center justify-center bg-purple-50/90">
                                            <svg class="w-5 h-5 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </div>
                                        <div x-show="!ps.autoPhoto && ps.searching" x-cloak class="w-full h-full flex items-center justify-center bg-purple-50">
                                            <svg class="w-5 h-5 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </div>
                                        <div x-show="ps.autoPhoto && ps.thumbError" x-cloak class="w-full h-full flex flex-col items-center justify-center text-red-400 bg-red-50 px-2 text-center">
                                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86A2.07 2.07 0 0020.73 16L13.8 4a2.07 2.07 0 00-3.6 0L3.27 16A2.07 2.07 0 005.07 19z"/></svg>
                                            <span class="text-[10px] font-medium">Thumbnail failed</span>
                                        </div>
                                        <div x-show="!ps.autoPhoto && !ps.searching" class="w-full h-full flex items-center justify-center text-gray-300">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    </div>
                                    {{-- Info + metadata --}}
                                    <div class="flex-1 min-w-0 space-y-1.5">
                                        <input type="text" x-model="photoSuggestions[idx].search_term" @change="queueAutoSaveDraft(300)" class="text-sm font-medium text-purple-700 break-words bg-transparent border-0 border-b border-transparent hover:border-gray-300 focus:border-purple-400 focus:ring-0 px-0 py-0 w-full" />
                                        <p x-show="ps.loadError" x-cloak class="text-[11px] text-red-500 break-words" x-text="ps.loadError"></p>
                                        <p x-show="ps.thumbError" x-cloak class="text-[11px] text-red-500 break-words" x-text="ps.thumbError"></p>
                                        <div x-show="ps.autoPhoto" x-cloak class="flex items-center gap-2 text-[11px]">
                                            <span class="text-gray-400" x-text="(ps.autoPhoto?.source || '') + ' — ' + (ps.autoPhoto?.width || '') + 'x' + (ps.autoPhoto?.height || '')"></span>
                                            <template x-if="ps.autoPhoto?.width && ps.autoPhoto?.height">
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium"
                                                    :class="(() => {
                                                        const r = ps.autoPhoto.width / ps.autoPhoto.height;
                                                        if (r >= 1.2 && r <= 2.5) return 'bg-green-100 text-green-700';
                                                        if (r >= 0.8 && r < 1.2) return 'bg-yellow-100 text-yellow-700';
                                                        return 'bg-red-100 text-red-700';
                                                    })()"
                                                    x-text="(() => {
                                                        const r = ps.autoPhoto.width / ps.autoPhoto.height;
                                                        const label = r.toFixed(2) + ':1';
                                                        if (r >= 1.2 && r <= 2.5) return label + ' Landscape';
                                                        if (r >= 1.0 && r < 1.2) return label + ' Square';
                                                        if (r < 1.0) return label + ' Portrait';
                                                        return label + ' Ultra-wide';
                                                    })()">
                                                </span>
                                            </template>
                                        </div>
                                        {{-- Source info — compact block with provider + URLs --}}
                                        <div x-show="ps.autoPhoto" x-cloak class="text-[10px] mt-1.5 bg-gray-50 border border-gray-100 rounded px-2 py-1.5 space-y-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span x-show="ps.autoPhoto?.source === 'google'" class="text-green-600 font-medium">via Google (SerpAPI)</span>
                                                <span x-show="ps.autoPhoto?.source === 'google-cse'" class="text-blue-600 font-medium">via Google (CSE)</span>
                                                <span x-show="ps.autoPhoto?.source === 'pexels'" class="text-teal-600 font-medium">Pexels</span>
                                                <span x-show="ps.autoPhoto?.source === 'unsplash'" class="text-gray-600 font-medium">Unsplash</span>
                                                <span x-show="ps.autoPhoto?.source === 'pixabay'" class="text-yellow-600 font-medium">Pixabay</span>
                                                <span x-show="ps.autoPhoto?.source === 'url-import'" class="text-orange-600 font-medium">Import URL</span>
                                                <span x-show="ps.autoPhoto?.source === 'upload'" class="text-purple-600 font-medium">Upload</span>
                                                <a :href="ps.autoPhoto?.url_large" target="_blank" class="text-blue-500 hover:underline truncate max-w-xs inline-flex items-center gap-0.5" :title="ps.autoPhoto?.url_large">
                                                    <strong class="text-blue-700" x-text="(() => { try { return new URL(ps.autoPhoto?.url_large || '').hostname; } catch(e) { return ''; } })()"></strong><span class="truncate" x-text="(() => { try { const u = new URL(ps.autoPhoto?.url_large || ''); const p = u.pathname; return p.length > 40 ? p.substring(0, 20) + '...' + p.substring(p.length - 18) : p; } catch(e) { return ''; } })()"></span>
                                                    <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            </div>
                                            <div x-show="ps.autoPhoto?.source_url && ps.autoPhoto?.source_url !== ps.autoPhoto?.url_large" x-cloak class="flex items-center gap-1.5">
                                                <span class="text-gray-400">Found on:</span>
                                                <a :href="ps.autoPhoto?.source_url" target="_blank" class="text-blue-500 hover:underline inline-flex items-center gap-0.5" :title="ps.autoPhoto?.source_url">
                                                    <strong class="text-blue-700" x-text="(() => { try { return new URL(ps.autoPhoto?.source_url || '').hostname; } catch(e) { return ''; } })()"></strong>
                                                    <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            </div>
                                        </div>
                                        {{-- Metadata fields --}}
                                        <div x-show="ps.autoPhoto || ps.alt_text || ps.caption" x-cloak class="space-y-0.5 max-w-lg">
                                            <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Alt</label><input type="text" x-model="photoSuggestions[idx].alt_text" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Alt text..."></div>
                                            <div class="flex items-center gap-2"><label class="text-[10px] text-gray-400 uppercase w-16 flex-shrink-0">Caption</label><input type="text" x-model="photoSuggestions[idx].caption" class="flex-1 border border-gray-200 rounded px-2 py-0.5 text-xs" placeholder="Caption..."></div>
                                            <div class="flex items-center gap-2"><label class="text-[10px] uppercase w-16 flex-shrink-0" :class="!photoSuggestions[idx].suggestedFilename || photoSuggestions[idx].suggestedFilename === 'auto' ? 'text-red-400 font-semibold' : 'text-gray-400'">Filename</label><p class="text-[11px] font-mono" :class="!photoSuggestions[idx].suggestedFilename || photoSuggestions[idx].suggestedFilename === 'auto' ? 'text-red-500' : 'text-gray-500'" x-text="photoSuggestions[idx].suggestedFilename || 'auto'"></p></div>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <button @click.stop="refreshPhotoMeta(idx)" :disabled="ps.refreshingMeta" class="text-[11px] inline-flex items-center gap-1 disabled:opacity-50" :class="photoSuggestionNeedsMetadata(ps) ? 'text-red-500 hover:text-red-700 font-semibold' : 'text-purple-500 hover:text-purple-700'">
                                                <svg class="w-3 h-3" :class="ps.refreshingMeta ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                <span x-text="ps.refreshingMeta ? 'Generating...' : 'Refresh Metadata'"></span>
                                            </button>
                                            <span x-show="ps.metaGenerator" x-cloak class="text-[9px] px-1.5 py-0.5 rounded" :class="ps.metaGenerator === 'local' ? 'bg-gray-100 text-gray-500' : 'bg-purple-100 text-purple-600'" x-text="ps.metaGenerator === 'local' ? 'PHP' : 'AI'"></span>
                                            <template x-if="photoSuggestionNeedsMetadata(ps)">
                                                <span class="text-[10px] text-red-500 font-medium">Needs metadata</span>
                                            </template>
                                        </div>
                                        {{-- (Source URL now shown above with bold domain for all photo types) --}}
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
                                                        applyPhotoSuggestionSelection(idx, { url_large: url, url_thumb: url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: 0, height: 0 });
                                                        $event.target.value = null;
                                                    ">
                                                </label>
                                            </div>
                                            <div x-show="showUrlInput" x-cloak class="flex gap-1.5 mt-1.5">
                                                <input type="text" x-model="photoUrlVal" class="flex-1 border border-gray-200 rounded px-2 py-1 text-xs" placeholder="Paste image URL...">
                                                <button @click.stop="
                                                    if (photoUrlVal.trim()) {
                                                        applyPhotoSuggestionSelection(idx, { url_large: photoUrlVal.trim(), url_thumb: photoUrlVal.trim(), source: 'url-import', alt: '', width: 0, height: 0 });
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
                            <option value="" x-text="selectedSite?.default_author ? '— Default: ' + selectedSite?.default_author + ' —' : '— Default author —'"></option>
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
                        <button x-show="!siteConn.testing && !authorsLoading && selectedSiteId" x-cloak @click="loadSiteAuthors(selectedSiteId)" type="button" class="text-xs text-blue-500 hover:text-blue-700">Reload authors</button>
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
