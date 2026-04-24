        // ── Draft ─────────────────────────────────────────
        async saveDraftNow(silent = false) {
            if (this._draftSaveTimer) {
                clearTimeout(this._draftSaveTimer);
                this._draftSaveTimer = null;
            }
            if (this.savingDraft) {
                this._pendingDraftSave = true;
                this._pendingDraftSilent = this._pendingDraftSave ? (this._pendingDraftSilent && silent) : silent;
                this._logDebug('draft', 'Draft save requested while another save is in flight', {
                    stage: 'draft',
                    substage: 'queued_while_saving',
                });
                if (silent) return;

                const idle = await this._waitForDraftIdle();
                if (!idle) {
                    this._logActivity('draft', 'error', 'Draft save wait timed out', {
                        stage: 'draft',
                        substage: 'wait_timeout',
                    });
                    if (!silent) this.showNotification('error', 'Draft save is still in progress');
                    return;
                }
                this._pendingDraftSave = false;
                this._pendingDraftSilent = true;
            }

            this.savingDraft = true;
            const draftBody = this.resolveDraftBodyForSave();
            const payload = {
                draft_id: this.draftId,
                title: this.articleTitle || 'Untitled Pipeline Draft',
                body: draftBody.body,
                editor_ready: draftBody.editorReady,
                excerpt: this.articleDescription || null,
                user_id: this.selectedUser?.id || null,
                site_id: this.selectedSite?.id || null,
                preset_id: this.selectedPresetId || null,
                template_id: this.selectedTemplateId || null,
                article_type: this.currentArticleType || null,
                ai_model: this.aiModel,
                author: this.publishAuthor || null,
                publish_action: this.publishAction || null,
                schedule_date: this.publishAction === 'future' ? (this.scheduleDate || null) : null,
                sources: this.sources.map(s => ({ url: s.url, title: s.title })),
                tags: this.selectedTagNames(),
                categories: this.selectedCategoryNames(),
                photo_suggestions: this.sanitizePhotoSuggestionsForPersistence() || null,
                featured_image_search: this.featuredImageSearch || null,
            };
            const signature = this._stableSignature(payload);
            if (signature === this._lastDraftPayloadSignature) {
                this.savingDraft = false;
                this._logDebug('draft', 'Skipped draft save (payload unchanged)', {
                    stage: 'draft',
                    substage: 'skip_unchanged',
                });
                return;
            }

            const startedAt = typeof performance !== 'undefined' ? performance.now() : Date.now();
            this._logActivity('draft', 'info', silent ? 'Auto-saving draft' : 'Saving draft', {
                stage: 'draft',
                substage: silent ? 'auto_start' : 'manual_start',
                payload_preview: this.pipelineDebugEnabled ? this._summarizeValue(payload, 1200) : '',
                debug_only: !this.pipelineDebugEnabled,
            });

            if (!silent) {
                const stateSaved = await this.flushPipelineStateNow();
                if (!stateSaved) {
                    this.savingDraft = false;
                    this._logActivity('draft', 'error', 'Draft settings failed to save before draft persistence.', {
                        stage: 'draft',
                        substage: 'state_flush_failed',
                    });
                    this.showNotification('error', 'Draft settings could not be saved. Please try again.');
                    return;
                }
            }

            try {
                const resp = await this._rawPipelineFetch('{{ route('publish.pipeline.save-draft') }}', {
                    method: 'POST',
                    headers: this.requestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify(payload)
                });
                const data = await resp.json().catch(() => ({}));
                if (resp.status === 409 && data.code === 'draft_session_conflict') {
                    this._handleDraftSessionConflict('draft', data, { silent });
                    this.savingDraft = false;
                    return;
                }
                if (data.success) {
                    this.draftId = data.draft_id;
                    this._clearDraftSessionConflict();
                    this._lastDraftPayloadSignature = signature;
                    this._logActivity('draft', 'success', data.message, {
                        stage: 'draft',
                        substage: 'saved',
                        status: resp.status,
                        duration_ms: Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt)),
                    });
                    if (!silent) this.showNotification('success', data.message);
                } else {
                    this._logActivity('draft', 'error', data.message || 'Draft save failed', {
                        stage: 'draft',
                        substage: 'response_error',
                        status: resp.status,
                        duration_ms: Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt)),
                    });
                    if (!silent) this.showNotification('error', data.message);
                }
            } catch (e) {
                this._logActivity('draft', 'error', 'Failed to save draft: ' + (e.message || 'Request failed'), {
                    stage: 'draft',
                    substage: 'exception',
                    duration_ms: Math.round(((typeof performance !== 'undefined' ? performance.now() : Date.now()) - startedAt)),
                });
                if (!silent) this.showNotification('error', 'Failed to save draft');
            }
            this.savingDraft = false;

            if (this._pendingDraftSave) {
                const followUpSilent = this._pendingDraftSilent;
                this._pendingDraftSave = false;
                this._pendingDraftSilent = true;
                this._logDebug('draft', 'Running queued follow-up draft save', {
                    stage: 'draft',
                    substage: 'follow_up',
                });
                this.queueAutoSaveDraft(followUpSilent ? 200 : 50);
            }
        },

        autoSaveDraft() {
            if (this._draftSessionConflictActive) return;
            if (this._suspendDraftAutoSave) {
                this._pendingPostSuspendDraftSave = true;
                return;
            }
            if (this.savingDraft) {
                this._pendingDraftSave = true;
                this._pendingDraftSilent = true;
                this._logDebug('draft', 'Auto-save requested while draft save is active; queued follow-up save', {
                    stage: 'draft',
                    substage: 'auto_deferred',
                });
                return;
            }
            this.saveDraftNow(true);
        },

        // ── Notifications ─────────────────────────────────
        showNotification(type, message) {
            this.notification = { show: true, type, message };
            this._logActivity('ui', type, message, { debug_only: type === 'success' });
            // Errors stay permanently, success fades after 8 seconds
            if (type === 'success') {
                setTimeout(() => { this.notification.show = false; }, 8000);
            }
            // Errors stay until user dismisses or next action
        },
