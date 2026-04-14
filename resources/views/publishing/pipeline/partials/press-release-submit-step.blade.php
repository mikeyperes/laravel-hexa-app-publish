<div x-show="currentArticleType === 'press-release'" x-cloak class="space-y-4">

    {{-- ═══ Sub-card 1: Press Release Content ═══ --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 bg-purple-50 border-b border-purple-100">
            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-800">Press Release Content</p>
                <p class="text-xs text-gray-400">Choose how this press release should be ingested</p>
            </div>
        </div>
        <div class="px-5 py-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <button @click="setPressReleaseSubmitMethod('content-dump')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'content-dump' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Content Dump</p>
                    <p class="mt-1 text-xs text-gray-500">Paste the raw press release text or notes directly.</p>
                </button>
                <button @click="setPressReleaseSubmitMethod('upload-documents')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'upload-documents' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Upload Press Release</p>
                    <p class="mt-1 text-xs text-gray-500">Accepted formats: `.doc`, `.docx`, `.pdf`.</p>
                </button>
                <button @click="setPressReleaseSubmitMethod('public-url')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'public-url' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Submit Public URL</p>
                    <p class="mt-1 text-xs text-gray-500">Pull the release from a public page.</p>
                </button>
            </div>

            {{-- Content dump --}}
            <div x-show="pressRelease.submit_method === 'content-dump'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content Dump</label>
                <textarea x-model="pressRelease.content_dump" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm leading-relaxed" rows="10" placeholder="Paste the full press release, raw notes, quotes, or the exact submitted content here."></textarea>
            </div>

            {{-- Upload documents --}}
            <div x-show="pressRelease.submit_method === 'upload-documents'" x-cloak class="space-y-3">
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Upload Press Release Files</p>
                            <p class="text-xs text-gray-500 mt-1">Accepted: `.doc`, `.docx`, `.pdf`</p>
                        </div>
                        <label class="inline-flex items-center gap-2 bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 cursor-pointer">
                            <svg x-show="pressReleaseUploadingDocuments" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="pressReleaseUploadingDocuments ? 'Uploading...' : 'Upload Files'"></span>
                            <input type="file" class="hidden" multiple accept=".doc,.docx,.pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf" @change="uploadPressReleaseDocuments($event.target.files); $event.target.value = null">
                        </label>
                    </div>
                </div>
                <div x-show="pressRelease.document_files.length > 0" x-cloak class="space-y-2">
                    <template x-for="file in pressRelease.document_files" :key="file.id">
                        <div class="flex items-start justify-between gap-3 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800 break-words" x-text="file.original_name || file.filename"></p>
                                <p class="text-xs text-gray-400 mt-1" x-text="(file.mime_type || 'file') + ' • ' + Math.max(1, Math.round((file.size || 0) / 1024)) + ' KB'"></p>
                            </div>
                            <a :href="file.url" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 flex-shrink-0">Open</a>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Public URL --}}
            <div x-show="pressRelease.submit_method === 'public-url'" x-cloak class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Public Press Release URL</label>
                    <input type="url" x-model="pressRelease.public_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://example.com/public-press-release">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pull / Scrape Method</label>
                    <select x-model="pressRelease.public_url_method" @change="savePipelineState()" class="w-full md:w-80 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="auto">Auto</option>
                        <option value="readability">Readability</option>
                        <option value="css">CSS Selector</option>
                        <option value="regex">Regex</option>
                        <option value="claude">Claude AI</option>
                        <option value="gpt">GPT</option>
                        <option value="grok">Grok</option>
                    </select>
                </div>

                {{-- Detect Content button --}}
                <div x-show="pressRelease.public_url" x-cloak>
                    <button @click="detectPressReleaseContent()" :disabled="pressReleaseDetectingContent" type="button" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2 font-medium">
                        <svg x-show="pressReleaseDetectingContent" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="pressReleaseDetectingContent ? 'Detecting Content...' : 'Detect Content'"></span>
                    </button>
                </div>

                {{-- Content detection activity log --}}
                <div x-show="pressRelease.content_detect_log?.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Content Detection Log</div>
                    <template x-for="(entry, idx) in (pressRelease.content_detect_log || [])" :key="idx">
                        <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                            <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                            <span class="break-words" :class="{
                                'text-green-400': entry.type === 'success',
                                'text-red-400': entry.type === 'error',
                                'text-blue-400': entry.type === 'info',
                                'text-gray-300': entry.type === 'step',
                            }" x-text="entry.message"></span>
                        </div>
                    </template>
                </div>

                {{-- Detected content output --}}
                <div x-show="pressRelease.detected_content" x-cloak class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-gray-700">Detected Content</h5>
                        <span class="text-xs text-gray-400" x-text="pressRelease.detected_word_count ? pressRelease.detected_word_count + ' words' : ''"></span>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto">
                        <div class="text-sm text-gray-800 whitespace-pre-wrap break-words leading-relaxed" x-html="pressRelease.detected_content_html || pressRelease.detected_content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Sub-card 2: Validate Release Details ═══ --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 bg-blue-50 border-b border-blue-100">
            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">Validate Release Details</p>
                <p class="text-xs text-gray-400">Review and auto-detect date, location, contact from the submission</p>
            </div>
            <button @click="detectPressReleaseFields()" :disabled="pressReleaseDetectingFields" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="pressReleaseDetectingFields" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="pressReleaseDetectingFields ? 'Detecting...' : 'Auto Detect'"></span>
            </button>
        </div>
        <div class="px-5 py-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Press Release Date</label>
                    <input type="text" x-model="pressRelease.details.date" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. April 11, 2026">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" x-model="pressRelease.details.location" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Miami, Florida">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact</label>
                    <input type="text" x-model="pressRelease.details.contact" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Sarah Smith, media@company.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact URL</label>
                    <input type="url" x-model="pressRelease.details.contact_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://example.com/contact">
                </div>
            </div>

            {{-- Validation activity log --}}
            <div x-show="pressRelease.activity_log.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Detection Log</div>
                <template x-for="(entry, idx) in pressRelease.activity_log" :key="idx">
                    <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <span class="text-gray-500 flex-shrink-0" x-text="new Date(entry.timestamp).toLocaleTimeString('en-US', { hour12: false })"></span>
                        <span class="break-words" :class="{
                            'text-green-400': entry.type === 'success',
                            'text-red-400': entry.type === 'error',
                            'text-yellow-400': entry.type === 'warning',
                            'text-blue-400': entry.type === 'info',
                            'text-gray-300': entry.type === 'step'
                        }" x-text="entry.message"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ═══ Sub-card 3: Photos ═══ --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 bg-green-50 border-b border-green-100">
            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">Photos</p>
                <p class="text-xs text-gray-400">Provide photos for the press release</p>
            </div>
            <span x-show="pressReleasePhotoAssets.length > 0" x-cloak class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700" x-text="pressReleasePhotoAssets.length + ' photo(s)'"></span>
        </div>
        <div class="px-5 py-5 space-y-4">
            <div class="grid grid-cols-1 gap-3" :class="pressRelease.submit_method === 'public-url' ? 'md:grid-cols-3' : 'md:grid-cols-2'">
                <button @click="pressRelease.photo_method = 'google-drive'; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'google-drive' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                    <p class="text-sm font-semibold text-gray-800">Google Drive URL</p>
                    <p class="mt-1 text-xs text-gray-500">Supply a public Google Drive folder or file link.</p>
                </button>
                <button @click="pressRelease.photo_method = 'upload-files'; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'upload-files' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                    <p class="text-sm font-semibold text-gray-800">Upload Photos</p>
                    <p class="mt-1 text-xs text-gray-500">Use the multi-photo upload tool for local image files.</p>
                </button>
                <button x-show="pressRelease.submit_method === 'public-url'" x-cloak @click="pressRelease.photo_method = 'detect-from-public-url'; if (!pressRelease.photo_public_url && pressRelease.public_url) pressRelease.photo_public_url = pressRelease.public_url; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'detect-from-public-url' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                    <p class="text-sm font-semibold text-gray-800">Detect From URL</p>
                    <p class="mt-1 text-xs text-gray-500">Extract photos from the public press release page.</p>
                </button>
            </div>

            <div x-show="pressRelease.photo_method === 'google-drive'" x-cloak class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Google Drive URL</label>
                    <input type="url" x-model="pressRelease.google_drive_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Public Google Drive URL (must have public permissions)">
                </div>
                <div x-show="pressRelease.google_drive_url" x-cloak class="flex justify-end">
                    <button @click="fetchPressReleaseDrivePhotos()" :disabled="pressReleaseFetchingDrivePhotos" type="button" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="pressReleaseFetchingDrivePhotos" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="pressReleaseFetchingDrivePhotos ? 'Fetching...' : 'Fetch Photos from Drive'"></span>
                    </button>
                </div>
            </div>

            <div x-show="pressRelease.photo_method === 'upload-files'" x-cloak class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">Upload image files, then refresh the synced list below.</p>
                    <button @click="refreshPressReleasePhotoFiles()" type="button" class="text-xs text-blue-600 hover:text-blue-800">Refresh Uploaded Photos</button>
                </div>
                @include('upload-portal::components.upload-portal', ['context' => 'press-release-photo', 'contextId' => $draftId, 'multi' => true])
            </div>

            <div x-show="pressRelease.photo_method === 'detect-from-public-url'" x-cloak class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Public Press Release URL</label>
                    <input type="url" x-model="pressRelease.photo_public_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Leave blank to reuse the submit URL">
                </div>
                <div class="flex justify-end">
                    <button @click="detectPressReleasePhotos()" :disabled="pressReleaseDetectingPhotos" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="pressReleaseDetectingPhotos" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="pressReleaseDetectingPhotos ? 'Detecting...' : 'Detect Photos From URL'"></span>
                    </button>
                </div>
            </div>

            {{-- Photo detection activity log --}}
            <div x-show="pressRelease.photo_detect_log?.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Photo Detection Log</div>
                <template x-for="(entry, idx) in (pressRelease.photo_detect_log || [])" :key="idx">
                    <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span class="break-words" :class="{
                            'text-green-400': entry.type === 'success',
                            'text-red-400': entry.type === 'error',
                            'text-blue-400': entry.type === 'info',
                            'text-gray-300': entry.type === 'step',
                        }" x-text="entry.message"></span>
                    </div>
                </template>
            </div>

            <div x-show="(pressRelease.detected_photos || []).length > 0 || (pressRelease.photo_files || []).length > 0" x-cloak>
                <div class="flex items-center justify-between mb-2">
                    <h5 class="text-sm font-semibold text-gray-700">Photo Assets</h5>
                    <span class="text-xs text-gray-400" x-text="((pressRelease.detected_photos || []).length + (pressRelease.photo_files || []).length) + ' photo(s)'"></span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <template x-for="(photo, aidx) in (pressRelease.detected_photos || [])" :key="'det-' + aidx">
                        <div class="rounded-xl overflow-hidden border-2 bg-white transition-all"
                            :class="pressRelease.selected_photo_keys?.['det-' + aidx] ? 'border-green-500 ring-2 ring-green-200' : 'border-gray-200 hover:border-gray-300'">
                            <div class="relative cursor-pointer select-none" @click.prevent="if (!pressRelease.selected_photo_keys) pressRelease.selected_photo_keys = {}; pressRelease.selected_photo_keys['det-' + aidx] = !pressRelease.selected_photo_keys['det-' + aidx]; savePipelineState()">
                                <img :src="photo.thumbnail_url || photo.url" class="w-full h-56 object-cover pointer-events-none" loading="lazy" onerror="this.style.display='none'" draggable="false">
                                <div x-show="pressRelease.selected_photo_keys?.['det-' + aidx]" class="absolute top-2 right-2 w-7 h-7 bg-green-500 rounded-full flex items-center justify-center shadow-md">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div x-show="!pressRelease.selected_photo_keys?.['det-' + aidx]" class="absolute top-2 right-2 w-7 h-7 bg-white/80 border-2 border-gray-300 rounded-full"></div>
                            </div>
                            <div class="p-3">
                                <p class="text-sm font-medium text-gray-800 break-words" x-text="photo.alt_text || photo.caption || 'Photo ' + (aidx + 1)"></p>
                                <p class="text-xs text-gray-400 mt-0.5" x-text="photo.source || 'detected'"></p>
                                <div class="flex items-center gap-2 mt-2">
                                    <a :href="photo.download_url || photo.url" target="_blank" @click.stop class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Download
                                    </a>
                                    <a :href="photo.view_url || photo.url" target="_blank" @click.stop class="inline-flex items-center gap-1 text-xs text-gray-500 hover:underline">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        Open
                                    </a>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Polish toggle --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <label class="inline-flex items-center gap-3 cursor-pointer">
            <input type="checkbox" x-model="pressRelease.polish_only" @change="savePipelineState(); refreshPromptPreview()" class="rounded border-gray-300 text-purple-600">
            <span class="text-sm text-gray-700">Polish grammar and structure but do not spin with AI.</span>
        </label>
    </div>

    {{-- Continue button --}}
    <div class="flex items-center justify-end">
        <button @click="continuePressReleaseStep3(); completeStep(4); openStep(5)" type="button" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-blue-700 font-medium" x-text="pressRelease.polish_only ? 'Continue to Polish →' : 'Continue to AI & Spin →'"></button>
    </div>
</div>
