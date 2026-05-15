@include('app-publish::publishing.pipeline.partials.press-release-workflow-script')
@include('app-publish::publishing.articles.partials.draft-approval-email-script')
@php
    $pipelinePayloadSanitized = json_decode(json_encode($pipelinePayload ?? []), true) ?: [];
    if (isset($pipelinePayloadSanitized['selectedUser']) && is_array($pipelinePayloadSanitized['selectedUser'])) {
        unset($pipelinePayloadSanitized['selectedUser']['email']);
        $pipelineSelectedUserId = (int) ($pipelinePayloadSanitized['selectedUser']['id'] ?? 0);
        if ($pipelineSelectedUserId > 0 && !\hexa_core\Models\User::query()->whereKey($pipelineSelectedUserId)->exists()) {
            $pipelinePayloadSanitized['selectedUser'] = null;
        }
    }
    $pipelineDraftState = json_decode(json_encode($draftState ?? []), true) ?: [];
    if (isset($pipelineDraftState['selectedUser']) && is_array($pipelineDraftState['selectedUser'])) {
        unset($pipelineDraftState['selectedUser']['email']);
    }
@endphp
<script>
function publishPipeline() {
    return {
        ...draftApprovalEmailMixin({ articleId: {{ $draftId }} }),
        ...pressReleaseWorkflowMixin({
            workflowDefinitions: @json($workflowDefinitions ?? []),
            pipelinePayload: @json($pipelinePayloadSanitized ?? []),
            pressReleaseDefaultState: @json($pressReleaseDefaultState ?? []),
        }),
        prArticleDefaultState: @json($prArticleDefaultState ?? []),
        // Step tracking
        currentStep: 1,
        openSteps: [1],
        completedSteps: [],
        get persistedArticleType() {
            return this.template_overrides?.article_type
                ?? this.draftState?.article_type
                ?? this.draftState?.publish_template?.article_type
                ?? this.selectedTemplate?.article_type
                ?? 'editorial';
        },
        get isGenerateMode() {
            const genTypes = ['press-release', 'listicle', 'expert-article', 'pr-full-feature'];
            return genTypes.includes(this.currentArticleType);
        },
        get currentArticleType() {
            return this.persistedArticleType;
        },
        get stepLabels() {
            return this.currentStepLabels();
        },

        manualEditorMode: @json($manualEditorMode ?? false),

        // Step 1 — User (preloaded from draft if user_id is set so Step 2 unlocks without a manual re-select)
        selectedUser: @json(isset($draftUser) && $draftUser ? ['id' => $draftUser->id, 'name' => $draftUser->name] : null),

        // Step 2 — Preset + Website
        presets: [],
        initialUserPresets: @json($initialUserPresets ?? []),
        initialUserPresetUserId: @json(isset($draftUser) && $draftUser ? (string) $draftUser->id : ''),
        presetsLoading: false,
        selectedPresetId: '',
        selectedPreset: null,
        editingPreset: false,

        // Step 3 — PR Subject Profiles
        prProfileSearch: '',
        prProfileResults: [],
        prProfileSearching: false,
        prProfileDropdownOpen: false,
        prSubmitCard: 'content',
        prArticle: @json($prArticleDefaultState ?? []),
        prArticleContextImporting: false,
        prContextTab: 'notion',
        prValidationErrors: {},
        selectedPrProfiles: [],
        prSubjectData: {},

        // Step 3 — Website
        sites: @json($sites ?? []),
        prSourceSites: @json($prSourceSites ?? []),
        draftState: @json($pipelineDraftState ?? []),
        latestCompletedPrepareHtml: @json($latestCompletedPrepareHtml ?? ''),
        selectedSiteId: '',
        selectedSite: null,
        initialUserTemplates: @json($initialUserTemplates ?? []),
        initialUserTemplateUserId: @json(isset($draftUser) && $draftUser ? (string) $draftUser->id : ''),

        // Step 4 — Sources
        sources: [],
        sourceTab: 'ai',
        pasteText: '',
        newsSearch: '',
        newsSearching: false,
        newsResults: [],
        newsHasSearched: false,
        newsMode: 'keyword',
        newsCategory: '',
        newsTrendingSelected: false,
        newsCountry: 'us',
        bookmarks: [],
        bookmarksLoading: false,
        uploadingSourceDoc: false,
        uploadedSourceDoc: null,
        uploadedSourceText: '',
        aiSearchTopic: '',
        aiSearchHistory: JSON.parse(localStorage.getItem('hws_search_history') || '[]'),
        aiSearchOptionLabels: @json(($aiSearchOptionLabels ?? [])),
        aiSearchModel: @json(($pipelineDefaults['search_model'] ?? null)),
        aiSearchCount: 4,
        aiSearching: false,
        aiSearchResults: [],
        aiSearchError: '',
        aiHasSearched: false,
        aiLog: [],
        _markingBrokenIdx: null,
        aiSearchCost: null,

        // Step 5 — Get Articles
        checking: false,
        checkResults: [],
        checkPassCount: 0,
        checkLog: [],
        checkUserAgent: 'chrome',
        extractMethod: 'auto',
        extractRetries: 1,
        extractTimeout: 20,
        extractMinWords: 50,
        extractAutoFallback: true,
        expandedSources: [],
        approvedSources: [],
        discardedSources: [],

        // Step 5 — AI Template + Config
        templates: [],
        templatesLoading: false,
        selectedTemplateId: '',
        selectedTemplate: null,
        editingTemplate: false,

        // Step 7 — Model
        aiModel: @json(($pipelineDefaults['spin_model'] ?? null)),
        customPrompt: '',
        supportingUrlType: 'matching_content_type',

        // Step 7 — Spin
        spinning: false,
        _spinAbortController: null,
        _hasSpunThisSession: false,
        spinWebResearch: true,
        spunContent: '',
        spunWordCount: 0,
        spinChangeRequest: '',
        showChangeInput: false,
        smartEditTemplates: [],
        appliedSmartEdits: [],
        suggestedTitles: [],
        suggestedCategories: [],
        suggestedTags: [],
        selectedTitleIdx: 0,
        selectedCategories: [],
        selectedTags: [],
        customCategoryInput: '',
        customTagInput: '',
        metadataLoading: false,
        suggestedUrls: [],
        checkingAllArticleLinks: false,
        photoSuggestions: [],
        photoSearch: '',
        photoSearching: false,
        photoResults: [],
        showPhotoPanel: false,
        showPhotoOverlay: false,
        showUploadPortal: false,
        insertingPhoto: null,
        photoCaption: '',
        overlayPhotoAlt: '',
        overlayPhotoCaption: '',
        overlayPhotoFilename: '',
        overlayMetaLoading: false,
        overlayMetaGenerated: false,
        _photoSuggestionIdx: null,
        expandedSuggestions: [],
        autoFetchingPhotos: false,
        _inlinePhotoAutoHydrationTimer: null,
        _lastInlinePhotoHydrationSignature: '',
        viewingPhotoIdx: null,
        lastAiCall: null,
        tokenUsage: null,
        spinError: '',
        spinDiagnostics: null,

        // Step 8 — Publish (combined)
        syndicationCategories: [],
        syndicationCategoriesCacheMeta: null,
        selectedSyndicationCats: [],
        loadingSyndicationCats: false,
        _syndicationAutoRequested: false,
        _syndicationAutoSiteId: '',
        _previousSiteId: null,
        articleTitle: '',
        editorContent: '',
        lastNonEmptyDraftBody: '',
        preparing: false,
        prepareOperationId: null,
        prepareOperationStatus: '',
        prepareOperationTransport: '',
        prepareOperationClientTrace: '',
        prepareOperationLastSequence: 0,
        prepareOperationPollTimer: null,
        prepareOperationStreamController: null,
        prepareOperationStreamReconnectTimer: null,
        _streamingPrepareOperation: false,
        _pollingPrepareOperation: false,
        prepareChecklist: [],
        prepareLog: [],
        prepareTraceId: '',
        prepareLastEventAt: 0,
        prepareLastStage: '',
        prepareLastMessage: '',
        prepareLastErrorMessage: '',
        prepareWatchdogTimer: null,
        _lastPrepareWatchdogNoticeAt: 0,
        prepareComplete: false,
        prepareIntegrityIssues: [],
        preparedHtml: '',
        preparedCategoryIds: [],
        preparedTagIds: [],
        preparedFeaturedMediaId: null,
        preparedFeaturedWpUrl: null,

        // Step 10 — Publish
        publishAction: 'draft_local',
        publishAuthor: '',
        publishAuthorSource: '',
        authorsLoading: false,
        existingWpPostId: null,
        existingWpStatus: '',
        existingWpPostUrl: '',
        existingWpAdminUrl: '',
        scheduleDate: '',
        publishing: false,
        publishOperationId: null,
        publishOperationStatus: '',
        publishOperationTransport: '',
        publishOperationClientTrace: '',
        publishOperationLastSequence: 0,
        publishOperationPollTimer: null,
        publishOperationStreamController: null,
        publishOperationStreamReconnectTimer: null,
        _streamingPublishOperation: false,
        _pollingPublishOperation: false,
        publishResult: null,
        publishError: '',

        // Draft
        draftId: {{ $draftId }},
        uploadedImages: {},
        orphanedMedia: [],
        _previousUploadedImages: null,
        featuredImageSearch: '',
        featuredPhoto: null,
        featuredResults: [],
        featuredSearchPending: false,
        featuredSearching: false,
        featuredLoadError: '',
        featuredThumbLoading: false,
        featuredThumbError: '',
        _featuredAutoHydrationTimer: null,
        _lastFeaturedAutoHydrationSignature: '',
        featuredUrlImport: '',
        pipelineOperationLiveStreamEnabled: @json(!app()->runningUnitTests() && !(app()->environment('local') && php_sapi_name() === 'cli-server')),
        innerPhotoUrlImport: '',
        featuredAlt: '',
        featuredCaption: '',
        featuredFilename: '',
        featuredRefreshingMeta: false,
        featuredMetaGenerator: '',
        resolvedPrompt: '',
        promptLog: [],
        promptLogOpen: false,
        promptLoading: false,
        promptPreviewDirty: true,
        promptCopied: false,
        _promptCopiedTimer: null,
        articleDescription: '',
        titleEditing: false,
        titleEditValue: '',
        spinLog: [],
        photoSuggestionsPending: false,

        // AI Detection
        aiDetecting: false,
        aiDetectionResults: {},
        aiDetectionThreshold: 10,
        aiDetectionAllPass: false,
        aiDetectionRan: false,
        aiDetectionEnabled: localStorage.getItem('aiDetectionEnabled') !== 'false',
        selectedFlaggedSentences: [],
        selectedFlaggedTexts: {},

        ...siteConnectionMixin(),
        ...presetFieldsMixin('template'),
        ...presetFieldsMixin('preset'),
        template_schema: @json($templateSchema ?? []),
        preset_schema: @json($presetSchema ?? []),
        ...presetFieldsMethods,
        savingDraft: false,
        saveError: '',
        _draftSaveTimer: null,
        filenamePattern: @json($filenamePattern ?? 'hexa_{draft_id}_{seo_name}'),

        publicationNotificationTemplates: @json($publicationNotificationTemplates ?? []),
        publicationNotificationDefaults: @json($publicationNotificationDefaults ?? []),
        publicationNotificationShortcodes: @json(config('hws-publish.shortcodes', [])),
        publicationNotificationArticleLinks: @json($draft->links_injected ?? null),
        publicationNotificationTemplateId: @json(($publicationNotificationDefaults['template_id'] ?? '')),
        publicationNotificationFromName: @json(($publicationNotificationDefaults['from_name'] ?? '')),
        publicationNotificationFromEmail: @json(($publicationNotificationDefaults['from_email'] ?? '')),
        publicationNotificationReplyTo: @json(($publicationNotificationDefaults['reply_to'] ?? '')),
        publicationNotificationCc: @json(($publicationNotificationDefaults['cc'] ?? '')),
        publicationNotificationTo: '',
        publicationNotificationSubject: @json(($publicationNotificationDefaults['subject'] ?? '')),
        publicationNotificationBody: @json(($publicationNotificationDefaults['body'] ?? '')),
        publicationNotificationSending: false,
        publicationNotificationStatus: '',
        publicationNotificationResult: null,
        googleDocExport: @json($draftState['googleDocExport'] ?? []),
        googleDocExporting: false,
        googleDocExportError: '',
        googleDocOwnerEmail: @json(config('google-docs.owner_email')),
        googleDocExportRoute: @json(route('publish.drafts.google-doc.export', ['id' => $draftId])),

        // Notification
        notification: { show: false, type: 'success', message: '' },
        pipelineDebugEnabled: new URLSearchParams(window.location.search).get('debug') === '1'
            || localStorage.getItem('publishPipelineDebug') === 'true',
        masterActivityLog: [],
        masterActivityLogOpen: false,
        masterActivityAutoScroll: true,
        emailDrawerOpen: false,
        emailDrawerTab: 'approval',
        emailDrawerWidth: (typeof localStorage !== 'undefined' && localStorage.getItem('emailDrawerWidth')) || 'L',
        cycleEmailDrawerWidth() {
            const order = ['M', 'L', 'XL'];
            const next = order[(order.indexOf(this.emailDrawerWidth) + 1) % order.length];
            this.emailDrawerWidth = next;
            try { localStorage.setItem('emailDrawerWidth', next); } catch (e) {}
        },
        activityRunHistory: [],
        activityRunHistoryLoading: false,
        selectedActivityRunTrace: '',
        activityRunPreviewEntries: [],
        crossDraftActivityRuns: [],
        draftApiActivityEntries: [],
        draftApiActivityLoading: false,
        expandedApiCalls: {},
        publishTraceId: '',
        _masterActivitySeq: 0,
        _clientSessionTraceId: '',
        _tabInstanceId: '',
        _lastLocalPipelineStateSignature: '',
        _lastServerPipelineStateSignature: '',
        _pendingServerPipelineStateSave: false,
        _lastDraftPayloadSignature: '',
        _lastPromptPreviewSignature: '',
        _pendingDraftSave: false,
        _pendingDraftSilent: true,
        _pipelineStateTimer: null,
        _masterActivityPersistTimer: null,
        _serverActivitySyncTimer: null,
        _syncingMasterActivityServer: false,
        _pendingMasterActivitySync: false,
        _masterActivitySyncBatchSize: 200,
        _serverActivityLogLoading: false,
        _serverActivityLogRestored: false,
        _activityRunHistoryRestored: false,
        _crossDraftActivityRunsRestored: false,
        _draftApiActivityRestored: false,
        _thumbReconcileTimers: [],
        _bootstrappedPresetUserId: '',
        _bootstrappedTemplateUserId: '',
        _suspendPipelineStateSave: false,
        _suspendDraftAutoSave: false,
        _pendingPostSuspendPipelineStateSave: false,
        _pendingPostSuspendDraftSave: false,
        draftSessionConflict: null,
        _draftSessionConflictActive: false,
        _spinEditorConfigured: false,
        _spinEditorConfiguring: false,
        _pendingSpinEditorContent: '',
        _pipelineOperationsRestored: false,
        _pageUnloading: false,

        // Flag to suppress step auto-navigation during state restore
        _restoring: false,

        // CSRF token
        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        googleDocCurrentTitle() {
            return String(this.articleTitle || this.draftState?.articleTitle || this.draftState?.title || 'Untitled').trim() || 'Untitled';
        },

        googleDocCurrentExcerpt() {
            return String(this.articleDescription || this.draftState?.articleDescription || this.draftState?.excerpt || '').trim();
        },

        googleDocCurrentBody() {
            const body = this._resolveCanonicalArticleHtml({ preferPrepared: false, hydrateState: true }) || this.editorContent || this.spunContent || this.draftState?.editorContent || this.draftState?.spunContent || '';
            return String(body || '');
        },

        googleDocHasDocument() {
            return !!String(this.googleDocExport?.document_id || '').trim();
        },

        googleDocIsStale() {
            return false;
        },

        googleDocStatusLabel() {
            if (!this.googleDocHasDocument()) return 'No Google Doc yet';
            return 'Google Doc ready';
        },

        googleDocActionLabel() {
            if (!this.googleDocHasDocument()) return 'Create Google Doc';
            return 'Update Google Doc';
        },

        openGoogleDoc() {
            if (this.googleDocExport?.url) {
                window.open(this.googleDocExport.url, '_blank', 'noopener');
            }
        },

        async exportGoogleDoc(openAfter = true) {
            if (this.googleDocExporting || !this.draftId || !this.googleDocExportRoute) return;
            this.googleDocExportError = '';
            this.googleDocExporting = true;
            try {
                const response = await fetch(this.googleDocExportRoute, {
                    method: 'POST',
                    headers: {
                        ...this.requestHeaders({ 'Content-Type': 'application/json' }),
                    },
                    body: JSON.stringify({
                        title: this.googleDocCurrentTitle(),
                        excerpt: this.googleDocCurrentExcerpt(),
                        body: this.googleDocCurrentBody(),
                    }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    this.googleDocExportError = data.message || 'Google Doc export failed.';
                    this.showNotification('error', this.googleDocExportError);
                    return;
                }
                this.googleDocExport = data.google_doc || {};
                if (this.draftState && typeof this.draftState === 'object') {
                    this.draftState.googleDocExport = this.googleDocExport;
                }
                this.showNotification('success', data.message || 'Google Doc exported successfully.');
                if (openAfter && this.googleDocExport?.url) {
                    window.open(this.googleDocExport.url, '_blank', 'noopener');
                }
            } catch (error) {
                this.googleDocExportError = error.message || 'Google Doc export failed.';
                this.showNotification('error', this.googleDocExportError);
            } finally {
                this.googleDocExporting = false;
            }
        },

        requestHeaders(extra = {}) {
            if (typeof window.hexaRequestHeaders === 'function') {
                return window.hexaRequestHeaders(extra);
            }

            return {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {}),
                ...extra,
            };
        },

        get pipelineStateKey() {
            return 'publishPipelineState:v2:' + String(this.draftId || 'new') + ':' + String(this._tabInstanceId || 'tab');
        },

        get legacyPipelineStateKey() {
            return 'publishPipelineState:' + String(this.draftId || 'new');
        },

        formatLabel(value) {
            return String(value || '').replace(/[-_]/g, ' ').replace(/\b\w/g, (ch) => ch.toUpperCase());
        },

        linkedValueHtml(value) {
            const raw = String(value ?? '');
            if (!raw.trim()) return '';
            const escapeHtml = (input) => String(input || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const pattern = /https?:\/\/[^\s<>'"]+/gi;
            let html = '';
            let lastIndex = 0;
            raw.replace(pattern, (match, offset) => {
                html += escapeHtml(raw.slice(lastIndex, offset)).replace(/\n/g, '<br>');
                const safeUrl = escapeHtml(match);
                html += '<a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline decoration-blue-200 underline-offset-2 break-all">' + safeUrl + '</a>';
                lastIndex = offset + match.length;
                return match;
            });
            html += escapeHtml(raw.slice(lastIndex)).replace(/\n/g, '<br>');
            return html;
        },

        prFieldHasError(field) {
            return !!this.prValidationErrors?.[field];
        },

        prInputBorderClass(field) {
            return this.prFieldHasError(field)
                ? 'border border-red-400 ring-2 ring-red-100 focus:ring-red-200 focus:border-red-400'
                : 'border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400';
        },

        prCardBorderClass(field) {
            return this.prFieldHasError(field)
                ? 'border-red-300 bg-red-50/40 ring-1 ring-red-100'
                : 'border-blue-200 bg-blue-50/60';
        },

        prSelectedContextEntries() {
            const items = [];
            for (const profile of (this.selectedPrProfiles || [])) {
                const pd = this.prSubjectData?.[profile.id] || null;
                if (!pd?.selectedEntries) continue;
                for (const relation of (pd.relations || [])) {
                    for (const entry of (relation.entries || [])) {
                        if (!pd.selectedEntries?.[entry.id]) continue;
                        items.push({ profile, relation, entry });
                    }
                }
            }
            return items;
        },

        hasSelectedPrContextEntries() {
            return this.prSelectedContextEntries().length > 0;
        },

        currentPrContextStatusLabel() {
            if (this.hasSelectedPrContextEntries()) {
                return 'Notion context selected';
            }
            if (String(this.prArticle?.expert_context_extracted?.title || this.prArticle?.expert_context_extracted?.text || '').trim() || String(this.prArticle?.expert_context_url || '').trim()) {
                return 'External article imported';
            }
            return 'Context article required';
        },

        startTitleEditing() {
            this.titleEditValue = String(this.articleTitle || this.draftState?.articleTitle || 'Untitled').trim() || 'Untitled';
            this.titleEditing = true;
            this.$nextTick(() => this.$refs.articleTitleInput?.focus());
        },

        cancelTitleEditing() {
            this.titleEditing = false;
            this.titleEditValue = String(this.articleTitle || this.draftState?.articleTitle || 'Untitled').trim() || 'Untitled';
        },

        commitTitleEditing() {
            const nextTitle = String(this.titleEditValue || '').trim() || 'Untitled';
            this.titleEditing = false;
            if (nextTitle === String(this.articleTitle || '').trim()) {
                return;
            }

            this.articleTitle = nextTitle;
            this.queuePipelineStateSave?.(50);
            this.queueAutoSaveDraft?.(150);
            if (typeof this._logActivity === 'function') {
                this._logActivity('draft', 'info', 'Draft title updated', {
                    stage: 'draft',
                    substage: 'title_update',
                    title: nextTitle,
                });
            }
        },

        @include('app-publish::publishing.pipeline.partials.activity-log-script')
        @include('app-publish::publishing.pipeline.partials.state-persistence-script')
        @include('app-publish::publishing.pipeline.partials.workflow-setup-script')
        @include('app-publish::publishing.pipeline.partials.spin-workflow-script')
        @include('app-publish::publishing.pipeline.partials.draft-notification-script')
    };
}
</script>
