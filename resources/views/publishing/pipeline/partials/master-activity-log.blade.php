<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <button @click.prevent="masterActivityLogOpen = !masterActivityLogOpen" type="button" data-master-activity-toggle class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-xl transition-colors">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-gray-800">Master Activity Log</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                      :class="pipelineDebugEnabled ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'"
                      x-text="pipelineDebugEnabled ? 'Debug On' : 'Debug Off'"></span>
                <span class="text-xs text-gray-400" x-text="visibleMasterActivityEntries.length + ' visible / ' + masterActivityLog.length + ' total'"></span>
            </div>
            <p class="text-xs text-gray-500 mt-1 truncate"
               x-text="lastMasterActivityEntry ? (lastMasterActivityEntry.time + ' • ' + lastMasterActivityEntry.message) : 'End-to-end pipeline trace across restore, requests, saves, prepare, publish, and UI events.'"></p>
        </div>
        <svg class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0" :class="masterActivityLogOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="masterActivityLogOpen" x-cloak x-collapse class="px-4 pb-4">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <button @click.stop.prevent="togglePipelineDebug()"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors"
                    :class="pipelineDebugEnabled ? 'bg-red-50 border-red-200 text-red-700 hover:bg-red-100' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                <span x-text="pipelineDebugEnabled ? 'Disable Verbose Debug' : 'Enable Verbose Debug'"></span>
            </button>
            <button @click.stop.prevent="capturePipelineSnapshot()"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100 transition-colors">
                Capture Snapshot
            </button>
            <button @click.stop.prevent="copyMasterActivityLog()"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 text-xs font-medium hover:bg-gray-50 transition-colors">
                Copy JSON
            </button>
            <button @click.stop.prevent="downloadMasterActivityLog()"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 text-xs font-medium hover:bg-gray-50 transition-colors">
                Download JSON
            </button>
            <button @click.stop.prevent="clearMasterActivityLog()"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 text-xs font-medium hover:bg-gray-50 transition-colors">
                Clear
            </button>
            <button @click.stop.prevent="refreshActivityRunHistory()"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 text-xs font-medium hover:bg-gray-50 transition-colors">
                Reload Runs
            </button>
            <label class="inline-flex items-center gap-2 text-xs text-gray-500 ml-auto">
                <input type="checkbox" x-model="masterActivityAutoScroll" class="rounded border-gray-300 text-blue-600">
                Auto-scroll
            </label>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 mb-3" x-show="!pipelineDebugEnabled" x-cloak>
            Verbose request payloads, response previews, and duplicate-save skip traces are hidden until debug is enabled.
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800 mb-3">
            This log survives reloads in local browser storage and also syncs durable per-draft run history to the backend.
        </div>

        <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-3 text-xs text-indigo-900 mb-3" data-activity-runs>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div>
                    <p class="font-semibold">Recent Runs</p>
                    <p class="text-indigo-700 mt-0.5">Switch between the live session trace and persisted draft runs.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span x-show="activityRunHistoryLoading" x-cloak class="inline-flex items-center gap-1 text-[11px] text-indigo-700">
                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading…
                    </span>
                    <button @click.stop.prevent="clearActivityRunPreview({ restoreLive: true })"
                            type="button"
                            class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg border text-[11px] font-medium transition-colors"
                            :class="selectedActivityRunTrace ? 'border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-100' : 'border-indigo-300 bg-indigo-600 text-white hover:bg-indigo-700'">
                        Live Session
                    </button>
                </div>
            </div>

            <div x-show="selectedActivityRunTrace" x-cloak data-activity-run-preview class="rounded-lg border border-indigo-200 bg-white px-3 py-2 mb-2">
                <p class="font-medium text-indigo-900">Viewing persisted run</p>
                <p class="text-indigo-700 mt-0.5" x-text="selectedActivityRun ? (selectedActivityRun.client_trace + ' • ' + selectedActivityRun.stage_label) : selectedActivityRunTrace"></p>
            </div>

            <div x-show="activityRunHistory.length === 0" x-cloak class="rounded-lg border border-dashed border-indigo-200 bg-white px-3 py-3 text-indigo-700">
                No persisted runs recorded for this draft yet.
            </div>

            <div x-show="activityRunHistory.length > 0" x-cloak class="space-y-2">
                <template x-for="run in activityRunHistory" :key="run.client_trace">
                    <button @click.stop.prevent="_loadActivityRun(run.client_trace)"
                            type="button"
                            data-activity-run-row
                            class="w-full rounded-lg border px-3 py-2 text-left transition-colors"
                            :class="selectedActivityRunTrace === run.client_trace ? 'border-indigo-400 bg-white shadow-sm' : 'border-indigo-100 bg-white/70 hover:bg-white'">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-indigo-900" x-text="run.workflow_type"></span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-indigo-100 text-[10px] font-medium text-indigo-700" x-text="run.total_events + ' events'"></span>
                            <span x-show="run.debug_enabled" x-cloak class="inline-flex items-center px-1.5 py-0.5 rounded bg-red-100 text-[10px] font-medium text-red-700">debug</span>
                            <span class="text-[11px] text-indigo-700" x-text="run.stage_label"></span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-indigo-700">
                            <span x-text="run.client_trace"></span>
                            <span>•</span>
                            <span x-text="run.last_event_at_label || run.started_at_label || 'time unknown'"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-3 text-xs text-emerald-900 mb-3" data-cross-draft-runs>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div>
                    <p class="font-semibold">Recent Draft Runs</p>
                    <p class="text-emerald-700 mt-0.5">Jump directly into other draft sessions with recent publish activity.</p>
                </div>
                <span x-show="activityRunHistoryLoading" x-cloak class="inline-flex items-center gap-1 text-[11px] text-emerald-700">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading…
                </span>
            </div>

            <div x-show="crossDraftActivityRunsVisible.length === 0" x-cloak class="rounded-lg border border-dashed border-emerald-200 bg-white px-3 py-3 text-emerald-700">
                No other recent draft runs available.
            </div>

            <div x-show="crossDraftActivityRunsVisible.length > 0" x-cloak class="space-y-2">
                <template x-for="run in crossDraftActivityRunsVisible" :key="'cross:' + run.client_trace">
                    <div data-cross-draft-run-row class="rounded-lg border border-emerald-100 bg-white px-3 py-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-emerald-900" x-text="'Draft #' + run.draft_id"></span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-100 text-[10px] font-medium text-emerald-700" x-text="run.total_events + ' events'"></span>
                                    <span class="text-[11px] text-emerald-700" x-text="run.workflow_type"></span>
                                </div>
                                <p class="mt-1 text-[11px] text-emerald-800 truncate" x-text="run.draft_title || 'Untitled draft'"></p>
                                <p class="mt-1 text-[11px] text-emerald-700" x-text="run.stage_label + ' • ' + (run.last_event_at_label || run.started_at_label || 'time unknown')"></p>
                            </div>
                            <button @click.stop.prevent="_openCrossDraftActivityRun(run)"
                                    type="button"
                                    class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg border border-emerald-200 bg-emerald-50 text-[11px] font-medium text-emerald-800 hover:bg-emerald-100 transition-colors">
                                Open Draft
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-3 text-xs text-sky-900 mb-3" data-api-service-summary>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <p class="font-semibold">API Call Activity</p>
                    <p class="text-sky-700 mt-0.5">Grouped external calls for this entire draft across persisted runs, including AI, search, WordPress, and remote fetches.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span x-show="draftApiActivityLoading" x-cloak class="inline-flex items-center gap-1 text-[11px] text-sky-700">
                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading…
                    </span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-white text-[11px] font-medium text-sky-800"
                          x-text="apiCallActivityEntries.length + ' call' + (apiCallActivityEntries.length === 1 ? '' : 's')"></span>
                    <span x-show="apiCallRunCount > 0" x-cloak class="inline-flex items-center px-2 py-1 rounded-full bg-white text-[11px] font-medium text-sky-800"
                          x-text="apiCallRunCount + ' run' + (apiCallRunCount === 1 ? '' : 's')"></span>
                    <span x-show="apiCallErrorCount > 0" x-cloak class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-[11px] font-medium text-red-700"
                          x-text="apiCallErrorCount + ' error' + (apiCallErrorCount === 1 ? '' : 's')"></span>
                    <span x-show="apiCallCostTotal !== null" x-cloak class="inline-flex items-center px-2 py-1 rounded-full bg-emerald-100 text-[11px] font-medium text-emerald-700"
                          x-text="formatApiCost(apiCallCostTotal)"></span>
                </div>
            </div>

            <div x-show="apiServiceSummaries.length === 0" x-cloak class="rounded-lg border border-dashed border-sky-200 bg-white px-3 py-3 text-sky-700">
                No persisted outbound API calls have been captured for this draft yet.
            </div>

            <div x-show="apiServiceSummaries.length > 0" x-cloak class="space-y-3">
                <template x-for="service in apiServiceSummaries" :key="'svc:' + service.key">
                    <div class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-sky-900" x-text="service.label"></span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-sky-100 text-[10px] font-medium text-sky-700"
                                          x-text="service.call_count + ' call' + (service.call_count === 1 ? '' : 's')"></span>
                                    <span x-show="service.error_count > 0" x-cloak class="inline-flex items-center px-1.5 py-0.5 rounded bg-red-100 text-[10px] font-medium text-red-700"
                                          x-text="service.error_count + ' error' + (service.error_count === 1 ? '' : 's')"></span>
                                    <span x-show="service.total_cost_usd !== null" x-cloak class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-100 text-[10px] font-medium text-emerald-700"
                                          x-text="formatApiCost(service.total_cost_usd)"></span>
                                </div>
                                <p x-show="service.host" x-cloak class="mt-1 text-[11px] text-sky-700" x-text="service.host"></p>
                            </div>
                        </div>

                        <div class="mt-3 space-y-2">
                            <template x-for="entry in service.entries" :key="apiCallEntryKey(entry)">
                                <div class="rounded-lg border border-sky-100 bg-slate-50 overflow-hidden">
                                    <button @click.stop.prevent="toggleApiCallEntry(entry)"
                                            type="button"
                                            class="w-full px-3 py-2 text-left hover:bg-slate-100 transition-colors">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-medium text-slate-900" x-text="entry.method || 'CALL'"></span>
                                                    <span class="text-slate-700 break-all" x-text="entry.url || entry.message"></span>
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-600">
                                                    <span x-text="formatApiCallWhen(entry)"></span>
                                                    <span x-show="entry.actor_name" x-cloak x-text="'User: ' + entry.actor_name"></span>
                                                    <span x-show="entry.run_trace" x-cloak class="break-all" x-text="'Run: ' + entry.run_trace"></span>
                                                    <span x-show="entry.duration_ms !== null" x-cloak x-text="entry.duration_ms + 'ms'"></span>
                                                    <span x-show="entry.model" x-cloak x-text="entry.model"></span>
                                                    <span x-show="formatApiTokens(entry)" x-cloak x-text="formatApiTokens(entry) + ' tokens'"></span>
                                                    <span x-show="entry.cost_usd !== null" x-cloak x-text="formatApiCost(entry.cost_usd)"></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                                                      :class="entry.type === 'error' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                                      x-text="entry.status ? ('HTTP ' + entry.status) : (entry.type === 'error' ? 'failed' : 'ok')"></span>
                                                <svg class="w-4 h-4 text-slate-400 transition-transform"
                                                     :class="isApiCallEntryExpanded(entry) ? 'rotate-180' : ''"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </button>

                                    <div x-show="isApiCallEntryExpanded(entry)" x-cloak class="border-t border-sky-100 bg-white px-3 py-3 space-y-3">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[11px] text-slate-700">
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                                <p class="font-semibold text-slate-900 mb-1">Request</p>
                                                <div class="space-y-1">
                                                    <p><span class="font-medium">Method:</span> <span x-text="entry.method || 'GET'"></span></p>
                                                    <p><span class="font-medium">URL:</span> <span class="break-all" x-text="entry.url || ''"></span></p>
                                                    <p x-show="entry.run_trace" x-cloak><span class="font-medium">Run:</span> <span class="break-all" x-text="entry.run_trace"></span></p>
                                                    <p x-show="entry.actor_name" x-cloak><span class="font-medium">User:</span> <span x-text="entry.actor_name"></span></p>
                                                    <p><span class="font-medium">When:</span> <span x-text="formatApiCallWhen(entry)"></span></p>
                                                </div>
                                            </div>

                                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                                <p class="font-semibold text-slate-900 mb-1">Response</p>
                                                <div class="space-y-1">
                                                    <p><span class="font-medium">Status:</span> <span x-text="entry.status ? ('HTTP ' + entry.status) : (entry.response_error || 'failed')"></span></p>
                                                    <p x-show="entry.duration_ms !== null" x-cloak><span class="font-medium">Duration:</span> <span x-text="entry.duration_ms + 'ms'"></span></p>
                                                    <p x-show="entry.model" x-cloak><span class="font-medium">Model:</span> <span x-text="entry.model"></span></p>
                                                    <p x-show="entry.total_tokens !== null || entry.input_tokens !== null || entry.output_tokens !== null" x-cloak>
                                                        <span class="font-medium">Tokens:</span>
                                                        <span x-text="formatApiTokens(entry)"></span>
                                                    </p>
                                                    <p x-show="entry.cost_usd !== null" x-cloak><span class="font-medium">Cost:</span> <span x-text="formatApiCost(entry.cost_usd)"></span></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div x-show="entry.request_headers || entry.request_payload_full" x-cloak class="space-y-2">
                                            <p class="text-[11px] font-semibold text-slate-900">Full Request Detail</p>
                                            <pre class="rounded-lg bg-slate-950 text-slate-100 p-3 text-[11px] overflow-x-auto whitespace-pre-wrap break-words"
                                                 x-text="formatApiPayload({ headers: entry.request_headers || {}, payload: entry.request_payload_full })"></pre>
                                        </div>

                                        <div x-show="entry.response_headers || entry.response_payload_full || entry.response_error" x-cloak class="space-y-2">
                                            <p class="text-[11px] font-semibold text-slate-900">Full Response Detail</p>
                                            <pre class="rounded-lg bg-slate-950 text-emerald-100 p-3 text-[11px] overflow-x-auto whitespace-pre-wrap break-words"
                                                 x-text="formatApiPayload({ headers: entry.response_headers || {}, payload: entry.response_payload_full, error: entry.response_error || '' })"></pre>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div x-show="visibleMasterActivityEntries.length === 0" x-cloak class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
            No activity captured yet.
        </div>

        <div x-show="visibleMasterActivityEntries.length > 0" x-cloak class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-2">
            Full Trace
        </div>

        <div x-show="visibleMasterActivityEntries.length > 0"
             x-cloak
             x-ref="masterActivityLogContainer"
             class="bg-gray-950 rounded-xl border border-gray-800 p-4 max-h-[32rem] overflow-y-auto space-y-2">
            <template x-for="entry in visibleMasterActivityEntries" :key="entry.client_event_id || entry.id">
                <div class="rounded-lg border border-gray-900 bg-gray-900/70 px-3 py-2 font-mono text-xs">
                    <div class="flex items-start gap-2">
                        <span class="text-gray-500 flex-shrink-0 w-20" x-text="entry.time"></span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wide flex-shrink-0"
                              :class="{
                                  'bg-blue-500/20 text-blue-300': entry.scope === 'network',
                                  'bg-emerald-500/20 text-emerald-300': entry.scope === 'prepare' || entry.scope === 'publish',
                                  'bg-purple-500/20 text-purple-300': entry.scope === 'spin' || entry.scope === 'ai',
                                  'bg-amber-500/20 text-amber-300': entry.scope === 'state' || entry.scope === 'draft',
                                  'bg-cyan-500/20 text-cyan-300': entry.scope === 'restore' || entry.scope === 'snapshot',
                                  'bg-gray-700 text-gray-200': ['ui', 'lifecycle', 'debug'].includes(entry.scope),
                              }"
                              x-text="entry.scope"></span>
                        <span class="flex-1 break-words"
                              :class="{
                                  'text-green-400': entry.type === 'success',
                                  'text-red-400': entry.type === 'error',
                                  'text-yellow-300': entry.type === 'warning',
                                  'text-blue-300': entry.type === 'info' || entry.type === 'step',
                                  'text-fuchsia-300': entry.type === 'debug',
                                  'text-gray-200': !['success', 'error', 'warning', 'info', 'step', 'debug'].includes(entry.type),
                              }"
                              x-text="entry.message"></span>
                    </div>

                    <div class="ml-[7rem] mt-1 space-y-1 text-[10px] text-gray-500">
                        <div class="flex flex-wrap gap-x-2 gap-y-1">
                            <span x-show="entry.method" x-text="entry.method"></span>
                            <span x-show="entry.url" x-text="entry.url"></span>
                            <span x-show="entry.status !== null" x-text="'HTTP ' + entry.status"></span>
                            <span x-show="entry.stage" x-text="entry.stage + (entry.substage ? '/' + entry.substage : '')"></span>
                            <span x-show="entry.trace_id" x-text="'trace ' + entry.trace_id"></span>
                            <span x-show="entry.sequence_no !== null" x-text="'seq ' + entry.sequence_no"></span>
                            <span x-show="entry.duration_ms !== null" x-text="entry.duration_ms + 'ms'"></span>
                            <span x-show="entry.step !== null" x-text="'step ' + entry.step"></span>
                            <span x-show="entry.draft_id" x-text="'draft #' + entry.draft_id"></span>
                        </div>
                        <p x-show="entry.details" x-cloak class="break-words whitespace-pre-wrap" x-text="entry.details"></p>
                        <p x-show="pipelineDebugEnabled && entry.payload_preview" x-cloak class="break-words whitespace-pre-wrap text-cyan-300" x-text="'request: ' + entry.payload_preview"></p>
                        <p x-show="pipelineDebugEnabled && entry.response_preview" x-cloak class="break-words whitespace-pre-wrap text-emerald-300" x-text="'response: ' + entry.response_preview"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
