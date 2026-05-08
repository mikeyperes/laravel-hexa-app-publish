        // ── Navigation ────────────────────────────────────
        isStepAccessible(step) {
            if (step === 1) return true;
            // Step 2 (Article Config) unlocks as soon as a user is set, preloaded, or present on the draft payload
            if (step === 2 && (this.selectedUser || this.draftState?.selectedUser)) return true;
            // Step 4 (Fetch) requires at least one source selected
            if (step === 4 && this.sources.length === 0 && !this.isGenerateMode) return false;
            return this.completedSteps.includes(step - 1) || this.completedSteps.includes(step);
        },

        _syncStepToUrl() {
            const url = new URL(window.location);
            url.searchParams.set('step', this.currentStep);
            history.replaceState(null, '', url.toString());
        },

        normalizedOpenSteps(step, keepCurrentOpen = false) {
            const numericStep = Number(step || 0);
            if (numericStep >= 4 && numericStep <= 7) {
                return keepCurrentOpen ? [3] : [3, numericStep];
            }
            return keepCurrentOpen ? [] : [numericStep];
        },

        normalizeNestedOpenSteps(steps, currentStep = null) {
            const normalized = Array.from(new Set((Array.isArray(steps) ? steps : []).map((step) => Number(step)).filter((step) => step >= 1 && step <= 7)));
            const activeStep = Number(currentStep || this.currentStep || 0);
            const hasNestedStep = normalized.some((step) => step >= 4 && step <= 7) || (activeStep >= 4 && activeStep <= 7);
            if (hasNestedStep && !normalized.includes(3)) {
                normalized.unshift(3);
            }
            return normalized;
        },

        goToStep(step) {
            if (this.isStepAccessible(step)) {
                this.currentStep = step;
                this.openSteps = this.normalizeNestedOpenSteps(this.normalizedOpenSteps(step), step);
                if (step === 5 || this.openSteps.includes(5)) {
                    this._queuePromptRefresh('go_to_step');
                }
                if (step === 7 || this.openSteps.includes(7)) {
                    this.$nextTick(() => this._restorePipelineOperations());
                    this._focusPublishActionBox({ behavior: 'smooth' });
                }
                this._syncStepToUrl();
                this._logActivity('ui', 'step', 'Navigated to step ' + step, {
                    stage: 'navigation',
                    substage: 'go_to_step',
                    step,
                    debug_only: true,
                });
            }
        },

        toggleStep(step) {
            if (!this.isStepAccessible(step)) return;
            this.currentStep = step;
            if (this.openSteps.includes(step)) {
                this.openSteps = this.normalizeNestedOpenSteps(this.normalizedOpenSteps(step, true), step);
            } else {
                this.openSteps = this.normalizeNestedOpenSteps(this.normalizedOpenSteps(step), step);
            }
            if (step === 5 && this.openSteps.includes(5)) {
                this._queuePromptRefresh('toggle_step');
            }
            if (step === 7 && this.openSteps.includes(7)) {
                this.$nextTick(() => this._restorePipelineOperations());
                this._focusPublishActionBox({ behavior: 'smooth' });
            }
            this._syncStepToUrl();
            this._logDebug('ui', 'Toggled step ' + step, {
                stage: 'navigation',
                substage: this.openSteps.includes(step) ? 'opened' : 'closed',
                step,
            });
        },

        openStep(step) {
            this.currentStep = step;
            this.openSteps = this.normalizeNestedOpenSteps(this.normalizedOpenSteps(step), step);
            if (step === 5 || this.openSteps.includes(5)) {
                this._queuePromptRefresh('open_step');
            }
            if (step === 7 || this.openSteps.includes(7)) {
                this.$nextTick(() => this._restorePipelineOperations());
                this._focusPublishActionBox({ behavior: 'smooth' });
            }
            if (!this._restoring) this._syncStepToUrl();
            this._logDebug('ui', 'Opened step ' + step, {
                stage: 'navigation',
                substage: 'open_step',
                step,
            });
        },

        completeStep(step) {
            if (!this.completedSteps.includes(step)) {
                this.completedSteps.push(step);
                this._logActivity('ui', 'success', 'Completed step ' + step, {
                    stage: 'navigation',
                    substage: 'complete_step',
                    step,
                    debug_only: true,
                });
            }
        },

        verifiedSourceCount() {
            return (Array.isArray(this.checkResults) ? this.checkResults : []).filter((result) => {
                return !!result?.success && String(result?.text || '').trim() !== '';
            }).length;
        },

        hasVerifiedSourceArticles() {
            return this.verifiedSourceCount() > 0;
        },

        stepFourPrimaryLabel() {
            if (this.currentArticleType === 'press-release') {
                return this.pressRelease?.polish_only ? 'Continue to Polish' : 'Continue to AI Spin';
            }
            if (this.checking) return 'Getting Article(s)...';
            return this.hasVerifiedSourceArticles() ? 'Continue to AI Spin' : 'Get Article(s)';
        },

        async handleStepFourPrimaryAction() {
            if (this.checking) return;

            if (this.currentArticleType === 'press-release') {
                this.completeStep(4);
                this.openStep(5);
                return;
            }

            if (this.hasVerifiedSourceArticles()) {
                this.completeStep(4);
                this.openStep(5);
                this.showNotification('success', 'Source articles ready — continue to AI Spin.');
                return;
            }

            await this.checkAllSources();
        },

        hasSpinOutput() {
            return !!String(this.spunContent || this.editorContent || '').trim() || Number(this.spunWordCount || 0) > 0;
        },

        // ── Step 1: User Selection ───────────────────────────
        async selectUser(user) {
            this.selectedUser = user;
            this._logActivity('user', 'info', 'Selected user ' + (user?.name || user?.email || user?.id || 'unknown'), {
                stage: 'selection',
                substage: 'user',
                details: this._summarizeValue({ user_id: user?.id || null, email: user?.email || '' }, 300),
            });
            this.completeStep(1);
            this.openStep(2);
            await Promise.all([this.loadUserPresets(), this.loadUserTemplates()]);

            // Auto-select default preset
            const defaultPreset = this.presets.find(p => p.is_default);
            if (defaultPreset) {
                this.selectedPresetId = String(defaultPreset.id);
                this.selectPreset();
            }

            // Auto-select default AI template
            const defaultTemplate = this.templates.find(t => t.is_default);
            if (defaultTemplate) {
                this.selectedTemplateId = String(defaultTemplate.id);
                this.selectTemplate();
            }

            if (this.template_overrides?.article_type) {
                this.autoSelectPrSource();
            }

            if (!this._restoring) this.autoSaveDraft();
        },

        clearUser() {
            this.selectedUser = null;
            this._logActivity('user', 'warning', 'Cleared selected user', {
                stage: 'selection',
                substage: 'user_clear',
                debug_only: true,
            });
            this.presets = [];
            this.selectedPreset = null;
            this.selectedPresetId = '';
            this.templates = [];
            this.selectedTemplate = null;
            this.selectedTemplateId = '';
            this.completedSteps = [];
            this.openSteps = [1];
            this.currentStep = 1;
        },

        async loadUserPresets() {
            if (!this.selectedUser) return;
            this.presetsLoading = true;
            if (this._useBootstrappedPresetsIfAvailable()) {
                this.presetsLoading = false;
                return this.presets;
            }
            try {
                const resp = await fetch(`{{ route('publish.presets.index') }}?user_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.presets = data.data || data || [];
            } catch (e) { this.presets = []; }
            this.presetsLoading = false;
        },

        async loadUserTemplates() {
            if (!this.selectedUser) return;
            this.templatesLoading = true;
            if (this._useBootstrappedTemplatesIfAvailable()) {
                this.templatesLoading = false;
                this.autoSelectPrSource();
                return this.templates;
            }
            try {
                const resp = await fetch(`{{ route('publish.templates.index') }}?user_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.templates = data.data || data || [];
                this.autoSelectPrSource();
            } catch (e) { this.templates = []; }
            this.templatesLoading = false;
        },

        // ── Step 2: Preset ────────────────────────────────
        selectPreset() {
            if (this.selectedPresetId) {
                this.selectedPreset = this.presets.find(p => p.id == this.selectedPresetId) || null;
                // Auto-fill from preset
                if (this.selectedPreset) {
                    if (this.selectedPreset.default_site_id && (!this._restoring || !this.selectedSiteId)) {
                        this.selectedSiteId = String(this.selectedPreset.default_site_id);
                        this.selectSite();
                    }
                    if (this.selectedPreset.default_publish_action) {
                        const actionMap = {
                            'auto-publish': 'publish',
                            'draft-local': 'draft_local',
                            'draft-wordpress': 'draft_wp',
                            'review': 'draft_local',
                            'notify': 'draft_local',
                        };
                        this.publishAction = actionMap[this.selectedPreset.default_publish_action] || 'draft_local';
                    }
                    this.loadPresetFields('preset', this.selectedPreset);
                }
            } else {
                this.selectedPreset = null;
                this.loadPresetFields('preset', null);
            }

            this.invalidatePromptPreview('select_preset');
            if (!this._restoring) this.autoSaveDraft();
        },

        // ── Step 3: Website ───────────────────────────────
        siteConnectionCacheKey(siteId = null) {
            const normalized = String(siteId || '').trim();
            return normalized ? ('publishSiteConnection:' + normalized) : null;
        },

        selectSite() {
            if (this.selectedSiteId) {
                this.selectedSite = this.sites.find(s => s.id == this.selectedSiteId) || this.prSourceSites.find(s => s.id == this.selectedSiteId) || null;
                if (this.selectedSite) {
                    const cacheKey = this.siteConnectionCacheKey(this.selectedSiteId);
                    const restoredFromCache = cacheKey ? this.restoreSiteConnection(this.selectedSiteId, cacheKey) : false;

                    this.siteConn.log = [];
                    this.siteConn.testing = false;
                    // Always reset author to this site's default (or clear it)
                    this.publishAuthor = this.selectedSite.default_author || '';
                    this.publishAuthorSource = this.selectedSite.default_author ? 'profile' : '';
                    if (restoredFromCache) {
                        this.selectedSite.status = 'connected';
                        if (this.siteConn.defaultAuthor && !this.publishAuthor) {
                            this.publishAuthor = this.siteConn.defaultAuthor;
                            this.publishAuthorSource = 'profile';
                        }
                        this.completeStep(2);
                    } else if (this.selectedSite.status === 'connected') {
                        this.siteConn.status = true;
                        this.siteConn.message = 'Connected';
                        this.completeStep(2);
                    } else if (this.selectedSite.status === 'error') {
                        this.siteConn.status = false;
                        this.siteConn.message = 'Connection error';
                    } else {
                        this.siteConn.status = null;
                        this.siteConn.message = 'Loading connection state...';
                    }
                    if (!this.siteConn.authors.length) {
                        this.loadSiteAuthors(this.selectedSiteId, { cacheKey });
                    }
                    if (this.currentArticleType === 'press-release' && this.selectedSite?.is_press_release_source && typeof this.loadSyndicationCategories === 'function') {
                        this.loadSyndicationCategories(false);
                    }
                    this._logActivity('site', this.siteConn.status === true ? 'success' : 'warning', 'Selected site ' + this.selectedSite.name, {
                        stage: 'selection',
                        substage: 'site',
                        details: this._summarizeValue({
                            site_id: this.selectedSite.id,
                            connection_status: this.selectedSite.status,
                            author: this.publishAuthor || '',
                        }, 300),
                    });
                    if (!this._restoring) this.autoSaveDraft();
                }
            } else {
                this.selectedSite = null;
                this.publishAuthor = '';
                this.siteConn.authors = [];
                this.siteConn.log = [];
                this.siteConn.status = null;
                this.siteConn.message = '';
                this._logDebug('site', 'Cleared selected site', {
                    stage: 'selection',
                    substage: 'site_clear',
                });
            }
        },

        refreshSiteConnection() {
            if (!this.selectedSiteId) return;
            this.testSiteConnection(this.selectedSiteId, this.csrfToken, {
                cacheKey: this.siteConnectionCacheKey(this.selectedSiteId),
                onSuccess: (d) => {
                    if (d.default_author) { this.publishAuthor = d.default_author; this.publishAuthorSource = 'profile'; }
                    if (this.selectedSite) {
                        this.selectedSite.status = 'connected';
                    }
                    this.completeStep(2);
                    this.autoSaveDraft();
                },
            });
        },

        autoSelectPressReleaseTemplate() {
            if (this.template_overrides?.article_type !== 'press-release') return;
            const current = this.templates.find(t => String(t.id) === String(this.selectedTemplateId));
            if (current && current.article_type === 'press-release') return;
            const preferred = this.templates.find(t => t.article_type === 'press-release' && t.name === 'Hexa PR Wire - Podcast Press Release')
                || this.templates.find(t => t.article_type === 'press-release');
            if (preferred) {
                this.selectedTemplateId = String(preferred.id);
                this.selectTemplate();
            }
        },

        autoSelectPrArticleTemplate() {
            const articleType = this.template_overrides?.article_type;
            if (!['pr-full-feature', 'expert-article'].includes(articleType)) return;

            const current = this.templates.find(t => String(t.id) === String(this.selectedTemplateId));
            if (current && current.article_type === articleType) return;

            const preferredName = articleType === 'pr-full-feature'
                ? 'Hexa PR Wire - PR Full Feature'
                : 'Hexa PR Wire - Expert Article';

            const preferred = this.templates.find(t => t.article_type === articleType && t.name === preferredName)
                || this.templates.find(t => t.article_type === articleType);

            if (preferred) {
                this.selectedTemplateId = String(preferred.id);
                this.selectTemplate();
            }
        },

        syncTemplateSelectionForArticleType() {
            const articleType = String(this.template_overrides?.article_type || '').trim();
            const current = this.templates.find(t => String(t.id) === String(this.selectedTemplateId)) || this.selectedTemplate || null;
            if (!current) return;

            const currentType = String(current.article_type || '').trim();
            if (!currentType || !articleType || currentType === articleType) return;

            const replacement = this.templates.find(t => t.article_type === articleType && t.is_default)
                || this.templates.find(t => t.article_type === articleType)
                || null;

            if (replacement) {
                this.selectedTemplateId = String(replacement.id);
                this.selectTemplate();
                return;
            }

            this.selectedTemplateId = '';
            this.selectedTemplate = null;
            this.loadPresetFields('template', null, null);
        },

        autoSelectPrSource() {
            this.syncTemplateSelectionForArticleType();
            if (this.template_overrides?.article_type === 'press-release') {
                this.autoSelectPressReleaseTemplate();
                if (this.prSourceSites.length === 0) return;
                // Save current non-PR site before switching
                const currentInPr = this.prSourceSites.find(s => String(s.id) === this.selectedSiteId);
                if (!currentInPr) {
                    if (this.selectedSiteId) this._previousSiteId = this.selectedSiteId;
                    this.selectedSiteId = String(this.prSourceSites[0].id);
                    this.selectSite();
                }
            } else {
                // Switching away from press-release — restore previous site if it was swapped
                if (this._previousSiteId) {
                    const prevSite = this.sites.find(s => String(s.id) === this._previousSiteId);
                    if (prevSite) {
                        this.selectedSiteId = this._previousSiteId;
                        this.selectSite();
                    }
                    this._previousSiteId = null;
                }
            }

            if (this.isPrArticleMode()) {
                this.autoSelectPrArticleTemplate();
            }
        },

        // ── Step 3: Sources ───────────────────────────────
        async uploadSourceDocument(files) {
            if (!files || !files.length) return;
            this.uploadingSourceDoc = true;
            const formData = new FormData();
            formData.append('file', files[0]);
            formData.append('draft_id', this.draftId);
            try {
                const resp = await fetch('{{ route("publish.pipeline.upload-source-doc") }}', {
                    method: 'POST',
                    headers: this.requestHeaders(),
                    body: formData,
                });
                const data = await resp.json();
                if (data.success) {
                    this.uploadedSourceDoc = { name: files[0].name, word_count: data.word_count };
                    this.uploadedSourceText = data.text;
                    this.showNotification('success', 'Document uploaded — ' + data.word_count + ' words extracted');
                } else {
                    this.showNotification('error', data.message || 'Upload failed');
                }
            } catch (e) {
                this.showNotification('error', 'Upload error: ' + e.message);
            }
            this.uploadingSourceDoc = false;
        },

        useUploadedContent() {
            const text = this.uploadedSourceText.trim();
            if (!text) return;
            // Add as a virtual source
            this.sources.push({ url: 'upload://' + (this.uploadedSourceDoc?.name || 'pasted-content'), title: this.uploadedSourceDoc?.name || 'Uploaded Content', status: 'ready', wordCount: text.split(/\s+/).filter(Boolean).length });
            this.maybeApplySmartDraftTitle?.();
            // Store the text so the spin step can use it
            this.checkResults.push({ url: 'upload://', success: true, text: text, word_count: text.split(/\s+/).filter(Boolean).length, formatted_html: '<p>' + text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>' });
            this.approvedSources.push(this.checkResults.length - 1);
            this.completeStep(3);
            this.completeStep(4);
            this.openStep(5);
            this.showNotification('success', 'Content added as source — proceed to AI & Spin');
        },

        skipSpinPublishAsIs() {
            const text = this.uploadedSourceText.trim();
            if (!text) return;
            // Set as editor content directly, skip spinning
            const html = '<p>' + text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
            this.editorContent = html;
            this.spunContent = html;
            this.spunWordCount = text.split(/\s+/).filter(Boolean).length;
            this.articleTitle = this.uploadedSourceDoc?.name?.replace(/\.(docx?|pdf)$/i, '') || 'Untitled';
            this.completeStep(3);
            this.completeStep(4);
            this.completeStep(5);
            this.openStep(6);
            this.showNotification('success', 'Content loaded — skipped spinning, go to Create Article');
        },

        addPastedUrls() {
            const urls = this.pasteText.split('\n').map(u => u.trim()).filter(u => u && u.startsWith('http'));
            urls.forEach(url => {
                if (!this.sources.find(s => s.url === url)) {
                    this.sources.push({ url, title: '', status: 'pending', wordCount: 0 });
                }
            });
            this.pasteText = '';
        },

        addSource(url, title) {
            if (!this.sources.find(s => s.url === url)) {
                this.sources.push({ url, title: title || '', status: 'pending', wordCount: 0 });
            }
            this.maybeApplySmartDraftTitle?.();
            this.showNotification('success', 'Source added');
        },

        removeSource(idx) {
            this.sources.splice(idx, 1);
        },

        setNewsMode(mode) {
            this.newsMode = mode;
            this.newsResults = [];
            this.newsHasSearched = false;
            this.newsSearching = false;

            if (mode === 'trending') {
                this.newsSearch = '';
                this.newsCategory = '';
                this.newsTrendingSelected = false;
                return;
            }

            this.newsTrendingSelected = false;
            if (mode !== 'genre') {
                this.newsCategory = '';
            }
        },

        selectTrendingCategory(category = '') {
            this.newsMode = 'trending';
            this.newsSearch = '';
            this.newsCategory = category;
            this.newsTrendingSelected = true;
            this.newsResults = [];
            this.newsHasSearched = false;
            this.searchNews();
        },

        _newsAbortController: null,

        async searchNews() {
            if (this.newsMode === 'keyword' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'local' && !this.newsSearch.trim()) return;
            if (this.newsMode === 'genre' && !this.newsCategory && !this.newsSearch.trim()) return;
            if (this.newsMode === 'trending' && !this.newsTrendingSelected) return;
            // Abort any in-flight search
            if (this._newsAbortController) { try { this._newsAbortController.abort(); } catch {} }
            this._newsAbortController = new AbortController();
            const signal = this._newsAbortController.signal;
            this.newsSearching = true;
            this.newsResults = [];
            this.newsHasSearched = false;
            try {
                const resp = await fetch('{{ route('publish.search.articles.post') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        query: this.newsSearch,
                        mode: this.newsMode,
                        category: this.newsCategory,
                        country: this.newsCountry,
                    }),
                    signal,
                });
                const data = await resp.json();
                this.newsResults = (data.data && data.data.articles) ? data.data.articles : [];
                this.newsHasSearched = true;
            } catch (e) {
                if (e.name === 'AbortError') return; // Cancelled by new search — don't update state
                this.newsResults = [];
                this.newsHasSearched = true;
                this.showNotification('error', 'Search failed: ' + e.message);
            } finally {
                if (!signal.aborted) this.newsSearching = false;
            }
        },

        _logAi(type, message) {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false });
            this.aiLog.push({ type, message, time });
            this._logActivity('ai', type, message, { debug_only: type === 'step' });
        },

        async aiSearchArticles() {
            if (!this.aiSearchTopic.trim()) return;
            this.aiSearching = true;
            this.aiSearchResults = [];
            this.aiSearchError = '';
            this.aiSearchCost = null;
            this.aiHasSearched = false;
            this.aiLog = [];

            const searchAgentLabel = this.aiSearchOptionLabels?.[this.aiSearchModel] || this.aiSearchModel;
            this._logAi('info', 'Starting AI article search for: ' + this.aiSearchTopic);
            this._logAi('info', 'Requesting top 10 articles via ' + searchAgentLabel + ' with web search...');

            try {
                // Collect URLs from current results + already-added sources to exclude duplicates on re-search
                const excludeUrls = [
                    ...this.aiSearchResults.map(a => a.url),
                    ...this.sources.map(s => s.url),
                ].filter(Boolean);

                const resp = await fetch('{{ route("publish.pipeline.ai-search") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        topic: this.aiSearchTopic,
                        draft_id: this.draftId || null,
                        count: 10,
                        model: this.aiSearchModel,
                        exclude_urls: excludeUrls.length > 0 ? excludeUrls : undefined,
                    }),
                });
                const data = await resp.json();
                if (data.success && data.data && data.data.articles) {
                    this._logAi('success', 'Found ' + data.data.articles.length + ' article(s)');
                    this.aiSearchResults = data.data.articles;

                    if (data.data.search_backend_label) {
                        let backendMessage = 'Reliable search backend: ' + data.data.search_backend_label;
                        if (data.data.fallback_reason) {
                            backendMessage += ' | AI search fallback reason: ' + data.data.fallback_reason;
                        }
                        this._logAi('info', backendMessage);
                    }

                    // Save to search history
                    const term = this.aiSearchTopic.trim();
                    if (term) {
                        this.aiSearchHistory = [term, ...this.aiSearchHistory.filter(t => t !== term)].slice(0, 20);
                        localStorage.setItem('hws_search_history', JSON.stringify(this.aiSearchHistory));
                    }

                    // Log each article
                    data.data.articles.forEach((article, i) => {
                        this._logAi('step', (i + 1) + '. ' + article.title + ' — ' + article.url.substring(0, 80));
                    });

                    this._logAi('info', data.data.articles.length + ' articles ready — select the ones you want as sources');

                    // Cost info
                    if (data.data.usage) {
                        this.aiSearchCost = { model: data.data.model || @json(($pipelineDefaults['search_model'] ?? null)), cost: data.data.cost || 0, usage: data.data.usage };
                        this._logAi('info', 'Cost: $' + (data.data.cost || 0).toFixed(4) + ' | Tokens: ' + (data.data.usage.input_tokens || 0) + '+' + (data.data.usage.output_tokens || 0) + ' | Model: ' + (data.data.model || @json(($pipelineDefaults['search_model'] ?? null))));
                    }

                    this.showNotification('success', data.data.articles.length + ' article(s) found — select sources below.');
                } else {
                    this._logAi('error', data.message || 'No articles found.');
                    this.aiSearchError = data.message || 'No articles found.';
                }
                this.aiHasSearched = true;
            } catch (e) {
                this._logAi('error', 'Search failed: ' + e.message);
                this.aiSearchError = 'Search failed: ' + e.message;
                this.aiHasSearched = true;
            }
            this.aiSearching = false;
        },

        isPrArticleMode() {
            return ['expert-article', 'pr-full-feature'].includes(this.currentArticleType);
        },

        normalizePrArticleState(state = {}) {
            const defaults = JSON.parse(JSON.stringify(this.prArticleDefaultState || {}));
            const incoming = JSON.parse(JSON.stringify(state || {}));
            const normalized = {
                ...defaults,
                ...incoming,
            };

            normalized.main_subject_id = normalized.main_subject_id ? Number(normalized.main_subject_id) : null;
            normalized.focus_instructions = normalized.focus_instructions || '';
            normalized.subject_position = normalized.subject_position || '';
            normalized.promotional_level = normalized.promotional_level || defaults.promotional_level || 'editorial-subtle';
            normalized.tone = normalized.tone || defaults.tone || 'journalistic';
            normalized.quote_guidance = normalized.quote_guidance || '';
            normalized.quote_count = Number(normalized.quote_count || defaults.quote_count || 0);
            normalized.include_subject_name_in_title = normalized.include_subject_name_in_title !== false;
            normalized.feature_photo_mode = normalized.feature_photo_mode || defaults.feature_photo_mode || 'featured_and_inline';
            normalized.inline_photo_target = Math.max(0, Number(normalized.inline_photo_target || defaults.inline_photo_target || 0));
            normalized.expert_source_mode = normalized.expert_source_mode || defaults.expert_source_mode || 'keywords';
            normalized.expert_keywords = normalized.expert_keywords || '';
            normalized.expert_context_url = normalized.expert_context_url || '';
            normalized.expert_context_extracted = normalized.expert_context_extracted && typeof normalized.expert_context_extracted === 'object'
                ? normalized.expert_context_extracted
                : {};

            return normalized;
        },

        syncPrArticleForCurrentArticleType({ force = false } = {}) {
            this.prArticle = this.normalizePrArticleState(this.prArticle || {});

            if (!this.prArticle.main_subject_id && this.selectedPrProfiles.length > 0) {
                this.prArticle.main_subject_id = Number(this.selectedPrProfiles[0].id);
            }

            if (this.currentArticleType === 'expert-article') {
                if (force || !this.prArticle.quote_count || this.prArticle.quote_count < 3) {
                    this.prArticle.quote_count = 4;
                }
                if (!['keywords', 'url', 'none'].includes(this.prArticle.expert_source_mode)) {
                    this.prArticle.expert_source_mode = 'keywords';
                }
                if (!['featured_and_inline', 'inline_only'].includes(this.prArticle.feature_photo_mode)) {
                    this.prArticle.feature_photo_mode = 'featured_and_inline';
                }
            }

            if (this.currentArticleType === 'pr-full-feature') {
                if (force || !this.prArticle.quote_count || this.prArticle.quote_count > 3) {
                    this.prArticle.quote_count = 2;
                }
                if (!['keywords', 'url', 'none'].includes(this.prArticle.expert_source_mode)) {
                    this.prArticle.expert_source_mode = 'keywords';
                }
                this.prArticle.include_subject_name_in_title = this.prArticle.include_subject_name_in_title !== false;
                this.prArticle.feature_photo_mode = 'featured_and_inline';
            }

            if (!this.prArticle.inline_photo_target || this.prArticle.inline_photo_target < 2) {
                this.prArticle.inline_photo_target = 3;
            }
        },

        currentPrArticlePromptSlug(polish = false) {
            if (this.currentArticleType === 'pr-full-feature') {
                return polish ? 'pr-full-feature-polish' : 'pr-full-feature-spin';
            }
            if (this.currentArticleType === 'expert-article') {
                return polish ? 'expert-article-polish' : 'expert-article-spin';
            }
            return null;
        },

        currentWorkflowPromptSlug(polish = false) {
            if (this.currentArticleType === 'press-release') {
                return this.currentPressReleasePromptSlug(polish);
            }
            if (this.isPrArticleMode()) {
                return this.currentPrArticlePromptSlug(polish);
            }
            return null;
        },


        resolvePrArticleTypeFromState(explicitType = '', state = null) {
            const snapshot = state && typeof state === 'object' ? state : {};
            const templateOverrides = snapshot.template_overrides && typeof snapshot.template_overrides === 'object'
                ? snapshot.template_overrides
                : (this.template_overrides && typeof this.template_overrides === 'object' ? this.template_overrides : {});
            const pressReleaseState = this.normalizePressReleaseState(
                snapshot.pressRelease && typeof snapshot.pressRelease === 'object'
                    ? snapshot.pressRelease
                    : (this.pressRelease || {})
            );
            const prArticleState = snapshot.prArticle && typeof snapshot.prArticle === 'object'
                ? snapshot.prArticle
                : (this.prArticle && typeof this.prArticle === 'object' ? this.prArticle : {});
            const prSubjectData = snapshot.prSubjectData && typeof snapshot.prSubjectData === 'object'
                ? snapshot.prSubjectData
                : (this.prSubjectData && typeof this.prSubjectData === 'object' ? this.prSubjectData : {});
            const selectedPrProfiles = Array.isArray(snapshot.selectedPrProfiles)
                ? snapshot.selectedPrProfiles
                : (Array.isArray(this.selectedPrProfiles) ? this.selectedPrProfiles : []);
            const validTypes = new Set(['editorial', 'opinion', 'news-report', 'local-news', 'expert-article', 'pr-full-feature', 'press-release', 'listicle']);
            const candidates = [
                explicitType,
                templateOverrides.article_type,
                snapshot.article_type,
                snapshot.currentArticleType,
                pressReleaseState.article_type,
                prArticleState.article_type,
                this.template_overrides?.article_type,
                this.pressRelease?.article_type,
                this.currentArticleType,
            ].map((value) => String(value || '').trim()).filter(Boolean);

            for (const candidate of candidates) {
                if (validTypes.has(candidate)) {
                    return candidate;
                }
            }

            const hasPrSubjects = selectedPrProfiles.length > 0
                || Number(prArticleState.main_subject_id || this.prArticle?.main_subject_id || 0) > 0
                || Object.keys(prSubjectData || {}).length > 0;
            if (hasPrSubjects) {
                return 'pr-full-feature';
            }

            const hasPressReleaseContent = !!(
                pressReleaseState.submit_method
                || pressReleaseState.content_dump
                || pressReleaseState.public_url
                || (Array.isArray(pressReleaseState.document_files) && pressReleaseState.document_files.length > 0)
                || pressReleaseState.notion_book?.id
                || pressReleaseState.notion_person?.name
                || pressReleaseState.notion_episode?.id
                || pressReleaseState.notion_guest?.name
                || (Array.isArray(pressReleaseState.detected_photos) && pressReleaseState.detected_photos.length > 0)
            );
            if (hasPressReleaseContent) {
                return 'press-release';
            }

            return '';
        },

        async handleArticleTypeChange(nextType = null) {
            if (nextType !== null && nextType !== undefined) {
                if (!this.template_overrides || typeof this.template_overrides !== 'object') {
                    this.template_overrides = {};
                }
                this.template_overrides.article_type = String(nextType || '').trim();
            }
            await new Promise((resolve) => {
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(resolve);
                    return;
                }
                resolve();
            });
            if (!this.template_dirty || typeof this.template_dirty !== 'object') {
                this.template_dirty = {};
            }
            this.template_dirty.article_type = true;
            this.syncTemplateSelectionForArticleType();
            this.autoSelectPrSource();
            this.syncPrArticleForCurrentArticleType({ force: false });
            this.invalidatePromptPreview('article_type_changed');
            this.$nextTick(() => this._ensureCreateArticleStepReady?.('article_type_changed'));
            this.savePipelineState();
            const stateSaved = typeof this.flushPipelineStateNow === 'function'
                ? await this.flushPipelineStateNow()
                : true;
            if (stateSaved === false || this._restoring) {
                return;
            }
            if (typeof this.saveDraftNow === 'function') {
                await this.saveDraftNow(true);
            }
        },

        draftTitleLooksPlaceholder(value = null) {
            const raw = String(value ?? this.articleTitle ?? '').trim();
            if (!raw) return true;
            if (/^untitled(?:\s+pipeline\s+draft)?$/i.test(raw)) return true;
            if (this.isPrArticleMode && this.isPrArticleMode() && this.isWeakPrArticleTitle && this.isWeakPrArticleTitle(raw)) return true;
            return false;
        },

        smartDraftTitleCandidate() {
            const uploadedName = String(this.uploadedSourceDoc?.name || '').replace(/\.(docx?|pdf)$/i, '').trim();
            const firstSourceTitle = String(((this.sources || []).find((source) => String(source?.title || '').trim())?.title) || '').trim();

            if (this.currentArticleType === 'press-release') {
                const method = String(this.pressRelease?.submit_method || '').trim();
                const bookTitle = String(this.pressRelease?.notion_book?.title || '').trim();
                const authorName = String(this.pressRelease?.notion_person?.name || this.pressRelease?.notion_book?.author || '').trim();
                if (method === 'notion-book' && bookTitle) {
                    return authorName && !bookTitle.toLowerCase().includes(authorName.toLowerCase()) ? (authorName + ' - ' + bookTitle) : bookTitle;
                }

                const episodeTitle = String(this.pressRelease?.notion_episode?.title || '').trim();
                if (method === 'notion-podcast' && episodeTitle) {
                    return episodeTitle;
                }

                const importedTitle = String(this.pressRelease?.public_url_title || this.pressRelease?.detected_title || '').trim();
                if (importedTitle) return importedTitle;
                if (uploadedName) return uploadedName;
                if (firstSourceTitle) return firstSourceTitle;
            }

            if (this.isPrArticleMode && this.isPrArticleMode()) {
                const contextTitle = String(this.prArticle?.expert_context_extracted?.title || '').trim();
                if (contextTitle) return contextTitle;
            }

            if (uploadedName) return uploadedName;
            if (firstSourceTitle) return firstSourceTitle;
            return '';
        },

        maybeApplySmartDraftTitle() {
            if (!this.draftTitleLooksPlaceholder(this.articleTitle)) {
                return false;
            }

            const candidate = String(this.smartDraftTitleCandidate ? this.smartDraftTitleCandidate() : '').trim();
            if (!candidate) return false;

            const normalized = this.ensurePrArticleTitleSubject && this.isPrArticleMode && this.isPrArticleMode()
                ? this.ensurePrArticleTitleSubject(candidate)
                : candidate;

            if (!normalized || normalized === String(this.articleTitle || '').trim()) {
                return false;
            }

            this.articleTitle = normalized;
            return true;
        },

        deriveArticleTitleFromHtml(html = '') {
            const raw = String(html || '').trim();
            if (!raw) return '';

            try {
                const container = document.createElement('div');
                container.innerHTML = raw;
                const headings = Array.from(container.querySelectorAll('h1, h2'));
                for (const heading of headings) {
                    const text = String(heading.textContent || '').replace(/\s+/g, ' ').trim();
                    if (text && text.length >= 18) {
                        return text;
                    }
                }

                const firstParagraph = container.querySelector('p');
                const paragraphText = String(firstParagraph?.textContent || '').replace(/\s+/g, ' ').trim();
                if (paragraphText) {
                    const sentence = paragraphText.split(/(?<=[.!?])\s+/)[0].trim().replace(/^[–—\-]\s*/, '');
                    if (sentence.length >= 30 && sentence.length <= 140) {
                        return sentence;
                    }
                }
            } catch (e) {}

            return '';
        },

        headlineCaseFromText(value = '', wordLimit = 10) {
            const cleaned = String(value || '')
                .replace(/https?:\/\/\S+/gi, ' ')
                .replace(/[\r\n]+/g, ' ')
                .replace(/[|•]+/g, ' ')
                .replace(/[_]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (!cleaned) return '';

            const words = cleaned.split(/\s+/).filter(Boolean).slice(0, wordLimit);
            const stop = new Set(['and','or','the','a','an','of','for','to','in','on','with','by','from','at','into','about']);
            return words.map((word, idx) => {
                const lower = word.toLowerCase();
                if (idx > 0 && stop.has(lower)) return lower;
                return lower.charAt(0).toUpperCase() + lower.slice(1);
            }).join(' ').replace(/[,:;\-]+$/, '').trim();
        },

        cleanPrTitleAngleCandidate(value = '', wordLimit = 12) {
            let cleaned = String(value || '')
                .replace(/https?:\/\/\S+/gi, ' ')
                .replace(/[\r\n]+/g, ' ')
                .replace(/[|•]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (!cleaned) return '';

            cleaned = cleaned
                .replace(/^title\s*:\s*/i, '')
                .replace(/^(focus instructions?|article focus instructions?|quote guidance|subject position|topic context|editorial context|keywords?)\s*:\s*/i, '')
                .replace(/^(focus on|highlight|show|explore|examine|discuss|cover|write about|explain|describe|use|build|select|define)\s+/i, '')
                .replace(/\b(?:the article|this article|the writer|writer|must|should)\b/gi, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            const headline = this.headlineCaseFromText(cleaned, wordLimit)
                .replace(/^[—:;\-\s]+|[—:;\-\s]+$/g, '')
                .trim();

            if (!headline) return '';
            if (/\b(?:a|an)?\s*(?:feature|expert|company|person|executive)\s+profile\b/i.test(headline)) {
                return '';
            }

            return headline;
        },

        isWeakPrArticleTitle(value = '') {
            const raw = String(value || '').trim();
            if (!raw) return true;

            const normalized = this.normalizeArticleHeadingText(raw);
            const subjectNames = (this.selectedPrProfiles || [])
                .map((profile) => this.normalizeArticleHeadingText(profile?.name || ''))
                .filter(Boolean);

            if (!normalized || normalized === 'untitled' || normalized === 'untitled pipeline draft') {
                return true;
            }

            if (/\b(?:a|an)?\s*(?:feature|expert|company|person|executive)\s+profile\b/i.test(raw)) {
                return true;
            }

            if (/^[^:]+:\s*(?:a|an)?\s*(?:feature|expert|company|person|executive)\s+profile$/i.test(raw)) {
                return true;
            }

            if (subjectNames.includes(normalized)) {
                return true;
            }

            return normalized.split(' ').length <= 3 && subjectNames.some((name) => normalized === name);
        },

        ensurePrArticleTitleSubject(title = '') {
            const cleaned = String(title || '').replace(/\s+/g, ' ').trim();
            if (!cleaned || !this.isPrArticleMode()) {
                return cleaned;
            }

            const mainSubject = this.selectedPrProfiles.find(p => Number(p.id) === Number(this.prArticle.main_subject_id || 0)) || this.selectedPrProfiles[0] || null;
            const subjectName = String(mainSubject?.name || '').trim();
            if (!subjectName) {
                return cleaned;
            }

            const normalizedSubject = this.normalizeArticleHeadingText(subjectName);
            const normalizedTitle = this.normalizeArticleHeadingText(cleaned);
            if (!normalizedSubject || normalizedTitle.includes(normalizedSubject)) {
                return cleaned;
            }

            if (this.currentArticleType === 'pr-full-feature' && this.prArticle.include_subject_name_in_title) {
                return `${subjectName}: ${cleaned}`;
            }

            return cleaned;
        },

        prArticleAngleHeadline() {
            const mainSubject = this.selectedPrProfiles.find(p => Number(p.id) === Number(this.prArticle.main_subject_id || 0)) || this.selectedPrProfiles[0] || null;
            const mainPd = mainSubject ? (this.prSubjectData[mainSubject.id] || {}) : {};
            const candidates = [];
            const push = (candidate, limit = 12) => {
                const cleaned = this.cleanPrTitleAngleCandidate(candidate, limit);
                if (!cleaned) return;
                if (candidates.some((existing) => existing.toLowerCase() === cleaned.toLowerCase())) return;
                candidates.push(cleaned);
            };

            push(this.prArticle?.focus_instructions || '', 12);
            push(this.prArticle?.expert_context_extracted?.title || '', 12);
            push(this.prArticle?.expert_keywords || '', 10);
            push(this.prArticle?.subject_position || '', 10);
            push(mainSubject?.context || '', 10);

            for (const field of (mainPd.fields || [])) {
                const label = String(field?.notion_field || field?.key || '').toLowerCase();
                const value = String(field?.display_value || field?.value || '').trim();
                if (!value) continue;
                if (/(short description|description|summary|about|title|job title|industry|expertise)/i.test(label)) {
                    push(value, /(description|about|summary)/i.test(label) ? 9 : 8);
                }
                if (candidates.length >= 4) break;
            }

            return candidates[0] || '';
        },

        normalizePrGeneratedTitles(values = []) {
            const seen = new Set();
            const cleaned = [];

            for (const rawValue of (Array.isArray(values) ? values : [])) {
                let candidate = String(rawValue || '').replace(/^title\s*:\s*/i, '').replace(/\s+/g, ' ').trim();
                if (!candidate) continue;
                candidate = this.ensurePrArticleTitleSubject(candidate);
                if (this.isWeakPrArticleTitle(candidate)) continue;
                const key = candidate.toLowerCase();
                if (seen.has(key)) continue;
                seen.add(key);
                cleaned.push(candidate);
            }

            const fallback = this.fallbackPrArticleTitle();
            if (fallback) {
                const fallbackKey = fallback.toLowerCase();
                if (!seen.has(fallbackKey)) {
                    cleaned.push(fallback);
                }
            }

            return cleaned.slice(0, 10);
        },

        fallbackPrArticleTitle() {
            const mainSubject = this.selectedPrProfiles.find(p => Number(p.id) === Number(this.prArticle.main_subject_id || 0)) || this.selectedPrProfiles[0] || null;
            const subjectName = mainSubject?.name || 'Untitled';
            const angleHeadline = this.prArticleAngleHeadline();

            if (this.currentArticleType === 'expert-article') {
                if (angleHeadline) {
                    if (this.normalizeArticleHeadingText(angleHeadline).includes(this.normalizeArticleHeadingText(subjectName))) {
                        return angleHeadline;
                    }
                    return `${subjectName} on ${angleHeadline}`.trim();
                }
            }

            if (this.currentArticleType === 'pr-full-feature' && subjectName !== 'Untitled') {
                if (angleHeadline) {
                    if (this.normalizeArticleHeadingText(angleHeadline).includes(this.normalizeArticleHeadingText(subjectName))) {
                        return angleHeadline;
                    }
                    return `${subjectName}: ${angleHeadline}`.trim();
                }
                return /^organization$/i.test(String(mainSubject?.type || '')) ? `Inside ${subjectName}` : `${subjectName} in Focus`;
            }

            return subjectName;
        },

        prPhotoFieldLabel(photo = {}) {
            return String(photo.property || photo.field || photo.source_field || photo.name || '').trim();
        },

        prFriendlyPhotoLabel(profile = {}, photo = {}, fallbackIndex = 1) {
            const source = String(photo.source || '').toLowerCase();
            const fieldLabel = this.prPhotoFieldLabel(photo);
            const rawName = String(photo.name || '').trim();
            const profileName = String(profile?.name || 'Subject').trim() || 'Subject';
            const cleanedFilename = rawName.replace(/\.[a-z0-9]{2,6}$/i, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
            const fieldLike = /(featured image url|profile image|headshot|photo url|portrait|image url)/i.test(fieldLabel);
            const fileLike = /\.(jpg|jpeg|png|webp|gif|avif)$/i.test(rawName);

            if (source === 'notion-profile') {
                if (fieldLike || fileLike || !rawName) {
                    return `${profileName} profile photo`;
                }
                return rawName;
            }

            if (fileLike && cleanedFilename) {
                return cleanedFilename;
            }

            return rawName || fieldLabel || `${profileName} photo ${fallbackIndex}`;
        },

        prPhotoSourcePriority(photo = {}) {
            const source = String(photo.source || '').toLowerCase();
            if (source === 'notion-profile') return 0;
            if (source === 'notion-drive') return 1;
            return 2;
        },

        normalizePrSearchTerm(value = '') {
            return String(value || '')
                .replace(/https?:\/\/\S+/gi, ' ')
                .replace(/[_]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        },

        extractPrSearchPhrasesFromText(value = '', limit = 6) {
            const cleaned = this.normalizePrSearchTerm(value);
            if (!cleaned) return [];

            const phrases = [];
            const push = (candidate) => {
                const term = this.normalizePrSearchTerm(candidate);
                if (!term) return;
                if (/^(featured image url|profile photo|image|photo|url)$/i.test(term)) return;
                if (/\.(jpg|jpeg|png|webp|gif|avif)$/i.test(term)) return;
                if (term.length < 4 || term.length > 80) return;
                if (phrases.some((existing) => existing.toLowerCase() === term.toLowerCase())) return;
                phrases.push(term);
            };

            cleaned.split(/[\r\n]+/).forEach((chunk) => {
                chunk.split(/\s*[.;!?|]\s*|\s*,\s*/).forEach((part) => push(part));
            });

            if (!phrases.length) {
                push(cleaned.split(/\s+/).slice(0, 8).join(' '));
            }

            return phrases.slice(0, limit);
        },

        buildPrArticleSearchTerms(limit = 12) {
            const terms = [];
            const add = (candidate) => {
                const term = this.normalizePrSearchTerm(candidate);
                if (!term) return;
                if (/^https?:\/\//i.test(term)) return;
                if (/\.(jpg|jpeg|png|webp|gif|avif)$/i.test(term)) return;
                if (/^(featured image url|profile photo|image|photo|url)$/i.test(term)) return;
                if (term.length < 3 || term.length > 90) return;
                if (terms.some((existing) => existing.toLowerCase() === term.toLowerCase())) return;
                terms.push(term);
            };

            const maybeAddFieldTerms = (label, value) => {
                const fieldLabel = String(label || '').toLowerCase();
                const fieldValue = String(value || '').trim();
                if (!fieldValue) return;

                if (/(title|job title|occupation|company|business\/company name|organizations founded|industry|expertise|topic|location|country)/i.test(fieldLabel)) {
                    this.extractPrSearchPhrasesFromText(fieldValue, 2).forEach(add);
                    return;
                }

                if (/(biography|description|about|summary)/i.test(fieldLabel)) {
                    this.extractPrSearchPhrasesFromText(fieldValue, 3).forEach(add);
                }
            };

            for (const profile of (this.selectedPrProfiles || [])) {
                add(profile?.name || '');
                if (profile?.description) {
                    this.extractPrSearchPhrasesFromText(profile.description, 2).forEach(add);
                }
                if (profile?.context) {
                    this.extractPrSearchPhrasesFromText(profile.context, 2).forEach(add);
                }

                const pd = this.prSubjectData[profile.id] || {};
                for (const field of (pd.fields || [])) {
                    maybeAddFieldTerms(field?.notion_field || field?.key, field?.display_value || field?.value || '');
                }

                for (const rel of (pd.relations || [])) {
                    for (const entry of (rel.entries || [])) {
                        if (!pd.selectedEntries?.[entry.id]) continue;
                        add(entry?.title || '');
                        if (entry?.detail?.properties) {
                            for (const [key, value] of Object.entries(entry.detail.properties || {})) {
                                if (typeof value === 'string') {
                                    maybeAddFieldTerms(key, value);
                                }
                            }
                        }
                    }
                }
            }

            this.extractPrSearchPhrasesFromText(this.prArticle?.expert_keywords || '', 4).forEach(add);
            this.extractPrSearchPhrasesFromText(this.prArticle?.focus_instructions || '', 4).forEach(add);
            this.extractPrSearchPhrasesFromText(this.prArticle?.subject_position || '', 3).forEach(add);

            return terms.slice(0, limit);
        },

        currentArticleSearchTerms() {
            if (this.isPrArticleMode()) {
                return this.buildPrArticleSearchTerms();
            }
            return [...new Set([...this.suggestedTags, ...this.photoSuggestions.map(p => p.search_term)].filter(Boolean))];
        },

        prPhotoAudit(profileId) {
            const profile = (this.selectedPrProfiles || []).find((candidate) => Number(candidate.id) === Number(profileId)) || null;
            const pd = this.prSubjectData[profileId] || {};
            const photos = Array.isArray(pd.photos) ? pd.photos : [];
            const direct = photos.filter((photo) => this.prProfilePhotoSource(photo, pd.driveUrl) !== 'notion-drive');
            const drive = photos.filter((photo) => this.prProfilePhotoSource(photo, pd.driveUrl) === 'notion-drive');
            const selectedInlineIds = Object.keys(pd.selectedInlinePhotos || {}).filter((id) => pd.selectedInlinePhotos[id]);
            const selectedInline = this.prInlinePhotoCandidates(profileId).filter((photo) => selectedInlineIds.includes(String(photo.id)));
            const directFields = [...new Set(direct.map((photo) => this.prPhotoFieldLabel(photo)).filter(Boolean).filter((label) => !/\.(jpg|jpeg|png|webp|gif|avif)$/i.test(label)))];
            const featuredPhoto = Number(profileId) === Number(this.prArticle.main_subject_id || this.selectedPrProfiles[0]?.id || 0)
                ? this.prSelectedFeaturedPhoto(profileId)
                : null;

            return {
                directCount: direct.length,
                driveCount: drive.length,
                driveAvailable: !!pd.driveUrl,
                driveLoaded: drive.length > 0,
                inlineSelectedCount: selectedInline.length,
                directFields,
                featuredLabel: featuredPhoto ? this.prFriendlyPhotoLabel(profile || {}, featuredPhoto, 1) : '',
                featuredSourceLabel: featuredPhoto ? this.prPhotoOriginAuditLabel(profile || {}, featuredPhoto, pd.driveUrl) : '',
            };
        },

        async loadDriveFallbackPhotos(profileId) {
            const profile = (this.selectedPrProfiles || []).find((candidate) => Number(candidate.id) === Number(profileId));
            if (!profile) return;
            return this.loadProfilePhotos(profile);
        },

        selectAllPrPhotos(profileId) {
            return this.selectAllPrInlinePhotos(profileId);
        },

        clearPrPhotos(profileId) {
            return this.clearPrInlinePhotos(profileId);
        },

        selectAllPrInlinePhotos(profileId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            pd.selectedInlinePhotos = {};
            this.prInlinePhotoCandidates(profileId).forEach((photo) => {
                pd.selectedInlinePhotos[photo.id] = true;
            });
            this.hydratePrArticleSelectedMedia();
            this.savePipelineState();
            this.queueAutoSaveDraft?.(250);
        },

        clearPrInlinePhotos(profileId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            pd.selectedInlinePhotos = {};
            this.hydratePrArticleSelectedMedia();
            this.savePipelineState();
            this.queueAutoSaveDraft?.(250);
        },

        selectAllPrRelationEntries(profileId, slug) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = (pd.relations || []).find((candidate) => candidate.slug === slug);
            if (!rel) return;
            pd.selectedEntries = pd.selectedEntries || {};
            (rel.entries || []).forEach((entry) => {
                pd.selectedEntries[entry.id] = true;
            });
            this.savePipelineState();
        },

        clearPrRelationEntries(profileId, slug) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = (pd.relations || []).find((candidate) => candidate.slug === slug);
            if (!rel) return;
            pd.selectedEntries = pd.selectedEntries || {};
            (rel.entries || []).forEach((entry) => {
                delete pd.selectedEntries[entry.id];
            });
            this.savePipelineState();
        },

        normalizeArticleHeadingText(value = '') {
            return String(value || '')
                .replace(/\s+/g, ' ')
                .replace(/[“”]/g, '"')
                .replace(/[‘’]/g, "'")
                .trim()
                .toLowerCase();
        },

        ensureArticleTitleInHtml(html = '', title = '') {
            const raw = String(html || '').trim();
            if (!raw) return '';

            try {
                const container = document.createElement('div');
                container.innerHTML = raw;
                const firstHeading = container.querySelector('h1');
                if (!firstHeading) {
                    return raw;
                }

                const headingText = this.normalizeArticleHeadingText(firstHeading.textContent || '');
                const titleText = this.normalizeArticleHeadingText(title || '');
                const h1Count = container.querySelectorAll('h1').length;

                if (headingText && (!titleText || headingText === titleText || h1Count === 1)) {
                    firstHeading.remove();
                    return container.innerHTML.trim();
                }
            } catch (e) {}

            return raw;
        },

        normalizeExistingPrArticleState() {
            if (!this.isPrArticleMode || !this.isPrArticleMode()) {
                return;
            }

            const currentTitle = String(this.articleTitle || '').trim();
            const normalizedTitles = this.normalizePrGeneratedTitles ? this.normalizePrGeneratedTitles(currentTitle ? [currentTitle] : []) : [];
            const normalizedTitle = String(normalizedTitles[0] || '').trim();
            if (normalizedTitle && (this.isWeakPrArticleTitle?.(currentTitle) || normalizedTitle !== currentTitle)) {
                this.articleTitle = this.ensurePrArticleTitleSubject ? this.ensurePrArticleTitleSubject(normalizedTitle) : normalizedTitle;
            }

            const currentHtml = String(this.editorContent || this.spunContent || '').trim();
            if (!currentHtml || !this.ensureArticleTitleInHtml) {
                return;
            }

            const cleaned = this.ensureArticleTitleInHtml(currentHtml, this.articleTitle || currentTitle || normalizedTitle);
            if (!cleaned || cleaned === currentHtml) {
                return;
            }

            this.editorContent = cleaned;
            this.spunContent = cleaned;
            this.spunWordCount = this.countWordsFromHtml(cleaned);
            this.rememberDraftBody(cleaned);
            this.extractArticleLinks(cleaned);
            this.$nextTick(() => this.setSpinEditor(cleaned));
        },

        looksLikeGoogleDriveFolderUrl(url = '') {
            const raw = String(url || '').trim();
            if (!raw) return false;

            try {
                const parsed = new URL(raw);
                if (!['drive.google.com', 'docs.google.com'].includes(parsed.hostname)) {
                    return false;
                }

                const path = String(parsed.pathname || '').toLowerCase();
                return path.includes('/folders/') || path.includes('/drive/folders/');
            } catch (e) {
                return false;
            }
        },

        notionDatabaseLabel(typeOrSlug = '') {
            const normalized = String(typeOrSlug || '').toLowerCase();
            if (normalized.includes('organization') || normalized.includes('company')) {
                return 'Company Database';
            }
            return 'Person Database';
        },

        notionTableLabelForProfile(profile = {}) {
            return this.notionDatabaseLabel(profile?.type_slug || profile?.type || '');
        },

        notionAuditFieldLabel(entry = {}) {
            return String(entry?.field || entry?.notion_field || entry?.key || '').trim();
        },

        notionAuditSourceLabel(entry = {}, fallbackTable = '') {
            const table = String(entry?.source_table || fallbackTable || '').trim();
            const field = String(entry?.source_field || entry?.field || entry?.notion_field || entry?.key || '').trim();
            if (table && field) return `${table} > ${field}`;
            return table || field || 'Notion';
        },

        notionProfileFieldSourceLabel(profile = {}, row = {}) {
            const table = this.notionDatabaseLabel(profile?.type_slug || profile?.type || '');
            const field = String(row?.notion_field || row?.key || row?.field || '').trim();
            if (table && field) return `${table} > ${field}`;
            return table || field || 'Notion';
        },

        prProfilePhotoUrl(photo = {}) {
            return this.toAbsoluteMediaUrl(photo?.webContentLink || photo?.webViewLink || photo?.thumbnailLink || photo?.url || '');
        },

        prProfilePhotoSource(photo = {}, driveUrl = '') {
            const explicit = String(photo?.source || '').trim().toLowerCase();
            const fieldLabel = this.prPhotoFieldLabel(photo);
            const rawName = String(photo?.name || '').trim();
            const fileLike = /\.(jpg|jpeg|png|webp|gif|avif)$/i.test(fieldLabel) || /\.(jpg|jpeg|png|webp|gif|avif)$/i.test(rawName);
            if (explicit === 'notion-drive') return 'notion-drive';
            if (explicit === 'notion-profile' && driveUrl && fileLike) return 'notion-drive';
            if (explicit) return explicit;
            if (driveUrl && fileLike) return 'notion-drive';
            return 'notion-profile';
        },

        prPhotoSourceLabel(photo = {}, driveUrl = '') {
            return this.prProfilePhotoSource(photo, driveUrl) === 'notion-drive'
                ? 'Google Drive gallery'
                : 'Direct Notion field';
        },

        prPhotoOriginAuditLabel(profile = {}, photo = {}, driveUrl = '') {
            if (!photo) return '';
            if (this.prProfilePhotoSource(photo, driveUrl) === 'notion-drive') {
                return 'Google Drive gallery';
            }
            const fieldLabel = this.prPhotoFieldLabel(photo);
            const tableLabel = this.notionTableLabelForProfile(profile);
            return fieldLabel ? `${tableLabel} > ${fieldLabel}` : `${tableLabel} direct photo`;
        },

        prPhotoIsAvatarLike(photo = {}, driveUrl = '') {
            const haystack = [
                photo?.thumbnailLink,
                photo?.webContentLink,
                photo?.webViewLink,
                photo?.url,
                photo?.name,
                this.prPhotoFieldLabel(photo),
                this.prProfilePhotoSource(photo, driveUrl),
            ].join(' ').toLowerCase();

            return /(linkedin|profile[-_]?displayphoto|shrink[_-]?800|googleusercontent|avatar|headshot-preview|profile-photo-preview)/i.test(haystack);
        },

        prFeaturedPhotoPriority(photo = {}, driveUrl = '') {
            const source = this.prProfilePhotoSource(photo, driveUrl);
            const label = this.prPhotoFieldLabel(photo);
            const ratio = Number(photo.width || 0) > 0 && Number(photo.height || 0) > 0
                ? Number(photo.width || 0) / Math.max(1, Number(photo.height || 1))
                : 0;
            let score = 100;

            if (source === 'notion-profile') score -= 40;
            if (source === 'notion-drive') score -= 25;
            if (/(featured image|profile image|headshot|portrait|professional photo|featured)/i.test(label)) score -= 35;
            if (/(personal photos|professional photos|gallery|album)/i.test(label)) score -= 15;
            if (this.prPhotoIsAvatarLike(photo, driveUrl)) score += 80;
            if (ratio > 1.15 && ratio < 2.2) score -= 12;
            if (ratio > 0 && ratio < 0.8) score += 20;

            return score;
        },

        prInlinePhotoPriority(photo = {}, driveUrl = '') {
            const source = this.prProfilePhotoSource(photo, driveUrl);
            const label = this.prPhotoFieldLabel(photo);
            let score = 100;

            if (source === 'notion-drive') score -= 45;
            if (source === 'notion-profile') score -= 15;
            if (/(personal photos|professional photos|gallery|album)/i.test(label)) score -= 20;
            if (/(featured image|profile image|headshot|portrait)/i.test(label)) score -= 5;
            if (this.prPhotoIsAvatarLike(photo, driveUrl)) score += 70;

            return score;
        },

        prPhotoCandidates(profileId) {
            const pd = this.prSubjectData[profileId] || {};
            const photos = Array.isArray(pd.photos) ? pd.photos : [];
            return [...photos].sort((a, b) => this.prFeaturedPhotoPriority(a, pd.driveUrl) - this.prFeaturedPhotoPriority(b, pd.driveUrl));
        },

        prFeaturedPhotoCandidates(profileId) {
            return this.prPhotoCandidates(profileId);
        },

        prInlinePhotoCandidates(profileId) {
            const pd = this.prSubjectData[profileId] || {};
            const photos = Array.isArray(pd.photos) ? pd.photos : [];
            const featuredId = String(pd.selectedFeaturedPhotoId || '');
            return photos
                .filter((photo) => String(photo.id) !== featuredId)
                .sort((a, b) => this.prInlinePhotoPriority(a, pd.driveUrl) - this.prInlinePhotoPriority(b, pd.driveUrl));
        },

        prSelectedFeaturedPhoto(profileId = null) {
            const resolvedProfileId = Number(profileId || this.prArticle.main_subject_id || this.selectedPrProfiles[0]?.id || 0);
            if (!resolvedProfileId) return null;
            const pd = this.prSubjectData[resolvedProfileId] || {};
            const candidates = this.prFeaturedPhotoCandidates(resolvedProfileId);
            const selectedId = String(pd.selectedFeaturedPhotoId || '');
            return candidates.find((photo) => String(photo.id) === selectedId) || candidates[0] || null;
        },

        isPrimaryPrProfilePhoto(profile = {}, photo = {}) {
            if (this.currentArticleType === 'expert-article' && this.prArticle.feature_photo_mode === 'inline_only') {
                return false;
            }
            const primary = this.selectedPrFeaturedAsset() || null;
            if (!primary) return false;
            return this.prProfilePhotoUrl(photo) === this.toAbsoluteMediaUrl(primary.url_large || primary.url || '');
        },

        normalizePrProfileForState(profile = {}) {
            const raw = JSON.parse(JSON.stringify(profile || {}));
            return {
                id: raw.id ?? null,
                name: raw.name || '',
                type: raw.type || '—',
                type_slug: raw.type_slug || '',
                description: raw.description || '',
                photo_url: raw.photo_url || '',
                external_source: raw.external_source || '',
                external_id: raw.external_id || '',
                context: raw.context || '',
                fields: raw.fields && typeof raw.fields === 'object' ? raw.fields : {},
            };
        },

        prPhotoUrlIsBlocked(url = '') {
            const raw = String(url || '').trim();
            if (!raw) {
                return true;
            }

            try {
                const parsed = new URL(raw);
                const host = String(parsed.hostname || '').toLowerCase();
                return host === 'media.licdn.com' || host.endsWith('.licdn.com');
            } catch (e) {
                return false;
            }
        },

        normalizePrSelectionMap(selection = {}) {
            const normalized = {};
            Object.entries(selection || {}).forEach(([key, value]) => {
                if (value) {
                    normalized[String(key)] = true;
                }
            });
            return normalized;
        },

        normalizePrPhotoCandidate(photo = {}) {
            const raw = JSON.parse(JSON.stringify(photo || {}));
            const url = raw.webContentLink || raw.webViewLink || raw.thumbnailLink || raw.url || raw.url_large || raw.url_full || '';
            if (!url) {
                return null;
            }

            if (this.looksLikeGoogleDriveFolderUrl(url)) {
                return null;
            }

            if (this.prPhotoUrlIsBlocked(url)) {
                return null;
            }

            const propertyLabel = String(raw.property || raw.source_field || raw.field || '').trim();
            const rawName = String(raw.name || raw.label || '').trim();
            const fileLike = /\.(jpg|jpeg|png|webp|gif|avif)$/i.test(propertyLabel) || /\.(jpg|jpeg|png|webp|gif|avif)$/i.test(rawName);
            const inferredSource = String(raw.source || '').trim().toLowerCase() || (fileLike ? 'notion-drive' : 'notion-profile');

            return {
                id: raw.id ? String(raw.id) : ('photo-' + Math.random().toString(36).slice(2, 10)),
                name: raw.name || raw.label || raw.property || 'Subject Photo',
                property: raw.property || raw.source_field || raw.field || '',
                source: inferredSource,
                source_url: raw.source_url || raw.webViewLink || raw.webContentLink || raw.thumbnailLink || url,
                thumbnailLink: raw.thumbnailLink || url,
                webContentLink: raw.webContentLink || url,
                webViewLink: raw.webViewLink || url,
                width: Number(raw.width || 0),
                height: Number(raw.height || 0),
            };
        },

        mergePrPhotoCollections(existing = [], incoming = []) {
            const merged = [];
            const seen = new Set();

            [...(Array.isArray(existing) ? existing : []), ...(Array.isArray(incoming) ? incoming : [])].forEach((photo) => {
                const normalized = this.normalizePrPhotoCandidate(photo);
                if (!normalized) return;
                const key = normalized.webContentLink || normalized.webViewLink || normalized.thumbnailLink;
                if (!key || seen.has(key)) return;
                seen.add(key);
                merged.push(normalized);
            });

            return merged;
        },

        sanitizePrSubjectDataForPersistence(subjectData = {}) {
            const normalized = {};

            Object.entries(subjectData || {}).forEach(([profileId, rawState]) => {
                const state = rawState && typeof rawState === 'object' ? rawState : {};
                const relations = Array.isArray(state.relations) ? state.relations : [];
                const photos = this.mergePrPhotoCollections([], state.photos || []);
                const fields = Array.isArray(state.fields) ? state.fields.map((field) => ({
                    key: field?.key || '',
                    notion_field: field?.notion_field || field?.key || '',
                    value: typeof field?.value === 'string' ? field.value : (field?.value ?? ''),
                    display_value: typeof field?.display_value === 'string' ? field.display_value : (field?.display_value ?? ''),
                })).filter(field => field.key || field.notion_field) : [];

                normalized[String(profileId)] = {
                    loading: false,
                    loaded: !!state.loaded || fields.length > 0 || relations.length > 0 || photos.length > 0,
                    fields,
                    driveUrl: this.looksLikeGoogleDriveFolderUrl(state.driveUrl || '') ? String(state.driveUrl) : '',
                    photos,
                    googleDocs: [],
                    loadingGoogleDocs: false,
                    loadingPhotos: false,
                    notionUrl: String(state.notionUrl || ''),
                    relations: relations.map((rel) => ({
                        slug: rel?.slug || '',
                        label: rel?.label || rel?.slug || '',
                        count: Number(rel?.count || 0),
                        preview_fields: Array.isArray(rel?.preview_fields) ? rel.preview_fields : [],
                        detail_fields: Array.isArray(rel?.detail_fields) ? rel.detail_fields : [],
                        open: rel?.open !== false,
                        loading: false,
                        loaded: rel?.loaded !== false || (Array.isArray(rel?.entries) && rel.entries.length > 0),
                        entries: (Array.isArray(rel?.entries) ? rel.entries : []).map((entry) => ({
                            id: entry?.id ?? null,
                            title: entry?.title || '',
                            preview: entry?.preview && typeof entry.preview === 'object' ? entry.preview : {},
                            open: !!entry?.open,
                            loading: false,
                            detail: null,
                        })),
                    })).filter((rel) => rel.slug),
                    selectedEntries: this.normalizePrSelectionMap(state.selectedEntries || {}),
                    selectedFeaturedPhotoId: state.selectedFeaturedPhotoId ? String(state.selectedFeaturedPhotoId) : '',
                    selectedInlinePhotos: this.normalizePrSelectionMap(state.selectedInlinePhotos || state.selectedPhotos || {}),
                };
            });

            return normalized;
        },

        extractGoogleDocUrlsFromValue(value) {
            if (typeof value !== 'string' || !value) return [];
            const matches = value.match(/https:\/\/docs\.google\.com\/document\/d\/[A-Za-z0-9_-]+[^\s)"]*/g) || [];
            return [...new Set(matches.map((match) => String(match).trim()))];
        },

        collectGoogleDocUrlsFromRecord(record = {}) {
            const urls = [];
            const fields = Array.isArray(record.fields) ? record.fields : [];
            for (const field of fields) {
                urls.push(...this.extractGoogleDocUrlsFromValue(field?.display_value || ''));
                urls.push(...this.extractGoogleDocUrlsFromValue(field?.value || ''));
            }

            const properties = record?.detail?.properties && typeof record.detail.properties === 'object'
                ? record.detail.properties
                : {};
            Object.values(properties).forEach((value) => {
                urls.push(...this.extractGoogleDocUrlsFromValue(typeof value === 'string' ? value : ''));
            });

            return [...new Set(urls)];
        },

        async fetchPrGoogleDocsContext(urls = []) {
            const uniqueUrls = [...new Set((Array.isArray(urls) ? urls : []).map((url) => String(url || '').trim()).filter(Boolean))].slice(0, 8);
            if (uniqueUrls.length === 0 || !this.draftId) {
                return { success: true, documents: [] };
            }

            const resp = await fetch('{{ route("publish.pipeline.pr-article.import-google-docs") }}', {
                method: 'POST',
                headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    draft_id: this.draftId,
                    urls: uniqueUrls,
                }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.success) {
                throw new Error(data.message || 'Failed to read Google Docs context.');
            }

            return data;
        },

        async loadPrGoogleDocsForProfile(profileId) {
            const pd = this.prSubjectData[profileId];
            if (!pd || pd.loadingGoogleDocs) return;
            const urls = this.collectGoogleDocUrlsFromRecord({ fields: pd.fields });
            if (!urls.length) {
                pd.googleDocs = [];
                return;
            }

            pd.loadingGoogleDocs = true;
            try {
                const data = await this.fetchPrGoogleDocsContext(urls);
                pd.googleDocs = Array.isArray(data.documents) ? data.documents : [];
            } catch (e) {
                pd.googleDocs = [];
            } finally {
                pd.loadingGoogleDocs = false;
            }
        },

        async loadPrGoogleDocsForEntry(entry) {
            if (!entry || entry.loadingGoogleDocs) return;
            const urls = this.collectGoogleDocUrlsFromRecord({ detail: entry.detail || {} });
            if (!urls.length) {
                entry.google_docs = [];
                return;
            }

            entry.loadingGoogleDocs = true;
            try {
                const data = await this.fetchPrGoogleDocsContext(urls);
                entry.google_docs = Array.isArray(data.documents) ? data.documents : [];
            } catch (e) {
                entry.google_docs = [];
            } finally {
                entry.loadingGoogleDocs = false;
            }
        },

        async searchPrProfiles() {
            this.prProfileSearching = true;
            this.prProfileDropdownOpen = true;
            try {
                const params = new URLSearchParams({
                    q: this.prProfileSearch || '',
                });
                ['person', 'organization'].forEach((type) => params.append('types[]', type));
                const resp = await fetch('{{ route("publish.profiles.search") }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                this.prProfileResults = await resp.json();
            } catch (e) {
                this.prProfileResults = [];
            }
            this.prProfileSearching = false;
        },

        async resolvePrProfile(profile) {
            if (profile?.id) {
                return profile;
            }

            if (profile?.external_source !== 'notion' || !profile?.external_id || !profile?.bridge_id) {
                return null;
            }

            const response = await fetch('{{ route("publish.profiles.resolve-notion") }}', {
                method: 'POST',
                headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    bridge_id: profile.bridge_id,
                    notion_page_id: profile.external_id,
                    name: profile.name || 'Untitled',
                }),
            });
            const data = await response.json();
            if (!response.ok || !data.success || !data.profile) {
                throw new Error(data.message || 'Failed to load the selected Notion subject.');
            }

            return data.profile;
        },

        async addPrProfile(profile) {
            if (this.selectedPrProfiles.some((selected) => (
                String(selected.id || '') === String(profile.id || profile.local_profile_id || '')
                || (selected.external_source === 'notion' && String(selected.external_id || '') === String(profile.external_id || ''))
            ))) return;

            let resolvedProfile = profile;
            if (!resolvedProfile?.id && resolvedProfile?.external_source === 'notion') {
                try {
                    resolvedProfile = await this.resolvePrProfile(resolvedProfile);
                } catch (error) {
                    this.showNotification('error', error.message || 'Failed to register the selected Notion subject.');
                    return;
                }
            }

            if (!resolvedProfile?.id) {
                this.showNotification('error', 'Selected subject could not be resolved.');
                return;
            }

            const normalizedProfile = this.normalizePrProfileForState({
                ...resolvedProfile,
                context: resolvedProfile.context || profile.context || '',
            });
            this.selectedPrProfiles.push(normalizedProfile);
            this.syncPrArticleForCurrentArticleType();
            this.prProfileSearch = '';
            this.prProfileResults = [];
            this.prProfileDropdownOpen = false;
            this.savePipelineState();
            this.loadProfileData(normalizedProfile);
        },

        removePrProfile(index, profileId) {
            this.selectedPrProfiles.splice(index, 1);
            delete this.prSubjectData[profileId];
            if (Number(this.prArticle.main_subject_id || 0) === Number(profileId || 0)) {
                this.prArticle.main_subject_id = this.selectedPrProfiles[0] ? Number(this.selectedPrProfiles[0].id) : null;
            }
            this.savePipelineState();
        },

        async loadProfileData(profile, options = {}) {
            const preserveSelections = options.preserveSelections !== false;
            const normalizedProfile = this.normalizePrProfileForState(profile);
            const existing = this.prSubjectData[normalizedProfile.id] || {};
            const selectedEntries = preserveSelections ? this.normalizePrSelectionMap(existing.selectedEntries || {}) : {};
            const selectedInlinePhotos = preserveSelections ? this.normalizePrSelectionMap(existing.selectedInlinePhotos || existing.selectedPhotos || {}) : {};
            const selectedFeaturedPhotoId = preserveSelections ? String(existing.selectedFeaturedPhotoId || '') : '';

            this.prSubjectData[normalizedProfile.id] = {
                loading: false,
                loaded: !!existing.loaded,
                fields: Array.isArray(existing.fields) ? existing.fields : [],
                driveUrl: this.looksLikeGoogleDriveFolderUrl(existing.driveUrl || '') ? String(existing.driveUrl) : '',
                photos: this.mergePrPhotoCollections([], existing.photos || []),
                googleDocs: Array.isArray(existing.googleDocs) ? existing.googleDocs : [],
                loadingGoogleDocs: false,
                loadingPhotos: false,
                notionUrl: String(existing.notionUrl || ''),
                relations: Array.isArray(existing.relations) ? existing.relations : [],
                selectedEntries,
                selectedFeaturedPhotoId,
                selectedInlinePhotos,
            };

            const pd = this.prSubjectData[normalizedProfile.id];
            pd.loading = true;
            pd.fields = Array.isArray(pd.fields) ? pd.fields : [];
            pd.relations = Array.isArray(pd.relations) ? pd.relations : [];

            if (normalizedProfile.fields && Object.keys(normalizedProfile.fields).length > 0) {
                for (const [key, val] of Object.entries(normalizedProfile.fields)) {
                    if (val) pd.fields.push({ key, notion_field: key, value: val, display_value: val });
                }
            }

            if (normalizedProfile.external_source === 'notion' && normalizedProfile.external_id) {
                pd.notionUrl = 'https://notion.so/' + normalizedProfile.external_id.replace(/-/g, '');
                try {
                    const resp = await fetch('/notion/profile/' + normalizedProfile.id + '/context', {
                        method: 'POST',
                        headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                        body: JSON.stringify({ fresh: false }),
                    });
                    const data = await resp.json();
                    if (data.success) {
                        if (data.fields?.length) pd.fields = data.fields;
                        pd.driveUrl = this.looksLikeGoogleDriveFolderUrl(data.profile?.drive_url || '') ? data.profile.drive_url : '';
                        pd.notionUrl = data.profile?.notion_url || pd.notionUrl;
                        pd.photos = this.mergePrPhotoCollections(pd.photos, data.profile?.photo_candidates || []);
                        if (pd.driveUrl) {
                            await this.loadProfilePhotos(normalizedProfile);
                        } else {
                            this.bootstrapPrPhotoSelection(normalizedProfile.id);
                        }
                        pd.relations = (data.relations || []).map(r => ({
                            ...r,
                            open: true,
                            loading: false,
                            loaded: false,
                            entries: [],
                        }));
                        for (const rel of pd.relations) {
                            this.loadPrRelation(normalizedProfile.id, rel.slug);
                        }
                        await this.loadPrGoogleDocsForProfile(normalizedProfile.id);
                    }
                } catch (e) {}
            }

            pd.loaded = true;
            pd.loading = false;
            this.syncPrArticleForCurrentArticleType();
            if (this.isPrArticleMode()) {
                this.$nextTick(() => this.hydratePrArticleSelectedMedia());
            }
            if (!options.skipSave) {
                this.savePipelineState();
            }
        },

        async loadPrRelation(profileId, slug) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = pd.relations.find(r => r.slug === slug);
            if (!rel) return;
            rel.loading = true;
            try {
                const resp = await fetch('/notion/profile/' + profileId + '/relations/' + slug, {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ fresh: false }),
                });
                const data = await resp.json();
                if (data.success) {
                    rel.entries = (data.entries || []).map(e => ({ ...e, open: false, loading: false, loadingGoogleDocs: false, detail: null, google_docs: [] }));
                    rel.count = data.total || rel.entries.length;
                    rel.loaded = true;
                }
            } catch (e) {}
            rel.loading = false;
        },

        async loadPrEntryDetail(profileId, slug, entryId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = pd.relations.find(r => r.slug === slug);
            if (!rel) return;
            const entry = rel.entries.find(e => e.id === entryId);
            if (!entry) return;
            if (entry.detail) { entry.open = !entry.open; return; }
            entry.open = true;
            entry.loading = true;
            try {
                const resp = await fetch('/notion/page/' + entryId + '/detail', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ fresh: false }),
                });
                const data = await resp.json();
                if (data.success) {
                    entry.detail = data.page || null;
                    await this.loadPrGoogleDocsForEntry(entry);
                }
            } catch (e) {}
            entry.loading = false;
        },

        togglePrEntry(profileId, slug, entryId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            const rel = pd.relations.find(r => r.slug === slug);
            if (!rel) return;
            const entry = rel.entries.find(e => e.id === entryId);
            if (!entry) return;
            if (!entry.detail) { this.loadPrEntryDetail(profileId, slug, entryId); return; }
            entry.open = !entry.open;
        },

        togglePrEntrySelect(profileId, entryId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            if (!pd.selectedEntries) pd.selectedEntries = {};
            pd.selectedEntries[entryId] = !pd.selectedEntries[entryId];
            if (pd.selectedEntries[entryId]) {
                for (const rel of (pd.relations || [])) {
                    const entry = (rel.entries || []).find((candidate) => String(candidate.id) === String(entryId));
                    if (entry && !entry.detail && !entry.loading) {
                        this.loadPrEntryDetail(profileId, rel.slug, entryId);
                        break;
                    }
                }
            }
            this.savePipelineState();
        },

        togglePrPhotoSelect(profileId, photoId) {
            return this.togglePrInlinePhotoSelect(profileId, photoId);
        },

        isPrFeaturedPhotoSelected(profileId, photoId) {
            const pd = this.prSubjectData[profileId] || {};
            return String(pd.selectedFeaturedPhotoId || '') === String(photoId || '');
        },

        isPrInlinePhotoSelected(profileId, photoId) {
            const pd = this.prSubjectData[profileId] || {};
            return !!pd.selectedInlinePhotos?.[photoId];
        },

        prInlineSelectionOrdinal(profileId, photoId) {
            const pd = this.prSubjectData[profileId] || {};
            const orderedIds = this.prInlinePhotoCandidates(profileId)
                .filter((photo) => !!pd.selectedInlinePhotos?.[photo.id])
                .map((photo) => String(photo.id));
            const idx = orderedIds.indexOf(String(photoId));
            return idx === -1 ? null : idx + 1;
        },

        togglePrInlinePhotoSelect(profileId, photoId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            if (!pd.selectedInlinePhotos) pd.selectedInlinePhotos = {};
            pd.selectedInlinePhotos[photoId] = !pd.selectedInlinePhotos[photoId];
            this.hydratePrArticleSelectedMedia();
            this.savePipelineState();
            this.queueAutoSaveDraft?.(250);
        },

        setPrFeaturedPhoto(profileId, photoId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            pd.selectedFeaturedPhotoId = String(photoId || '');
            if (pd.selectedInlinePhotos?.[photoId]) {
                delete pd.selectedInlinePhotos[photoId];
            }
            this.bootstrapPrPhotoSelection(profileId);
            this.hydratePrArticleSelectedMedia();
            this.savePipelineState();
            this.queueAutoSaveDraft?.(250);
        },

        bootstrapPrPhotoSelection(profileId) {
            const pd = this.prSubjectData[profileId];
            if (!pd || !Array.isArray(pd.photos) || pd.photos.length === 0) return;
            const mainSubjectId = Number(this.prArticle.main_subject_id || this.selectedPrProfiles[0]?.id || 0);
            const isMainSubject = Number(profileId) === mainSubjectId;
            const featuredCandidates = this.prFeaturedPhotoCandidates(profileId);
            if (!pd.selectedFeaturedPhotoId && isMainSubject && featuredCandidates[0]) {
                pd.selectedFeaturedPhotoId = String(featuredCandidates[0].id);
            }

            const selectedIds = Object.keys(pd.selectedInlinePhotos || {}).filter(id => pd.selectedInlinePhotos[id]);
            if (selectedIds.length > 0) return;

            const inlineCandidates = this.prInlinePhotoCandidates(profileId);
            if (!inlineCandidates.length) return;

            const autoLimit = isMainSubject
                ? Math.max(2, Number(this.prArticle.inline_photo_target || 3))
                : Math.min(1, inlineCandidates.length);

            pd.selectedInlinePhotos = pd.selectedInlinePhotos || {};
            inlineCandidates.slice(0, autoLimit).forEach((photo) => {
                pd.selectedInlinePhotos[photo.id] = true;
            });
        },

        prPhotoAssetFromDrivePhoto(profile, photo) {
            const url = photo.webContentLink || photo.webViewLink || photo.thumbnailLink || '';
            const thumb = photo.thumbnailLink || photo.webContentLink || photo.webViewLink || url;
            const inferredSource = this.prProfilePhotoSource(photo, this.prSubjectData?.[profile?.id]?.driveUrl || '');
            const sourceUrl = photo.source_url || photo.webViewLink || url;
            const label = this.prFriendlyPhotoLabel(profile, photo, 1);
            return {
                id: photo.id || null,
                source: inferredSource,
                source_url: sourceUrl,
                source_field: this.prPhotoFieldLabel(photo),
                url,
                url_thumb: thumb,
                url_large: url,
                url_full: url,
                alt: label,
                photographer: profile.name || '',
                photographer_url: '',
                width: Number(photo.width || 0),
                height: Number(photo.height || 0),
            };
        },

        selectedPrFeaturedAsset() {
            if (!this.isPrArticleMode()) return null;
            const mainSubjectId = Number(this.prArticle.main_subject_id || this.selectedPrProfiles[0]?.id || 0);
            const profile = this.selectedPrProfiles.find((candidate) => Number(candidate.id) === mainSubjectId) || this.selectedPrProfiles[0] || null;
            if (!profile) return null;
            const photo = this.prSelectedFeaturedPhoto(profile.id);
            if (!photo) return null;
            const asset = this.prPhotoAssetFromDrivePhoto(profile, photo);
            if (!asset.url_large || this.looksLikeGoogleDriveFolderUrl(asset.url_large)) return null;
            return asset;
        },

        selectedPrInlineAssets(limit = null) {
            const mainSubjectId = Number(this.prArticle.main_subject_id || this.selectedPrProfiles[0]?.id || 0);
            const orderedProfiles = [
                ...this.selectedPrProfiles.filter(p => Number(p.id) === mainSubjectId),
                ...this.selectedPrProfiles.filter(p => Number(p.id) !== mainSubjectId),
            ];

            const assets = [];
            const seen = new Set();

            for (const profile of orderedProfiles) {
                const pd = this.prSubjectData[profile.id];
                if (!pd) continue;
                const selectedIds = Object.keys(pd.selectedInlinePhotos || {}).filter(id => pd.selectedInlinePhotos[id]);
                const sourcePhotos = selectedIds.length > 0
                    ? this.prInlinePhotoCandidates(profile.id).filter((photo) => selectedIds.includes(String(photo.id)))
                    : this.prInlinePhotoCandidates(profile.id);

                for (const photo of sourcePhotos) {
                    const asset = this.prPhotoAssetFromDrivePhoto(profile, photo);
                    if (!asset.url_large || this.looksLikeGoogleDriveFolderUrl(asset.url_large) || seen.has(asset.url_large)) continue;
                    assets.push(asset);
                    seen.add(asset.url_large);
                    if (limit && assets.length >= limit) {
                        return assets;
                    }
                }
            }

            return assets;
        },

        selectedPrPhotoAssets(limit = null) {
            const combined = [];
            const seen = new Set();
            const featured = this.selectedPrFeaturedAsset();
            if (featured?.url_large) {
                combined.push(featured);
                seen.add(featured.url_large);
                if (limit && combined.length >= limit) {
                    return combined.slice(0, limit);
                }
            }
            for (const asset of this.selectedPrInlineAssets(limit ? Math.max(limit - combined.length, 0) : null)) {
                if (!asset?.url_large || seen.has(asset.url_large)) continue;
                combined.push(asset);
                seen.add(asset.url_large);
                if (limit && combined.length >= limit) break;
            }
            return combined;
        },

        prInlinePlaceholderHtml(idx, label, altText) {
            return '<div class="photo-placeholder" contenteditable="false" data-idx="' + idx + '" data-search="' + this._escHtml(label) + '" data-caption="' + this._escHtml(altText || label) + '" style="border:2px dashed #a78bfa;background:#f5f3ff;border-radius:8px;padding:12px 16px;margin:16px 0;cursor:pointer;text-align:center;color:#7c3aed;font-size:13px;">Loading photo...</div>';
        },

        injectPrArticlePhotoPlaceholders() {
            const editor = tinymce.get('spin-preview-editor');
            const originalHtml = editor ? editor.getContent() : (this.editorContent || this.spunContent || '');
            if (!originalHtml) return;
            if (/photo-placeholder/i.test(originalHtml)) {
                this.hydrateResolvedPhotoPlaceholders('pr_article_existing_placeholders');
                return;
            }

            let html = originalHtml;
            let inserted = 0;
            const placeholders = (this.photoSuggestions || []).filter(ps => !ps?.removed).map((ps, idx) =>
                this.prInlinePlaceholderHtml(idx, ps.search_term || ('selected client photo ' + (idx + 1)), ps.alt_text || ps.search_term || '')
            );

            html = html.replace(/<\/p>/gi, (match) => {
                const shouldInsert = inserted < placeholders.length && (inserted === 0 || inserted % 2 === 0);
                if (!shouldInsert) {
                    return match;
                }
                const block = placeholders[inserted];
                inserted += 1;
                return match + block;
            });

            while (inserted < placeholders.length) {
                html += placeholders[inserted];
                inserted += 1;
            }

            if (editor) {
                editor.setContent(html);
            }
            this.editorContent = html;
            this.spunContent = html;
            this.hydrateResolvedPhotoPlaceholders('pr_article_placeholder_injected');
        },

        async ensurePrSubjectContextReady() {
            for (const profile of this.selectedPrProfiles || []) {
                const pd = this.prSubjectData[profile.id];
                if (!pd) continue;

                if (!pd.loaded && !pd.loading) {
                    await this.loadProfileData(profile, { preserveSelections: true, skipSave: true });
                }

                if (!Array.isArray(pd.googleDocs) || pd.googleDocs.length === 0) {
                    await this.loadPrGoogleDocsForProfile(profile.id);
                }

                const selectedIds = Object.keys(pd.selectedEntries || {}).filter((id) => pd.selectedEntries[id]);
                for (const rel of (pd.relations || [])) {
                    for (const entry of (rel.entries || [])) {
                        if (!selectedIds.includes(String(entry.id))) continue;
                        if (!entry.detail && !entry.loading) {
                            await this.loadPrEntryDetail(profile.id, rel.slug, entry.id);
                        } else if (entry.detail && (!Array.isArray(entry.google_docs) || entry.google_docs.length === 0) && !entry.loadingGoogleDocs) {
                            await this.loadPrGoogleDocsForEntry(entry);
                        }
                    }
                }
            }
        },

        hydratePrArticleSelectedMedia() {
            if (!this.isPrArticleMode()) return;
            this.syncPrArticleForCurrentArticleType();

            const allowFeatured = this.currentArticleType === 'pr-full-feature'
                || this.prArticle.feature_photo_mode !== 'inline_only';
            const featuredAsset = allowFeatured ? this.selectedPrFeaturedAsset() : null;
            const inlineAssets = this.selectedPrInlineAssets(Number(this.prArticle.inline_photo_target || 3));

            const hasSubjectFeatured = ['notion-drive', 'notion-profile'].includes(String(this.featuredPhoto?.source || '').toLowerCase());
            const currentFeaturedUrl = this.toAbsoluteMediaUrl(this.featuredPhoto?.url_large || this.featuredPhoto?.url || '');
            const nextFeaturedUrl = this.toAbsoluteMediaUrl(featuredAsset?.url_large || featuredAsset?.url || '');

            if (allowFeatured && featuredAsset && (!this.featuredPhoto || !hasSubjectFeatured || currentFeaturedUrl !== nextFeaturedUrl)) {
                this.applyFeaturedPhotoSelection(featuredAsset, { refreshMeta: false });
                this.featuredAlt = featuredAsset.alt || this.featuredAlt || '';
                this.featuredCaption = this.featuredCaption || '';
                this.featuredFilename = this.featuredFilename || (this.buildFilename(featuredAsset.alt || 'featured subject', 0) + '.jpg');
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            } else if ((!allowFeatured || !featuredAsset) && hasSubjectFeatured) {
                this.featuredPhoto = null;
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            }

            this.photoSuggestions = inlineAssets.map((asset, idx) => this.normalizePhotoSuggestionState({
                position: idx,
                search_term: asset.alt || ('selected client photo ' + (idx + 1)),
                alt_text: asset.alt || '',
                caption: '',
                suggestedFilename: this.buildFilename(asset.alt || ('client photo ' + (idx + 1)), idx + 1),
                autoPhoto: asset,
                confirmed: true,
                loadAttempted: true,
                removed: false,
            }, idx));
            this.photoSuggestionsPending = false;
            this.$nextTick(() => {
                if (!inlineAssets.length) {
                    this.syncDeferredEnrichmentState('pr_article_media_seeded');
                    return;
                }
                if (/photo-placeholder/i.test(this.editorContent || this.spunContent || '')) {
                    (this.photoSuggestions || []).forEach((ps, idx) => {
                        if (ps?.autoPhoto && !ps?.removed) {
                            this.updatePlaceholderInEditor(idx, 0, { syncEditor: false, markThumbPending: false });
                        }
                    });
                    this.syncEditorStateFromEditor();
                } else {
                    this.injectPrArticlePhotoPlaceholders();
                }
                this.syncDeferredEnrichmentState('pr_article_media_seeded');
            });
        },

        buildPrArticleSourceText(usePlaceholder = false) {
            if (!this.isPrArticleMode()) return null;

            this.syncPrArticleForCurrentArticleType();
            const mainSubject = this.selectedPrProfiles.find(p => Number(p.id) === Number(this.prArticle.main_subject_id || 0)) || this.selectedPrProfiles[0] || null;
            const lines = [
                '=== ARTICLE BRIEF ===',
                'Article type: ' + (this.currentArticleType === 'pr-full-feature' ? 'PR Full Feature' : 'Expert Article'),
                'Main subject: ' + (mainSubject?.name || 'No main subject selected'),
                'Promotional level: ' + (this.prArticle.promotional_level || ''),
                'Tone: ' + (this.prArticle.tone || ''),
                'Quote target: ' + (this.prArticle.quote_count || 0) + ' quote(s)',
                'Headline guidance: ' + (this.prArticle.include_subject_name_in_title ? 'Include the main subject name naturally.' : 'Subject name is optional in the headline.'),
                'Voice rule: Write in third person. Never use first-person phrasing in the headline or body except inside direct quotes copied from source material.',
            ];

            if (this.prArticle.focus_instructions) {
                lines.push('Focus instructions: ' + this.prArticle.focus_instructions);
            } else if (usePlaceholder) {
                lines.push('Focus instructions: [The article direction instructions will be inserted here]');
            }

            if (this.prArticle.quote_guidance) {
                lines.push('Quote guidance: ' + this.prArticle.quote_guidance);
            }

            if (this.currentArticleType === 'expert-article') {
                lines.push('Subject position on the topic: ' + (this.prArticle.subject_position || 'Use the subject as a credible expert voice.'));
                lines.push('Photo mode: ' + (this.prArticle.feature_photo_mode === 'inline_only' ? 'Inline subject photos only; no featured subject image.' : 'Use the subject as the featured image and also include inline photos.'));
            } else {
                lines.push('Photo mode: Use the main subject as the featured image when subject photos exist.');
                lines.push('Inline client photos: Use 2-3 real subject photos in the body when available.');
            }

            const contextLabel = this.currentArticleType === 'expert-article' ? 'TOPIC CONTEXT' : 'EDITORIAL CONTEXT';
            if (this.prArticle.expert_source_mode === 'keywords' && this.prArticle.expert_keywords) {
                lines.push(`\n=== ${contextLabel} SEARCH TERMS ===\n` + this.prArticle.expert_keywords);
            } else if (this.prArticle.expert_source_mode === 'keywords' && usePlaceholder) {
                lines.push(`\n=== ${contextLabel} SEARCH TERMS ===\n[Context search terms will be inserted here]`);
            }

            if (this.prArticle.expert_source_mode === 'url' && this.prArticle.expert_context_extracted?.text) {
                const imported = this.prArticle.expert_context_extracted;
                lines.push(`\n=== IMPORTED ${contextLabel} ARTICLE ===`);
                lines.push('Title: ' + (imported.title || ''));
                lines.push('URL: ' + (imported.url || this.prArticle.expert_context_url || ''));
                if (imported.excerpt) {
                    lines.push('Excerpt: ' + imported.excerpt);
                }
                if (this.currentArticleType === 'pr-full-feature') {
                    lines.push('Use this article as editorial backdrop only. Keep the client as the central feature subject.');
                }
                if (imported.text) {
                    lines.push("\n" + imported.text.substring(0, 12000));
                }
            } else if (this.prArticle.expert_source_mode === 'url' && usePlaceholder) {
                lines.push(`\n=== IMPORTED ${contextLabel} ARTICLE ===\n[Imported article context will be inserted here]`);
            }

            return lines.join("\n").trim();
        },

        buildPrSubjectContext() {
            if (!this.selectedPrProfiles?.length) return null;

            this.syncPrArticleForCurrentArticleType();
            const parts = [];
            const mainSubject = this.selectedPrProfiles.find(p => Number(p.id) === Number(this.prArticle.main_subject_id || 0)) || this.selectedPrProfiles[0] || null;
            const photoAssets = this.selectedPrPhotoAssets(Number(this.prArticle.inline_photo_target || 3));

            parts.push('=== ARTICLE STRATEGY ===');
            parts.push('Main subject: ' + (mainSubject?.name || ''));
            parts.push('Promotional level: ' + (this.prArticle.promotional_level || ''));
            parts.push('Tone: ' + (this.prArticle.tone || ''));
            parts.push('Quote target: ' + (this.prArticle.quote_count || 0) + ' quote(s)');
            if (this.prArticle.focus_instructions) {
                parts.push('Focus instructions: ' + this.prArticle.focus_instructions);
            }
            if (this.prArticle.quote_guidance) {
                parts.push('Quote guidance: ' + this.prArticle.quote_guidance);
            }
            if (this.currentArticleType === 'expert-article' && this.prArticle.subject_position) {
                parts.push('Subject position: ' + this.prArticle.subject_position);
            }
            if (this.currentArticleType === 'expert-article') {
                parts.push('Photo mode: ' + (this.prArticle.feature_photo_mode === 'inline_only' ? 'inline subject photos only' : 'subject can be featured and inline'));
            } else {
                parts.push('Headline instruction: ' + (this.prArticle.include_subject_name_in_title ? 'include the main subject name in the headline' : 'subject name optional in the headline'));
            }
            parts.push('Voice rule: third person only for the headline and narrative body, except direct quotes copied from source material.');
            parts.push('');

            if (photoAssets.length > 0) {
                parts.push('=== SELECTED SUBJECT PHOTO INVENTORY ===');
                photoAssets.forEach((asset, idx) => {
                    parts.push((idx === 0 ? 'PRIMARY PHOTO' : 'PHOTO ' + (idx + 1)) + ': ' + (asset.alt || 'subject photo') + ' | URL: ' + (asset.url_large || asset.source_url || ''));
                });
                parts.push('Use the selected subject photo inventory. Emit one FEATURED marker when the brief allows it, and 2-3 PHOTO markers for inline placement where relevant.');
                parts.push('');
            }

            if (this.prArticle.expert_source_mode === 'keywords' && this.prArticle.expert_keywords) {
                parts.push('=== CONTEXT SEARCH TERMS ===');
                parts.push(this.prArticle.expert_keywords);
                parts.push('');
            }

            if (this.prArticle.expert_source_mode === 'url' && this.prArticle.expert_context_extracted?.text) {
                parts.push('=== IMPORTED CONTEXT ARTICLE ===');
                parts.push('Title: ' + (this.prArticle.expert_context_extracted.title || ''));
                parts.push('URL: ' + (this.prArticle.expert_context_extracted.url || this.prArticle.expert_context_url || ''));
                if (this.prArticle.expert_context_extracted.excerpt) {
                    parts.push('Excerpt: ' + this.prArticle.expert_context_extracted.excerpt);
                }
                if (this.currentArticleType === 'pr-full-feature') {
                    parts.push('Use this external article as editorial backdrop only. The profile subject remains the focus.');
                }
                parts.push(this.prArticle.expert_context_extracted.text.substring(0, 12000));
                parts.push('');
            }

            for (const profile of this.selectedPrProfiles) {
                const pd = this.prSubjectData[profile.id];
                if (!pd?.loaded) continue;

                let section = '--- SUBJECT: ' + profile.name + ' ---\n';
                if (Number(profile.id) === Number(this.prArticle.main_subject_id || 0)) {
                    section += 'ROLE: MAIN SUBJECT\n';
                }
                if (profile.context) {
                    section += 'PROFILE-SPECIFIC INSTRUCTIONS: ' + profile.context + '\n\n';
                }

                if (pd.fields?.length) {
                    section += 'PROFILE DATA:\n';
                    for (const f of pd.fields) {
                        const value = String(f.display_value || f.value || '').trim();
                        if (!value) continue;
                        section += '- ' + f.notion_field + ': ' + value.substring(0, 500) + '\n';
                    }
                    section += '\n';
                }

                if (Array.isArray(pd.googleDocs) && pd.googleDocs.length > 0) {
                    section += 'GOOGLE DOC CONTEXT (PROFILE-LEVEL):\n';
                    for (const doc of pd.googleDocs) {
                        section += '- ' + (doc.title || 'Google Doc') + '\n';
                        section += '  URL: ' + (doc.url || '') + '\n';
                        if (doc.preview) {
                            section += '  Preview: ' + doc.preview.substring(0, 600) + '\n';
                        }
                        if (doc.text) {
                            section += '  Extracted Text: ' + doc.text.substring(0, 2500) + '\n';
                        }
                    }
                    section += '\n';
                }

                const selectedIds = Object.keys(pd.selectedEntries || {}).filter(id => pd.selectedEntries[id]);
                if (selectedIds.length > 0 && pd.relations?.length) {
                    for (const rel of pd.relations) {
                        const selected = (rel.entries || []).filter(e => selectedIds.includes(String(e.id)));
                        if (selected.length === 0) continue;
                        section += rel.label.toUpperCase() + ':\n';
                        for (const entry of selected) {
                            section += '  - ' + entry.title + '\n';
                            if (entry.detail?.properties) {
                                let fieldCount = 0;
                                for (const [k, v] of Object.entries(entry.detail.properties)) {
                                    if (fieldCount >= 12) break;
                                    if (typeof v !== 'string') continue;
                                    const value = v.trim();
                                    if (!value) continue;
                                    fieldCount += 1;
                                    section += '    ' + k + ': ' + value.substring(0, 500) + '\n';
                                }
                            }
                            if (Array.isArray(entry.google_docs) && entry.google_docs.length > 0) {
                                for (const doc of entry.google_docs) {
                                    section += '    Google Doc: ' + (doc.title || 'Google Doc') + '\n';
                                    if (doc.url) {
                                        section += '      URL: ' + doc.url + '\n';
                                    }
                                    if (doc.preview) {
                                        section += '      Preview: ' + doc.preview.substring(0, 600) + '\n';
                                    }
                                    if (doc.text) {
                                        section += '      Extracted Text: ' + doc.text.substring(0, 2500) + '\n';
                                    }
                                }
                            }
                        }
                        section += '\n';
                    }
                }

                const selectedPhotoIds = Object.keys(pd.selectedPhotos || {}).filter(id => pd.selectedPhotos[id]);
                if (selectedPhotoIds.length > 0) {
                    section += 'SELECTED PROFILE PHOTOS:\n';
                    for (const photo of (pd.photos || [])) {
                        if (selectedPhotoIds.includes(String(photo.id))) {
                            section += '- ' + photo.name + ' [URL: ' + (photo.webViewLink || photo.webContentLink || '') + ']\n';
                        }
                    }
                    section += '\n';
                }

                parts.push(section);
            }

            return parts.join('\n').trim() || null;
        },

        async importPrArticleContextUrl(quiet = false) {
            if (!this.prArticle.expert_context_url) {
                if (!quiet) {
                    this.showNotification('error', 'Enter a live article URL to import context.');
                }
                return false;
            }

            this.prArticleContextImporting = true;
            try {
                const resp = await fetch('{{ route("publish.pipeline.pr-article.import-context-url") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        url: this.prArticle.expert_context_url,
                    }),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) {
                    throw new Error(data.message || 'Failed to import article context.');
                }
                this.prArticle.expert_context_extracted = data.context || {};
                this.maybeApplySmartDraftTitle?.();
                this.savePipelineState();
                if (!quiet) {
                    this.showNotification('success', data.message || 'Article context imported.');
                }
                return true;
            } catch (error) {
                if (!quiet) {
                    this.showNotification('error', error.message || 'Failed to import article context.');
                }
                return false;
            } finally {
                this.prArticleContextImporting = false;
            }
        },

        async continuePrArticleStep3() {
            if (!this.selectedPrProfiles.length) {
                this.showNotification('error', 'Select at least one Notion subject before continuing.');
                return;
            }

            this.syncPrArticleForCurrentArticleType();

            const effectiveMode = String(this.prArticle.expert_source_mode || '').trim() || (String(this.prArticle.expert_context_url || '').trim() ? 'url' : (String(this.prArticle.expert_keywords || '').trim() ? 'keywords' : 'none'));
            const hasFocusInstructions = !!String(this.prArticle.focus_instructions || '').trim();
            const hasContextKeywords = effectiveMode !== 'url' && !!String(this.prArticle.expert_keywords || '').trim();
            const hasContextUrl = !!String(this.prArticle.expert_context_url || '').trim();
            const hasImportedContext = !!String(this.prArticle.expert_context_extracted?.text || '').trim();
            const hasContextPackage = effectiveMode === 'url' ? (hasContextUrl || hasImportedContext) : hasContextKeywords;

            if (this.currentArticleType === 'pr-full-feature') {
                if (!hasContextPackage) {
                    this.showNotification('error', 'Add editorial context terms or import a context article before continuing.');
                    return;
                }
                if (!hasFocusInstructions) {
                    this.showNotification('error', 'Tell the writer how to use that context in Article Focus Instructions before continuing.');
                    return;
                }
            }

            if (this.currentArticleType === 'expert-article' && !hasContextPackage) {
                this.showNotification('error', 'Add topic context terms or import a context article before continuing.');
                return;
            }

            await this.ensurePrSubjectContextReady();

            if ((this.currentArticleType === 'expert-article' || this.currentArticleType === 'pr-full-feature')
                && this.prArticle.expert_source_mode === 'url'
                && this.prArticle.expert_context_url
                && !this.prArticle.expert_context_extracted?.text) {
                const imported = await this.importPrArticleContextUrl(true);
                if (!imported) {
                    return;
                }
            }

            this.completeStep(3);
            this.completeStep(4);
            this.openStep(5);
            this.invalidatePromptPreview('pr_article_ready', { fetch: true });
            this.savePipelineState();
        },

        async loadProfilePhotos(profile) {
            const data = this.prSubjectData[profile.id];
            if (!data || !data.driveUrl) return;
            data.loadingPhotos = true;
            try {
                const resp = await fetch('{{ route("notion.profile.fetch-photos") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ drive_url: data.driveUrl }),
                });
                const result = await resp.json();
                if (result.success) {
                    const drivePhotos = (result.photos || []).map((photo) => ({ ...photo, source: photo?.source || 'notion-drive' }));
                    data.photos = this.mergePrPhotoCollections(data.photos, drivePhotos);
                    this.bootstrapPrPhotoSelection(profile.id);
                    this.hydratePrArticleSelectedMedia();
                    this.savePipelineState();
                }
            } catch (e) {}
            data.loadingPhotos = false;
        },

        async loadBookmarks() {
            if (this.bookmarks.length > 0 || !this.selectedUser) return;
            this.bookmarksLoading = true;
            try {
                const resp = await fetch(`{{ route('publish.bookmarks.index') }}?user_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.bookmarks = data.data || data || [];
            } catch (e) { this.bookmarks = []; }
            this.bookmarksLoading = false;
        },

        // ── Step 5: Get Articles ──────────────────────────
        toggleSourceExpand(idx) {
            const pos = this.expandedSources.indexOf(idx);
            if (pos === -1) this.expandedSources.push(idx);
            else this.expandedSources.splice(pos, 1);
        },
        approveSource(idx) {
            if (!this.approvedSources.includes(idx)) this.approvedSources.push(idx);
            this.discardedSources = this.discardedSources.filter(i => i !== idx);
            this.invalidatePromptPreview('source_approved');
        },
        discardSource(idx) {
            if (!this.discardedSources.includes(idx)) this.discardedSources.push(idx);
            this.approvedSources = this.approvedSources.filter(i => i !== idx);
            this.invalidatePromptPreview('source_discarded');
        },
        async flagAsBroken(idx) {
            const result = this.checkResults[idx];
            const source = this.sources[idx];
            if (!source) return;
            try {
                await fetch('/publish/scrape-activity/ban', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        domain: new URL(source.url).hostname,
                        reason: 'Flagged as broken from pipeline — ' + (result?.message || 'user flagged'),
                    })
                });
            } catch(e) {}
            this.discardSource(idx);
            this.showNotification('warning', 'Source flagged as broken and domain banned');
        },
        removeSource(idx) {
            this.sources.splice(idx, 1);
            this.checkResults.splice(idx, 1);
            this.approvedSources = this.approvedSources.filter(i => i !== idx).map(i => i > idx ? i - 1 : i);
            this.discardedSources = this.discardedSources.filter(i => i !== idx).map(i => i > idx ? i - 1 : i);
            this.expandedSources = this.expandedSources.filter(i => i !== idx).map(i => i > idx ? i - 1 : i);
            this.invalidatePromptPreview('source_removed');
            this.savePipelineState();
        },
        failedSourceKeywords(idx) {
            const source = this.sources[idx];
            if (!source) return '';

            const url = source.url || '';
            const title = source.title || '';
            let keywords = title;

            if (!keywords) {
                try {
                    const path = new URL(url).pathname;
                    keywords = path.split('/').pop().replace(/[-_]/g, ' ').replace(/\.(html?|php|aspx?)$/i, '').replace(/\d{4,}/g, '').trim();
                } catch (e) {
                    keywords = '';
                }
            }

            return keywords;
        },
        async persistFailedSource(idx) {
            const result = this.checkResults[idx];
            const source = this.sources[idx];
            if (!source) return false;

            try {
                await fetch('{{ route("publish.failed-sources.store") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        url: source.url,
                        title: source.title || '',
                        error_message: result?.message || 'Extraction failed',
                        source_api: result?.source_api || '',
                    })
                });
                return true;
            } catch (e) {
                return false;
            }
        },
        async markFailedSource(idx) {
            this._markingBrokenIdx = idx;
            const saved = await this.persistFailedSource(idx);
            this._markingBrokenIdx = null;
            this.removeSource(idx);
            this.showNotification(saved ? 'warning' : 'info', saved ? 'Source marked as broken and removed.' : 'Source removed.');
        },
        searchFailedSource(idx) {
            const keywords = this.failedSourceKeywords(idx);
            this.removeSource(idx);
            if (!keywords) {
                this.showNotification('warning', 'No title keywords available.');
                return;
            }
            this.sourceTab = 'ai';
            this.currentStep = 3;
            if (!this.openSteps.includes(3)) this.openSteps.push(3);
            this.aiSearchTopic = keywords;
            this.showNotification('info', 'Search term loaded — click Find Articles when ready.');
        },
        async markAndSearchFailedSource(idx) {
            const keywords = this.failedSourceKeywords(idx);
            this._markingBrokenIdx = idx;
            const saved = await this.persistFailedSource(idx);
            this._markingBrokenIdx = null;
            this.removeSource(idx);
            if (!keywords) {
                this.showNotification(saved ? 'warning' : 'info', saved ? 'Marked broken and removed.' : 'Removed.');
                return;
            }
            this.sourceTab = 'ai';
            this.currentStep = 3;
            if (!this.openSteps.includes(3)) this.openSteps.push(3);
            this.aiSearchTopic = keywords;
            this.showNotification(saved ? 'warning' : 'info', (saved ? 'Marked broken. ' : '') + 'Search term loaded — click Find Articles when ready.');
        },

        _logCheck(type, message) {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.checkLog.push({ type, message, time });
            this._logActivity('check', type, message, { debug_only: type === 'step' });
        },

        async checkAllSources() {
            if (this.sources.length === 0) return;
            this.checking = true;
            this.checkResults = [];
            this.checkLog = [];
            this.expandedSources = [];
            this.approvedSources = [];
            this.discardedSources = [];
            this.checkPassCount = 0;
            // Keep step 4 open during extraction
            if (!this.openSteps.includes(4)) this.openSteps.push(4);

            const methodNames = { auto: 'Auto (Readability + Structured + Heuristic + Jina)', readability: 'Readability', structured: 'Structured Data (JSON-LD)', heuristic: 'DOM Heuristic', css: 'CSS Selector', regex: 'Regex (raw HTML parsing)', jina: 'Jina Reader', claude: 'Claude AI', gpt: 'GPT', grok: 'Grok', gemini: 'Gemini' };
            const uaNames = { chrome: 'Chrome Desktop', googlebot: 'Googlebot', bingbot: 'Bingbot', mobile: 'Mobile Chrome', curl: 'cURL' };
            this._logCheck('info', 'Starting article extraction for ' + this.sources.length + ' source(s)...');
            this.sources.forEach((s, i) => this._logCheck('step', (i + 1) + '. ' + s.url));
            this._logCheck('info', 'Method: ' + (methodNames[this.extractMethod] || this.extractMethod));
            this._logCheck('info', 'User Agent: ' + (uaNames[this.checkUserAgent] || this.checkUserAgent));
            this._logCheck('info', 'Timeout: ' + this.extractTimeout + 's per request | Retries: ' + this.extractRetries + ' | Min words: ' + this.extractMinWords);
            this._logCheck('info', 'Auto-fallback (Googlebot): ' + (this.extractAutoFallback ? 'On' : 'Off'));
            this._logCheck('info', 'Sending extraction request...');

            try {
                const resp = await fetch('{{ route('publish.pipeline.check') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        urls: this.sources.map(s => s.url),
                        user_agent: this.checkUserAgent,
                        method: this.extractMethod,
                        retries: parseInt(this.extractRetries),
                        timeout: parseInt(this.extractTimeout),
                        min_words: parseInt(this.extractMinWords),
                        auto_fallback: this.extractAutoFallback,
                    })
                });
                const data = await resp.json();
                this.checkResults = data.results || [];
                this.checkPassCount = this.checkResults.filter(r => r.success).length;

                // Auto-expand all successful results
                this.expandedSources = this.checkResults.map((r, i) => r.success ? i : null).filter(i => i !== null);

                this.checkResults.forEach((r, i) => {
                    if (this.sources[i]) {
                        this.sources[i].status = r.success ? 'verified' : 'failed';
                        this.sources[i].wordCount = r.word_count;
                        if (r.title && !this.sources[i].title) {
                            this.sources[i].title = r.title;
                        }
                        if (r.title && i === 0) {
                            this.maybeApplySmartDraftTitle?.();
                        }
                    }
                    const url = this.sources[i]?.url || 'unknown';
                    const domain = url.replace(/^https?:\/\//, '').split('/')[0];
                    if (r.success) {
                        this._logCheck('success', domain + ' — ' + r.word_count + ' words' + (r.title ? ' — "' + r.title.substring(0, 60) + '"' : ''));
                        if (r.method_used) this._logCheck('step', '  Method: ' + (methodNames[r.method_used] || r.method_used));
                        if (r.response_time) this._logCheck('step', '  Response time: ' + r.response_time + 'ms');
                        if (r.http_status) this._logCheck('step', '  HTTP: ' + r.http_status);
                    } else {
                        this._logCheck('error', domain + ' — ' + (r.message || 'Extraction failed'));
                        if (r.http_status) this._logCheck('step', '  HTTP: ' + r.http_status);
                        if (r.method_used) this._logCheck('step', '  Method tried: ' + (methodNames[r.method_used] || r.method_used));
                        if (r.fallback_tried) this._logCheck('step', '  Fallback attempted: ' + r.fallback_tried);
                    }
                });

                this.invalidatePromptPreview('sources_checked');
                this._logCheck('success', 'Done. ' + this.checkPassCount + '/' + this.checkResults.length + ' sources extracted successfully.');
            } catch (e) {
                this._logCheck('error', 'Network error: ' + (e.message || 'Request failed'));
                this.showNotification('error', 'Failed to check sources');
            }
            this.checking = false;
        },

        async retrySingleSource(idx) {
            const source = this.sources[idx];
            if (!source) return;

            try {
                const resp = await fetch('{{ route('publish.pipeline.check') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        urls: [source.url],
                        user_agent: 'googlebot',
                        method: 'readability',
                        retries: 2,
                        timeout: 30,
                        min_words: 25,
                        auto_fallback: true,
                    })
                });
                const data = await resp.json();
                if (data.results && data.results[0]) {
                    this.checkResults[idx] = data.results[0];
                    this.sources[idx].status = data.results[0].success ? 'verified' : 'failed';
                    this.sources[idx].wordCount = data.results[0].word_count;
                    this.checkPassCount = this.checkResults.filter(r => r.success).length;
                    this.invalidatePromptPreview('source_retried');
                }
            } catch (e) {
                this.showNotification('error', 'Retry failed');
            }
        },
