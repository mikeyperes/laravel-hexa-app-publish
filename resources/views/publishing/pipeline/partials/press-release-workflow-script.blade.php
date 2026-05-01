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
        _pressReleaseLegacyRefreshEpisodeId: null,
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
            normalized.notion_source_fields = normalized.notion_source_fields && typeof normalized.notion_source_fields === 'object'
                ? {
                    episode: Array.isArray(normalized.notion_source_fields.episode) ? normalized.notion_source_fields.episode : [],
                    guest: Array.isArray(normalized.notion_source_fields.guest) ? normalized.notion_source_fields.guest : [],
                    enforcement: Array.isArray(normalized.notion_source_fields.enforcement) ? normalized.notion_source_fields.enforcement : [],
                }
                : { episode: [], guest: [], enforcement: [] };
            normalized.detected_content = normalized.detected_content || '';
            normalized.detected_content_html = normalized.detected_content_html || '';
            normalized.detected_word_count = normalized.detected_word_count || 0;
            const legacySelectedPhotoKeys = this.normalizePressReleaseSelectionMap(normalized.selected_photo_keys || {});
            normalized.featured_photo_key = String(normalized.featured_photo_key || '').trim();
            normalized.inline_photo_keys = this.normalizePressReleaseSelectionMap(normalized.inline_photo_keys || {});
            if (!Object.keys(normalized.inline_photo_keys).length && Object.keys(legacySelectedPhotoKeys).length) {
                normalized.inline_photo_keys = { ...legacySelectedPhotoKeys };
            }
            if (normalized.featured_photo_key && normalized.inline_photo_keys[normalized.featured_photo_key]) {
                delete normalized.inline_photo_keys[normalized.featured_photo_key];
            }
            normalized.selected_photo_keys = {
                ...legacySelectedPhotoKeys,
                ...(normalized.featured_photo_key ? { [normalized.featured_photo_key]: true } : {}),
                ...normalized.inline_photo_keys,
            };
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
            const detected = (this.pressRelease.detected_photos || []).map((photo, index) => {
                const stablePreview = this.pressReleaseStableDrivePreviewUrl(photo);
                return {
                    key: String(photo.key || '').trim() || ('detected-' + index),
                    label: photo.label || photo.alt_text || photo.caption || photo.url || 'Detected photo',
                    filename: photo.filename || ('detected-photo-' + (index + 1)),
                    url: this.toAbsoluteMediaUrl(photo.url),
                    thumbnail_url: stablePreview || this.toAbsoluteMediaUrl(photo.thumbnail_url || photo.preview_url || photo.url),
                    preview_url: stablePreview || this.toAbsoluteMediaUrl(photo.preview_url || photo.thumbnail_url || photo.url),
                    source_url: this.toAbsoluteMediaUrl(photo.source_url || photo.download_url || photo.view_url || photo.url),
                    alt_text: photo.alt_text || '',
                    caption: photo.caption || '',
                    source_label: photo.source_label || photo.source || 'Detected from public URL',
                    source: photo.source || 'detected',
                    origin: photo.origin || photo.source || '',
                    role: photo.role || '',
                    download_url: this.toAbsoluteMediaUrl(photo.download_url || photo.source_url || photo.url),
                    view_url: this.toAbsoluteMediaUrl(photo.view_url || photo.url),
                };
            });

            let normalizedAssets = [...uploaded, ...detected].filter((asset) => !!asset.url);
            if (this.pressReleaseShouldFilterLegacyGuestMedia(normalizedAssets)) {
                normalizedAssets = normalizedAssets.filter((asset) => !this.pressReleaseAssetIsLegacyGuestMedia(asset));
            }
            this.pressReleasePhotoAssets = normalizedAssets;
            const featuredUrl = this.toAbsoluteMediaUrl(this.featuredPhoto?.url_large || this.featuredPhoto?.url || '');
            if (featuredUrl) {
                const matchedFeaturedAsset = this.pressReleasePhotoAssets.find((asset) => asset.url === featuredUrl);
                if (matchedFeaturedAsset?.key && !String(this.pressRelease?.featured_photo_key || '').trim()) {
                    this.pressRelease.featured_photo_key = matchedFeaturedAsset.key;
                }
            }
            if (this.pressRelease?.featured_photo_key && this.pressRelease?.inline_photo_keys?.[this.pressRelease.featured_photo_key]) {
                delete this.pressRelease.inline_photo_keys[this.pressRelease.featured_photo_key];
            }
            this.syncPressReleaseSelectedPhotoKeys();
            return this.pressReleasePhotoAssets;
        },

        pressReleaseAssetIsLegacyGuestMedia(asset = {}) {
            const source = String(asset?.source || '').toLowerCase();
            const label = String(asset?.source_label || '').toLowerCase();
            const urls = [asset?.url, asset?.source_url, asset?.download_url, asset?.view_url, asset?.thumbnail_url, asset?.preview_url]
                .map((value) => this.toAbsoluteMediaUrl(value || '').toLowerCase())
                .filter(Boolean);
            return source.includes('notion-guest-media')
                || label.includes('notion guest media')
                || urls.some((value) => value.includes('media.licdn.com') || value.includes('.licdn.com') || value.includes('drive-storage'));
        },

        pressReleaseShouldFilterLegacyGuestMedia(assets = []) {
            if (this.currentArticleType !== 'press-release' || this.pressRelease?.submit_method !== 'notion-podcast') {
                return false;
            }
            return (assets || []).some((asset) => String(asset?.source || '').toLowerCase() === 'notion-guest-drive');
        },

        pressReleaseAssetKey(asset = {}, fallbackKey = '') {
            return String(asset?.key || asset?.id || fallbackKey || asset?.url || '').trim();
        },

        normalizePressReleaseSelectionMap(map = {}) {
            if (!map || typeof map !== 'object') {
                return {};
            }
            return Object.entries(map).reduce((carry, [key, value]) => {
                const normalizedKey = String(key || '').trim();
                if (normalizedKey && value) {
                    carry[normalizedKey] = true;
                }
                return carry;
            }, {});
        },

        syncPressReleaseSelectedPhotoKeys() {
            const union = {};
            const featuredKey = String(this.pressRelease?.featured_photo_key || '').trim();
            const inlineKeys = this.normalizePressReleaseSelectionMap(this.pressRelease?.inline_photo_keys || {});
            if (featuredKey) {
                union[featuredKey] = true;
            }
            Object.keys(inlineKeys).forEach((key) => {
                union[key] = true;
            });
            if (this.pressRelease && typeof this.pressRelease === 'object') {
                this.pressRelease.inline_photo_keys = inlineKeys;
                this.pressRelease.selected_photo_keys = union;
            }
            return union;
        },

        pressReleaseAssetIsSelected(asset = {}, fallbackKey = '') {
            return this.pressReleaseAssetIsFeatured(asset, fallbackKey) || this.pressReleaseAssetIsInline(asset, fallbackKey);
        },

        togglePressReleaseAssetSelection(asset = {}, fallbackKey = '') {
            return this.togglePressReleaseInlinePhotoSelection(asset, fallbackKey);
        },

        selectAllPressReleaseInlinePhotos() {
            if (!this.pressRelease || typeof this.pressRelease !== 'object') return;
            const next = {};
            const featuredKey = String(this.pressRelease?.featured_photo_key || '').trim();
            (this.pressReleasePhotoAssets || []).forEach((asset) => {
                const key = this.pressReleaseAssetKey(asset);
                if (!key || key === featuredKey) return;
                next[key] = true;
            });
            this.pressRelease.inline_photo_keys = next;
            this.syncPressReleaseSelectedPhotoKeys();
            this.savePipelineState();
        },

        clearPressReleaseInlinePhotos() {
            if (!this.pressRelease || typeof this.pressRelease !== 'object') return;
            this.pressRelease.inline_photo_keys = {};
            this.syncPressReleaseSelectedPhotoKeys();
            this.savePipelineState();
        },

        togglePressReleaseInlinePhotoSelection(asset = {}, fallbackKey = '', options = {}) {
            const key = this.pressReleaseAssetKey(asset, fallbackKey);
            if (!key) return;
            if (!this.pressRelease.inline_photo_keys || typeof this.pressRelease.inline_photo_keys !== 'object') {
                this.pressRelease.inline_photo_keys = {};
            }
            const nextValue = options.force === null || typeof options.force === 'undefined'
                ? !this.pressRelease.inline_photo_keys[key]
                : !!options.force;
            if (nextValue) {
                this.pressRelease.inline_photo_keys[key] = true;
            } else {
                delete this.pressRelease.inline_photo_keys[key];
            }
            this.syncPressReleaseSelectedPhotoKeys();
            if (options.save !== false) {
                this.savePipelineState();
            }
        },

        pressReleaseAssetIsFeatured(asset = {}, fallbackKey = '') {
            const key = this.pressReleaseAssetKey(asset, fallbackKey);
            if (key && String(this.pressRelease?.featured_photo_key || '').trim() === key) {
                return true;
            }
            const assetUrl = this.toAbsoluteMediaUrl(asset.url || '');
            const featuredUrl = this.toAbsoluteMediaUrl(this.featuredPhoto?.url_large || this.featuredPhoto?.url || '');
            return !!(assetUrl && featuredUrl && assetUrl === featuredUrl);
        },

        pressReleaseAssetIsInline(asset = {}, fallbackKey = '') {
            const key = this.pressReleaseAssetKey(asset, fallbackKey);
            if (key && this.pressRelease?.inline_photo_keys?.[key]) {
                return true;
            }
            const hasExplicitInlineSelections = Object.keys(this.pressRelease?.inline_photo_keys || {}).some((candidateKey) => this.pressRelease?.inline_photo_keys?.[candidateKey]);
            const assetUrl = this.toAbsoluteMediaUrl(asset.url || '');
            return !hasExplicitInlineSelections && !!(assetUrl && this.pressReleaseHasInlineAsset(assetUrl));
        },

        buildPressReleaseSourceText(usePlaceholder = false) {
            const blocks = [];
            let sourceText = (this.pressRelease.resolved_source_text || this.pressRelease.content_dump || '').trim();
            if (this.currentArticleType === 'press-release' && this.pressRelease.submit_method === 'notion-podcast' && sourceText) {
                sourceText = sourceText.replace(
                    /- Include exactly one inline guest\/client image in the body when a preferred inline guest image URL is provided\./i,
                    '- Use the selected inline subject photos listed below in the body. Do not use generic stock images in place of the selected subject photos.'
                );
            }
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
                if (details.date && details.location) {
                    blocks.push([
                        '=== Required Dateline ===',
                        'Use this exact dateline verbatim at the start of the first paragraph: ' + details.location + ' (Hexa PR Wire - ' + details.date + ') -',
                        'Never substitute another city, state, or date.',
                        'Ignore any other dateline, city, state, or publication date found in transcript, source material, or prior examples.'
                    ].join("\n"));
                }
            }

            if (this.currentArticleType === 'press-release' && this.pressRelease.submit_method === 'notion-podcast') {
                const featuredAsset = this.preferredPressReleaseFeaturedAsset();
                const inlineAssets = this.selectedPressReleaseInlineAssets();
                const mediaLines = ['=== Selected Press Release Media ==='];
                if (featuredAsset) {
                    mediaLines.push('Featured image URL: ' + this.pressReleaseSourceUploadUrl(featuredAsset));
                    mediaLines.push('Featured image label: ' + (featuredAsset.label || featuredAsset.alt_text || 'Featured press release image'));
                } else if (usePlaceholder) {
                    mediaLines.push('Featured image URL: [Episode featured image URL will be inserted here]');
                }

                mediaLines.push('Inline photo count: ' + inlineAssets.length);
                if (inlineAssets.length) {
                    inlineAssets.forEach((asset, idx) => {
                        mediaLines.push('Inline Photo ' + (idx + 1) + ' URL: ' + this.pressReleaseSourceUploadUrl(asset));
                        mediaLines.push('Inline Photo ' + (idx + 1) + ' Caption: ' + (asset.caption || asset.alt_text || asset.label || ('Selected subject photo ' + (idx + 1))));
                    });
                    mediaLines.push('Instruction: Use the featured image as the WordPress featured image and weave the selected inline subject photos naturally into the body. Do not reuse the same inline photo more than once and do not substitute stock photos for these selected subject photos.');
                } else if (usePlaceholder) {
                    mediaLines.push('Inline Photo 1 URL: [Selected inline subject photo URLs will be inserted here]');
                }
                blocks.push(mediaLines.join("\n"));
            }

            if (this.pressRelease.google_drive_url && this.pressRelease.submit_method !== 'notion-podcast') {
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

            const token = ++this._pressReleaseEpisodeSearchToken;
            this.pressReleaseEpisodeSearching = true;
            this.pressReleaseEpisodeDropdownOpen = !!loadRecent;
            this.pressReleaseEpisodeNoResults = false;

            try {
                const searchUrl = new URL('{{ route("publish.pipeline.press-release.search-notion-episodes.live") }}', window.location.origin);
                searchUrl.searchParams.set('draft_id', this.draftId);
                searchUrl.searchParams.set('limit', '10');
                if (query) {
                    searchUrl.searchParams.set('q', query);
                }
                const response = await fetch(searchUrl.toString(), {
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json();
                if (token !== this._pressReleaseEpisodeSearchToken) {
                    return;
                }
                this.pressReleaseEpisodeResults = Array.isArray(data) ? data : [];
                this.pressReleaseEpisodeNoResults = this.pressReleaseEpisodeResults.length === 0;
                this.pressReleaseEpisodeDropdownOpen = loadRecent && (this.pressReleaseEpisodeResults.length > 0 || this.pressReleaseEpisodeNoResults);
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
        pressReleaseNeedsLegacyNotionRefresh() {
            if (this.currentArticleType !== 'press-release' || this.pressRelease?.submit_method !== 'notion-podcast') {
                return false;
            }
            const episodeId = String(this.pressRelease?.notion_episode?.id || '').trim();
            if (!episodeId || this.pressReleaseImportingEpisodeId === episodeId || this._pressReleaseLegacyRefreshEpisodeId === episodeId) {
                return false;
            }
            const photos = Array.isArray(this.pressRelease?.detected_photos) ? this.pressRelease.detected_photos : [];
            if (!photos.length) {
                return false;
            }
            const inlineUrl = String(this.pressRelease?.notion_guest?.inline_photo_url || '').trim();
            const hasLegacyLinkedIn = /media\.licdn\.com/i.test(inlineUrl)
                || photos.some((photo) => /media\.licdn\.com/i.test(String(photo?.url || '')));
            const hasLegacyGuestMedia = photos.some((photo) => {
                const source = String(photo?.source || '').toLowerCase();
                const label = String(photo?.source_label || '').toLowerCase();
                return source.includes('notion-guest-media') || label.includes('notion guest media');
            });
            const hasDriveGuestMedia = photos.some((photo) => {
                const source = String(photo?.source || '').toLowerCase();
                const label = String(photo?.source_label || '').toLowerCase();
                return source.includes('notion-guest-drive') || label.includes('drive photo');
            });
            return (hasLegacyLinkedIn || hasLegacyGuestMedia) && !hasDriveGuestMedia;
        },

        async maybeAutoRefreshLegacyNotionPodcastImport() {
            if (!this.pressReleaseNeedsLegacyNotionRefresh()) {
                return false;
            }
            const episodeId = String(this.pressRelease?.notion_episode?.id || '').trim();
            if (!episodeId) {
                return false;
            }
            this._pressReleaseLegacyRefreshEpisodeId = episodeId;
            const requestedInlineCount = Math.max(1, Object.keys(this.normalizePressReleaseSelectionMap(this.pressRelease?.inline_photo_keys || {})).length || 1);
            try {
                await this.importPressReleaseNotionEpisode({ id: episodeId }, {
                    notify: false,
                    inlineCount: requestedInlineCount,
                });
                this.showNotification('info', 'Updated this draft to the latest Notion podcast media import.');
                return true;
            } catch (error) {
                this._pressReleaseLegacyRefreshEpisodeId = null;
                return false;
            }
        },

        async importPressReleaseNotionEpisode(record, options = {}) {
            if (!record?.id) {
                this.showNotification('error', 'Invalid Notion episode selection.');
                return;
            }

            const notify = options.notify !== false;
            const preservedInlineCount = Math.max(1, Number(options.inlineCount || Object.keys(this.normalizePressReleaseSelectionMap(this.pressRelease?.inline_photo_keys || {})).length || 1));
            this.pressReleaseImportingEpisodeId = record.id;

            try {
                const response = await this._rawPipelineFetch('{{ route("publish.pipeline.press-release.import-notion-episode") }}', {
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
                const importedInlineAssets = importedAssets.filter((asset) => {
                    if (!asset?.key) return false;
                    if (importedFeaturedAsset?.key && asset.key === importedFeaturedAsset.key) return false;
                    if (importedFeaturedUrl && asset.url === importedFeaturedUrl) return false;
                    return true;
                });
                this.pressRelease.inline_photo_keys = {};
                importedInlineAssets.slice(0, preservedInlineCount).forEach((asset) => {
                    this.pressRelease.inline_photo_keys[asset.key] = true;
                });
                this.syncPressReleaseSelectedPhotoKeys();
                this.applyPodcastPressReleaseMediaDefaults({ injectInline: false, notify: false });
                this.savePipelineState();
                this.invalidatePromptPreview('press_release_notion_episode_import', { fetch: this.currentStep === 5 || this.openSteps.includes(5) });
                if (notify) {
                    this.showNotification('success', data.message || 'Podcast episode imported from Notion.');
                }
                this._pressReleaseLegacyRefreshEpisodeId = null;
                return data;
            } catch (error) {
                if (notify) {
                    this.showNotification('error', error.message || 'Failed to import the selected podcast episode.');
                }
                this._pressReleaseLegacyRefreshEpisodeId = null;
                throw error;
            } finally {
                this.pressReleaseImportingEpisodeId = null;
            }
        },

        async uploadPressReleaseDocuments(files) {
            if (!files || files.length === 0) return;

            this.pressReleaseUploadingDocuments = true;
            const form = new FormData();
            form.append('draft_id', this.draftId);
            Array.from(files).forEach((file) => form.append('documents[]', file));

            try {
                const response = await this._rawPipelineFetch('{{ route("publish.pipeline.press-release.upload-documents") }}', {
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
                const response = await this._rawPipelineFetch('{{ route("publish.pipeline.press-release.detect-fields") }}', {
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
                const response = await this._rawPipelineFetch('{{ route("publish.pipeline.press-release.detect-photos") }}', {
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
                    this.pressRelease.inline_photo_keys = {};
                    this.pressRelease.featured_photo_key = '';
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
                const response = await this._rawPipelineFetch('{{ route("notion.profile.fetch-photos") }}', {
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
                    this.pressRelease.inline_photo_keys = {};
                    this.pressRelease.featured_photo_key = '';
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
            const payloadForServer = { ...payload, _saved_at: new Date().toISOString() };

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
                        payload: payloadForServer,
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
                    key: String(photo.key || '').trim() || ('detected-' + merged.length),
                    label: photo.label || photo.alt_text || photo.caption || photo.url || 'Detected photo',
                    url: key,
                    thumbnail_url: this.toAbsoluteMediaUrl(photo.thumbnail_url || photo.preview_url || photo.url),
                    preview_url: this.toAbsoluteMediaUrl(photo.preview_url || photo.thumbnail_url || photo.url),
                    source_url: this.toAbsoluteMediaUrl(photo.source_url || photo.download_url || photo.view_url || photo.url),
                    alt_text: photo.alt_text || '',
                    caption: photo.caption || '',
                    source: photo.source || 'detected',
                    source_label: photo.source_label || photo.source || 'Detected photo',
                    origin: photo.origin || photo.source || '',
                    role: photo.role || '',
                    download_url: this.toAbsoluteMediaUrl(photo.download_url || photo.source_url || photo.url),
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
            const [asset] = this.selectedPressReleaseInlineAssets(1);
            return asset ? { ...asset } : null;
        },

        selectedPressReleaseInlineAssets(limit = null) {
            const rawSelectionCount = Object.keys(this.pressRelease?.inline_photo_keys || {}).filter((key) => this.pressRelease?.inline_photo_keys?.[key]).length;
            const featuredUrl = this.toAbsoluteMediaUrl(this.pressRelease?.notion_episode?.featured_image_url || '');
            const selectedInlineKeys = Object.keys(this.normalizePressReleaseSelectionMap(this.pressRelease?.inline_photo_keys || {}));
            const selectedInlineKeySet = new Set(selectedInlineKeys);
            let assets = ((this.pressReleasePhotoAssets || []).length ? this.pressReleasePhotoAssets : this.rebuildPressReleasePhotoAssets())
                .map((asset) => ({ ...asset }))
                .filter((asset) => this.toAbsoluteMediaUrl(asset.url || '') && this.toAbsoluteMediaUrl(asset.url || '') !== featuredUrl);
            if (this.pressReleaseShouldFilterLegacyGuestMedia(assets)) {
                assets = assets.filter((asset) => !this.pressReleaseAssetIsLegacyGuestMedia(asset));
            }
            const seen = new Set();
            const selected = [];
            assets.forEach((asset) => {
                const key = this.pressReleaseAssetKey(asset);
                const assetUrl = this.toAbsoluteMediaUrl(asset.url || '');
                if (key && selectedInlineKeySet.has(key) && !seen.has(assetUrl)) {
                    selected.push(asset);
                    seen.add(assetUrl);
                }
            });

            const requestedCount = rawSelectionCount > 0 ? rawSelectionCount : selected.length;
            if (requestedCount > 0 && selected.length < requestedCount) {
                assets.forEach((asset) => {
                    if (selected.length >= requestedCount) return;
                    const assetUrl = this.toAbsoluteMediaUrl(asset.url || '');
                    if (!assetUrl || seen.has(assetUrl)) return;
                    selected.push(asset);
                    seen.add(assetUrl);
                });
            }

            if (!selected.length) {
                const inlineUrl = this.pressRelease?.notion_guest?.inline_photo_url || '';
                const match = assets.find((asset) => inlineUrl && asset.url === this.toAbsoluteMediaUrl(inlineUrl))
                    || assets.find((asset) => asset.source === 'notion-guest-drive')
                    || assets.find((asset) => asset.source === 'google-drive')
                    || assets.find((asset) => asset.role === 'inline' && !this.pressReleaseAssetIsLegacyGuestMedia(asset))
                    || assets.find((asset) => asset.role === 'inline')
                    || assets.find((asset) => asset.source === 'notion-guest-media')
                    || null;
                if (match) {
                    selected.push(match);
                }
            }

            return limit ? selected.slice(0, limit) : selected;
        },

        pressReleaseSourceUploadUrl(asset = {}) {
            return this.toAbsoluteMediaUrl(asset.source_url || asset.download_url || asset.view_url || asset.url || asset.thumbnail_url || '');
        },

        pressReleaseExtractGoogleDriveFileId(value = '') {
            const candidate = this.toAbsoluteMediaUrl(value);
            if (!candidate) return '';
            try {
                const parsed = new URL(candidate, window.location.origin);
                const directId = parsed.searchParams.get('id');
                if (directId) return directId;
                const match = parsed.pathname.match(/\/d\/([A-Za-z0-9_-]+)/i);
                if (match && match[1]) return match[1];
            } catch (error) {
                const match = String(candidate).match(/[?&]id=([A-Za-z0-9_-]+)/i) || String(candidate).match(/\/d\/([A-Za-z0-9_-]+)/i);
                if (match && match[1]) return match[1];
            }
            return '';
        },

        pressReleaseStableDrivePreviewUrl(asset = {}) {
            const candidates = [
                asset.source_url,
                asset.download_url,
                asset.view_url,
                asset.thumbnail_url,
                asset.preview_url,
                asset.url,
            ];
            for (const candidate of candidates) {
                const fileId = this.pressReleaseExtractGoogleDriveFileId(candidate || '');
                if (fileId) {
                    return 'https://drive.google.com/thumbnail?id=' + encodeURIComponent(fileId) + '&sz=w1600';
                }
            }
            return '';
        },

        pressReleaseEditorPreviewUrl(asset = {}) {
            const stableDrivePreview = this.pressReleaseStableDrivePreviewUrl(asset);
            if (stableDrivePreview) {
                return stableDrivePreview;
            }

            const preview = this.toAbsoluteMediaUrl(asset.thumbnail_url || asset.preview_url || asset.view_url || asset.download_url || asset.url || '');
            if (/googleusercontent\.com/i.test(preview)) {
                return preview
                    .replace(/=s\d+(?:-[a-z0-9_]+)?$/i, '=w1600')
                    .replace(/=w\d+(?:-[a-z0-9_]+)?$/i, '=w1600');
            }
            return preview;
        },

        pressReleaseHasInlineAsset(url) {
            const target = this.toAbsoluteMediaUrl(url);
            const html = this.editorContent || this.spunContent || '';
            return !!(target && html && html.includes(target));
        },

        buildPressReleaseInlineFigure(asset, position = 0) {
            const escapeHtml = (value) => String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
            const sourceUrl = this.pressReleaseSourceUploadUrl(asset);
            const previewUrl = this.pressReleaseEditorPreviewUrl(asset) || sourceUrl;
            const alt = escapeHtml(asset.alt_text || asset.label || 'Selected press release photo');
            const caption = escapeHtml(asset.caption || asset.alt_text || asset.label || 'Selected press release photo');
            const assetKey = escapeHtml(this.pressReleaseAssetKey(asset, 'press-release-inline-' + position));
            return '<figure class="press-release-inline-photo podcast-inline-guest-photo" data-press-release-inline="1" data-asset-key="' + assetKey + '">'
                + '<img src="' + previewUrl + '" data-source-url="' + sourceUrl + '" alt="' + alt + '" loading="lazy">'
                + '<figcaption>' + caption + '</figcaption></figure>';
        },

        removePressReleaseManagedInlineFigures(html, assets = null) {
            let cleaned = String(html || '');
            const selectedAssets = Array.isArray(assets) ? assets : this.selectedPressReleaseInlineAssets();
            cleaned = cleaned.replace(/<figure\b[^>]*class="[^"]*(?:press-release-inline-photo|podcast-inline-guest-photo)[^"]*"[^>]*>[\s\S]*?<\/figure>/gi, '');
            const escapeRegex = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            selectedAssets.forEach((asset) => {
                [this.pressReleaseSourceUploadUrl(asset), this.pressReleaseEditorPreviewUrl(asset)].forEach((candidate) => {
                    if (!candidate) return;
                    const quoted = escapeRegex(candidate).replace(/&/g, '&(?:amp;)?');
                    cleaned = cleaned.replace(new RegExp('<figure\\b[^>]*>[\\s\\S]*?<img[^>]+(?:src|data-source-url)\\s*=\\s*["\\\']' + quoted + '["\\\'][\\s\\S]*?<\\/figure>', 'gi'), '');
                    cleaned = cleaned.replace(new RegExp('<p\\b[^>]*>\\s*<img[^>]+(?:src|data-source-url)\\s*=\\s*["\\\']' + quoted + '["\\\'][^>]*>\\s*<\\/p>', 'gi'), '');
                });
            });
            if (this.currentArticleType === 'press-release' && this.pressRelease?.submit_method === 'notion-podcast') {
                cleaned = cleaned.replace(/<figure\b[^>]*>[\s\S]*?(?:media\.licdn\.com|drive-storage)[\s\S]*?<\/figure>/gi, '');
                cleaned = cleaned.replace(/<p\b[^>]*>[\s\S]*?(?:media\.licdn\.com|drive-storage)[\s\S]*?<\/p>/gi, '');
                cleaned = cleaned.replace(/<img[^>]+(?:media\.licdn\.com|drive-storage)[^>]*>\s*/gi, '');
            }
            return cleaned.replace(/\n{3,}/g, '\n\n').trim();
        },

        injectSelectedPressReleaseInlineAssetsIntoHtml(html, assets = null) {
            const inlineAssets = Array.isArray(assets) ? assets : this.selectedPressReleaseInlineAssets();
            let nextHtml = this.removePressReleaseManagedInlineFigures(html, inlineAssets);
            if (!inlineAssets.length) {
                return nextHtml;
            }

            const figures = inlineAssets.map((asset, idx) => this.buildPressReleaseInlineFigure(asset, idx));
            let inserted = 0;
            let paragraphIndex = 0;
            nextHtml = nextHtml.replace(/<\/p>/gi, (match) => {
                paragraphIndex += 1;
                if (inserted >= figures.length) {
                    return match;
                }
                const shouldInsert = paragraphIndex === 2 || (paragraphIndex > 2 && ((paragraphIndex - 2) % 2 === 0));
                if (!shouldInsert) {
                    return match;
                }
                const figure = figures[inserted++] || '';
                return match + '\n' + figure + '\n';
            });

            if (inserted < figures.length && /<h[2-6]\b[^>]*>\s*About\b/i.test(nextHtml)) {
                const remainder = figures.slice(inserted).join('\n');
                nextHtml = nextHtml.replace(/<h[2-6]\b[^>]*>\s*About\b/i, remainder + '\n$&');
                inserted = figures.length;
            }

            while (inserted < figures.length) {
                nextHtml += '\n' + figures[inserted++] + '\n';
            }

            return nextHtml.trim();
        },

        normalizePodcastPressReleaseYoutubeHtml(html) {
            let nextHtml = String(html || '').trim();
            const embedUrl = this.toAbsoluteMediaUrl(this.pressRelease?.notion_episode?.youtube_embed_url || '');
            if (!nextHtml || !embedUrl) {
                return nextHtml;
            }

            nextHtml = nextHtml.replace(/<div\b[^>]*>\s*(<iframe\b[^>]*youtube\.com\/embed\/[^>]*><\/iframe>)\s*<\/div>/gi, '$1');
            nextHtml = nextHtml.replace(/(<iframe\b[^>]*?)\s+style="[^"]*"/gi, '$1');

            let foundEmbed = false;
            nextHtml = nextHtml.replace(/<iframe\b([^>]*)src="([^"]*youtube[^"]*)"([^>]*)><\/iframe>/gi, (match, before, src, after) => {
                foundEmbed = true;
                const decoded = String(src || '').replace(/&amp;/gi, '&');
                const lower = decoded.toLowerCase();
                if (decoded === embedUrl && !lower.includes('your_video_id')) {
                    return match;
                }
                return '<iframe' + before + 'src="' + embedUrl + '"' + after + '></iframe>';
            });

            if (foundEmbed) {
                return nextHtml;
            }

            const iframe = '<div class="podcast-youtube-embed"><iframe width="560" height="315" src="' + embedUrl + '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';

            if (/<h[2-6]\b[^>]*>\s*About\b/i.test(nextHtml)) {
                return nextHtml.replace(/<h[2-6]\b[^>]*>\s*About\b/i, iframe + '\n$&');
            }

            let inserted = false;
            let paragraphIndex = 0;
            nextHtml = nextHtml.replace(/<\/p>/gi, (match) => {
                paragraphIndex += 1;
                if (!inserted && paragraphIndex === 2) {
                    inserted = true;
                    return match + '\n' + iframe + '\n';
                }
                return match;
            });

            if (inserted) {
                return nextHtml;
            }

            if (/<h[2-6]\b/i.test(nextHtml)) {
                return nextHtml.replace(/<h[2-6]\b/i, iframe + '\n$&');
            }

            return nextHtml + '\n' + iframe;
        },

        normalizePodcastPressReleaseHtml(html, options = {}) {
            let nextHtml = String(html || '').trim();
            if (this.currentArticleType !== 'press-release' || this.pressRelease.submit_method !== 'notion-podcast' || !nextHtml) {
                return nextHtml;
            }

            nextHtml = this.normalizePodcastPressReleaseYoutubeHtml(nextHtml);
            const inlineAssets = Array.isArray(options.inlineAssets) ? options.inlineAssets : this.selectedPressReleaseInlineAssets();
            nextHtml = this.injectSelectedPressReleaseInlineAssetsIntoHtml(nextHtml, inlineAssets);
            return nextHtml.trim();
        },

        seedPressReleaseInlinePhotoSuggestions(inlineAssets = null) {
            const assets = Array.isArray(inlineAssets) ? inlineAssets : this.selectedPressReleaseInlineAssets();
            this.photoSuggestions = assets.map((asset, idx) => this.normalizePhotoSuggestionState({
                position: idx,
                search_term: asset.caption || asset.alt_text || asset.label || ('selected press release photo ' + (idx + 1)),
                alt_text: asset.alt_text || asset.label || '',
                caption: asset.caption || asset.alt_text || asset.label || '',
                suggestedFilename: this.filenameBaseFromAsset(asset),
                autoPhoto: {
                    id: this.pressReleaseAssetKey(asset) || null,
                    source: asset.source || 'notion-guest-drive',
                    source_url: this.pressReleaseSourceUploadUrl(asset),
                    url: this.pressReleaseEditorPreviewUrl(asset) || this.pressReleaseSourceUploadUrl(asset),
                    url_thumb: this.pressReleaseEditorPreviewUrl(asset) || this.pressReleaseSourceUploadUrl(asset),
                    url_large: this.pressReleaseEditorPreviewUrl(asset) || this.pressReleaseSourceUploadUrl(asset),
                    url_full: this.pressReleaseSourceUploadUrl(asset),
                    alt: asset.alt_text || asset.label || '',
                    width: 0,
                    height: 0,
                },
                confirmed: true,
                removed: false,
                loadAttempted: true,
            }, idx));
            this.photoSuggestionsPending = false;
            this._lastInlinePhotoHydrationSignature = '';
        },

        applyPodcastPressReleaseMediaDefaults(options = {}) {
            if (this.currentArticleType !== 'press-release' || this.pressRelease.submit_method !== 'notion-podcast') {
                return;
            }

            const opts = { injectInline: false, notify: false, ...options };
            this.rebuildPressReleasePhotoAssets();
            const featuredAsset = this.preferredPressReleaseFeaturedAsset();
            if (featuredAsset) {
                this.setPressReleaseFeaturedPhoto(featuredAsset, { notify: opts.notify, save: false });
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            } else if (!this.featuredPhoto && (this.pressReleasePhotoAssets || []).length > 0) {
                this.setPressReleaseFeaturedPhoto({ ...(this.pressReleasePhotoAssets[0] || {}) }, { notify: opts.notify, save: false });
                this.featuredImageSearch = '';
                this.featuredSearchPending = false;
            }

            let inlineAssets = this.selectedPressReleaseInlineAssets();
            const hasInlineSelections = Object.keys(this.normalizePressReleaseSelectionMap(this.pressRelease?.inline_photo_keys || {})).length > 0;
            if (inlineAssets[0] && !hasInlineSelections) {
                this.togglePressReleaseInlinePhotoSelection(inlineAssets[0], inlineAssets[0].key, { force: true, save: false });
                inlineAssets = this.selectedPressReleaseInlineAssets();
            }

            this.seedPressReleaseInlinePhotoSuggestions(inlineAssets);
            const shouldInjectInline = !!opts.injectInline || this.currentStep === 6 || (Array.isArray(this.openSteps) && this.openSteps.includes(6));
            if (!shouldInjectInline) {
                this.syncPressReleaseSelectedPhotoKeys();
                this.syncDeferredEnrichmentState?.('press_release_media_defaults');
                this.savePipelineState();
                return;
            }

            const currentHtml = this._resolveCanonicalArticleHtml?.({ preferPrepared: false, hydrateState: false }) || this.editorContent || this.spunContent || this.lastNonEmptyDraftBody || '';
            if (!currentHtml) {
                this.syncPressReleaseSelectedPhotoKeys();
                this.syncDeferredEnrichmentState?.('press_release_media_defaults');
                this.savePipelineState();
                return;
            }

            const nextHtml = this.normalizePodcastPressReleaseHtml(currentHtml, { inlineAssets });
            if (nextHtml && nextHtml !== currentHtml) {
                this.spunContent = nextHtml;
                this.editorContent = nextHtml;
                this.spunWordCount = this.countWordsFromHtml?.(nextHtml) || this.spunWordCount;
                this.setSpinEditor?.(nextHtml);
                this.rememberDraftBody?.(nextHtml);
                this.extractArticleLinks?.(nextHtml);
                this.queueAutoSaveDraft?.(300);
            }

            this.syncPressReleaseSelectedPhotoKeys();
            this.syncDeferredEnrichmentState?.('press_release_media_defaults');
            this.savePipelineState();
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
            const key = this.pressReleaseAssetKey(asset);
            if (key) {
                this.pressRelease.featured_photo_key = key;
            }
            this.syncPressReleaseSelectedPhotoKeys();
            if (options.save !== false) {
                this.savePipelineState();
            }
            if (notify) {
                this.showNotification('success', 'Press release photo set as featured image.');
            }
        },

        insertPressReleaseAssetIntoEditor(asset) {
            this.togglePressReleaseInlinePhotoSelection(asset, this.pressReleaseAssetKey(asset), { force: true, save: false });
            const inlineAssets = this.selectedPressReleaseInlineAssets();
            this.seedPressReleaseInlinePhotoSuggestions(inlineAssets);

            const currentHtml = this._resolveCanonicalArticleHtml?.({ preferPrepared: false, hydrateState: false }) || this.editorContent || this.spunContent || '';
            const nextHtml = this.normalizePodcastPressReleaseHtml(currentHtml, { inlineAssets });

            if (nextHtml) {
                this.spunContent = nextHtml;
                this.editorContent = nextHtml;
                this.spunWordCount = this.countWordsFromHtml?.(nextHtml) || this.spunWordCount;
                this.setSpinEditor?.(nextHtml);
                this.rememberDraftBody?.(nextHtml);
                this.extractArticleLinks?.(nextHtml);
            }

            this.showNotification("success", "Selected press release photos were refreshed in the article body.");
            this.syncDeferredEnrichmentState?.('press_release_asset_inserted');
            this.savePipelineState();
            this.queueAutoSaveDraft(300);
        },
    };
}
</script>
