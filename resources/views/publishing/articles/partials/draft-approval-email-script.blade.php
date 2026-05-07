@once
<script>
window.draftApprovalEmailMixin = window.draftApprovalEmailMixin || function draftApprovalEmailMixin(config = {}) {
    return {
        approvalEmailOpen: false,
        approvalEmailLoading: false,
        approvalEmailPreviewLoading: false,
        approvalEmailSending: false,
        approvalEmailLogsLoading: false,
        approvalEmailLoadedDraftId: null,
        approvalEmailTargetId: Number(config.articleId || 0) || null,
        approvalEmailArticle: null,
        approvalEmailStatus: '',
        approvalEmailStatusType: 'info',
        approvalEmailTo: '',
        approvalEmailCc: '',
        approvalEmailFromName: 'Scale My Publication',
        approvalEmailFromEmail: 'no-reply@scalemypublication.com',
        approvalEmailReplyTo: 'support@scalemypublication.com',
        approvalEmailSubject: '',
        approvalEmailImageMode: 'links',
        approvalEmailPreviewHtml: '',
        approvalEmailWarnings: [],
        approvalEmailHeaders: {},
        approvalEmailSnapshot: {},
        approvalEmailLogs: [],

        approvalEmailRequestHeaders(extra = {}) {
            const base = typeof this.requestHeaders === 'function'
                ? this.requestHeaders({ 'Content-Type': 'application/json' })
                : {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                };

            return { ...base, ...extra };
        },

        approvalEmailPayload() {
            return {
                to: String(this.approvalEmailTo || '').trim(),
                cc: String(this.approvalEmailCc || '').trim(),
                from_name: String(this.approvalEmailFromName || '').trim(),
                from_email: String(this.approvalEmailFromEmail || '').trim(),
                reply_to: String(this.approvalEmailReplyTo || '').trim(),
                subject: String(this.approvalEmailSubject || '').trim(),
                image_mode: String(this.approvalEmailImageMode || 'links').trim() || 'links',
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
                approvalEmailImageMode: config.image_mode ?? 'links',
            };

            Object.entries(fields).forEach(([key, value]) => {
                if (preserveFilled && String(this[key] || '').trim() !== '') {
                    return;
                }
                this[key] = String(value ?? '');
            });
        },

        approvalEmailSetStatus(type, message) {
            this.approvalEmailStatusType = type;
            this.approvalEmailStatus = message || '';
            if (typeof this.showNotification === 'function' && message) {
                this.showNotification(type === 'success' ? 'success' : 'error', message);
            }
        },

        approvalEmailImageModeHelp() {
            switch (String(this.approvalEmailImageMode || 'links')) {
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

        async approvalEmailLoad(articleId = null, options = {}) {
            const targetId = Number(articleId || this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return false;

            this.approvalEmailTargetId = targetId;
            this.approvalEmailLoading = true;
            this.approvalEmailSetStatus('info', '');

            try {
                const response = await fetch(`/article/articles/${targetId}/approval-email`, {
                    method: 'GET',
                    headers: this.approvalEmailRequestHeaders(),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to load draft approval email state.');
                }

                this.approvalEmailArticle = data.article || null;
                this.approvalEmailLogs = Array.isArray(data.logs) ? data.logs : [];
                this.approvalEmailLoadedDraftId = targetId;
                this.approvalEmailApplyComposer(data.composer || {}, { preserveFilled: !!options.preserveFilled });
                if (options.open !== false) this.approvalEmailOpen = true;
                return true;
            } catch (error) {
                this.approvalEmailSetStatus('error', error?.message || 'Failed to load draft approval email state.');
                return false;
            } finally {
                this.approvalEmailLoading = false;
            }
        },

        async approvalEmailEnsureLoaded(preserveFilled = true) {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return false;
            if (this.approvalEmailLoadedDraftId === targetId) return true;
            return this.approvalEmailLoad(targetId, { preserveFilled, open: false });
        },

        async approvalEmailRefreshLogs() {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return false;

            this.approvalEmailLogsLoading = true;
            try {
                const response = await fetch(`/article/articles/${targetId}/approval-email/logs`, {
                    method: 'GET',
                    headers: this.approvalEmailRequestHeaders(),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to refresh approval email logs.');
                }
                this.approvalEmailLogs = Array.isArray(data.logs) ? data.logs : [];
                return true;
            } catch (error) {
                this.approvalEmailSetStatus('error', error?.message || 'Failed to refresh approval email logs.');
                return false;
            } finally {
                this.approvalEmailLogsLoading = false;
            }
        },

        async approvalEmailPreview() {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return;
            if (typeof this.flushPipelineStateNow === 'function') {
                const saved = await this.flushPipelineStateNow();
                if (!saved) {
                    this.approvalEmailSetStatus('error', 'Draft state failed to save before preview.');
                    return;
                }
            }

            this.approvalEmailPreviewLoading = true;
            this.approvalEmailSetStatus('info', 'Rendering approval email preview…');
            try {
                const response = await fetch(`/article/articles/${targetId}/approval-email/preview`, {
                    method: 'POST',
                    headers: this.approvalEmailRequestHeaders(),
                    body: JSON.stringify(this.approvalEmailPayload()),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Preview failed.');
                }
                this.approvalEmailApplyComposer(data.composer || {}, { preserveFilled: false });
                this.approvalEmailPreviewHtml = data.preview_html || '';
                this.approvalEmailWarnings = Array.isArray(data.warnings) ? data.warnings : [];
                this.approvalEmailHeaders = data.headers || {};
                this.approvalEmailSnapshot = data.snapshot || {};
                this.approvalEmailSetStatus('success', 'Approval email preview rendered.');
            } catch (error) {
                this.approvalEmailSetStatus('error', error?.message || 'Approval email preview failed.');
            } finally {
                this.approvalEmailPreviewLoading = false;
            }
        },

        async approvalEmailSend() {
            const targetId = Number(this.approvalEmailTargetId || this.draftId || 0) || 0;
            if (!targetId) return;
            if (typeof this.flushPipelineStateNow === 'function') {
                const saved = await this.flushPipelineStateNow();
                if (!saved) {
                    this.approvalEmailSetStatus('error', 'Draft state failed to save before sending.');
                    return;
                }
            }

            this.approvalEmailSending = true;
            this.approvalEmailSetStatus('info', 'Sending draft approval email…');
            try {
                const response = await fetch(`/article/articles/${targetId}/approval-email/send`, {
                    method: 'POST',
                    headers: this.approvalEmailRequestHeaders(),
                    body: JSON.stringify(this.approvalEmailPayload()),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Send failed.');
                }
                this.approvalEmailLogs = Array.isArray(data.logs) ? data.logs : this.approvalEmailLogs;
                if (data.email?.preview_html) this.approvalEmailPreviewHtml = data.email.preview_html;
                this.approvalEmailSetStatus('success', data.message || 'Draft approval email sent.');
            } catch (error) {
                this.approvalEmailSetStatus('error', error?.message || 'Draft approval email failed.');
            } finally {
                this.approvalEmailSending = false;
            }
        },
    };
};
</script>
@endonce
