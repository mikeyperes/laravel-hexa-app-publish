    {{-- Upload Portal Modal (triggered from TinyMCE toolbar) --}}
    <template x-if="showUploadPortal">
        <div x-cloak>
            @include('upload-portal::components.upload-portal', ['context' => 'article', 'contextId' => $draftId, 'multi' => true])
        </div>
    </template>

    {{-- Photo Search Overlay (triggered from TinyMCE toolbar) --}}
    <div x-show="showPhotoOverlay" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-0 m-0" style="top:0;left:0;right:0;bottom:0;" @click.self="showPhotoOverlay = false" @keydown.escape.window="showPhotoOverlay = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="font-semibold text-gray-800">Search & Insert Photo</h3>
                <button @click="showPhotoOverlay = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6">
                @include('app-publish::publishing.pipeline.partials.photo-picker', [
                    'pickerId' => 'overlay-picker',
                    'searchQuery' => '',
                    'onSelect' => 'function(photo) { insertingPhoto = photo; photoCaption = photo.alt || articleTitle; overlayPhotoAlt = \'Click Get Metadata to generate\'; overlayPhotoCaption = \'Click Get Metadata to generate\'; overlayPhotoFilename = \'auto\'; overlayMetaGenerated = false; }',
                ])
                {{-- URL import + file upload --}}
                <div class="flex gap-2 mt-4 mb-4">
                    <input type="text" x-model="innerPhotoUrlImport" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Paste image URL...">
                    <button @click="importInnerPhotoFromUrl()" :disabled="!innerPhotoUrlImport" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">Import URL</button>
                    <label class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700 cursor-pointer inline-flex items-center gap-1">
                        Upload
                        <input type="file" class="hidden" accept="image/*" @change="uploadInnerPhoto($event.target.files); $event.target.value = null">
                    </label>
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
         class="fixed bottom-4 right-4 z-50 max-w-sm rounded-xl shadow-2xl p-4 flex items-center gap-3 bg-white opacity-100"
         :class="notification.type === 'success' ? 'border border-green-200 ring-1 ring-green-100' : 'border border-red-200 ring-1 ring-red-100'">
        <template x-if="notification.type === 'success'">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </template>
        <template x-if="notification.type === 'error'">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </template>
        <div class="flex-1 min-w-0">
            <span class="text-sm break-words block" :class="notification.type === 'success' ? 'text-green-800' : 'text-red-800'" x-text="notification.message"></span>
            <template x-if="notification.code || notification.status">
                <span class="mt-1 block text-[11px] font-mono break-all" :class="notification.type === 'success' ? 'text-green-700' : 'text-red-700'" x-text="((notification.status ? ('HTTP ' + notification.status) : 'HTTP ?') + (notification.code ? (' · ' + notification.code) : ''))"></span>
            </template>
        </div>
        <button @click="notification.show = false" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>
