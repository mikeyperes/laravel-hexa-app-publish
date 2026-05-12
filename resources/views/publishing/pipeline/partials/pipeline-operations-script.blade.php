        // ── Step 8: Review & Publish ────────────────────
        _appendPrepareLogEntry(entry = {}) {
            const capturedAt = entry.captured_at ? Date.parse(entry.captured_at) : NaN;
            const time = entry.time || (Number.isFinite(capturedAt)
                ? new Date(capturedAt).toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' })
                : this._activityTime());

            this.prepareLog.push({
                client_event_id: entry.client_event_id || '',
                run_trace: entry.run_trace || '',
                type: entry.type || 'info',
                message: entry.message || '',
                time,
                stage: entry.stage || '',
                substage: entry.substage || '',
                trace_id: entry.trace_id || this.prepareTraceId || this.publishTraceId || '',
                duration_ms: Number.isFinite(Number(entry.duration_ms)) ? Number(entry.duration_ms) : null,
                sequence_no: entry.sequence_no ?? null,
            });

            this.$nextTick(() => {
                const el = this.$refs.prepareLogContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        _resetPrepareOperationTracking({ clearLog = false } = {}) {
            this._stopPipelineOperationPoll('prepare');
            this._stopPipelineOperationStream('prepare');
            this.prepareOperationId = null;
            this.prepareOperationStatus = '';
            this.prepareOperationTransport = '';
            this.prepareOperationClientTrace = '';
            this.prepareOperationLastSequence = 0;
            this.prepareLastMessage = '';
            if (clearLog) this.prepareLog = [];
        },

        _resetPublishOperationTracking() {
            this._stopPipelineOperationPoll('publish');
            this._stopPipelineOperationStream('publish');
            this.publishOperationId = null;
            this.publishOperationStatus = '';
            this.publishOperationTransport = '';
            this.publishOperationClientTrace = '';
            this.publishOperationLastSequence = 0;
        },

        _prepareOperationIsActive() {
            return this.preparing || ['queued', 'running'].includes(this.prepareOperationStatus);
        },

        _normalizePipelineArticleType(value = null) {
            return String(value || this.currentArticleType || this.article_type || 'editorial').trim().toLowerCase();
        },

        _pipelineOperationContextMatches(operation = {}) {
            if (!operation || typeof operation !== 'object') return true;

            const currentSiteId = String(
                this.selectedSiteId
                || this.selectedSite?.id
                || this.draftState?.selectedSiteId
                || ''
            ).trim();
            const currentArticleType = this._normalizePipelineArticleType();
            const summary = operation.request_summary && typeof operation.request_summary === 'object'
                ? operation.request_summary
                : {};
            const resultPayload = operation.result_payload && typeof operation.result_payload === 'object'
                ? operation.result_payload
                : {};

            const operationSiteId = String(
                summary.site_id
                ?? summary.publish_site_id
                ?? operation.publish_site_id
                ?? resultPayload.site_id
                ?? resultPayload.publish_site_id
                ?? ''
            ).trim();
            if (currentSiteId && operationSiteId && currentSiteId !== operationSiteId) {
                return false;
            }

            const operationArticleType = String(
                summary.article_type
                ?? resultPayload.article_type
                ?? ''
            ).trim().toLowerCase();
            if (currentArticleType && operationArticleType && currentArticleType !== operationArticleType) {
                return false;
            }

            return true;
        },

        _isStalePipelineOperationSnapshot(type, operation = {}) {
            const incomingId = Number(operation?.id || 0);
            if (!incomingId) return false;

            const currentId = Number(type === 'prepare'
                ? (this.prepareOperationId || 0)
                : (this.publishOperationId || 0));

            if (currentId > 0 && incomingId < currentId) {
                return true;
            }

            return !this._pipelineOperationContextMatches(operation);
        },

        _normalizePipelineOperationEvents(events) {
            if (Array.isArray(events)) return events;
            if (events && typeof events === 'object') return Object.values(events);
            return [];
        },

        _pipelineOperationUsesLiveStream(operation = null) {
            if (!this.pipelineOperationLiveStreamEnabled) return false;
            if (typeof window === 'undefined' || typeof window.TextDecoder === 'undefined') return false;
            if (operation && operation.stream_supported === false) return false;
            return true;
        },

        _pipelineOperationStreamUrl(operationId) {
            return '{{ route("publish.pipeline.operations.stream", ["operation" => "__OP__"]) }}'.replace('__OP__', encodeURIComponent(String(operationId)));
        },

        _stopPipelineOperationStream(type) {
            const controllerKey = type === 'prepare' ? 'prepareOperationStreamController' : 'publishOperationStreamController';
            const reconnectKey = type === 'prepare' ? 'prepareOperationStreamReconnectTimer' : 'publishOperationStreamReconnectTimer';
            const streamingKey = type === 'prepare' ? '_streamingPrepareOperation' : '_streamingPublishOperation';

            if (this[reconnectKey]) {
                clearTimeout(this[reconnectKey]);
                this[reconnectKey] = null;
            }
            if (this[controllerKey]) {
                this[controllerKey].abort();
                this[controllerKey] = null;
            }
            this[streamingKey] = false;
        },

        _schedulePipelineOperationStreamReconnect(type, delay = 1250) {
            const reconnectKey = type === 'prepare' ? 'prepareOperationStreamReconnectTimer' : 'publishOperationStreamReconnectTimer';
            const statusKey = type === 'prepare' ? 'prepareOperationStatus' : 'publishOperationStatus';
            const idKey = type === 'prepare' ? 'prepareOperationId' : 'publishOperationId';
            if (!this[idKey] || !['queued', 'running'].includes(this[statusKey])) return;

            clearTimeout(this[reconnectKey]);
            this[reconnectKey] = setTimeout(() => {
                this[reconnectKey] = null;
                this._startPipelineOperationStream(type);
            }, delay);
        },

        async _handlePipelineOperationStreamMessage(type, payload = {}) {
            const sequenceKey = type === 'prepare' ? 'prepareOperationLastSequence' : 'publishOperationLastSequence';
            const statusKey = type === 'prepare' ? 'prepareOperationStatus' : 'publishOperationStatus';
            const previousStatus = this[statusKey];
            const operation = payload.operation || null;

            if (operation && !this._isStalePipelineOperationSnapshot(type, operation)) {
                this._applyPipelineOperationSnapshot(type, operation);
            }

            if (payload.kind === 'event' && payload.event) {
                const event = payload.event;
                this[sequenceKey] = Math.max(this[sequenceKey] || 0, Number(event.id || 0));
                if (type === 'prepare') {
                    this._applyPrepareOperationEvent(event);
                } else {
                    this._applyPublishOperationEvent(event);
                }
            }

            if (payload.kind === 'terminal' && operation) {
                this._stopPipelineOperationStream(type);
                this._stopPipelineOperationPoll(type);
                if (type === 'prepare') {
                    this.preparing = false;
                    if (operation.status === 'completed') {
                        this._applyPrepareOperationResult(operation, { notify: previousStatus !== 'completed' });
                    } else if (operation.status === 'failed') {
                        this._applyPrepareOperationFailure(
                            operation.error_message || operation.last_message || 'Preparation failed',
                            {
                                stage: 'prepare',
                                substage: operation.last_substage || 'failed',
                                trace_id: operation.trace_id || this.prepareTraceId || '',
                            },
                            { notify: previousStatus !== 'failed' }
                        );
                    }
                } else {
                    this.publishing = false;
                    if (operation.status === 'completed') {
                        this._applyPublishOperationResult(operation, { notify: previousStatus !== 'completed' });
                        this.autoSaveDraft();
                    } else if (operation.status === 'failed') {
                        this._applyPublishOperationFailure(operation, { notify: previousStatus !== 'failed' });
                        this.autoSaveDraft();
                    }
                }
            }
        },

        async _startPipelineOperationStream(type) {
            const idKey = type === 'prepare' ? 'prepareOperationId' : 'publishOperationId';
            const statusKey = type === 'prepare' ? 'prepareOperationStatus' : 'publishOperationStatus';
            const sequenceKey = type === 'prepare' ? 'prepareOperationLastSequence' : 'publishOperationLastSequence';
            const controllerKey = type === 'prepare' ? 'prepareOperationStreamController' : 'publishOperationStreamController';
            const streamingKey = type === 'prepare' ? '_streamingPrepareOperation' : '_streamingPublishOperation';
            const operationId = this[idKey];

            if (!operationId || !['queued', 'running'].includes(this[statusKey])) return false;
            if (!this._pipelineOperationUsesLiveStream()) return false;

            this._stopPipelineOperationStream(type);

            const controller = new AbortController();
            this[controllerKey] = controller;
            this[streamingKey] = true;

            try {
                const params = new URLSearchParams({
                    after_sequence: String(this[sequenceKey] || 0),
                    limit: '200',
                    heartbeat_ms: '1250',
                    timeout_seconds: '45',
                });
                const response = await this._rawPipelineFetch(this._pipelineOperationStreamUrl(operationId) + '?' + params.toString(), {
                    method: 'GET',
                    signal: controller.signal,
                    __skipActivityTracking: true,
                    headers: {
                        'Accept': 'application/x-ndjson',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (!response.ok || !response.body) {
                    throw new Error('Operation stream failed (HTTP ' + response.status + ')');
                }

                const reader = response.body.getReader();
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

                        const payload = JSON.parse(trimmed);
                        await this._handlePipelineOperationStreamMessage(type, payload);

                        if (payload.kind === 'timeout') {
                            this._schedulePipelineOperationStreamReconnect(type, 250);
                            return true;
                        }

                        if (payload.kind === 'terminal') {
                            return true;
                        }
                    }
                }

                if (buffer.trim()) {
                    const payload = JSON.parse(buffer.trim());
                    await this._handlePipelineOperationStreamMessage(type, payload);
                    if (payload.kind === 'terminal') return true;
                    if (payload.kind === 'timeout') {
                        this._schedulePipelineOperationStreamReconnect(type, 250);
                        return true;
                    }
                }

                if (['queued', 'running'].includes(this[statusKey])) {
                    this._schedulePipelineOperationStreamReconnect(type, 600);
                }
                return true;
            } catch (error) {
                if (controller.signal.aborted) return false;
                this._logActivity(type, 'warning', 'Operation live stream failed: ' + (error.message || 'unknown'), {
                    stage: type,
                    substage: 'stream_failed',
                });
                this._schedulePipelineOperationPoll(type, 900);
                return false;
            } finally {
                if (this[controllerKey] === controller) {
                    this[controllerKey] = null;
                }
                this[streamingKey] = false;
            }
        },

        _buildPrepareChecklist() {
            const activePhotos = this.photoSuggestions.filter(p => !p.removed && p.autoPhoto);
            const allowSyndication = this.canUsePublicationSyndication?.();
            const selectedPublicationIds = allowSyndication && Array.isArray(this.selectedSyndicationCats)
                ? this.selectedSyndicationCats.map((id) => Number(id))
                : [];
            const publicationNames = Array.isArray(this.syndicationCategories)
                ? this.syndicationCategories
                    .filter((cat) => selectedPublicationIds.includes(Number(cat.id)))
                    .map((cat) => cat.label || cat.name || ('Publication #' + cat.id))
                : selectedPublicationIds.map((id) => 'Publication #' + id);
            const photoItems = activePhotos.map((p, i) => ({
                label: 'Photo ' + (i + 1) + ': ' + (p.search_term || 'image'),
                status: 'pending',
                detail: '',
                type: 'photo',
                source: (p.autoPhoto?.source || '') + ' — ' + (() => { try { return new URL(this.resolvePhotoLargeUrl(p.autoPhoto) || this.resolvePhotoThumbUrl(p.autoPhoto) || '').hostname; } catch(e) { return ''; } })(),
                url: this.resolvePhotoLargeUrl(p.autoPhoto) || this.resolvePhotoThumbUrl(p.autoPhoto) || '',
                filename: p.suggestedFilename || this.buildFilename(p.search_term, i + 1),
                attempts: [],
            }));
            const featuredItem = {
                label: 'Featured: ' + (this.featuredImageSearch || 'image'),
                status: this.featuredPhoto ? 'pending' : 'skipped',
                detail: this.featuredPhoto ? '' : 'none selected',
                type: 'featured',
                source: this.featuredPhoto ? ((this.featuredPhoto?.source || '') + ' — ' + (() => { try { return new URL(this.resolvePhotoLargeUrl(this.featuredPhoto) || this.resolvePhotoThumbUrl(this.featuredPhoto) || '').hostname; } catch(e) { return ''; } })()) : '',
                url: this.resolvePhotoLargeUrl(this.featuredPhoto) || this.resolvePhotoThumbUrl(this.featuredPhoto) || '',
                filename: this.featuredFilename || '',
                attempts: [],
            };
            const catItems = this.selectedCategoryNames().map(c => ({
                label: c,
                status: 'pending',
                detail: '',
                type: 'category',
            }));
            const tagItems = this.selectedTagNames().map(t => ({
                label: t,
                status: 'pending',
                detail: '',
                type: 'tag',
            }));
            const publicationItems = publicationNames.map((name) => ({
                label: name,
                status: 'pending',
                detail: 'selected for syndication',
                type: 'publication',
            }));

            return [
                { label: 'Connect to WordPress & author', status: 'running', detail: (this.publishAuthor || 'default') + ' — waiting to connect...', type: 'auth' },
                { label: 'Clean HTML for WordPress', status: 'pending', detail: 'waiting to sanitize html...', type: 'html' },
                ...photoItems,
                featuredItem,
                ...catItems,
                ...publicationItems,
                ...tagItems,
                { label: 'Integrity check', status: 'pending', detail: '', type: 'integrity' },
            ];
        },

        _beginPrepareOperationSession({ announce = true, resetLog = true } = {}) {
            this.preparing = true;
            if (resetLog) this.prepareLog = [];
            this.prepareTraceId = '';
            this.prepareLastEventAt = 0;
            this.prepareLastStage = '';
            this.prepareLastMessage = '';
            this.prepareLastErrorMessage = '';
            this.prepareComplete = false;
            this.prepareIntegrityIssues = [];
            this._startPrepareWatchdog();
            if (Object.keys(this.uploadedImages).length > 0) {
                this._previousUploadedImages = { ...this.uploadedImages };
            }
            this.orphanedMedia = [];
            this.prepareChecklist = this._buildPrepareChecklist();

            if (!announce) return;

            const site = this.selectedSite || {};
            const activePhotos = this.photoSuggestions.filter(p => !p.removed && p.autoPhoto);
            this._logPrepare('info', 'Starting WordPress preparation for ' + (site.name || 'selected site') + '...');
            this._logPrepare('info', 'Site: ' + (site.url || 'unknown') + ' | Connection: ' + ((site.connection_type || '') === 'wptoolkit' ? 'WP Toolkit' : 'REST API'));
            if (this.publishAuthor) this._logPrepare('info', 'Author: ' + this.publishAuthor);
            this._logPrepare('info', 'Images: ' + activePhotos.length + ' inner + ' + (this.featuredPhoto ? '1 featured' : 'no featured'));
            this._logPrepare('info', 'Categories: ' + this.selectedCategoryNames().join(', '));
            const publicationNames = this.canUsePublicationSyndication?.() && Array.isArray(this.syndicationCategories)
                ? this.syndicationCategories
                    .filter((cat) => (this.selectedSyndicationCats || []).map((id) => Number(id)).includes(Number(cat.id)))
                    .map((cat) => cat.label || cat.name || ('Publication #' + cat.id))
                : [];
            if (publicationNames.length) this._logPrepare('info', 'Publication Syndication: ' + publicationNames.join(', '));
            this._logPrepare('info', 'Tags: ' + this.selectedTagNames().join(', '));
        },

        _mergeUploadedImageRecord(img) {
            if (!img) return;
            if (img.source_url) this.uploadedImages[img.source_url] = img;
            if (img.inline_url) this.uploadedImages[img.inline_url] = img;
            if (img.media_url) this.uploadedImages[img.media_url] = img;
            if (img.sizes) {
                Object.values(img.sizes).forEach(url => {
                    if (typeof url === 'string') this.uploadedImages[url] = img;
                });
            }
        },

        _uploadedImageIdentity(img = {}) {
            if (!img || typeof img !== 'object') return '';
            if (img.media_id) return 'media:' + String(img.media_id);
            if (img.source_url) return 'source:' + String(img.source_url);
            if (img.media_url) return 'wp:' + String(img.media_url);
            if (img.inline_url) return 'inline:' + String(img.inline_url);
            if (img.file_path) return 'file:' + String(img.file_path);
            if (img.filename) return 'name:' + String(img.filename);
            return JSON.stringify(img);
        },

        _uniqueUploadedImageRecords(records = null) {
            const source = records && typeof records === 'object' ? records : this.uploadedImages;
            const seen = new Set();
            const items = [];

            Object.values(source || {}).forEach((img) => {
                const key = this._uploadedImageIdentity(img);
                if (!key || seen.has(key)) return;
                seen.add(key);
                items.push(img);
            });

            return items.sort((a, b) => {
                const aFeatured = a?.is_featured ? 1 : 0;
                const bFeatured = b?.is_featured ? 1 : 0;
                if (aFeatured !== bFeatured) return bFeatured - aFeatured;
                return String(a?.filename || '').localeCompare(String(b?.filename || ''));
            });
        },

        get uploadedImageList() {
            return this._uniqueUploadedImageRecords();
        },

        _applyPrepareChecklistEvent(event) {
            const msg = event.message?.toLowerCase() || '';
            const rawMsg = event.message || '';
            const stage = (event.stage || '').toLowerCase();
            const substage = (event.substage || '').toLowerCase();
            const findItem = (type) => this.prepareChecklist.find(c => c.type === type);
            const findItems = (type) => this.prepareChecklist.filter(c => c.type === type);
            const authItem = findItem('auth');
            const htmlItem = findItem('html');
            const integrityItem = findItem('integrity');

            if (authItem) {
                if (stage === 'connection') {
                    if (event.type === 'error') {
                        authItem.status = 'failed';
                    } else if (event.type === 'warning') {
                        authItem.status = 'skipped';
                    } else if (event.type === 'success') {
                        authItem.status = 'done';
                    } else {
                        authItem.status = 'running';
                    }
                    if (rawMsg) authItem.detail = rawMsg;
                } else if (['html', 'media', 'taxonomy', 'integrity', 'prepare'].includes(stage) && ['pending', 'running'].includes(authItem.status)) {
                    authItem.status = 'done';
                    authItem.detail = authItem.detail || ('Connection established' + (this.publishAuthor ? ' — ' + this.publishAuthor : ''));
                }
            }

            if (htmlItem) {
                if (stage === 'html') {
                    if (event.type === 'error') {
                        htmlItem.status = 'failed';
                    } else if (event.type === 'success') {
                        htmlItem.status = 'done';
                    } else {
                        htmlItem.status = 'running';
                    }
                    if (rawMsg) htmlItem.detail = rawMsg;
                } else if (['media', 'taxonomy', 'integrity', 'prepare'].includes(stage) && ['pending', 'running'].includes(htmlItem.status)) {
                    htmlItem.status = 'done';
                    htmlItem.detail = htmlItem.detail || 'HTML cleaned for WordPress';
                }
            }

            const photoMatch = msg.match(/uploading photo (\d+)\//);
            if (photoMatch) {
                const idx = parseInt(photoMatch[1]) - 1;
                const photos = findItems('photo');
                if (photos[idx]) {
                    if (msg.includes('already on wordpress') || msg.includes('skipped')) {
                        photos[idx].status = 'done';
                        photos[idx].detail = rawMsg.replace(/^Uploading photo \d+\/\d+:\s*/i, '');
                    } else {
                        photos[idx].status = 'running';
                        if (rawMsg) photos[idx].detail = rawMsg;
                    }
                }
            }

            const skipMatch = msg.match(/photo (\d+):?\s*skipped/i);
            if (skipMatch) {
                const idx = parseInt(skipMatch[1]) - 1;
                const photos = findItems('photo');
                if (photos[idx]) { photos[idx].status = 'done'; photos[idx].detail = rawMsg; }
            }

            const attemptMatch = msg.match(/attempt (\d+):/);
            if (attemptMatch && (msg.includes('success') || msg.includes('failed'))) {
                const fi = findItem('featured');
                if (fi && fi.status === 'running') {
                    fi.attempts.push({ text: rawMsg, type: event.type });
                    if (msg.includes('success')) { fi.status = 'done'; fi.detail = rawMsg; }
                } else {
                    const photos = findItems('photo');
                    const running = photos.find(p => p.status === 'running');
                    if (running) {
                        running.attempts.push({ text: rawMsg, type: event.type });
                        if (msg.includes('success')) { running.status = 'done'; running.detail = rawMsg; }
                    }
                }
            }

            if (event.type === 'success' && msg.includes('uploaded') && msg.includes('media_id') && !msg.includes('featured') && !msg.includes('images uploaded')) {
                const photos = findItems('photo');
                const running = photos.find(p => p.status === 'running');
                if (running) { running.status = 'done'; running.detail = rawMsg; }
            }

            if (msg.includes('all') && msg.includes('attempts failed')) {
                const photos = findItems('photo');
                const running = photos.find(p => p.status === 'running');
                if (running) { running.status = 'failed'; running.detail = rawMsg; }
            }

            if (msg.includes('images uploaded') && event.type === 'success') {
                findItems('photo').forEach(p => {
                    if (p.status === 'pending' || p.status === 'running') {
                        p.status = 'done';
                        if (!p.detail) p.detail = rawMsg;
                    }
                });
            }

            if (msg.includes('featured image') || msg.includes('featured:')) {
                const fi = findItem('featured');
                if (fi) {
                    if (stage === 'media' && substage.startsWith('featured')) {
                        if (event.type === 'info') {
                            fi.status = 'running';
                        } else if (event.type === 'success') {
                            fi.status = 'done';
                        } else if (event.type === 'error') {
                            fi.status = 'failed';
                        } else if (event.type === 'warning') {
                            fi.status = msg.includes('no featured') ? 'skipped' : 'running';
                        }
                        if (rawMsg) fi.detail = rawMsg;
                    }
                    if (attemptMatch) {
                        fi.status = 'running';
                        fi.attempts.push({ text: rawMsg, type: event.type });
                    }
                    if (
                        msg.includes('featured image uploaded')
                        || (event.type === 'success' && (msg.includes('media_id=') || msg.includes('media_id:')))
                    ) {
                        fi.status = 'done';
                        fi.detail = rawMsg;
                    }
                    if (msg.includes('failed') && event.type === 'error') { fi.status = 'failed'; fi.detail = rawMsg; }
                    if (msg.includes('no featured') && event.type === 'warning') { fi.status = 'skipped'; fi.detail = rawMsg; }
                }
            }

            if (msg.match(/^(creating.*categor|categor)/)) {
                if (msg.includes('creating') && msg.includes('categor')) {
                    findItems('category').forEach(c => { if (c.status === 'pending') c.status = 'running'; });
                }
                const catTermMatch = rawMsg.match(/'([^']+)'\s*(?:—|-)\s*(ready|skipped|created|already exists)/i);
                if (catTermMatch && msg.startsWith('categor')) {
                    const catItem = findItems('category').find(c => c.label.toLowerCase() === catTermMatch[1].toLowerCase());
                    if (catItem) {
                        catItem.status = 'done';
                        catItem.detail = catTermMatch[2];
                        const idM = rawMsg.match(/id:\s*(\d+)/);
                        if (idM) catItem.detail += ' (id: ' + idM[1] + ')';
                    }
                }
                if (event.type === 'success' && msg.includes('categor') && msg.includes('ready')) {
                    findItems('category').forEach(c => { c.status = 'done'; if (!c.detail) c.detail = 'ready'; });
                }
            }

            if (msg.match(/^(creating.*tag|tags?:)/) && !msg.includes('photo') && !msg.includes('upload') && !msg.includes('hexa_')) {
                if (msg.includes('creating') && msg.includes('tag')) {
                    findItems('tag').forEach(t => { if (t.status === 'pending') t.status = 'running'; });
                }
                const tagTermMatch = rawMsg.match(/'([^']+)'\s*(?:—|-)\s*(ready|skipped|created|already exists)/i);
                if (tagTermMatch && msg.startsWith('tag')) {
                    const tagItem = findItems('tag').find(t => t.label.toLowerCase() === tagTermMatch[1].toLowerCase());
                    if (tagItem) {
                        tagItem.status = 'done';
                        tagItem.detail = tagTermMatch[2];
                        const idM = rawMsg.match(/id:\s*(\d+)/);
                        if (idM) tagItem.detail += ' (id: ' + idM[1] + ')';
                    }
                }
                if (event.type === 'success' && msg.includes('tag') && msg.includes('ready')) {
                    findItems('tag').forEach(t => { t.status = 'done'; if (!t.detail) t.detail = 'ready'; });
                }
            }

            if (integrityItem && stage === 'integrity') {
                if (event.type === 'success' || event.type === 'warning') {
                    integrityItem.status = 'done';
                } else if (event.type === 'error') {
                    integrityItem.status = 'failed';
                } else {
                    integrityItem.status = 'running';
                }
                if (rawMsg) integrityItem.detail = rawMsg;
            }

            if (event.wp_image) {
                this._mergeUploadedImageRecord(event.wp_image);
            }
        },

        _applyPrepareOperationEvent(event = {}) {
            this.prepareLastEventAt = Date.now();
            this.prepareTraceId = event.trace_id || this.prepareTraceId;
            this.prepareLastStage = [event.stage, event.substage].filter(Boolean).join('/') || this.prepareLastStage;
            this.prepareLastMessage = event.message || this.prepareLastMessage;
            if (event.type === 'error') this.prepareLastErrorMessage = event.message || this.prepareLastErrorMessage;
            this._mergeMasterActivityEntries([{ ...event, server_persisted: true }]);
            this._appendPrepareLogEntry({
                ...event,
                message: this._formatPrepareMessage(event),
            });
            this._applyPrepareChecklistEvent(event);
        },

        _applyPublishOperationEvent(event = {}) {
            this.publishTraceId = event.trace_id || this.publishTraceId;
            if (event.type === 'error' && event.message) this.publishError = event.message;
            this._mergeMasterActivityEntries([{ ...event, server_persisted: true }]);
            this._appendPrepareLogEntry(event);
        },

        _applyPrepareOperationResult(operation = {}, { notify = true } = {}) {
            const result = operation?.result_payload || {};
            if (!result || result.success === false) return;
            this._stopPipelineOperationStream('prepare');

            this.preparedHtml = result.html || this.editorContent;
            if (result.html) {
                this.spunContent = result.html;
                this.editorContent = result.html;
                this.spunWordCount = this.countWordsFromHtml(result.html);
                this.rememberDraftBody(result.html);
                this.extractArticleLinks(result.html);
                const textarea = document.getElementById('spin-preview-editor');
                if (textarea) textarea.value = result.html;
                if (typeof tinymce !== 'undefined') {
                    const editor = tinymce.get('spin-preview-editor') || tinymce.activeEditor;
                    if (editor) this._safeSetSpinEditorContent(editor, result.html, { syncState: false });
                }
            }
            this.preparedCategoryIds = result.category_ids || [];
            this.preparedTagIds = result.tag_ids || [];
            this.preparedFeaturedMediaId = result.featured_media_id || null;
            this.preparedFeaturedWpUrl = result.featured_wp_url || null;
            if (Array.isArray(result.wp_images)) {
                result.wp_images.forEach(img => this._mergeUploadedImageRecord(img));
            }
            this.prepareIntegrityIssues = result.integrity_issues || [];
            this.prepareChecklist.forEach(c => {
                if (c.status === 'pending' || c.status === 'running') c.status = 'done';
            });
            this.prepareOperationStatus = 'completed';
            this.prepareComplete = true;
            this.prepareLastErrorMessage = '';
            this.prepareLastMessage = result.message || this.prepareLastMessage;

            if (this._previousUploadedImages) {
                const currentUrls = new Set(Object.keys(this.uploadedImages));
                this.orphanedMedia = this._uniqueUploadedImageRecords(this._previousUploadedImages)
                    .filter(img => img.media_id && !currentUrls.has(img.source_url))
                    .map(img => ({ ...img, deleting: false, deleted: false }));
                if (this.orphanedMedia.length) {
                    this._appendPrepareLogEntry({
                        type: 'warning',
                        message: this.orphanedMedia.length + ' orphaned photo(s) from previous prepare',
                        trace_id: this.prepareTraceId || operation.trace_id || '',
                    });
                }
                this._previousUploadedImages = null;
            }

            if (notify) this.showNotification('success', 'Content prepared for WordPress');
            this.$nextTick(() => {
                const el = this.$refs.publishActionBox;
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },

        _applyPrepareOperationFailure(message, meta = {}, { notify = true } = {}) {
            this._stopPipelineOperationStream('prepare');
            this.prepareOperationStatus = 'failed';
            this._failPrepare(message, meta, { log: false });
            if (notify && message) this.showNotification('error', message);
        },

        _applyPublishOperationResult(operation = {}, { notify = true } = {}) {
            const result = operation?.result_payload || {};
            if (!result || result.success === false) return;
            this._stopPipelineOperationStream('publish');
            const normalizedPostUrl = this._normalizePublishedPostUrl(result.post_url, result.post_id, result.post_status);
            if (normalizedPostUrl) {
                result.post_url = normalizedPostUrl;
            }

            this.publishResult = result;
            this.publishError = '';
            if (result.links_injected) {
                this.publicationNotificationArticleLinks = result.links_injected;
            }
            if (result.post_id) {
                this.existingWpPostId = result.post_id;
                this.existingWpAdminUrl = this.selectedSite?.url
                    ? String(this.selectedSite.url).replace(/\/+$/, '') + '/wp-admin/post.php?post=' + String(result.post_id) + '&action=edit'
                    : this.existingWpAdminUrl;
            }
            if (result.post_status) {
                this.existingWpStatus = result.post_status;
            }
            if (result.post_url && ['publish', 'future'].includes(String(result.post_status || '').toLowerCase())) {
                this.existingWpPostUrl = result.post_url;
            }
            this.completeStep(7);
            this.initializePublicationNotificationState?.();
            this.hydratePublicationNotificationFields?.({ force: !this.publicationNotificationResult });
            if (notify && result.message) this.showNotification('success', result.message);
        },

        _applyPublishOperationFailure(operation = {}, { notify = true } = {}) {
            this._stopPipelineOperationStream('publish');
            const message = operation?.error_message || operation?.result_payload?.message || this.publishError || 'Publish failed';
            this.publishError = message;
            if (notify && message) this.showNotification('error', message);
        },

        openInlineMasterActivityLog({ focusReview = true, scroll = true, behavior = 'smooth' } = {}) {
            if (focusReview && this.isStepAccessible?.(7)) {
                this.currentStep = 7;
                this.openStep?.(7);
                this._syncStepToUrl?.();
            }
            this.masterActivityLogOpen = true;
            this.$nextTick(() => {
                const target = this.$refs.inlineMasterActivityLog || document.querySelector('[data-inline-master-activity-log]');
                if (scroll && target?.scrollIntoView) {
                    target.scrollIntoView({ behavior, block: 'start' });
                }
            });
        },

        publicationNotificationResolvedLinks() {
            const payload = this.publishResult?.links_injected || this.publicationNotificationArticleLinks || {};
            if (!payload || typeof payload !== 'object') {
                return { html: '', plain: '' };
            }

            const plainCandidate = payload.plain || payload.link_output || payload.html || payload.link_output_html || payload.link_output_standard || '';

            return {
                html: String(payload.html || payload.link_output_html || payload.link_output_standard || '').trim(),
                plain: this.publicationNotificationHtmlToText(plainCandidate),
            };
        },

        publicationNotificationEscapeHtml(value = '') {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        publicationNotificationHtmlToText(html = '') {
            const container = document.createElement('div');
            container.innerHTML = String(html || '')
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<\/p>/gi, '\n\n')
                .replace(/<\/div>/gi, '\n')
                .replace(/<\/h[1-6]>/gi, '\n\n')
                .replace(/<\/li>/gi, '\n');
            const raw = String(container.textContent || container.innerText || '');
            return raw
                .replace(/\u00a0/g, ' ')
                .replace(/[ \t]+\n/g, '\n')
                .replace(/\n{3,}/g, '\n\n')
                .trim();
        },

        publicationNotificationResolvedArticle() {
            const body = String(
                this._resolveCanonicalArticleHtml?.({ preferPrepared: true, hydrateState: false })
                || this.preparedHtml
                || this.editorContent
                || this.spunContent
                || ''
            ).trim();
            const title = String(this.articleTitle || '').trim();
            const header = title ? '<h1>' + this.publicationNotificationEscapeHtml(title) + '</h1>' : '';
            const full = [header, body].filter(Boolean).join('\n');

            return {
                header_html: header,
                header_plain: title,
                body_html: body,
                body_plain: this.publicationNotificationHtmlToText(body),
                html: full,
                plain: this.publicationNotificationHtmlToText(full),
            };
        },

        publicationNotificationTokenMap(extra = {}) {
            const permalink = String(this.publishResult?.post_url || this.existingWpPostUrl || '').trim();
            const publicationUrl = String(this.selectedSite?.url || '').trim();
            const publicationName = String(this.selectedSite?.name || '').trim();
            const username = String(this.selectedUser?.name || '').trim();
            const links = this.publicationNotificationResolvedLinks();
            const article = this.publicationNotificationResolvedArticle();
            const articleLinksHtml = links.html || (permalink ? '<a href="' + permalink + '">' + permalink + '</a>' : '');
            const articleLinksPlain = links.plain || permalink;
            return {
                '{permalink}': permalink,
                '{username}': username,
                '{publication_name}': publicationName,
                '{publication_url}': publicationUrl,
                '{article_title}': String(this.articleTitle || '').trim(),
                '{article}': article.html,
                '{article_plain}': article.plain,
                '{article_body}': article.body_html,
                '{article_body_plain}': article.body_plain,
                '{article_header}': article.header_html,
                '{article_header_plain}': article.header_plain,
                '{article_links}': articleLinksHtml,
                '{article_links_plain}': articleLinksPlain,
                '{press_release_links}': articleLinksHtml,
                '{press_release_links_plain}': articleLinksPlain,
                '{site_name}': publicationName,
                '{site_url}': publicationUrl,
                '{account_name}': username,
                '{campaign_name}': '',
                ...extra,
            };
        },

        renderPublicationNotificationText(template = '', tokens = null) {
            let output = String(template || '');
            const replacements = tokens || this.publicationNotificationTokenMap();
            Object.entries(replacements || {}).forEach(([code, value]) => {
                output = output.split(code).join(String(value || ''));
            });
            return output;
        },

        getPublicationNotificationTemplateById(templateId = null) {
            const target = String(templateId || this.publicationNotificationTemplateId || '').trim();
            if (!target) return null;
            return (Array.isArray(this.publicationNotificationTemplates) ? this.publicationNotificationTemplates : [])
                .find((template) => String(template.id) === target) || null;
        },

        defaultPublicationNotificationTemplate() {
            const templates = Array.isArray(this.publicationNotificationTemplates) ? this.publicationNotificationTemplates : [];
            if (this.currentArticleType === 'press-release' && this.selectedSite?.is_press_release_source) {
                const pressReleaseTemplate = templates.find((template) => String(template.name || '').toLowerCase().includes('press release'));
                if (pressReleaseTemplate) {
                    return pressReleaseTemplate;
                }
            }
            return templates.find((template) => !!template.is_primary) || templates[0] || null;
        },

        extractPublicationNotificationProfileEmail(profileId = null) {
            const resolvedProfileId = Number(profileId || this.prArticle?.main_subject_id || this.selectedPrProfiles?.[0]?.id || 0);
            if (!resolvedProfileId) return '';
            const fields = Array.isArray(this.prSubjectData?.[resolvedProfileId]?.fields) ? this.prSubjectData[resolvedProfileId].fields : [];
            const candidates = ['primary email', 'public email', 'email'];
            for (const field of fields) {
                const label = String(field?.notion_field || field?.key || '').trim().toLowerCase();
                if (!candidates.includes(label)) continue;
                const value = String(field?.display_value || field?.value || '').trim();
                if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    return value;
                }
            }
            return '';
        },

        derivePublicationNotificationRecipient() {
            const directCandidates = [
                this.pressRelease?.notion_guest?.email,
                this.pressRelease?.details?.contact_email,
                this.pressRelease?.contact_email,
            ];
            for (const candidate of directCandidates) {
                const value = String(candidate || '').trim();
                if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    return value;
                }
            }
            return this.extractPublicationNotificationProfileEmail();
        },

        initializePublicationNotificationState() {
            if (!this.publicationNotificationTemplateId) {
                const template = this.getPublicationNotificationTemplateById(this.publicationNotificationDefaults?.template_id)
                    || this.defaultPublicationNotificationTemplate();
                if (template?.id) {
                    this.publicationNotificationTemplateId = String(template.id);
                }
            }

            if (!this.publicationNotificationFromName) {
                this.publicationNotificationFromName = String(this.publicationNotificationDefaults?.from_name || 'Scale My Publication');
            }
            if (!this.publicationNotificationFromEmail) {
                this.publicationNotificationFromEmail = String(this.publicationNotificationDefaults?.from_email || 'no-reply@scalemypublication.com');
            }
            if (!this.publicationNotificationReplyTo) {
                this.publicationNotificationReplyTo = String(this.publicationNotificationDefaults?.reply_to || '');
            }
            if (!this.publicationNotificationCc) {
                this.publicationNotificationCc = String(this.publicationNotificationDefaults?.cc || '');
            }
            if (!this.publicationNotificationSubject) {
                this.publicationNotificationSubject = String(this.publicationNotificationDefaults?.subject || '');
            }
            if (!this.publicationNotificationBody) {
                this.publicationNotificationBody = String(this.publicationNotificationDefaults?.body || '');
            }
            this.hydratePublicationNotificationFields({ force: false });
        },

        hydratePublicationNotificationFields({ force = false } = {}) {
            const template = this.getPublicationNotificationTemplateById()
                || this.getPublicationNotificationTemplateById(this.publicationNotificationDefaults?.template_id)
                || this.defaultPublicationNotificationTemplate();
            if (template?.id && (!this.publicationNotificationTemplateId || force)) {
                this.publicationNotificationTemplateId = String(template.id);
            }

            const defaults = this.publicationNotificationDefaults || {};
            const tokens = this.publicationNotificationTokenMap();
            const recipient = this.derivePublicationNotificationRecipient();
            const fromName = String(template?.from_name || defaults.from_name || 'Scale My Publication');
            const fromEmail = String(template?.from_email || defaults.from_email || 'no-reply@scalemypublication.com');
            const replyTo = String(template?.reply_to || defaults.reply_to || '');
            const cc = String(template?.cc || defaults.cc || '');
            const subject = String(template?.subject || defaults.subject || 'Your article is now live on {publication_name}');
            const body = String(template?.body || defaults.body || '');

            if (force || !this.publicationNotificationFromName) {
                this.publicationNotificationFromName = this.renderPublicationNotificationText(fromName, tokens);
            }
            if (force || !this.publicationNotificationFromEmail) {
                this.publicationNotificationFromEmail = this.renderPublicationNotificationText(fromEmail, tokens);
            }
            if (force || !this.publicationNotificationReplyTo) {
                this.publicationNotificationReplyTo = this.renderPublicationNotificationText(replyTo, tokens);
            }
            if (force || !this.publicationNotificationCc) {
                this.publicationNotificationCc = this.renderPublicationNotificationText(cc, tokens);
            }
            if (force || !this.publicationNotificationTo) {
                this.publicationNotificationTo = recipient;
            }
            if (force || !this.publicationNotificationSubject) {
                this.publicationNotificationSubject = this.renderPublicationNotificationText(subject, tokens);
            }
            if (force || !this.publicationNotificationBody) {
                this.publicationNotificationBody = this.renderPublicationNotificationText(body, tokens);
            }
        },

        applyPublicationNotificationTemplate(templateId = null, { force = true } = {}) {
            if (templateId !== null) {
                this.publicationNotificationTemplateId = String(templateId || '');
            }
            this.hydratePublicationNotificationFields({ force });
            this.publicationNotificationStatus = '';
            this.publicationNotificationResult = null;
        },

        async sendPublicationNotification() {
            if (this.publicationNotificationSending) return;
            this.hydratePublicationNotificationFields({ force: false });

            if (!this.publicationNotificationTo) {
                this.showNotification('error', 'Notification recipient email is required.');
                return;
            }
            if (!this.publicationNotificationFromEmail) {
                this.showNotification('error', 'Notification from email is required.');
                return;
            }
            if (!this.publicationNotificationSubject || !this.publicationNotificationBody) {
                this.showNotification('error', 'Notification subject and body are required.');
                return;
            }

            this.publicationNotificationSending = true;
            this.publicationNotificationStatus = '';
            this.publicationNotificationResult = null;

            const payload = {
                draft_id: this.draftId,
                template_id: this.publicationNotificationTemplateId || null,
                to: this.publicationNotificationTo || '',
                from_name: this.publicationNotificationFromName || '',
                from_email: this.publicationNotificationFromEmail || '',
                reply_to: this.publicationNotificationReplyTo || '',
                cc: this.publicationNotificationCc || '',
                subject: this.publicationNotificationSubject || '',
                body: this.publicationNotificationBody || '',
            };

            try {
                const resp = await fetch('{{ route('publish.pipeline.send-publication-notification') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify(payload),
                });
                const data = await resp.json().catch(() => ({}));
                if (resp.ok && data.success) {
                    this.publicationNotificationStatus = 'success';
                    this.publicationNotificationResult = data;
                    this.showNotification('success', data.message || 'Publication notification sent.');
                } else {
                    this.publicationNotificationStatus = 'error';
                    this.publicationNotificationResult = data;
                    this.showNotification('error', data.message || 'Publication notification failed.');
                }
            } catch (error) {
                this.publicationNotificationStatus = 'error';
                this.publicationNotificationResult = { message: error.message || 'Publication notification failed.' };
                this.showNotification('error', error.message || 'Publication notification failed.');
            }

            this.publicationNotificationSending = false;
        },

        _applyPipelineOperationSnapshot(type, operation = {}) {
            if (!operation || !operation.id) return;
            if (this._isStalePipelineOperationSnapshot(type, operation)) return;

            if (type === 'prepare') {
                this.prepareOperationId = operation.id;
                this.prepareOperationStatus = operation.status || '';
                this.prepareOperationTransport = operation.transport || '';
                this.prepareOperationClientTrace = operation.client_trace || '';
                this.prepareTraceId = operation.trace_id || this.prepareTraceId;
                this.preparing = ['queued', 'running'].includes(this.prepareOperationStatus);
                if (operation.last_stage || operation.last_substage) {
                    this.prepareLastStage = [operation.last_stage, operation.last_substage].filter(Boolean).join('/');
                }
                if (operation.last_message) {
                    this.prepareLastMessage = operation.last_message;
                }
                const authItem = this.prepareChecklist.find(item => item.type === 'auth');
                if (authItem && ['queued', 'running'].includes(this.prepareOperationStatus) && (!this.prepareLastStage || this.prepareLastStage.startsWith('connection'))) {
                    authItem.status = 'running';
                    authItem.detail = this.prepareOperationStatus === 'queued'
                        ? ((this.publishAuthor || 'default') + ' — queued to connect...')
                        : ((this.publishAuthor || 'default') + ' — connecting to server...');
                }
                return;
            }

            this.publishOperationId = operation.id;
            this.publishOperationStatus = operation.status || '';
            this.publishOperationTransport = operation.transport || '';
            this.publishOperationClientTrace = operation.client_trace || '';
            this.publishTraceId = operation.trace_id || this.publishTraceId;
        },

        _stopPipelineOperationPoll(type) {
            const timerKey = type === 'prepare' ? 'prepareOperationPollTimer' : 'publishOperationPollTimer';
            const pollingKey = type === 'prepare' ? '_pollingPrepareOperation' : '_pollingPublishOperation';
            if (this[timerKey]) {
                clearTimeout(this[timerKey]);
                this[timerKey] = null;
            }
            this[pollingKey] = false;
            if (type === 'prepare') this._stopPrepareWatchdog();
        },

        _schedulePipelineOperationPoll(type, delay = 1500) {
            const timerKey = type === 'prepare' ? 'prepareOperationPollTimer' : 'publishOperationPollTimer';
            const idKey = type === 'prepare' ? 'prepareOperationId' : 'publishOperationId';
            const statusKey = type === 'prepare' ? 'prepareOperationStatus' : 'publishOperationStatus';
            const streamingKey = type === 'prepare' ? '_streamingPrepareOperation' : '_streamingPublishOperation';
            if (!this[idKey] || !['queued', 'running'].includes(this[statusKey])) return;
            if (this[streamingKey]) return;

            clearTimeout(this[timerKey]);
            this[timerKey] = setTimeout(() => {
                this[timerKey] = null;
                this._pollPipelineOperation(type);
            }, delay);
        },

        async _pollPipelineOperation(type, { force = false } = {}) {
            const idKey = type === 'prepare' ? 'prepareOperationId' : 'publishOperationId';
            const statusKey = type === 'prepare' ? 'prepareOperationStatus' : 'publishOperationStatus';
            const sequenceKey = type === 'prepare' ? 'prepareOperationLastSequence' : 'publishOperationLastSequence';
            const pollingKey = type === 'prepare' ? '_pollingPrepareOperation' : '_pollingPublishOperation';
            const streamingKey = type === 'prepare' ? '_streamingPrepareOperation' : '_streamingPublishOperation';
            const operationId = this[idKey];
            if (!operationId || this[pollingKey]) return;
            if (!force && this[streamingKey]) return;

            this[pollingKey] = true;
            try {
                const route = '{{ route("publish.pipeline.operations.show", ["operation" => "__OP__"]) }}'.replace('__OP__', encodeURIComponent(String(operationId)));
                const params = new URLSearchParams({
                    after_sequence: String(this[sequenceKey] || 0),
                    limit: '200',
                });
                const response = await this._rawPipelineFetch(route + '?' + params.toString(), {
                    method: 'GET',
                    __skipActivityTracking: true,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.success || !data.operation) {
                    throw new Error(data.message || ('Operation poll failed (HTTP ' + response.status + ')'));
                }

                const previousStatus = this[statusKey];
                if (this._isStalePipelineOperationSnapshot(type, data.operation)) {
                    return;
                }
                this._applyPipelineOperationSnapshot(type, data.operation);
                this._normalizePipelineOperationEvents(data.events).forEach(event => {
                    this[sequenceKey] = Math.max(this[sequenceKey] || 0, Number(event.id || 0));
                    if (type === 'prepare') {
                        this._applyPrepareOperationEvent(event);
                    } else {
                        this._applyPublishOperationEvent(event);
                    }
                });

                if (type === 'prepare') {
                    if (data.operation.status === 'completed') {
                        this.preparing = false;
                        this._stopPipelineOperationPoll('prepare');
                        this._stopPipelineOperationStream('prepare');
                        this._applyPrepareOperationResult(data.operation, { notify: previousStatus !== 'completed' });
                        return;
                    }
                    if (data.operation.status === 'failed') {
                        this._stopPipelineOperationPoll('prepare');
                        this._stopPipelineOperationStream('prepare');
                        this._applyPrepareOperationFailure(
                            data.operation.error_message || data.operation.last_message || 'Preparation failed',
                            {
                                stage: 'prepare',
                                substage: data.operation.last_substage || 'failed',
                                trace_id: data.operation.trace_id || this.prepareTraceId || '',
                            },
                            { notify: previousStatus !== 'failed' }
                        );
                        return;
                    }
                } else {
                    if (data.operation.status === 'completed') {
                        this.publishing = false;
                        this._stopPipelineOperationPoll('publish');
                        this._stopPipelineOperationStream('publish');
                        this._applyPublishOperationResult(data.operation, { notify: previousStatus !== 'completed' });
                        this.autoSaveDraft();
                        return;
                    }
                    if (data.operation.status === 'failed') {
                        this.publishing = false;
                        this._stopPipelineOperationPoll('publish');
                        this._stopPipelineOperationStream('publish');
                        this._applyPublishOperationFailure(data.operation, { notify: previousStatus !== 'failed' });
                        this.autoSaveDraft();
                        return;
                    }
                }

                this._schedulePipelineOperationPoll(type, 1500);
            } catch (error) {
                this._logActivity(type, 'warning', 'Operation status poll failed: ' + (error.message || 'unknown'), {
                    stage: type,
                    substage: 'poll_failed',
                });
                this._schedulePipelineOperationPoll(type, 2500);
            } finally {
                this[pollingKey] = false;
            }
        },

        async _restoreLatestPipelineOperation(type) {
            if (!this.draftId) return;

            const params = new URLSearchParams({
                draft_id: String(this.draftId),
                operation_type: type,
                after_sequence: '0',
                limit: '200',
            });

            try {
                const response = await this._rawPipelineFetch('{{ route("publish.pipeline.operations.latest") }}?' + params.toString(), {
                    method: 'GET',
                    __skipActivityTracking: true,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success || !data.operation) return;
                if (this._isStalePipelineOperationSnapshot(type, data.operation)) return;

                if (type === 'prepare') {
                    if (!this.prepareChecklist.length) {
                        this._beginPrepareOperationSession({ announce: false, resetLog: false });
                    }
                    this.preparing = ['queued', 'running'].includes(data.operation.status);
                }

                this._applyPipelineOperationSnapshot(type, data.operation);
                this._normalizePipelineOperationEvents(data.events).forEach(event => {
                    if (type === 'prepare') {
                        this.prepareOperationLastSequence = Math.max(this.prepareOperationLastSequence || 0, Number(event.id || 0));
                        this._applyPrepareOperationEvent(event);
                    } else {
                        this.publishOperationLastSequence = Math.max(this.publishOperationLastSequence || 0, Number(event.id || 0));
                        this._applyPublishOperationEvent(event);
                    }
                });

                if (type === 'prepare') {
                    if (data.operation.status === 'completed') {
                        this.preparing = false;
                        this._stopPipelineOperationPoll('prepare');
                        this._stopPipelineOperationStream('prepare');
                        this._applyPrepareOperationResult(data.operation, { notify: false });
                    } else if (data.operation.status === 'failed') {
                        this._stopPipelineOperationPoll('prepare');
                        this._stopPipelineOperationStream('prepare');
                        this._applyPrepareOperationFailure(data.operation.error_message || data.operation.last_message || 'Preparation failed', {
                            stage: 'prepare',
                            substage: data.operation.last_substage || 'failed',
                            trace_id: data.operation.trace_id || '',
                        }, { notify: false });
                    } else {
                        const streaming = await this._startPipelineOperationStream('prepare');
                        if (!streaming) {
                            this._pollPipelineOperation('prepare');
                            this._schedulePipelineOperationPoll('prepare', 800);
                        }
                    }
                    return;
                }

                if (data.operation.status === 'completed') {
                    this.publishing = false;
                    this._stopPipelineOperationPoll('publish');
                    this._stopPipelineOperationStream('publish');
                    this._applyPublishOperationResult(data.operation, { notify: false });
                } else if (data.operation.status === 'failed') {
                    this.publishing = false;
                    this._stopPipelineOperationPoll('publish');
                    this._stopPipelineOperationStream('publish');
                    this._applyPublishOperationFailure(data.operation, { notify: false });
                } else {
                    this.publishing = true;
                    const streaming = await this._startPipelineOperationStream('publish');
                    if (!streaming) {
                        this._schedulePipelineOperationPoll('publish', 800);
                    }
                }
            } catch (error) {}
        },

        shouldRestorePipelineOperations(force = false) {
            if (!this.draftId) return false;
            if (force) return true;

            return this.currentStep === 7
                || (Array.isArray(this.openSteps) && this.openSteps.includes(7))
                || ['queued', 'running'].includes(this.prepareOperationStatus)
                || ['queued', 'running'].includes(this.publishOperationStatus)
                || !!this.preparing
                || !!this.publishing;
        },

        _focusPublishActionBox({ behavior = 'smooth' } = {}) {
            this.$nextTick(() => {
                const isStepSevenOpen = this.currentStep === 7
                    && Array.isArray(this.openSteps)
                    && this.openSteps.includes(7);

                if (!isStepSevenOpen) return;

                const el = this.$refs.publishActionBox;
                if (el) {
                    el.scrollIntoView({ behavior, block: 'start' });
                }
            });
        },

        async _restorePipelineOperations(force = false) {
            if (!this.shouldRestorePipelineOperations(force)) return;
            if (this._pipelineOperationsRestored && !force) return;

            this._pipelineOperationsRestored = true;
            await this._restoreLatestPipelineOperation('prepare');
            await this._restoreLatestPipelineOperation('publish');
        },

        _buildPrepareRequestPayload() {
            const html = this._resolveCanonicalArticleHtml({ preferPrepared: false, hydrateState: true });
            const publicationTermIds = this.canUsePublicationSyndication?.()
                ? (Array.isArray(this.selectedSyndicationCats) ? this.selectedSyndicationCats.map((id) => Number(id)) : [])
                : [];
            return {
                html,
                title: this.articleTitle || 'article',
                site_id: this.selectedSite?.id || null,
                categories: this.selectedCategoryNames(),
                publication_term_ids: publicationTermIds,
                tags: this.selectedTagNames(),
                draft_id: this.draftId,
                article_type: this.template_overrides?.article_type || this.currentArticleType || null,
                photo_meta: this.photoSuggestions.filter(p => !p.removed && p.autoPhoto).map((p, i) => ({
                    alt_text: p.alt_text || '',
                    caption: p.caption || '',
                    filename: (p.search_term || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 60),
                })),
                featured_url: this.resolvePhotoLargeUrl(this.featuredPhoto) || this.resolvePhotoThumbUrl(this.featuredPhoto) || this.featuredPhoto?.url || null,
                existing_featured_media_id: this.preparedFeaturedMediaId || null,
                featured_meta: this.featuredPhoto ? {
                    alt_text: this.featuredAlt || '',
                    caption: this.featuredCaption || '',
                    filename: (this.featuredImageSearch || 'featured').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 60),
                } : null,
                existing_uploads: this.uploadedImageList,
            };
        },

        _buildPublishRequestPayload(wpStatus) {
            const html = this._resolveCanonicalArticleHtml({ preferPrepared: true, hydrateState: true });
            const publicationTermIds = this.canUsePublicationSyndication?.()
                ? (Array.isArray(this.selectedSyndicationCats) ? this.selectedSyndicationCats.map((id) => Number(id)) : [])
                : [];
            return {
                html,
                title: this.articleTitle || 'Untitled',
                site_id: this.selectedSite?.id || null,
                category_ids: this.preparedCategoryIds,
                tag_ids: this.preparedTagIds,
                publication_term_ids: publicationTermIds,
                featured_media_id: this.preparedFeaturedMediaId || null,
                status: wpStatus,
                date: this.publishAction === 'future' ? this.scheduleDate : null,
                draft_id: this.draftId,
                existing_post_id: this.existingWpPostId || null,
                categories: this.selectedCategoryNames(),
                tags: this.selectedTagNames(),
                wp_images: this.uploadedImageList,
                word_count: this.spunWordCount,
                ai_model: this.aiModel,
                ai_cost: this.lastAiCall?.cost || null,
                ai_provider: this.lastAiCall?.provider || null,
                ai_tokens_input: this.lastAiCall?.usage?.input_tokens || null,
                ai_tokens_output: this.lastAiCall?.usage?.output_tokens || null,
                resolved_prompt: this.resolvedPrompt || null,
                photo_suggestions: this.sanitizePhotoSuggestionsForPersistence() || null,
                featured_image_search: this.featuredImageSearch || null,
                author: this.publishAuthor || null,
                sources: this.sources.map(s => ({ url: s.url, title: s.title })),
                template_id: this.selectedTemplateId || null,
                preset_id: this.selectedPresetId || null,
                user_id: this.selectedUser?.id || null,
                article_type: this.template_overrides?.article_type || this.currentArticleType || null,
            };
        },

        _logPrepare(type, message, meta = {}) {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.prepareLog.push({
                type,
                message,
                time,
                stage: meta.stage || '',
                substage: meta.substage || '',
                trace_id: meta.trace_id || this.prepareTraceId || '',
                duration_ms: Number.isFinite(Number(meta.duration_ms)) ? Number(meta.duration_ms) : null,
                sequence_no: meta.sequence_no ?? null,
            });
            this._logActivity(meta.scope || ((meta.stage || '').startsWith('publish') ? 'publish' : 'prepare'), type, message, {
                ...meta,
                duration_ms: Number.isFinite(Number(meta.duration_ms)) ? Number(meta.duration_ms) : null,
                debug_only: type === 'step' && !this.pipelineDebugEnabled,
            });
            this.$nextTick(() => {
                const el = this.$refs.prepareLogContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        _humanizePipelineLabel(value, fallback = '') {
            const text = String(value || fallback || '').trim();
            if (!text) return '';
            return text.replace(/\//g, ' / ').replace(/_/g, ' ');
        },

        _normalizePublishedPostUrl(value, postId = null, postStatus = '') {
            const text = String(value || '').trim();
            const matches = text.match(/https?:\/\/[^\s]+/g) || [];
            if (matches.length > 0) return matches[matches.length - 1];
            const normalizedStatus = String(postStatus || '').toLowerCase();
            if (postId && this.selectedSite?.url && ['publish', 'future'].includes(normalizedStatus)) {
                return String(this.selectedSite.url).replace(/\/+$/, '') + '/?p=' + String(postId);
            }
            return text || null;
        },

        _formatPrepareMessage(event) {
            const stageParts = [event.stage, event.substage].filter(Boolean);
            const prefix = stageParts.length ? '[' + stageParts.join('/') + '] ' : '';
            return prefix + (event.message || '');
        },

        _startPrepareWatchdog() {
            this._stopPrepareWatchdog();
            this.prepareLastEventAt = Date.now();
            this._lastPrepareWatchdogNoticeAt = 0;
            this.prepareWatchdogTimer = setInterval(() => {
                if (!this._prepareOperationIsActive()) return;
                if (['completed', 'failed'].includes(this.prepareOperationStatus)) {
                    this.preparing = false;
                    this._stopPrepareWatchdog();
                    return;
                }
                const idleMs = Date.now() - (this.prepareLastEventAt || Date.now());
                if (idleMs < 25000) return;
                if (this.prepareOperationId && !this._pollingPrepareOperation) {
                    this._pollPipelineOperation('prepare', { force: true });
                }
                if (this._lastPrepareWatchdogNoticeAt && (Date.now() - this._lastPrepareWatchdogNoticeAt) < 20000) return;
                this._lastPrepareWatchdogNoticeAt = Date.now();
                const humanStage = this._humanizePipelineLabel(
                    this.prepareLastStage,
                    this.prepareOperationStatus === 'queued' ? 'queue' : 'prepare startup'
                );
                const currentMessage = this.prepareLastMessage ? (' — ' + this.prepareLastMessage) : '';
                const idleMessage = this.prepareLastStage || this.prepareLastMessage
                    ? ('Still working in ' + humanStage + ' for ' + Math.round(idleMs / 1000) + 's' + currentMessage)
                    : ('Waiting for prepare progress from the server for ' + Math.round(idleMs / 1000) + 's');
                this._logPrepare('warning', idleMessage, {
                    stage: 'stream',
                    substage: 'idle',
                    trace_id: this.prepareTraceId || '',
                });
            }, 5000);
        },

        _stopPrepareWatchdog() {
            if (this.prepareWatchdogTimer) {
                clearInterval(this.prepareWatchdogTimer);
                this.prepareWatchdogTimer = null;
            }
        },

        _failPrepare(message, meta = {}, { log = true } = {}) {
            this.prepareComplete = false;
            this.preparing = false;
            this._stopPrepareWatchdog();
            if (message) this.prepareLastErrorMessage = message;
                this.prepareChecklist.forEach(item => {
                    if (item.status === 'running' || item.status === 'pending') {
                        item.status = 'failed';
                    if (!item.detail && message) item.detail = message;
                }
            });
            if (message && log) this._logPrepare('error', message, meta);
        },

        async prepareForWp() {
            if (!this.selectedSite) {
                this.showNotification('error', 'No WordPress site selected');
                return;
            }
            this.syncEditorStateFromEditor();
            this._resetPrepareOperationTracking({ clearLog: true });
            this._beginPrepareOperationSession({ announce: true, resetLog: true });

            try {
                const resp = await fetch('{{ route('publish.pipeline.prepare') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({
                        'Content-Type': 'application/json',
                        'X-Pipeline-Client-Trace': this._buildClientTrace('prepare'),
                    }),
                    body: JSON.stringify(this._buildPrepareRequestPayload())
                });
                const data = await resp.json().catch(() => ({}));
                if (resp.ok && (!data || !data.operation) && resp.status === 202) {
                    await this._restoreLatestPipelineOperation('prepare');
                    if (this.prepareOperationId) {
                        this._appendPrepareLogEntry({
                            type: 'info',
                            message: 'Recovered active prepare operation after accepted response.',
                            stage: 'prepare',
                            substage: 'recovered',
                            trace_id: this.prepareTraceId || '',
                        });
                        return;
                    }
                }
                if (!resp.ok || !data.success || !data.operation) {
                    const errMsg = data.message || ('Prepare failed (HTTP ' + resp.status + ')');
                    this._failPrepare(errMsg, { stage: 'prepare', substage: 'start_failed' });
                    this.showNotification('error', errMsg);
                    return;
                }

                this._applyPipelineOperationSnapshot('prepare', data.operation);
                if (data.operation.status === 'completed') {
                    this.preparing = false;
                    this._stopPipelineOperationPoll('prepare');
                    this._stopPipelineOperationStream('prepare');
                    this._applyPrepareOperationResult(data.operation, { notify: true });
                    return;
                }
                if (data.operation.status === 'failed') {
                    this._stopPipelineOperationPoll('prepare');
                    this._stopPipelineOperationStream('prepare');
                    this._applyPrepareOperationFailure(data.operation.error_message || data.operation.last_message || 'Preparation failed', {
                        stage: 'prepare',
                        substage: data.operation.last_substage || 'failed',
                        trace_id: data.operation.trace_id || '',
                    }, { notify: true });
                    return;
                }

                this._appendPrepareLogEntry({
                    type: 'info',
                    message: 'Prepare operation ' + (data.operation.transport === 'queue' ? 'queued' : 'started') + ' — trace ' + (data.operation.trace_id || ''),
                    trace_id: data.operation.trace_id || '',
                    stage: 'prepare',
                    substage: data.operation.transport === 'queue' ? 'queued' : 'started',
                });
                const streaming = await this._startPipelineOperationStream('prepare');
                if (!streaming) {
                    this._pollPipelineOperation('prepare');
                    this._schedulePipelineOperationPoll('prepare', 400);
                }
            } catch (e) {
                const errMsg = 'Connection error: ' + (e.message || 'Request failed');
                this._failPrepare(errMsg, { stage: 'prepare', substage: 'request_failed', trace_id: this.prepareTraceId || '' });
                this.showNotification('error', errMsg);
            }
        },

        // ── Orphan Cleanup ──────────────────────────────
        async deleteOrphanedMedia(idx) {
            const media = this.orphanedMedia[idx];
            if (!media || media.deleting || media.deleted) return;
            if (!this.selectedSite?.id) {
                this.showNotification('error', 'No WordPress site selected.');
                return;
            }
            this.orphanedMedia[idx].deleting = true;
            try {
                const resp = await fetch('{{ route("publish.pipeline.delete-media") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ site_id: this.selectedSite?.id || null, media_id: media.media_id })
                });
                const data = await resp.json();
                if (data.success) {
                    this.orphanedMedia[idx].deleted = true;
                    this.showNotification('success', 'Deleted media #' + media.media_id);
                } else {
                    this.showNotification('error', 'Delete failed: ' + (data.message || 'unknown'));
                }
            } catch (e) {
                this.showNotification('error', 'Delete error: ' + e.message);
            }
            this.orphanedMedia[idx].deleting = false;
        },

        // ── Step 10: Publish ──────────────────────────────
        async publishArticle() {
            this.syncEditorStateFromEditor();
            this.publishing = true;
            this._resetPublishOperationTracking();
            this.publishResult = null;
            this.publishError = '';
            this.publishTraceId = '';
            this.publicationNotificationStatus = '';
            this.publicationNotificationResult = null;
            this._logActivity('publish', 'info', 'Publish requested', {
                stage: 'publish',
                substage: 'start',
                details: this._summarizeValue({
                    action: this.publishAction,
                    site_id: this.selectedSite?.id || null,
                    prepare_complete: this.prepareComplete,
                }, 500),
            });

            // Save local draft only
            if (this.publishAction === 'draft_local') {
                await this.saveDraftNow();
                this.publishResult = { message: 'Saved as local draft.', post_url: null, draft_id: this.draftId };
                this.completeStep(7);
                this.publishing = false;
                return;
            }

            // WP publish
            if (!this.selectedSite) {
                this.publishError = 'No WordPress site selected.';
                this.publishing = false;
                return;
            }

            const wpStatus = this.publishAction === 'future' ? 'future' : (this.publishAction === 'draft_wp' ? 'draft' : 'publish');

            // Warn if featured image was not uploaded
            if (this.featuredPhoto && !this.preparedFeaturedMediaId) {
                this.showNotification('warning', 'Featured image was not uploaded during prepare — post will have no featured image. Re-prepare to fix.');
                this._logPrepare('warning', 'Featured image not uploaded — publishing without featured image');
            }

            this._logPrepare('step', 'Starting publish to ' + (this.selectedSite?.name || 'selected site') + '...');

            try {
                const resp = await fetch('{{ route('publish.pipeline.publish') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({
                        'Content-Type': 'application/json',
                        'X-Pipeline-Client-Trace': this._buildClientTrace('publish'),
                    }),
                    body: JSON.stringify(this._buildPublishRequestPayload(wpStatus))
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.success || !data.operation) {
                    const errMsg = data.message || ('Publish failed (HTTP ' + resp.status + ')');
                    this.publishError = errMsg;
                    this._logPrepare('error', errMsg, { scope: 'publish', stage: 'publish', substage: 'start_failed' });
                    this.showNotification('error', errMsg);
                    this.publishing = false;
                    return;
                }

                this._applyPipelineOperationSnapshot('publish', data.operation);
                if (data.operation.status === 'completed') {
                    this.publishing = false;
                    this._stopPipelineOperationPoll('publish');
                    this._stopPipelineOperationStream('publish');
                    this._applyPublishOperationResult(data.operation, { notify: true });
                    this.autoSaveDraft();
                    return;
                }
                if (data.operation.status === 'failed') {
                    this.publishing = false;
                    this._stopPipelineOperationPoll('publish');
                    this._stopPipelineOperationStream('publish');
                    this._applyPublishOperationFailure(data.operation, { notify: true });
                    this.autoSaveDraft();
                    return;
                }

                this._appendPrepareLogEntry({
                    type: 'info',
                    message: 'Publish operation ' + (data.operation.transport === 'queue' ? 'queued' : 'started') + ' — trace ' + (data.operation.trace_id || ''),
                    trace_id: data.operation.trace_id || '',
                    stage: 'publish',
                    substage: data.operation.transport === 'queue' ? 'queued' : 'started',
                });
                const streaming = await this._startPipelineOperationStream('publish');
                if (!streaming) {
                    this._schedulePipelineOperationPoll('publish', 400);
                }
            } catch (e) {
                this._logPrepare('error', 'Connection error: ' + (e.message || 'Request failed'), {
                    scope: 'publish',
                    stage: 'publish',
                    substage: 'request_failed',
                    trace_id: this.publishTraceId || '',
                });
                this.publishError = 'Network error during publishing.';
                this.publishing = false;
            }
        },
