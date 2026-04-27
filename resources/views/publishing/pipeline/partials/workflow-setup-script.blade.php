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

        goToStep(step) {
            if (this.isStepAccessible(step)) {
                this.currentStep = step;
                if (!this.openSteps.includes(step)) {
                    this.openSteps = [step];
                }
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
                this.openSteps = this.openSteps.filter(s => s !== step);
            } else {
                this.openSteps = [step];
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
            this.openSteps = [step];
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
                return this.templates;
            }
            try {
                const resp = await fetch(`{{ route('publish.templates.index') }}?user_id=${this.selectedUser.id}&format=json`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                this.templates = data.data || data || [];
                this.autoSelectPressReleaseTemplate();
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
                    } else {
                        this.siteConn.status = false;
                        this.siteConn.message = this.selectedSite.status === 'error' ? 'Connection error' : 'Not connected';
                    }
                    if (!this.siteConn.authors.length) {
                        this.loadSiteAuthors(this.selectedSiteId, { cacheKey });
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

        autoSelectPrSource() {
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

        deriveArticleTitleFromHtml(html = '') {
            const raw = String(html || '').trim();
            if (!raw) return '';

            try {
                const container = document.createElement('div');
                container.innerHTML = raw;
                const h1 = container.querySelector('h1');
                if (h1 && h1.textContent) {
                    return h1.textContent.trim();
                }
            } catch (e) {}

            return '';
        },

        fallbackPrArticleTitle() {
            const mainSubject = this.selectedPrProfiles.find(p => Number(p.id) === Number(this.prArticle.main_subject_id || 0)) || this.selectedPrProfiles[0] || null;
            const subjectName = mainSubject?.name || 'Untitled';

            if (this.currentArticleType === 'expert-article') {
                const importedTitle = String(this.prArticle?.expert_context_extracted?.title || '').trim();
                if (importedTitle) {
                    return `${subjectName} on ${importedTitle.replace(/\s+/g, ' ')}`.trim();
                }

                const keywordHeadline = String(this.prArticle?.expert_keywords || '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 8)
                    .join(' ')
                    .trim();

                if (keywordHeadline) {
                    return `${subjectName} on ${keywordHeadline}`.trim();
                }
            }

            if (this.currentArticleType === 'pr-full-feature' && subjectName !== 'Untitled') {
                return `${subjectName}: A Feature Profile`;
            }

            return subjectName;
        },

        ensureArticleTitleInHtml(html = '', title = '') {
            const bodyHtml = String(html || '');
            const cleanTitle = String(title || '').trim();
            if (!bodyHtml || !cleanTitle) return bodyHtml;
            if (/<h1[\s>]/i.test(bodyHtml)) return bodyHtml;

            const h1 = `<h1>${this._escHtml(cleanTitle)}</h1>\n`;
            return h1 + bodyHtml;
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

        normalizePrProfileForState(profile = {}) {
            const raw = JSON.parse(JSON.stringify(profile || {}));
            return {
                id: raw.id ?? null,
                name: raw.name || '',
                description: raw.description || '',
                external_source: raw.external_source || '',
                external_id: raw.external_id || '',
                fields: raw.fields && typeof raw.fields === 'object' ? raw.fields : {},
            };
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

            return {
                id: raw.id ? String(raw.id) : ('photo-' + Math.random().toString(36).slice(2, 10)),
                name: raw.name || raw.label || raw.property || 'Subject Photo',
                property: raw.property || '',
                source: raw.source || 'notion-profile',
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
                    selectedPhotos: this.normalizePrSelectionMap(state.selectedPhotos || {}),
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
                    type: 'person',
                });
                const resp = await fetch('{{ route("publish.profiles.search") }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                this.prProfileResults = await resp.json();
            } catch (e) {
                this.prProfileResults = [];
            }
            this.prProfileSearching = false;
        },

        addPrProfile(profile) {
            if (this.selectedPrProfiles.some(p => p.id === profile.id)) return;
            const normalizedProfile = this.normalizePrProfileForState({
                ...profile,
                context: profile.context || '',
            });
            this.selectedPrProfiles.push(normalizedProfile);
            this.syncPrArticleForCurrentArticleType();
            this.prProfileSearch = '';
            this.prProfileResults = [];
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
            const selectedPhotos = preserveSelections ? this.normalizePrSelectionMap(existing.selectedPhotos || {}) : {};

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
                selectedPhotos,
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
                        this.bootstrapPrPhotoSelection(normalizedProfile.id);
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
                        if (pd.driveUrl) {
                            this.loadProfilePhotos(normalizedProfile);
                        }
                        await this.loadPrGoogleDocsForProfile(normalizedProfile.id);
                    }
                } catch (e) {}
            }

            pd.loaded = true;
            pd.loading = false;
            this.syncPrArticleForCurrentArticleType();
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
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            if (!pd.selectedPhotos) pd.selectedPhotos = {};
            pd.selectedPhotos[photoId] = !pd.selectedPhotos[photoId];
            this.savePipelineState();
        },

        bootstrapPrPhotoSelection(profileId) {
            const pd = this.prSubjectData[profileId];
            if (!pd || !Array.isArray(pd.photos) || pd.photos.length === 0) return;
            const selectedIds = Object.keys(pd.selectedPhotos || {}).filter(id => pd.selectedPhotos[id]);
            if (selectedIds.length > 0) return;

            const mainSubjectId = Number(this.prArticle.main_subject_id || this.selectedPrProfiles[0]?.id || 0);
            const isMainSubject = Number(profileId) === mainSubjectId;
            const autoLimit = isMainSubject
                ? Math.max(2, Number(this.prArticle.inline_photo_target || 3))
                : 1;

            pd.selectedPhotos = pd.selectedPhotos || {};
            (pd.photos || []).slice(0, autoLimit).forEach((photo) => {
                pd.selectedPhotos[photo.id] = true;
            });
        },

        prPhotoAssetFromDrivePhoto(profile, photo) {
            const url = photo.webContentLink || photo.webViewLink || photo.thumbnailLink || '';
            const thumb = photo.thumbnailLink || photo.webContentLink || photo.webViewLink || url;
            const inferredSource = String(photo.source || '').trim() || 'notion-profile';
            const sourceUrl = photo.source_url || photo.webViewLink || url;
            return {
                id: photo.id || null,
                source: inferredSource,
                source_url: sourceUrl,
                url,
                url_thumb: thumb,
                url_large: url,
                url_full: url,
                alt: photo.name || profile.name || 'subject photo',
                photographer: profile.name || '',
                photographer_url: '',
                width: Number(photo.width || 0),
                height: Number(photo.height || 0),
            };
        },

        selectedPrPhotoAssets(limit = null) {
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
                const selectedIds = Object.keys(pd.selectedPhotos || {}).filter(id => pd.selectedPhotos[id]);
                const sourcePhotos = selectedIds.length > 0
                    ? (pd.photos || []).filter(photo => selectedIds.includes(String(photo.id)))
                    : (pd.photos || []);

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

            const selectedAssets = this.selectedPrPhotoAssets(Number(this.prArticle.inline_photo_target || 3));
            if (!selectedAssets.length) return;

            const allowFeatured = this.currentArticleType === 'pr-full-feature'
                || this.prArticle.feature_photo_mode !== 'inline_only';

            const hasSubjectFeatured = ['notion-drive', 'notion-profile'].includes(String(this.featuredPhoto?.source || '').toLowerCase());
            if (allowFeatured && selectedAssets[0] && (!this.featuredPhoto || !hasSubjectFeatured)) {
                this.applyFeaturedPhotoSelection(selectedAssets[0], { refreshMeta: false });
                this.featuredAlt = selectedAssets[0].alt || this.featuredAlt || '';
                this.featuredCaption = this.featuredCaption || '';
                this.featuredFilename = this.featuredFilename || (this.buildFilename(selectedAssets[0].alt || 'featured subject', 0) + '.jpg');
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            } else if (!allowFeatured) {
                this.featuredPhoto = null;
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            }

            this.photoSuggestions = selectedAssets.map((asset, idx) => this.normalizePhotoSuggestionState({
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

                if (this.prArticle.expert_source_mode === 'keywords' && this.prArticle.expert_keywords) {
                    lines.push("\n=== TOPIC KEYWORDS ===\n" + this.prArticle.expert_keywords);
                } else if (this.prArticle.expert_source_mode === 'keywords' && usePlaceholder) {
                    lines.push("\n=== TOPIC KEYWORDS ===\n[Topic keywords will be inserted here]");
                }

                if (this.prArticle.expert_source_mode === 'url' && this.prArticle.expert_context_extracted?.text) {
                    const imported = this.prArticle.expert_context_extracted;
                    lines.push("\n=== IMPORTED TOPIC ARTICLE ===");
                    lines.push('Title: ' + (imported.title || ''));
                    lines.push('URL: ' + (imported.url || this.prArticle.expert_context_url || ''));
                    if (imported.excerpt) {
                        lines.push('Excerpt: ' + imported.excerpt);
                    }
                    if (imported.text) {
                        lines.push("\n" + imported.text.substring(0, 12000));
                    }
                } else if (this.prArticle.expert_source_mode === 'url' && usePlaceholder) {
                    lines.push("\n=== IMPORTED TOPIC ARTICLE ===\n[Imported article context will be inserted here]");
                }
            } else {
                lines.push('Photo mode: Use the main subject as the featured image when subject photos exist.');
                lines.push('Inline client photos: Use 2-3 real subject photos in the body when available.');
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
            parts.push('');

            if (photoAssets.length > 0) {
                parts.push('=== SELECTED SUBJECT PHOTO INVENTORY ===');
                photoAssets.forEach((asset, idx) => {
                    parts.push((idx === 0 ? 'PRIMARY PHOTO' : 'PHOTO ' + (idx + 1)) + ': ' + (asset.alt || 'subject photo') + ' | URL: ' + (asset.url_large || asset.source_url || ''));
                });
                parts.push('Use the selected subject photo inventory. Emit one FEATURED marker when the brief allows it, and 2-3 PHOTO markers for inline placement where relevant.');
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
            await this.ensurePrSubjectContextReady();

            if (this.currentArticleType === 'expert-article'
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
                    data.photos = this.mergePrPhotoCollections(data.photos, result.photos || []);
                    this.bootstrapPrPhotoSelection(profile.id);
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
