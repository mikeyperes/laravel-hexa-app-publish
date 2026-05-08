{{-- Email drawer: Draft Approval + Live Notification, tabbed.
     Reuses existing approvalEmail* and publicationNotification* x-models.
     Functionality is unchanged — visual layout, tooltips, and tab switching only. --}}
<div class="flex flex-col h-full" x-init="approvalEmailEnsureLoaded()">

    {{-- Header --}}
    <div class="px-5 py-3 border-b border-gray-200 flex items-start gap-3 flex-shrink-0">
        {{-- Featured image (small thumbnail) --}}
        <div class="flex-shrink-0 w-14 h-14 rounded-md bg-gray-100 overflow-hidden">
            <template x-if="approvalEmailArticle?.featured_image_url">
                <img :src="approvalEmailArticle.featured_image_url" alt="" class="w-full h-full object-cover">
            </template>
            <template x-if="!approvalEmailArticle?.featured_image_url">
                <div class="w-full h-full flex items-center justify-center text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </template>
        </div>
        {{-- Title block --}}
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-900 text-base truncate" x-text="approvalEmailArticle?.title || 'Email'"></h3>
            <p class="text-xs text-gray-500">Approval drafts and live notifications.</p>
        </div>
        <button type="button" @click="cycleEmailDrawerWidth()" class="v2-btn v2-btn-ghost px-2 py-1.5 flex-shrink-0 inline-flex items-center gap-1 text-xs font-medium" :title="'Drawer width: ' + emailDrawerWidth + ' — click to cycle (M → L → XL)'" aria-label="Cycle drawer width">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7L4 12l4 5M16 7l4 5-4 5"/></svg>
            <span x-text="emailDrawerWidth"></span>
        </button>
        <button type="button" @click="emailDrawerOpen = false" class="v2-btn v2-btn-ghost p-1.5 flex-shrink-0" aria-label="Close email drawer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Tabs --}}
    <div class="px-5 pt-3 border-b border-gray-200 bg-white flex gap-1 flex-shrink-0">
        <button type="button" @click="emailDrawerTab = 'approval'"
                :class="emailDrawerTab === 'approval' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                class="px-3 py-2 text-sm font-medium border-b-2 transition">
            <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Draft approval
            </span>
        </button>
        <button type="button" @click="emailDrawerTab = 'notification'"
                :class="emailDrawerTab === 'notification' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                class="px-3 py-2 text-sm font-medium border-b-2 transition">
            <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                Live notification
            </span>
        </button>
    </div>

    {{-- ─────────── APPROVAL TAB ─────────── --}}
    <div x-show="emailDrawerTab === 'approval'" class="flex-1 overflow-y-auto">

        {{-- Status strip --}}
        <div class="px-5 py-2.5 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-gray-500 font-semibold uppercase tracking-wider">Status</span>
            <template x-if="!approvalEmailLogs || approvalEmailLogs.length === 0">
                <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 font-medium">Not sent yet</span>
            </template>
            <template x-if="approvalEmailLogs && approvalEmailLogs.length > 0">
                <span class="inline-flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium" x-text="(approvalEmailLogs[0]?.status_label || approvalEmailLogs[0]?.status || 'Sent')"></span>
                    <span x-show="approvalEmailLogs[0]?.viewed_at" x-cloak class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium">Viewed</span>
                    <span x-show="approvalEmailLogs[0]?.reviewed_at" x-cloak class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium" x-text="'Reviewed: ' + (approvalEmailLogs[0]?.review_payload?.decision || 'completed')"></span>
                </span>
            </template>
        </div>

        <div class="p-5">
            @include('app-publish::publishing.articles.partials.draft-approval-email-panel-body')
        </div>
    </div>

    {{-- ─────────── NOTIFICATION TAB ─────────── --}}
    <div x-show="emailDrawerTab === 'notification'" x-cloak class="flex-1 overflow-y-auto">

        {{-- Status strip --}}
        <div class="px-5 py-2.5 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-gray-500 font-semibold uppercase tracking-wider">Status</span>
            <template x-if="!(publishResult?.post_url || existingWpPostUrl)">
                <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 font-medium">Awaiting permalink</span>
            </template>
            <template x-if="(publishResult?.post_url || existingWpPostUrl) && publicationNotificationStatus !== 'success'">
                <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">Permalink ready</span>
            </template>
            <template x-if="publicationNotificationStatus === 'success'">
                <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Notification sent</span>
            </template>
            <template x-if="publicationNotificationStatus === 'error'">
                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium" x-text="publicationNotificationResult?.message || 'Error'"></span>
            </template>
        </div>

        <div class="p-5 space-y-5">

            <template x-if="!(publishResult?.post_url || existingWpPostUrl)">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0l-7.1 12.25A2 2 0 005 19z"/></svg>
                    <p>Publish or update the article first, then come back here to send the live notification.</p>
                </div>
            </template>

            {{-- Template --}}
            <section class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Template</h4>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Notification template</label>
                    <select x-model="publicationNotificationTemplateId" @change="applyPublicationNotificationTemplate(publicationNotificationTemplateId, { force: true })" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Default publication notification</option>
                        <template x-for="template in publicationNotificationTemplates" :key="template.id">
                            <option :value="String(template.id)" x-text="template.name + (template.is_primary ? ' (Default)' : '')"></option>
                        </template>
                    </select>
                </div>
            </section>

            {{-- Recipients --}}
            <section class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Recipients</h4>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">To</label>
                    <input type="email" x-model="publicationNotificationTo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="client@example.com">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">CC</label>
                    <input type="text" x-model="publicationNotificationCc" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="name@example.com, team@example.com">
                </div>
            </section>

            {{-- Sender --}}
            <section class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Sender</h4>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">From name</label>
                        <input type="text" x-model="publicationNotificationFromName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">From email</label>
                        <input type="email" x-model="publicationNotificationFromEmail" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Reply-to</label>
                    <input type="email" x-model="publicationNotificationReplyTo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </section>

            {{-- Content --}}
            <section class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Content</h4>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" x-model="publicationNotificationSubject" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Body</label>
                    <textarea x-model="publicationNotificationBody" rows="9" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <details class="rounded-lg border border-gray-200 bg-gray-50">
                    <summary class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-600">Available shortcodes</summary>
                    <div class="border-t border-gray-200 px-3 py-3">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(description, code) in publicationNotificationShortcodes" :key="code">
                                <span class="inline-flex items-center gap-1 rounded border border-gray-200 bg-white px-2 py-0.5 text-[11px] text-gray-700">
                                    <span class="font-mono text-blue-700" x-text="code"></span>
                                    <span class="text-gray-400">·</span>
                                    <span x-text="description"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </details>
            </section>

            {{-- Action row --}}
            <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-gray-100">
                <button type="button" @click="hydratePublicationNotificationFields({ force: true })" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span class="hidden sm:inline">Refresh autofill</span>
                </button>
                <div class="flex-1"></div>
                <button type="button" @click="sendPublicationNotification()" :disabled="publicationNotificationSending || !(publishResult?.post_url || existingWpPostUrl)" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50 shadow-sm">
                    <svg x-show="publicationNotificationSending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg x-show="!publicationNotificationSending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span x-text="publicationNotificationSending ? 'Sending…' : 'Send live notification'"></span>
                </button>
            </div>

            <div x-show="publicationNotificationStatus" x-cloak class="rounded-lg px-3 py-2 text-sm" :class="publicationNotificationStatus === 'success' ? 'border border-green-200 bg-green-50 text-green-700' : 'border border-red-200 bg-red-50 text-red-700'" x-text="publicationNotificationResult?.message || publicationNotificationStatus"></div>
        </div>
    </div>
</div>
