@extends('layouts.app')
@section('title', $campaign->name . ' — Campaign')
@section('header', 'Campaign: ' . $campaign->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-4" x-data="campaignShow()">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <p class="text-xs font-mono text-gray-400">{{ $campaign->campaign_id }}</p>
                    @if($campaign->status === 'active')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700">Active</span>
                    @elseif($campaign->status === 'paused')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-yellow-100 text-yellow-700">Paused</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ ucfirst($campaign->status) }}</span>
                    @endif
                    @if($campaign->auto_publish)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-purple-100 text-purple-700">Auto-Publish</span>
                    @endif
                </div>
                <h2 class="text-xl font-semibold text-gray-800">{{ $campaign->name }}</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('campaigns.create', ['id' => $campaign->id]) }}" class="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 px-3 py-1.5 rounded-lg">Edit</a>
                <button @click="startOperation('draft-wordpress', 'Instant draft')" :disabled="running" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-1">
                    <svg x-show="running && runningMode === 'draft-wordpress'" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Instant Draft
                </button>
                <button @click="startOperation('auto-publish', 'Instant publish')" :disabled="running" class="text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 disabled:opacity-50 inline-flex items-center gap-1">
                    <svg x-show="running && runningMode === 'auto-publish'" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Instant Publish
                </button>
            </div>
        </div>
        <div x-show="runResult" x-cloak class="mt-3 p-3 rounded-lg text-sm border" :class="runSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" x-text="runResult"></div>
        @if(!empty($resolvedSettings['error']))
            <div class="mt-3 rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                Resolved settings warning: {{ $resolvedSettings['error'] }}
            </div>
        @endif
    </div>

    {{-- Instant Checklist --}}
    <div x-show="campaignChecklist.length > 0 || operationStatus" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-900">Campaign Checklist</h3>
                    <p class="text-xs text-gray-500 mt-1" x-text="operationLabel || 'Run an instant draft or publish to stream the full checklist.'"></p>
                </div>
                <div class="text-right text-xs text-gray-500 space-y-1">
                    <p x-show="operationStatus" x-text="'Status: ' + operationStatus + (operationTransport ? ' via ' + operationTransport.replace('_', ' ') : '')"></p>
                    <p x-show="operationTraceId" x-text="'Trace: ' + operationTraceId"></p>
                    <p x-show="campaignChecklist.length > 0" x-text="checklistProgressText()"></p>
                </div>
            </div>
            <div x-show="operationCurrent" x-cloak class="mt-3 text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2" x-text="operationCurrent"></div>
        </div>
        <div class="px-6 py-4 space-y-3">
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
                    </div>
                </div>
            </template>
        </div>
        <div x-show="runLog.length > 0" x-cloak class="border-t border-gray-200 bg-gray-950 px-6 py-4 max-h-72 overflow-y-auto" x-ref="campaignLogContainer">
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

    {{-- Details (row layout) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-2">
        <h3 class="font-semibold text-gray-800 mb-3">Details</h3>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">User</span><p class="text-sm text-gray-800">{{ $campaign->user->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Site</span><p class="text-sm text-gray-800">{{ $campaign->site->name ?? '—' }} @if($campaign->site)<span class="text-xs text-gray-400">({{ $campaign->site->url }})</span>@endif</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Campaign Preset</span><p class="text-sm text-gray-800">{{ $campaign->campaignPreset->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">AI Template</span><p class="text-sm text-gray-800">{{ $campaign->template->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">WP Preset</span><p class="text-sm text-gray-800">{{ $campaign->wpPreset->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Article Type</span><p class="text-sm text-gray-800">{{ $resolvedSettings['article_type'] ? ucwords(str_replace('-', ' ', $resolvedSettings['article_type'])) : '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Delivery</span><p class="text-sm text-gray-800">{{ ucwords(str_replace('-', ' ', $resolvedSettings['delivery_mode'] ?? 'draft-local')) }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Discovery</span><p class="text-sm text-gray-800">{{ ucwords(str_replace('-', ' ', $resolvedSettings['source_method'] ?? 'keyword')) }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Search Terms</span><p class="text-sm text-gray-800">{{ count($resolvedSettings['search_terms'] ?? []) }} term(s)</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Author</span><p class="text-sm text-gray-800">{{ $campaign->author ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Post Status</span><p class="text-sm text-gray-800">{{ ucfirst($campaign->post_status ?? 'draft') }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Schedule</span><p class="text-sm text-gray-800">{{ $campaign->articles_per_interval }} post(s) / {{ $campaign->interval_unit }}{{ $campaign->run_at_time ? ' at ' . $campaign->run_at_time : '' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Timezone</span><p class="text-sm text-gray-800">{{ $campaign->timezone ?? 'America/New_York' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Last Run</span><p class="text-sm text-gray-800">{{ $campaign->last_run_at ? $campaign->last_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') . ' (' . $campaign->last_run_at->utc()->format('H:i') . ' UTC)' : 'Never' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Next Run</span><p class="text-sm text-gray-800">{{ $campaign->next_run_at ? $campaign->next_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') . ' (' . $campaign->next_run_at->utc()->format('H:i') . ' UTC)' : '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Auto-Publish</span><p class="text-sm text-gray-800">{{ $campaign->auto_publish ? 'Yes — fully automated' : 'No — manual review' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">AI Instructions</span><p class="text-sm text-gray-800 whitespace-pre-line">{{ $campaign->ai_instructions ?? $campaign->notes ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Created</span><p class="text-sm text-gray-800">{{ $campaign->created_at ? $campaign->created_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : '—' }} by {{ $campaign->creator->name ?? '—' }}</p></div>
    </div>

    {{-- Preset Settings (expandable) --}}
    @include('app-publish::partials.preset-fields', ['prefix' => 'template', 'label' => 'AI Template Settings'])
    @include('app-publish::partials.preset-fields', ['prefix' => 'preset', 'label' => 'WordPress Preset Settings'])

    {{-- Campaign History (articles) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Campaign History ({{ $campaign->articles->count() }} articles)</h3>
        </div>
        @forelse($campaign->articles as $article)
        <div class="p-4 border-b border-gray-100 hover:bg-gray-50 flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <a href="{{ route('publish.articles.show', $article->id) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 break-words">{{ $article->title ?: 'Untitled' }}</a>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $article->article_id }}
                    @if($article->wp_post_url) &middot; <a href="{{ $article->wp_post_url }}" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-0.5">WP Post <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> @endif
                    &middot; {{ $article->word_count ? number_format($article->word_count) . ' words' : '' }}
                    &middot; {{ $article->created_at ? $article->created_at->diffForHumans() : '' }}
                </p>
            </div>
            @if($article->status === 'completed' || $article->status === 'published')
                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700">{{ ucfirst($article->status) }}</span>
            @else
                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">{{ ucfirst($article->status) }}</span>
            @endif
        </div>
        @empty
        <div class="p-8 text-center text-gray-400 text-sm">No articles created by this campaign yet.</div>
        @endforelse
    </div>

    {{-- Run Logs --}}
    @if(isset($runLogs) && $runLogs->count() > 0)
    <div class="bg-gray-900 rounded-xl border border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-white uppercase tracking-wide mb-3">Cron Run Logs ({{ $runLogs->count() }})</h3>
        <div class="space-y-1 max-h-64 overflow-y-auto">
            @foreach($runLogs as $log)
            <div class="flex items-start gap-2 text-xs font-mono py-1 {{ !$loop->first ? 'border-t border-gray-800' : '' }}">
                <span class="text-gray-500 flex-shrink-0">{{ $log->created_at }}</span>
                <span class="text-gray-300 break-words">{{ $log->action }}: {{ $log->description }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Links --}}
    <div class="flex gap-4 text-xs text-gray-400">
        <a href="{{ route('campaigns.index') }}" class="hover:text-blue-600">&larr; All Campaigns</a>
        <a href="{{ route('campaigns.create', ['id' => $campaign->id]) }}" class="hover:text-blue-600">Edit Campaign</a>
        @if(Route::has('publish.schedule.index'))
            <a href="{{ route('publish.schedule.index') }}" class="hover:text-blue-600">Cron Schedule</a>
        @endif
    </div>
</div>

@push('scripts')
@include('app-publish::partials.preset-fields-mixin')
<script>
function campaignShow() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

    const templateData = @json($campaign->template);
    const presetData = @json($campaign->wpPreset);
    const checklistDefinitions = @json($checklistDefinitions ?? []);

    return {
        ...presetFieldsMixin('template'),
        ...presetFieldsMixin('preset'),
        template_schema: @json(\hexa_app_publish\Publishing\Templates\Models\PublishTemplate::getFieldSchema()),
        preset_schema: @json(\hexa_app_publish\Publishing\Presets\Models\PublishPreset::getFieldSchema()),
        ...presetFieldsMethods,

        running: false, runResult: '', runSuccess: false,
        runLog: [],
        runningMode: '',
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
        campaignChecklistByMode: checklistDefinitions,
        campaignChecklist: [],

        init() {
            if (templateData) this.loadPresetFields('template', templateData);
            if (presetData) this.loadPresetFields('preset', presetData);
        },

        checklistProgressText() {
            const enabled = this.campaignChecklist.filter(item => item.enabled !== false);
            const done = enabled.filter(item => item.status === 'done').length;
            return enabled.length ? (done + '/' + enabled.length + ' complete') : 'No checklist items';
        },

        cloneChecklist(mode) {
            const definitions = this.campaignChecklistByMode?.[mode] || [];
            return JSON.parse(JSON.stringify(definitions));
        },

        resetOperationState() {
            this.running = false;
            this.runningMode = '';
            this.operationId = null;
            this.operationStatus = '';
            this.operationTransport = '';
            this.operationTraceId = '';
            this.operationCurrent = '';
            this.operationLabel = '';
            this.operationShowUrl = '';
            this.operationStreamUrl = '';
            if (this.operationStreamController) {
                try { this.operationStreamController.abort(); } catch (e) {}
            }
            this.operationStreamController = null;
            if (this.operationPollTimer) {
                clearTimeout(this.operationPollTimer);
            }
            this.operationPollTimer = null;
        },

        pushRunLog(entry) {
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
            this.operationShowUrl = operation.show_url || '';
            this.operationStreamUrl = operation.stream_url || '';
            this.operationCurrent = operation.last_stage
                ? ('Current: ' + operation.last_stage.replace(/_/g, ' ') + (operation.last_message ? ' — ' + operation.last_message : ''))
                : (operation.last_message || '');
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
            this.runResult = message || (success ? 'Campaign run complete.' : 'Campaign run failed.');
            if (resultPayload?.article_url) {
                this.runResult += ' — Article: ' + resultPayload.article_url;
            }
            if (resultPayload?.wp_post_url) {
                this.runResult += ' — WP: ' + resultPayload.wp_post_url;
            }
        },

        async startOperation(mode, label) {
            if (!confirm('Run campaign now as ' + label.toLowerCase() + '?')) return;
            this.resetOperationState();
            this.running = true;
            this.runningMode = mode;
            this.runResult = '';
            this.runSuccess = false;
            this.runLog = [];
            this.operationLabel = label;
            this.campaignChecklist = this.cloneChecklist(mode);
            try {
                const r = await fetch('{{ route("campaigns.start-operation", $campaign->id) }}', { method: 'POST', headers, body: JSON.stringify({ mode }) });
                const d = await r.json();
                if (!r.ok || !d.success) {
                    throw new Error(d.message || 'Failed to start campaign operation');
                }
                this.campaignChecklist = d.checklist || this.campaignChecklist;
                this.applyOperation(d.operation);
                this.runResult = d.message || '';
                await this.startStreaming();
            } catch(e) {
                this.runSuccess = false;
                this.runResult = 'Error: ' + e.message;
                this.running = false;
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
                    const resp = await fetch(this.operationShowUrl, { headers: { 'Accept': 'application/json' } });
                    const data = await resp.json();
                    if (!data.success) throw new Error(data.message || 'Polling failed');
                    if (data.operation) this.applyOperation(data.operation);
                    (data.events || []).forEach((event) => {
                        if (!this.runLog.find(existing => existing.message === event.message && existing.stage === event.stage && existing.time === event.captured_at)) {
                            this.pushRunLog(event);
                            this.updateChecklistFromEvent(event);
                        }
                    });
                    if (['completed', 'failed'].includes(this.operationStatus)) {
                        const resultPayload = data.operation?.result_payload || {};
                        this.finishChecklist(this.operationStatus === 'completed', resultPayload.message || this.runResult, resultPayload);
                        return;
                    }
                    this.pollOperation(1000);
                } catch (e) {
                    this.running = false;
                    this.runSuccess = false;
                    this.runResult = 'Polling error: ' + e.message;
                }
            }, delay);
        },
    };
}
</script>
@endpush
@endsection
