{{-- ══════════════════════════════════════════════════════════════
     Step 7: Review & Publish
     ══════════════════════════════════════════════════════════════ --}}
<div class="pipeline-step-card" :class="{ 'ring-2 ring-blue-400': currentStep === 7, 'opacity-50': !isStepAccessible(7) }">
    <button @click="toggleStep(7)" :disabled="!isStepAccessible(7)" class="pipeline-step-toggle disabled:cursor-not-allowed">
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
    <div x-show="openSteps.includes(7)" x-cloak x-collapse class="pipeline-step-panel">

        {{-- ═══ Action Dock ═══ --}}
        <div x-ref="publishActionBox" x-init="$nextTick(() => _focusPublishActionBox({ behavior: 'auto' }))" class="border border-gray-200 rounded-xl p-5 mb-4 bg-white">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex-1 space-y-4">
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700">Publish Actions</h5>
                        <p class="mt-1 text-xs text-gray-500" x-show="publishAction === 'draft_local'" x-cloak>Use this to keep working locally without sending anything to WordPress.</p>
                        <p class="mt-1 text-xs text-gray-500" x-show="publishAction === 'publish' || publishAction === 'draft_wp' || publishAction === 'future'" x-cloak>Step 1: prepare the WordPress payload. Step 2: publish or update the same post from this panel. After a post is live, you can keep editing, re-prepare, and publish the update from here.</p>
                    </div>

                    <div x-show="existingWpPostId" x-cloak class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        <template x-if="existingWpStatus === 'publish'">
                            <div class="space-y-2">
                                <p>WordPress post <span class="font-mono" x-text="'#' + existingWpPostId"></span> is already live. Edit the article, run prepare again, then use <span class="font-medium">Update live WordPress post</span> to replace the same post without leaving this editor.</p>
                                <div class="flex flex-wrap gap-3 text-xs">
                                    <button type="button" @click="openStep(6); currentStep = 6; _syncStepToUrl && _syncStepToUrl()" class="text-blue-700 hover:text-blue-900 underline">Edit article content</button>
                                    <button type="button" @click="publishAction = 'publish'; $nextTick(() => _focusPublishActionBox())" class="text-blue-700 hover:text-blue-900 underline">Re-prepare this live post</button>
                                </div>
                            </div>
                        </template>
                        <template x-if="existingWpStatus !== 'publish'">
                            <p>This draft already owns WordPress post <span class="font-mono" x-text="'#' + existingWpPostId"></span>. Use <span class="font-medium">Publish existing WordPress draft live</span> to promote that same post, or <span class="font-medium">Update existing WordPress draft</span> to keep refining it safely.</p>
                        </template>
                    </div>

                    <div class="grid gap-2 lg:grid-cols-2">
                        <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-blue-300 hover:bg-blue-50/40">
                            <input type="radio" x-model="publishAction" value="publish" class="text-blue-600">
                            <span x-text="existingWpStatus === 'publish' ? 'Update live WordPress post' : (existingWpPostId ? 'Publish existing WordPress draft live' : 'Publish immediately')"></span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-blue-300 hover:bg-blue-50/40">
                            <input type="radio" x-model="publishAction" value="draft_local" class="text-blue-600">
                            <span>Save as local draft</span>
                        </label>
                        <label x-show="existingWpStatus !== 'publish'" x-cloak class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-blue-300 hover:bg-blue-50/40">
                            <input type="radio" x-model="publishAction" value="draft_wp" class="text-blue-600">
                            <span x-text="existingWpPostId ? 'Update existing WordPress draft' : 'Push as WordPress draft'"></span>
                        </label>
                        <label x-show="existingWpStatus !== 'publish'" x-cloak class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-blue-300 hover:bg-blue-50/40">
                            <input type="radio" x-model="publishAction" value="future" class="text-blue-600">
                            <span x-text="existingWpPostId ? 'Schedule existing WordPress post' : 'Schedule'"></span>
                        </label>
                    </div>

                    <div x-show="publishAction === 'future'" x-cloak class="max-w-md">
                        <label class="block text-xs text-gray-500 mb-1">Schedule Date & Time</label>
                        <input type="datetime-local" x-model="scheduleDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="xl:w-80 border border-gray-200 rounded-xl p-4 bg-gray-50 space-y-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Readiness</p>
                        <p class="mt-1 text-sm font-medium text-gray-800" x-text="publishAction === 'draft_local'
                            ? 'Ready to save locally now.'
                            : (_prepareOperationIsActive()
                                ? 'Preparing WordPress payload...'
                                : (prepareComplete
                                    ? (existingWpStatus === 'publish' ? 'Prepared. Update the live post when you are ready.' : 'Prepared. Publish controls are ready.')
                                    : 'Prepare is required before WordPress actions are enabled.'))"></p>
                        <p class="mt-1 text-xs text-gray-500" x-show="publishAction !== 'draft_local' && existingWpPostId" x-cloak x-text="existingWpStatus === 'publish' ? ('WordPress post #' + existingWpPostId + ' is live and will be updated in place.') : ('WordPress post #' + existingWpPostId + ' will be updated instead of duplicated.')"></p>
                    </div>

                    <div x-show="publishing || publishOperationStatus || _prepareOperationIsActive() || prepareOperationStatus" x-cloak class="text-[11px] text-gray-500 space-y-1">
                        <p x-show="_prepareOperationIsActive() || prepareOperationStatus" x-text="'Prepare: ' + (prepareOperationStatus || (prepareComplete ? 'completed' : 'idle')) + (prepareOperationTransport ? ' via ' + prepareOperationTransport.replace('_', ' ') : '')"></p>
                        <p x-show="publishOperationStatus" x-text="'Publish: ' + publishOperationStatus + (publishOperationTransport ? ' via ' + publishOperationTransport.replace('_', ' ') : '')"></p>
                        <p x-show="prepareTraceId" x-text="'Prepare trace: ' + prepareTraceId"></p>
                        <p x-show="publishTraceId" x-text="'Publish trace: ' + publishTraceId"></p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button x-show="publishAction === 'publish' || publishAction === 'draft_wp' || publishAction === 'future'" x-cloak @click="prepareForWp()" :disabled="_prepareOperationIsActive()" class="text-white px-5 py-2.5 rounded-lg text-sm disabled:opacity-50 flex items-center justify-center gap-2" :class="prepareComplete && prepareIntegrityIssues.length === 0 && !prepareChecklist.some(c => c.status === 'failed' || c.status === 'skipped') ? 'bg-green-600 hover:bg-green-700' : (prepareComplete ? 'bg-orange-500 hover:bg-orange-600' : 'bg-blue-600 hover:bg-blue-700')">
                            <svg x-show="_prepareOperationIsActive()" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="_prepareOperationIsActive() ? (prepareOperationStatus === 'queued' ? 'Queued...' : 'Preparing...') : (prepareComplete && prepareChecklist.some(c => c.status === 'failed' || c.status === 'skipped') ? 'Retry Prepare' : (prepareComplete ? (existingWpStatus === 'publish' ? 'Re-prepare Live Post' : 'Prepared') : (existingWpStatus === 'publish' ? 'Prepare Live Update' : 'Prepare for WordPress')))"></span>
                        </button>

                        <button @click="publishArticle()" :disabled="publishing || ((publishAction === 'publish' || publishAction === 'draft_wp' || publishAction === 'future') && !prepareComplete)" class="bg-green-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2">
                            <svg x-show="publishing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="publishing ? (publishOperationStatus === 'queued' ? 'Queued...' : 'Publishing...') : (publishAction === 'draft_local' ? 'Save Local Draft' : (publishAction === 'draft_wp' ? (existingWpPostId ? 'Update WP Draft' : 'Create WP Draft') : (publishAction === 'future' ? (existingWpPostId ? 'Schedule Existing Post' : 'Schedule Post') : (existingWpStatus === 'publish' ? 'Update Live Post' : (existingWpPostId ? 'Publish Existing Draft' : 'Publish')))))"></span>
                        </button>

                        <button @click="saveDraftNow()" :disabled="savingDraft" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-50 flex items-center justify-center gap-2">
                            <svg x-show="savingDraft" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="savingDraft ? 'Saving...' : 'Save Draft'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="publishError" x-cloak class="mt-4 bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-sm text-red-700" x-text="publishError"></p>
            </div>
        </div>

        {{-- ═══ Prepare for WordPress (only for WP actions) ═══ --}}
        <div x-show="publishAction === 'publish' || publishAction === 'draft_wp' || publishAction === 'future'" x-cloak class="border border-gray-200 rounded-xl p-5 mb-4">
            <h5 class="text-sm font-semibold text-gray-700 mb-3">Preparation Details</h5>
            <p class="text-xs text-gray-500 mb-4">The action dock above stays live. Use this section to inspect checklist progress, uploaded media, integrity fixes, and the full activity log.</p>
            <div x-show="_prepareOperationIsActive() || prepareOperationStatus" x-cloak class="mb-4 text-[11px] text-gray-500 space-y-1">
                <p x-show="prepareOperationStatus" x-text="'Status: ' + prepareOperationStatus + (prepareOperationTransport ? ' via ' + prepareOperationTransport.replace('_', ' ') : '')"></p>
                <p x-show="prepareTraceId" x-text="'Trace: ' + prepareTraceId"></p>
                <p x-show="_prepareOperationIsActive() && (prepareLastStage || prepareLastMessage)" x-text="'Current: ' + _humanizePipelineLabel(prepareLastStage, prepareOperationStatus === 'queued' ? 'queue' : 'prepare startup') + (prepareLastMessage ? ' — ' + prepareLastMessage : '')"></p>
            </div>

            {{-- Checklist --}}
            <div x-show="prepareChecklist.length > 0" x-cloak class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Preparation Checklist</h6>
                    <span class="text-xs text-gray-400" x-text="prepareChecklist.filter(c => c.status === 'done').length + '/' + prepareChecklist.length + ' complete'"></span>
                </div>

                {{-- Reusable status icon macro --}}
                {{-- Each section filters by item.type --}}

                {{-- Connection & Auth --}}
                <div class="mb-3">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1.5">Connection & Auth</p>
                    <template x-for="item in prepareChecklist.filter(c => c.type === 'auth')" :key="item.label">
                        @include('app-publish::publishing.pipeline.partials.checklist-item')
                    </template>
                </div>

                {{-- Content Processing --}}
                <div class="mb-3">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1.5">Content Processing</p>
                    <template x-for="item in prepareChecklist.filter(c => c.type === 'html')" :key="item.label">
                        @include('app-publish::publishing.pipeline.partials.checklist-item')
                    </template>
                </div>

                {{-- Media Upload — per-photo items with attempts --}}
                <div class="mb-3">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1.5">Media Upload</p>
                    <template x-for="item in prepareChecklist.filter(c => c.type === 'photo' || c.type === 'featured')" :key="item.label">
                        <div class="py-1.5 px-2 rounded-lg mb-1" :class="item.status === 'done' ? 'bg-green-50' : (item.status === 'failed' ? 'bg-red-50' : '')">
                            <div class="flex items-center gap-2.5">
                                @include('app-publish::publishing.pipeline.partials.checklist-icon')
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium" :class="{'text-gray-800': item.status === 'done', 'text-blue-700': item.status === 'running', 'text-red-700': item.status === 'failed', 'text-gray-400': item.status === 'pending', 'text-yellow-700': item.status === 'skipped'}" x-text="item.label"></span>
                                </div>
                            </div>
                            {{-- Source + filename info --}}
                            <div x-show="item.source || item.filename" x-cloak class="ml-7 mt-0.5 text-[10px] text-gray-400">
                                <span x-show="item.source" x-text="item.source"></span>
                                <span x-show="item.source && item.filename" class="mx-1">|</span>
                                <span x-show="item.filename" class="font-mono" x-text="item.filename"></span>
                            </div>
                            {{-- Attempt log --}}
                            <template x-if="item.attempts && item.attempts.length > 0">
                                <div class="ml-7 mt-1 space-y-0.5">
                                    <template x-for="(att, ai) in item.attempts" :key="ai">
                                        <p class="text-[10px]" :class="att.type === 'success' || att.text?.toLowerCase().includes('success') ? 'text-green-600' : (att.type === 'warning' ? 'text-red-500' : 'text-gray-400')" x-text="att.text"></p>
                                    </template>
                                </div>
                            </template>
                            {{-- Final detail — split into text lines, make URLs clickable --}}
                            <template x-if="item.detail">
                                <div class="ml-7 mt-0.5 text-[10px] text-gray-500 break-words space-y-0.5">
                                    <template x-for="(line, li) in (item.detail || '').split('\n').filter(Boolean)" :key="li">
                                        <p>
                                            <template x-if="line.match(/^WP:\s*https?:\/\//)">
                                                <span>WP: <a :href="line.replace(/^WP:\s*/, '')" target="_blank" class="text-blue-500 hover:underline inline-flex items-center gap-0.5" x-text="line.replace(/^WP:\s*/, '')"><svg class="w-2.5 h-2.5 inline flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></span>
                                            </template>
                                            <template x-if="!line.match(/^WP:\s*https?:\/\//)">
                                                <span x-text="line"></span>
                                            </template>
                                        </p>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Taxonomy — per category + per tag --}}
                <div class="mb-3" x-show="prepareChecklist.some(c => c.type === 'category')" x-cloak>
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1.5">Categories</p>
                    <template x-for="item in prepareChecklist.filter(c => c.type === 'category')" :key="item.label">
                        @include('app-publish::publishing.pipeline.partials.checklist-item')
                    </template>
                </div>
                <div x-show="prepareChecklist.some(c => c.type === 'tag')" x-cloak>
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1.5">Tags</p>
                    <template x-for="item in prepareChecklist.filter(c => c.type === 'tag')" :key="item.label">
                        @include('app-publish::publishing.pipeline.partials.checklist-item')
                    </template>
                </div>

                {{-- Integrity Check (always last) --}}
                <div class="mt-3" x-show="prepareChecklist.some(c => c.type === 'integrity')" x-cloak>
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1.5">Verification</p>
                    <template x-for="item in prepareChecklist.filter(c => c.type === 'integrity')" :key="item.label">
                        @include('app-publish::publishing.pipeline.partials.checklist-item')
                    </template>
                </div>

                {{-- Progress bar --}}
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="bg-green-500 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + Math.round((prepareChecklist.filter(c => c.status === 'done').length / prepareChecklist.length) * 100) + '%'"></div>
                    </div>
                </div>
            </div>

            {{-- Orphaned Media Cleanup --}}
            <div x-show="orphanedMedia && orphanedMedia.length > 0" x-cloak class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4">
                <h6 class="text-xs font-semibold text-orange-700 uppercase tracking-wide mb-2">Orphaned Media</h6>
                <p class="text-xs text-orange-600 mb-3">These photos were uploaded in a previous prepare but are no longer used. Delete them to free up storage on WordPress.</p>
                <div class="space-y-2">
                    <template x-for="(media, idx) in orphanedMedia" :key="media.media_id">
                        <div class="flex items-center gap-3 py-2 px-3 bg-white rounded-lg border border-orange-100" :class="media.deleted ? 'opacity-50' : ''">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-700" x-text="media.filename || ('media_id: ' + media.media_id)"></p>
                                <p class="text-[10px] text-gray-400 break-all" x-text="media.media_url || ''"></p>
                            </div>
                            <button x-show="!media.deleted" @click="deleteOrphanedMedia(idx)" :disabled="media.deleting" class="text-xs text-red-500 hover:text-red-700 px-3 py-1 border border-red-200 rounded hover:bg-red-50 inline-flex items-center gap-1 disabled:opacity-50 flex-shrink-0">
                                <svg x-show="media.deleting" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="media.deleting ? 'Deleting...' : 'Delete'"></span>
                            </button>
                            <span x-show="media.deleted" x-cloak class="text-xs text-green-600 font-medium flex-shrink-0">Deleted</span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Prepare Activity Log --}}
            <div x-show="prepareLog.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 mb-4 max-h-64 overflow-y-auto" x-ref="prepareLogContainer">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Activity Log</p>
                    <span class="text-[10px] text-gray-500" x-text="prepareLog.length + ' entries'"></span>
                </div>
                <template x-for="(entry, idx) in prepareLog" :key="idx">
                    <div class="py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <div class="flex items-start gap-2">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span :class="{'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info', 'text-yellow-400': entry.type === 'warning', 'text-gray-400': entry.type === 'step'}" x-text="entry.message" class="break-words"></span>
                        </div>
                        <div x-show="entry.stage || entry.trace_id || entry.duration_ms !== null" x-cloak class="ml-12 mt-0.5 text-[10px] text-gray-500 break-words">
                            <span x-show="entry.stage" x-text="(entry.stage || '') + (entry.substage ? '/' + entry.substage : '')"></span>
                            <span x-show="entry.stage && entry.trace_id" class="mx-1">|</span>
                            <span x-show="entry.trace_id" x-text="'trace ' + entry.trace_id"></span>
                            <span x-show="(entry.stage || entry.trace_id) && entry.duration_ms !== null" class="mx-1">|</span>
                            <span x-show="entry.duration_ms !== null" x-text="entry.duration_ms + 'ms'"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Uploaded photos report --}}
            <div x-show="uploadedImageList.length > 0" x-cloak class="mt-4">
                <h6 class="text-xs font-semibold text-gray-500 uppercase mb-2">Uploaded Photos</h6>
                <div class="space-y-3">
                    <template x-for="(img, imgIdx) in uploadedImageList" :key="_uploadedImageIdentity(img) || imgIdx">
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs space-y-1">
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Filename</span><span class="font-mono text-gray-800 break-all" x-text="img.filename || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Media ID</span><span class="text-gray-800" x-text="img.media_id || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Path</span><span class="font-mono text-gray-600 break-all" x-text="img.file_path || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Size</span><span class="text-gray-800" x-text="img.file_size ? (Math.round(img.file_size / 1024) + ' KB') : '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Alt Text</span><span class="text-gray-700 break-words" x-text="img.alt_text || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Caption</span><span class="text-gray-700 break-words" x-text="img.caption || '—'"></span></div>
                            <div x-show="img.source_url" class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Source</span><a :href="img.source_url" target="_blank" class="text-blue-500 hover:underline break-all inline-flex items-center gap-0.5" x-text="img.source_url"><svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></div>
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

        @if(request()->routeIs('publish.pipeline.v2'))
        <div class="mt-4" x-ref="inlineMasterActivityLog" data-inline-master-activity-log>
            @include('app-publish::publishing.pipeline.partials.master-activity-log')
        </div>
        @endif

        {{-- ═══ Publish Result — full post + photo report ═══ --}}
        <div x-show="publishResult" x-cloak class="bg-green-50 border border-green-200 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="font-semibold text-green-800 text-lg" x-text="publishResult?.message || 'Published successfully!'"></span>
            </div>

            {{-- Post details (row layout) --}}
            <div class="space-y-2 mb-4">
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Title</span><p class="text-sm font-medium text-gray-800 break-words" x-text="articleTitle || 'Untitled'"></p></div>
                <div x-show="publishResult?.post_status === 'publish' && publishResult?.post_url" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Permalink</span><a :href="publishResult?.post_url" target="_blank" class="text-sm text-blue-600 hover:underline break-all inline-flex items-center gap-1" x-text="publishResult?.post_url"><svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></div>
                <div x-show="publishResult?.post_id && selectedSite?.url" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">WordPress Admin</span><a :href="String(selectedSite?.url || '').replace(/\/+$/, '') + '/wp-admin/post.php?post=' + publishResult?.post_id + '&action=edit'" target="_blank" class="text-sm text-blue-600 hover:underline break-all inline-flex items-center gap-1" x-text="String(selectedSite?.url || '').replace(/\/+$/, '') + '/wp-admin/post.php?post=' + publishResult?.post_id + '&action=edit'"><svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></div>
                <div x-show="publishResult?.post_id" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">WP Post ID</span><p class="text-sm font-mono text-gray-800" x-text="publishResult?.post_id"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">WordPress Status</span><p class="text-sm text-gray-800" x-text="publishResult?.post_status === 'publish' ? 'Live' : (publishResult?.post_status === 'future' ? 'Scheduled' : 'Draft')"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Author</span><p class="text-sm text-gray-800" x-text="publishAuthor || 'Default'"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Website</span><p class="text-sm text-gray-800" x-text="selectedSite?.name || 'Local'"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Word Count</span><p class="text-sm text-gray-800" x-text="spunWordCount + ' words'"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">AI Model</span><p class="text-sm font-mono text-gray-800" x-text="aiModel"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Draft ID</span><p class="text-sm text-gray-800" x-text="'#' + draftId"></p></div>
                <div x-show="preparedFeaturedWpUrl || featuredPhoto?.url_large" class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Featured Image</span><div><a :href="preparedFeaturedWpUrl || featuredPhoto?.url_large" target="_blank" class="text-sm text-blue-600 hover:underline break-all" x-text="preparedFeaturedWpUrl || featuredPhoto?.url_large"></a><p x-show="preparedFeaturedWpUrl && featuredPhoto?.url_large && preparedFeaturedWpUrl !== featuredPhoto?.url_large" class="text-[10px] text-gray-400 mt-0.5">Source: <span x-text="featuredPhoto?.url_large"></span></p></div></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Date Created</span><p class="text-sm text-gray-800" x-text="new Date().toLocaleString()"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Categories</span><p class="text-sm text-gray-800 break-words" x-text="selectedCategoryNames().length ? selectedCategoryNames().join(', ') : 'None'"></p></div>
                <div class="flex items-start gap-3 py-1 border-b border-green-200"><span class="text-xs text-gray-500 w-24 flex-shrink-0">Tags</span><p class="text-sm text-gray-800 break-words" x-text="selectedTagNames().length ? selectedTagNames().join(', ') : 'None'"></p></div>
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
                <button type="button" @click="openStep(6); currentStep = 6; _syncStepToUrl && _syncStepToUrl()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white text-gray-700 text-xs font-medium rounded-lg border border-gray-300 hover:bg-gray-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Continue Editing
                </button>
                <button type="button" @click="publishAction = 'publish'; currentStep = 7; openStep(7); $nextTick(() => _focusPublishActionBox())" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white text-blue-700 text-xs font-medium rounded-lg border border-blue-200 hover:bg-blue-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Re-prepare Update
                </button>
                <a x-show="publishResult?.post_status === 'publish' && publishResult?.post_url" :href="publishResult?.post_url" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    View Live
                </a>
                <a :href="String(selectedSite?.url || '').replace(/\/+$/, '') + '/wp-admin/post.php?post=' + publishResult?.post_id + '&action=edit'" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-700 text-white text-xs font-medium rounded-lg hover:bg-gray-800">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Open in WP Admin
                </a>
            </div>

            <div x-show="(publishResult?.post_url || existingWpPostUrl) && publishAction !== 'draft_local'" x-cloak class="mt-6 bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h6 class="text-sm font-semibold text-gray-800">Client Notification Email</h6>
                        <p class="mt-1 text-xs text-gray-500">Choose a template, confirm the recipient fields, and send the live article notification after publication.</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700" x-text="(publishResult?.post_url || existingWpPostUrl) ? 'Permalink ready' : 'Awaiting permalink'"></span>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Template</label>
                            <select x-model="publicationNotificationTemplateId" @change="applyPublicationNotificationTemplate(publicationNotificationTemplateId, { force: true })" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">Default publication notification</option>
                                <template x-for="template in publicationNotificationTemplates" :key="template.id">
                                    <option :value="String(template.id)" x-text="template.name + (template.is_primary ? ' (Default)' : '')"></option>
                                </template>
                            </select>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">From name</label>
                                <input type="text" x-model="publicationNotificationFromName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">From email</label>
                                <input type="email" x-model="publicationNotificationFromEmail" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Reply-to</label>
                                <input type="email" x-model="publicationNotificationReplyTo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">CC</label>
                                <input type="text" x-model="publicationNotificationCc" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="name@example.com, team@example.com">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">To</label>
                            <input type="email" x-model="publicationNotificationTo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="client@example.com">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Subject</label>
                            <input type="text" x-model="publicationNotificationSubject" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Body</label>
                            <textarea x-model="publicationNotificationBody" rows="10" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"></textarea>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Available shortcodes</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(description, code) in publicationNotificationShortcodes" :key="code">
                                    <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] text-gray-700">
                                        <span class="font-mono" x-text="code"></span>
                                        <span class="text-gray-400">•</span>
                                        <span x-text="description"></span>
                                    </span>
                                </template>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">The permalink and publication values are resolved from the live post that was just published.</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="hydratePublicationNotificationFields({ force: true })" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Refresh Autofill
                            </button>
                            <button type="button" @click="sendPublicationNotification()" :disabled="publicationNotificationSending" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                                <svg x-show="publicationNotificationSending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="publicationNotificationSending ? 'Sending email…' : 'Send Client Email'"></span>
                            </button>
                            <span x-show="publicationNotificationStatus" x-cloak class="text-xs" :class="publicationNotificationStatus === 'success' ? 'text-green-600' : 'text-red-600'" x-text="publicationNotificationResult?.message || publicationNotificationStatus"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Uploaded photos full report --}}
            <div x-show="uploadedImageList.length > 0" x-cloak>
                <h6 class="text-sm font-semibold text-gray-700 mb-2">Photos</h6>
                <div class="space-y-3">
                    <template x-for="(img, imgIdx) in uploadedImageList" :key="_uploadedImageIdentity(img) || imgIdx">
                        <div class="bg-white border border-gray-200 rounded-lg p-3 text-xs space-y-1">
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Filename</span><span class="font-mono text-gray-800 break-all" x-text="img.filename || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Media ID</span><span class="text-gray-800" x-text="img.media_id || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Path</span><span class="font-mono text-gray-600 break-all" x-text="img.file_path || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Size</span><span class="text-gray-800" x-text="img.file_size ? (Math.round(img.file_size / 1024) + ' KB') : '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Alt Text</span><span class="text-gray-700 break-words" x-text="img.alt_text || '—'"></span></div>
                            <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Caption</span><span class="text-gray-700 break-words" x-text="img.caption || '—'"></span></div>
                            <div x-show="img.source_url" class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Source</span><a :href="img.source_url" target="_blank" class="text-blue-500 hover:underline break-all inline-flex items-center gap-0.5" x-text="img.source_url"><svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></div>
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
                    <span x-text="selectedSite?.name || 'Not selected'"></span>
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
                    <a x-show="!publishAuthor && selectedSite?.id" x-cloak :href="selectedSite?.id ? '/publish/sites/' + selectedSite.id : '#'" target="_blank" class="text-xs text-orange-500 hover:text-orange-700">Set in site settings</a>
                </div>
            </div>
            <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">Publish Action</span><p class="text-sm text-gray-800" x-text="publishAction === 'publish' ? (existingWpStatus === 'publish' ? 'Update live WordPress post' : (existingWpPostId ? 'Publish existing WordPress draft live' : 'Publish immediately')) : (publishAction === 'draft_wp' ? (existingWpPostId ? 'Update existing WordPress draft' : 'Create WordPress draft') : (publishAction === 'future' ? (existingWpPostId ? 'Schedule existing WordPress post' : 'Schedule post') : 'Save as local draft'))"></p></div>
            <div x-show="existingWpPostId" x-cloak class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 space-y-2">
                <div class="flex items-start gap-3 text-sm">
                    <span class="text-xs text-blue-500 w-28 flex-shrink-0 pt-0.5 uppercase tracking-wide">Existing WP Post</span>
                    <div class="space-y-1 text-blue-900">
                        <p><span class="font-mono" x-text="'#' + existingWpPostId"></span> already belongs to this article.</p>
                        <p class="text-xs text-blue-700" x-text="existingWpStatus === 'publish' ? 'WordPress reports this post as live.' : 'WordPress reports this post as a draft. Publishing from here should update the same post instead of creating a duplicate.'"></p>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <a x-show="existingWpAdminUrl" :href="existingWpAdminUrl" target="_blank" class="text-blue-700 hover:text-blue-900 underline">Open in WordPress admin</a>
                            <a x-show="existingWpStatus === 'publish' && existingWpPostUrl" :href="existingWpPostUrl" target="_blank" class="text-blue-700 hover:text-blue-900 underline">Open live URL</a>
                        </div>
                    </div>
                </div>
            </div>

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
    </div>
</div>
