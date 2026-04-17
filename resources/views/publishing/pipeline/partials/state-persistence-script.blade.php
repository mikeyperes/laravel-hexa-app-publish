        // ── State Persistence ────────────────────────────
        _stateVersion: 4,

        normalizePhotoSuggestionState(ps, idx) {
            const suggestion = { ...(ps || {}) };

            suggestion.autoPhoto = this.sanitizePhotoAssetForPersistence(suggestion.autoPhoto || null);
            suggestion.confirmed = !!suggestion.confirmed;
            suggestion.removed = !!suggestion.removed;
            suggestion.refreshingMeta = false;
            suggestion.searching = false;
            suggestion.loadError = '';
            suggestion.searchResults = [];
            suggestion.suggestedFilename = suggestion.suggestedFilename || this.buildFilename(suggestion.search_term, idx + 1);
            suggestion.loadAttempted = !!suggestion.autoPhoto;
            suggestion.thumbLoading = false;
            suggestion.thumbError = '';

            return suggestion;
        },

        normalizeUniqueTextList(values, limit = 10) {
            const seen = new Set();

            return (Array.isArray(values) ? values : [])
                .map((value) => String(value || '').trim())
                .filter((value) => {
                    if (!value) return false;
                    const key = value.toLowerCase();
                    if (seen.has(key)) return false;
                    seen.add(key);
                    return true;
                })
                .slice(0, limit);
        },

        metadataSelectionDefaults(categoryCount = 0, tagCount = 0) {
            return {
                categories: Array.from({ length: Math.min(3, Number(categoryCount || 0)) }, (_, i) => i),
                tags: Array.from({ length: Math.min(5, Number(tagCount || 0)) }, (_, i) => i),
            };
        },

        selectedCategoryNames() {
            return (Array.isArray(this.suggestedCategories) ? this.suggestedCategories : [])
                .filter((value, idx) => Array.isArray(this.selectedCategories) && this.selectedCategories.includes(idx));
        },

        selectedTagNames() {
            return (Array.isArray(this.suggestedTags) ? this.suggestedTags : [])
                .filter((value, idx) => Array.isArray(this.selectedTags) && this.selectedTags.includes(idx));
        },

        applyGeneratedMetadata(metadata = {}) {
            const titles = this.normalizeUniqueTextList(metadata.titles || [], 10);
            const categories = this.normalizeUniqueTextList(metadata.categories || [], 10);
            const tags = this.normalizeUniqueTextList(metadata.tags || [], 10);
            const defaults = this.metadataSelectionDefaults(categories.length, tags.length);

            this.suggestedTitles = titles;
            this.suggestedCategories = categories;
            this.suggestedTags = tags;
            this.selectedTitleIdx = 0;
            this.selectedCategories = defaults.categories;
            this.selectedTags = defaults.tags;

            if (titles.length > 0) {
                this.articleTitle = titles[0];
            }

            if (typeof metadata.description === 'string' && metadata.description.trim()) {
                this.articleDescription = metadata.description.trim();
            }
        },

        addCustomMetadataValue(kind, rawValue) {
            const value = String(rawValue || '').trim();
            if (!value) return;

            const listKey = kind === 'category' ? 'suggestedCategories' : 'suggestedTags';
            const selectionKey = kind === 'category' ? 'selectedCategories' : 'selectedTags';
            const existingIndex = this[listKey].findIndex(item => String(item || '').trim().toLowerCase() === value.toLowerCase());

            if (existingIndex !== -1) {
                if (!this[selectionKey].includes(existingIndex)) {
                    this[selectionKey] = [...this[selectionKey], existingIndex];
                }
                return;
            }

            const nextIndex = this[listKey].length;
            this[listKey] = [...this[listKey], value];
            this[selectionKey] = [...this[selectionKey], nextIndex];
        },

        isGoogleLikePhotoSource(source = '') {
            return ['google', 'google-cse'].includes(String(source || '').toLowerCase());
        },

        metadataTextLooksBad(text = '') {
            return /wikipedia|pexels|unsplash|pixabay|imdb|getty|shutterstock|dreamstime|alamy|flickr|wikimedia/i.test(String(text || ''));
        },

        photoSuggestionNeedsMetadata(ps = null) {
            const source = ps?.autoPhoto?.source || '';
            const alt = String(ps?.alt_text || '').trim();
            const caption = String(ps?.caption || '').trim();
            const filename = String(ps?.suggestedFilename || '').trim();

            if (!alt || !caption || !filename || filename === 'auto') {
                return true;
            }

            if (this.metadataTextLooksBad(alt) || this.metadataTextLooksBad(caption)) {
                return true;
            }

            return false;
        },

        featuredPhotoNeedsMetadata() {
            const source = this.featuredPhoto?.source || '';
            const alt = String(this.featuredAlt || '').trim();
            const caption = String(this.featuredCaption || '').trim();
            const filename = String(this.featuredFilename || '').trim();

            if (!alt || !caption || !filename || filename === 'auto') {
                return true;
            }

            if (this.metadataTextLooksBad(alt) || this.metadataTextLooksBad(caption)) {
                return true;
            }

            return false;
        },

        setPhotoThumbPending(idx) {
            if (!this.photoSuggestions[idx]) return;
            this.photoSuggestions[idx].thumbLoading = !!this.photoSuggestions[idx].autoPhoto;
            this.photoSuggestions[idx].thumbError = '';
            this.scheduleThumbStateReconcile('inline:' + idx);
        },

        setFeaturedThumbPending() {
            this.featuredThumbLoading = !!this.featuredPhoto;
            this.featuredThumbError = '';
            this.scheduleThumbStateReconcile('featured');
        },

        _clearThumbReconcileTimers() {
            (this._thumbReconcileTimers || []).forEach(timer => clearTimeout(timer));
            this._thumbReconcileTimers = [];
        },

        scheduleThumbStateReconcile(reason = '') {
            this._clearThumbReconcileTimers();
            [60, 220, 700, 1600].forEach(delay => {
                const timer = setTimeout(() => this.reconcileThumbState(reason), delay);
                this._thumbReconcileTimers.push(timer);
            });
        },

        reconcileThumbState(reason = '') {
            this.$nextTick(() => {
                if (this.featuredPhoto) {
                    const featuredImg = document.querySelector('[data-featured-thumb]');
                    if (featuredImg?.complete) {
                        if (featuredImg.naturalWidth > 0) {
                            this.featuredThumbLoading = false;
                            this.featuredThumbError = '';
                        } else {
                            this.featuredThumbLoading = false;
                            this.featuredThumbError = 'Featured thumbnail failed to load';
                        }
                    }
                } else {
                    this.featuredThumbLoading = false;
                    this.featuredThumbError = '';
                }

                document.querySelectorAll('[data-inline-thumb-index]').forEach((img) => {
                    const idx = Number(img.getAttribute('data-inline-thumb-index'));
                    if (!Number.isFinite(idx) || !this.photoSuggestions[idx]?.autoPhoto) return;

                    if (img.complete) {
                        if (img.naturalWidth > 0) {
                            this.photoSuggestions[idx].thumbLoading = false;
                            this.photoSuggestions[idx].thumbError = '';
                        } else {
                            this.photoSuggestions[idx].thumbLoading = false;
                            this.photoSuggestions[idx].thumbError = 'Thumbnail failed to load';
                        }
                    }
                });

                this._logDebug('enrichment', 'Reconciled thumbnail state', {
                    stage: 'enrichment',
                    substage: 'thumb_reconcile',
                    details: reason,
                });
            });
        },

        sanitizePhotoAssetForPersistence(photo) {
            if (!photo || typeof photo !== 'object') return null;

            const normalized = {
                id: photo.id ?? null,
                source: photo.source || '',
                source_url: photo.source_url || photo.pexels_url || photo.unsplash_url || photo.pixabay_url || photo.url || '',
                url: photo.url || photo.url_large || photo.url_full || photo.url_thumb || '',
                url_thumb: photo.url_thumb || photo.url_large || photo.url_full || photo.url || '',
                url_large: photo.url_large || photo.url_full || photo.url_thumb || photo.url || '',
                url_full: photo.url_full || photo.url_large || photo.url_thumb || photo.url || '',
                alt: photo.alt || '',
                photographer: photo.photographer || '',
                photographer_url: photo.photographer_url || '',
                width: Number(photo.width || 0),
                height: Number(photo.height || 0),
            };

            if (!normalized.url && !normalized.url_large && !normalized.url_thumb && !normalized.url_full) {
                return null;
            }

            return normalized;
        },

        sanitizePhotoSuggestionForPersistence(ps, idx) {
            const suggestion = ps || {};

            return {
                position: Number.isFinite(Number(suggestion.position)) ? Number(suggestion.position) : idx,
                search_term: (suggestion.search_term || '').trim(),
                alt_text: suggestion.alt_text || '',
                caption: suggestion.caption || '',
                suggestedFilename: suggestion.suggestedFilename || this.buildFilename(suggestion.search_term, idx + 1),
                autoPhoto: this.sanitizePhotoAssetForPersistence(suggestion.autoPhoto || null),
                confirmed: !!(suggestion.autoPhoto && suggestion.confirmed),
                removed: !!suggestion.removed,
            };
        },

        sanitizePhotoSuggestionsForPersistence(list = this.photoSuggestions || []) {
            return (Array.isArray(list) ? list : []).map((ps, idx) => this.sanitizePhotoSuggestionForPersistence(ps, idx));
        },

        sanitizeSelectedUserForPersistence(user) {
            if (!user || typeof user !== 'object') return null;

            return {
                id: user.id ?? null,
                name: user.name || '',
            };
        },

        supportingUrlTypeOptions() {
            return @json(config('hws-publish.supporting_url_types', []));
        },

        supportingUrlTypeLabel() {
            return this.supportingUrlTypeOptions()?.[this.supportingUrlType]?.label || 'Matching Content Type';
        },

        supportingUrlTypeDescription() {
            return this.supportingUrlTypeOptions()?.[this.supportingUrlType]?.description
                || 'Prefer supporting URLs that match the article’s actual content category and editorial style.';
        },

        resolvePhotoThumbUrl(photo) {
            return photo?.url_thumb || photo?.url_large || photo?.url_full || photo?.url || '';
        },

        resolvePhotoLargeUrl(photo) {
            return photo?.url_large || photo?.url_full || photo?.url_thumb || photo?.url || '';
        },

        hasUnresolvedPhotoSuggestions() {
            return (this.photoSuggestions || []).some(ps => !ps?.removed && !ps?.autoPhoto && (ps?.search_term || '').trim());
        },

        hasPendingPhotoSuggestions() {
            return (this.photoSuggestions || []).some(ps => !ps?.removed && !ps?.autoPhoto && !ps?.loadAttempted && (ps?.search_term || '').trim());
        },

        _pendingInlinePhotoHydrationSignature() {
            return this._stableSignature((this.photoSuggestions || [])
                .map((ps, idx) => ({
                    idx,
                    removed: !!ps?.removed,
                    has_photo: !!ps?.autoPhoto,
                    load_attempted: !!ps?.loadAttempted,
                    search_term: (ps?.search_term || '').trim(),
                }))
                .filter(ps => !ps.removed && !ps.has_photo && !ps.load_attempted && ps.search_term));
        },

        shouldAutoHydrateInlinePhotos() {
            return !this._restoring
                && !this.autoFetchingPhotos
                && this.hasPendingPhotoSuggestions()
                && (this.currentStep === 6 || this.openSteps.includes(6));
        },

        queueInlinePhotoAutoHydration(reason = '', delay = 120) {
            if (this._inlinePhotoAutoHydrationTimer) {
                clearTimeout(this._inlinePhotoAutoHydrationTimer);
                this._inlinePhotoAutoHydrationTimer = null;
            }

            if (!this.shouldAutoHydrateInlinePhotos()) {
                return;
            }

            const signature = this._pendingInlinePhotoHydrationSignature();
            if (!signature || signature === this._lastInlinePhotoHydrationSignature) {
                return;
            }

            this._inlinePhotoAutoHydrationTimer = setTimeout(() => {
                this._inlinePhotoAutoHydrationTimer = null;

                if (!this.shouldAutoHydrateInlinePhotos()) {
                    return;
                }

                const nextSignature = this._pendingInlinePhotoHydrationSignature();
                if (!nextSignature || nextSignature === this._lastInlinePhotoHydrationSignature) {
                    return;
                }

                this._lastInlinePhotoHydrationSignature = nextSignature;
                this._logActivity('enrichment', 'info', 'Auto-loading inline photo suggestions', {
                    stage: 'enrichment',
                    substage: 'inline_photos_auto',
                    details: this._summarizeValue({
                        reason,
                        pending_count: (this.photoSuggestions || []).filter(ps => !ps?.removed && !ps?.autoPhoto && !ps?.loadAttempted && (ps?.search_term || '').trim()).length,
                    }, 200),
                    debug_only: !this.pipelineDebugEnabled,
                });
                this.autoFetchPhotos();
            }, delay);
        },

        shouldAutoHydrateFeaturedImage() {
            return !this._restoring
                && !this.featuredSearching
                && !!(this.featuredImageSearch || '').trim()
                && !this.featuredPhoto
                && (this.currentStep === 6 || this.openSteps.includes(6));
        },

        queueFeaturedImageAutoHydration(reason = '', delay = 120) {
            if (this._featuredAutoHydrationTimer) {
                clearTimeout(this._featuredAutoHydrationTimer);
                this._featuredAutoHydrationTimer = null;
            }

            if (!this.shouldAutoHydrateFeaturedImage()) {
                return;
            }

            const signature = (this.featuredImageSearch || '').trim().toLowerCase();
            if (!signature || signature === this._lastFeaturedAutoHydrationSignature) {
                return;
            }

            this._featuredAutoHydrationTimer = setTimeout(() => {
                this._featuredAutoHydrationTimer = null;

                if (!this.shouldAutoHydrateFeaturedImage()) {
                    return;
                }

                const nextSignature = (this.featuredImageSearch || '').trim().toLowerCase();
                if (!nextSignature || nextSignature === this._lastFeaturedAutoHydrationSignature) {
                    return;
                }

                this._lastFeaturedAutoHydrationSignature = nextSignature;
                this._logActivity('enrichment', 'info', 'Auto-loading featured image suggestions', {
                    stage: 'enrichment',
                    substage: 'featured_auto',
                    details: this._summarizeValue({
                        reason,
                        search_term: this.featuredImageSearch,
                    }, 200),
                    debug_only: !this.pipelineDebugEnabled,
                });
                this.searchFeaturedImage();
            }, delay);
        },

        syncDeferredEnrichmentState(reason = '', { log = true } = {}) {
            const nextPhotoPending = this.hasPendingPhotoSuggestions();
            const nextFeaturedPending = !!((this.featuredImageSearch || '').trim() && !this.featuredPhoto);
            const changed = nextPhotoPending !== this.photoSuggestionsPending || nextFeaturedPending !== this.featuredSearchPending;

            this.photoSuggestionsPending = nextPhotoPending;
            this.featuredSearchPending = nextFeaturedPending;

            if (!nextPhotoPending) {
                this._lastInlinePhotoHydrationSignature = '';
            }
            if (!nextFeaturedPending) {
                this._lastFeaturedAutoHydrationSignature = '';
            }

            if (changed && log) {
                this._logDebug('enrichment', 'Deferred enrichment state updated', {
                    stage: 'enrichment',
                    substage: 'sync',
                    details: this._summarizeValue({
                        reason,
                        photo_suggestions_pending: this.photoSuggestionsPending,
                        featured_search_pending: this.featuredSearchPending,
                    }, 400),
                });
            }
        },

        shouldAutoLoadPromptPreview() {
            return !this._restoring && (this.promptLogOpen || this.currentStep === 5 || this.openSteps.includes(5));
        },

        buildPromptPreviewPayload() {
            const sourceTexts = this.currentArticleType === 'press-release'
                ? [this.buildPressReleaseSourceText(true)]
                : (
                    this.approvedSources.length > 0
                        ? this.approvedSources.map(i => this.checkResults[i]?.text || '').filter(Boolean)
                        : ['[Source articles will be inserted here]']
                );

            return {
                source_texts: sourceTexts,
                template_id: this.selectedTemplateId || null,
                preset_id: this.selectedPresetId || null,
                prompt_slug: this.currentPressReleasePromptSlug(),
                custom_prompt: this.customPrompt || null,
                web_research: this.spinWebResearch,
                supporting_url_type: this.supportingUrlType || 'matching_content_type',
            };
        },

        invalidatePromptPreview(reason = 'changed', { fetch = false } = {}) {
            const hadPreview = !!this.resolvedPrompt || this.promptLog.length > 0 || !this.promptPreviewDirty;

            this.promptPreviewDirty = true;
            this._lastPromptPreviewSignature = '';
            this.resolvedPrompt = '';
            this.promptLog = [];

            if (hadPreview) {
                this._logDebug('prompt', 'Prompt preview invalidated', {
                    stage: 'prompt',
                    substage: 'invalidated',
                    details: reason,
                });
            }

            if (fetch || this.shouldAutoLoadPromptPreview()) {
                this._queuePromptRefresh(reason);
            }
        },

        init() {
            this._ensureTabInstanceId();
            this._clientSessionTraceId = this._buildClientTrace('session');
            this._installActivityFetchTracker();
            window.addEventListener('beforeunload', () => { this._pageUnloading = true; }, { once: true });
            window.addEventListener('pagehide', () => { this._pageUnloading = true; }, { once: true });
            this._restoreMasterActivityLog();
            if (this.pipelineDebugEnabled) {
                this._restoreServerActivityLog(true);
            }
            this._logActivity('lifecycle', 'info', 'Publish pipeline initialized', {
                trace_id: this._clientSessionTraceId,
                draft_id: this.draftId,
            });

            const draftState = this.draftState || {};
            const serverState = this.pipelinePayload && Object.keys(this.pipelinePayload).length > 0
                ? { ...this.pipelinePayload, draftId: this.draftId, _v: this.pipelinePayload._v || this._stateVersion }
                : null;
            const saved = localStorage.getItem(this.pipelineStateKey)
                || localStorage.getItem(this.legacyPipelineStateKey);
            const legacySaved = !saved && !draftState.body ? localStorage.getItem('publishPipelineState') : null;
            const savedState = saved || legacySaved;
            let state = null;
            let restoredFromLocalState = false;

            this._restoring = true;

            if (savedState) {
                try {
                    state = JSON.parse(savedState);
                    if (
                        !state._v ||
                        state._v < this._stateVersion ||
                        (state.draftId && String(state.draftId) !== String(this.draftId))
                    ) {
                        localStorage.removeItem(this.pipelineStateKey);
                        state = null;
                    }
                } catch (e) {
                    state = null;
                }
                restoredFromLocalState = !!state;
            }

            if (!state && serverState) {
                state = serverState;
            }

            if (state) {
                // ── Declarative restore — all persistentFields auto-restore ──
                for (const key of this.persistentFields) {
                    if (state[key] !== undefined && state[key] !== null) {
                        this[key] = state[key];
                    }
                }

                // ── Special handling for fields that need post-processing ──
                // Arrays that may serialize as objects in Alpine proxy
                if (state.openSteps) this.openSteps = Array.isArray(state.openSteps) ? state.openSteps : Object.values(state.openSteps);
                if (state.completedSteps) this.completedSteps = Array.isArray(state.completedSteps) ? state.completedSteps : Object.values(state.completedSteps);
                this.pressRelease = this.restorePressReleaseStateFromLegacy(state);

                // Site connection (nested object)
                if (state.selectedSiteId) {
                    this.selectedSiteId = String(state.selectedSiteId);
                    this.siteConn.status = state.siteConnStatus ?? null;
                    this.siteConn.message = state.siteConnMessage || '';
                    if (state.siteConnLog) this.siteConn.log = state.siteConnLog;
                    if (state.siteConnAuthors) this.siteConn.authors = state.siteConnAuthors;
                    this.$nextTick(() => {
                        this.selectedSiteId = String(state.selectedSiteId);
                        this.selectedSite = this.sites.find(s => s.id == state.selectedSiteId) || state.selectedSite || null;
                    });
                }

                // Editor content needs TinyMCE sync
                if (state.spunContent || state.editorContent) {
                    const restoredEditorHtml = state.editorContent || state.spunContent;
                    this.spunContent = restoredEditorHtml;
                    this.editorContent = restoredEditorHtml;
                    this.spunWordCount = state.spunWordCount || this.countWordsFromHtml(restoredEditorHtml);
                    this.rememberDraftBody(restoredEditorHtml);
                    this.setSpinEditor(restoredEditorHtml);
                    this.extractArticleLinks(restoredEditorHtml);
                }

                // Title from suggested titles
                if (state.selectedTitleIdx !== undefined && this.suggestedTitles[this.selectedTitleIdx]) {
                    this.articleTitle = this.suggestedTitles[this.selectedTitleIdx];
                }

                // Check results count
                if (state.checkResults) {
                    this.checkPassCount = state.checkResults.filter(r => r.success).length;
                }

                this.suggestedTitles = this.normalizeUniqueTextList(this.suggestedTitles || [], 10);
                this.suggestedCategories = this.normalizeUniqueTextList(this.suggestedCategories || [], 10);
                this.suggestedTags = this.normalizeUniqueTextList(this.suggestedTags || [], 10);
                this.selectedCategories = (Array.isArray(this.selectedCategories) ? this.selectedCategories : []).filter(idx => idx < this.suggestedCategories.length);
                this.selectedTags = (Array.isArray(this.selectedTags) ? this.selectedTags : []).filter(idx => idx < this.suggestedTags.length);

                // Photo suggestions — reset loading flags + rebuild filenames
                if (state.photoSuggestions) {
                    this.photoSuggestions = state.photoSuggestions.map((ps, idx) => this.normalizePhotoSuggestionState(ps, idx));
                    this._lastInlinePhotoHydrationSignature = '';
                }

                // Featured photo needs results array
                if (state.featuredPhoto) {
                    this.featuredPhoto = this.sanitizePhotoAssetForPersistence(state.featuredPhoto);
                    this.featuredResults = this.featuredPhoto ? [this.featuredPhoto] : [];
                    this.featuredThumbLoading = false;
                    this.featuredThumbError = '';
                }

                // AI detection — reset loading flags
                if (state.aiDetectionResults) {
                    Object.keys(this.aiDetectionResults).forEach(k => { this.aiDetectionResults[k].loading = false; });
                }

            }

            if (serverState && restoredFromLocalState) {
                if (!this.pressRelease || Object.keys(this.pressRelease).length === 0) {
                    this.pressRelease = this.restorePressReleaseStateFromLegacy(serverState);
                }
                if (!Array.isArray(this.pressRelease.photo_files) || this.pressRelease.photo_files.length === 0) {
                    this.pressRelease.photo_files = this.normalizePressReleaseState(serverState.pressRelease || {}).photo_files || [];
                }
                if (!Array.isArray(this.pressRelease.document_files) || this.pressRelease.document_files.length === 0) {
                    this.pressRelease.document_files = this.normalizePressReleaseState(serverState.pressRelease || {}).document_files || [];
                }
            }

            // Database-backed draft state is authoritative for site/user/template/preset restore.
            // But localStorage (pipeline state) takes precedence if user changed it this session.
            if (state?.selectedUser) {
                this.selectedUser = this.sanitizeSelectedUserForPersistence(state.selectedUser);
            } else if (draftState.selectedUser) {
                this.selectedUser = this.sanitizeSelectedUserForPersistence(draftState.selectedUser);
            }
            if (draftState.selectedSiteId) {
                this.selectedSiteId = String(draftState.selectedSiteId);
                this.selectedSite = this.sites.find(s => s.id == draftState.selectedSiteId) || draftState.selectedSite || null;
                this.siteConn.status = draftState.siteConnStatus ?? this.siteConn.status;
                if (draftState.siteConnStatus === true) {
                    this.siteConn.message = 'Loaded from saved draft.';
                }
            }
            if (draftState.publishAuthor) this.publishAuthor = draftState.publishAuthor;
            if (!this.articleTitle && draftState.articleTitle) this.articleTitle = draftState.articleTitle;
            if (!this.aiModel && draftState.aiModel) this.aiModel = draftState.aiModel;
            if (!this.spunContent && draftState.body) {
                this.spunContent = draftState.body;
                this.editorContent = draftState.body;
                this.spunWordCount = draftState.wordCount || this.countWordsFromHtml(draftState.body);
                this.rememberDraftBody(draftState.body);
                this.extractArticleLinks(draftState.body);
                this.$nextTick(() => this.setSpinEditor(draftState.body));
            }
            if (!this.spunContent && !this.editorContent && this.latestCompletedPrepareHtml) {
                this.spunContent = this.latestCompletedPrepareHtml;
                this.editorContent = this.latestCompletedPrepareHtml;
                this.preparedHtml = this.latestCompletedPrepareHtml;
                this.spunWordCount = this.countWordsFromHtml(this.latestCompletedPrepareHtml);
                this.rememberDraftBody(this.latestCompletedPrepareHtml);
                this.extractArticleLinks(this.latestCompletedPrepareHtml);
                this.$nextTick(() => this.setSpinEditor(this.latestCompletedPrepareHtml));
            }
            if ((!Array.isArray(this.photoSuggestions) || this.photoSuggestions.length === 0) && Array.isArray(draftState.photoSuggestions)) {
                this.photoSuggestions = draftState.photoSuggestions.map((ps, idx) => this.normalizePhotoSuggestionState(ps, idx));
                this._lastInlinePhotoHydrationSignature = '';
            }
            if (!this.featuredImageSearch && draftState.featuredImageSearch) {
                this.featuredImageSearch = draftState.featuredImageSearch;
            }
            if ((this.spunContent || draftState.body) && !state?.currentStep) {
                this.currentStep = 6;
                this.openSteps = [6];
                this.completedSteps = Array.from(new Set([...(this.completedSteps || []), 1, 2, 3, 4, 5]));
            }

            // Auto-complete steps based on restored data
            if (this.selectedUser && !this.completedSteps.includes(1)) this.completedSteps.push(1);
            if (this.selectedSiteId && !this.completedSteps.includes(2)) this.completedSteps.push(2);
            if ((this.currentArticleType === 'press-release' ? this.hasPressReleaseSubmittedContent() : this.sources.length > 0) && !this.completedSteps.includes(3)) this.completedSteps.push(3);
            if ((this.currentArticleType === 'press-release' ? this.hasPressReleaseValidationData() : this.checkResults.length > 0) && !this.completedSteps.includes(4)) this.completedSteps.push(4);
            if (this.spunContent && !this.completedSteps.includes(5)) this.completedSteps.push(5);
            if (this.spunContent && !this.completedSteps.includes(6)) this.completedSteps.push(6);

            const finishRestore = () => {
                const restoredStateSignature = this._stableSignature(this.buildPipelineStateSnapshot());
                this._lastLocalPipelineStateSignature = restoredStateSignature;
                this._lastServerPipelineStateSignature = restoredStateSignature;
                localStorage.setItem(this.pipelineStateKey, JSON.stringify(this.buildPipelineStateSnapshot()));
                localStorage.removeItem('publishPipelineState');
                this._restoring = false;
                // Auto-select PR source if press-release type and only one source
                this.autoSelectPrSource();
                // If there's already spun content, ensure Create Article is accessible
                if (this.spunContent || this.editorContent) {
                    if (!this.completedSteps.includes(5)) this.completedSteps.push(5);
                    if (this.currentStep <= 5) {
                        this.currentStep = 6;
                        this.openSteps = [6];
                    }
                }
                // Restore step from URL query string (overrides saved state)
                const urlStep = new URLSearchParams(window.location.search).get('step');
                if (urlStep) {
                    const s = parseInt(urlStep);
                    if (s >= 1 && s <= 7 && this.isStepAccessible(s)) {
                        this.currentStep = s;
                        this.openSteps = [s];
                    }
                }

                this.syncDeferredEnrichmentState('restore_complete', { log: false });
                this.hydrateResolvedPhotoPlaceholders('restore_complete');
                this.scheduleThumbStateReconcile('restore_complete');
                this.queueInlinePhotoAutoHydration('restore_complete');
                this.queueFeaturedImageAutoHydration('restore_complete');
                if (this.shouldAutoLoadPromptPreview()) {
                    this._queuePromptRefresh('restore_complete');
                }

                this._logActivity('restore', 'info', 'Pipeline restore complete', {
                    trace_id: this._clientSessionTraceId,
                    stage: 'restore',
                    substage: 'complete',
                    details: this._summarizeValue({
                        source: restoredFromLocalState ? 'local-storage' : (serverState ? 'server/draft' : 'draft'),
                        current_step: this.currentStep,
                        completed_steps: this.completedSteps,
                        selected_site_id: this.selectedSiteId,
                        has_content: !!(this.spunContent || this.editorContent),
                    }, 900),
                });

                this.$nextTick(() => this._restorePipelineOperations());
            };

            if (this.selectedUser) {
                const restoredPresetId = draftState.selectedPresetId || state?.selectedPresetId || '';
                const restoredPreset = state?.selectedPreset || null;
                const restoredTemplateId = draftState.selectedTemplateId || state?.selectedTemplateId || '';
                const restoredTemplate = state?.selectedTemplate || null;
                const usingBootstrappedPresets = this._useBootstrappedPresetsIfAvailable();
                const usingBootstrappedTemplates = this._useBootstrappedTemplatesIfAvailable();

                this.presetsLoading = !usingBootstrappedPresets;
                this.templatesLoading = !usingBootstrappedTemplates;
                window.dispatchEvent(new CustomEvent('hexa-form-loading', {
                    detail: {
                        component_id: 'article-preset-form',
                        loading: !usingBootstrappedTemplates,
                    }
                }));

                const presetsPromise = usingBootstrappedPresets
                    ? Promise.resolve(this.presets)
                    : this.loadUserPresets();
                const templatesPromise = usingBootstrappedTemplates
                    ? Promise.resolve(this.templates)
                    : this.loadUserTemplates();

                Promise.allSettled([presetsPromise, templatesPromise])
                    .then((results) => {
                        const rejected = results.filter(result => result.status === 'rejected');
                        if (rejected.length > 0) {
                            this._logActivity('restore', 'warning', 'Preset/template bootstrap fallback encountered an error', {
                                stage: 'restore',
                                substage: 'preset_template_load',
                                details: this._summarizeValue(rejected.map(result => String(result.reason?.message || result.reason || 'Unknown restore error')), 400),
                            });
                        }

                        try {
                            // Restore saved preset or auto-select default
                            if (restoredPresetId) {
                                this.selectedPresetId = String(restoredPresetId);
                                this.selectedPreset = this.presets.find(p => p.id == restoredPresetId) || restoredPreset;
                            } else {
                                const defaultPreset = this.presets.find(p => p.is_default);
                                if (defaultPreset) {
                                    this.selectedPresetId = String(defaultPreset.id);
                                    this.selectedPreset = defaultPreset;
                                }
                            }
                            if (this.selectedPreset) {
                                this.loadPresetFields('preset', this.selectedPreset, null, state?.preset_overrides || null);
                            }
                            if (state?.preset_overrides) {
                                Object.assign(this.preset_overrides, state.preset_overrides);
                                if (state.preset_dirty) Object.assign(this.preset_dirty, state.preset_dirty);
                            }

                            // Restore saved template or auto-select default
                            if (restoredTemplateId) {
                                this.selectedTemplateId = String(restoredTemplateId);
                                this.selectedTemplate = this.templates.find(t => t.id == restoredTemplateId) || restoredTemplate;
                            } else {
                                const defaultTemplate = this.templates.find(t => t.is_default);
                                if (defaultTemplate) {
                                    this.selectedTemplateId = String(defaultTemplate.id);
                                    this.selectedTemplate = defaultTemplate;
                                }
                            }
                            if (this.selectedTemplate) {
                                // Pass saved overrides directly so loadPresetFields sends merged values to the form
                                this.loadPresetFields('template', this.selectedTemplate, null, state?.template_overrides || null);
                            }
                            // Re-apply saved overrides to the pipeline override layer
                            if (state?.template_overrides) {
                                Object.assign(this.template_overrides, state.template_overrides);
                                if (state.template_dirty) Object.assign(this.template_dirty, state.template_dirty);
                            }
                            // Sync article_type to standalone dropdown
                            if (this.selectedTemplate?.article_type && !this.template_overrides?.article_type) {
                                this.template_overrides.article_type = this.selectedTemplate.article_type;
                            }

                            // Auto-select site from default preset if no site saved
                            if (this.selectedPreset?.default_site_id && !this.selectedSiteId) {
                                this.selectedSiteId = String(this.selectedPreset.default_site_id);
                                this.selectedSite = this.sites.find(s => s.id == this.selectedPreset.default_site_id) || null;
                            }
                        } catch (error) {
                            this._logActivity('restore', 'error', 'Preset/template restore failed: ' + (error?.message || 'Unknown restore error'), {
                                stage: 'restore',
                                substage: 'preset_template_restore',
                            });
                        }

                        finishRestore();
                    })
                    .finally(() => {
                        this.presetsLoading = false;
                        this.templatesLoading = false;
                        window.dispatchEvent(new CustomEvent('hexa-form-loading', {
                            detail: {
                                component_id: 'article-preset-form',
                                loading: false,
                            }
                        }));
                    });
            } else {
                finishRestore();
            }

            // ── Auto-watch ALL persistent fields for save ──
            const draftTriggers = ['articleTitle', 'editorContent', 'photoSuggestions', 'featuredImageSearch', 'featuredPhoto', 'featuredAlt', 'featuredCaption', 'featuredFilename'];
            for (const field of this.persistentFields) {
                this.$watch(field, () => {
                    this.queuePipelineStateSave();
                    if (!this._restoring && draftTriggers.includes(field)) {
                        this.queueAutoSaveDraft();
                    }
                });
            }
            // Site connection (nested, not in persistentFields)
            this.$watch('siteConn.status', () => this.queuePipelineStateSave());
            this.$watch('siteConn.authors', () => this.queuePipelineStateSave());
            // Reset prepare state when photos change after prepare is complete
            // NOTE: don't watch editorContent — it changes on every TinyMCE sync
            const invalidatePrepare = () => { if (this.prepareComplete && !this._restoring && !this.preparing) { this.prepareComplete = false; } };
            this.$watch('featuredPhoto', invalidatePrepare);
            this.$watch('photoSuggestions', invalidatePrepare);
            this.$watch('selectedTemplateId', () => { if (!this._restoring) this.invalidatePromptPreview('template_changed'); });
            this.$watch('selectedPresetId', () => { if (!this._restoring) this.invalidatePromptPreview('preset_changed'); });
            this.$watch('currentStep', step => {
                if (this._restoring) return;
                if (step === 6 || this.openSteps.includes(6)) {
                    this.queueInlinePhotoAutoHydration('step_change');
                    this.queueFeaturedImageAutoHydration('step_change');
                }
                if ((step === 5 || this.openSteps.includes(5) || this.promptLogOpen) && !this.promptLoading) {
                    this._queuePromptRefresh('step_change');
                }
            });
            this.$watch('openSteps', steps => {
                if (!this._restoring && Array.isArray(steps) && steps.includes(6)) {
                    this.queueInlinePhotoAutoHydration('open_steps');
                    this.queueFeaturedImageAutoHydration('open_steps');
                }
            });
            this.$watch('promptLogOpen', open => {
                if (!this._restoring && open && !this.promptLoading) {
                    this._queuePromptRefresh('prompt_log_open');
                }
            });
            this.$watch('masterActivityLogOpen', open => {
                if (open) this._restoreServerActivityLog(true);
            });
        },

        // ── Declarative persistent fields ─────────────────────
        // Add a field name here = auto-saved AND auto-restored.
        get persistentFields() {
            return [
                // Step tracking
                'currentStep', 'openSteps', 'completedSteps',
                // Step 1 — User
                'selectedUser',
                // Step 2 — Presets + Site
                'selectedPresetId', 'selectedPreset', 'selectedTemplateId', 'selectedTemplate',
                'selectedSiteId', 'selectedSite',
                'template_overrides', 'template_dirty', 'preset_overrides', 'preset_dirty',
                // Step 3 — Sources
                'sources', 'sourceTab', 'pasteText', 'newsMode', 'newsCategory', 'newsTrendingSelected',
                'newsSearch', 'newsResults', 'newsHasSearched',
                'aiSearchTopic', 'aiSearchResults', 'aiHasSearched',
                // Step 4 — Fetch
                'checkResults', 'approvedSources', 'discardedSources', 'expandedSources',
                // Step 5 — AI Spin
                'aiModel', 'customPrompt', 'supportingUrlType', 'spinWebResearch', 'spunContent', 'spunWordCount', 'suggestedTitles',
                'suggestedCategories', 'suggestedTags', 'selectedCategories', 'selectedTags',
                'selectedTitleIdx', 'tokenUsage',
                // Step 6 — Create Article
                'articleTitle', 'articleDescription', 'editorContent',
                'photoSuggestions', 'featuredImageSearch', 'featuredPhoto',
                'featuredAlt', 'featuredCaption', 'featuredFilename',
                // Step 7 — Publish + uploaded media tracking
                'publishAction', 'publishAuthor', 'publishAuthorSource', 'scheduleDate',
                'uploadedImages', 'preparedFeaturedMediaId', 'preparedFeaturedWpUrl',
                // AI Detection
                'aiDetectionResults', 'aiDetectionRan', 'aiDetectionAllPass',
                // Press Release workflow state
                'pressRelease',
                // PR Full Feature — profile subjects
                'selectedPrProfiles',
                // Press Release syndication selection
                'selectedSyndicationCats',
            ];
        },

        queuePipelineStateSave(delay = 150) {
            if (this._restoring) return;
            if (this._draftSessionConflictActive) return;
            if (this._suspendPipelineStateSave) {
                this._pendingPostSuspendPipelineStateSave = true;
                return;
            }
            clearTimeout(this._pipelineStateTimer);
            this._pipelineStateTimer = setTimeout(() => {
                this._pipelineStateTimer = null;
                this.savePipelineState();
            }, delay);
        },

        savePipelineState() {
            if (this._draftSessionConflictActive) return;
            if (this._suspendPipelineStateSave) {
                this._pendingPostSuspendPipelineStateSave = true;
                return;
            }
            if (this._pipelineStateTimer) {
                clearTimeout(this._pipelineStateTimer);
                this._pipelineStateTimer = null;
            }
            const state = this.buildPipelineStateSnapshot();
            const signature = this._stableSignature(state);
            if (signature === this._lastLocalPipelineStateSignature) {
                this._logDebug('state', 'Skipped local pipeline state write (signature unchanged)', {
                    stage: 'state',
                    substage: 'local_skip',
                });
                return;
            }

            this._lastLocalPipelineStateSignature = signature;
            localStorage.setItem(this.pipelineStateKey, JSON.stringify(state));
            localStorage.removeItem('publishPipelineState');
            this._logDebug('state', 'Local pipeline state persisted', {
                stage: 'state',
                substage: 'local_saved',
                details: this._summarizeValue({
                    step: this.currentStep,
                    completed_steps: this.completedSteps,
                    selected_site_id: this.selectedSiteId,
                }, 500),
            });
            if (!this._restoring) {
                this.queueServerPipelineStateSave();
            }
        },

        clearPipeline() {
            localStorage.removeItem(this.pipelineStateKey);
            localStorage.removeItem('publishPipelineState');
            localStorage.removeItem(this.masterActivityLogKey);
            clearTimeout(this._pipelineStateTimer);
            this._pipelineStateTimer = null;
            this.clearMasterActivityLog();
            this._lastLocalPipelineStateSignature = '';
            this._lastServerPipelineStateSignature = '';
            this._lastDraftPayloadSignature = '';
            this._pendingPostSuspendPipelineStateSave = false;
            this._pendingPostSuspendDraftSave = false;
            this._resetPrepareOperationTracking({ clearLog: true });
            this._resetPublishOperationTracking();
            this.currentStep = 1;
            this.openSteps = [1];
            this.completedSteps = [];
            this.selectedUser = null;
            this.userSearch = '';
            this.presets = [];
            this.selectedPreset = null;
            this.selectedPresetId = '';
            this.templates = [];
            this.selectedTemplate = null;
            this.selectedTemplateId = '';
            this.selectedSiteId = '';
            this.selectedSite = null;
            this.sources = [];
            this.checkResults = [];
            this.aiSearchTopic = '';
            this.aiSearchResults = [];
            this.aiSearchError = '';
            this.aiSearchCost = null;
            this.aiHasSearched = false;
            this.aiModel = @json(($pipelineDefaults['spin_model'] ?? 'grok-3'));
            this.aiSearchModel = @json(($pipelineDefaults['search_model'] ?? 'grok-3-mini'));
            this.spunContent = '';
            this.spunWordCount = 0;
            this.articleTitle = '';
            this.editorContent = '';
            this.articleDescription = '';
            this.customPrompt = '';
            this.supportingUrlType = 'matching_content_type';
            this.resolvedPrompt = '';
            this.promptLog = [];
            this.promptPreviewDirty = true;
            this._lastPromptPreviewSignature = '';
            this.spinWebResearch = true;
            this.publishAction = 'draft_local';
            this.publishAuthor = '';
            this.publishAuthorSource = '';
            this.scheduleDate = '';
            this.publishing = false;
            this.publishResult = null;
            this.publishError = '';
            this.preparing = false;
            this.prepareComplete = false;
            this.prepareChecklist = [];
            this.prepareLog = [];
            this.prepareTraceId = '';
            this.prepareIntegrityIssues = [];
            this.uploadedImages = {};
            this.orphanedMedia = [];
            this._previousUploadedImages = null;
            this.photoSuggestions = [];
            this.photoSuggestionsPending = false;
            this.featuredImageSearch = '';
            this.featuredPhoto = null;
            this.featuredResults = [];
            this.featuredSearchPending = false;
            this.featuredThumbLoading = false;
            this.featuredThumbError = '';
            this._clearThumbReconcileTimers();
            this.featuredAlt = '';
            this.featuredCaption = '';
            this.featuredFilename = '';
            this.publishTraceId = '';
            this.suggestedTitles = [];
            this.suggestedCategories = [];
            this.suggestedTags = [];
            this.suggestedUrls = [];
            this.template_overrides = {};
            this.template_dirty = {};
            this.siteConn = { testing: false, status: null, message: '', authors: [], log: [], defaultAuthor: null };
            this.pressRelease = this.normalizePressReleaseState({});
            this.selectedPrProfiles = [];
            this._previousSiteId = null;
            this.editingPreset = false;
            this.editingTemplate = false;
            this.presetsLoading = false;
            this.templatesLoading = false;
            window.dispatchEvent(new CustomEvent('hexa-search-clear', { detail: { component_id: 'pipeline-user' } }));
            this.savePipelineState();
        },
