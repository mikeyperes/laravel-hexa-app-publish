        get masterActivityLogKey() {
            return 'publishPipelineActivityLog:' + String(this.draftId || 'new') + ':' + String(this._tabInstanceId || 'tab');
        },

        get legacyMasterActivityLogKey() {
            return 'publishPipelineActivityLog:' + String(this.draftId || 'new');
        },

        get activeMasterActivityEntries() {
            if (this.selectedActivityRunTrace) {
                return this.activityRunPreviewEntries;
            }

            return this.masterActivityLog;
        },

        get visibleMasterActivityEntries() {
            return this.activeMasterActivityEntries.filter(entry => this.pipelineDebugEnabled || !entry.debug_only);
        },

        get selectedActivityRun() {
            return this.activityRunHistory.find(run => run.client_trace === this.selectedActivityRunTrace) || null;
        },

        get crossDraftActivityRunsVisible() {
            return (this.crossDraftActivityRuns || []).filter(run => Number(run.draft_id || 0) !== Number(this.draftId || 0));
        },

        get lastMasterActivityEntry() {
            return this.visibleMasterActivityEntries.length
                ? this.visibleMasterActivityEntries[this.visibleMasterActivityEntries.length - 1]
                : null;
        },

        _activityTime() {
            return new Date().toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        },

        _safeStringify(value) {
            try {
                return JSON.stringify(value);
            } catch (e) {
                return String(value);
            }
        },

        _stableSignature(value) {
            return this._safeStringify(value);
        },

        _chunkArray(items = [], size = 200) {
            if (!Array.isArray(items) || items.length === 0) return [];

            const chunkSize = Math.max(1, Number(size) || 1);
            const chunks = [];

            for (let i = 0; i < items.length; i += chunkSize) {
                chunks.push(items.slice(i, i + chunkSize));
            }

            return chunks;
        },

        _pruneMasterActivityLog(maxEntries = 400) {
            const limit = Math.max(1, Number(maxEntries) || 1);
            if (this.masterActivityLog.length <= limit) return;

            let overflow = this.masterActivityLog.length - limit;

            for (let i = 0; i < this.masterActivityLog.length && overflow > 0; ) {
                if (this.masterActivityLog[i]?.server_persisted) {
                    this.masterActivityLog.splice(i, 1);
                    overflow--;
                    continue;
                }

                i++;
            }

            if (overflow > 0) {
                this.masterActivityLog.splice(0, overflow);
            }
        },

        _summarizeValue(value, maxLen = 320) {
            if (value === null || value === undefined || value === '') return '';
            const stringValue = typeof value === 'string' ? value : this._safeStringify(value);
            if (stringValue.length <= maxLen) return stringValue;

            return stringValue.substring(0, maxLen) + '...';
        },

        _activityUrlLabel(url) {
            if (!url) return '';
            try {
                const parsed = new URL(url, window.location.origin);

                return parsed.pathname + parsed.search;
            } catch (e) {
                return String(url);
            }
        },

        _isLikelyNavigationAbort(error) {
            const message = String(error?.message || '').toLowerCase();

            return this._pageUnloading
                || document.visibilityState === 'hidden'
                || message.includes('failed to fetch')
                || message.includes('abort');
        },

        _masterActivityEntryKey(entry = {}) {
            if (entry.client_event_id) return String(entry.client_event_id);

            return [
                entry.run_trace || this._clientSessionTraceId || 'run',
                entry.id ?? 'event',
                entry.time || '',
                entry.message || '',
            ].join(':');
        },

        _normalizeMasterActivityEntry(entry = {}) {
            const normalizedId = Number(entry.id ?? 0) || (++this._masterActivitySeq);

            return {
                id: normalizedId,
                client_event_id: entry.client_event_id || ((entry.run_trace || this._clientSessionTraceId || 'run') + ':' + normalizedId),
                run_trace: entry.run_trace || this._clientSessionTraceId || '',
                captured_at: entry.captured_at || null,
                time: entry.time || this._activityTime(),
                scope: entry.scope || 'pipeline',
                type: entry.type || 'info',
                message: entry.message || '',
                stage: entry.stage || '',
                substage: entry.substage || '',
                trace_id: entry.trace_id || '',
                duration_ms: Number.isFinite(Number(entry.duration_ms)) ? Number(entry.duration_ms) : null,
                sequence_no: entry.sequence_no ?? null,
                method: entry.method || '',
                status: entry.status ?? entry.status_code ?? null,
                url: entry.url ? this._activityUrlLabel(entry.url) : '',
                details: entry.details || '',
                payload_preview: entry.payload_preview || '',
                response_preview: entry.response_preview || '',
                debug_only: !!entry.debug_only,
                draft_id: entry.draft_id ?? this.draftId ?? null,
                step: entry.step ?? this.currentStep ?? null,
                server_persisted: !!entry.server_persisted,
            };
        },

        _formatActivityRunStage(run = {}) {
            const stage = String(run.last_stage || '').trim();
            const substage = String(run.last_substage || '').trim();
            const combined = [stage, substage].filter(Boolean).join(' / ');

            return combined || 'activity recorded';
        },

        _formatActivityRunTime(value) {
            if (!value) return '';

            try {
                return new Date(value).toLocaleString('en-US', {
                    hour12: false,
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
            } catch (error) {
                return String(value);
            }
        },

        _normalizeActivityRun(run = {}) {
            const clientTrace = String(run.client_trace || '').trim();

            return {
                id: Number(run.id || 0) || null,
                draft_id: Number(run.draft_id || 0) || null,
                draft_title: String(run.draft_title || '').trim(),
                client_trace: clientTrace,
                workflow_type: String(run.workflow_type || '').trim() || 'default',
                debug_enabled: !!run.debug_enabled,
                started_at: run.started_at || null,
                last_event_at: run.last_event_at || null,
                last_scope: String(run.last_scope || '').trim(),
                last_type: String(run.last_type || '').trim(),
                last_stage: String(run.last_stage || '').trim(),
                last_substage: String(run.last_substage || '').trim(),
                total_events: Number(run.total_events || 0) || 0,
                stage_label: this._formatActivityRunStage(run),
                started_at_label: this._formatActivityRunTime(run.started_at),
                last_event_at_label: this._formatActivityRunTime(run.last_event_at),
            };
        },

        _mergeActivityRunHistory(runs = []) {
            if (!Array.isArray(runs) || runs.length === 0) return;

            const existing = new Map((this.activityRunHistory || []).map(run => [String(run.client_trace || ''), run]));
            runs.map(run => this._normalizeActivityRun(run))
                .filter(run => run.client_trace)
                .forEach(run => {
                    if (existing.has(run.client_trace)) {
                        Object.assign(existing.get(run.client_trace), run);
                        return;
                    }

                    this.activityRunHistory.push(run);
                    existing.set(run.client_trace, run);
                });

            this.activityRunHistory.sort((a, b) => {
                const aAt = a.last_event_at ? Date.parse(a.last_event_at) : NaN;
                const bAt = b.last_event_at ? Date.parse(b.last_event_at) : NaN;
                if (Number.isFinite(aAt) && Number.isFinite(bAt) && aAt !== bAt) {
                    return bAt - aAt;
                }

                return String(b.client_trace || '').localeCompare(String(a.client_trace || ''));
            });
        },

        _recordActivityRunHeartbeat(entry = {}) {
            const trace = String(entry.run_trace || this._clientSessionTraceId || '').trim();
            if (!trace) return;

            const matchingEntries = this.masterActivityLog.filter(item => String(item.run_trace || '') === trace);
            this._mergeActivityRunHistory([{
                client_trace: trace,
                workflow_type: this.currentWorkflowKey(),
                debug_enabled: this.pipelineDebugEnabled,
                started_at: matchingEntries[0]?.captured_at || entry.captured_at || new Date().toISOString(),
                last_event_at: entry.captured_at || new Date().toISOString(),
                last_scope: entry.scope || '',
                last_type: entry.type || '',
                last_stage: entry.stage || '',
                last_substage: entry.substage || '',
                total_events: matchingEntries.length,
            }]);
        },

        _setActivityRunPreviewEntries(entries = []) {
            this.activityRunPreviewEntries = Array.isArray(entries)
                ? entries.map(entry => this._normalizeMasterActivityEntry({ ...entry, server_persisted: true }))
                : [];
        },

        _buildActivityIndexUrl({ trace = null, limit = 400, runsLimit = 10, includeCrossDraftRuns = false, allDraftsLimit = 12 } = {}) {
            const params = new URLSearchParams({
                draft_id: String(this.draftId || ''),
                limit: String(limit),
                runs_limit: String(runsLimit),
            });

            if (trace) {
                params.set('trace', String(trace));
            }

            if (includeCrossDraftRuns) {
                params.set('include_cross_draft_runs', '1');
                params.set('exclude_draft_id', String(this.draftId || ''));
                params.set('all_drafts_limit', String(allDraftsLimit));
            }

            return '{{ route("publish.pipeline.activity.index") }}?' + params.toString();
        },

        _mergeMasterActivityEntries(entries = []) {
            if (!Array.isArray(entries) || entries.length === 0) return;

            const normalizedEntries = entries.map(entry => this._normalizeMasterActivityEntry(entry));
            const existing = new Map(this.masterActivityLog.map(entry => [this._masterActivityEntryKey(entry), entry]));

            normalizedEntries.forEach(entry => {
                const key = this._masterActivityEntryKey(entry);
                if (existing.has(key)) {
                    Object.assign(existing.get(key), entry, {
                        server_persisted: existing.get(key).server_persisted || entry.server_persisted,
                    });
                    return;
                }

                this.masterActivityLog.push(entry);
                existing.set(key, entry);
            });

            this.masterActivityLog.sort((a, b) => {
                const aAt = a.captured_at ? Date.parse(a.captured_at) : NaN;
                const bAt = b.captured_at ? Date.parse(b.captured_at) : NaN;
                if (Number.isFinite(aAt) && Number.isFinite(bAt) && aAt !== bAt) {
                    return aAt - bAt;
                }
                if ((a.run_trace || '') === (b.run_trace || '') && a.id !== b.id) {
                    return a.id - b.id;
                }

                return this._masterActivityEntryKey(a).localeCompare(this._masterActivityEntryKey(b));
            });

            this._pruneMasterActivityLog(400);

            const maxId = this.masterActivityLog.reduce((max, entry) => Math.max(max, Number(entry.id || 0)), 0);
            this._masterActivitySeq = Math.max(this._masterActivitySeq, maxId);
            this._queuePersistMasterActivityLog();
            this._scrollMasterActivityLog();
        },

        async _rawPipelineFetch(input, init = {}) {
            const originalFetch = window.__publishPipelineOriginalFetch || window.fetch.bind(window);
            const headers = new Headers(init?.headers || {});
            headers.set('X-Pipeline-Draft-Id', String(this.draftId || ''));
            headers.set('X-Pipeline-Tab-Id', this._ensureTabInstanceId());
            if (this.pipelineDebugEnabled) headers.set('X-Pipeline-Debug', '1');

            const finalInit = { ...(init || {}), headers };
            delete finalInit.__skipActivityTracking;

            return originalFetch(input, finalInit);
        },

        _buildClientTrace(scope = 'trace') {
            return [
                scope,
                this.draftId || 'new',
                Date.now().toString(36),
                Math.random().toString(36).slice(2, 8),
            ].join('-');
        },

        _ensureTabInstanceId() {
            if (this._tabInstanceId) return this._tabInstanceId;

            try {
                const existing = sessionStorage.getItem('publishPipelineTabId');
                if (existing) {
                    this._tabInstanceId = existing;
                    return this._tabInstanceId;
                }

                this._tabInstanceId = this._buildClientTrace('tab');
                sessionStorage.setItem('publishPipelineTabId', this._tabInstanceId);
                return this._tabInstanceId;
            } catch (error) {
                this._tabInstanceId = this._buildClientTrace('tab');
                return this._tabInstanceId;
            }
        },

        _useBootstrappedPresetsIfAvailable() {
            const userId = String(this.selectedUser?.id || '');
            if (!userId
                || this._bootstrappedPresetUserId === userId
                || String(this.initialUserPresetUserId || '') !== userId
                || !Array.isArray(this.initialUserPresets)
            ) {
                return false;
            }

            this.presets = this.initialUserPresets.map(item => ({ ...(item || {}) }));
            this._bootstrappedPresetUserId = userId;
            this._logDebug('bootstrap', 'Used bootstrapped preset payload', {
                stage: 'bootstrap',
                substage: 'presets',
                details: this._summarizeValue({ user_id: userId, count: this.presets.length }, 200),
            });

            return true;
        },

        _useBootstrappedTemplatesIfAvailable() {
            const userId = String(this.selectedUser?.id || '');
            if (!userId
                || this._bootstrappedTemplateUserId === userId
                || String(this.initialUserTemplateUserId || '') !== userId
                || !Array.isArray(this.initialUserTemplates)
            ) {
                return false;
            }

            this.templates = this.initialUserTemplates.map(item => ({ ...(item || {}) }));
            this._bootstrappedTemplateUserId = userId;
            this._logDebug('bootstrap', 'Used bootstrapped template payload', {
                stage: 'bootstrap',
                substage: 'templates',
                details: this._summarizeValue({ user_id: userId, count: this.templates.length }, 200),
            });

            return true;
        },

        async _withPersistenceSuspended(callback, { state = true, draft = true } = {}) {
            const prevState = this._suspendPipelineStateSave;
            const prevDraft = this._suspendDraftAutoSave;
            if (state) this._suspendPipelineStateSave = true;
            if (draft) this._suspendDraftAutoSave = true;

            try {
                return await callback();
            } finally {
                this._suspendPipelineStateSave = prevState;
                this._suspendDraftAutoSave = prevDraft;

                if (!this._restoring && !this._suspendPipelineStateSave && this._pendingPostSuspendPipelineStateSave) {
                    this._pendingPostSuspendPipelineStateSave = false;
                    this.queuePipelineStateSave(120);
                }

                if (!this._restoring && !this._suspendDraftAutoSave && this._pendingPostSuspendDraftSave) {
                    this._pendingPostSuspendDraftSave = false;
                    this.queueAutoSaveDraft(300);
                }
            }
        },

        _scrollMasterActivityLog() {
            this.$nextTick(() => {
                const el = this.$refs.masterActivityLogContainer;
                if (el && this.masterActivityAutoScroll) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        },

        _queuePersistMasterActivityLog() {
            clearTimeout(this._masterActivityPersistTimer);
            this._masterActivityPersistTimer = setTimeout(() => {
                this._masterActivityPersistTimer = null;
                this._persistMasterActivityLog();
            }, 150);
        },

        _persistMasterActivityLog() {
            try {
                localStorage.setItem(this.masterActivityLogKey, JSON.stringify({
                    saved_at: Date.now(),
                    seq: this._masterActivitySeq,
                    entries: this.masterActivityLog.slice(-400),
                }));
                localStorage.removeItem(this.legacyMasterActivityLogKey);
            } catch (error) {}
        },

        _restoreMasterActivityLog() {
            try {
                const saved = localStorage.getItem(this.masterActivityLogKey)
                    || localStorage.getItem(this.legacyMasterActivityLogKey);
                if (!saved) return;

                const parsed = JSON.parse(saved);
                const entries = Array.isArray(parsed?.entries) ? parsed.entries : [];
                this.masterActivityLog = [];
                this._masterActivitySeq = Number(parsed?.seq || 0);
                this._mergeMasterActivityEntries(entries);
            } catch (error) {
                this.masterActivityLog = [];
                this._masterActivitySeq = 0;
            }
        },

        async _loadActivityRun(trace, { notify = false } = {}) {
            if (!this.draftId || !trace || this.activityRunHistoryLoading) return;

            this.activityRunHistoryLoading = true;

            try {
                const response = await this._rawPipelineFetch(this._buildActivityIndexUrl({
                    trace,
                    limit: 400,
                    runsLimit: 10,
                }), {
                    method: 'GET',
                    __skipActivityTracking: true,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    if (Array.isArray(data.recent_runs)) {
                        this._mergeActivityRunHistory(data.recent_runs);
                    }
                    this.selectedActivityRunTrace = data.selected_trace || String(trace);
                    this._setActivityRunPreviewEntries(data.events || []);
                    this._activityRunHistoryRestored = true;
                    this._scrollMasterActivityLog();
                    if (notify) {
                        this.showNotification('success', 'Loaded persisted run ' + this.selectedActivityRunTrace);
                    }
                    return;
                }

                throw new Error(data.message || 'Failed to load persisted run');
            } catch (error) {
                this.showNotification('error', error.message || 'Failed to load persisted run');
            } finally {
                this.activityRunHistoryLoading = false;
            }
        },

        clearActivityRunPreview({ restoreLive = false } = {}) {
            this.selectedActivityRunTrace = '';
            this.activityRunPreviewEntries = [];
            if (restoreLive) {
                this._serverActivityLogRestored = false;
                this._restoreServerActivityLog(true);
            }
        },

        _openCrossDraftActivityRun(run = {}) {
            const draftId = Number(run?.draft_id || 0);
            if (!draftId) return;

            const url = new URL('{{ route("publish.pipeline") }}', window.location.origin);
            url.searchParams.set('id', String(draftId));
            url.searchParams.set('step', '7');
            if (this.pipelineDebugEnabled) {
                url.searchParams.set('debug', '1');
            }

            window.open(url.toString(), '_blank', 'noopener');
        },

        _handleDraftSessionConflict(scope, data = {}, { silent = false } = {}) {
            const conflict = data?.conflict || {};
            const message = data?.message || 'Another tab is actively editing this draft. Saves are paused in this tab.';

            this.draftSessionConflict = {
                ...conflict,
                scope,
                detected_at: new Date().toISOString(),
            };
            this._draftSessionConflictActive = true;
            this._pendingDraftSave = false;
            this._pendingDraftSilent = true;
            this._pendingServerPipelineStateSave = false;

            this._logActivity(scope, 'warning', message, {
                stage: scope === 'state' ? 'state' : 'draft',
                substage: 'session_conflict',
                details: this._summarizeValue(this.draftSessionConflict, 600),
            });

            if (!silent) {
                this.showNotification('error', message);
            }
        },

        _clearDraftSessionConflict() {
            this._draftSessionConflictActive = false;
            this.draftSessionConflict = null;
        },

        _logActivity(scope, type, message, meta = {}) {
            const nextId = ++this._masterActivitySeq;
            const entry = this._normalizeMasterActivityEntry({
                id: nextId,
                client_event_id: meta.client_event_id || ((this._clientSessionTraceId || 'session') + ':' + nextId),
                run_trace: meta.run_trace || this._clientSessionTraceId || '',
                captured_at: meta.captured_at || new Date().toISOString(),
                time: this._activityTime(),
                scope: scope || 'pipeline',
                type: type || 'info',
                message: message || '',
                stage: meta.stage || '',
                substage: meta.substage || '',
                trace_id: meta.trace_id || '',
                duration_ms: Number.isFinite(Number(meta.duration_ms)) ? Number(meta.duration_ms) : null,
                sequence_no: meta.sequence_no ?? null,
                method: meta.method || '',
                status: meta.status ?? null,
                url: meta.url ? this._activityUrlLabel(meta.url) : '',
                details: meta.details || '',
                payload_preview: meta.payload_preview || '',
                response_preview: meta.response_preview || '',
                debug_only: !!meta.debug_only,
                draft_id: meta.draft_id ?? this.draftId ?? null,
                step: meta.step ?? this.currentStep ?? null,
                server_persisted: !!meta.server_persisted,
            });

            this.masterActivityLog.push(entry);
            this._pruneMasterActivityLog(400);
            this._recordActivityRunHeartbeat(entry);

            if (this.pipelineDebugEnabled || entry.type === 'error' || entry.type === 'warning') {
                const consoleMethod = entry.type === 'error'
                    ? 'error'
                    : (entry.type === 'warning' ? 'warn' : 'log');
                console[consoleMethod]('[PublishPipeline][' + entry.scope + ']', entry.message, entry);
            }

            this._queuePersistMasterActivityLog();
            this._queueServerActivitySync();
            this._scrollMasterActivityLog();

            return entry;
        },

        _logDebug(scope, message, meta = {}) {
            return this._logActivity(scope, 'debug', message, { ...meta, debug_only: true });
        },

        _queueServerActivitySync(delay = 400) {
            if (!this.draftId) return;
            clearTimeout(this._serverActivitySyncTimer);
            this._serverActivitySyncTimer = setTimeout(() => {
                this._serverActivitySyncTimer = null;
                this._syncMasterActivityLogToServer();
            }, delay);
        },

        async _syncMasterActivityLogToServer() {
            if (!this.draftId) return;
            if (this._syncingMasterActivityServer) {
                this._pendingMasterActivitySync = true;
                return;
            }

            const entries = this.masterActivityLog.filter(entry => !entry.server_persisted);

            if (entries.length === 0) return;

            this._syncingMasterActivityServer = true;
            this._pendingMasterActivitySync = false;

            try {
                const groupedEntries = entries.reduce((carry, entry) => {
                    const runTrace = entry.run_trace || this._clientSessionTraceId;
                    if (!carry[runTrace]) carry[runTrace] = [];
                    carry[runTrace].push(entry);
                    return carry;
                }, {});

                let syncFailed = false;
                for (const [runTrace, grouped] of Object.entries(groupedEntries)) {
                    const chunks = this._chunkArray(grouped, this._masterActivitySyncBatchSize);

                    for (const chunk of chunks) {
                        const response = await this._rawPipelineFetch('{{ route("publish.pipeline.activity.sync") }}', {
                            method: 'POST',
                            __skipActivityTracking: true,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({
                                draft_id: this.draftId,
                                client_trace: runTrace,
                                workflow_type: this.currentWorkflowKey(),
                                debug_enabled: this.pipelineDebugEnabled,
                                entries: chunk.map(entry => ({
                                    id: entry.id,
                                    client_event_id: entry.client_event_id,
                                    run_trace: entry.run_trace,
                                    captured_at: entry.captured_at,
                                    scope: entry.scope,
                                    type: entry.type,
                                    message: entry.message,
                                    stage: entry.stage,
                                    substage: entry.substage,
                                    trace_id: entry.trace_id,
                                    duration_ms: entry.duration_ms,
                                    sequence_no: entry.sequence_no,
                                    method: entry.method,
                                    status: entry.status,
                                    url: entry.url,
                                    details: entry.details,
                                    payload_preview: entry.payload_preview,
                                    response_preview: entry.response_preview,
                                    debug_only: entry.debug_only,
                                    step: entry.step,
                                })),
                            }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (response.ok && data.success) {
                            const syncedIds = new Set(data.synced_event_ids || []);
                            this.masterActivityLog.forEach(entry => {
                                if (syncedIds.has(entry.client_event_id)) {
                                    entry.server_persisted = true;
                                }
                            });
                            this._queuePersistMasterActivityLog();
                            continue;
                        }

                        this._pendingMasterActivitySync = true;
                        syncFailed = true;
                        break;
                    }

                    if (syncFailed) break;
                }
            } catch (error) {
                this._pendingMasterActivitySync = true;
            }

            this._syncingMasterActivityServer = false;
            if (this._pendingMasterActivitySync) {
                this._queueServerActivitySync(800);
            }
        },

        async _restoreServerActivityLog(force = false) {
            if (!this.draftId) return;
            if (this._serverActivityLogRestored || this._serverActivityLogLoading) return;
            if (!force && !this.pipelineDebugEnabled && !this.masterActivityLogOpen) return;

            this._serverActivityLogLoading = true;

            try {
                const response = await this._rawPipelineFetch(this._buildActivityIndexUrl({
                    limit: 400,
                    runsLimit: 10,
                    includeCrossDraftRuns: true,
                    allDraftsLimit: 12,
                }), {
                    method: 'GET',
                    __skipActivityTracking: true,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    if (Array.isArray(data.recent_runs)) {
                        this._mergeActivityRunHistory(data.recent_runs);
                        this._activityRunHistoryRestored = true;
                    }
                    if (Array.isArray(data.recent_draft_runs)) {
                        this.crossDraftActivityRuns = data.recent_draft_runs.map(run => this._normalizeActivityRun(run));
                        this._crossDraftActivityRunsRestored = true;
                    }
                    if (!this.selectedActivityRunTrace && Array.isArray(data.events) && data.events.length > 0) {
                        this._mergeMasterActivityEntries(data.events.map(entry => ({ ...entry, server_persisted: true })));
                    }
                }
                this._serverActivityLogRestored = true;
            } catch (error) {
            } finally {
                this._serverActivityLogLoading = false;
            }
        },

        async _clearMasterActivityLogOnServer() {
            if (!this.draftId) return;

            try {
                await this._rawPipelineFetch('{{ route("publish.pipeline.activity.clear") }}', {
                    method: 'DELETE',
                    __skipActivityTracking: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ draft_id: this.draftId }),
                });
            } catch (error) {}
        },

        _installActivityFetchTracker() {
            window.__publishPipelineActivityTarget = this;

            if (window.__publishPipelineTrackedFetchInstalled) {
                return;
            }

            const originalFetch = window.fetch.bind(window);
            window.__publishPipelineTrackedFetchInstalled = true;
            window.__publishPipelineOriginalFetch = originalFetch;
            window.fetch = (input, init) => {
                const target = window.__publishPipelineActivityTarget;
                if (!target || typeof target._trackedFetch !== 'function') {
                    return originalFetch(input, init);
                }

                return target._trackedFetch(originalFetch, input, init);
            };
        },

        async _trackedFetch(originalFetch, input, init = {}) {
            const url = typeof input === 'string' ? input : (input?.url || '');
            const normalizedUrl = this._activityUrlLabel(url);
            const initOptions = { ...(init || {}) };
            const skipActivityTracking = !!initOptions.__skipActivityTracking || normalizedUrl.startsWith('/article/publish/activity');
            delete initOptions.__skipActivityTracking;
            const method = String(init?.method || (typeof input !== 'string' ? input.method : 'GET') || 'GET').toUpperCase();
            const headers = new Headers(initOptions?.headers || (input instanceof Request ? input.headers : {}));
            const traceId = headers.get('X-Pipeline-Client-Trace') || this._buildClientTrace(method.toLowerCase());
            const startedAt = typeof performance !== 'undefined' ? performance.now() : Date.now();

            if (!headers.has('X-Pipeline-Client-Trace')) {
                headers.set('X-Pipeline-Client-Trace', traceId);
            }
            headers.set('X-Pipeline-Draft-Id', String(this.draftId || ''));
            headers.set('X-Pipeline-Tab-Id', this._ensureTabInstanceId());
            if (this.pipelineDebugEnabled) {
                headers.set('X-Pipeline-Debug', '1');
            }

            if (skipActivityTracking) {
                let passthroughInput = input;
                let passthroughInit = { ...initOptions, headers };
                if (input instanceof Request) {
                    passthroughInput = new Request(input, passthroughInit);
                    passthroughInit = undefined;
                }

                return originalFetch(passthroughInput, passthroughInit);
            }

            const requestBodyPreview = this.pipelineDebugEnabled
                ? this._summarizeValue(initOptions?.body || '', 1200)
                : '';

            this._logActivity('network', 'step', method + ' ' + normalizedUrl, {
                trace_id: traceId,
                method,
                url: normalizedUrl,
                payload_preview: requestBodyPreview,
                debug_only: !this.pipelineDebugEnabled,
            });

            let finalInput = input;
            let finalInit = { ...initOptions, headers };
            if (input instanceof Request) {
                finalInput = new Request(input, finalInit);
                finalInit = undefined;
            }

            try {
                const response = await originalFetch(finalInput, finalInit);
                const durationMs = Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt));
                const contentType = response.headers.get('content-type') || '';

                this._logActivity('network', response.ok ? 'success' : 'error', method + ' ' + normalizedUrl + ' -> HTTP ' + response.status, {
                    trace_id: traceId,
                    method,
                    url: normalizedUrl,
                    status: response.status,
                    duration_ms: durationMs,
                    stage: 'http',
                    substage: response.ok ? 'response' : 'error',
                    details: this.pipelineDebugEnabled ? 'content-type: ' + contentType : '',
                    debug_only: !this.pipelineDebugEnabled,
                });

                if (this.pipelineDebugEnabled && response.clone && !contentType.includes('text/event-stream')) {
                    response.clone().text().then(text => {
                        if (text) {
                            this._logDebug('network', 'Response preview for ' + normalizedUrl, {
                                trace_id: traceId,
                                method,
                                url: normalizedUrl,
                                status: response.status,
                                response_preview: this._summarizeValue(text, 1200),
                            });
                        }
                    }).catch(() => {});
                }

                return response;
            } catch (error) {
                const durationMs = Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt));

                this._logActivity('network', 'error', method + ' ' + normalizedUrl + ' failed: ' + (error.message || 'Request failed'), {
                    trace_id: traceId,
                    method,
                    url: normalizedUrl,
                    duration_ms: durationMs,
                    stage: 'http',
                    substage: 'exception',
                    debug_only: false,
                });

                throw error;
            }
        },

        togglePipelineDebug() {
            this.pipelineDebugEnabled = !this.pipelineDebugEnabled;
            localStorage.setItem('publishPipelineDebug', this.pipelineDebugEnabled ? 'true' : 'false');
            this._logActivity('debug', 'info', this.pipelineDebugEnabled ? 'Verbose pipeline debug enabled' : 'Verbose pipeline debug disabled', {
                debug_only: false,
                trace_id: this._clientSessionTraceId,
            });

            if (this.pipelineDebugEnabled) {
                this.capturePipelineSnapshot('debug-enabled');
                this._restoreServerActivityLog(true);
            }
        },

        async clearMasterActivityLog() {
            this.masterActivityLog = [];
            this.activityRunHistory = [];
            this.selectedActivityRunTrace = '';
            this.activityRunPreviewEntries = [];
            this.crossDraftActivityRuns = [];
            this._masterActivitySeq = 0;
            clearTimeout(this._masterActivityPersistTimer);
            clearTimeout(this._serverActivitySyncTimer);
            this._pendingMasterActivitySync = false;
            this._serverActivityLogRestored = false;
            this._activityRunHistoryRestored = false;
            this._crossDraftActivityRunsRestored = false;
            localStorage.removeItem(this.masterActivityLogKey);
            localStorage.removeItem(this.legacyMasterActivityLogKey);
            await this._clearMasterActivityLogOnServer();
            this.showNotification('success', 'Master activity log cleared');
        },

        async refreshActivityRunHistory() {
            this._serverActivityLogRestored = false;
            this._crossDraftActivityRunsRestored = false;
            await this._restoreServerActivityLog(true);
            this.showNotification('success', 'Activity runs reloaded');
        },

        async copyMasterActivityLog() {
            const payload = JSON.stringify(this.visibleMasterActivityEntries, null, 2);
            try {
                await navigator.clipboard.writeText(payload);
                this.showNotification('success', 'Master activity log copied');
            } catch (error) {
                this.showNotification('error', 'Failed to copy master activity log');
            }
        },

        downloadMasterActivityLog() {
            const payload = JSON.stringify({
                draft_id: this.draftId || null,
                downloaded_at: new Date().toISOString(),
                debug_enabled: this.pipelineDebugEnabled,
                entries: this.visibleMasterActivityEntries,
            }, null, 2);

            try {
                const blob = new Blob([payload], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'publish-pipeline-log-draft-' + String(this.draftId || 'new') + '.json';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
                this.showNotification('success', 'Master activity log downloaded');
            } catch (error) {
                this.showNotification('error', 'Failed to download master activity log');
            }
        },

        capturePipelineSnapshot(reason = 'manual') {
            const snapshot = {
                reason,
                draft_id: this.draftId,
                current_step: this.currentStep,
                completed_steps: [...(this.completedSteps || [])],
                open_steps: [...(this.openSteps || [])],
                selected_user_id: this.selectedUser?.id || null,
                selected_site_id: this.selectedSite?.id || null,
                publish_action: this.publishAction,
                state_signature: this._lastLocalPipelineStateSignature,
                server_state_signature: this._lastServerPipelineStateSignature,
                draft_signature: this._lastDraftPayloadSignature,
                flags: {
                    restoring: this._restoring,
                    saving_draft: this.savingDraft,
                    spinning: this.spinning,
                    prompt_preview_dirty: this.promptPreviewDirty,
                    preparing: this.preparing,
                    prepare_complete: this.prepareComplete,
                    publishing: this.publishing,
                    photo_suggestions_pending: this.photoSuggestionsPending,
                    featured_search_pending: this.featuredSearchPending,
                },
                counts: {
                    sources: this.sources.length,
                    check_results: this.checkResults.length,
                    approved_sources: this.approvedSources.length,
                    photo_suggestions: this.photoSuggestions.filter(p => !p.removed).length,
                    uploaded_images: Object.keys(this.uploadedImages || {}).length,
                    prepare_log: this.prepareLog.length,
                    master_log: this.masterActivityLog.length,
                },
            };

            this._logActivity('snapshot', 'info', 'Pipeline snapshot captured', {
                details: this._summarizeValue(snapshot, 1400),
                debug_only: !this.pipelineDebugEnabled,
            });
            this.showNotification('success', 'Pipeline snapshot captured');

            return snapshot;
        },
