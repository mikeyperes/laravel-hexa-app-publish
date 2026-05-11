@once
<script>
window.draftApprovalEmailMixin = window.draftApprovalEmailMixin || function draftApprovalEmailMixin(config = {}) {
    return {
        approvalEmailOpen: false,
        approvalEmailLoading: false,
        approvalEmailPreviewLoading: false,
        approvalEmailSending: false,
        approvalEmailTestSending: false,
        approvalEmailLogsLoading: false,
        approvalEmailLoadedDraftId: null,
        approvalEmailTargetId: Number(config.articleId || 0) || null,
        approvalEmailArticle: null,
        approvalEmailStatus: '',
        approvalEmailStatusType: 'info',
        approvalEmailToTouched: false,
        approvalEmailCcTouched: false,
        approvalEmailTo: '',
        approvalEmailCc: '',
        approvalEmailFromName: 'Scale My Publication',
        approvalEmailFromEmail: 'no-reply@scalemypublication.com',
        approvalEmailReplyTo: 'support@scalemypublication.com',
        approvalEmailSubject: '',
        approvalEmailIntroHtml: '',
        approvalEmailImageMode: 'embed',
        approvalEmailAdditionalCcs: '',
        approvalEmailSmtpSettingsUrl: @json(route('settings.smtp-accounts.index')),
        approvalEmailPreviewHtml: '',
        approvalEmailWarnings: [],
        approvalEmailHeaders: {},
        approvalEmailSnapshot: {},
        approvalEmailLogs: [],
        approvalEmailSuperAdminEmail: 'michael@mike-ro-tech.com',
        approvalEmailIntroEditorId: 'approval-email-intro-' + Math.random().toString(36).slice(2),
        approvalEmailTestTo: "michael@mike-ro-tech.com",
        _approvalEmailTinyMcePromise: null,

        approvalEmailRequestHeaders(extra = {}) {
            const base = typeof this.requestHeaders === "function"
                ? this.requestHeaders({ "Content-Type": "application/json" })
                : {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]")?.content || this.csrfToken || "",
                };

            return { ...base, ...extra };
        },

        async approvalEmailRefreshCsrfToken() {
            try {
                const response = await fetch(window.location.href, {
                    method: "GET",
                    credentials: "same-origin",
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                });
                const html = await response.text();
                const match = html.match(/<meta\s+name=\"csrf-token\"\s+content=\"([^\"]+)\"/i);
                const token = match?.[1] || document.querySelector("meta[name=csrf-token]")?.content || "";
                if (token) {
                    const meta = document.querySelector("meta[name=csrf-token]");
                    if (meta) meta.setAttribute("content", token);
                }
                return token;
            } catch (error) {
                return document.querySelector("meta[name=csrf-token]")?.content || this.csrfToken || "";
            }
        },

        async approvalEmailFetchJson(url, init = {}, options = {}) {
            const fetcher = typeof this._rawPipelineFetch === "function"
                ? this._rawPipelineFetch.bind(this)
                : window.fetch.bind(window);
            let response = await fetcher(url, {
                credentials: "same-origin",
                ...init,
                headers: this.approvalEmailRequestHeaders(init.headers || {}),
            });
            if (response.status === 419 && options.retryOn419 !== false) {
                await this.approvalEmailRefreshCsrfToken();
                response = await fetcher(url, {
                    credentials: "same-origin",
                    ...init,
                    headers: this.approvalEmailRequestHeaders(init.headers || {}),
                });
            }
            const data = await response.json().catch(() => ({}));
            return { response, data };
        },

        syncPublishEmailQueryState() {
            try {
                if (!String(window.location.pathname || "").includes("/article/publish")) return;
                const url = new URL(window.location.href);
                if (this.emailDrawerOpen) {
                    url.searchParams.set("email", this.emailDrawerTab === "notification" ? "notification" : "approval");
                    const articleId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
                    if (articleId) {
                        url.searchParams.set("email_article", String(articleId));
                    } else {
                        url.searchParams.delete("email_article");
                    }
                } else {
                    url.searchParams.delete("email");
                    url.searchParams.delete("email_article");
                }
                window.history.replaceState({}, "", url.toString());
            } catch (error) {}
        },

        restorePublishEmailQueryState() {
            try {
                if (!String(window.location.pathname || "").includes("/article/publish")) return;
                const url = new URL(window.location.href);
                const panel = String(url.searchParams.get("email") || "").trim();
                if (!["approval", "notification"].includes(panel)) return;
                const articleId = Number(url.searchParams.get("email_article") || this.approvalEmailTargetId || this.draftId || 0) || 0;
                this.emailDrawerTab = panel;
                this.emailDrawerOpen = true;
                if (articleId) {
                    this.approvalEmailTargetId = articleId;
                }
                if (panel === "approval") {
                    this.approvalEmailEnsureLoaded?.(true);
                }
            } catch (error) {}
        },

        approvalEmailStatusClass() {
            switch (String(this.approvalEmailStatusType || "info")) {
                case "success":
                    return "border border-green-200 bg-green-50 text-green-700";
                case "info":
                    return "border border-blue-200 bg-blue-50 text-blue-700";
                default:
                    return "border border-red-200 bg-red-50 text-red-700";
            }
        },

        approvalEmailPayload() {
            this.approvalEmailPullIntroEditorHtml();
            return {
                to: String(this.approvalEmailTo || '').trim(),
                cc: String(this.approvalEmailCc || '').trim(),
                to_touched: !!this.approvalEmailToTouched,
                cc_touched: !!this.approvalEmailCcTouched,
                from_name: String(this.approvalEmailFromName || '').trim(),
                from_email: String(this.approvalEmailFromEmail || '').trim(),
                reply_to: String(this.approvalEmailReplyTo || '').trim(),
                subject: String(this.approvalEmailSubject || '').trim(),
                intro_html: String(this.approvalEmailIntroHtml || ''),
                image_mode: String(this.approvalEmailImageMode || 'embed').trim() || 'embed',
            };
        },

        approvalEmailApplyComposer(config = {}, options = {}) {
            const preserveFilled = !!options.preserveFilled;
            const fields = {
                approvalEmailTo: config.to ?? '',
                approvalEmailCc: config.cc ?? '',
                approvalEmailFromName: config.from_name ?? 'Scale My Publication',
                approvalEmailFromEmail: config.from_email ?? 'no-reply@scalemypublication.com',
                approvalEmailReplyTo: config.reply_to ?? 'support@scalemypublication.com',
                approvalEmailSubject: config.subject ?? '',
                approvalEmailIntroHtml: config.intro_html ?? '',
                approvalEmailImageMode: config.image_mode ?? 'embed',
            };

            Object.entries(fields).forEach(([key, value]) => {
                if (preserveFilled && String(this[key] || '').trim() !== '') {
                    return;
                }
                this[key] = String(value ?? '');
            });
            this.approvalEmailAdditionalCcs = String(config.additional_ccs ?? this.approvalEmailAdditionalCcs ?? '');
            this.approvalEmailSmtpSettingsUrl = String(config.smtp_settings_url ?? this.approvalEmailSmtpSettingsUrl ?? '');

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => {
                    this.approvalEmailEnsureIntroEditor();
                    this.approvalEmailSyncIntroEditor();
                });
            }
        },

        approvalEmailSetStatus(type, message) {
            this.approvalEmailStatusType = type;
            this.approvalEmailStatus = message || '';
            if (typeof this.showNotification === 'function' && message) {
                this.showNotification(type === 'success' ? 'success' : (type === 'info' ? 'info' : 'error'), message);
            }
        },

        approvalEmailClearTransientStatus() {
            this.approvalEmailStatusType = 'info';
            this.approvalEmailStatus = '';
        },

        approvalEmailPersistState() {
            try {
                if (this.savePipelineState) {
                    this.savePipelineState();
                }
            } catch (error) {}
        },

        approvalEmailStatusNeedsSmtpSettings() {
            const message = String(this.approvalEmailStatus || '').toLowerCase();
            return this.approvalEmailStatusType === 'error'
                && (message.includes('smtp') || message.includes('mail'));
        },

        approvalEmailImageModeHelp() {
            switch (String(this.approvalEmailImageMode || 'embed')) {
                case 'embed':
                    return 'Embed the featured image and inline images directly in the email body.';
                case 'wordpress':
                    return 'Use the prepared WordPress-hosted assets. Run Prepare first so all inline images are uploaded.';
                default:
                    return 'Replace each image with a clickable link and caption block.';
            }
        },

        approvalEmailLogStatusClass(status) {
            switch (String(status || '').toLowerCase()) {
                case 'sent': return 'bg-blue-50 text-blue-700 border-blue-200';
                case 'viewed': return 'bg-amber-50 text-amber-700 border-amber-200';
                case 'reviewed': return 'bg-green-50 text-green-700 border-green-200';
                case 'failed': return 'bg-red-50 text-red-700 border-red-200';
                default: return 'bg-gray-50 text-gray-700 border-gray-200';
            }
        },

        approvalEmailMountComposer() {
            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => {
                    this.approvalEmailEnsureIntroEditor();
                    this.approvalEmailSyncIntroEditor();
                });
            }
        },

        approvalEmailIntroTextarea() {
            return this.$refs?.approvalEmailIntroEditor || document.getElementById(this.approvalEmailIntroEditorId);
        },

        async approvalEmailEnsureTinyMceLoaded() {
            if (window.tinymce) return true;
            if (this._approvalEmailTinyMcePromise) return this._approvalEmailTinyMcePromise;

            const tinyKey = @json(config('services.tinymce.api_key') ?: 'no-api-key');
            const sources = ['https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js'];
            if (tinyKey && tinyKey !== 'no-api-key') {
                sources.push('https://cdn.tiny.cloud/1/' + tinyKey + '/tinymce/7/tinymce.min.js');
            }

            this._approvalEmailTinyMcePromise = new Promise((resolve) => {
                const trySource = (index) => {
                    if (window.tinymce) {
                        resolve(true);
                        return;
                    }
                    if (index >= sources.length) {
                        resolve(false);
                        return;
                    }
                    const src = sources[index];
                    const existing = Array.from(document.querySelectorAll('script[data-approval-email-tinymce="1"]')).find((node) => node.src === src);
                    if (existing) {
                        existing.addEventListener('load', () => resolve(!!window.tinymce), { once: true });
                        existing.addEventListener('error', () => trySource(index + 1), { once: true });
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = src;
                    script.referrerPolicy = 'origin';
                    script.dataset.approvalEmailTinymce = '1';
                    script.onload = () => resolve(!!window.tinymce);
                    script.onerror = () => trySource(index + 1);
                    document.head.appendChild(script);
                };
                trySource(0);
            }).finally(() => {
                this._approvalEmailTinyMcePromise = null;
            });

            return this._approvalEmailTinyMcePromise;
        },

        async approvalEmailEnsureIntroEditor() {
            const textarea = this.approvalEmailIntroTextarea();
            if (!textarea) return false;
            const ready = await this.approvalEmailEnsureTinyMceLoaded();
            if (!ready || !window.tinymce) {
                textarea.value = String(this.approvalEmailIntroHtml || '');
                return false;
            }

            const existing = window.tinymce.get(this.approvalEmailIntroEditorId);
            if (existing) {
                this.approvalEmailSyncIntroEditor();
                return true;
            }

            await window.tinymce.init({
                target: textarea,
                menubar: false,
                branding: false,
                promotion: false,
                min_height: 360,
                height: 360,
                plugins: 'link lists code autoresize',
                toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | removeformat | code',
                block_formats: 'Paragraph=p;Heading 2=h2;Heading 3=h3',
                resize: true,
                autoresize_min_height: 360,
                autoresize_max_height: 960,
                autoresize_bottom_margin: 12,
                setup: (editor) => {
                    editor.on('init', () => {
                        editor.setContent(String(this.approvalEmailIntroHtml || ''));
                    });
                    const sync = () => {
                        this.approvalEmailIntroHtml = editor.getContent({ format: 'html' }) || '';
                    };
                    editor.on('change input undo redo keyup setcontent', sync);
                },
            });

            this.approvalEmailSyncIntroEditor();
            return true;
        },

        approvalEmailPullIntroEditorHtml() {
            const editor = window.tinymce?.get(this.approvalEmailIntroEditorId);
            if (editor) {
                this.approvalEmailIntroHtml = editor.getContent({ format: 'html' }) || '';
                return this.approvalEmailIntroHtml;
            }
            const textarea = this.approvalEmailIntroTextarea();
            if (textarea) {
                this.approvalEmailIntroHtml = textarea.value || '';
            }
            return this.approvalEmailIntroHtml;
        },

        approvalEmailSyncIntroEditor() {
            const next = String(this.approvalEmailIntroHtml || '');
            const editor = window.tinymce?.get(this.approvalEmailIntroEditorId);
            if (editor) {
                const current = editor.getContent({ format: 'html' }) || '';
                if (current !== next) {
                    editor.setContent(next);
                }
                return;
            }
            const textarea = this.approvalEmailIntroTextarea();
            if (textarea && textarea.value !== next) {
                textarea.value = next;
            }
        },

        approvalEmailAppendCcAddress(email) {
            const candidate = String(email || '').trim();
            if (!candidate) return;
            const list = String(this.approvalEmailCc || '')
                .split(',')
                .map((value) => value.trim())
                .filter((value) => value !== '');
            const lower = list.map((value) => value.toLowerCase());
            if (!lower.includes(candidate.toLowerCase())) {
                list.push(candidate);
                this.approvalEmailCc = list.join(', ');
                this.approvalEmailCcTouched = true;
                this.approvalEmailPersistState();
            }
        },

        approvalEmailAppendCcList(value) {
            const candidates = String(value || '')
                .split(',')
                .map((entry) => entry.trim())
                .filter((entry) => entry !== '');
            if (!candidates.length) return;
            const list = String(this.approvalEmailCc || '')
                .split(',')
                .map((entry) => entry.trim())
                .filter((entry) => entry !== '');
            const lower = list.map((entry) => entry.toLowerCase());
            for (const candidate of candidates) {
                if (!lower.includes(candidate.toLowerCase())) {
                    list.push(candidate);
                    lower.push(candidate.toLowerCase());
                }
            }
            this.approvalEmailCc = list.join(', ');
            this.approvalEmailCcTouched = true;
            this.approvalEmailPersistState();
        },

        async approvalEmailLoad(articleId = null, options = {}) {
            const targetId = Number(articleId || this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return false;

            this.approvalEmailTargetId = targetId;
            this.approvalEmailLoading = true;
            this.approvalEmailClearTransientStatus();

            try {
                const { response, data } = await this.approvalEmailFetchJson("/article/articles/" + targetId + "/approval-email", {
                    method: "GET",
                }, { retryOn419: false });
                if (!response.ok || !data.success) {
                    throw new Error(data.message || "Failed to load draft approval email state.");
                }

                this.approvalEmailArticle = data.article || null;
                this.approvalEmailLogs = Array.isArray(data.logs) ? data.logs : [];
                this.approvalEmailLoadedDraftId = targetId;
                this.approvalEmailApplyComposer(data.composer || {}, { preserveFilled: !!options.preserveFilled });
                if (!String(this.approvalEmailTestTo || "").trim()) {
                    this.approvalEmailTestTo = this.approvalEmailSuperAdminEmail;
                }
                await this.approvalEmailEnsureIntroEditor();
                this.approvalEmailSyncIntroEditor();
                if (options.open !== false) this.approvalEmailOpen = true;
                return true;
            } catch (error) {
                this.approvalEmailSetStatus("error", error?.message || "Failed to load draft approval email state.");
                return false;
            } finally {
                this.approvalEmailLoading = false;
            }
        },

        async approvalEmailEnsureLoaded(preserveFilled = true) {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return false;
            if (this.approvalEmailLoadedDraftId === targetId) {
                this.approvalEmailClearTransientStatus();
                return true;
            }
            return this.approvalEmailLoad(targetId, { preserveFilled, open: false });
        },

        async approvalEmailRefreshLogs() {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return false;

            this.approvalEmailLogsLoading = true;
            try {
                const { response, data } = await this.approvalEmailFetchJson("/article/articles/" + targetId + "/approval-email/logs", {
                    method: "GET",
                }, { retryOn419: false });
                if (!response.ok || !data.success) {
                    throw new Error(data.message || "Failed to refresh approval email logs.");
                }
                this.approvalEmailLogs = Array.isArray(data.logs) ? data.logs : [];
                return true;
            } catch (error) {
                this.approvalEmailSetStatus("error", error?.message || "Failed to refresh approval email logs.");
                return false;
            } finally {
                this.approvalEmailLogsLoading = false;
            }
        },

        async approvalEmailPreview() {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return;
            if (typeof this.flushPipelineStateNow === "function") {
                const saved = await this.flushPipelineStateNow();
                if (!saved) {
                    this.approvalEmailSetStatus("error", "Draft state failed to save before preview.");
                    return;
                }
            }

            this.approvalEmailPreviewLoading = true;
            this.approvalEmailSetStatus("info", "Rendering approval email preview…");
            try {
                const { response, data } = await this.approvalEmailFetchJson("/article/articles/" + targetId + "/approval-email/preview", {
                    method: "POST",
                    body: JSON.stringify(this.approvalEmailPayload()),
                });
                if (!response.ok || !data.success) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat().join(" ") || "Preview failed.");
                }
                this.approvalEmailApplyComposer(data.composer || {}, { preserveFilled: false });
                this.approvalEmailPreviewHtml = data.preview_html || "";
                this.approvalEmailWarnings = Array.isArray(data.warnings) ? data.warnings : [];
                this.approvalEmailHeaders = data.headers || {};
                this.approvalEmailSnapshot = data.snapshot || {};
                this.approvalEmailSetStatus("success", "Approval email preview rendered.");
            } catch (error) {
                this.approvalEmailSetStatus("error", error?.message || "Approval email preview failed.");
            } finally {
                this.approvalEmailPreviewLoading = false;
            }
        },

        approvalEmailTestPromptOpen: false,

        approvalEmailOpenTestPrompt() {
            if (!this.approvalEmailTestTo || String(this.approvalEmailTestTo).trim() === "") {
                this.approvalEmailTestTo = this.approvalEmailSuperAdminEmail || "michael@mike-ro-tech.com";
            }
            this.approvalEmailTestPromptOpen = true;
            this.$nextTick(() => {
                if (this.$refs.approvalEmailTestInput) {
                    this.$refs.approvalEmailTestInput.focus();
                    this.$refs.approvalEmailTestInput.select();
                }
            });
        },

        async approvalEmailSubmitTest() {
            const ok = await this.approvalEmailSendTest();
            if (ok) this.approvalEmailTestPromptOpen = false;
        },

        async approvalEmailSendTest() {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            const testTo = String(this.approvalEmailTestTo || this.approvalEmailSuperAdminEmail || "").trim();
            if (!targetId) return;
            if (!testTo) {
                this.approvalEmailSetStatus("error", "Enter a valid test recipient email address.");
                return;
            }
            if (typeof this.flushPipelineStateNow === "function") {
                const saved = await this.flushPipelineStateNow();
                if (!saved) {
                    this.approvalEmailSetStatus("error", "Draft state failed to save before test send.");
                    return;
                }
            }

            this.approvalEmailTestSending = true;
            this.approvalEmailSetStatus("info", "Sending test email to " + testTo + "…");
            try {
                const { response, data } = await this.approvalEmailFetchJson("/article/articles/" + targetId + "/approval-email/send", {
                    method: "POST",
                    body: JSON.stringify({ ...this.approvalEmailPayload(), test_mode: true, test_to: testTo }),
                });
                if (!response.ok || !data.success) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat().join(" ") || "Test send failed.");
                }
                this.approvalEmailLogs = Array.isArray(data.logs) ? data.logs : this.approvalEmailLogs;
                if (data.email?.preview_html) this.approvalEmailPreviewHtml = data.email.preview_html;
                this.approvalEmailSetStatus("success", data.message || "Draft approval test email sent.");
                return true;
            } catch (error) {
                this.approvalEmailSetStatus("error", error?.message || "Draft approval test email failed.");
                return false;
            } finally {
                this.approvalEmailTestSending = false;
            }
        },

        async approvalEmailSend() {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return;
            if (typeof this.flushPipelineStateNow === "function") {
                const saved = await this.flushPipelineStateNow();
                if (!saved) {
                    this.approvalEmailSetStatus("error", "Draft state failed to save before sending.");
                    return;
                }
            }

            this.approvalEmailSending = true;
            this.approvalEmailSetStatus("info", "Sending draft approval email…");
            try {
                const { response, data } = await this.approvalEmailFetchJson("/article/articles/" + targetId + "/approval-email/send", {
                    method: "POST",
                    body: JSON.stringify(this.approvalEmailPayload()),
                });
                if (!response.ok || !data.success) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat().join(" ") || "Send failed.");
                }
                this.approvalEmailLogs = Array.isArray(data.logs) ? data.logs : this.approvalEmailLogs;
                if (data.email?.preview_html) this.approvalEmailPreviewHtml = data.email.preview_html;
                this.approvalEmailSetStatus("success", data.message || "Draft approval email sent.");
            } catch (error) {
                this.approvalEmailSetStatus("error", error?.message || "Draft approval email failed.");
            } finally {
                this.approvalEmailSending = false;
            }
        },
    };
};
</script>
@endonce
