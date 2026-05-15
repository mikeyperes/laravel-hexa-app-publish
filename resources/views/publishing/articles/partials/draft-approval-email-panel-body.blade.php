<div class="space-y-5" x-init="approvalEmailMountComposer()">

    {{-- ─── Recipients ─── --}}
    <section class="space-y-3">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Recipients</h4>
        <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
                <label class="block text-xs font-medium text-gray-700">To</label>
                <button type="button" @click="approvalEmailSecondaryUserSearchOpen = !approvalEmailSecondaryUserSearchOpen; if (approvalEmailSecondaryUserSearchOpen) { $nextTick(() => $refs.approvalEmailSecondaryUserQuery?.focus()); }" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span x-text="approvalEmailSecondaryUser ? 'Change secondary user' : 'Add secondary user'"></span>
                </button>
            </div>
            <input type="email" x-model="approvalEmailTo" @input.debounce.300ms="approvalEmailToTouched = true; approvalEmailPersistState()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="client@example.com">

            <div x-show="approvalEmailSecondaryUserSearchOpen" x-cloak class="rounded-xl border border-gray-200 bg-gray-50 p-3 space-y-2">
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500">Search secondary user</label>
                <input type="text" x-ref="approvalEmailSecondaryUserQuery" x-model="approvalEmailSecondaryUserQuery" @input.debounce.250ms="approvalEmailSearchSecondaryUsers()" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Type a user name or email">
                <div x-show="approvalEmailSecondaryUserSearching" x-cloak class="text-[11px] text-gray-500">Searching users…</div>
                <div x-show="!approvalEmailSecondaryUserSearching && approvalEmailSecondaryUserQuery && approvalEmailSecondaryUserResults.length === 0" x-cloak class="text-[11px] text-gray-500">No matching users found.</div>
                <div x-show="approvalEmailSecondaryUserResults.length > 0" x-cloak class="max-h-56 overflow-auto rounded-lg border border-gray-200 bg-white divide-y divide-gray-100">
                    <template x-for="user in approvalEmailSecondaryUserResults" :key="user.id">
                        <button type="button" @click="approvalEmailChooseSecondaryUser(user)" class="w-full px-3 py-2 text-left hover:bg-blue-50">
                            <div class="text-sm font-medium text-gray-900" x-text="user.name || user.email || ('User #' + user.id)"></div>
                            <div class="text-[11px] text-gray-500"><span x-text="approvalEmailSecondaryUserAddress(user) || 'No public email set'"></span></div>
                        </button>
                    </template>
                </div>
            </div>

            <div x-show="approvalEmailSecondaryUser" x-cloak class="rounded-xl border border-blue-100 bg-blue-50 px-3 py-3 space-y-2">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-blue-900" x-text="approvalEmailSecondaryUser?.name || 'Secondary user'"></p>
                        <p class="text-[11px] text-blue-700" x-text="approvalEmailSecondaryUserAddress() || 'No public email available'"></p>
                        <p x-show="approvalEmailSecondaryUser?.additional_contact_emails" x-cloak class="text-[11px] text-blue-700 break-all" x-text="approvalEmailSecondaryUser?.additional_contact_emails"></p>
                    </div>
                    <button type="button" @click="approvalEmailClearSecondaryUser()" class="inline-flex items-center gap-1 rounded border border-blue-200 bg-white px-2 py-1 text-[11px] font-medium text-blue-700 hover:bg-blue-100">Clear</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="approvalEmailImportSecondaryUserTo()" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100">Use public email for To</button>
                    <button type="button" @click="approvalEmailImportSecondaryUserCcs()" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100">Add CC from secondary user</button>
                </div>
            </div>

            <p class="text-xs text-gray-500">Defaults to the selected article contact email. Falls back to the selected user, publishing account, then creator login email.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">CC</label>
            <input type="text" x-model="approvalEmailCc" @input.debounce.300ms="approvalEmailCcTouched = true; approvalEmailPersistState()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="team@example.com, account@example.com">
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="button" @click="approvalEmailImportSuperAdmin()" :disabled="approvalEmailSuperAdminImporting" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    <svg x-show="approvalEmailSuperAdminImporting" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg x-show="!approvalEmailSuperAdminImporting" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span x-text="approvalEmailSuperAdminImporting ? 'Adding…' : 'Add super admin email'"></span>
                </button>
                <button type="button" @click="approvalEmailImportCcs()" :disabled="approvalEmailCcsImporting" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    <svg x-show="approvalEmailCcsImporting" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg x-show="!approvalEmailCcsImporting" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span x-text="approvalEmailCcsImporting ? 'Adding…' : 'Add CCs'"></span>
                </button>
            </div>
            <div class="mt-1.5 space-y-0.5">
                <p x-show="approvalEmailSuperAdminEmpty" x-cloak class="text-[11px] text-gray-500">
                    No super admin email set. <a href="{{ route('profile.edit') }}" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 underline">Click here to add</a>.
                </p>
                <p x-show="approvalEmailCcsEmpty" x-cloak class="text-[11px] text-gray-500">
                    No CCs available. <a href="{{ route('profile.edit') }}" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 underline">Click here to add</a>.
                </p>
                <p x-show="approvalEmailSecondaryUserCcEmpty" x-cloak class="text-[11px] text-gray-500">The selected secondary user has no CC emails configured.</p>
                <p x-show="!approvalEmailSuperAdminEmpty && !approvalEmailCcsEmpty && !approvalEmailSecondaryUserCcEmpty" x-cloak class="text-[11px] text-gray-500">Loads creator additional contact emails by default when they exist.</p>
            </div>
        </div>
    </section>

    {{-- ─── Sender ─── --}}
    <section class="space-y-3">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Sender</h4>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">From name</label>
                <input type="text" x-model="approvalEmailFromName" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">From email</label>
                <input type="email" x-model="approvalEmailFromEmail" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="no-reply@example.com">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Reply-to</label>
            <input type="email" x-model="approvalEmailReplyTo" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
    </section>

    {{-- ─── Content ─── --}}
    <section class="space-y-3">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Content</h4>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Template</label>
            <select x-model="approvalEmailTemplateId" @change="applyApprovalEmailTemplate(approvalEmailTemplateId, { force: true })" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Default email template</option>
                <template x-for="template in approvalEmailTemplates" :key="template.id">
                    <option :value="String(template.id)" x-text="template.name + ((template.is_selected_default || template.is_primary) ? ' (Default)' : '')"></option>
                </template>
            </select>
            <p class="mt-1.5 text-xs text-gray-500">This controls the full email layout. Draft articles default to review-ready templates; live articles default to publication notification templates.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Subject</label>
            <input type="text" x-model="approvalEmailSubject" @input.debounce.300ms="approvalEmailPersistState()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <label class="text-xs font-medium text-gray-700">Email body template</label>
                <x-hexa-tooltip mode="hover" label="?" widthClass="w-80" position="bottom">Use shortcodes like {article}, {article_body}, {article_header}, {permalink}, and {press_release_links}. Include {article} if you want the full article rendered inline.</x-hexa-tooltip>
            </div>
            <textarea
                x-ref="approvalEmailIntroEditor"
                :id="approvalEmailIntroEditorId"
                x-model="approvalEmailBodyTemplate"
                @input.debounce.300ms="approvalEmailPersistState()"
                rows="12"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="<p>Hi,</p><p>Your draft is ready.</p><hr><div>{article}</div>"></textarea>
            <p class="mt-1.5 text-xs text-gray-500">TinyMCE loads here automatically. If the editor library is blocked, this falls back to a normal HTML textarea.</p>
        </div>
        <details x-show="Object.keys(approvalEmailShortcodes || {}).length > 0" x-cloak class="rounded-xl border border-gray-200 bg-gray-50 overflow-hidden">
            <summary class="cursor-pointer px-4 py-2.5 flex items-center justify-between gap-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-100 select-none">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    Available shortcodes
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="text-[10px] text-gray-400 normal-case tracking-normal" x-text="Object.keys(approvalEmailShortcodes || {}).length + ' codes'"></span>
                    <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
            </summary>
            <div class="border-t border-gray-200 bg-white">
                <template x-for="(description, code) in approvalEmailShortcodes" :key="code">
                    <div class="flex items-baseline gap-2.5 px-4 py-2 text-xs border-b border-gray-100 last:border-b-0">
                        <code class="font-mono text-blue-700 bg-blue-50 border border-blue-100 px-1.5 py-0.5 rounded shrink-0 text-[11px]" x-text="code"></code>
                        <span class="text-gray-400">—</span>
                        <span class="text-gray-700" x-text="description"></span>
                    </div>
                </template>
                <p class="px-4 py-2.5 bg-gray-50 border-t border-gray-100 text-[11px] text-gray-500">
                    Use <code class="font-mono text-blue-700">{article}</code> for the full inline article, <code class="font-mono text-blue-700">{permalink}</code> for the live article URL, and <code class="font-mono text-blue-700">{press_release_links}</code> for syndicated press release links.
                </p>
            </div>
        </details>
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <label class="text-xs font-medium text-gray-700">Image handling</label>
                <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">How images render in the email body. Links + captions is safest for inboxes; embed forces direct attachment; WordPress-hosted uses already-uploaded media.</x-hexa-tooltip>
            </div>
            <select x-model="approvalEmailImageMode" @change="approvalEmailPersistState()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="links">Clickable image links + captions</option>
                <option value="embed">Embed images directly</option>
                <option value="wordpress">Use prepared WordPress-hosted images</option>
            </select>
            <p class="mt-1.5 text-xs text-gray-500" x-text="approvalEmailImageModeHelp()"></p>
        </div>
    </section>

    {{-- ─── Action row ─── --}}
    <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-gray-100">
        <button type="button" @click="approvalEmailLoad(approvalEmailTargetId || draftId, { preserveFilled: true, open: false })" :disabled="approvalEmailLoading" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
            <svg x-show="approvalEmailLoading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg x-show="!approvalEmailLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span class="hidden sm:inline">Refresh draft data</span>
        </button>
        <div class="flex-1"></div>
        <button type="button" @click="approvalEmailPreview()" :disabled="approvalEmailPreviewLoading || approvalEmailSending || approvalEmailTestSending" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50">
            <svg x-show="approvalEmailPreviewLoading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg x-show="!approvalEmailPreviewLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span x-text="approvalEmailPreviewLoading ? 'Rendering…' : 'Preview'"></span>
        </button>
        <button type="button" @click="approvalEmailOpenTestPrompt()" :disabled="approvalEmailSending || approvalEmailPreviewLoading || approvalEmailTestSending" :class="approvalEmailTestPromptOpen ? 'border-indigo-400 bg-indigo-100 ring-1 ring-indigo-300' : 'border-indigo-200 bg-indigo-50'" class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
            <svg x-show="approvalEmailTestSending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg x-show="!approvalEmailTestSending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span x-text="approvalEmailTestSending ? 'Sending test…' : 'Send test email'"></span>
        </button>
        <button type="button" @click="approvalEmailSend()" :disabled="approvalEmailSending || approvalEmailPreviewLoading || approvalEmailTestSending" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50 shadow-sm">
            <svg x-show="approvalEmailSending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg x-show="!approvalEmailSending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
            <span x-text="approvalEmailSending ? 'Sending…' : 'Send draft email'"></span>
        </button>
    </div>

    {{-- Send test prompt — appears when "Send test email" is clicked --}}
    <div x-show="approvalEmailTestPromptOpen" x-cloak class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
        <div class="flex items-center justify-between gap-2 mb-2">
            <label class="text-[11px] font-semibold uppercase tracking-wider text-indigo-700">Where should the test go?</label>
            <button type="button" @click="approvalEmailTestPromptOpen = false" class="text-indigo-400 hover:text-indigo-700" aria-label="Cancel">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <input type="email" x-ref="approvalEmailTestInput" x-model="approvalEmailTestTo" @input.debounce.300ms="approvalEmailPersistState()" @keydown.enter.prevent="approvalEmailSubmitTest()" @keydown.escape="approvalEmailTestPromptOpen = false" placeholder="michael@mike-ro-tech.com" class="flex-1 rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <button type="button" @click="approvalEmailTestPromptOpen = false" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
            <button type="button" @click="approvalEmailSubmitTest()" :disabled="approvalEmailTestSending || !approvalEmailTestTo" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="approvalEmailTestSending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <svg x-show="!approvalEmailTestSending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span x-text="approvalEmailTestSending ? 'Sending…' : 'Send test'"></span>
            </button>
        </div>
        <p class="mt-2 text-[11px] text-indigo-700/80">Defaults to the super admin email. The test does not affect the main To or CC recipients.</p>
    </div>

    {{-- Send result --}}
    <div x-show="approvalEmailStatus" x-cloak class="rounded-lg px-3 py-2 text-sm space-y-2" :class="approvalEmailStatusClass()">
        <div x-text="approvalEmailStatus"></div>
        <a
            x-show="approvalEmailStatusNeedsSmtpSettings() && approvalEmailSmtpSettingsUrl"
            x-cloak
            :href="approvalEmailSmtpSettingsUrl"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 rounded border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
        >
            Open SMTP settings
        </a>
    </div>

    {{-- Preview warnings --}}
    <div x-show="approvalEmailWarnings.length > 0" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <p class="font-semibold mb-1">Preview warnings</p>
        <ul class="list-disc space-y-1 pl-5">
            <template x-for="warning in approvalEmailWarnings" :key="warning">
                <li x-text="warning"></li>
            </template>
        </ul>
    </div>

    {{-- Inline preview — inbox-style --}}
    <div x-show="approvalEmailPreviewHtml" x-cloak class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white px-4 py-2 flex items-center justify-between gap-3">
            <p class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-600 uppercase tracking-wider">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Inbox preview
            </p>
            <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-medium text-gray-500" x-text="'Image mode: ' + approvalEmailImageMode"></span>
        </div>
        <div x-html="approvalEmailPreviewHtml"></div>
    </div>

    {{-- Send log --}}
    <details class="rounded-lg border border-gray-200 bg-white" x-show="approvalEmailLogs.length > 0" x-cloak>
        <summary class="cursor-pointer px-4 py-3 flex items-center justify-between gap-3 text-sm">
            <div>
                <span class="font-semibold text-gray-800">Send history</span>
                <span class="ml-2 text-xs text-gray-500" x-text="approvalEmailLogs.length + (approvalEmailLogs.length === 1 ? ' email' : ' emails')"></span>
            </div>
            <button type="button" @click.prevent.stop="approvalEmailRefreshLogs()" :disabled="approvalEmailLogsLoading" class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                <svg x-show="approvalEmailLogsLoading" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Refresh
            </button>
        </summary>
        <div class="divide-y divide-gray-100 border-t border-gray-200">
            <template x-for="log in approvalEmailLogs" :key="log.id">
                <details class="group px-4 py-3">
                    <summary class="cursor-pointer list-none">
                        <div class="flex flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-semibold text-gray-900 truncate" x-text="log.subject"></span>
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium" :class="approvalEmailLogStatusClass(log.status)" x-text="log.status_label || log.status"></span>
                                <span x-show="log.is_test" x-cloak class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700">Test email</span>
                            </div>
                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-gray-500">
                                <span><span class="font-medium text-gray-700">To:</span> <span x-text="log.to || '—'"></span></span>
                                <span><span class="font-medium text-gray-700">Sent:</span> <span x-text="log.sent_at || 'Not sent'"></span></span>
                                <span><span class="font-medium text-gray-700">Created:</span> <span x-text="log.created_at || '—'"></span></span>
                                <span x-show="log.viewed_at" x-cloak><span class="font-medium text-gray-700">Viewed:</span> <span x-text="log.viewed_at"></span></span>
                                <span x-show="log.reviewed_at" x-cloak><span class="font-medium text-gray-700">Reviewed:</span> <span x-text="log.reviewed_at"></span></span>
                            </div>
                        </div>
                    </summary>
                    <div class="mt-3 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div class="grid gap-2 md:grid-cols-2 text-[11px] text-gray-600">
                            <div class="space-y-0.5">
                                <p><span class="font-semibold text-gray-700">From:</span> <span x-text="(log.from_name || '') + ' <' + (log.from_email || '—') + '>'"></span></p>
                                <p><span class="font-semibold text-gray-700">Reply-to:</span> <span x-text="log.reply_to || '—'"></span></p>
                                <p><span class="font-semibold text-gray-700">Image mode:</span> <span x-text="log.image_mode"></span></p>
                                <p><span class="font-semibold text-gray-700">Type:</span> <span x-text="log.context || 'draft-approval'"></span></p>
                                <p><span class="font-semibold text-gray-700">Triggered by:</span> <span x-text="log.sender ? ((log.sender.name || 'Unknown') + (log.sender.email ? ' <' + log.sender.email + '>' : '')) : 'System'"></span></p>
                                <p><span class="font-semibold text-gray-700">SMTP:</span> <span x-text="log.smtp_account?.name || log.email_log?.smtp_account_id || 'Default'"></span></p>
                            </div>
                            <div class="space-y-0.5">
                                <p><span class="font-semibold text-gray-700">Provider status:</span> <span x-text="log.email_log?.status || '—'"></span></p>
                                <p><span class="font-semibold text-gray-700">Provider error:</span> <span x-text="log.email_log?.error || log.error || '—'"></span></p>
                                <p><span class="font-semibold text-gray-700">Email log id:</span> <span x-text="log.email_log?.id || '—'"></span></p>
                                <p class="break-all"><span class="font-semibold text-gray-700">Log context:</span> <span class="font-mono" x-text="log.email_log_context"></span></p>
                            </div>
                        </div>
                        <div x-show="log.review_payload" x-cloak class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-[11px] text-gray-600 space-y-0.5">
                            <p class="font-semibold text-gray-700">Review response</p>
                            <p><span class="font-medium">Decision:</span> <span x-text="log.review_payload?.decision || '—'"></span></p>
                            <p><span class="font-medium">Reviewer:</span> <span x-text="log.review_payload?.reviewer_name || '—'"></span></p>
                            <p><span class="font-medium">Email:</span> <span x-text="log.review_payload?.reviewer_email || '—'"></span></p>
                            <p><span class="font-medium">Note:</span> <span x-text="log.review_payload?.note || '—'"></span></p>
                        </div>
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer px-3 py-2 text-[11px] font-semibold text-gray-700">Headers + diagnostics</summary>
                            <div class="border-t border-gray-200 p-3 grid gap-3 lg:grid-cols-2">
                                <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-[11px] text-gray-600">
                                    <p class="font-semibold text-gray-700 mb-1">Headers</p>
                                    <pre class="whitespace-pre-wrap font-mono" x-text="JSON.stringify(log.headers || {}, null, 2)"></pre>
                                </div>
                                <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-[11px] text-gray-600">
                                    <p class="font-semibold text-gray-700 mb-1">Diagnostics</p>
                                    <pre class="whitespace-pre-wrap font-mono" x-text="JSON.stringify(log.diagnostics || {}, null, 2)"></pre>
                                </div>
                            </div>
                        </details>
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer px-3 py-2 text-[11px] font-semibold text-gray-700">Body + preview</summary>
                            <div class="border-t border-gray-200 p-3 space-y-3">
                                <div class="max-w-none prose prose-sm" x-html="log.preview_html"></div>
                                <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-[11px] text-gray-600">
                                    <p class="font-semibold text-gray-700 mb-1">Plain text body</p>
                                    <pre class="whitespace-pre-wrap font-mono" x-text="log.body_text || ''"></pre>
                                </div>
                            </div>
                        </details>
                    </div>
                </details>
            </template>
        </div>
    </details>

    <p x-show="approvalEmailLogs.length === 0" x-cloak class="text-xs text-gray-400 text-center pt-2">No emails have been sent yet — fill the form above and click <span class="font-medium">Send email</span>.</p>
</div>
