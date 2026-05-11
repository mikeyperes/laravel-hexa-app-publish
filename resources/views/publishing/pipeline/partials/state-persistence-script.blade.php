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
            suggestion.loadAttempted = !!suggestion.loadAttempted || !!suggestion.autoPhoto;
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
            const rawTitles = this.normalizeUniqueTextList(metadata.titles || [], 10);
            const titles = this.isPrArticleMode()
                ? this.normalizePrGeneratedTitles(rawTitles)
                : rawTitles;
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
                url: this.normalizeHostedMediaUrl(photo.url || photo.url_large || photo.url_full || photo.url_thumb || ''),
                url_thumb: this.normalizeHostedMediaUrl(photo.preview_url || photo.url_thumb || photo.url_large || photo.url_full || photo.url || ''),
                url_large: this.normalizeHostedMediaUrl(photo.url_large || photo.url_full || photo.preview_url || photo.url_thumb || photo.url || ''),
                url_full: this.normalizeHostedMediaUrl(photo.url_full || photo.url_large || photo.preview_url || photo.url_thumb || photo.url || ''),
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
            const autoPhoto = this.sanitizePhotoAssetForPersistence(suggestion.autoPhoto || null);

            return {
                position: Number.isFinite(Number(suggestion.position)) ? Number(suggestion.position) : idx,
                search_term: (suggestion.search_term || '').trim(),
                alt_text: suggestion.alt_text || '',
                caption: suggestion.caption || '',
                suggestedFilename: suggestion.suggestedFilename || this.buildFilename(suggestion.search_term, idx + 1),
                autoPhoto,
                confirmed: !!(autoPhoto && suggestion.confirmed),
                removed: !!suggestion.removed,
                loadAttempted: !!suggestion.loadAttempted || !!autoPhoto,
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

        normalizeHostedMediaUrl(url = '') {
            const value = String(url || '').trim();
            if (!value) return '';
            const origin = String(window.location.origin || '').replace(/\/$/, '');
            if (/drive\.google\.com\/uc/i.test(value)) {
                const previewUrl = typeof this.stableGoogleDrivePreviewUrl === 'function'
                    ? this.stableGoogleDrivePreviewUrl(value)
                    : '';
                if (previewUrl) {
                    return previewUrl;
                }
            }

            return value
                .replace(origin + '/storage/publish/', origin + '/publish/')
                .replace('/storage/publish/', '/publish/');
        },

        sanitizePersistedArticleHtml(html = '') {
            return String(html || '')
                .replace(/<div[^>]*class="[^"]*photo-placeholder[^"]*"[^>]*>[\s\S]*?<\/div>/gi, '')
                .replace(/<span[^>]*class="[^"]*photo-(?:view|confirm|change|remove)[^"]*"[^>]*>[\s\S]*?<\/span>/gi, '')
                .replace(/<(?:(?:p)|(?:div))\b[^>]*>\s*(?:&nbsp;|<br\s*\/?>|\s)*<\/(?:p|div)>/gi, '')
                .replace(/(?:View|Confirm|Change|Remove){2,}/g, '')
                .replace(/\n{3,}/g, '\n\n')
                .trim();
        },

        normalizeHostedMediaHtml(html = '') {
            return this.sanitizePersistedArticleHtml(String(html || ''))
                .replace(/https?:\/\/[^\s"']+\/storage\/publish\//gi, (match) => this.normalizeHostedMediaUrl(match))
                .replace(/(["'(])\/storage\/publish\//gi, '$1/publish/');
        },

        resolvePhotoThumbUrl(photo) {
            return this.normalizeHostedMediaUrl(photo?.preview_url || photo?.url_thumb || photo?.url_large || photo?.url_full || photo?.url || '');
        },

        resolvePhotoLargeUrl(photo) {
            return this.normalizeHostedMediaUrl(photo?.url_large || photo?.url_full || photo?.preview_url || photo?.url_thumb || photo?.url || '');
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
                && !this.featuredSearchAttempted
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
                    this.isPrArticleMode()
                        ? [this.buildPrArticleSourceText(true)].filter(Boolean)
                        : (
                            this.approvedSources.length > 0
                                ? this.approvedSources.map(i => this.checkResults[i]?.text || '').filter(Boolean)
                                : ['[Source articles will be inserted here]']
                        )
                );

            return {
                source_texts: sourceTexts,
                template_id: this.selectedTemplateId || null,
                preset_id: this.selectedPresetId || null,
                prompt_slug: this.currentWorkflowPromptSlug(),
                custom_prompt: this.customPrompt || null,
                web_research: this.spinWebResearch,
                supporting_url_type: this.supportingUrlType || 'matching_content_type',
                pr_subject_context: this.isPrArticleMode() ? this.buildPrSubjectContext() : null,
                article_type: this.template_overrides?.article_type || this.currentArticleType || null,
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
            const saved = localStorage.getItem(this.pipelineStateKey);
            const legacySaved = (!saved && !serverState)
                ? (localStorage.getItem(this.legacyPipelineStateKey) || (!draftState.body ? localStorage.getItem('publishPipelineState') : null))
                : null;
            const savedState = saved || legacySaved;
            let state = null;
            let restoredFromLocalState = false;
            const parseSavedAt = (value) => {
                if (!value) return null;
                const ts = Date.parse(value);
                return Number.isFinite(ts) ? ts : null;
            };

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

            const localSavedAt = state ? parseSavedAt(state._saved_at) : null;
            const serverSavedAt = serverState ? parseSavedAt(serverState._saved_at) : null;
            if (state && serverState) {
                const shouldPreferServerState = (
                    (serverSavedAt !== null && localSavedAt === null)
                    || (serverSavedAt !== null && localSavedAt !== null && serverSavedAt > localSavedAt)
                );
                if (shouldPreferServerState) {
                    state = serverState;
                    restoredFromLocalState = false;
                    this._safePipelineStateWrite(this.pipelineStateKey, JSON.stringify(serverState));
                    localStorage.removeItem('publishPipelineState');
                }
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
                this.openSteps = this.normalizeNestedOpenSteps(this.openSteps, state.currentStep || this.currentStep);
                this.pressRelease = this.restorePressReleaseStateFromLegacy(state);
                this.rebuildPressReleasePhotoAssets?.();
                this.selectedPrProfiles = (Array.isArray(this.selectedPrProfiles) ? this.selectedPrProfiles : [])
                    .map((profile) => this.normalizePrProfileForState(profile))
                    .filter((profile) => !!profile.id);
                this.prSubjectData = this.sanitizePrSubjectDataForPersistence(this.prSubjectData || {});

                if (!this.template_overrides || typeof this.template_overrides !== 'object') {
                    this.template_overrides = {};
                }

                const restoredPressReleaseState = this.normalizePressReleaseState(this.pressRelease || {});
                const restoredPrSubjectCount = Array.isArray(this.selectedPrProfiles) ? this.selectedPrProfiles.length : 0;
                const draftArticleTypeHint = String(draftState?.article_type || '').trim();
                const draftSiteIdHint = draftState?.selectedSiteId ? String(draftState.selectedSiteId) : '';
                const explicitRestoredArticleType = String(
                    this.template_overrides.article_type
                    || state?.template_overrides?.article_type
                    || state?.article_type
                    || state?.currentArticleType
                    || draftState?.article_type
                    || ''
                ).trim();
                const resolvedRestoredArticleType = String(
                    this.resolvePrArticleTypeFromState?.(explicitRestoredArticleType, {
                        selectedPrProfiles: this.selectedPrProfiles,
                        prArticle: this.prArticle,
                        prSubjectData: this.prSubjectData,
                    }) || ''
                ).trim();
                const hasRestoredPressReleaseContent = !!(
                    restoredPressReleaseState.submit_method
                    || restoredPressReleaseState.content_dump
                    || restoredPressReleaseState.public_url
                    || (Array.isArray(restoredPressReleaseState.document_files) && restoredPressReleaseState.document_files.length > 0)
                    || restoredPressReleaseState.notion_book?.id
                    || restoredPressReleaseState.notion_person?.name
                    || restoredPressReleaseState.notion_episode?.id
                    || restoredPressReleaseState.notion_guest?.name
                    || (Array.isArray(restoredPressReleaseState.detected_photos) && restoredPressReleaseState.detected_photos.length > 0)
                );

                if (!resolvedRestoredArticleType && hasRestoredPressReleaseContent && restoredPrSubjectCount === 0) {
                    this.template_overrides.article_type = 'press-release';
                    this.pressRelease.article_type = 'press-release';
                } else if (resolvedRestoredArticleType) {
                    this.template_overrides.article_type = resolvedRestoredArticleType;
                    this.pressRelease.article_type = resolvedRestoredArticleType;
                } else if (restoredPrSubjectCount > 0) {
                    this.template_overrides.article_type = 'pr-full-feature';
                    this.pressRelease.article_type = 'pr-full-feature';
                }

                const finalRestoredArticleType = String(this.template_overrides.article_type || '').trim();
                const shouldPreferDraftContext = !!(
                    restoredFromLocalState
                    && (
                        (draftArticleTypeHint && draftArticleTypeHint !== 'press-release' && finalRestoredArticleType === 'press-release')
                        || (draftSiteIdHint && state?.selectedSiteId && String(state.selectedSiteId) !== draftSiteIdHint && draftState?.selectedSite && !draftState.selectedSite.is_press_release_source)
                    )
                );

                const restoredSiteIdForBootstrap = shouldPreferDraftContext && draftSiteIdHint
                    ? draftSiteIdHint
                    : (state.selectedSiteId ? String(state.selectedSiteId) : '');

                if (shouldPreferDraftContext) {
                    if (draftArticleTypeHint) {
                        this.template_overrides.article_type = draftArticleTypeHint;
                        this.pressRelease.article_type = draftArticleTypeHint;
                    }
                    if (restoredSiteIdForBootstrap) {
                        this.selectedSiteId = restoredSiteIdForBootstrap;
                        this.selectedSite = this.sites.find(s => s.id == restoredSiteIdForBootstrap) || draftState.selectedSite || null;
                    }
                }

                // Site connection (nested object)
                if (restoredSiteIdForBootstrap) {
                    this.selectedSiteId = restoredSiteIdForBootstrap;
                    this.siteConn.status = state.siteConnStatus ?? null;
                    this.siteConn.message = state.siteConnMessage || '';
                    if (state.siteConnLog) this.siteConn.log = state.siteConnLog;
                    if (state.siteConnAuthors) this.siteConn.authors = state.siteConnAuthors;
                    this.$nextTick(() => {
                        this.selectedSiteId = restoredSiteIdForBootstrap;
                        this.selectedSite = this.sites.find(s => s.id == restoredSiteIdForBootstrap) || (shouldPreferDraftContext ? draftState.selectedSite : state.selectedSite) || null;
                        const cacheKey = this.siteConnectionCacheKey?.(restoredSiteIdForBootstrap);
                        if (cacheKey) {
                            const restored = this.restoreSiteConnection(restoredSiteIdForBootstrap, cacheKey);
                            if (restored && this.selectedSite) {
                                this.selectedSite.status = 'connected';
                            }
                        }
                    });
                }

                // Editor content needs TinyMCE sync
                if (state.spunContent || state.editorContent) {
                    const restoredEditorHtml = this.normalizeHostedMediaHtml(state.editorContent || state.spunContent);
                    this.spunContent = restoredEditorHtml;
                    this.editorContent = restoredEditorHtml;
                    this.spunWordCount = state.spunWordCount || this.countWordsFromHtml(restoredEditorHtml);
                    this.rememberDraftBody(restoredEditorHtml);
                    this.setSpinEditor(restoredEditorHtml);
                    this.extractArticleLinks(restoredEditorHtml);
                    const titleLooksPlaceholder = !this.articleTitle || /^untitled(?:\s+pipeline\s+draft)?$/i.test(String(this.articleTitle || '').trim()) || (this.isPrArticleMode && this.isPrArticleMode() && this.isWeakPrArticleTitle && this.isWeakPrArticleTitle(this.articleTitle || ''));
                    if (titleLooksPlaceholder) {
                        const derivedTitle = this.deriveArticleTitleFromHtml(restoredEditorHtml);
                        if (derivedTitle) this.articleTitle = this.ensurePrArticleTitleSubject ? this.ensurePrArticleTitleSubject(derivedTitle) : derivedTitle;
                    }
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
                this.rebuildPressReleasePhotoAssets?.();
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
                    if (this.selectedSite) {
                        this.selectedSite.status = 'connected';
                    }
                }
            }
            if (draftState.publishAuthor) this.publishAuthor = draftState.publishAuthor;
            const articleTitleLooksPlaceholder = !this.articleTitle || /^untitled(?:\s+pipeline\s+draft)?$/i.test((this.articleTitle || '').trim()) || (this.isPrArticleMode && this.isPrArticleMode() && this.isWeakPrArticleTitle && this.isWeakPrArticleTitle(this.articleTitle || ''));
            if (articleTitleLooksPlaceholder && draftState.articleTitle) this.articleTitle = draftState.articleTitle;
            if (!this.articleDescription && draftState.articleDescription) this.articleDescription = draftState.articleDescription;
            if (!this.aiModel && draftState.aiModel) this.aiModel = draftState.aiModel;
            if ((!this.publishAction || this.publishAction === 'draft_local') && draftState.publishAction) {
                this.publishAction = draftState.publishAction;
            }
            if (!this.scheduleDate && draftState.scheduleDate) {
                this.scheduleDate = draftState.scheduleDate;
            }
            const draftArticleType = String(
                draftState.article_type
                || draftState.currentArticleType
                || draftState?.template_overrides?.article_type
                || ''
            ).trim();
            const currentArticleType = String(this.currentArticleType || '').trim();
            const draftSiteIdForWp = draftState.selectedSiteId ? String(draftState.selectedSiteId) : '';
            const currentSiteIdForWp = this.selectedSiteId ? String(this.selectedSiteId) : '';
            const draftWpPostId = draftState.existingWpPostId ? String(draftState.existingWpPostId) : '';
            const currentWpPostId = this.existingWpPostId ? String(this.existingWpPostId) : '';
            const wpBindingCompatible = (!draftArticleType || !currentArticleType || draftArticleType === currentArticleType)
                && (!draftSiteIdForWp || !currentSiteIdForWp || draftSiteIdForWp === currentSiteIdForWp);
            const shouldSyncWpStateFromDraft = wpBindingCompatible && !!draftWpPostId && (
                !currentWpPostId
                || currentWpPostId === draftWpPostId
                || String(draftState.existingWpStatus || '').toLowerCase() === 'publish'
            );

            if (shouldSyncWpStateFromDraft) {
                this.existingWpPostId = draftState.existingWpPostId;
                this.existingWpStatus = draftState.existingWpStatus || this.existingWpStatus || '';
                this.existingWpPostUrl = draftState.existingWpPostUrl || this.existingWpPostUrl || '';
                this.existingWpAdminUrl = draftState.existingWpAdminUrl || this.existingWpAdminUrl || '';

                if (String(this.existingWpStatus || '').toLowerCase() === 'publish' && this.publishAction !== 'draft_local') {
                    this.publishAction = 'publish';
                }

                if (this.publishResult && String(this.publishResult.post_id || '') === draftWpPostId) {
                    this.publishResult.post_status = draftState.existingWpStatus || this.publishResult.post_status || '';
                    this.publishResult.post_url = draftState.existingWpPostUrl || this.publishResult.post_url || '';
                }
            }
            if (!this.canUsePublicationSyndication?.()) {
                this.selectedSyndicationCats = [];
                this.syndicationCategories = [];
                this.syndicationCategoriesCacheMeta = null;
                if (Array.isArray(this.prepareChecklist) && this.prepareChecklist.length > 0) {
                    this.prepareChecklist = this.prepareChecklist.filter((item) => item?.type !== 'publication');
                }
            }
            if ((!Array.isArray(this.suggestedCategories) || this.suggestedCategories.length === 0) && Array.isArray(draftState.categories) && draftState.categories.length > 0) {
                this.suggestedCategories = this.normalizeUniqueTextList(draftState.categories, 10);
                if (!Array.isArray(this.selectedCategories) || this.selectedCategories.length === 0) {
                    this.selectedCategories = this.suggestedCategories.map((_, idx) => idx);
                }
            }
            if ((!Array.isArray(this.suggestedTags) || this.suggestedTags.length === 0) && Array.isArray(draftState.tags) && draftState.tags.length > 0) {
                this.suggestedTags = this.normalizeUniqueTextList(draftState.tags, 10);
                if (!Array.isArray(this.selectedTags) || this.selectedTags.length === 0) {
                    this.selectedTags = this.suggestedTags.map((_, idx) => idx);
                }
            }
            if (!this.spunContent && draftState.body) {
                const restoredDraftHtml = this.normalizeHostedMediaHtml(draftState.body);
                this.spunContent = restoredDraftHtml;
                this.editorContent = restoredDraftHtml;
                this.spunWordCount = draftState.wordCount || this.countWordsFromHtml(restoredDraftHtml);
                this.rememberDraftBody(restoredDraftHtml);
                this.extractArticleLinks(restoredDraftHtml);
                this.$nextTick(() => this.setSpinEditor(restoredDraftHtml));
            }
            if (!this.spunContent && !this.editorContent && this.latestCompletedPrepareHtml) {
                const restoredPreparedHtml = this.normalizeHostedMediaHtml(this.latestCompletedPrepareHtml);
                this.spunContent = restoredPreparedHtml;
                this.editorContent = restoredPreparedHtml;
                this.preparedHtml = restoredPreparedHtml;
                this.spunWordCount = this.countWordsFromHtml(this.latestCompletedPrepareHtml);
                this.rememberDraftBody(this.latestCompletedPrepareHtml);
                this.extractArticleLinks(this.latestCompletedPrepareHtml);
                this.$nextTick(() => this.setSpinEditor(this.latestCompletedPrepareHtml));
            }
            if ((!Array.isArray(this.photoSuggestions) || this.photoSuggestions.length === 0) && Array.isArray(draftState.photoSuggestions)) {
                this.photoSuggestions = draftState.photoSuggestions.map((ps, idx) => this.normalizePhotoSuggestionState(ps, idx));
                this._lastInlinePhotoHydrationSignature = '';
            }
            if ((!this.articleTitle || /^untitled(?:\s+pipeline\s+draft)?$/i.test(String(this.articleTitle || '').trim()) || (this.isPrArticleMode && this.isPrArticleMode() && this.isWeakPrArticleTitle && this.isWeakPrArticleTitle(this.articleTitle || ''))) && (this.editorContent || this.spunContent || draftState.body)) {
                const derivedTitle = this.deriveArticleTitleFromHtml(this.editorContent || this.spunContent || draftState.body || '');
                if (derivedTitle) this.articleTitle = this.ensurePrArticleTitleSubject ? this.ensurePrArticleTitleSubject(derivedTitle) : derivedTitle;
            }
            if (this.maybeApplySmartDraftTitle && this.draftTitleLooksPlaceholder && this.draftTitleLooksPlaceholder(this.articleTitle)) {
                this.maybeApplySmartDraftTitle();
            }
            if (!this.featuredImageSearch && draftState.featuredImageSearch) {
                this.featuredImageSearch = draftState.featuredImageSearch;
            }
            this._lastFeaturedImageSearchValue = String(this.featuredImageSearch || '').trim().toLowerCase();
            if ((this.spunContent || draftState.body) && !state?.currentStep) {
                this.currentStep = 6;
                this.openSteps = this.normalizeNestedOpenSteps(this.normalizedOpenSteps(6), 6);
                this.completedSteps = Array.from(new Set([...(this.completedSteps || []), 1, 2, 3, 4, 5]));
            }

            // Auto-complete steps based on restored data
            if (this.selectedUser && !this.completedSteps.includes(1)) this.completedSteps.push(1);
            if (this.selectedSiteId && !this.completedSteps.includes(2)) this.completedSteps.push(2);
            const hasStep3Data = this.currentArticleType === 'press-release'
                ? this.hasPressReleaseSubmittedContent()
                : (this.isPrArticleMode() ? this.selectedPrProfiles.length > 0 : this.sources.length > 0);
            const hasStep4Data = this.currentArticleType === 'press-release'
                ? this.hasPressReleaseValidationData()
                : (this.isPrArticleMode() ? this.selectedPrProfiles.length > 0 : this.checkResults.length > 0);
            if (hasStep3Data && !this.completedSteps.includes(3)) this.completedSteps.push(3);
            if (hasStep4Data && !this.completedSteps.includes(4)) this.completedSteps.push(4);
            if (this.spunContent && !this.completedSteps.includes(5)) this.completedSteps.push(5);
            if (this.spunContent && !this.completedSteps.includes(6)) this.completedSteps.push(6);

            const finishRestore = () => {
                this.syncPrArticleForCurrentArticleType({ force: false });
                const restoredState = this.buildPipelineStateSnapshot();
                const restoredStateSignature = this._stableSignature(restoredState);
                this._lastLocalPipelineStateSignature = restoredStateSignature;
                this._lastServerPipelineStateSignature = restoredStateSignature;
                const restoredStateForStorage = {
                    ...restoredState,
                    _saved_at: state?._saved_at || serverState?._saved_at || new Date().toISOString(),
                };
                this._safePipelineStateWrite(this.pipelineStateKey, JSON.stringify(restoredStateForStorage));
                localStorage.removeItem('publishPipelineState');
                this._restoring = false;
                // Auto-select PR source if press-release type and only one source
                this.autoSelectPrSource();
                // If there's already spun content, ensure Create Article is accessible
                if (this.spunContent || this.editorContent) {
                    if (!this.completedSteps.includes(5)) this.completedSteps.push(5);
                    if (this.currentStep <= 5) {
                        this.currentStep = 6;
                        this.openSteps = this.normalizeNestedOpenSteps(this.normalizedOpenSteps(6), 6);
                    }
                }
                // Restore step from URL query string (overrides saved state)
                const urlStep = new URLSearchParams(window.location.search).get('step');
                if (urlStep) {
                    const s = parseInt(urlStep);
                    const allowLockedPreviewStep = [5, 6].includes(s);
                    if (s >= 1 && s <= 7 && (this.isStepAccessible(s) || allowLockedPreviewStep)) {
                        this.currentStep = s;
                        this.openSteps = this.normalizeNestedOpenSteps(this.normalizedOpenSteps(s), s);
                    }
                }

                if (this.selectedSiteId) {
                    const cacheKey = this.siteConnectionCacheKey?.(this.selectedSiteId);
                    if (cacheKey) {
                        const restored = this.restoreSiteConnection(this.selectedSiteId, cacheKey);
                        if (restored && this.selectedSite) {
                            this.selectedSite.status = 'connected';
                        }
                    }
                }

                if (this.selectedSiteId && (!Array.isArray(this.siteConn.authors) || this.siteConn.authors.length === 0)) {
                    this.loadSiteAuthors(this.selectedSiteId, {
                        cacheKey: this.siteConnectionCacheKey?.(this.selectedSiteId),
                    });
                }

                this.syncDeferredEnrichmentState('restore_complete', { log: false });
                this.hydrateResolvedPhotoPlaceholders('restore_complete');
                this.scheduleThumbStateReconcile('restore_complete');
                this.queueInlinePhotoAutoHydration('restore_complete');
                this.queueFeaturedImageAutoHydration('restore_complete');
                this.normalizeExistingPrArticleState?.();
                if (this.isPrArticleMode()) {
                    Promise.all((this.selectedPrProfiles || []).map((profile) => this.loadProfileData(profile, { preserveSelections: true, skipSave: true })))
                        .finally(() => {
                            this.$nextTick(() => {
                                this.hydratePrArticleSelectedMedia();
                                this.normalizeExistingPrArticleState?.();
                            });
                        });
                }
                if (this.currentArticleType === 'press-release' && this.isPressReleaseNotionImport?.()) {
                    this.$nextTick(async () => {
                        this.applyNotionPressReleaseMediaDefaults?.({
                            injectInline: this.currentStep === 6 || (Array.isArray(this.openSteps) && this.openSteps.includes(6)),
                            notify: false,
                        });
                        if (this.isPressReleaseNotionPodcastImport?.()) {
                            await this.maybeAutoRefreshLegacyNotionPodcastImport?.();
                        }
                    });
                }
                if (this.currentStep === 6 || (Array.isArray(this.openSteps) && this.openSteps.includes(6))) {
                    this._ensureCreateArticleStepReady?.('restore_complete');
                } else {
                    this.$nextTick(() => this.maybeAutoLoadSyndicationCategories?.());
                }
                if (this.shouldAutoLoadPromptPreview()) {
                    this._queuePromptRefresh('restore_complete');
                }

                this.initializePublicationNotificationState?.();

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

                this.$nextTick(() => {
                    this._restorePipelineOperations();
                    this.restorePublishEmailQueryState?.();
                });
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
                            const restoredArticleType = this.template_overrides?.article_type
                                || state?.template_overrides?.article_type
                                || draftState.article_type
                                || state?.article_type
                                || serverState?.article_type
                                || state?.pressRelease?.article_type
                                || serverState?.pressRelease?.article_type
                                || this.selectedTemplate?.article_type
                                || null;
                            if (restoredArticleType) {
                                this.template_overrides.article_type = restoredArticleType;
                            }
                            this.syncTemplateSelectionForArticleType?.();

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
            this.$watch('selectedSiteId', siteId => {
                if (!this._restoring && siteId && (!Array.isArray(this.siteConn.authors) || this.siteConn.authors.length === 0)) {
                    this.loadSiteAuthors(siteId, {
                        cacheKey: this.siteConnectionCacheKey?.(siteId),
                    });
                }
            });
            // Reset prepare state when photos change after prepare is complete
            // NOTE: don't watch editorContent — it changes on every TinyMCE sync
            const invalidatePrepare = () => { if (this.prepareComplete && !this._restoring && !this.preparing) { this.prepareComplete = false; } };
            this.$watch('featuredPhoto', invalidatePrepare);
            this.$watch('photoSuggestions', invalidatePrepare);
            this.$watch('selectedTemplateId', () => { if (!this._restoring) this.invalidatePromptPreview('template_changed'); });
            this.$watch('selectedPresetId', () => { if (!this._restoring) this.invalidatePromptPreview('preset_changed'); });
            this.$watch('featuredImageSearch', value => {
                const signature = String(value || '').trim().toLowerCase();
                if (this._restoring) {
                    this._lastFeaturedImageSearchValue = signature;
                    return;
                }
                if (signature === this._lastFeaturedImageSearchValue) {
                    return;
                }
                this._lastFeaturedImageSearchValue = signature;
                this.featuredSearchAttempted = false;
                if (!signature) {
                    this.featuredResults = [];
                    this.featuredSearchPending = false;
                }
                this.syncDeferredEnrichmentState('featured_query_changed', { log: false });
            });
            this.$watch('template_overrides.article_type', () => {
                if (this._restoring) return;
                this.syncTemplateSelectionForArticleType?.();
                this.syncPrArticleForCurrentArticleType({ force: false });
                this.invalidatePromptPreview('article_type_changed');
                this.$nextTick(() => this._ensureCreateArticleStepReady?.('article_type_changed'));
            });
            this.$watch('emailDrawerOpen', () => {
                if (this._restoring) return;
                this.syncPublishEmailQueryState?.();
            });
            this.$watch('emailDrawerTab', () => {
                if (this._restoring) return;
                this.syncPublishEmailQueryState?.();
            });
            this.$watch('approvalEmailTargetId', () => {
                if (this._restoring) return;
                this.syncPublishEmailQueryState?.();
            });
            this.$watch('currentStep', step => {
                if (this._restoring) return;
                if (step === 6 || this.openSteps.includes(6)) {
                    this.queueInlinePhotoAutoHydration('step_change');
                    this.queueFeaturedImageAutoHydration('step_change');
                    if (this.currentArticleType === 'press-release' && this.isPressReleaseNotionImport?.()) {
                        this.$nextTick(() => this.applyNotionPressReleaseMediaDefaults?.({ injectInline: true, notify: false }));
                    }
                }
                if ((step === 5 || this.openSteps.includes(5) || this.promptLogOpen) && !this.promptLoading) {
                    this._queuePromptRefresh('step_change');
                }
            });
            this.$watch('openSteps', steps => {
                if (!this._restoring && Array.isArray(steps) && steps.includes(6)) {
                    this.queueInlinePhotoAutoHydration('open_steps');
                    this.queueFeaturedImageAutoHydration('open_steps');
                    if (this.currentArticleType === 'press-release' && this.isPressReleaseNotionImport?.()) {
                        this.$nextTick(() => this.applyNotionPressReleaseMediaDefaults?.({ injectInline: true, notify: false }));
                    }
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
                'photoSuggestions', 'featuredImageSearch', 'featuredPhoto', 'featuredSearchAttempted',
                'featuredAlt', 'featuredCaption', 'featuredFilename',
                // Step 7 — Publish + uploaded media tracking
                'publishAction', 'publishAuthor', 'publishAuthorSource', 'scheduleDate',
                'existingWpPostId', 'existingWpStatus', 'existingWpPostUrl', 'existingWpAdminUrl',
                'uploadedImages', 'preparedFeaturedMediaId', 'preparedFeaturedWpUrl',
                // AI Detection
                'aiDetectionResults', 'aiDetectionRan', 'aiDetectionAllPass',
                // Press Release workflow state
                'pressRelease',
                // PR Full Feature — profile subjects
                'selectedPrProfiles',
                'prArticle',
                'prSubjectData',
                // Press Release syndication selection
                'selectedSyndicationCats',
                // Post-publish client notification
                'publicationNotificationTemplateId',
                'publicationNotificationFromName',
                'publicationNotificationFromEmail',
                'publicationNotificationReplyTo',
                'publicationNotificationCc',
                'publicationNotificationTo',
                'publicationNotificationSubject',
                'publicationNotificationBody',
                'approvalEmailToTouched',
                'approvalEmailCcTouched',
                'approvalEmailTo',
                'approvalEmailCc',
                'approvalEmailTestTo',
                'approvalEmailFromName',
                'approvalEmailFromEmail',
                'approvalEmailReplyTo',
                'approvalEmailSubject',
                'approvalEmailIntroHtml',
                'approvalEmailImageMode',
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

        _safePipelineStateWrite(key, value) {
            try {
                localStorage.setItem(key, value);
                return true;
            } catch (e) {
                const isQuota = e?.name === 'QuotaExceededError'
                    || e?.code === 22
                    || e?.code === 1014
                    || /quota/i.test(String(e?.message || e));
                if (!isQuota) {
                    this._logDebug('state', 'Local pipeline state write failed', {
                        stage: 'state', substage: 'write_error', error: String(e?.message || e),
                    });
                    return false;
                }
                try {
                    const prefix = 'publishPipelineState:';
                    const orphans = [];
                    for (let i = 0; i < localStorage.length; i++) {
                        const k = localStorage.key(i);
                        if (k && k.startsWith(prefix) && k !== key && k !== 'publishPipelineState') {
                            orphans.push(k);
                        }
                    }
                    orphans.forEach(k => localStorage.removeItem(k));
                    localStorage.setItem(key, value);
                    this._logDebug('state', 'Local pipeline state write succeeded after sweeping orphans', {
                        stage: 'state', substage: 'quota_recovered', swept: orphans.length,
                    });
                    return true;
                } catch (e2) {
                    this._logDebug('state', 'Local pipeline state write dropped (quota exceeded)', {
                        stage: 'state', substage: 'quota_dropped', error: String(e2?.message || e2),
                    });
                    return false;
                }
            }
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
            const persistedState = { ...state, _saved_at: new Date().toISOString() };
            this._safePipelineStateWrite(this.pipelineStateKey, JSON.stringify(persistedState));
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

        async flushPipelineStateNow({ silent = false, forceRetry = false } = {}) {
            if (this._restoring) return true;
            if (forceRetry && this._draftSessionConflictActive) {
                this._clearDraftSessionConflict?.();
            }
            if (this._draftSessionConflictActive) return false;

            this.savePipelineState();

            if (this._pipelineStateTimer) {
                clearTimeout(this._pipelineStateTimer);
                this._pipelineStateTimer = null;
            }
            if (this._serverPipelineStateTimer) {
                clearTimeout(this._serverPipelineStateTimer);
                this._serverPipelineStateTimer = null;
            }

            return await this.savePipelineStateToServer({ silent, forceRetry });
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
            this.aiModel = @json(($pipelineDefaults['spin_model'] ?? null));
            this.aiSearchModel = @json(($pipelineDefaults['search_model'] ?? null));
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
            this.existingWpPostId = null;
            this.existingWpStatus = '';
            this.existingWpPostUrl = '';
            this.existingWpAdminUrl = '';
            this.publishing = false;
            this.publishResult = null;
            this.approvalEmailTo = '';
            this.approvalEmailCc = '';
            this.approvalEmailFromName = 'Scale My Publication';
            this.approvalEmailFromEmail = 'no-reply@scalemypublication.com';
            this.approvalEmailReplyTo = 'support@scalemypublication.com';
            this.approvalEmailSubject = '';
            this.approvalEmailIntroHtml = '';
            this.approvalEmailImageMode = 'embed';
            this.approvalEmailPreviewHtml = '';
            this.approvalEmailWarnings = [];
            this.approvalEmailHeaders = {};
            this.approvalEmailSnapshot = {};
            this.approvalEmailLogs = [];
            this.approvalEmailStatus = '';
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
            this.prArticle = this.normalizePrArticleState({});
            this.prSubjectData = {};
            this.prArticleContextImporting = false;
            this.publicationNotificationTemplateId = this.publicationNotificationDefaults?.template_id || '';
            this.publicationNotificationFromName = this.publicationNotificationDefaults?.from_name || '';
            this.publicationNotificationFromEmail = this.publicationNotificationDefaults?.from_email || '';
            this.publicationNotificationReplyTo = this.publicationNotificationDefaults?.reply_to || '';
            this.publicationNotificationCc = this.publicationNotificationDefaults?.cc || '';
            this.publicationNotificationTo = '';
            this.publicationNotificationSubject = this.publicationNotificationDefaults?.subject || '';
            this.publicationNotificationBody = this.publicationNotificationDefaults?.body || '';
            this.publicationNotificationSending = false;
            this.publicationNotificationStatus = '';
            this.publicationNotificationResult = null;
            this._previousSiteId = null;
            this.editingPreset = false;
            this.editingTemplate = false;
            this.presetsLoading = false;
            this.templatesLoading = false;
            window.dispatchEvent(new CustomEvent('hexa-search-clear', { detail: { component_id: 'pipeline-user' } }));
            this.savePipelineState();
        },
