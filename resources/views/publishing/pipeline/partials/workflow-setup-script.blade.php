        // ── Navigation ────────────────────────────────────
        isStepAccessible(step) {
            if (step === 1) return true;
            // Step 2 (Article Config) unlocks as soon as a user is set or preloaded
            if (step === 2 && this.selectedUser) return true;
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
        selectSite() {
            if (this.selectedSiteId) {
                this.selectedSite = this.sites.find(s => s.id == this.selectedSiteId) || this.prSourceSites.find(s => s.id == this.selectedSiteId) || null;
                if (this.selectedSite) {
                    // Use cached connection status from DB instead of live-testing
                    this.siteConn.log = [];
                    this.siteConn.testing = false;
                    // Always reset author to this site's default (or clear it)
                    this.publishAuthor = this.selectedSite.default_author || '';
                    this.publishAuthorSource = this.selectedSite.default_author ? 'profile' : '';
                    if (this.selectedSite.status === 'connected') {
                        this.siteConn.status = true;
                        this.siteConn.message = 'Connected';
                        this.completeStep(2);
                        if (!this.siteConn.authors.length) {
                            this.loadSiteAuthors(this.selectedSiteId);
                        }
                    } else {
                        this.siteConn.status = false;
                        this.siteConn.message = this.selectedSite.status === 'error' ? 'Connection error' : 'Not connected';
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
                onSuccess: (d) => {
                    if (d.default_author) { this.publishAuthor = d.default_author; this.publishAuthorSource = 'profile'; }
                    this.completeStep(2);
                    this.autoSaveDraft();
                },
            });
        },

        autoSelectPrSource() {
            if (this.template_overrides?.article_type === 'press-release') {
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
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
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
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
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

            this._logAi('info', 'Starting AI article search for: ' + this.aiSearchTopic);
            this._logAi('info', 'Requesting top 6 articles via ' + this.aiSearchModel + ' with web search...');

            try {
                // Collect URLs from current results + already-added sources to exclude duplicates on re-search
                const excludeUrls = [
                    ...this.aiSearchResults.map(a => a.url),
                    ...this.sources.map(s => s.url),
                ].filter(Boolean);

                const resp = await fetch('{{ route("publish.pipeline.ai-search") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({
                        topic: this.aiSearchTopic,
                        draft_id: this.draftId || null,
                        count: 6,
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

        async searchPrProfiles() {
            this.prProfileSearching = true;
            this.prProfileDropdownOpen = true;
            try {
                const params = new URLSearchParams({ q: this.prProfileSearch || '' });
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
            profile.context = '';
            this.selectedPrProfiles.push(profile);
            this.prProfileSearch = '';
            this.prProfileResults = [];
            this.savePipelineState();
            // Auto-load Notion data + relations + photos
            this.loadProfileData(profile);
        },

        async loadProfileData(profile) {
            if (!this.prSubjectData[profile.id]) {
                this.prSubjectData[profile.id] = {
                    loading: false, loaded: false,
                    fields: [], driveUrl: '', photos: [], loadingPhotos: false,
                    notionUrl: '', relations: [], selectedEntries: {}, selectedPhotos: {},
                };
            }
            const pd = this.prSubjectData[profile.id];
            pd.loading = true;
            pd.fields = [];
            pd.relations = [];

            // Local fields fallback
            if (profile.fields && Object.keys(profile.fields).length > 0) {
                for (const [key, val] of Object.entries(profile.fields)) {
                    if (val) pd.fields.push({ key, notion_field: key, value: val, display_value: val });
                }
            }

            // Notion-linked: use context endpoint for fields + relations
            if (profile.external_source === 'notion' && profile.external_id) {
                pd.notionUrl = 'https://notion.so/' + profile.external_id.replace(/-/g, '');
                try {
                    const resp = await fetch('/notion/profile/' + profile.id + '/context', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ fresh: false }),
                    });
                    const data = await resp.json();
                    if (data.success) {
                        if (data.fields?.length) pd.fields = data.fields;
                        pd.driveUrl = data.profile?.drive_url || '';
                        pd.notionUrl = data.profile?.notion_url || pd.notionUrl;
                        pd.relations = (data.relations || []).map(r => ({
                            ...r, open: true, loading: false, loaded: false, entries: [],
                        }));
                        // Auto-load all relation entries
                        for (const rel of pd.relations) {
                            this.loadPrRelation(profile.id, rel.slug);
                        }
                        // Auto-load photos
                        if (pd.driveUrl) this.loadProfilePhotos(profile);
                    }
                } catch (e) {}
            }
            pd.loaded = true;
            pd.loading = false;
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ fresh: false }),
                });
                const data = await resp.json();
                if (data.success) {
                    rel.entries = (data.entries || []).map(e => ({ ...e, open: false, loading: false, detail: null }));
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ fresh: false }),
                });
                const data = await resp.json();
                if (data.success) entry.detail = data.page || null;
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
        },

        togglePrPhotoSelect(profileId, photoId) {
            const pd = this.prSubjectData[profileId];
            if (!pd) return;
            if (!pd.selectedPhotos) pd.selectedPhotos = {};
            pd.selectedPhotos[photoId] = !pd.selectedPhotos[photoId];
        },

        buildPrSubjectContext() {
            if (!this.selectedPrProfiles?.length) return null;
            const parts = [];
            for (const profile of this.selectedPrProfiles) {
                const pd = this.prSubjectData[profile.id];
                if (!pd?.loaded) continue;
                let section = '--- SUBJECT: ' + profile.name + ' ---\n';
                // User instructions
                if (profile.context) section += 'INSTRUCTIONS: ' + profile.context + '\n\n';
                // Profile fields
                if (pd.fields?.length) {
                    section += 'PROFILE DATA:\n';
                    for (const f of pd.fields) {
                        if (f.display_value || f.value) section += '- ' + f.notion_field + ': ' + (f.display_value || f.value) + '\n';
                    }
                    section += '\n';
                }
                // Selected relation entries
                const selectedIds = Object.keys(pd.selectedEntries || {}).filter(id => pd.selectedEntries[id]);
                if (selectedIds.length > 0 && pd.relations?.length) {
                    for (const rel of pd.relations) {
                        const selected = (rel.entries || []).filter(e => selectedIds.includes(e.id));
                        if (selected.length === 0) continue;
                        section += rel.label.toUpperCase() + ':\n';
                        for (const entry of selected) {
                            section += '  - ' + entry.title + '\n';
                            if (entry.detail?.properties) {
                                for (const [k, v] of Object.entries(entry.detail.properties)) {
                                    if (v && typeof v === 'string' && v.length > 0) section += '    ' + k + ': ' + v + '\n';
                                }
                            }
                        }
                        section += '\n';
                    }
                }
                // Selected photos
                const selectedPhotoIds = Object.keys(pd.selectedPhotos || {}).filter(id => pd.selectedPhotos[id]);
                if (selectedPhotoIds.length > 0) {
                    section += 'SELECTED PHOTOS (' + selectedPhotoIds.length + '):\n';
                    for (const photo of (pd.photos || [])) {
                        if (selectedPhotoIds.includes(photo.id)) {
                            section += '- ' + photo.name + ' [URL: ' + (photo.webViewLink || photo.webContentLink || '') + ']\n';
                        }
                    }
                    section += 'Use [PHOTO: filename] markers in the article body where each selected photo should be placed.\n\n';
                }
                parts.push(section);
            }
            return parts.length ? parts.join('\n') : null;
        },

        async loadProfilePhotos(profile) {
            const data = this.prSubjectData[profile.id];
            if (!data || !data.driveUrl) return;
            data.loadingPhotos = true;
            try {
                const resp = await fetch('{{ route("notion.profile.fetch-photos") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ drive_url: data.driveUrl }),
                });
                const result = await resp.json();
                if (result.success) {
                    data.photos = result.photos || [];
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
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
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
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
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
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
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
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
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
