        // ── Step 6: AI Template ─────────────────────────────
        selectTemplate() {
            if (this.selectedTemplateId) {
                this.selectedTemplate = this.templates.find(t => t.id == this.selectedTemplateId) || null;
                if (this.selectedTemplate) {
                    if (this.selectedTemplate.ai_engine) this.aiModel = this.selectedTemplate.ai_engine;
                    this.loadPresetFields('template', this.selectedTemplate);
                }
            } else {
                this.selectedTemplate = null;
                this.loadPresetFields('template', null);
            }

            this.invalidatePromptPreview('select_template');
            if (!this._restoring) this.autoSaveDraft();
        },

        // ── Step 7: Spin ──────────────────────────────────
        _logSpin(type, message) {
            const time = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.spinLog.push({ type, message, time });
            this._logActivity('spin', type, message, { debug_only: type === 'step' });
        },

        _formatSpinMs(ms) {
            const numeric = Number(ms || 0);
            if (!Number.isFinite(numeric) || numeric <= 0) return '0ms';
            if (numeric >= 1000) return (numeric / 1000).toFixed(1) + 's';
            return Math.round(numeric) + 'ms';
        },

        _startSpinRequestWatchdog() {
            this._stopSpinRequestWatchdog();
            const startedAt = Date.now();
            const checkpoints = [15, 30, 45, 60];
            let checkpointIndex = 0;
            this._spinRequestStartedAt = startedAt;
            this._spinRequestWatchdog = setInterval(() => {
                const elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);
                if (checkpointIndex < checkpoints.length && elapsedSeconds >= checkpoints[checkpointIndex]) {
                    this._logSpin('step', 'Still waiting on ' + (this.aiModel || 'the selected model') + ' — ' + elapsedSeconds + 's elapsed.');
                    checkpointIndex += 1;
                }
            }, 1000);
        },

        _stopSpinRequestWatchdog() {
            if (this._spinRequestWatchdog) {
                clearInterval(this._spinRequestWatchdog);
                this._spinRequestWatchdog = null;
            }
            this._spinRequestStartedAt = 0;
        },

        _recordSpinDiagnostics(diagnostics, fallbackElapsedMs = 0) {
            if (!diagnostics || typeof diagnostics !== 'object') {
                return;
            }

            this.spinDiagnostics = diagnostics;

            const totalMs = Number(diagnostics.total_ms || fallbackElapsedMs || 0);
            const providerCallMs = Number(diagnostics.provider_call_ms || 0);
            const promptBuildMs = Number(diagnostics.prompt_build_ms || 0);
            const postProcessMs = Number(diagnostics.post_process_ms || 0);
            const attempts = Array.isArray(diagnostics.attempts) ? diagnostics.attempts : [];

            this._logSpin('step', 'Server timings — prompt build ' + this._formatSpinMs(promptBuildMs) + ', provider call ' + this._formatSpinMs(providerCallMs) + ', post-process ' + this._formatSpinMs(postProcessMs) + ', total ' + this._formatSpinMs(totalMs) + '.');

            if (attempts.length > 0) {
                attempts.forEach((attempt, index) => {
                    const provider = attempt?.provider || 'unknown';
                    const model = attempt?.model || 'unknown';
                    const durationMs = Number(attempt?.duration_ms || 0);
                    const status = attempt?.success ? 'success' : 'failed';
                    const message = String(attempt?.message || '').trim();
                    this._logSpin(
                        attempt?.success ? 'info' : 'step',
                        'Attempt ' + (index + 1) + ' — ' + provider + ' / ' + model + ' — ' + status + ' in ' + this._formatSpinMs(durationMs) + (message ? ' — ' + message : '')
                    );
                });
            }
        },

        _promptRefreshTimer: null,
        _queuePromptRefresh(reason = 'queued', force = false) {
            clearTimeout(this._promptRefreshTimer);
            this._promptRefreshTimer = setTimeout(() => this.refreshPromptPreview({ reason, force }), 500);
        },

        async refreshPhotoMeta(idx) {
            const ps = this.photoSuggestions[idx];
            if (!ps) return;
            const oldAlt = ps.alt_text || '';
            const oldCaption = ps.caption || '';
            const oldFilename = ps.suggestedFilename || '';
            this.photoSuggestions[idx].refreshingMeta = true;
            try {
                const articleText = (this.spunContent || '').replace(/<[^>]*>/g, '').substring(0, 2000);
                const resp = await fetch('{{ route("publish.pipeline.photo-meta") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        search_term: ps.search_term,
                        article_title: this.articleTitle || '',
                        article_text: articleText,
                        photo_source: ps.autoPhoto?.source || '',
                        photo_alt: ps.autoPhoto?.alt || '',
                        photo_url: ps.autoPhoto?.url_large || '',
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.photoSuggestions.splice(idx, 1, {
                        ...this.photoSuggestions[idx],
                        alt_text: data.alt,
                        caption: data.caption,
                        suggestedFilename: data.filename || this.buildFilename(ps.autoPhoto?.alt || ps.search_term, idx + 1),
                        metaGenerator: data.generator || 'unknown',
                    });
                    this.showNotification('success', 'Photo #' + (idx + 1) + ' metadata refreshed via ' + (data.generator === 'local' ? 'PHP' : 'AI'));
                } else {
                    console.error('[Photo Meta #' + idx + '] Error:', data.message || 'Unknown error');
                    this.showNotification('error', 'Failed to refresh metadata: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('[Photo Meta #' + idx + '] Exception:', e);
                this.showNotification('error', 'Failed to refresh metadata: ' + e.message);
            }
            this.photoSuggestions[idx].refreshingMeta = false;
        },

        async refreshFeaturedMeta() {
            const oldAlt = this.featuredAlt || '';
            const oldCaption = this.featuredCaption || '';
            const oldFilename = this.featuredFilename || '';
            this.featuredRefreshingMeta = true;
            try {
                const articleText = (this.spunContent || '').replace(/<[^>]*>/g, '').substring(0, 2000);
                const resp = await fetch('{{ route("publish.pipeline.photo-meta") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        search_term: this.featuredImageSearch,
                        article_title: this.articleTitle || '',
                        article_text: articleText,
                        photo_source: this.featuredPhoto?.source || '',
                        photo_alt: this.featuredPhoto?.alt || '',
                        photo_url: this.featuredPhoto?.url_large || '',
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.featuredAlt = data.alt;
                    this.featuredCaption = data.caption;
                    this.featuredFilename = data.filename || this.buildFilename(this.featuredPhoto?.alt || this.featuredImageSearch, 0);
                    this.featuredMetaGenerator = data.generator || 'unknown';
                    this.showNotification('success', 'Metadata refreshed via ' + (data.generator === 'local' ? 'PHP generator' : 'AI'));
                } else {
                    console.error('[Featured Meta] Error:', data.message || 'Unknown error');
                    this.showNotification('error', 'Failed to refresh metadata: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('[Featured Meta] Exception:', e);
                this.showNotification('error', 'Failed to refresh metadata: ' + e.message);
            }
            this.featuredRefreshingMeta = false;
        },

        async getOverlayPhotoMeta() {
            if (!this.insertingPhoto) return;
            this.overlayMetaLoading = true;
            try {
                const articleText = (this.spunContent || '').replace(/<[^>]*>/g, '').substring(0, 2000);
                const resp = await fetch('{{ route("publish.pipeline.photo-meta") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        search_term: this.photoSearch || this.insertingPhoto.alt || '',
                        article_title: this.articleTitle || '',
                        article_text: articleText,
                        photo_source: this.insertingPhoto?.source || '',
                        photo_alt: this.insertingPhoto?.alt || '',
                        photo_url: this.insertingPhoto?.url_large || '',
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.overlayPhotoAlt = data.alt;
                    this.overlayPhotoCaption = data.caption;
                    this.overlayPhotoFilename = data.filename || this.buildFilename(this.photoSearch || this.insertingPhoto?.alt, 0);
                    this.overlayMetaGenerated = true;
                    console.log('[Overlay Meta] NEW: alt="' + data.alt + '" caption="' + data.caption + '" file="' + this.overlayPhotoFilename + '"');
                    this.showNotification('success', 'Photo metadata generated');
                } else {
                    console.error('[Overlay Meta] Error:', data.message || 'Unknown error');
                    this.showNotification('error', 'Failed to get metadata: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('[Overlay Meta] Exception:', e);
                this.showNotification('error', 'Failed to get metadata: ' + e.message);
            }
            this.overlayMetaLoading = false;
        },

        copyResolvedPrompt() {
            if (!this.resolvedPrompt) return;

            const markCopied = () => {
                this.promptCopied = true;
                if (this._promptCopiedTimer) {
                    clearTimeout(this._promptCopiedTimer);
                }
                this._promptCopiedTimer = setTimeout(() => {
                    this.promptCopied = false;
                }, 1800);
            };

            const fallbackCopy = () => {
                const textarea = document.createElement("textarea");
                textarea.value = this.resolvedPrompt;
                textarea.setAttribute("readonly", "readonly");
                textarea.style.position = "fixed";
                textarea.style.opacity = "0";
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                let copied = false;
                try {
                    copied = document.execCommand("copy");
                } catch (error) {}
                document.body.removeChild(textarea);
                if (copied) {
                    markCopied();
                }
                return copied;
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(this.resolvedPrompt)
                    .then(() => {
                        markCopied();
                    })
                    .catch(() => {
                        fallbackCopy();
                    });
                return;
            }

            fallbackCopy();
        },

        async refreshPromptPreview(options = {}) {
            if (this._restoring) return;
            if (!options.force && !this.shouldAutoLoadPromptPreview()) {
                this.promptPreviewDirty = true;
                return;
            }

            const payload = this.buildPromptPreviewPayload();
            const signature = this._stableSignature(payload);
            if (!options.force && !this.promptPreviewDirty && this.resolvedPrompt && signature === this._lastPromptPreviewSignature) {
                return;
            }
            if (this.promptLoading) return;

            this.promptLoading = true;
            try {
                const resp = await fetch('{{ route("publish.pipeline.preview-prompt") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify(payload)
                });
                const data = await resp.json();
                if (data.success) {
                    this.resolvedPrompt = data.prompt;
                    this.promptLog = data.log || [];
                    this._lastPromptPreviewSignature = signature;
                    this.promptPreviewDirty = false;
                } else {
                    this.promptPreviewDirty = true;
                }
            } catch (e) {
                this.promptPreviewDirty = true;
            }
            this.promptLoading = false;
        },

        cancelSpin() {
            if (!this._spinAbortController) return;
            try {
                this._spinAbortController.abort();
            } catch (error) {}
        },

        async spinArticle() {
            this.spinning = true;
            this.spinError = '';
            this.spinLog = [];
            this.spinDiagnostics = null;
            this._logSpin('info', 'Starting article spin...');
            this._logSpin('step', 'Model: ' + this.aiModel);
            this._logSpin('step', this.currentArticleType === 'press-release'
                ? 'Press release source: ' + (this.pressRelease.resolved_source_label || this.pressRelease.submit_method)
                : 'Sources: ' + this.checkResults.filter(r => r.success).length);
            if (this.customPrompt) this._logSpin('step', 'Custom instructions: ' + this.customPrompt.substring(0, 100));
            if (this.selectedTemplate) this._logSpin('step', 'Template: ' + this.selectedTemplate.name);
            if (this.selectedPreset) this._logSpin('step', 'Preset: ' + this.selectedPreset.name);

            // Collect verified source texts
            const sourceTexts = this.currentArticleType === 'press-release'
                ? [this.buildPressReleaseSourceText(false)].filter(Boolean)
                : (this.isPrArticleMode()
                    ? [this.buildPrArticleSourceText(false)].filter(Boolean)
                    : this.checkResults
                        .filter(r => r.success && r.text)
                        .map(r => r.text));

            if (sourceTexts.length === 0) {
                this.spinError = this.currentArticleType === 'press-release'
                    ? 'No submitted press release source text is available yet. Detect fields or provide content first.'
                    : (this.isPrArticleMode()
                        ? 'No PR article source package is ready yet. Load subject context or import topic context first.'
                        : 'No verified source texts available. Please check sources first.');
                this.spinning = false;
                return;
            }

            try {
                const requestStartedAt = Date.now();
                this._startSpinRequestWatchdog();
                if (this._spinAbortController) { try { this._spinAbortController.abort(); } catch (error) {} }
                const controller = new AbortController();
                this._spinAbortController = controller;
                const resp = await fetch('{{ route('publish.pipeline.spin') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    signal: controller.signal,
                    body: JSON.stringify({
                        draft_id: this.draftId || null,
                        source_texts: sourceTexts,
                        template_id: this.selectedTemplateId || null,
                        preset_id: this.selectedPresetId || null,
                        prompt_slug: this.currentWorkflowPromptSlug(),
                        model: this.aiModel,
                        custom_prompt: this.customPrompt || null,
                        supporting_url_type: this.supportingUrlType || 'matching_content_type',
                        pr_subject_context: this.isPrArticleMode() ? this.buildPrSubjectContext() : null,
                        article_type: this.currentArticleType || null,
                        web_research: this.spinWebResearch,
                    })
                });
                const data = await resp.json();
                const elapsedMs = Date.now() - requestStartedAt;
                this._stopSpinRequestWatchdog();

                if (data.success) {
                    this.resetGeneratedArticleStateForSpin();
                    let nextHtml = data.html;
                    this.spunWordCount = data.word_count;
                    this.tokenUsage = data.usage;
                    this.spinDiagnostics = data.diagnostics || null;
                    this.lastAiCall = { user_name: data.user_name, model: data.model, provider: data.provider, usage: data.usage, cost: data.cost, ip: data.ip, timestamp_utc: data.timestamp_utc };
                    this.showNotification('success', data.message);
                    this._logSpin('success', 'Article generated — ' + data.word_count + ' words');
                    this._logSpin('info', 'Model: ' + (data.model || this.aiModel) + ' | Cost: $' + (data.cost || 0).toFixed(4) + ' | Tokens: ' + ((data.usage?.input_tokens || 0) + '+' + (data.usage?.output_tokens || 0)));
                    this._recordSpinDiagnostics(data.diagnostics, elapsedMs);
                    this.extractArticleLinks(nextHtml);

                    // Metadata from single prompt (titles, categories, tags)
                    if (data.metadata) {
                        this._logSpin('success', 'Metadata: ' + (data.metadata.titles?.length || 0) + ' titles, ' + (data.metadata.categories?.length || 0) + ' categories, ' + (data.metadata.tags?.length || 0) + ' tags');
                        this.applyGeneratedMetadata(data.metadata);
                    }

                    const currentTitle = String(this.articleTitle || '').trim();
                    const titleLooksPlaceholder = !currentTitle
                        || /^untitled(?:\s+pipeline\s+draft)?$/i.test(currentTitle)
                        || (this.isPrArticleMode() && this.isWeakPrArticleTitle(currentTitle));
                    const resolvedTitle = this.ensurePrArticleTitleSubject(
                        this.deriveArticleTitleFromHtml(nextHtml)
                        || (titleLooksPlaceholder ? '' : currentTitle)
                        || this.fallbackPrArticleTitle()
                    );
                    if (resolvedTitle && titleLooksPlaceholder) {
                        this.articleTitle = resolvedTitle;
                    }
                    nextHtml = this.ensureArticleTitleInHtml(nextHtml, this.articleTitle || resolvedTitle || '');

                    this.spunContent = nextHtml;
                    this.editorContent = nextHtml;
                    this.setSpinEditor(nextHtml);
                    this.rememberDraftBody(nextHtml);

                    // Featured image — auto-fetch with results
                    const hasPrSubjectPhotoAssets = this.isPrArticleMode() && this.selectedPrPhotoAssets(1).length > 0;
                    const hasImportedPressReleaseAssets = this.currentArticleType === 'press-release'
                        && this.isPressReleaseNotionImport?.()
                        && this.pressReleasePhotoAssets.length > 0;
                    if (data.featured_image && !hasPrSubjectPhotoAssets && !hasImportedPressReleaseAssets) {
                        this.featuredImageSearch = data.featured_image;
                        this.featuredSearchPending = true;
                    } else if (hasPrSubjectPhotoAssets || hasImportedPressReleaseAssets) {
                        this.featuredImageSearch = '';
                        this.featuredSearchPending = false;
                    }
                    if (data.featured_meta) {
                        this.featuredAlt = data.featured_meta.alt || '';
                        this.featuredCaption = data.featured_meta.caption || '';
                        this.featuredFilename = (data.featured_meta.filename || 'featured') + '.jpg';
                    }

                    // Resolved prompt for preview
                    if (data.resolved_prompt) this.resolvedPrompt = data.resolved_prompt;
                    if (data.resolved_prompt) {
                        this._lastPromptPreviewSignature = this._stableSignature(this.buildPromptPreviewPayload());
                        this.promptPreviewDirty = false;
                    }

                    if (data.photo_suggestions) {
                        this.photoSuggestions = data.photo_suggestions.map((ps, idx) => this.normalizePhotoSuggestionState({
                            ...ps,
                            autoPhoto: null,
                            confirmed: false,
                            removed: false,
                            searchResults: [],
                            loadAttempted: false,
                        }, idx));
                        this.photoSuggestionsPending = true;
                        this._lastInlinePhotoHydrationSignature = '';
                    }

                    this.syncDeferredEnrichmentState('spin_success');
                    if (this.isPrArticleMode()) {
                        this.$nextTick(() => this.hydratePrArticleSelectedMedia());
                    }
                    if (hasImportedPressReleaseAssets) {
                        this.$nextTick(() => this.applyNotionPressReleaseMediaDefaults({ injectInline: true, notify: false }));
                    }

                    this.queueAutoSaveDraft(300);

                    // Always advance to Create Article after spin
                    this.completeStep(5);
                    // Open both AI & Spin and Create Article
                    this.currentStep = 6;
                    this.openSteps = [5, 6];
                    this.queueInlinePhotoAutoHydration('spin_success');
                    this.queueFeaturedImageAutoHydration('spin_success');
                    if (!this._restoring) this._syncStepToUrl();
                    // Run AI detection in background (non-blocking)
                    if (this.aiDetectionEnabled) {
                        this.$nextTick(() => this.runAiDetection());
                    }
                    this._hasSpunThisSession = true;
                } else {
                    this.spinDiagnostics = data.diagnostics || null;
                    this.spinError = data.message;
                    this._logSpin('error', 'Spin failed: ' + data.message);
                    this._recordSpinDiagnostics(data.diagnostics, elapsedMs);
                }
            } catch (e) {
                this._stopSpinRequestWatchdog();
                if (e?.name === 'AbortError') {
                    this.spinError = 'Spin cancelled.';
                    this._logSpin('warning', 'Spin cancelled by user.');
                    this.showNotification?.('warning', 'Spin cancelled.');
                } else {
                    this.spinError = 'Network error during spinning.';
                    this._logSpin('error', 'Network error: ' + (e.message || 'Request failed'));
                }
            }
            this._spinAbortController = null;
            this.spinning = false;
        },

        acceptSpin() {
            // Read latest content from spin TinyMCE editor
            const spinEditor = tinymce.get('spin-preview-editor');
            this.spunContent = spinEditor ? spinEditor.getContent() : this.spunContent;
            // Extract title from H1 and REMOVE from body
            const tmp = document.createElement('div');
            tmp.innerHTML = this.spunContent;
            const h1 = tmp.querySelector('h1');
            if (h1) {
                if (!this.articleTitle) this.articleTitle = h1.textContent.trim();
                h1.remove();
                this.spunContent = tmp.innerHTML;
            }
            this.editorContent = this.spunContent;
            this.completeStep(6);
            this.openStep(7);
            this.autoSaveDraft();
        },

        async requestSpinChanges() {
            if (!this.spinChangeRequest.trim()) return;
            // Read latest from spin TinyMCE
            const spinEditor = tinymce.get('spin-preview-editor');
            const currentContent = spinEditor ? spinEditor.getContent() : this.spunContent;
            if (!currentContent) return;
            this.spinning = true;
            this.spinError = '';
            this.spinDiagnostics = null;
            try {
                const requestStartedAt = Date.now();
                this._startSpinRequestWatchdog();
                if (this._spinAbortController) { try { this._spinAbortController.abort(); } catch (error) {} }
                const controller = new AbortController();
                this._spinAbortController = controller;
                const resp = await fetch('{{ route('publish.pipeline.spin') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    signal: controller.signal,
                    body: JSON.stringify({
                        draft_id: this.draftId || null,
                        source_texts: [currentContent],
                        template_id: this.selectedTemplateId || null,
                        preset_id: this.selectedPresetId || null,
                        prompt_slug: this.currentWorkflowPromptSlug(true),
                        model: this.aiModel,
                        change_request: this.spinChangeRequest,
                        pr_subject_context: this.isPrArticleMode() ? this.buildPrSubjectContext() : null,
                        article_type: this.currentArticleType || null,
                    })
                });
                const data = await resp.json();
                const elapsedMs = Date.now() - requestStartedAt;
                this._stopSpinRequestWatchdog();
                if (data.success) {
                    this.spunContent = data.html;
                    this.editorContent = data.html;
                    this.spunWordCount = data.word_count;
                    this.tokenUsage = data.usage;
                    this.spinDiagnostics = data.diagnostics || null;
                    this.setSpinEditor(data.html);
                    this.extractArticleLinks(data.html);
                    this.lastAiCall = { user_name: data.user_name, model: data.model, provider: data.provider, usage: data.usage, cost: data.cost, ip: data.ip, timestamp_utc: data.timestamp_utc };
                    this.spinChangeRequest = '';
                    this.showChangeInput = false;
                    this.appliedSmartEdits = [];
                    this.showNotification('success', 'Changes applied.');
                    this._recordSpinDiagnostics(data.diagnostics, elapsedMs);

                    const hasPrSubjectPhotoAssets = this.isPrArticleMode() && this.selectedPrPhotoAssets(1).length > 0;
                    const hasImportedPressReleaseAssets = this.currentArticleType === 'press-release'
                        && this.isPressReleaseNotionImport?.()
                        && this.pressReleasePhotoAssets.length > 0;

                    if (this.isPrArticleMode()) {
                        this.$nextTick(() => this.hydratePrArticleSelectedMedia());
                    }
                    if (hasImportedPressReleaseAssets) {
                        this.$nextTick(() => this.applyNotionPressReleaseMediaDefaults({ injectInline: true, notify: false }));
                    }

                    this.syncDeferredEnrichmentState('spin_change_success');
                    this.queueInlinePhotoAutoHydration('spin_change_success');
                    this.queueFeaturedImageAutoHydration('spin_change_success');
                    this.queueAutoSaveDraft(300);
                    this._hasSpunThisSession = true;

                    // Re-run AI detection after changes
                    this.$nextTick(() => this.runAiDetection());
                } else {
                    this.spinDiagnostics = data.diagnostics || null;
                    this.spinError = data.message;
                    this._recordSpinDiagnostics(data.diagnostics, elapsedMs);
                }
            } catch (e) {
                this._stopSpinRequestWatchdog();
                if (e?.name === 'AbortError') {
                    this.spinError = 'Spin cancelled.';
                    this._logSpin('warning', 'Spin change request cancelled by user.');
                    this.showNotification?.('warning', 'Spin cancelled.');
                } else {
                    this.spinError = 'Network error.';
                }
            }
            this._spinAbortController = null;
            this.spinning = false;
        },

        @include('app-publish::publishing.pipeline.partials.media-ai-script')
