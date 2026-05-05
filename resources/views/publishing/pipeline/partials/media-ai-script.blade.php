        // ── AI Detection ──────────────────────────────────
        toggleAiDetection() {
            this.aiDetectionEnabled = !this.aiDetectionEnabled;
            localStorage.setItem('aiDetectionEnabled', this.aiDetectionEnabled ? 'true' : 'false');
        },

        toggleFlaggedSentence(id, text) {
            const idx = this.selectedFlaggedSentences.indexOf(id);
            if (idx === -1) {
                this.selectedFlaggedSentences.push(id);
                this.selectedFlaggedTexts[id] = text;
            } else {
                this.selectedFlaggedSentences.splice(idx, 1);
                delete this.selectedFlaggedTexts[id];
            }
        },

        async runAiDetection() {
            if (!this.spunContent || !this.aiDetectionEnabled) return;
            this.aiDetecting = true;
            this.aiDetectionRan = true;
            this.aiDetectionAllPass = false;
            this.selectedFlaggedSentences = [];
            this.selectedFlaggedTexts = {};

            // Initialize all detectors with loading state
            this.aiDetectionResults = {
                gptzero: { name: 'GPTZero', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
                copyleaks: { name: 'Copyleaks', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
                zerogpt: { name: 'ZeroGPT', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
                originality: { name: 'Originality', loading: true, success: false, ai_score: null, passes: false, debug_mode: false, message: '', sentences: [], raw: null, showRaw: false },
            };

            this._logSpin('info', 'Running AI detection scan...');

            const spinEditor = tinymce.get('spin-preview-editor');
            const editorHtml = spinEditor ? (spinEditor.getContent() || '') : '';
            const html = (editorHtml && editorHtml.trim()) ? editorHtml : (this.spunContent || '');
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            const plainText = (tmp.textContent || tmp.innerText || '').trim();

            if (!plainText) {
                this._logSpin('warning', 'Skipped AI detection because no article text was available yet.');
                this.aiDetecting = false;
                return;
            }

            try {
                const resp = await fetch('{{ route("publish.pipeline.detect-ai") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ text: plainText, article_id: this.draftId })
                });
                const data = await resp.json();

                if (data.success) {
                    this.aiDetectionThreshold = data.threshold;
                    this.aiDetectionAllPass = data.all_pass;

                    const updatedResults = {};
                    for (const [key, result] of Object.entries(data.results)) {
                        updatedResults[key] = { ...result, loading: false, showRaw: false };
                    }
                    this.aiDetectionResults = updatedResults;

                    const passCount = Object.values(data.results).filter(r => r.passes).length;
                    const totalCount = Object.values(data.results).length;
                    this._logSpin(data.all_pass ? 'success' : 'warning', 'AI Detection: ' + passCount + '/' + totalCount + ' passed (max ' + data.threshold + '% AI)');
                } else {
                    this._logSpin('error', 'AI detection failed: ' + (data.message || 'Unknown error'));
                    this.aiDetectionResults = {};
                }
            } catch (e) {
                this._logSpin('error', 'AI detection network error: ' + (e.message || 'Request failed'));
                const failed = {};
                for (const key of Object.keys(this.aiDetectionResults)) {
                    failed[key] = { ...this.aiDetectionResults[key], loading: false, success: false, message: 'Network error' };
                }
                this.aiDetectionResults = failed;
            }
            this.aiDetecting = false;
        },

        processDetectionRespin() {
            // Use selected checkboxes if any, otherwise all flagged from failing detectors
            const selected = Object.values(this.selectedFlaggedTexts);
            let flagged = [];

            if (selected.length > 0) {
                flagged = selected;
            } else {
                for (const [key, det] of Object.entries(this.aiDetectionResults)) {
                    if (!det.passes && det.sentences && det.sentences.length > 0) {
                        flagged.push(...det.sentences);
                    }
                }
            }

            const humanizePrompt = flagged.length > 0
                ? 'The following sentences were flagged as AI-generated. Rewrite them to sound more natural and human:\n\n' + flagged.map(s => '- ' + s).join('\n') + '\n\nMake the writing more conversational, vary sentence length, use natural transitions, and avoid formulaic patterns.'
                : 'This article was flagged as AI-generated. Rewrite it to sound more natural and human. Vary sentence length, use natural transitions, and avoid formulaic patterns.';

            this.spinChangeRequest = humanizePrompt;
            this.showChangeInput = true;
            this.loadSmartEdits();
            this.$nextTick(() => {
                document.querySelector('[x-show="showChangeInput"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },

        ignoreDetection() {
            this.acceptSpin();
        },

        @include('app-publish::publishing.pipeline.partials.spin-editor-script')

        async generateMetadata(html) {
            this.metadataLoading = true;
            try {
                const resp = await fetch('{{ route("publish.pipeline.metadata") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ article_html: html, draft_id: this.draftId || null })
                });
                const data = await resp.json();
                if (data.success) {
                    this.applyGeneratedMetadata(data);
                    this.suggestedUrls = (data.urls || []).map((url) => this.normalizeSuggestedUrlEntry({
                        url,
                        title: url,
                        nofollow: true,
                    }));
                }
            } catch (e) { /* silently fail */ }
            this.metadataLoading = false;
        },

        toggleSelection(arr, idx) {
            const pos = arr.indexOf(idx);
            if (pos === -1) arr.push(idx);
            else arr.splice(pos, 1);
        },

        addCustomCategory() {
            this.addCustomMetadataValue('category', this.customCategoryInput);
            this.customCategoryInput = '';
        },

        addCustomTag() {
            this.addCustomMetadataValue('tag', this.customTagInput);
            this.customTagInput = '';
        },

        normalizeSuggestedUrlEntry(link = {}) {
            return {
                url: String(link.url || '').trim(),
                title: String(link.title || link.url || '').trim(),
                nofollow: !!link.nofollow,
                checking: false,
                status_code: link.status_code ?? null,
                status_text: link.status_text || '',
                status_tone: link.status_tone || 'gray',
                checked_via: link.checked_via || '',
                final_url: link.final_url || '',
                is_broken: !!link.is_broken,
            };
        },

        extractArticleLinks(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            const anchors = tmp.querySelectorAll('a[href]');
            const seen = new Set();
            this.suggestedUrls = Array.from(anchors)
                .map(a => this.normalizeSuggestedUrlEntry({
                    url: a.getAttribute('href'),
                    title: a.textContent.trim() || a.getAttribute('href'),
                    nofollow: (a.getAttribute('rel') || '').includes('nofollow'),
                }))
                .filter((link) => {
                    if (!link.url || !link.url.startsWith('http')) return false;
                    const key = link.url.replace(/\/+$/, '').toLowerCase();
                    if (seen.has(key)) return false;
                    seen.add(key);
                    return true;
                });
        },

        async checkArticleLinkStatus(idx) {
            const link = this.suggestedUrls[idx];
            if (!link?.url || link.checking) return;

            this.suggestedUrls.splice(idx, 1, { ...link, checking: true });

            try {
                const resp = await fetch('{{ route("publish.pipeline.link-status") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ url: link.url }),
                });
                const data = await resp.json();
                const finalUrl = data.data?.final_url || '';
                const canonicalized = !!(finalUrl && !data.data?.is_broken && finalUrl !== link.url);
                const updated = {
                    ...link,
                    url: canonicalized ? finalUrl : link.url,
                    checking: false,
                    status_code: data.data?.status_code ?? null,
                    status_text: canonicalized
                        ? ((data.data?.status_text || 'Redirect updated') + ' · canonicalized')
                        : (data.data?.status_text || (data.success ? 'OK' : 'Check failed')),
                    status_tone: data.data?.status_tone || (data.success ? 'green' : 'red'),
                    checked_via: data.data?.checked_via || '',
                    final_url: finalUrl,
                    is_broken: !!(data.data?.is_broken),
                };

                this.suggestedUrls.splice(idx, 1, updated);

                if (updated.is_broken) {
                    this.showNotification('warning', 'Broken link detected: ' + updated.status_text);
                } else if (canonicalized) {
                    this.showNotification('success', 'Updated redirected link to canonical URL.');
                }
            } catch (e) {
                this.suggestedUrls.splice(idx, 1, {
                    ...link,
                    checking: false,
                    status_text: 'Check failed',
                    status_tone: 'red',
                    is_broken: false,
                });
            }
        },

        async checkAllArticleLinks() {
            if (!Array.isArray(this.suggestedUrls) || this.suggestedUrls.length === 0 || this.checkingAllArticleLinks) return;
            this.checkingAllArticleLinks = true;
            await Promise.all(this.suggestedUrls.map((link, idx) => this.checkArticleLinkStatus(idx)));
            this.checkingAllArticleLinks = false;
        },

        async loadSyndicationCategories(force = false) {
            if (!this.selectedSite?.id || this.loadingSyndicationCats) return;
            this.loadingSyndicationCats = true;
            try {
                const url = new URL("/publish/sites/" + this.selectedSite.id + "/categories", window.location.origin);
                url.searchParams.set("taxonomy", "publication");
                if (force) {
                    url.searchParams.set("force", "1");
                }
                const resp = await fetch(url.toString(), {
                    headers: this.requestHeaders(),
                });
                const data = await resp.json();
                if (data.success && Array.isArray(data.categories)) {
                    const normalized = data.categories.map((cat) => ({
                        ...cat,
                        id: Number(cat.id),
                        parent: Number(cat.parent || 0),
                        depth: Number(cat.depth || 0),
                        label: cat.label || cat.name || "",
                        is_parent: !!cat.is_parent,
                    }));
                    this.syndicationCategories = normalized;
                    this.syndicationCategoriesCacheMeta = {
                        cached: !!data.cached,
                        cached_at: data.cached_at || null,
                        age_seconds: Number(data.age_seconds || 0),
                        age_human: data.age_human || "just now",
                    };
                    const existing = Array.isArray(this.selectedSyndicationCats)
                        ? this.selectedSyndicationCats.map((id) => Number(id))
                        : [];
                    const validIds = new Set(normalized.map((cat) => Number(cat.id)));
                    const preserved = existing.filter((id) => validIds.has(Number(id)));
                    this.selectedSyndicationCats = preserved.length > 0 && !force
                        ? preserved
                        : normalized.map((cat) => Number(cat.id));
                    this._syndicationAutoRequested = true;
                    this._syndicationAutoSiteId = String(this.selectedSite?.id || "");
                    this.savePipelineState?.();
                } else {
                    this.showNotification("error", data.message || "Failed to load publication taxonomy");
                }
            } catch (e) {
                this.showNotification("error", "Error loading publication taxonomy: " + e.message);
            }
            this.loadingSyndicationCats = false;
        },

        maybeAutoLoadSyndicationCategories() {
            const siteId = String(this.selectedSite?.id || "");
            const stepSixActive = Number(this.currentStep || 0) === 6
                || (Array.isArray(this.openSteps) && this.openSteps.includes(6));

            if (this.currentArticleType !== 'press-release' || !this.selectedSite?.is_press_release_source || !siteId || !stepSixActive) {
                return false;
            }

            if (this._syndicationAutoSiteId !== siteId) {
                this._syndicationAutoSiteId = siteId;
                this._syndicationAutoRequested = false;
            }

            if (this.loadingSyndicationCats || (Array.isArray(this.syndicationCategories) && this.syndicationCategories.length > 0) || this._syndicationAutoRequested) {
                return false;
            }

            this._syndicationAutoRequested = true;
            return this.loadSyndicationCategories(false);
        },

        resyncSyndicationCategories() {
            this._syndicationAutoRequested = true;
            return this.loadSyndicationCategories(true);
        },

        selectAllSyndicationCats() {
            this.selectedSyndicationCats = (this.syndicationCategories || []).map((cat) => Number(cat.id));
            this.savePipelineState?.();
        },

        clearSyndicationCats() {
            this.selectedSyndicationCats = [];
            this.savePipelineState?.();
        },

        syndicationCategoryTree() {
            const categories = Array.isArray(this.syndicationCategories) ? this.syndicationCategories : [];
            const byParent = new Map();
            categories.forEach((cat) => {
                const parentId = Number(cat.parent || 0);
                if (!byParent.has(parentId)) byParent.set(parentId, []);
                byParent.get(parentId).push(cat);
            });
            const build = (parentId = 0) => {
                return (byParent.get(Number(parentId)) || []).map((cat) => ({
                    ...cat,
                    children: build(Number(cat.id)),
                }));
            };
            return build(0);
        },

        syndicationDescendantIds(parentId) {
            const categories = Array.isArray(this.syndicationCategories) ? this.syndicationCategories : [];
            const out = [];
            const walk = (id) => {
                categories
                    .filter((cat) => Number(cat.parent || 0) === Number(id))
                    .forEach((child) => {
                        out.push(Number(child.id));
                        walk(Number(child.id));
                    });
            };
            walk(Number(parentId));
            return out;
        },

        syndicationNodeState(cat) {
            if (!cat) return 'none';
            const ids = [Number(cat.id), ...this.syndicationDescendantIds(Number(cat.id))];
            const selected = ids.filter((id) => this.selectedSyndicationCats.includes(Number(id))).length;
            if (selected === 0) return 'none';
            if (selected === ids.length) return 'all';
            return 'some';
        },

        isSyndicationSelected(id) {
            return this.selectedSyndicationCats.includes(Number(id));
        },

        toggleSyndicationCat(id) {
            const normalizedId = Number(id);
            const idx = this.selectedSyndicationCats.indexOf(normalizedId);
            if (idx > -1) this.selectedSyndicationCats.splice(idx, 1);
            else this.selectedSyndicationCats.push(normalizedId);
            this.selectedSyndicationCats = Array.from(new Set(this.selectedSyndicationCats.map((value) => Number(value))));
            this.savePipelineState?.();
        },

        toggleSyndicationTreeNode(cat) {
            if (!cat) return;
            const ids = [Number(cat.id), ...this.syndicationDescendantIds(Number(cat.id))];
            const shouldSelect = ids.some((id) => !this.selectedSyndicationCats.includes(Number(id)));
            if (shouldSelect) {
                this.selectedSyndicationCats = Array.from(new Set([
                    ...this.selectedSyndicationCats.map((value) => Number(value)),
                    ...ids,
                ]));
            } else {
                const remove = new Set(ids.map((id) => Number(id)));
                this.selectedSyndicationCats = this.selectedSyndicationCats.filter((id) => !remove.has(Number(id)));
            }
            this.savePipelineState?.();
        },

        removeArticleLink(idx) {
            const link = this.suggestedUrls[idx];
            if (!link) return;

            // Remove from the TinyMCE editor content
            const editor = typeof tinymce !== 'undefined' ? tinymce.activeEditor : null;
            if (editor) {
                let html = editor.getContent();
                // Find and remove <a> tags matching this URL — replace with just the link text
                const tmp = document.createElement('div');
                tmp.innerHTML = html;
                const anchors = tmp.querySelectorAll('a[href]');
                for (const a of anchors) {
                    if (a.getAttribute('href') === link.url) {
                        a.replaceWith(document.createTextNode(a.textContent));
                    }
                }
                this._safeSetSpinEditorContent(editor, tmp.innerHTML, { syncState: false });
                this.editorContent = tmp.innerHTML;
                this.spunContent = tmp.innerHTML;
            }

            // Remove from the array
            this.suggestedUrls.splice(idx, 1);
        },

        async searchPhotos() {
            if (!this.photoSearch.trim()) return;
            this.photoSearching = true;
            this.photoResults = [];
            try {
                const resp = await fetch('{{ route("publish.search.images.post") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        query: this.photoSearch,
                        draft_id: this.draftId || null,
                        per_page: 15,
                        // Keep default inline search on stock-only sources.
                        sources: ['pexels'],
                        quality_context: 'inline',
                        probe_quality: false,
                    })
                });
                const data = await resp.json();
                this.photoResults = data.data?.photos || [];
            } catch (e) { this.photoResults = []; }
            this.photoSearching = false;
        },

        async searchFeaturedImage() {
            if (!this.featuredImageSearch.trim()) return;
            this.featuredSearching = true;
            this.featuredSearchAttempted = true;
            this.featuredSearchPending = true;
            this.featuredLoadError = '';
            this.featuredThumbError = '';
            this.featuredResults = [];
            try {
                const resp = await fetch('{{ route("publish.search.google-images") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        query: this.featuredImageSearch,
                        draft_id: this.draftId || null,
                        per_page: 8,
                        // Force a single explicit Google Images provider call.
                        provider: 'google-cse',
                        quality_context: 'featured',
                        probe_quality: false,
                    })
                });
                const data = await resp.json();
                const photos = data.data?.photos || [];
                this.featuredResults = photos;
                if (photos.length > 0) {
                    this.featuredPhoto = this.sanitizePhotoAssetForPersistence(photos[0]);
                    this.setFeaturedThumbPending();
                    this.featuredLoadError = '';
                } else {
                    this.featuredLoadError = data.message || 'No featured image suggestions found.';
                    this.featuredThumbLoading = false;
                }
            } catch (e) {
                this.featuredLoadError = e.message || 'Featured image search failed.';
                this.featuredThumbLoading = false;
            }
            this.featuredSearching = false;
            this.syncDeferredEnrichmentState('featured_search');
            this.queueAutoSaveDraft(300);
        },

        importFeaturedFromUrl() {
            const url = this.featuredUrlImport.trim();
            if (!url) return;
            this.applyFeaturedPhotoSelection({ url_large: url, url_thumb: url, url: url, source: 'url-import', alt: '', width: 0, height: 0 });
            this.featuredUrlImport = '';
            this.showNotification('success', 'Featured image set from URL');
        },

        async uploadFeaturedPhoto(files) {
            if (!files || !files.length) return;
            const file = files[0];
            const url = URL.createObjectURL(file);
            // Upload to server temp storage
            const formData = new FormData();
            formData.append('file', file);
            formData.append('draft_id', this.draftId);
            formData.append('type', 'featured');
            try {
                const resp = await fetch('{{ route("publish.pipeline.upload-photo") }}', {
                    method: 'POST',
                    headers: this.requestHeaders(),
                    body: formData,
                });
                const data = await resp.json();
                if (data.success) {
                    this.applyFeaturedPhotoSelection({ url_large: data.url, url_thumb: data.url, url: data.url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: data.width || 0, height: data.height || 0 });
                    this.showNotification('success', 'Featured image uploaded');
                } else {
                    this.showNotification('error', data.message || 'Upload failed');
                }
            } catch (e) {
                // Fallback: use local blob URL
                this.applyFeaturedPhotoSelection({ url_large: url, url_thumb: url, url: url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: 0, height: 0 });
                this.showNotification('info', 'Using local preview — will upload during prepare');
            }
        },

        importInnerPhotoFromUrl() {
            const url = this.innerPhotoUrlImport.trim();
            if (!url) return;
            this.insertingPhoto = { url_large: url, url_thumb: url, url: url, source: 'url-import', alt: '', width: 0, height: 0 };
            this.overlayPhotoAlt = 'Click Get Metadata to generate';
            this.overlayPhotoCaption = 'Click Get Metadata to generate';
            this.overlayPhotoFilename = 'auto';
            this.overlayMetaGenerated = false;
            this.innerPhotoUrlImport = '';
        },

        async uploadInnerPhoto(files) {
            if (!files || !files.length) return;
            const file = files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('draft_id', this.draftId);
            formData.append('type', 'inner');
            try {
                const resp = await fetch('{{ route("publish.pipeline.upload-photo") }}', {
                    method: 'POST',
                    headers: this.requestHeaders(),
                    body: formData,
                });
                const data = await resp.json();
                if (data.success) {
                    this.insertingPhoto = { url_large: data.url, url_thumb: data.url, url: data.url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: data.width || 0, height: data.height || 0 };
                    this.overlayPhotoAlt = file.name.replace(/\.[^.]+$/, '');
                    this.overlayPhotoCaption = '';
                    this.overlayPhotoFilename = file.name.replace(/\.[^.]+$/, '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
                    this.overlayMetaGenerated = false;
                } else {
                    this.showNotification('error', data.message || 'Upload failed');
                }
            } catch (e) {
                const url = URL.createObjectURL(file);
                this.insertingPhoto = { url_large: url, url_thumb: url, url: url, source: 'upload', alt: file.name.replace(/\.[^.]+$/, ''), width: 0, height: 0 };
                this.overlayPhotoAlt = file.name.replace(/\.[^.]+$/, '');
                this.overlayPhotoCaption = '';
                this.overlayPhotoFilename = 'auto';
                this.overlayMetaGenerated = false;
            }
        },

        insertPhotoIntoEditor() {
            if (!this.insertingPhoto) return;
            const photo = this.insertingPhoto;
            const caption = this.photoCaption || '';
            const imgUrl = this.resolvePhotoLargeUrl(photo) || this.resolvePhotoThumbUrl(photo);
            const figureHtml = '<figure class="wp-block-image"><img src="' + imgUrl + '" alt="' + caption.replace(/"/g, '&quot;') + '" style="max-width:100%;height:auto"><figcaption>' + caption + '</figcaption></figure>';
            const editor = tinymce.get('spin-preview-editor');
            if (editor) {
                if (this._photoSuggestionIdx !== null) {
                    const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + this._photoSuggestionIdx + '"]');
                    if (placeholder) {
                        editor.dom.setOuterHTML(placeholder, figureHtml);
                        this.photoSuggestions[this._photoSuggestionIdx].autoPhoto = photo;
                        this.photoSuggestions[this._photoSuggestionIdx].confirmed = true;
                        this.syncDeferredEnrichmentState('inline_photo_inserted');
                    } else {
                        editor.execCommand('mceInsertContent', false, figureHtml);
                    }
                    this._photoSuggestionIdx = null;
                } else {
                    editor.execCommand('mceInsertContent', false, figureHtml);
                }
            }
            this.syncEditorStateFromEditor();
            this.queueAutoSaveDraft(300);
            this.insertingPhoto = null;
            this.photoCaption = '';
            this.showPhotoPanel = false;
        },

        // ── Photo Management ─────────────────────────────
        async loadPendingPhotoSuggestions() {
            if (!this.hasUnresolvedPhotoSuggestions()) {
                this.photoSuggestionsPending = false;
                return;
            }

            (this.photoSuggestions || []).forEach((ps, idx) => {
                if (!ps?.removed && !ps?.autoPhoto && (ps?.search_term || '').trim()) {
                    this.photoSuggestions[idx].loadAttempted = false;
                    this.photoSuggestions[idx].loadError = '';
                    this.photoSuggestions[idx].searching = false;
                }
            });
            this._lastInlinePhotoHydrationSignature = '';

            this._logActivity('enrichment', 'info', 'Loading deferred inline photo suggestions', {
                stage: 'enrichment',
                substage: 'inline_photos_requested',
                details: this._summarizeValue({
                    pending_count: this.photoSuggestions.filter(ps => !ps?.removed && !ps?.autoPhoto && (ps?.search_term || '').trim()).length,
                }, 200),
                debug_only: !this.pipelineDebugEnabled,
            });
            await this.autoFetchPhotos();
        },

        async autoFetchPhotos() {
            if (this.photoSuggestions.length === 0) return;
            const pendingIndexes = this.photoSuggestions
                .map((ps, idx) => ({ ps, idx }))
                .filter(({ ps }) => !ps?.removed && !ps?.autoPhoto && !ps?.loadAttempted && (ps?.search_term || '').trim())
                .map(({ idx }) => idx);

            if (pendingIndexes.length === 0) {
                this.photoSuggestionsPending = false;
                return;
            }

            this.autoFetchingPhotos = true;
            this.photoSuggestionsPending = false;
            pendingIndexes.forEach((idx) => {
                this.photoSuggestions[idx].loadAttempted = true;
                this.photoSuggestions[idx].searching = true;
                this.photoSuggestions[idx].loadError = '';
                this.photoSuggestions[idx].thumbError = '';
            });

            try {
                const resp = await fetch('{{ route("publish.search.images.batch") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        queries: pendingIndexes.map((idx) => ({
                            key: String(idx),
                            query: this.photoSuggestions[idx]?.search_term || '',
                            per_page: 12,
                        })),
                        // Auto inline enrichment stays on a single stock provider by default.
                        sources: ['pexels'],
                        quality_context: 'inline',
                        probe_quality: false,
                    }),
                });
                const data = await resp.json();
                const results = data.data?.results || {};

                await this._withPersistenceSuspended(async () => {
                    pendingIndexes.forEach((idx) => {
                        const ps = this.photoSuggestions[idx];
                        const result = results[String(idx)] || {};
                        const photos = result.photos || [];

                        this.photoSuggestions[idx].searching = false;
                        this.photoSuggestions[idx].searchResults = photos;

                        if (photos.length > 0) {
                            this.photoSuggestions[idx].autoPhoto = this.sanitizePhotoAssetForPersistence(photos[0]);
                            this.photoSuggestions[idx].loadError = '';
                            this.photoSuggestions[idx].suggestedFilename = this.buildFilename(ps.search_term, idx + 1);
                            this.setPhotoThumbPending(idx);
                            this.updatePlaceholderInEditor(idx, 0, { syncEditor: false });
                        } else {
                            const errorMessage = (result.errors && result.errors.length ? result.errors.join(' | ') : '') || 'No photos found';
                            this.updatePlaceholderError(idx, errorMessage, { syncEditor: false });
                        }
                    });

                    this.syncEditorStateFromEditor();
                    this.syncDeferredEnrichmentState('inline_photo_fetch');
                });
            } catch (e) {
                await this._withPersistenceSuspended(async () => {
                    pendingIndexes.forEach((idx) => {
                        this.photoSuggestions[idx].searching = false;
                        this.updatePlaceholderError(idx, e.message || 'Search failed', { syncEditor: false });
                    });
                    this.syncEditorStateFromEditor();
                    this.syncDeferredEnrichmentState('inline_photo_fetch_error');
                });
            }
            this.autoFetchingPhotos = false;
            this.queueAutoSaveDraft(300);
        },

        updatePlaceholderError(idx, message, { syncEditor = true } = {}) {
            if (this.photoSuggestions[idx]) {
                this.photoSuggestions[idx].loadError = message;
                this.photoSuggestions[idx].thumbLoading = false;
                this.photoSuggestions[idx].thumbError = message;
            }
            const editor = tinymce.get('spin-preview-editor');
            if (!editor || !editor.getBody()) return;
            const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
            if (!placeholder) return;
            const ps = this.photoSuggestions[idx];
            const newHtml = '<div class="photo-placeholder" contenteditable="false" data-idx="' + idx + '" style="border:2px dashed #ef4444;background:#fef2f2;border-radius:8px;padding:12px 16px;margin:16px 0;text-align:center;color:#dc2626;font-size:13px;">'
                + '<span>' + message + ' for: ' + this._escHtml(ps?.search_term || '') + '</span><br>'
                + '<span class="photo-change" style="cursor:pointer;display:inline-block;background:#2563eb;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-top:6px;">Search Manually</span>'
                + '</div>';
            editor.dom.setOuterHTML(placeholder, newHtml);
            if (syncEditor) this.syncEditorStateFromEditor();
        },

        updatePlaceholderInEditor(idx, retries, { syncEditor = true, markThumbPending = true } = {}) {
            retries = retries || 0;
            const editor = tinymce.get('spin-preview-editor');
            if (!editor || !editor.getBody()) {
                if (retries < 10) { const self = this; setTimeout(() => self.updatePlaceholderInEditor(idx, retries + 1, { syncEditor, markThumbPending }), 500); }
                return;
            }
            const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
            if (!placeholder) {
                if (retries < 10) { const self = this; setTimeout(() => self.updatePlaceholderInEditor(idx, retries + 1, { syncEditor, markThumbPending }), 500); }
                return;
            }
            const ps = this.photoSuggestions[idx];
            if (!ps || !ps.autoPhoto) return;
            this.photoSuggestions[idx].thumbLoading = !!markThumbPending;
            this.photoSuggestions[idx].thumbError = '';
            const thumbUrl = this.resolvePhotoLargeUrl(ps.autoPhoto);
            const altText = ps.alt_text || ps.caption || '';
            const newHtml = '<div class="photo-placeholder" contenteditable="false" data-idx="' + idx + '" data-search="' + this._escHtml(ps.search_term) + '" data-caption="' + this._escHtml(altText) + '" style="border:2px solid #a78bfa;background:#faf5ff;border-radius:8px;padding:12px;margin:16px 0;cursor:pointer;">'
                + '<img src="' + thumbUrl + '" style="width:300px;max-width:100%;height:auto;object-fit:cover;border-radius:6px;display:block;margin-bottom:8px;" />'
                + '<p style="margin:0 0 4px;font-size:12px;color:#7c3aed;font-weight:600;">' + this._escHtml(ps.search_term) + '</p>'
                + '<p style="margin:0 0 8px;font-size:11px;color:#6b7280;">' + this._escHtml(altText) + '</p>'
                + '<span class="photo-view" style="cursor:pointer;display:inline-block;background:#7c3aed;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-right:4px;">View</span>'
                + '<span class="photo-confirm" style="cursor:pointer;display:inline-block;background:#16a34a;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-right:4px;font-weight:600;">Confirm</span>'
                + '<span class="photo-change" style="cursor:pointer;display:inline-block;background:#2563eb;color:white;padding:2px 8px;border-radius:4px;font-size:11px;margin-right:4px;">Change</span>'
                + '<span class="photo-remove" style="cursor:pointer;display:inline-block;background:#dc2626;color:white;padding:2px 8px;border-radius:4px;font-size:11px;">Remove</span>'
                + '</div>';
            editor.dom.setOuterHTML(placeholder, newHtml);
            if (syncEditor) this.syncEditorStateFromEditor();
            if (markThumbPending) this.scheduleThumbStateReconcile('inline_placeholder:' + idx);
        },

        hydrateResolvedPhotoPlaceholders(reason = '') {
            const editor = tinymce.get('spin-preview-editor');
            if (!editor || !editor.getBody()) return false;

            let hydrated = 0;
            (this.photoSuggestions || []).forEach((ps, idx) => {
                if (ps?.removed) return;

                const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
                if (!placeholder) return;

                if (ps?.autoPhoto) {
                    this.updatePlaceholderInEditor(idx, 0, { syncEditor: false, markThumbPending: false });
                    hydrated++;
                    return;
                }

                if (ps?.loadError) {
                    this.updatePlaceholderError(idx, ps.loadError, { syncEditor: false });
                    hydrated++;
                }
            });

            if (hydrated > 0) {
                this.syncEditorStateFromEditor();
                this._logDebug('enrichment', 'Hydrated restored editor photo placeholders', {
                    stage: 'enrichment',
                    substage: 'editor_placeholder_restore',
                    details: this._summarizeValue({ reason, hydrated }, 200),
                });
            }

            return hydrated > 0;
        },

        _escHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        _slugify(str) {
            return String(str || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 80);
        },

        buildFilename(searchTerm, index) {
            const slug = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 60);
            const now = new Date();
            const dateStr = now.getFullYear().toString() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
            return (this.filenamePattern || 'hexa_{draft_id}_{seo_name}')
                .replace('{draft_id}', this.draftId || '0')
                .replace('{seo_name}', slug(searchTerm))
                .replace('{index}', String(index || 1))
                .replace('{article_slug}', slug(this.articleTitle))
                .replace('{date}', dateStr)
                .replace('{post_id}', '0');
        },

        countWordsFromHtml(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html || '';
            const text = (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
            return text ? text.split(' ').length : 0;
        },

        rememberDraftBody(html) {
            if ((html || '').trim()) {
                this.lastNonEmptyDraftBody = html;
            }
        },

        resolveDraftBodyForSave() {
            const editor = typeof tinymce !== 'undefined' ? tinymce.get('spin-preview-editor') : null;
            const body = this._resolveCanonicalArticleHtml({ preferPrepared: false, hydrateState: true });

            return {
                body: body || '',
                editorReady: !!editor,
            };
        },

        syncEditorStateFromEditor() {
            this._resolveCanonicalArticleHtml({ preferPrepared: false, hydrateState: true });
        },

        _resolveCanonicalArticleHtml({ preferPrepared = false, hydrateState = false } = {}) {
            const candidates = [];
            const pushCandidate = (value) => {
                const content = String(value || '').trim();
                if (!content) return;

                const textOnly = content
                    .replace(/<br\s*\/?>/gi, ' ')
                    .replace(/&nbsp;/gi, ' ')
                    .replace(/<p>\s*<\/p>/gi, ' ')
                    .replace(/<[^>]+>/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();

                if (!textOnly) return;
                candidates.push(content);
            };

            const editor = typeof tinymce !== 'undefined' ? tinymce.get('spin-preview-editor') : null;
            if (preferPrepared) pushCandidate(this.preparedHtml);

            if (editor) {
                try { pushCandidate(editor.getContent()); } catch (error) {}
                try { pushCandidate(editor.getBody()?.innerHTML); } catch (error) {}
                try { pushCandidate(editor.targetElm?.value); } catch (error) {}
            }

            const textarea = document.getElementById('spin-preview-editor');
            if (textarea) {
                pushCandidate(textarea.value);
                pushCandidate(textarea.innerHTML);
            }

            pushCandidate(this.editorContent);
            pushCandidate(this.spunContent);
            if (!preferPrepared) pushCandidate(this.preparedHtml);
            pushCandidate(this.lastNonEmptyDraftBody);
            pushCandidate(this.latestCompletedPrepareHtml);

            const resolved = candidates[0] || '';

            if (hydrateState && resolved) {
                this.spunContent = resolved;
                this.editorContent = resolved;
                this.spunWordCount = this.countWordsFromHtml(resolved);
                this.rememberDraftBody(resolved);
                this.extractArticleLinks(resolved);
                if (textarea && textarea.value !== resolved) {
                    textarea.value = resolved;
                }
            }

            return resolved;
        },

        resetGeneratedArticleStateForSpin() {
            this.suggestedTitles = [];
            this.suggestedCategories = [];
            this.suggestedTags = [];
            this.selectedTitleIdx = 0;
            this.selectedCategories = [];
            this.selectedTags = [];
            this.suggestedUrls = [];
            this.articleTitle = '';
            this.articleDescription = '';
            this.photoSuggestions = [];
            this.photoSuggestionsPending = false;
            this.uploadedImages = {};
            this.featuredImageSearch = '';
            this.featuredPhoto = null;
            this.featuredResults = [];
            this.featuredSearchPending = false;
            this.featuredAlt = '';
            this.featuredCaption = '';
            this.featuredFilename = '';
            this.featuredRefreshingMeta = false;
            this.promptPreviewDirty = true;
            this._lastPromptPreviewSignature = '';
            this.preparing = false;
            this.prepareChecklist = [];
            this.prepareLog = [];
            this.prepareComplete = false;
            this.prepareIntegrityIssues = [];
            this.preparedHtml = '';
            this.preparedCategoryIds = [];
            this.preparedTagIds = [];
            this.preparedFeaturedMediaId = null;
            this.preparedFeaturedWpUrl = null;
            this.aiDetectionResults = {};
            this.aiDetectionAllPass = false;
            this.aiDetectionRan = false;
            this.selectedFlaggedSentences = [];
            this.selectedFlaggedTexts = {};
        },

        queueAutoSaveDraft(delay) {
            if (this._restoring) return;
            if (this._suspendDraftAutoSave) {
                this._pendingPostSuspendDraftSave = true;
                return;
            }
            if (this._draftSaveTimer) clearTimeout(this._draftSaveTimer);
            this._logDebug('draft', 'Queued debounced draft save', {
                stage: 'draft',
                substage: 'queued',
                duration_ms: delay || 800,
            });
            this._draftSaveTimer = setTimeout(() => {
                this._draftSaveTimer = null;
                if (this.savingDraft) {
                    this._pendingDraftSave = true;
                    this._pendingDraftSilent = true;
                    this.queueAutoSaveDraft(300);
                    return;
                }
                this.autoSaveDraft();
            }, delay || 800);
        },

        async _waitForDraftIdle(maxMs = 10000) {
            const startedAt = Date.now();

            while (this.savingDraft && (Date.now() - startedAt) < maxMs) {
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            return !this.savingDraft;
        },

        viewPhotoInfo(idx) {
            this.viewingPhotoIdx = idx;
            const ps = this.photoSuggestions[idx];
            if (!ps) return;
            // Generate suggested filename if not already set
            if (!ps.suggestedFilename) {
                const ext = (ps.autoPhoto?.url_full || '').split('.').pop()?.split('?')[0] || 'jpg';
                ps.suggestedFilename = this._slugify(ps.alt_text || ps.search_term) + '.' + ext;
            }
            this.$nextTick(() => {
                document.querySelector('[data-photo-modal]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
            });
        },

        confirmPhoto(idx) {
            const ps = this.photoSuggestions[idx];
            if (!ps || !ps.autoPhoto) return;
            const editor = tinymce.get('spin-preview-editor');
            if (!editor) return;
            const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
            if (!placeholder) return;
            const photo = ps.autoPhoto;
            const altText = (ps.alt_text || '').replace(/"/g, '&quot;');
            const caption = ps.caption || '';
            const imgUrl = this.resolvePhotoLargeUrl(photo) || this.resolvePhotoThumbUrl(photo);
            const figureHtml = '<figure class="wp-block-image"><img src="' + imgUrl + '" alt="' + altText + '" style="max-width:100%;height:auto">' + (caption ? '<figcaption>' + this._escHtml(caption) + '</figcaption>' : '') + '</figure>';
            editor.dom.setOuterHTML(placeholder, figureHtml);
            this.photoSuggestions[idx].confirmed = true;
            this.syncEditorStateFromEditor();
            this.syncDeferredEnrichmentState('inline_photo_confirmed');
            this.queueAutoSaveDraft(300);
            this.viewingPhotoIdx = null;
        },

        changePhoto(idx) {
            this._photoSuggestionIdx = idx;
            if (!this.expandedSuggestions.includes(idx)) this.expandedSuggestions.push(idx);
            this.$nextTick(() => {
                document.querySelector('[data-photo-section]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
            });
        },

        removePhotoPlaceholder(idx) {
            const editor = tinymce.get('spin-preview-editor');
            if (editor) {
                const placeholder = editor.getBody().querySelector('.photo-placeholder[data-idx="' + idx + '"]');
                if (placeholder) editor.dom.remove(placeholder);
            }
            this.photoSuggestions[idx].removed = true;
            this.syncEditorStateFromEditor();
            this.syncDeferredEnrichmentState('inline_photo_removed');
            this.queueAutoSaveDraft(300);
        },

        async searchPhotosForSuggestion(idx) {
            const ps = this.photoSuggestions[idx];
            if (!ps || !ps.search_term.trim()) return;
            this.photoSuggestions[idx].searching = true;
            this.photoSuggestions[idx].loadError = '';
            try {
                const resp = await fetch('{{ route("publish.search.images.post") }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ query: ps.search_term, per_page: 20, draft_id: this.draftId || null })
                });
                const data = await resp.json();
                this.photoSuggestions[idx].searchResults = data.data?.photos || [];
                if (!this.photoSuggestions[idx].searchResults.length) {
                    this.photoSuggestions[idx].loadError = data.message || 'No photos found';
                }
            } catch (e) {
                this.photoSuggestions[idx].searchResults = [];
                this.photoSuggestions[idx].loadError = e.message || 'Search failed';
            }
            this.photoSuggestions[idx].loadAttempted = true;
            this.photoSuggestions[idx].searching = false;
        },

        applyPhotoSuggestionSelection(idx, photo, options = {}) {
            if (!this.photoSuggestions[idx]) return;

            const normalizedPhoto = this.sanitizePhotoAssetForPersistence(photo);
            if (!normalizedPhoto) return;

            this.photoSuggestions.splice(idx, 1, {
                ...this.photoSuggestions[idx],
                autoPhoto: normalizedPhoto,
                confirmed: false,
                loadAttempted: true,
                loadError: '',
                alt_text: normalizedPhoto.alt || '',
                caption: '',
                suggestedFilename: 'auto',
                metaGenerator: '',
            });
            this.setPhotoThumbPending(idx);
            this.updatePlaceholderInEditor(idx);
            this.syncDeferredEnrichmentState('inline_photo_selected');
            this.queueAutoSaveDraft(300);

            if (options.refreshMeta !== false) {
                this.$nextTick(() => setTimeout(() => this.refreshPhotoMeta(idx), 75));
            }
        },

        applyFeaturedPhotoSelection(photo, options = {}) {
            const normalizedPhoto = this.sanitizePhotoAssetForPersistence(photo);
            if (!normalizedPhoto) return;

            this.featuredPhoto = normalizedPhoto;
            this.setFeaturedThumbPending();
            this.featuredAlt = normalizedPhoto.alt || '';
            this.featuredCaption = '';
            this.featuredFilename = 'auto';
            this.featuredMetaGenerator = '';
            this.featuredSearchPending = false;
            this.syncDeferredEnrichmentState('featured_photo_selected');
            this.queueAutoSaveDraft(300);

            if (options.refreshMeta !== false) {
                this.$nextTick(() => setTimeout(() => this.refreshFeaturedMeta(), 75));
            }
        },

        resetFeaturedPhotoSelection() {
            this.featuredPhoto = null;
            this.featuredAlt = '';
            this.featuredCaption = '';
            this.featuredFilename = '';
            this.featuredMetaGenerator = '';
            this.featuredThumbLoading = false;
            this.featuredThumbError = '';
            this.featuredSearchPending = !!this.featuredImageSearch;
            this.syncDeferredEnrichmentState('featured_photo_removed');
            this.queueAutoSaveDraft(300);
        },

        selectPhotoForSuggestion(idx, photo) {
            this.applyPhotoSuggestionSelection(idx, photo);
        },

        async loadSmartEdits() {
            if (this.smartEditTemplates.length > 0) return;
            try {
                const resp = await fetch('{{ route("publish.smart-edits.index") }}?format=json', { headers: { 'Accept': 'application/json' } });
                this.smartEditTemplates = await resp.json();
            } catch (e) { this.smartEditTemplates = []; }
        },

        appendSmartEdit(tpl) {
            if (this.appliedSmartEdits.includes(tpl.id)) {
                // Remove it
                this.appliedSmartEdits = this.appliedSmartEdits.filter(id => id !== tpl.id);
                this.spinChangeRequest = this.spinChangeRequest.replace('[' + tpl.name + '] ' + tpl.prompt + '\n', '');
            } else {
                // Append it
                this.appliedSmartEdits.push(tpl.id);
                this.spinChangeRequest = this.spinChangeRequest + '[' + tpl.name + '] ' + tpl.prompt + '\n';
            }
        },

        @include('app-publish::publishing.pipeline.partials.pipeline-operations-script')
