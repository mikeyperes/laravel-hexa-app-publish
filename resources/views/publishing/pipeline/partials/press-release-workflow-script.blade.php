<script>
function pressReleaseWorkflowMixin(config) {
    const clone = (value) => {
        try { return JSON.parse(JSON.stringify(value)); } catch (e) { return value; }
    };

    return {
        workflowDefinitions: config.workflowDefinitions || {},
        pipelinePayload: config.pipelinePayload || {},
        pressReleaseDefaultState: clone(config.pressReleaseDefaultState || {}),
        pressRelease: clone(config.pressReleaseDefaultState || {}),
        pressReleaseUploadingDocuments: false,
        pressReleaseDetectingContent: false,
        pressReleaseDetectingFields: false,
        pressReleaseDetectingPhotos: false,
        pressReleaseFetchingDrivePhotos: false,
        _serverPipelineStateTimer: null,
        _savingPipelineStateServer: false,

        normalizePressReleaseState(state = {}) {
            const defaults = clone(this.pressReleaseDefaultState || {});
            const normalized = {
                ...defaults,
                ...clone(state || {}),
                details: {
                    ...(defaults.details || {}),
                    ...clone((state || {}).details || {}),
                },
            };

            normalized.document_files = Array.isArray(normalized.document_files) ? normalized.document_files : [];
            normalized.photo_files = Array.isArray(normalized.photo_files) ? normalized.photo_files : [];
            normalized.detected_photos = Array.isArray(normalized.detected_photos) ? normalized.detected_photos : [];
            normalized.activity_log = Array.isArray(normalized.activity_log) ? normalized.activity_log : [];
            normalized.content_detect_log = Array.isArray(normalized.content_detect_log) ? normalized.content_detect_log : [];
            normalized.photo_detect_log = Array.isArray(normalized.photo_detect_log) ? normalized.photo_detect_log : [];
            normalized.detected_content = normalized.detected_content || '';
            normalized.detected_content_html = normalized.detected_content_html || '';
            normalized.detected_word_count = normalized.detected_word_count || 0;
            normalized.selected_photo_keys = normalized.selected_photo_keys || {};
            normalized.polish_only = !!normalized.polish_only;

            return normalized;
        },

        currentWorkflowKey() {
            return this.currentArticleType === 'press-release'
                ? 'press-release'
                : (this.isGenerateMode ? 'generate' : 'default');
        },

        currentStepLabels() {
            const key = this.currentWorkflowKey();
            const definitions = this.workflowDefinitions || {};
            if (key === 'press-release') {
                const definition = definitions['press-release'] || {};
                return this.pressRelease.polish_only
                    ? (definition.step_labels_polish || definition.step_labels || [])
                    : (definition.step_labels || []);
            }

            return (definitions[key] || definitions.default || {}).step_labels || [];
        },

        currentPressReleasePromptSlug() {
            if (this.currentArticleType !== 'press-release') {
                return null;
            }

            return this.pressRelease.polish_only ? 'press-release-polish' : 'press-release-spin';
        },

        restorePressReleaseStateFromLegacy(state = {}) {
            const legacy = {
                content_dump: state.pressReleaseContent || '',
                details: {
                    date: state.pressReleaseDate || '',
                    location: state.pressReleaseLocation || '',
                    contact: state.pressReleaseContact || '',
                    contact_url: state.pressReleaseContactUrl || '',
                },
            };

            return this.normalizePressReleaseState({
                ...legacy,
                ...(state.pressRelease || {}),
            });
        },

        buildPressReleaseSourceText(usePlaceholder = false) {
            const blocks = [];
            const sourceText = (this.pressRelease.resolved_source_text || this.pressRelease.content_dump || '').trim();
            if (sourceText) {
                blocks.push("=== Submitted Source Material ===\n" + sourceText);
            } else if (usePlaceholder) {
                blocks.push('[Press release source material will be inserted here]');
            }

            const details = this.pressRelease.details || {};
            const detailLines = [];
            if (details.date) detailLines.push('Date: ' + details.date);
            if (details.location) detailLines.push('Location: ' + details.location);
            if (details.contact) detailLines.push('Contact: ' + details.contact);
            if (details.contact_url) detailLines.push('Contact URL: ' + details.contact_url);
            if (detailLines.length) {
                blocks.push("=== Validated Details ===\n" + detailLines.join("\n"));
            }

            if (this.pressRelease.google_drive_url) {
                blocks.push("=== Photo Source Reference ===\nGoogle Drive URL: " + this.pressRelease.google_drive_url);
            }

            return blocks.join("\n\n").trim();
        },

        hasPressReleaseSubmittedContent() {
            return !!(
                (this.pressRelease.content_dump || '').trim() ||
                (this.pressRelease.public_url || '').trim() ||
                (this.pressRelease.document_files || []).length
            );
        },

        hasPressReleaseValidationData() {
            const details = this.pressRelease.details || {};
            return !!(
                (this.pressRelease.resolved_source_preview || '').trim() ||
                (details.date || '').trim() ||
                (details.location || '').trim() ||
                (details.contact || '').trim() ||
                (details.contact_url || '').trim()
            );
        },

        setPressReleaseSubmitMethod(method) {
            this.pressRelease.submit_method = method;
            if (method !== 'detect-from-public-url' && !this.pressRelease.photo_public_url && this.pressRelease.public_url) {
                this.pressRelease.photo_public_url = this.pressRelease.public_url;
            }
            this.savePipelineState();
            this.invalidatePromptPreview('press_release_submit_method');
        },

        continuePressReleaseStep3() {
            const method = this.pressRelease.submit_method;
            if (method === 'content-dump' && !this.pressRelease.content_dump.trim()) {
                this.showNotification('error', 'Paste the press release content before continuing.');
                return;
            }
            if (method === 'upload-documents' && this.pressRelease.document_files.length === 0) {
                this.showNotification('error', 'Upload at least one press release document before continuing.');
                return;
            }
            if (method === 'public-url' && !this.pressRelease.public_url.trim()) {
                this.showNotification('error', 'Enter a public press release URL before continuing.');
                return;
            }
            if (method === 'public-url' && !this.pressRelease.photo_public_url) {
                this.pressRelease.photo_public_url = this.pressRelease.public_url;
            }

            this.completeStep(3);
            this.openStep(4);
            this.savePipelineState();
        },

        continuePressReleaseStep4() {
            this.completeStep(4);
            this.openStep(5);
            this.invalidatePromptPreview('press_release_step4_continue', { fetch: true });
            this.savePipelineState();
        },

        async uploadPressReleaseDocuments(files) {
            if (!files || files.length === 0) return;

            this.pressReleaseUploadingDocuments = true;
            const form = new FormData();
            form.append('draft_id', this.draftId);
            Array.from(files).forEach((file) => form.append('documents[]', file));

            try {
                const response = await fetch('{{ route("publish.pipeline.press-release.upload-documents") }}', {
                    method: 'POST',
                    headers: this.requestHeaders(),
                    body: form,
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Document upload failed.');
                }

                this.pressRelease.document_files = data.documents || [];
                this.savePipelineState();
                this.showNotification('success', data.message || 'Documents uploaded.');
            } catch (error) {
                this.showNotification('error', error.message || 'Document upload failed.');
            }

            this.pressReleaseUploadingDocuments = false;
        },

        async refreshPressReleasePhotoFiles() {
            try {
                const response = await fetch('/upload-portal/files?context=press-release-photo&context_id=' + this.draftId, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to load uploaded photos.');
                }

                this.pressRelease.photo_files = data.files || [];
                this.savePipelineState();
                this.showNotification('success', 'Uploaded press release photos refreshed.');
            } catch (error) {
                this.showNotification('error', error.message || 'Failed to load uploaded photos.');
            }
        },

        async detectPressReleaseFields() {
            this.pressReleaseDetectingFields = true;

            try {
                const response = await fetch('{{ route("publish.pipeline.press-release.detect-fields") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        model: this.aiModel || 'claude-sonnet-4-20250514',
                    }),
                });
                const data = await response.json();
                if (data.press_release) {
                    const normalized = this.normalizePressReleaseState(data.press_release);
                    Object.assign(this.pressRelease, normalized);
                    this.savePipelineState();
                    this.invalidatePromptPreview('press_release_fields_detected', { fetch: true });
                }
                if (!response.ok && !data.press_release) {
                    throw new Error(data.message || 'Field detection failed.');
                }
                this.showNotification(data.success ? 'success' : 'error', data.message || 'Field detection completed.');
            } catch (error) {
                this.showNotification('error', error.message || 'Field detection failed.');
            }

            this.pressReleaseDetectingFields = false;
        },

        async detectPressReleasePhotos() {
            this.pressReleaseDetectingPhotos = true;
            if (!this.pressRelease.photo_detect_log) this.pressRelease.photo_detect_log = [];
            this.pressRelease.photo_detect_log = [];

            const now = () => new Date().toLocaleTimeString('en-US', { hour12: false });
            const log = (type, message) => {
                this.pressRelease.photo_detect_log.push({ type, message, time: now() });
                if (typeof this._logActivity === 'function') {
                    this._logActivity('press_release', type, message, { debug_only: type === 'step' });
                }
            };

            const url = this.pressRelease.photo_public_url || this.pressRelease.public_url || '';
            log('info', 'Starting photo detection...');
            log('step', 'URL: ' + (url || '(none — using submit URL)'));

            try {
                log('info', 'Sending detection request to server...');
                const response = await fetch('{{ route("publish.pipeline.press-release.detect-photos") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ draft_id: this.draftId }),
                });
                const data = await response.json();

                if (data.press_release) {
                    const photoCount = (data.press_release.detected_photos || []).length;
                    log('success', 'Detection complete. Found ' + photoCount + ' photo(s).');

                    (data.press_release.detected_photos || []).forEach((photo, i) => {
                        log('step', (i + 1) + '. ' + (photo.alt_text || photo.caption || photo.url || 'Photo ' + (i + 1)));
                    });

                    // Update detected photos explicitly for Alpine reactivity
                    const savedLog = [...this.pressRelease.photo_detect_log];
                    const photos = data.press_release.detected_photos || [];
                    this.pressRelease.detected_photos = [...photos];
                    this.pressRelease.selected_photo_keys = {};
                    this.pressRelease.photo_detect_log = savedLog;
                    this.savePipelineState();
                }

                if (!response.ok && !data.press_release) {
                    log('error', data.message || 'Photo detection failed.');
                }
            } catch (error) {
                log('error', 'Network error: ' + error.message);
            }

            this.pressReleaseDetectingPhotos = false;
        },

        async fetchPressReleaseDrivePhotos() {
            if (!this.pressRelease.google_drive_url) return;
            this.pressReleaseFetchingDrivePhotos = true;
            if (!this.pressRelease.photo_detect_log) this.pressRelease.photo_detect_log = [];
            this.pressRelease.photo_detect_log = [];

            const now = () => new Date().toLocaleTimeString('en-US', { hour12: false });
            const log = (type, message) => {
                this.pressRelease.photo_detect_log.push({ type, message, time: now() });
                if (typeof this._logActivity === 'function') {
                    this._logActivity('press_release', type, message, { debug_only: type === 'step' });
                }
            };

            log('info', 'Fetching photos from Google Drive...');
            log('step', 'URL: ' + this.pressRelease.google_drive_url);

            try {
                const response = await fetch('{{ route("notion.profile.fetch-photos") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ drive_url: this.pressRelease.google_drive_url }),
                });
                const data = await response.json();
                if (data.success && data.photos) {
                    log('success', 'Found ' + data.photos.length + ' photo(s) from Drive.');
                    // Convert Drive photos to detected_photos format
                    const drivePhotos = data.photos.map(p => ({
                        url: p.thumbnailLink || p.webContentLink || p.webViewLink || '',
                        thumbnail_url: p.thumbnailLink || p.webContentLink || '',
                        alt_text: p.name || '',
                        caption: '',
                        source: 'google-drive',
                        download_url: p.webContentLink || '',
                        view_url: p.webViewLink || '',
                    }));
                    drivePhotos.forEach((p, i) => { log('step', (i + 1) + '. ' + p.alt_text); });
                    this.pressRelease.detected_photos = [...drivePhotos];
                    this.pressRelease.selected_photo_keys = {};
                    this.savePipelineState();
                } else {
                    log('error', data.message || 'Failed to fetch photos from Drive.');
                }
            } catch (error) {
                log('error', 'Network error: ' + error.message);
            }

            this.pressReleaseFetchingDrivePhotos = false;
        },

        async detectPressReleaseContent() {
            if (!this.pressRelease.public_url) return;
            this.pressReleaseDetectingContent = true;
            this.pressRelease.content_detect_log = [];
            this.pressRelease.detected_content = '';
            this.pressRelease.detected_content_html = '';
            this.pressRelease.detected_word_count = 0;

            const now = () => new Date().toLocaleTimeString('en-US', { hour12: false });
            const log = (type, message) => {
                if (!this.pressRelease.content_detect_log) this.pressRelease.content_detect_log = [];
                this.pressRelease.content_detect_log.push({ type, message, time: now() });
                if (typeof this._logActivity === 'function') {
                    this._logActivity('press_release', type, message, { debug_only: type === 'step' });
                }
            };

            log('info', 'Starting content detection from URL...');
            log('step', 'URL: ' + this.pressRelease.public_url);
            log('step', 'Method: ' + (this.pressRelease.public_url_method || 'auto'));

            try {
                log('info', 'Sending extraction request...');
                const response = await fetch('{{ route("publish.pipeline.check") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        urls: [this.pressRelease.public_url],
                        method: this.pressRelease.public_url_method || 'auto',
                        user_agent: 'chrome',
                        retries: 1,
                        timeout: 30,
                        min_words: 20,
                        auto_fallback: true,
                    }),
                });
                const data = await response.json();

                if (data.results && data.results.length > 0) {
                    const result = data.results[0];
                    if (result.success) {
                        log('success', 'Content extracted: ' + result.word_count + ' words');
                        log('step', 'Title: ' + (result.title || '(none)'));
                        this.pressRelease.detected_content = result.text || '';
                        this.pressRelease.detected_content_html = result.formatted_html || '';
                        this.pressRelease.detected_word_count = result.word_count || 0;
                        // Also set as content dump for the spin step
                        if (!this.pressRelease.content_dump || this.pressRelease.content_dump.length < 50) {
                            this.pressRelease.content_dump = result.text || '';
                            log('info', 'Content set as source text for AI step.');
                        }
                        this.pressRelease.resolved_source_preview = (result.text || '').substring(0, 500) + (result.text?.length > 500 ? '...' : '');
                        this.pressRelease.resolved_source_label = result.title || 'Detected from URL';
                        this.savePipelineState();
                        this.invalidatePromptPreview('press_release_content_detected', { fetch: this.currentStep === 5 || this.openSteps.includes(5) });
                    } else {
                        log('error', 'Extraction failed: ' + (result.message || 'Unknown error'));
                        if (result.fetch_info?.reason) log('step', 'Reason: ' + result.fetch_info.reason);
                        if (result.fetch_info?.suggestion) log('step', 'Suggestion: ' + result.fetch_info.suggestion);
                    }
                } else {
                    log('error', data.message || 'No results returned.');
                }
            } catch (error) {
                log('error', 'Network error: ' + error.message);
            }

            this.pressReleaseDetectingContent = false;
        },

        queueServerPipelineStateSave() {
            clearTimeout(this._serverPipelineStateTimer);
            const signature = this._lastLocalPipelineStateSignature || this._stableSignature(this.buildPipelineStateSnapshot());
            if (!this._pendingServerPipelineStateSave && signature && signature === this._lastServerPipelineStateSignature) {
                this._logDebug?.('state', 'Skipped server pipeline-state queue (signature unchanged)', {
                    stage: 'state',
                    substage: 'server_skip',
                });
                return;
            }

            this._pendingServerPipelineStateSave = true;
            this._logDebug?.('state', 'Queued server pipeline-state save', {
                stage: 'state',
                substage: 'server_queued',
            });
            this._serverPipelineStateTimer = setTimeout(() => this.savePipelineStateToServer(), 350);
        },

        async savePipelineStateToServer() {
            if (!this.draftId) return true;
            if (this._draftSessionConflictActive) return false;
            if (this._savingPipelineStateServer) {
                this._pendingServerPipelineStateSave = true;
                this._logDebug?.('state', 'Server pipeline-state save deferred while another request is in flight', {
                    stage: 'state',
                    substage: 'server_deferred',
                });
                return false;
            }

            const payload = this.buildPipelineStateSnapshot();
            const signature = this._stableSignature(payload);
            if (!this._pendingServerPipelineStateSave && signature === this._lastServerPipelineStateSignature) {
                return true;
            }

            this._savingPipelineStateServer = true;
            this._pendingServerPipelineStateSave = false;
            const startedAt = typeof performance !== 'undefined' ? performance.now() : Date.now();

            try {
                const response = await this._rawPipelineFetch('{{ route("publish.pipeline.state.save") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        workflow_type: this.currentWorkflowKey(),
                        payload,
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (response.status === 409 && data.code === 'draft_session_conflict') {
                    this._handleDraftSessionConflict?.('state', data, { silent: true });
                    this._savingPipelineStateServer = false;
                    return false;
                }
                if (response.ok && data.success) {
                    this._clearDraftSessionConflict?.();
                    this._lastServerPipelineStateSignature = signature;
                    this._logDebug?.('state', 'Server pipeline state saved', {
                        stage: 'state',
                        substage: 'server_saved',
                        status: response.status,
                        duration_ms: Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt)),
                        details: data.state_id ? ('state_id: ' + data.state_id + ' | workflow: ' + (data.workflow_type || this.currentWorkflowKey())) : '',
                    });
                    this._savingPipelineStateServer = false;
                    if (this._pendingServerPipelineStateSave) {
                        this.queueServerPipelineStateSave();
                    }
                    return true;
                }

                this._pendingServerPipelineStateSave = true;
                this._logActivity?.('state', 'error', data.message || 'Server pipeline state save failed', {
                    stage: 'state',
                    substage: 'server_response_error',
                    status: response.status,
                    duration_ms: Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt)),
                });
            } catch (error) {
                if (this._isLikelyNavigationAbort?.(error)) {
                    this._savingPipelineStateServer = false;
                    return false;
                }
                this._pendingServerPipelineStateSave = true;
                this._logActivity?.('state', 'error', 'Server pipeline state save error: ' + error.message, {
                    stage: 'state',
                    substage: 'server_exception',
                    duration_ms: Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt)),
                });
            }

            this._savingPipelineStateServer = false;
            if (this._pendingServerPipelineStateSave) {
                this.queueServerPipelineStateSave();
            }
            return false;
        },

        buildPipelineStateSnapshot() {
            const state = { _v: this._stateVersion, draftId: this.draftId };
            state.siteConnStatus = this.siteConn.status;
            state.siteConnMessage = this.siteConn.message;
            state.siteConnLog = clone(this.siteConn.log);
            state.siteConnAuthors = clone(this.siteConn.authors);

            for (const key of this.persistentFields) {
                state[key] = clone(this[key]);
            }

            state.selectedUser = this.sanitizeSelectedUserForPersistence(state.selectedUser);
            state.photoSuggestions = this.sanitizePhotoSuggestionsForPersistence(state.photoSuggestions || []);
            state.featuredPhoto = this.sanitizePhotoAssetForPersistence(state.featuredPhoto || null);

            return state;
        },

        toAbsoluteMediaUrl(url) {
            if (!url) return '';
            try {
                return new URL(url, window.location.origin).toString();
            } catch (e) {
                return url;
            }
        },

        filenameBaseFromAsset(asset) {
            const base = (asset.filename || asset.label || 'press-release-photo')
                .toString()
                .replace(/\.[a-z0-9]+$/i, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

            return base || 'press-release-photo';
        },

        get pressReleasePhotoAssets() {
            const uploaded = (this.pressRelease.photo_files || []).map((file) => ({
                key: 'upload-' + file.id,
                label: file.original_name || file.filename || 'Uploaded photo',
                filename: file.original_name || file.filename || 'uploaded-photo',
                url: this.toAbsoluteMediaUrl(file.url),
                thumbnail_url: this.toAbsoluteMediaUrl(file.url),
                alt_text: '',
                caption: '',
                source_label: 'Uploaded Photo',
                source: 'uploaded',
            }));

            const detected = (this.pressRelease.detected_photos || []).map((photo, index) => ({
                key: 'detected-' + index,
                label: photo.alt_text || photo.caption || photo.url || 'Detected photo',
                filename: 'detected-photo-' + (index + 1),
                url: this.toAbsoluteMediaUrl(photo.url),
                thumbnail_url: this.toAbsoluteMediaUrl(photo.thumbnail_url || photo.url),
                alt_text: photo.alt_text || '',
                caption: photo.caption || '',
                source_label: photo.source || 'Detected from public URL',
                source: photo.source || 'detected',
            }));

            return [...uploaded, ...detected].filter((asset) => !!asset.url);
        },

        setPressReleaseFeaturedPhoto(asset) {
            const url = this.toAbsoluteMediaUrl(asset.url);
            const thumb = this.toAbsoluteMediaUrl(asset.thumbnail_url || asset.url);
            this.featuredPhoto = {
                url,
                url_large: url,
                url_thumb: thumb,
                alt: asset.alt_text || '',
                source: asset.source,
            };
            this.featuredAlt = asset.alt_text || '';
            this.featuredCaption = asset.caption || '';
            this.featuredFilename = this.filenameBaseFromAsset(asset) + '.jpg';
            this.savePipelineState();
            this.showNotification('success', 'Press release photo set as featured image.');
        },

        insertPressReleaseAssetIntoEditor(asset) {
            const editor = window.tinymce?.get('spin-preview-editor');
            const src = this.toAbsoluteMediaUrl(asset.url);
            const alt = (asset.alt_text || '').replace(/"/g, '&quot;');
            const caption = asset.caption || '';
            const figure = caption
                ? '<figure><img src="' + src + '" alt="' + alt + '"><figcaption>' + caption + '</figcaption></figure>'
                : '<p><img src="' + src + '" alt="' + alt + '"></p>';

            if (editor) {
                editor.insertContent(figure);
                this.editorContent = editor.getContent();
            } else {
                this.editorContent = (this.editorContent || '') + figure;
            }

            this.showNotification('success', 'Press release photo inserted into the article.');
            this.queueAutoSaveDraft(300);
        },
    };
}
</script>
