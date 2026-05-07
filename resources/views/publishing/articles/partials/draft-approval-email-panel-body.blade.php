<div class="space-y-5">
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">To</label>
                <input type="email" x-model="approvalEmailTo" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="client@example.com">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">CC</label>
                <input type="text" x-model="approvalEmailCc" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="team@example.com, account@example.com">
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">From name</label>
                    <input type="text" x-model="approvalEmailFromName" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">From email</label>
                    <input type="email" x-model="approvalEmailFromEmail" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Reply-To</label>
                <input type="email" x-model="approvalEmailReplyTo" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Subject</label>
                <input type="text" x-model="approvalEmailSubject" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Image handling</label>
                <select x-model="approvalEmailImageMode" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white">
                    <option value="links">Clickable image links + captions</option>
                    <option value="embed">Embed images directly</option>
                    <option value="wordpress">Use prepared WordPress-hosted images</option>
                </select>
                <p class="mt-2 text-xs text-gray-500" x-text="approvalEmailImageModeHelp()"></p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 space-y-2 text-xs text-gray-600">
                <p class="font-semibold text-gray-700">Draft email defaults</p>
                <p><span class="font-medium text-gray-700">From:</span> <span x-text="approvalEmailFromName || 'Scale My Publication'"></span> &lt;<span x-text="approvalEmailFromEmail || 'no-reply@scalemypublication.com'"></span>&gt;</p>
                <p><span class="font-medium text-gray-700">Reply-To:</span> <span x-text="approvalEmailReplyTo || 'support@scalemypublication.com'"></span></p>
                <p><span class="font-medium text-gray-700">Mode:</span> <span x-text="approvalEmailImageMode"></span></p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="approvalEmailLoad(approvalEmailTargetId || draftId, { preserveFilled: true, open: false })" :disabled="approvalEmailLoading" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    Refresh Draft Data
                </button>
                <button type="button" @click="approvalEmailPreview()" :disabled="approvalEmailPreviewLoading || approvalEmailSending" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50">
                    <svg x-show="approvalEmailPreviewLoading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="approvalEmailPreviewLoading ? 'Rendering Preview…' : 'Preview Email'"></span>
                </button>
                <button type="button" @click="approvalEmailSend()" :disabled="approvalEmailSending || approvalEmailPreviewLoading" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    <svg x-show="approvalEmailSending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="approvalEmailSending ? 'Sending…' : 'Send Draft Email'"></span>
                </button>
            </div>

            <div x-show="approvalEmailStatus" x-cloak class="rounded-lg px-3 py-2 text-sm" :class="approvalEmailStatusType === 'success' ? 'border border-green-200 bg-green-50 text-green-700' : 'border border-red-200 bg-red-50 text-red-700'" x-text="approvalEmailStatus"></div>
        </div>
    </div>

    <div x-show="approvalEmailWarnings.length > 0" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <p class="font-semibold mb-2">Preview Warnings</p>
        <ul class="list-disc space-y-1 pl-5">
            <template x-for="warning in approvalEmailWarnings" :key="warning">
                <li x-text="warning"></li>
            </template>
        </ul>
    </div>

    <div x-show="approvalEmailPreviewHtml" x-cloak class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-800">Email Preview</p>
                <p class="text-xs text-gray-500">Header debug + rendered email body exactly as it will be sent.</p>
            </div>
            <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-600" x-text="approvalEmailImageMode"></span>
        </div>
        <div class="p-4 max-w-none prose prose-sm" x-html="approvalEmailPreviewHtml"></div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-800">Approval Email Log</p>
                <p class="text-xs text-gray-500">Per-draft send history, headers, provider account, review status, and body snapshots.</p>
            </div>
            <button type="button" @click="approvalEmailRefreshLogs()" :disabled="approvalEmailLogsLoading" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                <svg x-show="approvalEmailLogsLoading" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Refresh Logs
            </button>
        </div>

        <div class="divide-y divide-gray-200" x-show="approvalEmailLogs.length > 0" x-cloak>
            <template x-for="log in approvalEmailLogs" :key="log.id">
                <details class="group px-4 py-4">
                    <summary class="cursor-pointer list-none">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="font-semibold text-gray-900" x-text="log.subject"></span>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-medium" :class="approvalEmailLogStatusClass(log.status)" x-text="log.status_label || log.status"></span>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                                    <span><span class="font-medium text-gray-700">To:</span> <span x-text="log.to || '—'"></span></span>
                                    <span><span class="font-medium text-gray-700">CC:</span> <span x-text="log.cc || '—'"></span></span>
                                    <span><span class="font-medium text-gray-700">Sent:</span> <span x-text="log.sent_at || 'Not sent'"></span></span>
                                    <span><span class="font-medium text-gray-700">Viewed:</span> <span x-text="log.viewed_at || 'Not viewed'"></span></span>
                                    <span><span class="font-medium text-gray-700">Reviewed:</span> <span x-text="log.reviewed_at || 'Not reviewed'"></span></span>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <a :href="log.public_review_url" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 font-medium text-blue-700 hover:bg-blue-100">Open Review Page</a>
                            </div>
                        </div>
                    </summary>
                    <div class="mt-4 space-y-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="grid gap-3 md:grid-cols-2 text-xs text-gray-600">
                            <div class="space-y-1">
                                <p><span class="font-semibold text-gray-700">From:</span> <span x-text="(log.from_name || '') + ' <' + (log.from_email || '—') + '>'"></span></p>
                                <p><span class="font-semibold text-gray-700">Reply-To:</span> <span x-text="log.reply_to || '—'"></span></p>
                                <p><span class="font-semibold text-gray-700">Image mode:</span> <span x-text="log.image_mode"></span></p>
                                <p><span class="font-semibold text-gray-700">SMTP account:</span> <span x-text="log.smtp_account?.name || log.email_log?.smtp_account_id || 'Default' "></span></p>
                            </div>
                            <div class="space-y-1">
                                <p><span class="font-semibold text-gray-700">Email log context:</span> <span class="font-mono break-all" x-text="log.email_log_context"></span></p>
                                <p><span class="font-semibold text-gray-700">Provider log status:</span> <span x-text="log.email_log?.status || '—'"></span></p>
                                <p><span class="font-semibold text-gray-700">Provider error:</span> <span x-text="log.email_log?.error || log.error || '—'"></span></p>
                            </div>
                        </div>
                        <div x-show="log.review_payload" x-cloak class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600 space-y-1">
                            <p class="font-semibold text-gray-700">Review response</p>
                            <p><span class="font-medium">Decision:</span> <span x-text="log.review_payload?.decision || '—'"></span></p>
                            <p><span class="font-medium">Reviewer:</span> <span x-text="log.review_payload?.reviewer_name || '—'"></span></p>
                            <p><span class="font-medium">Email:</span> <span x-text="log.review_payload?.reviewer_email || '—'"></span></p>
                            <p><span class="font-medium">Note:</span> <span x-text="log.review_payload?.note || '—'"></span></p>
                        </div>
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-700">Preview + body debug</summary>
                            <div class="border-t border-gray-200 p-3 space-y-3">
                                <div class="max-w-none prose prose-sm" x-html="log.preview_html"></div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-700 mb-1">Plain text body</p>
                                    <pre class="whitespace-pre-wrap font-mono" x-text="log.body_text || ''"></pre>
                                </div>
                            </div>
                        </details>
                    </div>
                </details>
            </template>
        </div>

        <div x-show="approvalEmailLogs.length === 0" x-cloak class="px-4 py-6 text-sm text-gray-500">No draft approval emails have been sent for this draft yet.</div>
    </div>
</div>
