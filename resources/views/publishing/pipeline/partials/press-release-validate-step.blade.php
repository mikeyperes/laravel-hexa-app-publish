<div x-show="currentArticleType === 'press-release'" x-cloak class="space-y-5">
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="text-base font-semibold text-gray-800">Validate Details</h4>
                <p class="text-xs text-gray-400 mt-1">Review the core press release fields and auto-detect them from the selected submission method.</p>
            </div>
            <button @click="detectPressReleaseFields()" :disabled="pressReleaseDetectingFields" type="button" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="pressReleaseDetectingFields" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="pressReleaseDetectingFields ? 'Detecting...' : 'Auto Detect'"></span>
            </button>
        </div>

        <div x-show="pressRelease.resolved_source_preview" x-cloak class="rounded-lg border border-purple-100 bg-white p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-purple-700" x-text="pressRelease.resolved_source_label || 'Resolved Source'"></p>
                <span class="text-[11px] text-gray-400" x-text="pressRelease.submit_method.replace('-', ' ')"></span>
            </div>
            <pre class="text-xs text-gray-600 whitespace-pre-wrap break-words max-h-52 overflow-y-auto" x-text="pressRelease.resolved_source_preview"></pre>
        </div>

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
                <input type="text" x-model="pressRelease.details.contact" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Sarah Smith, media contact">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact URL</label>
                <input type="url" x-model="pressRelease.details.contact_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://example.com/contact">
            </div>
        </div>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4">
        <div>
            <h4 class="text-base font-semibold text-gray-800">Upload Photos</h4>
            <p class="text-xs text-gray-400 mt-1">Provide photos by public Google Drive URL, direct upload, or detection from the public release URL.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <button @click="pressRelease.photo_method = 'google-drive'; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'google-drive' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                <p class="text-sm font-semibold text-gray-800">Google Drive URL</p>
                <p class="mt-1 text-xs text-gray-500">Supply a public Google Drive folder or file link.</p>
            </button>
            <button @click="pressRelease.photo_method = 'upload-files'; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'upload-files' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                <p class="text-sm font-semibold text-gray-800">Upload Photos</p>
                <p class="mt-1 text-xs text-gray-500">Use the multi-photo upload tool for local image files.</p>
            </button>
            <button @click="pressRelease.photo_method = 'detect-from-public-url'; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'detect-from-public-url' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                <p class="text-sm font-semibold text-gray-800">Detect From Public URL</p>
                <p class="mt-1 text-xs text-gray-500">Try to extract usable photos from the public press release page.</p>
            </button>
        </div>

        <div x-show="pressRelease.photo_method === 'google-drive'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1">Google Drive URL</label>
            <input type="url" x-model="pressRelease.google_drive_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Public Google Drive URL (must have public permissions)">
            <p class="text-xs text-gray-400 mt-1">Make sure the link is public. Put that directly in the label/instructions for the team using this workflow.</p>
        </div>

        <div x-show="pressRelease.photo_method === 'upload-files'" x-cloak class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-gray-500">Upload image files, then refresh the synced list below.</p>
                <button @click="refreshPressReleasePhotoFiles()" type="button" class="text-xs text-blue-600 hover:text-blue-800">Refresh Uploaded Photos</button>
            </div>
            <template x-if="pressRelease.photo_method === 'upload-files'">
                <div>
                    @include('upload-portal::components.upload-portal', ['context' => 'press-release-photo', 'contextId' => $draftId, 'multi' => true])
                </div>
            </template>
        </div>

        <div x-show="pressRelease.photo_method === 'detect-from-public-url'" x-cloak class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Public Press Release URL</label>
                <input type="url" x-model="pressRelease.photo_public_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Leave blank to reuse the submit URL from step 3">
            </div>
            <div class="flex justify-end">
                <button @click="detectPressReleasePhotos()" :disabled="pressReleaseDetectingPhotos" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                    <svg x-show="pressReleaseDetectingPhotos" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="pressReleaseDetectingPhotos ? 'Detecting...' : 'Detect From Public URL'"></span>
                </button>
            </div>
        </div>

        <div x-show="pressRelease.photo_files.length > 0 || pressRelease.detected_photos.length > 0" x-cloak class="space-y-3">
            <h5 class="text-sm font-semibold text-gray-700">Photo Assets</h5>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <template x-for="asset in pressReleasePhotoAssets" :key="asset.key">
                    <div class="rounded-lg overflow-hidden border border-gray-200 bg-white">
                        <img :src="asset.thumbnail_url || asset.url" class="w-full h-36 object-cover" loading="lazy">
                        <div class="p-3 space-y-1">
                            <p class="text-xs font-medium text-gray-800 break-words" x-text="asset.label"></p>
                            <p class="text-[11px] text-gray-400 break-words" x-text="asset.source_label"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
        <label class="inline-flex items-center gap-3 cursor-pointer">
            <input type="checkbox" x-model="pressRelease.polish_only" @change="savePipelineState(); invalidatePromptPreview('press_release_polish_toggle', { fetch: true })" class="rounded border-gray-300 text-purple-600">
            <span class="text-sm text-gray-700">Polish grammar and structure but do not spin with AI.</span>
        </label>
        <p class="text-xs text-gray-400">If enabled, step 5 becomes <span class="font-medium">Polish</span> and uses the dedicated press release polish prompt.</p>
    </div>

    <div x-show="pressRelease.activity_log.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-72 overflow-y-auto">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Activity Log</div>
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

    <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-gray-400">You can continue manually, but auto-detect is recommended so the details are normalized before the AI step.</p>
        <button @click="continuePressReleaseStep4()" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700" x-text="pressRelease.polish_only ? 'Continue to Polish →' : 'Continue to AI & Spin →'"></button>
    </div>
</div>
