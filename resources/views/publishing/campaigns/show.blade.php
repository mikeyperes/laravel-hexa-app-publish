@extends('layouts.app')
@section('title', 'Campaign — ' . ($campaign->name ?: 'Untitled'))
@section('header', '')

@section('content')
<style>
    .hx-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:visible; margin-bottom:24px; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    .hx-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:20px 24px; border-bottom:1px solid #f1f5f9; }
    .hx-card-header.hx-clickable { cursor:pointer; user-select:none; transition:background 0.12s; }
    .hx-card-header.hx-clickable:hover { background:#fafbfc; }
    .hx-card-title-block { display:flex; align-items:flex-start; gap:14px; min-width:0; flex:1; }
    .hx-card-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:#eff6ff; color:#2563eb; }
    .hx-card-icon svg { width:20px; height:20px; }
    .hx-card-icon.green  { background:#f0fdf4; color:#16a34a; }
    .hx-card-icon.amber  { background:#fffbeb; color:#d97706; }
    .hx-card-icon.purple { background:#faf5ff; color:#9333ea; }
    .hx-card-icon.pink   { background:#fdf2f8; color:#db2777; }
    .hx-card-icon.slate  { background:#f1f5f9; color:#475569; }
    .hx-card-icon.indigo { background:#eef2ff; color:#4f46e5; }
    .hx-card-icon.red    { background:#fef2f2; color:#dc2626; }
    .hx-card-title { font-size:16px; font-weight:700; color:#111827; margin:0; line-height:1.3; }
    .hx-card-subtitle { font-size:13px; color:#6b7280; margin-top:4px; line-height:1.45; }
    .hx-card-body { padding:24px; }
    .hx-card-header-right { display:flex; align-items:center; gap:10px; flex-shrink:0; }
    .hx-tag { display:inline-flex; align-items:center; padding:2px 9px; border-radius:9999px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap; }
    .hx-tag.blue  { background:#dbeafe; color:#1d4ed8; }
    .hx-tag.green { background:#dcfce7; color:#166534; }
    .hx-tag.amber { background:#fef3c7; color:#92400e; }
    .hx-tag.red   { background:#fee2e2; color:#991b1b; }
    .hx-tag.gray  { background:#f3f4f6; color:#4b5563; }
    .hx-tag.slate { background:#f1f5f9; color:#334155; }
    .hx-label { display:block; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; }
    .hx-input, .hx-select, .hx-textarea { width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; background:#fff; color:#111827; transition:border-color 0.1s, box-shadow 0.1s; }
    .hx-input:focus, .hx-select:focus, .hx-textarea:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,0.2); }
    .hx-textarea { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; resize:vertical; min-height:72px; }
    .hx-select { appearance:none; -webkit-appearance:none; -moz-appearance:none; padding-right:36px; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:16px 16px; cursor:pointer; }
    .hx-select:hover { border-color:#94a3b8; }
    .hx-select option { background:#fff; color:#111827; }
    .hx-autocomplete { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:16px 16px; padding-right:36px; }
    .hx-field { margin-bottom:20px; }
    .hx-field:last-child { margin-bottom:0; }
    .hx-field-hint { font-size:12px; color:#9ca3af; margin-top:6px; }
    .hx-grid-2 { display:grid; grid-template-columns:1fr; gap:20px; }
    @media(min-width:640px) { .hx-grid-2 { grid-template-columns:1fr 1fr; } }
    .hx-grid-3 { display:grid; grid-template-columns:1fr; gap:20px; }
    @media(min-width:640px) { .hx-grid-3 { grid-template-columns:repeat(3, 1fr); } }
    .hx-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; font-size:13px; font-weight:500; border:1px solid transparent; cursor:pointer; transition:background 0.1s, opacity 0.1s; white-space:nowrap; }
    .hx-btn-primary { background:#2563eb; color:#fff; }
    .hx-btn-primary:hover:not(:disabled) { background:#1d4ed8; }
    .hx-btn-secondary { background:#fff; color:#374151; border-color:#d1d5db; }
    .hx-btn-secondary:hover:not(:disabled) { background:#f9fafb; }
    .hx-btn-green { background:#16a34a; color:#fff; }
    .hx-btn-green:hover:not(:disabled) { background:#15803d; }
    .hx-btn-amber { background:#d97706; color:#fff; }
    .hx-btn-amber:hover:not(:disabled) { background:#b45309; }
    .hx-btn-red { background:#fff; color:#b91c1c; border-color:#fecaca; }
    .hx-btn-red:hover:not(:disabled) { background:#fef2f2; }
    .hx-btn:disabled { opacity:0.55; cursor:not-allowed; }
    .hx-chev { transition:transform 0.15s; color:#9ca3af; }
    .hx-chev.open { transform:rotate(180deg); }
    .hx-link { color:#2563eb; }
    .hx-link:hover { color:#1d4ed8; text-decoration:underline; }
    .hx-link-muted { color:#64748b; }
    .hx-link-muted:hover { color:#334155; text-decoration:underline; }
    [x-cloak] { display:none !important; }
</style>

<div class="max-w-6xl mx-auto pt-4 flex flex-col" x-data="campaignDashboard()" x-init="init()">

    {{-- ───────────────────────────────────────────────
         HEADER CARD
         ─────────────────────────────────────────────── --}}
    <div class="hx-card">
        <div class="hx-card-header">
            <div class="hx-card-title-block">
                <div class="hx-card-icon amber">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <input type="text" x-model="form.name" class="w-full text-xl font-bold text-gray-900 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none focus:border-b focus:border-blue-500" placeholder="Untitled Campaign">
                    <div class="hx-card-subtitle flex flex-wrap items-center gap-2 mt-2">
                        <span class="hx-tag gray">{{ $campaign->campaign_id ?? ('#' . $campaign->id) }}</span>
                        <span class="hx-tag" :class="campaignStatus === 'active' ? 'green' : (campaignStatus === 'paused' ? 'amber' : (campaignStatus === 'archived' ? 'red' : 'gray'))" x-text="formatLabel(campaignStatus)"></span>
                        <span class="text-xs text-gray-500">Created by {{ $campaign->creator->name ?? '—' }} · {{ $campaign->created_at ? $campaign->created_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A') : '—' }}</span>
                    </div>
                </div>
            </div>
            <div class="hx-card-header-right">
                <button @click="startOperation('draft-wordpress', 'Instant Draft')" :disabled="running" class="hx-btn hx-btn-secondary">
                    <svg x-show="running && runningMode === 'draft-wordpress'" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg x-show="!running || runningMode !== 'draft-wordpress'" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <span>Instant Draft</span>
                </button>
                <button @click="startOperation('auto-publish', 'Instant Publish')" :disabled="running" class="hx-btn hx-btn-primary">
                    <svg x-show="running && runningMode === 'auto-publish'" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg x-show="!running || runningMode !== 'auto-publish'" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <span>Instant Publish</span>
                </button>
                <button x-show="running || staleOperationInfo" x-cloak @click="stopOperation()" :disabled="stoppingRun" class="hx-btn hx-btn-red">
                    <svg x-show="stoppingRun" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg x-show="!stoppingRun" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z"/></svg>
                    <span x-text="staleOperationInfo && !running ? 'Force Stop Stale Run' : 'Force Stop Run'"></span>
                </button>
                <button x-show="campaignStatus === 'active'" x-cloak @click="pauseCampaign()" class="hx-btn hx-btn-amber">Pause</button>
                <button x-show="campaignStatus !== 'active'" x-cloak @click="activateCampaign()" class="hx-btn hx-btn-green">Activate</button>
                <button @click="duplicateCampaign()" class="hx-btn hx-btn-secondary">Duplicate</button>
                <button @click="deleteCampaign()" class="hx-btn hx-btn-red">Delete</button>
            </div>
        </div>
        <div class="px-5 py-2 text-xs border-t border-gray-100 flex items-center justify-between bg-gray-50/50 rounded-b-xl">
            <div class="text-gray-500">
                <span class="mr-4"><span class="text-gray-400">Last run:</span> <span class="font-semibold text-gray-800">{{ $campaign->last_run_at ? $campaign->last_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : 'Never' }}</span></span>
                <span><span class="text-gray-400">Next run:</span> <span class="font-semibold text-gray-800">{{ $campaign->next_run_at ? $campaign->next_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : '—' }}</span></span>
            </div>
            <div>
                <span x-show="saving" x-cloak class="text-gray-400">Saving…</span>
                <span x-show="!saving && saveResult" x-cloak :class="saveSuccess ? 'text-green-600' : 'text-red-500'" x-text="saveResult"></span>
            </div>
        </div>
        <div class="px-5 py-3 text-xs border-t border-gray-100 bg-white rounded-b-xl flex flex-wrap items-center justify-between gap-2">
            <div class="text-gray-500">
                Use <span class="font-semibold text-gray-700">Instant Draft</span> or <span class="font-semibold text-gray-700">Instant Publish</span> to spawn another article from this same campaign using the current campaign-only overrides.
            </div>
            <div x-show="staleOperationInfo && !running" x-cloak class="text-amber-700">
                A stale run is blocking new spawns until it is force-stopped.
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         INTEGRITY REPORT
         ─────────────────────────────────────────────── --}}
    <div class="hx-card">
        <div class="hx-card-header">
            <div class="hx-card-title-block">
                <div class="hx-card-icon red">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Campaign Integrity Report</h3>
                    <p class="hx-card-subtitle">Checks coherence across presets, WordPress connection state, author resolution, delivery rules, and the historical article record.</p>
                </div>
            </div>
            <div class="hx-card-header-right flex-wrap justify-end">
                <span class="hx-tag" :class="integrityReport?.summary?.status === 'error' ? 'red' : (integrityReport?.summary?.status === 'warning' ? 'amber' : 'green')" x-text="integritySummaryLabel()"></span>
                <button @click="runIntegrityReport(false)" :disabled="integrityRunning" class="hx-btn hx-btn-secondary">
                    <span x-text="integrityRunning ? 'Running…' : 'Run Full Integrity Report'"></span>
                </button>
                <button @click="refreshAuthorsFromSource()" :disabled="integrityRunning || !form.publish_site_id" class="hx-btn hx-btn-secondary">Refresh Authors From Source</button>
            </div>
        </div>
        <div class="hx-card-body">
            <div class="hx-grid-3 mb-4">
                <div class="rounded-xl border border-gray-200 px-4 py-3 bg-gray-50">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">WordPress connection</div>
                    <div class="mt-2 text-sm text-gray-900" x-text="siteConnectionSummary()"></div>
                </div>
                <div class="rounded-xl border border-gray-200 px-4 py-3 bg-gray-50">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Author cache</div>
                    <div class="mt-2 text-sm text-gray-900" x-text="authorCacheSummary()"></div>
                </div>
                <div class="rounded-xl border border-gray-200 px-4 py-3 bg-gray-50">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last report</div>
                    <div class="mt-2 text-sm text-gray-900" x-text="integrityReport?.generated_at ? formatDateTime(integrityReport.generated_at) : 'Not run yet'"></div>
                </div>
            </div>

            <div x-show="(integrityReport?.issues || []).length === 0" x-cloak class="rounded-xl border border-green-200 bg-green-50 px-4 py-4 text-sm text-green-800">
                No blocking or warning issues were detected. This campaign currently looks coherent.
            </div>

            <div x-show="(integrityReport?.issues || []).length > 0" x-cloak class="space-y-3">
                <template x-for="(issue, idx) in integrityReport.issues" :key="issue.code + '-' + idx">
                    <div class="rounded-xl border px-4 py-4" :class="issue.severity === 'error' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold" :class="issue.severity === 'error' ? 'text-red-900' : 'text-amber-900'" x-text="issue.title"></div>
                                <p class="mt-1 text-sm" :class="issue.severity === 'error' ? 'text-red-800' : 'text-amber-800'" x-text="issue.message"></p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="hx-tag" :class="issue.severity === 'error' ? 'red' : 'amber'" x-text="issue.blocking ? 'Blocking' : formatLabel(issue.severity)"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         LIVE RUN CHECKLIST
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-ref="liveChecklistCard">
        <div class="hx-card-header">
            <div class="hx-card-title-block">
                <div class="hx-card-icon indigo">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Instant Run Output</h3>
                    <p class="hx-card-subtitle">Streams the same campaign runner stages the cron system uses, plus the article publish checklist, in real time.</p>
                </div>
            </div>
            <div class="hx-card-header-right text-right">
                <div class="text-xs text-gray-500 space-y-1">
                    <p x-show="operationLabel" x-cloak class="font-medium text-gray-700" x-text="operationLabel"></p>
                    <p x-show="operationStatus" x-cloak x-text="'Status: ' + operationStatus + (operationTransport ? ' via ' + operationTransport.replace(/_/g, ' ') : '')"></p>
                    <p x-show="operationId" x-cloak x-text="'Operation: #' + operationId + (operationOutput.article_record_id ? ' · Article #' + operationOutput.article_record_id : '')"></p>
                    <p x-show="operationTraceId" x-cloak x-text="'Trace: ' + operationTraceId"></p>
                    <p x-show="operationStartedAt || running" x-cloak x-text="'Elapsed: ' + operationElapsedText()"></p>
                    <p x-show="campaignChecklist.length > 0" x-cloak x-text="checklistProgressText()"></p>
                </div>
            </div>
        </div>
        <div class="hx-card-body">
            <div x-show="runResult" x-cloak class="mb-4 p-3 rounded-lg text-sm border" :class="{
                'bg-blue-50 border-blue-200 text-blue-800': runState === 'info',
                'bg-green-50 border-green-200 text-green-800': runState === 'success',
                'bg-red-50 border-red-200 text-red-800': runState === 'error'
            }" x-text="runResult"></div>

            <div x-show="staleOperationInfo && !running" x-cloak class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Stale Run Detected</div>
                        <p class="mt-1 text-sm text-amber-900">
                            Operation <span class="font-semibold" x-text="staleOperationInfo ? '#' + staleOperationInfo.id : ''"></span>
                            stopped updating and is not auto-resumed anymore.
                            Force stop it before spawning another article.
                        </p>
                        <div class="mt-2 text-xs text-amber-800 space-y-1">
                            <p x-show="staleOperationInfo && staleOperationInfo.last_stage" x-cloak x-text="staleOperationInfo && staleOperationInfo.last_stage ? 'Last stage: ' + formatLabel(staleOperationInfo.last_stage) + (staleOperationInfo.last_substage ? ' / ' + formatLabel(staleOperationInfo.last_substage) : '') : ''"></p>
                            <p x-show="staleOperationInfo && staleOperationInfo.last_message" x-cloak x-text="staleOperationInfo && staleOperationInfo.last_message ? 'Last message: ' + staleOperationInfo.last_message : ''"></p>
                            <p x-show="staleOperationInfo && staleOperationInfo.last_event_at" x-cloak x-text="staleOperationInfo && staleOperationInfo.last_event_at ? 'Last event: ' + formatDateTime(staleOperationInfo.last_event_at) : ''"></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a x-show="staleOperationArticle?.show_url" x-cloak :href="staleOperationArticle ? staleOperationArticle.show_url : '#'" target="_blank" rel="noopener" class="hx-btn hx-btn-secondary">Open Stale Article</a>
                        <button type="button" @click="stopOperation()" :disabled="stoppingRun" class="hx-btn hx-btn-red">
                            <span x-text="stoppingRun ? 'Stopping…' : 'Force Stop Stale Run'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="operationCurrent" x-cloak class="mb-4 text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2" x-text="operationCurrent"></div>

            <div x-show="operationOutput.article_url || operationOutput.wp_post_url" x-cloak class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-green-700">Latest Output</div>
                        <p class="mt-1 text-sm text-green-900">This run spawned an article and returned publish targets.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a x-show="operationOutput.article_url" x-cloak :href="operationOutput.article_url" class="hx-btn hx-btn-secondary" target="_blank" rel="noopener">Open Article</a>
                        <a x-show="operationOutput.wp_post_url" x-cloak :href="operationOutput.wp_post_url" class="hx-btn hx-btn-primary" target="_blank" rel="noopener">Open WordPress</a>
                    </div>
                </div>
            </div>

            <div x-show="campaignChecklist.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-500 text-center">
                Click <span class="font-medium text-gray-700">Instant Draft</span> or <span class="font-medium text-gray-700">Instant Publish</span> to open the full live checklist and cron-style activity feed.
            </div>

            <div x-show="campaignChecklist.length > 0" x-cloak class="space-y-3">
                <template x-for="item in campaignChecklist" :key="item.key">
                    <div class="flex items-start gap-3 border border-gray-100 rounded-lg px-4 py-3">
                        <div class="w-5 h-5 mt-0.5 flex-shrink-0">
                            <template x-if="item.status === 'done'">
                                <div class="w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-[11px] font-bold">✓</div>
                            </template>
                            <template x-if="item.status === 'failed'">
                                <div class="w-5 h-5 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-[11px] font-bold">!</div>
                            </template>
                            <template x-if="item.status === 'running'">
                                <div class="w-5 h-5 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
                            </template>
                            <template x-if="item.status === 'pending'">
                                <div class="w-5 h-5 rounded-full border border-gray-300 bg-white"></div>
                            </template>
                            <template x-if="item.status === 'skipped'">
                                <div class="w-5 h-5 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-[11px] font-bold">-</div>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-medium text-gray-900" x-text="item.label"></h4>
                                <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full"
                                      :class="{
                                          'bg-green-100 text-green-700': item.status === 'done',
                                          'bg-red-100 text-red-700': item.status === 'failed',
                                          'bg-blue-100 text-blue-700': item.status === 'running',
                                          'bg-gray-100 text-gray-500': item.status === 'pending' || item.status === 'skipped'
                                      }"
                                      x-text="item.status"></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1" x-text="item.live_detail || item.detail"></p>
                            <div x-show="(item.event_lines || []).length > 0" x-cloak class="mt-2 space-y-1">
                                <template x-for="(line, lineIdx) in item.event_lines" :key="item.key + '-line-' + lineIdx">
                                    <p class="text-[11px] text-gray-500 break-words font-mono" x-text="line"></p>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="runLog.length > 0" x-cloak class="mt-4 border border-gray-200 bg-gray-950 rounded-xl px-5 py-4 max-h-72 overflow-y-auto" x-ref="campaignLogContainer">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-300">Live Activity</h4>
                    <span class="text-[10px] text-gray-500" x-text="runLog.length + ' entries'"></span>
                </div>
                <template x-for="(entry, idx) in runLog" :key="idx">
                    <div class="py-1.5 text-xs font-mono" :class="{
                        'text-green-400': entry.type === 'success' || entry.type === 'done',
                        'text-red-400': entry.type === 'error',
                        'text-yellow-400': entry.type === 'warning',
                        'text-blue-400': entry.type === 'info',
                        'text-gray-400': entry.type === 'step'
                    }">
                        <span class="text-gray-500" x-text="entry.time || entry.captured_at || ''"></span>
                        <span class="ml-2" x-text="entry.message"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         CRON ACTIVITY (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon green">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Cron Activity</h3>
                    <p class="hx-card-subtitle">Hexa Core Cron Manager registration, scheduler entry, last run details, and the full publish cron metadata for this campaign.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag green">Hexa Core</span>
                <span class="hx-tag slate">{{ count($cronJobsData ?? []) }} job{{ count($cronJobsData ?? []) === 1 ? '' : 's' }}</span>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">

            {{-- ── System scheduler row ── --}}
            <div class="py-4 border-b border-gray-100">
                <div class="flex items-baseline gap-3 mb-2">
                    <h4 class="text-sm font-semibold text-gray-900">System Scheduler</h4>
                    <span class="hx-tag {{ ($schedulerHealth['installed'] ?? false) ? 'green' : 'red' }}">{{ ($schedulerHealth['installed'] ?? false) ? 'Installed' : 'Missing' }}</span>
                    <span class="text-xs text-gray-500 ml-auto">{{ $schedulerHealth['line_count'] ?? 0 }} crontab line(s) scanned</span>
                </div>
                <p class="text-sm text-gray-600">{{ $schedulerHealth['message'] ?? 'Scheduler status unavailable.' }}</p>
                @if(!empty($schedulerHealth['entry']))
                    <pre class="mt-2 whitespace-pre-wrap break-words rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-xs text-gray-700 font-mono">{{ $schedulerHealth['entry'] }}</pre>
                @else
                    <div class="mt-2 rounded-lg border border-dashed border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">No <code>artisan schedule:run</code> entry was detected for this publish app.</div>
                @endif
            </div>

            {{-- ── Campaign timing row ── --}}
            <div class="py-4 border-b border-gray-100">
                <div class="flex items-baseline gap-3 mb-3">
                    <h4 class="text-sm font-semibold text-gray-900">Campaign Timing</h4>
                    <span class="hx-tag slate">{{ ucwords(str_replace('-', ' ', $campaign->status ?? 'draft')) }}</span>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex items-baseline gap-3">
                        <dt class="w-36 text-gray-500 flex-shrink-0">Last run</dt>
                        <dd class="text-gray-900 font-medium">
                            {{ $campaign->last_run_at ? $campaign->last_run_at->setTimezone($displayTimezone)->format('M j, Y g:i A T') : 'Never' }}
                            @if($campaign->last_run_at)
                                <span class="text-gray-400 ml-2">· {{ $campaign->last_run_at->setTimezone($displayTimezone)->diffForHumans() }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="w-36 text-gray-500 flex-shrink-0">Next run</dt>
                        <dd class="text-gray-900 font-medium">
                            {{ $campaign->next_run_at ? $campaign->next_run_at->setTimezone($displayTimezone)->format('M j, Y g:i A T') : '—' }}
                            @if($campaign->next_run_at)
                                <span class="text-gray-400 ml-2">· {{ $campaign->next_run_at->setTimezone($displayTimezone)->diffForHumans() }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="w-36 text-gray-500 flex-shrink-0">Cadence</dt>
                        <dd class="text-gray-900 font-medium">
                            {{ ucwords($campaign->interval_unit ?? 'daily') }}
                            <span class="text-gray-400">·</span>
                            {{ $campaign->articles_per_interval ?? 1 }} post{{ ($campaign->articles_per_interval ?? 1) === 1 ? '' : 's' }}/run
                            <span class="text-gray-400">·</span>
                            runs at {{ $campaign->run_at_time ?: '—' }}
                            <span class="text-gray-400">·</span>
                            {{ $campaign->drip_interval_minutes ?? 60 }} min drip
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- ── Hexa Core registry row ── --}}
            <div class="py-4 border-b border-gray-100">
                <div class="flex items-baseline gap-3 mb-3">
                    <h4 class="text-sm font-semibold text-gray-900">Hexa Core Registry</h4>
                    <span class="hx-tag blue">{{ count($cronJobsData ?? []) }} job{{ count($cronJobsData ?? []) === 1 ? '' : 's' }}</span>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex items-baseline gap-3">
                        <dt class="w-36 text-gray-500 flex-shrink-0">Registered jobs</dt>
                        <dd class="text-gray-900 font-medium">{{ count($cronJobsData ?? []) }}</dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="w-36 text-gray-500 flex-shrink-0">Primary job</dt>
                        <dd class="text-gray-900 font-mono text-[13px]">{{ $cronJobsData[0]['name'] ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="flex flex-wrap gap-2 mt-3">
                    @if(!empty($primaryCronRunUrl))
                        <form method="POST" action="{{ $primaryCronRunUrl }}">
                            @csrf
                            <button type="submit" class="hx-btn hx-btn-primary">Run Publish Cron</button>
                        </form>
                    @endif
                    @if(!empty($cronManagerUrl))
                        <a href="{{ $cronManagerUrl }}" target="_blank" rel="noopener" class="hx-btn hx-btn-secondary">Open Cron Manager</a>
                    @endif
                </div>
            </div>

            {{-- ── Per-job detail rows (stacked, no columns) ── --}}
            @forelse(($cronJobsData ?? []) as $job)
                <div class="py-5 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                    <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-semibold text-gray-900 font-mono">{{ $job['name'] }}</h4>
                                <span class="hx-tag {{ $job['enabled'] ? 'green' : 'red' }}">{{ $job['enabled'] ? 'Enabled' : 'Disabled' }}</span>
                                <span class="hx-tag {{ ($job['last_status'] ?? 'never') === 'success' ? 'green' : (($job['last_status'] ?? 'never') === 'never' ? 'gray' : 'amber') }}">{{ ucfirst($job['last_status'] ?? 'never') }}</span>
                            </div>
                            @if(!empty($job['description']))
                                <p class="mt-1.5 text-sm text-gray-600">{{ $job['description'] }}</p>
                            @endif
                            <div class="mt-1 text-xs text-gray-500 font-mono break-all">{{ $job['command'] }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2 flex-shrink-0">
                            <form method="POST" action="{{ $job['run_url'] }}">
                                @csrf
                                <button type="submit" class="hx-btn hx-btn-secondary">Run Now</button>
                            </form>
                            <a href="{{ $job['output_url'] }}" target="_blank" rel="noopener" class="hx-btn hx-btn-secondary">Full Output</a>
                        </div>
                    </div>

                    <dl class="space-y-1.5 text-sm">
                        <div class="flex items-baseline gap-3">
                            <dt class="w-36 text-gray-500 flex-shrink-0">Package</dt>
                            <dd class="text-gray-900 font-medium">{{ $job['package_name'] ?: '—' }}</dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="w-36 text-gray-500 flex-shrink-0">Schedule</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $job['schedule_label'] ?: '—' }}
                                <span class="text-gray-400 mx-1">·</span>
                                <code class="text-[13px] font-mono text-gray-700">{{ $job['schedule'] ?: '—' }}</code>
                            </dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="w-36 text-gray-500 flex-shrink-0">Run count</dt>
                            <dd class="text-gray-900 font-mono text-[13px]">{{ number_format($job['run_count']) }}</dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="w-36 text-gray-500 flex-shrink-0">Last run</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $job['last_run']['display'] ?? 'Never' }}
                                @if(!empty($job['last_run']['relative']))
                                    <span class="text-gray-400 ml-2">· {{ $job['last_run']['relative'] }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="w-36 text-gray-500 flex-shrink-0">Next run</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $job['next_run']['display'] ?? 'Pending' }}
                                @if(!empty($job['next_run']['relative']))
                                    <span class="text-gray-400 ml-2">· {{ $job['next_run']['relative'] }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="w-36 text-gray-500 flex-shrink-0">Created</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $job['created_at']['display'] ?? '—' }}
                                @if(!empty($job['created_at']['relative']))
                                    <span class="text-gray-400 ml-2">· {{ $job['created_at']['relative'] }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="w-36 text-gray-500 flex-shrink-0">Updated</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $job['updated_at']['display'] ?? '—' }}
                                @if(!empty($job['updated_at']['relative']))
                                    <span class="text-gray-400 ml-2">· {{ $job['updated_at']['relative'] }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Last output</div>
                        @if(!empty($job['last_output']))
                            <pre class="whitespace-pre-wrap break-words rounded-lg bg-gray-900 px-4 py-3 text-xs text-gray-100 font-mono">{{ $job['last_output'] }}</pre>
                        @elseif(!empty($job['last_output_preview']))
                            <pre class="whitespace-pre-wrap break-words rounded-lg bg-gray-900 px-4 py-3 text-xs text-gray-100 font-mono">{{ $job['last_output_preview'] }}</pre>
                        @else
                            <div class="rounded-lg border border-dashed border-gray-200 px-4 py-3 text-xs text-gray-500">No cron output has been recorded yet.</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-4">
                    <div class="rounded-xl border border-dashed border-red-200 bg-red-50 px-4 py-6 text-center text-sm text-red-700">No Hexa Core cron jobs are registered for <code>app-publish</code>.</div>
                </div>
            @endforelse

        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         CAMPAIGN DETAILS (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon blue">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9h6m-6 4h6"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Campaign Details</h3>
                    <p class="hx-card-subtitle">Internal name, description, and AI instructions for this campaign.</p>
                </div>
            </div>
            <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-grid-2">
                <div class="hx-field">
                    <label class="hx-label">Internal Description</label>
                    <textarea rows="3" x-model="form.description" class="hx-textarea" placeholder="Internal summary of the campaign's editorial goal."></textarea>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Campaign Instructions</label>
                    <textarea rows="3" x-model="form.ai_instructions" class="hx-textarea" placeholder="Editorial guardrails passed to the AI for each run."></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         SCHEDULE & CADENCE (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon purple">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Campaign Schedule &amp; Cadence</h3>
                    <p class="hx-card-subtitle">Owning user, campaign cadence, posts per run, run time, and drip interval. Cron reporting lives in the dedicated card above.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag slate" x-text="formatLabel(form.interval_unit || 'daily') + ' · ' + (form.articles_per_interval || 1) + '/run'"></span>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-field">
                <label class="hx-label">User</label>
                <div class="max-w-md"
                    @hexa-search-selected.window="if ($event.detail.component_id === 'campaign-user') form.user_id = $event.detail.item.id"
                    @hexa-search-cleared.window="if ($event.detail.component_id === 'campaign-user') form.user_id = null">
                    @php $currentUser = $campaign->user ? ['id' => $campaign->user->id, 'name' => $campaign->user->name, 'email' => $campaign->user->email] : null; @endphp
                    <x-hexa-smart-search url="{{ route('api.search.users') }}" name="user_id" placeholder="Search users..." display-field="name" subtitle-field="email" value-field="id" id="campaign-user" show-id :selected="$currentUser" />
                </div>
                <p class="hx-field-hint">Timezone: {{ $campaign->timezone ?? 'America/New_York' }} — inherited from the selected user.</p>
            </div>
            <div class="hx-grid-2">
                <div class="hx-field">
                    <label class="hx-label">Frequency</label>
                    <select x-model="form.interval_unit" class="hx-select">
                        <option value="hourly">Hourly</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Posts per run</label>
                    <input type="number" min="1" max="50" x-model.number="form.articles_per_interval" class="hx-input">
                </div>
                <div class="hx-field">
                    <label class="hx-label">Run at time</label>
                    <input type="time" x-model="form.run_at_time" class="hx-input">
                </div>
                <div class="hx-field">
                    <label class="hx-label">Drip interval (minutes between posts in a run)</label>
                    <input type="number" min="1" max="1440" x-model.number="form.drip_interval_minutes" class="hx-input">
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         CONTENT SOURCE — Campaign Preset (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon indigo">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Content Source — Campaign Preset</h3>
                    <p class="hx-card-subtitle">Search queries, discovery mode, and run-time instructions.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <a x-show="campaignPresetEditUrl" x-cloak :href="campaignPresetEditUrl" target="_blank" rel="noopener" @click.stop class="hx-link text-xs inline-flex items-center gap-1">Edit preset
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4h6m0 0v6m0-6L10 14M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                </a>
                <a href="{{ route('campaigns.presets.index') }}" target="_blank" rel="noopener" @click.stop class="text-xs text-gray-500 hover:text-gray-800">Manage</a>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-field">
                <label class="hx-label">Campaign preset</label>
                <select x-model="form.campaign_preset_id" @change="onCampaignPresetChange()" class="hx-select max-w-md">
                    <option value="">— No preset —</option>
                    @foreach($campaignPresets as $cp)
                        <option value="{{ $cp->id }}">{{ $cp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4 rounded-2xl border border-indigo-200 bg-indigo-50/80 px-4 py-3 text-sm text-indigo-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">Changes below apply only to this campaign.</p>
                        <p class="mt-1 text-indigo-800/90">They are saved as campaign-specific overrides and survive refresh. To update the preset itself for every campaign, open the preset in a new tab.</p>
                    </div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700" x-text="campaignPresetOverrideCount + ' override' + (campaignPresetOverrideCount === 1 ? '' : 's')"></div>
                </div>
                <a x-show="campaignPresetEditUrl" x-cloak :href="campaignPresetEditUrl" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-indigo-700 hover:text-indigo-900">
                    Edit preset in new tab
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4h6m0 0v6m0-6L10 14M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                </a>
            </div>
            @include('app-publish::partials.preset-fields', [
                'prefix' => 'campaignPreset',
                'title' => 'Campaign-only preset overrides',
                'description' => 'Only the discovery-specific preset settings that make sense at the campaign layer are editable here.',
                'excludeFields' => ['user_id', 'name', 'search_queries', 'campaign_instructions', 'posts_per_run', 'frequency', 'run_at_time', 'drip_minutes', 'is_active', 'is_default']
            ])
            <div class="pt-4 mt-4 border-t border-gray-100">
                <div class="hx-field">
                    <label class="hx-label">Search queries <span class="text-gray-400 normal-case font-normal">— one per line, overrides preset</span></label>
                    <textarea rows="5" x-model="termsText" class="hx-textarea" placeholder="Leave blank to use preset queries"></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         ARTICLE GENERATION — Article Preset (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon pink">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Article Generation — Article Preset</h3>
                    <p class="hx-card-subtitle">Tone, word count, models, and how each article is written.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <a x-show="articlePresetEditUrl" x-cloak :href="articlePresetEditUrl" target="_blank" rel="noopener" @click.stop class="hx-link text-xs inline-flex items-center gap-1">Edit preset
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4h6m0 0v6m0-6L10 14M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                </a>
                <a href="{{ route('publish.templates.index') }}" target="_blank" rel="noopener" @click.stop class="text-xs text-gray-500 hover:text-gray-800">Manage</a>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-grid-2">
                <div class="hx-field">
                    <label class="hx-label">Article preset</label>
                    <select x-model="form.publish_template_id" @change="onArticlePresetChange()" class="hx-select">
                        <option value="">— No preset —</option>
                        @foreach($aiTemplates as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hx-field">
                    @php
                        $campaignArticleTypeOptions = collect($articleTypes ?? [])->filter()->values();
                        $campaignArticleTypeHint = $campaignArticleTypeOptions->isNotEmpty()
                            ? $campaignArticleTypeOptions
                                ->map(fn ($value) => ucwords(str_replace('-', ' ', (string) $value)))
                                ->implode(', ')
                            : 'Editorial';
                    @endphp
                    <label class="hx-label">Article type <span class="text-gray-400 normal-case font-normal">— campaigns support {{ $campaignArticleTypeHint }}</span></label>
                    <select x-model="form.article_type" class="hx-select">
                        @forelse($campaignArticleTypeOptions as $at)
                            <option value="{{ $at }}">{{ ucwords(str_replace('-', ' ', $at)) }}</option>
                        @empty
                            <option value="editorial">Editorial</option>
                        @endforelse
                    </select>
                </div>
            </div>
            <div class="mb-4 rounded-2xl border border-fuchsia-200 bg-fuchsia-50/80 px-4 py-3 text-sm text-fuchsia-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">Article preset edits here are campaign-only overrides.</p>
                        <p class="mt-1 text-fuchsia-800/90">Use this section to tune how this single campaign writes. Campaigns stay within the supported editorial-style types even if the source preset supports promotional or unsupported types. To change the underlying preset itself, open the preset in a new tab.</p>
                    </div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-fuchsia-700" x-text="articlePresetOverrideCount + ' override' + (articlePresetOverrideCount === 1 ? '' : 's')"></div>
                </div>
                <a x-show="articlePresetEditUrl" x-cloak :href="articlePresetEditUrl" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-fuchsia-700 hover:text-fuchsia-900">
                    Edit preset in new tab
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4h6m0 0v6m0-6L10 14M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                </a>
            </div>
            @include('app-publish::partials.preset-fields', [
                'prefix' => 'template',
                'title' => 'Campaign-only article preset overrides',
                'description' => 'These fields override the selected article preset only for this campaign.',
                'excludeFields' => ['publish_account_id', 'name', 'article_type', 'is_default']
            ])
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         PUBLISHING TARGET
         ─────────────────────────────────────────────── --}}
    <div class="hx-card order-last" x-data="{open: false}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon green">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Publishing Target</h3>
                    <p class="hx-card-subtitle">WordPress site, author, delivery mode, and post status.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag slate" x-text="deliveryModeLabel(form.delivery_mode)"></span>
                <span class="hx-tag gray" x-text="'Status · ' + formatLabel(form.post_status || 'draft')"></span>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-field">
                <label class="hx-label">WordPress site</label>
                <select x-model="form.publish_site_id" @change="doTestSite()" class="hx-select max-w-md">
                    <option value="">— Select site —</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->url }})</option>
                    @endforeach
                </select>
                <div x-show="form.publish_site_id" x-cloak class="mt-2 flex items-center gap-2 text-xs rounded-lg px-3 py-2 max-w-md" :class="siteConn.status === true ? 'bg-green-50 text-green-800' : (siteConn.status === false ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-600')">
                    <span x-show="siteConn.testing" x-cloak>Testing…</span>
                    <span x-show="!siteConn.testing" x-cloak x-text="siteConn.message || 'Click Test to verify site access.'"></span>
                    <button @click="doTestSite()" class="ml-auto hx-link">Test</button>
                </div>
            </div>
            <div class="hx-grid-3">
                <div class="hx-field"
                    @hexa-search-selected.window="if ($event.detail.component_id === 'campaign-author') form.author = $event.detail.item.username || $event.detail.item.user_login || $event.detail.item.slug || $event.detail.item.display_name || $event.detail.item.name || ''"
                    @hexa-search-cleared.window="if ($event.detail.component_id === 'campaign-author') form.author = ''">
                    @php
                        $selectedAuthor = !empty($campaignForm['author']) ? [
                            'id' => $campaignForm['author'],
                            'name' => $campaignForm['author'],
                            'display_name' => $campaignForm['author'],
                            'username' => $campaignForm['author'],
                            'user_login' => $campaignForm['author'],
                            'email' => '',
                        ] : null;
                    @endphp
                    <x-hexa-smart-search
                        url="{{ route('campaigns.authors.search', $campaign->id) }}"
                        name="author"
                        label="Author"
                        placeholder="Type to search WordPress authors..."
                        display-field="display_name"
                        subtitle-field="email"
                        value-field="username"
                        id="campaign-author"
                        :selected="$selectedAuthor"
                        :min-chars="0"
                        :debounce="250"
                    />
                <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" @click="refreshAuthorsFromSource()" :disabled="integrityRunning || !form.publish_site_id" class="hx-link text-xs">Refresh authors from source</button>
                        <span class="text-xs text-gray-400" x-text="authorCacheSummary()"></span>
                    </div>
                    <p class="hx-field-hint mt-1">The stored value is the real WordPress login, not the display name. Leave this blank to use the site's random author pool when one is configured, otherwise Publish falls back to the site's default author. Cached authors stay warm until you explicitly retest or refresh them.</p>
                    <p class="hx-field-hint mt-1" x-show="integrityReport?.site?.default_author || integrityReport?.site?.author_cast_count" x-cloak>
                        <span x-text="'Default: ' + (integrityReport?.site?.default_author || 'none')"></span>
                        <span x-show="integrityReport?.site?.author_cast_count" x-cloak x-text="' · Pool: ' + integrityReport.site.author_cast_count + ' author' + (integrityReport.site.author_cast_count === 1 ? '' : 's')"></span>
                    </p>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Publish destination</label>
                    <select x-model="form.delivery_mode" class="hx-select">
                        <option value="draft-local">Publish only (no WordPress)</option>
                        <option value="draft-wordpress">WordPress delivery</option>
                    </select>
                    <p class="hx-field-hint mt-1" x-text="deliveryModeHint(form.delivery_mode)"></p>
                </div>
                <div class="hx-field" x-show="form.delivery_mode !== 'draft-local'" x-cloak>
                    <label class="hx-label">Post status</label>
                    <select x-model="form.post_status" class="hx-select">
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="publish">Publish</option>
                    </select>
                    <p class="hx-field-hint mt-1">This is the real WordPress status control. Use it to keep the post as a draft, send it for review, or publish it live.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         CAMPAIGN ARTICLES — 1 per row, consolidated
         ─────────────────────────────────────────────── --}}
    @php
        $transientArticleIds = collect([
            $activeOperationArticle['article_record_id'] ?? null,
            $staleOperationArticle['article_record_id'] ?? null,
        ])->filter()->map(fn ($id) => (int) $id)->all();
        $campaignArticleFlags = (array) ($integrityReport['article_flags'] ?? []);
        $campaignArticlePool = $campaign->articles->reject(function ($article) use ($transientArticleIds) {
            if (in_array((int) $article->id, $transientArticleIds, true)) {
                return true;
            }

            return trim((string) ($article->title ?? '')) === 'Campaign run starting...';
        })->values();
        $exceptionCampaignArticles = $campaignArticlePool->filter(function ($article) use ($campaignArticleFlags) {
            $flags = (array) ($campaignArticleFlags[(string) $article->id] ?? []);
            foreach ($flags as $flag) {
                $severity = strtolower((string) ($flag['severity'] ?? ''));
                $label = strtolower((string) ($flag['label'] ?? ''));
                if ($severity === 'error' && in_array($label, ['type drift', 'site drift'], true)) {
                    return true;
                }
                if ($label === 'stale pipeline') {
                    return true;
                }
            }

            return false;
        })->values();
        $exceptionCampaignArticleIds = $exceptionCampaignArticles->pluck('id')->map(fn ($id) => (int) $id)->all();
        $visibleCampaignArticles = $campaignArticlePool->reject(fn ($article) => in_array((int) $article->id, $exceptionCampaignArticleIds, true))->values();

        $hiddenCampaignArticleCount = max(0, $campaign->articles->count() - $campaignArticlePool->count());
    @endphp
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon slate">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Campaign Articles</h3>
                    <p class="hx-card-subtitle">Most recent completed or reopenable articles from this campaign. Active scaffold rows stay in the live run panel until they become real article records.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag green">{{ $visibleCampaignArticles->count() }} clean visible</span>
                @if($exceptionCampaignArticles->count() > 0)
                    <span class="hx-tag red">{{ $exceptionCampaignArticles->count() }} integrity exception{{ $exceptionCampaignArticles->count() === 1 ? '' : 's' }}</span>
                @endif
                @if($hiddenCampaignArticleCount > 0)
                    <span class="hx-tag amber">{{ $hiddenCampaignArticleCount }} transient hidden</span>
                @endif
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            @forelse($visibleCampaignArticles as $article)
                @include('app-publish::publishing.articles.partials.article-card', [
                    'article' => $article,
                    'campaign' => $campaign,
                    'integrityFlags' => $campaignArticleFlags[(string) $article->id] ?? [],
                ])
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-400">This campaign has not produced any completed or reopenable articles yet.</div>
            @endforelse
        </div>
    </div>

    @if($exceptionCampaignArticles->count() > 0)
        <div class="hx-card" x-data="{open: true}">
            <div class="hx-card-header hx-clickable" @click="open = !open">
                <div class="hx-card-title-block">
                    <div class="hx-card-icon red">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="hx-card-title">Integrity Exceptions</h3>
                        <p class="hx-card-subtitle">These article records no longer match this campaign cleanly. They are separated here so they do not masquerade as normal editorial campaign output.</p>
                    </div>
                </div>
                <div class="hx-card-header-right">
                    <span class="hx-tag red">{{ $exceptionCampaignArticles->count() }} exception{{ $exceptionCampaignArticles->count() === 1 ? '' : 's' }}</span>
                    <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
            <div x-show="open" x-cloak class="hx-card-body">
                @foreach($exceptionCampaignArticles as $article)
                    @include('app-publish::publishing.articles.partials.article-card', [
                        'article' => $article,
                        'campaign' => $campaign,
                        'integrityFlags' => $campaignArticleFlags[(string) $article->id] ?? [],
                    ])
                @endforeach
            </div>
        </div>
    @endif

    {{-- ───────────────────────────────────────────────
         ACTIVITY / RUNS LOG (expandable, collapsed by default)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: false}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon red">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Activity Log</h3>
                    <p class="hx-card-subtitle">Most recent campaign activity and run events.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag slate">{{ count($runLogs ?? []) }} entr{{ count($runLogs ?? []) === 1 ? 'y' : 'ies' }}</span>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="space-y-2 max-h-[28rem] overflow-auto">
                @forelse($runLogs as $log)
                    <div class="rounded-lg border border-gray-200 px-4 py-2">
                        <div class="flex items-center justify-between gap-3 text-xs text-gray-400">
                            <span>{{ \Illuminate\Support\Carbon::parse($log->created_at)->timezone($campaign->timezone ?? 'America/New_York')->format('M j, g:i:s A') }}</span>
                            <span>{{ $log->action }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700">{{ $log->message }}</p>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-400 text-center">No activity logged yet.</div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@include('app-publish::partials.preset-fields-mixin')
@include('app-publish::partials.site-connection-mixin')
<script>
function campaignDashboard() {
    const initialForm          = @json($campaignForm);
    const campaignPresetItems  = @json($campaignPresetItems);
    const articlePresetItems   = @json($aiTemplateItems);
    const campaignPresetSchema = @json($campaignPresetSchema ?? []);
    const articlePresetSchema  = @json($articlePresetSchema ?? []);
    const campaignPresetValues = @json($campaignPresetValues ?? []);
    const articlePresetValues  = @json($articlePresetValues ?? []);
    const campaignPresetOverrides = @json($campaignPresetOverrides ?? []);
    const articlePresetOverrides  = @json($articlePresetOverrides ?? []);
    const initialOperation     = @json($activeOperation ?? null);
    const initialOperationArticle = @json($activeOperationArticle ?? null);
    const staleOperation       = @json($staleOperation ?? null);
    const staleOperationArticle = @json($staleOperationArticle ?? null);
    const checklistDefinitions = @json($checklistDefinitions ?? []);
    const initialIntegrityReport = @json($integrityReport ?? []);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

    return {
        ...presetFieldsMixin('campaignPreset'),
        ...presetFieldsMixin('template'),
        ...presetFieldsMethods,
        ...siteConnectionMixin(),

        editId: {{ (int) $campaign->id }},
        campaignStatus: @json($campaign->status ?? 'draft'),
        form: {
            ...initialForm,
            post_status: initialForm.post_status || @json($campaign->post_status ?? 'draft'),
        },
        termsText: Array.isArray(initialForm.keywords) ? initialForm.keywords.join('\n') : '',
        saving: false,
        saveResult: '',
        saveSuccess: true,
        stoppingRun: false,
        running: false,
        runningMode: '',
        runResult: '',
        runSuccess: false,
        runState: '',
        runLog: [],
        seenEventKeys: [],
        lastEventSequence: 0,
        operationId: null,
        operationStatus: '',
        operationTransport: '',
        operationTraceId: '',
        operationCurrent: '',
        operationLabel: '',
        operationShowUrl: '',
        operationStreamUrl: '',
        operationStreamController: null,
        operationPollTimer: null,
        operationStartedAt: '',
        operationCompletedAt: '',
        elapsedTimer: null,
        operationOutput: {
            article_url: initialOperationArticle?.show_url || '',
            wp_post_url: initialOperationArticle?.wp_post_url || '',
            article_record_id: initialOperationArticle?.article_record_id || '',
        },
        staleOperationInfo: staleOperation,
        staleOperationArticle: staleOperationArticle,
        campaignChecklistByMode: checklistDefinitions,
        campaignChecklist: [],
        integrityReport: initialIntegrityReport || { summary: { status: 'pass', errors: 0, warnings: 0, blocking_errors: 0 }, issues: [], site: {} },
        integrityRunning: false,
        _saveTimer: null,
        _autosaveReady: false,

        get campaignPresetEditUrl() {
            return this.form.campaign_preset_id
                ? @json(route('campaigns.presets.index')) + '?id=' + this.form.campaign_preset_id
                : null;
        },
        get articlePresetEditUrl() {
            return this.form.publish_template_id
                ? '/publish/article-presets/' + this.form.publish_template_id + '/edit'
                : null;
        },
        get campaignPresetOverrideCount() {
            return Object.keys(this.campaignPreset_overrides || {}).length;
        },
        get articlePresetOverrideCount() {
            return Object.keys(this.template_overrides || {}).length;
        },

        init() {
            if (this.form.delivery_mode === 'auto-publish') {
                this.form.delivery_mode = 'draft-wordpress';
                this.form.post_status = this.form.post_status || 'publish';
            }
            this._loadCampaignPreset();
            this._loadArticlePreset();
            this.$watch('termsText', v => { this.form.keywords = (v || '').split('\n').map(s => s.trim()).filter(Boolean); });
            const queueAutoSave = () => {
                if (!this._autosaveReady) return;
                clearTimeout(this._saveTimer);
                this._saveTimer = setTimeout(() => this.autoSave(), 600);
            };
            this.$watch('form', () => {
                queueAutoSave();
            }, { deep: true });
            this.$watch('campaignPreset_overrides', () => { queueAutoSave(); }, { deep: true });
            this.$watch('template_overrides', () => { queueAutoSave(); }, { deep: true });
            if (this.form.publish_site_id) {
                this.restoreSiteConnection(this.form.publish_site_id, 'campaignSiteConnection_' + this.editId);
            }
            if (initialOperation) {
                const mode = initialOperation?.request_summary?.mode
                    || initialOperation?.request_summary?.delivery_mode
                    || this.form.delivery_mode
                    || 'draft-wordpress';
                this.runningMode = mode;
                this.operationLabel = this.operationModeLabel(mode);
                this.campaignChecklist = this.cloneChecklist(mode);
                this.applyOperation(initialOperation);

                if (['completed', 'failed'].includes(this.operationStatus)) {
                    const resultPayload = initialOperation?.result_payload || {};
                    this.finishChecklist(this.operationStatus === 'completed', resultPayload.message || (this.operationStatus === 'completed' ? 'Last instant run completed.' : 'Last instant run failed.'), resultPayload);
                } else {
                    this.running = true;
                    this.runSuccess = true;
                    this.runState = 'info';
                    this.runResult = 'Resumed active operation stream.';
                    this.$nextTick(() => {
                        this.$refs.liveChecklistCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                    this.pollOperation(400);
                    this.startStreaming();
                }
            } else if (staleOperation) {
                this.runSuccess = false;
                this.runState = 'error';
                this.runResult = 'A stale campaign run was found and not auto-resumed. Force stop it before spawning another article.';
                this.operationLabel = this.operationModeLabel(
                    staleOperation?.request_summary?.mode
                    || staleOperation?.request_summary?.delivery_mode
                    || this.form.delivery_mode
                    || 'draft-wordpress'
                );
            }
            this.$nextTick(() => {
                this._autosaveReady = true;
            });
        },

        formatLabel(value) {
            return (value || '').replace(/[-_]/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
        },

        deliveryModeLabel(value) {
            if (value === 'draft-wordpress') return 'WordPress delivery';
            if (value === 'draft-local') return 'Local only';
            return this.formatLabel(value || 'draft-wordpress');
        },

        deliveryModeHint(value) {
            if (value === 'draft-wordpress') {
                return 'Creates or updates a WordPress post. The post status right beside it controls whether that post stays draft, goes pending review, or publishes live.';
            }
            return 'Keeps the article inside Publish only. Nothing is created or updated on WordPress.';
        },

        formatDateTime(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        },

        integritySummaryLabel() {
            const summary = this.integrityReport?.summary || {};
            if ((summary.errors || 0) > 0) {
                return summary.errors + ' error' + (summary.errors === 1 ? '' : 's');
            }
            if ((summary.warnings || 0) > 0) {
                return summary.warnings + ' warning' + (summary.warnings === 1 ? '' : 's');
            }
            return 'Integrity clean';
        },

        siteConnectionSummary() {
            const site = this.integrityReport?.site || {};
            if (!this.form.publish_site_id) return 'No site selected.';
            if (site.last_connected_relative) {
                return 'Connection since ' + site.last_connected_relative + '.';
            }
            return 'Connection has not been verified yet.';
        },

        authorCacheSummary() {
            const site = this.integrityReport?.site || {};
            const parts = [];
            if (typeof site.author_count === 'number' && site.author_count > 0) {
                parts.push(site.author_count + ' author' + (site.author_count === 1 ? '' : 's') + ' cached');
            }
            if (site.author_cache_hit === true) {
                parts.push('served from cache');
            } else if (site.author_cache_hit === false) {
                parts.push('fresh from source');
            }
            if (site.authors_cached_at) {
                parts.push('cached ' + this.formatDateTime(site.authors_cached_at));
            }
            return parts.length ? parts.join(' · ') : 'Author cache status not available.';
        },

        hasBlockingIntegrityIssues() {
            return Number(this.integrityReport?.summary?.blocking_errors || 0) > 0;
        },

        operationModeLabel(mode) {
            if (mode === 'auto-publish') return 'Instant Publish';
            if (mode === 'draft-wordpress') return 'Instant Draft';
            if (mode === 'draft-local') return 'Local Draft Run';
            return this.formatLabel(mode || 'Campaign Run');
        },

        checklistProgressText() {
            const enabled = this.campaignChecklist.filter(item => item.enabled !== false);
            const done = enabled.filter(item => item.status === 'done').length;
            return enabled.length ? (done + '/' + enabled.length + ' complete') : 'No checklist items';
        },

        cloneChecklist(mode) {
            const definitions = this.campaignChecklistByMode?.[mode] || [];
            return JSON.parse(JSON.stringify(definitions)).map((item) => ({
                ...item,
                live_detail: item.live_detail || '',
                event_lines: [],
            }));
        },

        operationElapsedText() {
            const startedAt = this.operationStartedAt ? new Date(this.operationStartedAt) : null;
            if (!startedAt || Number.isNaN(startedAt.getTime())) {
                return '00:00';
            }

            const endedAt = this.operationCompletedAt ? new Date(this.operationCompletedAt) : new Date();
            const seconds = Math.max(0, Math.floor((endedAt.getTime() - startedAt.getTime()) / 1000));
            const minutesPart = String(Math.floor(seconds / 60)).padStart(2, '0');
            const secondsPart = String(seconds % 60).padStart(2, '0');

            return minutesPart + ':' + secondsPart;
        },

        syncElapsedTimer() {
            if (this.elapsedTimer) {
                clearInterval(this.elapsedTimer);
                this.elapsedTimer = null;
            }

            if (!this.operationStartedAt || this.operationCompletedAt) {
                return;
            }

            this.elapsedTimer = setInterval(() => {
                if (this.operationCompletedAt) {
                    clearInterval(this.elapsedTimer);
                    this.elapsedTimer = null;
                    return;
                }

                this.operationStartedAt = this.operationStartedAt;
            }, 1000);
        },

        resetOperationState() {
            this.running = false;
            this.runningMode = '';
            this.seenEventKeys = [];
            this.lastEventSequence = 0;
            this.operationId = null;
            this.operationStatus = '';
            this.operationTransport = '';
            this.operationTraceId = '';
            this.operationCurrent = '';
            this.operationLabel = '';
            this.operationShowUrl = '';
            this.operationStreamUrl = '';
            this.operationStartedAt = '';
            this.operationCompletedAt = '';
            this.operationOutput = { article_url: '', wp_post_url: '', article_record_id: '' };
            if (this.operationStreamController) {
                try { this.operationStreamController.abort(); } catch (e) {}
            }
            this.operationStreamController = null;
            if (this.operationPollTimer) {
                clearTimeout(this.operationPollTimer);
            }
            this.operationPollTimer = null;
            if (this.elapsedTimer) {
                clearInterval(this.elapsedTimer);
            }
            this.elapsedTimer = null;
        },

        appendChecklistLine(item, line) {
            const normalized = (line || '').trim();
            if (!normalized) return;

            item.event_lines = Array.isArray(item.event_lines) ? item.event_lines : [];
            if (item.event_lines[item.event_lines.length - 1] === normalized) {
                return;
            }

            item.event_lines.push(normalized);
            if (item.event_lines.length > 12) {
                item.event_lines = item.event_lines.slice(-12);
            }
        },

        recordChecklistEvent(item, entry) {
            this.appendChecklistLine(item, (entry.message || '') + (entry.details ? ' — ' + entry.details : ''));

            [
                ['url', 'URL'],
                ['source', 'Source'],
                ['search_term', 'Search'],
                ['author', 'Author'],
                ['hostname', 'Host'],
                ['connection_label', 'Connection'],
                ['connection_mode', 'Mode'],
                ['source_domain', 'Domain'],
                ['source_type', 'Type'],
                ['alt_text', 'Alt'],
                ['caption', 'Caption'],
                ['wp_url', 'WP URL'],
                ['media_id', 'Media ID'],
            ].forEach(([key, label]) => {
                const value = entry?.[key];
                if (value !== undefined && value !== null && String(value).trim() !== '') {
                    this.appendChecklistLine(item, label + ': ' + value);
                }
            });
        },

        pushRunLog(entry) {
            const sequence = Number(entry.id || entry.sequence_no || 0);
            const eventKey = entry.client_event_id || [sequence, entry.stage || '', entry.substage || '', entry.message || ''].join('|');
            if (this.seenEventKeys.includes(eventKey)) {
                return;
            }
            this.seenEventKeys.push(eventKey);
            if (this.seenEventKeys.length > 1200) {
                this.seenEventKeys = this.seenEventKeys.slice(-800);
            }
            if (sequence > this.lastEventSequence) {
                this.lastEventSequence = sequence;
            }
            this.runLog.push({
                time: entry.time || entry.captured_at || new Date().toLocaleTimeString(),
                type: entry.type || 'info',
                message: entry.message || '',
                stage: entry.stage || '',
                substage: entry.substage || '',
                details: entry.details || '',
            });
            this.$nextTick(() => {
                const container = this.$refs.campaignLogContainer;
                if (container) container.scrollTop = container.scrollHeight;
            });
        },

        findChecklistIndex(stage) {
            return this.campaignChecklist.findIndex(item => Array.isArray(item.stages) && item.stages.includes(stage));
        },

        updateChecklistFromEvent(entry) {
            const stage = entry.stage || '';
            if (!stage) return;
            const idx = this.findChecklistIndex(stage);
            if (idx === -1) return;

            for (let i = 0; i < idx; i++) {
                const item = this.campaignChecklist[i];
                if (item.enabled !== false && ['pending', 'running'].includes(item.status)) {
                    item.status = 'done';
                }
            }

            const item = this.campaignChecklist[idx];
            if (!item || item.enabled === false) return;

            item.live_detail = entry.details
                ? (entry.message + ' — ' + entry.details)
                : entry.message;
            this.recordChecklistEvent(item, entry);

            if (entry.type === 'error') {
                item.status = 'failed';
                return;
            }

            const completeSubstages = ['resolved', 'created', 'manual_links', 'complete', 'local_draft'];
            if (entry.type === 'done' || completeSubstages.includes(entry.substage || '')) {
                item.status = 'done';
                return;
            }

            item.status = 'running';
        },

        applyOperation(operation) {
            if (!operation) return;
            this.operationId = operation.id;
            this.operationStatus = operation.status || '';
            this.operationTransport = operation.transport || '';
            this.operationTraceId = operation.trace_id || '';
            this.operationShowUrl = operation.show_url || this.operationShowUrl || '';
            this.operationStreamUrl = operation.stream_url || this.operationStreamUrl || '';
            this.operationStartedAt = operation.started_at || this.operationStartedAt || new Date().toISOString();
            this.operationCompletedAt = operation.completed_at || '';
            this.operationCurrent = operation.last_stage
                ? ('Current: ' + operation.last_stage.replace(/_/g, ' ') + (operation.last_message ? ' — ' + operation.last_message : ''))
                : (operation.last_message || '');
            const resultPayload = operation.result_payload || {};
            if (resultPayload.article_url) this.operationOutput.article_url = resultPayload.article_url;
            if (resultPayload.wp_post_url) this.operationOutput.wp_post_url = resultPayload.wp_post_url;
            if (resultPayload.article_id) this.operationOutput.article_record_id = resultPayload.article_id;
            this.syncElapsedTimer();
        },

        finishChecklist(success, message, resultPayload = null) {
            this.running = false;
            if (success) {
                this.campaignChecklist.forEach(item => {
                    if (item.enabled !== false && ['pending', 'running'].includes(item.status)) {
                        item.status = 'done';
                    }
                });
            }
            this.runSuccess = success;
            this.runState = success ? 'success' : 'error';
            this.operationCompletedAt = this.operationCompletedAt || new Date().toISOString();
            this.runResult = message || (success ? 'Campaign run complete.' : 'Campaign run failed.');
            if (resultPayload?.article_url) {
                this.operationOutput.article_url = resultPayload.article_url;
            }
            if (resultPayload?.wp_post_url) {
                this.operationOutput.wp_post_url = resultPayload.wp_post_url;
            }
            if (this.operationPollTimer) {
                clearTimeout(this.operationPollTimer);
                this.operationPollTimer = null;
            }
            if (this.elapsedTimer) {
                clearInterval(this.elapsedTimer);
                this.elapsedTimer = null;
            }
        },

        _loadCampaignPreset() {
            const p = campaignPresetItems.find(x => String(x.id) === String(this.form.campaign_preset_id));
            const values = p ? p.form_values : campaignPresetValues;
            const overrides = Object.keys(this.campaignPreset_overrides || {}).length
                ? this.campaignPreset_overrides
                : campaignPresetOverrides;
            this.loadPresetFields('campaignPreset', values, campaignPresetSchema, overrides);
        },
        _loadArticlePreset() {
            const p = articlePresetItems.find(x => String(x.id) === String(this.form.publish_template_id));
            const values = p ? p.form_values : articlePresetValues;
            const overrides = Object.keys(this.template_overrides || {}).length
                ? this.template_overrides
                : articlePresetOverrides;
            this.loadPresetFields('template', values, articlePresetSchema, overrides);
        },

        onCampaignPresetChange() {
            this._loadCampaignPreset();
            const p = campaignPresetItems.find(x => String(x.id) === String(this.form.campaign_preset_id));
            if (!p) return;
            const v = p.form_values || {};
            if (!this.termsText.trim() && Array.isArray(v.search_queries) && v.search_queries.length) {
                this.termsText = v.search_queries.join('\n');
            }
            if (!this.form.ai_instructions && v.campaign_instructions) this.form.ai_instructions = v.campaign_instructions;
            if (v.posts_per_run && (this.form.articles_per_interval || 1) <= 1) this.form.articles_per_interval = v.posts_per_run;
            if (v.frequency && this.form.interval_unit === 'daily') this.form.interval_unit = v.frequency;
            if (v.run_at_time && this.form.run_at_time === '09:00') this.form.run_at_time = v.run_at_time;
            if (v.drip_minutes && (this.form.drip_interval_minutes || 60) === 60) this.form.drip_interval_minutes = v.drip_minutes;
        },
        onArticlePresetChange() { this._loadArticlePreset(); },

        async autoSave() {
            if (!this.editId) return;
            this.saving = true;
            try {
                const payload = {
                    ...this.form,
                    campaign_preset_overrides: { ...(this.campaignPreset_overrides || {}) },
                    article_preset_overrides: { ...(this.template_overrides || {}) },
                };
                const r = await fetch('/campaigns/' + this.editId, { method: 'PUT', headers, body: JSON.stringify(payload) });
                const d = await r.json();
                this.saveSuccess = !!d.success;
                this.saveResult = d.success ? 'Saved ' + new Date().toLocaleTimeString() : (d.message || 'Save failed');
            } catch (e) {
                this.saveSuccess = false;
                this.saveResult = 'Network error';
            }
            this.saving = false;
        },

        async doTestSite() {
            if (!this.form.publish_site_id) return;
            await this.testSiteConnection(this.form.publish_site_id, csrf, {
                cacheKey: 'campaignSiteConnection_' + this.editId,
                onSuccess: (d) => {
                    if (d.default_author && !this.form.author && !(Array.isArray(d.author_cast) && d.author_cast.length)) {
                        this.form.author = d.default_author;
                    }
                },
            });
            await this.runIntegrityReport(true);
        },

        async refreshAuthorsFromSource() {
            if (!this.form.publish_site_id) return;
            this.integrityRunning = true;
            try {
                await this.loadSiteAuthors(this.form.publish_site_id, {
                    cacheKey: 'campaignSiteConnection_' + this.editId,
                    force: true,
                });
                await this.runIntegrityReport(true);
                this.runSuccess = true;
                this.runState = 'info';
                this.runResult = 'WordPress authors refreshed from source.';
            } catch (e) {
                this.runSuccess = false;
                this.runState = 'error';
                this.runResult = 'Author refresh failed: ' + e.message;
            } finally {
                this.integrityRunning = false;
            }
        },

        async runIntegrityReport(forceAuthors = false) {
            this.integrityRunning = true;
            try {
                const query = forceAuthors ? '?force_authors=1' : '';
                const response = await fetch('/campaigns/' + this.editId + '/integrity-report' + query, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Integrity report failed.');
                }
                this.integrityReport = data.report || this.integrityReport;
                return this.integrityReport;
            } finally {
                this.integrityRunning = false;
            }
        },

        async startOperation(mode, label) {
            if (this.staleOperationInfo) {
                this.runSuccess = false;
                this.runState = 'error';
                this.runResult = 'Force stop the stale run before spawning another article from this campaign.';
                this.$nextTick(() => {
                    this.$refs.liveChecklistCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                return;
            }
            await this.autoSave();
            await this.runIntegrityReport(false);
            if (this.hasBlockingIntegrityIssues()) {
                this.runSuccess = false;
                this.runState = 'error';
                this.runResult = 'Fix the blocking integrity issues before running this campaign.';
                this.$nextTick(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
                return;
            }
            if (!confirm('Run campaign now as ' + label.toLowerCase() + '?')) return;
            this.resetOperationState();
            this.running = true;
            this.runningMode = mode;
            this.runResult = '';
            this.runSuccess = false;
            this.runState = '';
            this.runLog = [];
            this.operationLabel = label;
            this.campaignChecklist = this.cloneChecklist(mode);
            this.operationStartedAt = new Date().toISOString();
            this.operationCompletedAt = '';
            this.syncElapsedTimer();
            this.$nextTick(() => {
                this.$refs.liveChecklistCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            try {
                const r = await fetch(@json(route('campaigns.start-operation', $campaign->id)), { method: 'POST', headers, body: JSON.stringify({ mode }) });
                const d = await r.json();
                if (!r.ok || !d.success) {
                    if (d.report) {
                        this.integrityReport = d.report;
                    }
                    throw new Error(d.message || 'Failed to start campaign operation');
                }
                this.campaignChecklist = d.checklist || this.campaignChecklist;
                if (d.article_url) {
                    this.operationOutput.article_url = d.article_url;
                }
                if (d.article_id) {
                    this.operationOutput.article_record_id = d.article_id;
                }
                this.applyOperation(d.operation);
                this.runSuccess = true;
                this.runState = 'info';
                this.runResult = d.message || '';
                this.pollOperation(1000);
                await this.startStreaming();
            } catch(e) {
                this.runSuccess = false;
                this.runState = 'error';
                this.runResult = 'Error: ' + e.message;
                this.running = false;
            }
        },

        async stopOperation() {
            const targetId = this.running ? this.operationId : (this.staleOperationInfo?.id || this.operationId);
            if (!targetId) {
                this.runSuccess = false;
                this.runState = 'error';
                this.runResult = 'No active campaign run found to stop.';
                return;
            }
            if (!confirm('Force stop this campaign run?')) return;

            this.stoppingRun = true;
            try {
                const r = await fetch('/campaigns/' + this.editId + '/operations/' + targetId + '/stop', {
                    method: 'POST',
                    headers,
                });
                const d = await r.json();
                if (!r.ok || !d.success) {
                    throw new Error(d.message || 'Failed to stop campaign run');
                }
                if (d.operation) {
                    this.applyOperation(d.operation);
                }
                this.staleOperationInfo = null;
                this.staleOperationArticle = null;
                const resultPayload = d.operation?.result_payload || {};
                this.finishChecklist(false, d.message || resultPayload.message || 'Run stop requested.', resultPayload);
            } catch (e) {
                this.runSuccess = false;
                this.runState = 'error';
                this.runResult = 'Error: ' + e.message;
            } finally {
                this.running = false;
                this.stoppingRun = false;
            }
        },

        async startStreaming() {
            if (!this.operationId || !this.operationStreamUrl) {
                this.running = false;
                return;
            }

            if (!window.ReadableStream || !this.operationStreamUrl) {
                this.pollOperation(400);
                return;
            }

            this.operationStreamController = new AbortController();
            try {
                const resp = await fetch(this.operationStreamUrl, {
                    headers: { 'Accept': 'application/x-ndjson' },
                    signal: this.operationStreamController.signal,
                });
                if (!resp.ok || !resp.body) {
                    this.pollOperation(400);
                    return;
                }

                const reader = resp.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';
                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (!trimmed) continue;
                        this.handleStreamPayload(JSON.parse(trimmed));
                    }
                }

                if (buffer.trim() !== '') {
                    this.handleStreamPayload(JSON.parse(buffer.trim()));
                }
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.pollOperation(800);
                }
            }
        },

        handleStreamPayload(payload) {
            if (payload.operation) {
                this.applyOperation(payload.operation);
            }

            if (payload.event) {
                this.pushRunLog(payload.event);
                this.updateChecklistFromEvent(payload.event);
            }

            if (payload.kind === 'terminal') {
                const resultPayload = payload.operation?.result_payload || {};
                this.finishChecklist((payload.operation?.status || '') === 'completed', resultPayload.message || this.runResult, resultPayload);
            }

            if (payload.kind === 'timeout') {
                this.pollOperation(400);
            }
        },

        async pollOperation(delay = 1000) {
            if (!this.operationShowUrl) return;
            if (this.operationPollTimer) clearTimeout(this.operationPollTimer);
            this.operationPollTimer = setTimeout(async () => {
                try {
                    const pollUrl = new URL(this.operationShowUrl, window.location.origin);
                    if (this.lastEventSequence > 0) {
                        pollUrl.searchParams.set('after_sequence', String(this.lastEventSequence));
                    }
                    pollUrl.searchParams.set('limit', '200');
                    const resp = await fetch(pollUrl.toString(), { headers: { 'Accept': 'application/json' } });
                    const data = await resp.json();
                    if (!data.success) throw new Error(data.message || 'Polling failed');
                    if (data.operation) this.applyOperation(data.operation);
                    const events = Array.isArray(data.events) ? data.events : [];
                    events.forEach((event) => {
                        this.pushRunLog(event);
                        this.updateChecklistFromEvent(event);
                    });
                    if (['completed', 'failed'].includes(this.operationStatus)) {
                        const resultPayload = data.operation?.result_payload || {};
                        this.finishChecklist(this.operationStatus === 'completed', resultPayload.message || this.runResult, resultPayload);
                        return;
                    }
                    this.pollOperation(1500);
                } catch (e) {
                    this.runState = 'info';
                    this.runResult = 'Waiting for live updates… ' + e.message;
                    this.pollOperation(2500);
                }
            }, delay);
        },
        async activateCampaign() {
            try { await fetch('/campaigns/' + this.editId + '/activate', { method: 'POST', headers }); } catch (e) {}
            window.location.reload();
        },
        async pauseCampaign() {
            try { await fetch('/campaigns/' + this.editId + '/pause', { method: 'POST', headers }); } catch (e) {}
            window.location.reload();
        },
        async duplicateCampaign() {
            try {
                const r = await fetch('/campaigns/' + this.editId + '/duplicate', { method: 'POST', headers });
                const d = await r.json();
                if (d.success && d.campaign?.id) window.location.href = '/campaigns/' + d.campaign.id;
            } catch (e) {}
        },
        async deleteCampaign() {
            if (!confirm('Delete this campaign? This cannot be undone.')) return;
            try {
                await fetch('/campaigns/' + this.editId, { method: 'DELETE', headers });
                window.location.href = @json(route('campaigns.index'));
            } catch (e) {}
        },
    };
}
</script>
@endpush
