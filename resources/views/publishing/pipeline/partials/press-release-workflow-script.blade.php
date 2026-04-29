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
        pressReleasePhotoAssets: [],
        pressReleaseUploadingDocuments: false,
        pressReleaseDetectingContent: false,
        pressReleaseDetectingFields: false,
        pressReleaseDetectingPhotos: false,
        pressReleaseFetchingDrivePhotos: false,
        pressReleaseEpisodeSearching: false,
        pressReleaseEpisodeResults: [],
        pressReleaseEpisodeDropdownOpen: false,
        pressReleaseEpisodeNoResults: false,
        pressReleaseImportingEpisodeId: null,
        _serverPipelineStateTimer: null,
        _savingPipelineStateServer: false,
        _pressReleaseEpisodeSearchToken: 0,

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
            normalized.notion_episode_query = normalized.notion_episode_query || '';
            normalized.notion_episode = normalized.notion_episode && typeof normalized.notion_episode === 'object' ? normalized.notion_episode : {};
            normalized.notion_guest = normalized.notion_guest && typeof normalized.notion_guest === 'object' ? normalized.notion_guest : {};
            normalized.notion_missing_fields = Array.isArray(normalized.notion_missing_fields) ? normalized.notion_missing_fields : [];
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

            const isPodcastImport = this.pressRelease.submit_method === 'notion-podcast';
            if (this.pressRelease.polish_only) {
                return isPodcastImport ? 'press-release-podcast-polish' : 'press-release-polish';
            }

            return isPodcastImport ? 'press-release-podcast-spin' : 'press-release-spin';
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

        rebuildPressReleasePhotoAssets() {
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
                role: '',
            }));

            const detected = (this.pressRelease.detected_photos || []).map((photo, index) => ({
                key: 'detected-' + index,
                label: photo.alt_text || photo.caption || photo.url || 'Detected photo',
                filename: photo.filename || ('detected-photo-' + (index + 1)),
                url: this.toAbsoluteMediaUrl(photo.url),
                thumbnail_url: this.toAbsoluteMediaUrl(photo.thumbnail_url || photo.url),
                alt_text: photo.alt_text || '',
                caption: photo.caption || '',
                source_label: photo.source_label || photo.source || 'Detected from public URL',
                source: photo.source || 'detected',
                role: photo.role || '',
                download_url: this.toAbsoluteMediaUrl(photo.download_url || photo.url),
                view_url: this.toAbsoluteMediaUrl(photo.view_url || photo.url),
            }));

            this.pressReleasePhotoAssets = [...uploaded, ...detected].filter((asset) => !!asset.url);
            return this.pressReleasePhotoAssets;
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
                (this.pressRelease.document_files || []).length ||
                this.pressRelease.notion_episode?.id
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
            if (!this.template_overrides) this.template_overrides = {};
            this.template_overrides.article_type = 'press-release';
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
            if (method === 'notion-podcast' && !this.pressRelease.notion_episode?.id) {
                this.showNotification('error', 'Select a Notion podcast episode before continuing.');
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

        async searchPressReleaseNotionEpisodes(loadRecent = false, options = {}) {
            const notifyEmpty = options.notifyEmpty === true;
            const query = loadRecent ? '' : String(this.pressRelease.notion_episode_query || '').trim();

            if (!loadRecent && !query) {
                this.pressReleaseEpisodeResults = [];
                this.pressReleaseEpisodeNoResults = false;
                this.pressReleaseEpisodeDropdownOpen = false;
                return;
            }

            const token = ++this._pressReleaseEpisodeSearchToken;
            this.pressReleaseEpisodeSearching = true;
            this.pressReleaseEpisodeDropdownOpen = true;
            this.pressReleaseEpisodeNoResults = false;

            try {
                const response = await fetch('{{ route("publish.pipeline.press-release.search-notion-episodes") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        query,
                        limit: 10,
                    }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to search podcast episodes.');
                }
                if (token !== this._pressReleaseEpisodeSearchToken) {
                    return;
                }
                this.pressReleaseEpisodeResults = Array.isArray(data.records) ? data.records : [];
                this.pressReleaseEpisodeNoResults = this.pressReleaseEpisodeResults.length === 0;
                this.pressReleaseEpisodeDropdownOpen = this.pressReleaseEpisodeResults.length > 0 || this.pressReleaseEpisodeNoResults;
                if (!this.pressReleaseEpisodeResults.length && notifyEmpty) {
                    this.showNotification('error', 'No podcast episodes matched that search.');
                }
            } catch (error) {
                if (token !== this._pressReleaseEpisodeSearchToken) {
                    return;
                }
                this.pressReleaseEpisodeResults = [];
                this.pressReleaseEpisodeNoResults = false;
                this.pressReleaseEpisodeDropdownOpen = false;
                this.showNotification('error', error.message || 'Failed to search podcast episodes.');
            } finally {
                if (token === this._pressReleaseEpisodeSearchToken) {
                    this.pressReleaseEpisodeSearching = false;
                }
            }
        },

        async importPressReleaseNotionEpisode(record) {
            if (!record?.id) {
                this.showNotification('error', 'Invalid Notion episode selection.');
                return;
            }

            this.pressReleaseImportingEpisodeId = record.id;

            try {
                const response = await fetch('{{ route("publish.pipeline.press-release.import-notion-episode") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        page_id: record.id,
                    }),
                });
                const data = await response.json();
                if (!response.ok || !data.success || !data.press_release) {
                    throw new Error(data.message || 'Failed to import the selected podcast episode.');
                }
                this.pressReleaseEpisodeDropdownOpen = false;
                this.pressReleaseEpisodeNoResults = false;
                this.pressReleaseEpisodeResults = [];
                if (!this.template_overrides) this.template_overrides = {};
                this.template_overrides.article_type = 'press-release';
                this.pressRelease = this.normalizePressReleaseState(data.press_release || {});
                const importedAssets = this.rebuildPressReleasePhotoAssets();
                const importedFeaturedUrl = this.toAbsoluteMediaUrl(this.pressRelease?.notion_episode?.featured_image_url || '');
                const importedFeaturedAsset = importedAssets.find((asset) => importedFeaturedUrl && asset.url === importedFeaturedUrl)
                    || importedAssets.find((asset) => asset.role === 'featured')
                    || importedAssets.find((asset) => asset.source === 'notion-episode-media')
                    || importedAssets[0]
                    || null;
                if (importedFeaturedAsset) {
                    this.setPressReleaseFeaturedPhoto({ ...importedFeaturedAsset }, { notify: false });
                    this.featuredImageSearch = '';
                    this.featuredSearchPending = false;
                }
                this.applyPodcastPressReleaseMediaDefaults({ injectInline: false, notify: false });
                this.savePipelineState();
                this.invalidatePromptPreview('press_release_notion_episode_import', { fetch: this.currentStep === 5 || this.openSteps.includes(5) });
                this.showNotification('success', data.message || 'Podcast episode imported from Notion.');
            } catch (error) {
                this.showNotification('error', error.message || 'Failed to import the selected podcast episode.');
            }

            this.pressReleaseImportingEpisodeId = null;
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
                this.rebuildPressReleasePhotoAssets();
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
                    this.rebuildPressReleasePhotoAssets();
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
                    this.rebuildPressReleasePhotoAssets();
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
                    const drivePhotos = data.photos.map(p => ({
                        url: p.thumbnailLink || p.webContentLink || p.webViewLink || '',
                        thumbnail_url: p.thumbnailLink || p.webContentLink || '',
                        alt_text: p.name || '',
                        caption: '',
                        source: 'google-drive',
                        source_label: 'Google Drive Podcast Asset',
                        role: 'inline',
                        download_url: p.webContentLink || '',
                        view_url: p.webViewLink || '',
                    }));
                    drivePhotos.forEach((p, i) => { log('step', (i + 1) + '. ' + p.alt_text); });
                    this.pressRelease.detected_photos = this.mergePressReleaseDetectedPhotos(drivePhotos);
                    this.pressRelease.selected_photo_keys = {};
                    this.rebuildPressReleasePhotoAssets();
                    this.applyPodcastPressReleaseMediaDefaults({ injectInline: false, notify: false });
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
            state.selectedPrProfiles = (Array.isArray(state.selectedPrProfiles) ? state.selectedPrProfiles : [])
                .map((profile) => this.normalizePrProfileForState(profile))
                .filter((profile) => !!profile.id);
            state.prSubjectData = this.sanitizePrSubjectDataForPersistence(state.prSubjectData || {});

            const titleLooksPlaceholder = !state.articleTitle || /^untitled(?:\s+pipeline\s+draft)?$/i.test(String(state.articleTitle || '').trim());
            if (titleLooksPlaceholder && state.spunContent) {
                const derivedTitle = this.deriveArticleTitleFromHtml(state.spunContent);
                if (derivedTitle) {
                    state.articleTitle = derivedTitle;
                }
            }
            if (!state.editorContent && state.spunContent) {
                state.editorContent = state.spunContent;
            }

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

        mergePressReleaseDetectedPhotos(newPhotos = []) {
            const merged = [];
            const seen = new Set();
            const push = (photo) => {
                if (!photo || !photo.url) return;
                const key = this.toAbsoluteMediaUrl(photo.url);
                if (!key || seen.has(key)) return;
                seen.add(key);
                merged.push({
                    url: key,
                    thumbnail_url: this.toAbsoluteMediaUrl(photo.thumbnail_url || photo.url),
                    alt_text: photo.alt_text || '',
                    caption: photo.caption || '',
                    source: photo.source || 'detected',
                    source_label: photo.source_label || photo.source || 'Detected photo',
                    role: photo.role || '',
                    download_url: this.toAbsoluteMediaUrl(photo.download_url || photo.url),
                    view_url: this.toAbsoluteMediaUrl(photo.view_url || photo.url),
                    filename: photo.filename || '',
                });
            };

            (this.pressRelease.detected_photos || []).forEach(push);
            (newPhotos || []).forEach(push);
            return merged;
        },

        preferredPressReleaseFeaturedAsset() {
            const featuredUrl = this.pressRelease?.notion_episode?.featured_image_url || '';
            const assets = ((this.pressReleasePhotoAssets || []).length ? this.pressReleasePhotoAssets : this.rebuildPressReleasePhotoAssets())
                .map((asset) => ({ ...asset }));
            const match = assets.find((asset) => featuredUrl && asset.url === this.toAbsoluteMediaUrl(featuredUrl))
                || assets.find((asset) => asset.role === 'featured')
                || assets.find((asset) => asset.source === 'notion-episode-media')
                || null;
            return match ? { ...match } : null;
        },

        preferredPressReleaseInlineAsset() {
            const inlineUrl = this.pressRelease?.notion_guest?.inline_photo_url || '';
            const featuredUrl = this.toAbsoluteMediaUrl(this.pressRelease?.notion_episode?.featured_image_url || '');
            const assets = ((this.pressReleasePhotoAssets || []).length ? this.pressReleasePhotoAssets : this.rebuildPressReleasePhotoAssets())
                .map((asset) => ({ ...asset }));
            const match = assets.find((asset) => inlineUrl && asset.url === this.toAbsoluteMediaUrl(inlineUrl))
                || assets.find((asset) => asset.role === 'inline' && asset.url !== featuredUrl)
                || assets.find((asset) => asset.source === 'notion-guest-media' && asset.url !== featuredUrl)
                || assets.find((asset) => asset.source === 'google-drive' && asset.url !== featuredUrl)
                || null;
            return match ? { ...match } : null;
        },

        pressReleaseHasInlineAsset(url) {
            const target = this.toAbsoluteMediaUrl(url);
            const html = this.editorContent || this.spunContent || '';
            return !!(target && html && html.includes(target));
        },

        buildPressReleaseInlineFigure(asset) {
            const src = this.toAbsoluteMediaUrl(asset.url);
            const alt = (asset.alt_text || '').replace(/"/g, '&quot;');
            const caption = asset.caption || '';
            return caption
                ? '<figure><img src="' + src + '" alt="' + alt + '"><figcaption>' + caption + '</figcaption></figure>'
                : '<p><img src="' + src + '" alt="' + alt + '"></p>';
        },

        injectPressReleaseInlineAssetIntoHtml(asset, html) {
            const figure = this.buildPressReleaseInlineFigure(asset);
            if (/<h[2-6]\b[^>]*>\s*About\b/i.test(html)) {
                return html.replace(/<h[2-6]\b[^>]*>\s*About\b/i, figure + '$&');
            }
            if (/<h[2-6]\b/i.test(html)) {
                return html.replace(/<h[2-6]\b/i, figure + '$&');
            }
            const paragraphMatches = html.match(/<p\b[^>]*>.*?<\/p>/gis) || [];
            if (paragraphMatches.length >= 1) {
                const first = paragraphMatches[0];
                return html.replace(first, first + figure);
            }
            return html + figure;
        },

        applyPodcastPressReleaseMediaDefaults(options = {}) {
            if (this.currentArticleType !== 'press-release' || this.pressRelease.submit_method !== 'notion-podcast') {
                return;
            }

            const opts = { injectInline: false, notify: false, ...options };
            this.rebuildPressReleasePhotoAssets();
            const featuredAsset = this.preferredPressReleaseFeaturedAsset();
            if (featuredAsset) {
                this.setPressReleaseFeaturedPhoto(featuredAsset, { notify: opts.notify });
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            } else if (!this.featuredPhoto && (this.pressReleasePhotoAssets || []).length > 0) {
                this.setPressReleaseFeaturedPhoto({ ...(this.pressReleasePhotoAssets[0] || {}) }, { notify: opts.notify });
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            }

            if (!opts.injectInline) {
                return;
            }

            const inlineAsset = this.preferredPressReleaseInlineAsset();
            if (!inlineAsset || this.pressReleaseHasInlineAsset(inlineAsset.url)) {
                return;
            }

            const nextHtml = this.injectPressReleaseInlineAssetIntoHtml(inlineAsset, this.editorContent || this.spunContent || '');
            if (!nextHtml) {
                return;
            }

            this.spunContent = nextHtml;
            this.editorContent = nextHtml;
            this.setSpinEditor?.(nextHtml);
            this.rememberDraftBody?.(nextHtml);
            this.queueAutoSaveDraft?.(300);
        },

        setPressReleaseFeaturedPhoto(asset, options = {}) {
            const notify = options.notify !== false;
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
            if (notify) {
                this.showNotification('success', 'Press release photo set as featured image.');
            }
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
