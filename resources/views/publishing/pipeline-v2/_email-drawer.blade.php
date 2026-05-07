{{-- Email drawer: Draft Approval + Live Notification, tabbed.
     Reuses existing approvalEmail* and publicationNotification* x-models.
     Functionality is unchanged — visual layout, tooltips, and tab switching only. --}}
<div class="flex flex-col h-full" x-init="approvalEmailEnsureLoaded()">

    {{-- Header --}}
    <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Email</h3>
            <p class="text-xs text-gray-500">Approval drafts and live notifications, all in one place.</p>
        </div>
        <button type="button" @click="emailDrawerOpen = false" class="v2-btn v2-btn-ghost p-1.5" aria-label="Close email drawer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Tabs --}}
    <div class="px-5 pt-3 border-b border-gray-200 bg-white flex gap-1">
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

    {{-- ─────────────── APPROVAL TAB ─────────────── --}}
    <div x-show="emailDrawerTab === 'approval'" class="flex-1 overflow-y-auto">

        {{-- Status strip --}}
        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-gray-500 font-semibold uppercase tracking-wide">Status</span>
                <template x-if="!approvalEmailLogs || approvalEmailLogs.length === 0">
                    <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-600 font-medium">Not sent yet</span>
                </template>
                <template x-if="approvalEmailLogs && approvalEmailLogs.length > 0">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700 font-medium" x-text="(approvalEmailLogs[0]?.status_label || approvalEmailLogs[0]?.status || 'sent')"></span>
                        <span x-show="approvalEmailLogs[0]?.viewed_at" x-cloak class="px-2 py-1 rounded-full bg-purple-100 text-purple-700 font-medium">Viewed</span>
                        <span x-show="approvalEmailLogs[0]?.reviewed_at" x-cloak class="px-2 py-1 rounded-full bg-green-100 text-green-700 font-medium" x-text="'Reviewed: ' + (approvalEmailLogs[0]?.review_payload?.decision || 'completed')"></span>
                    </span>
                </template>
            </div>
            <p class="mt-2 text-xs text-gray-500">Send the full draft inline for review before publication.</p>
        </div>

        {{-- Field overrides + tooltips wrapper. We render the existing partial verbatim;
             tooltips below the form give field-level help without changing inputs. --}}
        <div class="p-5 space-y-4">
            <div class="rounded-lg bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-900 flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
                <div>
                    <p class="font-semibold">How approval works</p>
                    <p>Set <span class="font-mono">To</span>, click <span class="font-semibold">Preview Email</span> to render exactly what the client will see, then <span class="font-semibold">Send Draft Email</span>. Reviewer status updates appear in the log below.</p>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="flex items-center gap-2 text-xs text-gray-700 px-3 py-2 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="font-semibold uppercase tracking-wide text-gray-500">Reply-To</span>
                    <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                        Where reviewer replies land. Defaults to support@scalemypublication.com so reviewer responses route to the team.
                    </x-hexa-tooltip>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-700 px-3 py-2 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="font-semibold uppercase tracking-wide text-gray-500">Image handling</span>
                    <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                        How images render in the email body. Links + captions is safest for inboxes; embed forces direct attachment; WordPress-hosted uses already-uploaded media.
                    </x-hexa-tooltip>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-700 px-3 py-2 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="font-semibold uppercase tracking-wide text-gray-500">Draft data</span>
                    <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                        Refresh Draft Data re-fetches the latest title, description, body, and photos so the email reflects current edits.
                    </x-hexa-tooltip>
                </div>
            </div>

            @include('app-publish::publishing.articles.partials.draft-approval-email-panel-body')
        </div>
    </div>

    {{-- ─────────────── NOTIFICATION TAB ─────────────── --}}
    <div x-show="emailDrawerTab === 'notification'" x-cloak class="flex-1 overflow-y-auto">

        {{-- Status strip --}}
        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-gray-500 font-semibold uppercase tracking-wide">Status</span>
                <template x-if="!(publishResult?.post_url || existingWpPostUrl)">
                    <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-600 font-medium">Awaiting permalink</span>
                </template>
                <template x-if="(publishResult?.post_url || existingWpPostUrl) && publicationNotificationStatus !== 'success'">
                    <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700 font-medium">Permalink ready</span>
                </template>
                <template x-if="publicationNotificationStatus === 'success'">
                    <span class="px-2 py-1 rounded-full bg-green-100 text-green-700 font-medium">Notification sent</span>
                </template>
                <template x-if="publicationNotificationStatus === 'error'">
                    <span class="px-2 py-1 rounded-full bg-red-100 text-red-700 font-medium" x-text="publicationNotificationResult?.message || 'Error'"></span>
                </template>
            </div>
            <p class="mt-2 text-xs text-gray-500">Send a live notification to the client once the article is published.</p>
        </div>

        <div class="p-5 space-y-4">
            <template x-if="!(publishResult?.post_url || existingWpPostUrl)">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0l-7.1 12.25A2 2 0 005 19z"/></svg>
                    <div>
                        <p class="font-semibold">Publish first</p>
                        <p>The notification email needs the live permalink. Publish the article (or update an existing post) on the page behind this drawer, then come back here.</p>
                    </div>
                </div>
            </template>

            {{-- Template + sender --}}
            <div class="space-y-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Template</label>
                        <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                            Saved notification templates auto-fill subject, body, and shortcodes. Default uses the publication's standard wording.
                        </x-hexa-tooltip>
                    </div>
                    <select x-model="publicationNotificationTemplateId" @change="applyPublicationNotificationTemplate(publicationNotificationTemplateId, { force: true })" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">Default publication notification</option>
                        <template x-for="template in publicationNotificationTemplates" :key="template.id">
                            <option :value="String(template.id)" x-text="template.name + (template.is_primary ? ' (Default)' : '')"></option>
                        </template>
                    </select>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">From name</label>
                        <input type="text" x-model="publicationNotificationFromName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">From email</label>
                        <input type="email" x-model="publicationNotificationFromEmail" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Reply-to</label>
                            <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                                Where the client's replies will land. Defaults to publication support inbox.
                            </x-hexa-tooltip>
                        </div>
                        <input type="email" x-model="publicationNotificationReplyTo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">CC</label>
                            <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                                Optional. Comma-separated email addresses receive a copy of the notification.
                            </x-hexa-tooltip>
                        </div>
                        <input type="text" x-model="publicationNotificationCc" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="name@example.com, team@example.com">
                    </div>
                </div>
            </div>

            {{-- To + Subject --}}
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">To</label>
                    <input type="email" x-model="publicationNotificationTo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="client@example.com">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Subject</label>
                    <input type="text" x-model="publicationNotificationSubject" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            {{-- Body --}}
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Body</label>
                    <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                        Plain text or HTML body. Shortcodes below get replaced with live values when the email is sent.
                    </x-hexa-tooltip>
                </div>
                <textarea x-model="publicationNotificationBody" rows="9" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"></textarea>
            </div>

            {{-- Shortcodes reference --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="flex items-center gap-2 mb-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Available shortcodes</p>
                    <x-hexa-tooltip mode="hover" label="?" widthClass="w-72" position="bottom">
                        Drop these tokens into Subject or Body. They get replaced with live post values (permalink, title, publication name) when the email is sent.
                    </x-hexa-tooltip>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(description, code) in publicationNotificationShortcodes" :key="code">
                        <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] text-gray-700">
                            <span class="font-mono" x-text="code"></span>
                            <span class="text-gray-400">·</span>
                            <span x-text="description"></span>
                        </span>
                    </template>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="button" @click="hydratePublicationNotificationFields({ force: true })" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Refresh autofill
                </button>
                <button type="button" @click="sendPublicationNotification()" :disabled="publicationNotificationSending || !(publishResult?.post_url || existingWpPostUrl)" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    <svg x-show="publicationNotificationSending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="publicationNotificationSending ? 'Sending email…' : 'Send Live Notification'"></span>
                </button>
                <span x-show="publicationNotificationStatus" x-cloak class="text-xs" :class="publicationNotificationStatus === 'success' ? 'text-green-600' : 'text-red-600'" x-text="publicationNotificationResult?.message || publicationNotificationStatus"></span>
            </div>
        </div>
    </div>
</div>
