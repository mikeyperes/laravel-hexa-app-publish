<div x-show="currentArticleType === 'press-release'" x-cloak class="space-y-4">

    {{-- ═══ Sub-card 1: Press Release Content ═══ --}}
    <div class="bg-white border rounded-xl overflow-hidden" :class="Object.keys(pressReleaseFieldErrors || {}).length ? 'border-red-300 ring-1 ring-red-100 bg-red-50/20' : 'border-gray-200'">
        <div class="flex items-center gap-3 px-5 py-4 bg-purple-50 border-b border-purple-100">
            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-800">Press Release Content</p>
                <p class="text-xs text-gray-400">Choose how this press release should be ingested</p>
            </div>
        </div>
        <div class="px-5 py-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                <button @click="setPressReleaseSubmitMethod('notion-podcast')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'notion-podcast' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Import Notion Podcast</p>
                    <p class="mt-1 text-xs text-gray-500">Select a podcast episode. The linked guest is imported automatically.</p>
                </button>
                <button @click="setPressReleaseSubmitMethod('notion-book')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'notion-book' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Import Notion Book</p>
                    <p class="mt-1 text-xs text-gray-500">Pick a person, then choose one related book from Notion.</p>
                </button>
                <button @click="setPressReleaseSubmitMethod('content-dump')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'content-dump' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Content Dump</p>
                    <p class="mt-1 text-xs text-gray-500">Paste the raw press release text or notes directly.</p>
                </button>
                <button @click="setPressReleaseSubmitMethod('upload-documents')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'upload-documents' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Upload Press Release</p>
                    <p class="mt-1 text-xs text-gray-500">Accepted formats: `.doc`, `.docx`, `.pdf`.</p>
                </button>
                <button @click="setPressReleaseSubmitMethod('public-url')" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.submit_method === 'public-url' ? 'border-purple-400 bg-purple-50 ring-1 ring-purple-300' : 'border-gray-200 hover:border-purple-300'">
                    <p class="text-sm font-semibold text-gray-800">Submit Public URL</p>
                    <p class="mt-1 text-xs text-gray-500">Pull the release from a public page.</p>
                </button>
            </div>

            {{-- Content dump --}}
            <div x-show="pressRelease.submit_method === 'content-dump'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content Dump <span class="text-red-500">*</span></label>
                <textarea x-model="pressRelease.content_dump" @input.debounce.400ms="clearPressReleaseFieldError('content_dump'); savePipelineState()" class="w-full border rounded-lg px-4 py-3 text-sm leading-relaxed" :class="pressReleaseInputBorderClass('content_dump')" rows="10" placeholder="Paste the full press release, raw notes, quotes, or the exact submitted content here."></textarea>
                <p x-show="pressReleaseFieldHasError('content_dump')" x-cloak class="mt-1 text-xs font-medium text-red-600">Paste the full press release content before continuing.</p>
            </div>

            {{-- Upload documents --}}
            <div x-show="pressRelease.submit_method === 'upload-documents'" x-cloak class="space-y-3">
                <div class="rounded-xl border border-dashed bg-gray-50 p-5" :class="pressReleaseFieldHasError('document_files') ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-300'">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Upload Press Release Files</p>
                            <p class="text-xs text-gray-500 mt-1">Accepted: `.doc`, `.docx`, `.pdf`</p>
                        </div>
                        <label class="inline-flex items-center gap-2 bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 cursor-pointer">
                            <svg x-show="pressReleaseUploadingDocuments" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="pressReleaseUploadingDocuments ? 'Uploading...' : 'Upload Files'"></span>
                            <input type="file" class="hidden" multiple accept=".doc,.docx,.pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf" @change="uploadPressReleaseDocuments($event.target.files); $event.target.value = null">
                        </label>
                    </div>
                </div>
                <div x-show="pressRelease.document_files.length > 0" x-cloak class="space-y-2">
                    <template x-for="file in pressRelease.document_files" :key="file.id">
                        <div class="flex items-start justify-between gap-3 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800 break-words" x-text="file.original_name || file.filename"></p>
                                <p class="text-xs text-gray-400 mt-1" x-text="(file.mime_type || 'file') + ' • ' + Math.max(1, Math.round((file.size || 0) / 1024)) + ' KB'"></p>
                            </div>
                            <a :href="file.url" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 flex-shrink-0">Open</a>
                        </div>
                    </template>
                </div>
                <p x-show="pressReleaseFieldHasError('document_files')" x-cloak class="mt-1 text-xs font-medium text-red-600">Upload at least one press release document before continuing.</p>
            </div>


            {{-- Notion podcast episode import --}}
            <div x-show="pressRelease.submit_method === 'notion-podcast'" x-cloak class="space-y-4"
                @hexa-search-selected.window="if ($event.detail.component_id === 'press-release-episode-search') importPressReleaseNotionEpisode($event.detail.item)">
                <div class="rounded-xl border border-blue-200 bg-blue-50/80 px-4 py-3 text-sm text-blue-900">
                    <span class="font-semibold">💡 Good to know:</span> select a podcast episode from Notion and the linked guest record, guest/company links, YouTube embed, episode thumbnail, and inline guest image are all enforced automatically.
                </div>

                <div class="space-y-3 rounded-xl p-3" :class="pressReleaseFieldHasError('notion_episode') ? 'border border-red-300 bg-red-50/40 ring-1 ring-red-100' : ''">
                    <x-hexa-smart-search
                        url="{{ route('publish.pipeline.press-release.search-notion-episodes.live', ['draft_id' => $draftId]) }}"
                        label="Episode Search"
                        placeholder="Search Notion podcast episodes by guest, topic, or episode title..."
                        display-field="title"
                        subtitle-field="subtitle"
                        value-field="id"
                        id="press-release-episode-search"
                        :min-chars="1"
                        :debounce="250"
                        class="w-full"
                    />
                    <div class="flex justify-end">
                        <button @click="searchPressReleaseNotionEpisodes(true, { notifyEmpty: true })" :disabled="pressReleaseEpisodeSearching" type="button" class="border border-gray-300 bg-white text-gray-700 px-4 py-2 rounded-lg text-sm hover:border-purple-300 hover:text-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                            <svg x-show="pressReleaseEpisodeSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="pressReleaseEpisodeSearching ? 'Loading…' : 'Load Recent Episodes'"></span>
                        </button>
                    </div>
                    <p x-show="pressReleaseFieldHasError('notion_episode')" x-cloak class="text-xs font-medium text-red-600">Select a Notion podcast episode before continuing.</p>
                </div>

                <div x-show="pressReleaseEpisodeDropdownOpen" x-cloak class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-800">Recent Podcast Episodes</p>
                        <button @click="pressReleaseEpisodeDropdownOpen = false" type="button" class="text-xs text-gray-500 hover:text-gray-700">Hide</button>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <template x-for="record in pressReleaseEpisodeResults" :key="'recent-' + record.id">
                            <button type="button" @click="importPressReleaseNotionEpisode(record)"
                                class="w-full text-left px-4 py-3 hover:bg-purple-50 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-800 break-words" x-text="record.title"></p>
                                        <p class="mt-1 text-xs text-gray-500 break-words" x-text="record.subtitle || 'Notion podcast interview record'"></p>
                                    </div>
                                    <span class="text-xs text-purple-600 font-medium flex-shrink-0" x-text="pressReleaseImportingEpisodeId === record.id ? 'Importing…' : 'Import'"></span>
                                </div>
                            </button>
                        </template>
                        <div x-show="pressReleaseEpisodeNoResults && !pressReleaseEpisodeSearching" x-cloak class="px-4 py-3 text-sm text-gray-500">
                            No recent podcast episodes matched that search.
                        </div>
                    </div>
                </div>

                <div x-show="pressRelease.notion_episode && pressRelease.notion_episode.id" x-cloak class="rounded-xl border border-purple-200 bg-white overflow-hidden">
                    <div class="px-4 py-3 border-b border-purple-100 bg-purple-50 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900" x-text="pressRelease.notion_episode.title || 'Selected episode'"></p>
                            <p class="text-xs text-gray-500 mt-1">Guest, host, and podcast are auto-derived from the linked Notion relations on the selected episode.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button @click="importPressReleaseNotionEpisode(pressRelease.notion_episode)" :disabled="pressReleaseImportingEpisodeId === pressRelease.notion_episode.id" type="button" class="inline-flex items-center gap-2 text-xs text-purple-600 hover:text-purple-800 font-medium disabled:opacity-50">
                                <svg x-show="pressReleaseImportingEpisodeId === pressRelease.notion_episode.id" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="pressReleaseImportingEpisodeId === pressRelease.notion_episode.id ? &quot;Refreshing…&quot; : &quot;Refresh Import&quot;"></span>
                            </button>
                            <a x-show="pressRelease.notion_episode?.record_url" x-cloak
                                :href="pressRelease.notion_episode.record_url"
                                target="_blank" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700" title="Open in Notion">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L18.39 2.33c-.42-.326-.98-.7-2.055-.607L3.01 2.87c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.84-.046.933-.56.933-1.167V6.354c0-.606-.233-.933-.746-.886l-15.177.887c-.56.046-.747.326-.747.933zm14.337.745c.093.42 0 .84-.42.886l-.7.14v10.264c-.607.327-1.167.514-1.634.514-.746 0-.933-.234-1.493-.933l-4.572-7.186v6.952l1.446.327s0 .84-1.167.84l-3.22.186c-.093-.186 0-.653.327-.746l.84-.233V9.854L7.326 9.76c-.094-.42.14-1.026.793-1.073l3.453-.233 4.759 7.278v-6.44l-1.213-.14c-.094-.513.28-.886.746-.933zM2.778 1.474l13.728-1.027c1.68-.14 2.1.094 2.8.607l3.874 2.753c.467.374.607.7.607 1.167v16.843c0 1.027-.374 1.634-1.68 1.727L6.937 24.22c-.98.047-1.447-.093-1.96-.747l-3.08-4.012c-.56-.747-.793-1.307-.793-1.96V3.107c0-.84.373-1.54 1.68-1.633z"/></svg>
                                Notion
                            </a>
                        </div>
                    </div>
                    <div class="px-4 py-4 space-y-3 text-sm text-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Guest</p>
                                <p class="mt-1 font-medium text-gray-900" x-text="pressRelease.notion_guest?.name || pressRelease.notion_episode?.guest || 'No linked guest found'"></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Host</p>
                                <p class="mt-1 font-medium text-gray-900" x-text="pressRelease.notion_host?.name || pressRelease.notion_episode?.host || 'No linked host found'"></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Podcast</p>
                                <p class="mt-1 font-medium text-gray-900" x-text="pressRelease.notion_podcast?.name || pressRelease.notion_episode?.podcast || 'No linked podcast found'"></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Episode Date</p>
                                <p class="mt-1 font-medium text-gray-900" x-text="pressRelease.notion_episode?.schedule || 'Not provided'"></p>
                            </div>
                        </div>
                        <div x-show="(pressRelease.notion_episode?.live_links || []).length > 0" x-cloak>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Live Links</p>
                            <div class="mt-2 space-y-1">
                                <template x-for="link in (pressRelease.notion_episode?.live_links || [])" :key="link">
                                    <a :href="link" target="_blank" rel="noopener noreferrer" class="inline-flex items-start gap-1 text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                        <span class="break-all" x-text="link"></span>
                                        <svg class="mt-0.5 h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </template>
                            </div>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50/70 px-3 py-3 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Press Release Input Audit</p>
                                <span class="text-[11px] text-blue-700">These episode, guest, links, and media values are what the AI will use.</span>
                            </div>
                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Episode Fields Pulled From Notion',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.episode || []',
                                    'rowKey' => "'episode-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Podcast Episode Database")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => true,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Guest / Person Fields Pulled From Notion',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.guest || []',
                                    'rowKey' => "'guest-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Person Database")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => true,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Host / Person Fields Pulled From Notion',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.host || []',
                                    'rowKey' => "'host-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Host Person Database")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => true,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Podcast Fields Pulled From Notion',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.podcast || []',
                                    'rowKey' => "'podcast-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Podcast Database")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => true,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                            </div>
                            <div class="rounded-lg border border-blue-200 bg-white/80 px-3 py-3">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Enforced Links & Media</p>
                                    <span class="text-[11px] text-blue-700">The first guest mention, first company mention, YouTube embed, episode thumbnail, and inline guest image are enforced.</span>
                                </div>
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Canonical Targets',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.enforcement || []',
                                    'rowKey' => "'enforce-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Canonical Targets")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => true,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                            </div>
                            <div class="flex flex-wrap justify-end gap-3">
                                <a x-show="pressRelease.notion_guest?.record_url" x-cloak :href="pressRelease.notion_guest.record_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700" title="Open guest in Notion">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L18.39 2.33c-.42-.326-.98-.7-2.055-.607L3.01 2.87c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.84-.046.933-.56.933-1.167V6.354c0-.606-.233-.933-.746-.886l-15.177.887c-.56.046-.747.326-.747.933zm14.337.745c.093.42 0 .84-.42.886l-.7.14v10.264c-.607.327-1.167.514-1.634.514-.746 0-.933-.234-1.493-.933l-4.572-7.186v6.952l1.446.327s0 .84-1.167.84l-3.22.186c-.093-.186 0-.653.327-.746l.84-.233V9.854L7.326 9.76c-.094-.42.14-1.026.793-1.073l3.453-.233 4.759 7.278v-6.44l-1.213-.14c-.094-.513.28-.886.746-.933zM2.778 1.474l13.728-1.027c1.68-.14 2.1.094 2.8.607l3.874 2.753c.467.374.607.7.607 1.167v16.843c0 1.027-.374 1.634-1.68 1.727L6.937 24.22c-.98.047-1.447-.093-1.96-.747l-3.08-4.012c-.56-.747-.793-1.307-.793-1.96V3.107c0-.84.373-1.54 1.68-1.633z"/></svg>
                                    View Guest in Notion
                                </a>
                                <a x-show="pressRelease.notion_host?.record_url" x-cloak :href="pressRelease.notion_host.record_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700" title="Open host in Notion">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L18.39 2.33c-.42-.326-.98-.7-2.055-.607L3.01 2.87c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.84-.046.933-.56.933-1.167V6.354c0-.606-.233-.933-.746-.886l-15.177.887c-.56.046-.747.326-.747.933zm14.337.745c.093.42 0 .84-.42.886l-.7.14v10.264c-.607.327-1.167.514-1.634.514-.746 0-.933-.234-1.493-.933l-4.572-7.186v6.952l1.446.327s0 .84-1.167.84l-3.22.186c-.093-.186 0-.653.327-.746l.84-.233V9.854L7.326 9.76c-.094-.42.14-1.026.793-1.073l3.453-.233 4.759 7.278v-6.44l-1.213-.14c-.094-.513.28-.886.746-.933zM2.778 1.474l13.728-1.027c1.68-.14 2.1.094 2.8.607l3.874 2.753c.467.374.607.7.607 1.167v16.843c0 1.027-.374 1.634-1.68 1.727L6.937 24.22c-.98.047-1.447-.093-1.96-.747l-3.08-4.012c-.56-.747-.793-1.307-.793-1.96V3.107c0-.84.373-1.54 1.68-1.633z"/></svg>
                                    View Host in Notion
                                </a>
                                <a x-show="pressRelease.notion_podcast?.record_url" x-cloak :href="pressRelease.notion_podcast.record_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700" title="Open podcast in Notion">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L18.39 2.33c-.42-.326-.98-.7-2.055-.607L3.01 2.87c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.84-.046.933-.56.933-1.167V6.354c0-.606-.233-.933-.746-.886l-15.177.887c-.56.046-.747.326-.747.933zm14.337.745c.093.42 0 .84-.42.886l-.7.14v10.264c-.607.327-1.167.514-1.634.514-.746 0-.933-.234-1.493-.933l-4.572-7.186v6.952l1.446.327s0 .84-1.167.84l-3.22.186c-.093-.186 0-.653.327-.746l.84-.233V9.854L7.326 9.76c-.094-.42.14-1.026.793-1.073l3.453-.233 4.759 7.278v-6.44l-1.213-.14c-.094-.513.28-.886.746-.933zM2.778 1.474l13.728-1.027c1.68-.14 2.1.094 2.8.607l3.874 2.753c.467.374.607.7.607 1.167v16.843c0 1.027-.374 1.634-1.68 1.727L6.937 24.22c-.98.047-1.447-.093-1.96-.747l-3.08-4.012c-.56-.747-.793-1.307-.793-1.96V3.107c0-.84.373-1.54 1.68-1.633z"/></svg>
                                    View Podcast in Notion
                                </a>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Guest link target</p>
                                    <template x-if="pressRelease.notion_guest?.person_url">
                                        <a :href="pressRelease.notion_guest.person_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-start gap-1 text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                            <span class="break-all" x-text="pressRelease.notion_guest.person_url"></span>
                                            <svg class="mt-0.5 h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <p x-show="!pressRelease.notion_guest?.person_url" x-cloak class="mt-1 text-amber-700">Missing person URL</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Company link target</p>
                                    <template x-if="pressRelease.notion_guest?.company_url">
                                        <a :href="pressRelease.notion_guest.company_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-start gap-1 text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                            <span class="break-all" x-text="pressRelease.notion_guest.company_url"></span>
                                            <svg class="mt-0.5 h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <p x-show="!pressRelease.notion_guest?.company_url" x-cloak class="mt-1 text-amber-700">Missing company URL</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">YouTube embed source</p>
                                    <template x-if="pressRelease.notion_episode?.youtube_url">
                                        <a :href="pressRelease.notion_episode.youtube_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-start gap-1 text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                            <span class="break-all" x-text="pressRelease.notion_episode.youtube_url"></span>
                                            <svg class="mt-0.5 h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <p x-show="!pressRelease.notion_episode?.youtube_url" x-cloak class="mt-1 text-amber-700">Missing YouTube URL</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Episode featured image</p>
                                    <template x-if="pressRelease.notion_episode?.featured_image_url">
                                        <a :href="pressRelease.notion_episode.featured_image_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-start gap-1 text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                            <span class="break-all" x-text="pressRelease.notion_episode.featured_image_url"></span>
                                            <svg class="mt-0.5 h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <p x-show="!pressRelease.notion_episode?.featured_image_url" x-cloak class="mt-1 text-amber-700">Missing featured image URL</p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Inline guest image</p>
                                    <template x-if="pressRelease.notion_guest?.inline_photo_url">
                                        <a :href="pressRelease.notion_guest.inline_photo_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-start gap-1 text-blue-600 hover:text-blue-800 break-all underline decoration-blue-200 underline-offset-2">
                                            <span class="break-all" x-text="pressRelease.notion_guest.inline_photo_url"></span>
                                            <svg class="mt-0.5 h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </template>
                                    <p x-show="!pressRelease.notion_guest?.inline_photo_url" x-cloak class="mt-1 text-amber-700">Missing guest/client image for inline use</p>
                                </div>
                            </div>
                        </div>
                        <div x-show="(pressRelease.notion_missing_fields || []).length > 0" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3">
                            <p class="text-xs uppercase tracking-wide text-amber-700">Follow-up needed</p>
                            <ul class="mt-2 list-disc pl-5 text-sm text-amber-900 space-y-1">
                                <template x-for="item in (pressRelease.notion_missing_fields || [])" :key="item">
                                    <li x-text="item"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Notion book import --}}
            <div x-show="pressRelease.submit_method === 'notion-book'" x-cloak class="space-y-4"
                @hexa-search-selected.window="if ($event.detail.component_id === 'press-release-person-search') loadPressReleaseNotionPersonBooks($event.detail.item)">
                <div class="rounded-xl border border-blue-200 bg-blue-50/80 px-4 py-3 text-sm text-blue-900">
                    <span class="font-semibold">💡 Good to know:</span> select a person from Notion first, then choose one of their related books. The author URL, Google Books link, book cover, Google Drive assets, and author photos are imported automatically.
                </div>

                <div class="space-y-3 rounded-xl p-3" :class="pressReleaseFieldHasError('notion_book') ? 'border border-red-300 bg-red-50/40 ring-1 ring-red-100' : ''">
                    <x-hexa-smart-search
                        url="{{ route('publish.pipeline.press-release.search-notion-people.live', ['draft_id' => $draftId]) }}"
                        label="Author Search"
                        placeholder="Search Notion people by author name, website, or company..."
                        display-field="title"
                        subtitle-field="subtitle"
                        value-field="id"
                        id="press-release-person-search"
                        :min-chars="1"
                        :debounce="250"
                        class="w-full"
                    />
                    <div class="flex justify-end">
                        <button @click="searchPressReleaseNotionPeople(true, { notifyEmpty: true })" :disabled="pressReleasePersonSearching" type="button" class="border border-gray-300 bg-white text-gray-700 px-4 py-2 rounded-lg text-sm hover:border-purple-300 hover:text-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                            <svg x-show="pressReleasePersonSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="pressReleasePersonSearching ? 'Loading…' : 'Load Recent People'"></span>
                        </button>
                    </div>
                    <p x-show="pressReleaseFieldHasError('notion_book')" x-cloak class="text-xs font-medium text-red-600">Select a Notion person and import one related book before continuing.</p>
                </div>

                <div x-show="pressReleasePersonDropdownOpen" x-cloak class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-800">Recent Notion People</p>
                        <button @click="pressReleasePersonDropdownOpen = false" type="button" class="text-xs text-gray-500 hover:text-gray-700">Hide</button>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <template x-for="record in pressReleasePersonResults" :key="'person-' + record.id">
                            <button type="button" @click="loadPressReleaseNotionPersonBooks(record)"
                                class="w-full text-left px-4 py-3 hover:bg-purple-50 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-800 break-words" x-text="record.title"></p>
                                        <p class="mt-1 text-xs text-gray-500 break-words" x-text="record.subtitle || 'Notion people record'"></p>
                                    </div>
                                    <span class="text-xs text-purple-600 font-medium flex-shrink-0" x-text="pressReleaseLoadingPersonBooks ? 'Loading…' : 'Select'"></span>
                                </div>
                            </button>
                        </template>
                        <div x-show="pressReleasePersonNoResults && !pressReleasePersonSearching" x-cloak class="px-4 py-3 text-sm text-gray-500">
                            No recent Notion people matched that search.
                        </div>
                    </div>
                </div>

                <div x-show="pressRelease.notion_person && pressRelease.notion_person.id" x-cloak class="rounded-xl border border-purple-200 bg-white overflow-hidden">
                    <div class="px-4 py-3 border-b border-purple-100 bg-purple-50 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900" x-text="pressRelease.notion_person.name || 'Selected person'"></p>
                            <p class="text-xs text-gray-500 mt-1">Books are loaded from the relation on this Notion People record.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button @click="loadPressReleaseNotionPersonBooks(pressRelease.notion_person)" :disabled="pressReleaseLoadingPersonBooks" type="button" class="inline-flex items-center gap-2 text-xs text-purple-600 hover:text-purple-800 font-medium disabled:opacity-50">
                                <svg x-show="pressReleaseLoadingPersonBooks" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="pressReleaseLoadingPersonBooks ? 'Refreshing…' : 'Refresh Books'"></span>
                            </button>
                            <a x-show="pressRelease.notion_person?.record_url" x-cloak
                                :href="pressRelease.notion_person.record_url"
                                target="_blank" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700" title="Open in Notion">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L18.39 2.33c-.42-.326-.98-.7-2.055-.607L3.01 2.87c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.84-.046.933-.56.933-1.167V6.354c0-.606-.233-.933-.746-.886l-15.177.887c-.56.046-.747.326-.747.933zm14.337.745c.093.42 0 .84-.42.886l-.7.14v10.264c-.607.327-1.167.514-1.634.514-.746 0-.933-.234-1.493-.933l-4.572-7.186v6.952l1.446.327s0 .84-1.167.84l-3.22.186c-.093-.186 0-.653.327-.746l.84-.233V9.854L7.326 9.76c-.094-.42.14-1.026.793-1.073l3.453-.233 4.759 7.278v-6.44l-1.213-.14c-.094-.513.28-.886.746-.933zM2.778 1.474l13.728-1.027c1.68-.14 2.1.094 2.8.607l3.874 2.753c.467.374.607.7.607 1.167v16.843c0 1.027-.374 1.634-1.68 1.727L6.937 24.22c-.98.047-1.447-.093-1.96-.747l-3.08-4.012c-.56-.747-.793-1.307-.793-1.96V3.107c0-.84.373-1.54 1.68-1.633z"/></svg>
                                Notion
                            </a>
                        </div>
                    </div>
                    <div class="px-4 py-4 space-y-4 text-sm text-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Primary author URL</p>
                                <template x-if="pressRelease.notion_person?.person_url">
                                    <a :href="pressRelease.notion_person.person_url" target="_blank" rel="noopener noreferrer" class="mt-1 block text-blue-600 hover:text-blue-800 break-all" x-text="pressRelease.notion_person.person_url"></a>
                                </template>
                                <p x-show="!pressRelease.notion_person?.person_url" x-cloak class="mt-1 text-amber-700">Missing public author URL</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Related books</p>
                                <p class="mt-1 font-medium text-gray-900" x-text="(pressRelease.notion_book_options || []).length + ' available'"></p>
                            </div>
                        </div>
                        <div x-show="pressRelease.notion_person?.social_urls && Object.keys(pressRelease.notion_person.social_urls || {}).length > 0" x-cloak>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Social Links</p>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                                <template x-for="[network, url] in Object.entries(pressRelease.notion_person.social_urls || {})" :key="network">
                                    <a :href="url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 break-all">
                                        <span class="font-medium capitalize" x-text="network.replace('_', ' ') + ':'"></span>
                                        <span x-text="' ' + url"></span>
                                    </a>
                                </template>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs uppercase tracking-wide text-gray-400">Choose a related book</p>
                                <p class="text-[11px] text-gray-500">The selected book becomes the PR source package.</p>
                            </div>
                            <div x-show="(pressRelease.notion_book_options || []).length > 0" x-cloak class="space-y-2">
                                <template x-for="book in (pressRelease.notion_book_options || [])" :key="'book-option-' + book.id">
                                    <button type="button" @click="importPressReleaseNotionBook(book)"
                                        class="w-full text-left rounded-lg border px-4 py-3 transition-colors"
                                        :class="pressRelease.notion_book?.id === book.id ? 'border-purple-300 bg-purple-50' : 'border-gray-200 hover:border-purple-300 hover:bg-purple-50/40'">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-gray-900 break-words" x-text="book.title"></p>
                                                <p class="mt-1 text-xs text-gray-500 break-words" x-text="book.subtitle || 'Related book record'"></p>
                                                <a x-show="book.book_url" x-cloak :href="book.book_url" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs text-blue-600 hover:text-blue-800 break-all" x-text="book.book_url"></a>
                                            </div>
                                            <span class="text-xs text-purple-600 font-medium flex-shrink-0" x-text="pressReleaseImportingBookId === book.id ? 'Importing…' : (pressRelease.notion_book?.id === book.id ? 'Imported' : 'Import')"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                            <div x-show="(pressRelease.notion_book_options || []).length === 0 && !pressReleaseLoadingPersonBooks" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900">
                                No related books were found on this Notion People record.
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="pressRelease.notion_book && pressRelease.notion_book.id" x-cloak class="rounded-xl border border-purple-200 bg-white overflow-hidden">
                    <div class="px-4 py-3 border-b border-purple-100 bg-purple-50 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900" x-text="pressRelease.notion_book.title || 'Selected book'"></p>
                            <p class="text-xs text-gray-500 mt-1">Author profile, book links, cover art, and author photos are enforced automatically.</p>
                        </div>
                        <a x-show="pressRelease.notion_book?.record_url" x-cloak
                            :href="pressRelease.notion_book.record_url"
                            target="_blank" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700" title="Open in Notion">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L18.39 2.33c-.42-.326-.98-.7-2.055-.607L3.01 2.87c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.84-.046.933-.56.933-1.167V6.354c0-.606-.233-.933-.746-.886l-15.177.887c-.56.046-.747.326-.747.933zm14.337.745c.093.42 0 .84-.42.886l-.7.14v10.264c-.607.327-1.167.514-1.634.514-.746 0-.933-.234-1.493-.933l-4.572-7.186v6.952l1.446.327s0 .84-1.167.84l-3.22.186c-.093-.186 0-.653.327-.746l.84-.233V9.854L7.326 9.76c-.094-.42.14-1.026.793-1.073l3.453-.233 4.759 7.278v-6.44l-1.213-.14c-.094-.513.28-.886.746-.933zM2.778 1.474l13.728-1.027c1.68-.14 2.1.094 2.8.607l3.874 2.753c.467.374.607.7.607 1.167v16.843c0 1.027-.374 1.634-1.68 1.727L6.937 24.22c-.98.047-1.447-.093-1.96-.747l-3.08-4.012c-.56-.747-.793-1.307-.793-1.96V3.107c0-.84.373-1.54 1.68-1.633z"/></svg>
                            Notion
                        </a>
                    </div>
                    <div class="px-4 py-4 space-y-3 text-sm text-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Author</p>
                                <p class="mt-1 font-medium text-gray-900" x-text="pressRelease.notion_person?.name || 'No linked person found'"></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Primary book URL</p>
                                <template x-if="pressRelease.notion_book?.book_url">
                                    <a :href="pressRelease.notion_book.book_url" target="_blank" rel="noopener noreferrer" class="mt-1 block text-blue-600 hover:text-blue-800 break-all" x-text="pressRelease.notion_book.book_url"></a>
                                </template>
                                <p x-show="!pressRelease.notion_book?.book_url" x-cloak class="mt-1 text-amber-700">Missing public book URL</p>
                            </div>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50/70 px-3 py-3 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Press Release Input Audit</p>
                                <span class="text-[11px] text-blue-700">These author, book, links, and media values are what the AI will use.</span>
                            </div>
                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Author Fields Pulled From Notion',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.person || []',
                                    'rowKey' => "'person-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Person Database")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => false,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Book Fields Pulled From Notion',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.book || []',
                                    'rowKey' => "'book-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Book Database")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => false,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                            </div>
                            <div class="rounded-lg border border-blue-200 bg-white/80 px-3 py-3">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Enforced Links & Media</p>
                                    <span class="text-[11px] text-blue-700">The first author mention, the first book mention, the book cover, and the inline author image are enforced.</span>
                                </div>
                                @include('app-publish::publishing.pipeline.partials.notion-field-panel', [
                                    'heading' => 'Canonical Targets',
                                    'rowsExpression' => 'pressRelease.notion_source_fields?.enforcement || []',
                                    'rowKey' => "'book-enforce-' + (row.field || row.source_field || rowIdx)",
                                    'labelExpression' => "notionAuditFieldLabel(row) || 'Field'",
                                    'sourceExpression' => 'notionAuditSourceLabel(row, "Canonical Targets")',
                                    'valueExpression' => "row.value || ''",
                                    'linkUrls' => true,
                                    'panelClass' => 'rounded-lg border border-white/70 bg-white/80 p-3',
                                ])
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Author link target</p>
                                    <template x-if="pressRelease.notion_person?.person_url">
                                        <a :href="pressRelease.notion_person.person_url" target="_blank" rel="noopener noreferrer" class="mt-1 block text-blue-600 hover:text-blue-800 break-all" x-text="pressRelease.notion_person.person_url"></a>
                                    </template>
                                    <p x-show="!pressRelease.notion_person?.person_url" x-cloak class="mt-1 text-amber-700">Missing author URL</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Book link target</p>
                                    <template x-if="pressRelease.notion_book?.book_url">
                                        <a :href="pressRelease.notion_book.book_url" target="_blank" rel="noopener noreferrer" class="mt-1 block text-blue-600 hover:text-blue-800 break-all" x-text="pressRelease.notion_book.book_url"></a>
                                    </template>
                                    <p x-show="!pressRelease.notion_book?.book_url" x-cloak class="mt-1 text-amber-700">Missing book URL</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Book cover</p>
                                    <template x-if="pressRelease.notion_book?.featured_image_url">
                                        <a :href="pressRelease.notion_book.featured_image_url" target="_blank" rel="noopener noreferrer" class="mt-1 block text-blue-600 hover:text-blue-800 break-all" x-text="pressRelease.notion_book.featured_image_url"></a>
                                    </template>
                                    <p x-show="!pressRelease.notion_book?.featured_image_url" x-cloak class="mt-1 text-amber-700">Missing book cover</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Inline author image</p>
                                    <template x-if="pressRelease.notion_person?.inline_photo_url">
                                        <a :href="pressRelease.notion_person.inline_photo_url" target="_blank" rel="noopener noreferrer" class="mt-1 block text-blue-600 hover:text-blue-800 break-all" x-text="pressRelease.notion_person.inline_photo_url"></a>
                                    </template>
                                    <p x-show="!pressRelease.notion_person?.inline_photo_url" x-cloak class="mt-1 text-amber-700">Missing author photo</p>
                                </div>
                            </div>
                        </div>
                        <div x-show="(pressRelease.notion_missing_fields || []).length > 0" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3">
                            <p class="text-xs uppercase tracking-wide text-amber-700">Follow-up needed</p>
                            <ul class="mt-2 list-disc pl-5 text-sm text-amber-900 space-y-1">
                                <template x-for="item in (pressRelease.notion_missing_fields || [])" :key="'book-missing-' + item">
                                    <li x-text="item"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Public URL --}}
            <div x-show="pressRelease.submit_method === 'public-url'" x-cloak class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Public Press Release URL <span class="text-red-500">*</span></label>
                    <input type="url" x-model="pressRelease.public_url" @input.debounce.400ms="clearPressReleaseFieldError('public_url'); savePipelineState()" class="w-full border rounded-lg px-3 py-2 text-sm" :class="pressReleaseInputBorderClass('public_url')" placeholder="https://example.com/public-press-release">
                    <p x-show="pressReleaseFieldHasError('public_url')" x-cloak class="mt-1 text-xs font-medium text-red-600">Enter a public press release URL before continuing.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pull / Scrape Method</label>
                    <select x-model="pressRelease.public_url_method" @change="savePipelineState()" class="w-full md:w-80 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="auto">Auto</option>
                        <option value="readability">Readability</option>
                        <option value="structured">Structured Data</option>
                        <option value="heuristic">DOM Heuristic</option>
                        <option value="css">CSS Selector</option>
                        <option value="regex">Regex</option>
                        <option value="jina">Jina Reader</option>
                        <option value="claude">Claude AI</option>
                        <option value="gpt">GPT</option>
                        <option value="grok">Grok</option>
                        <option value="gemini">Gemini</option>
                    </select>
                </div>

                {{-- Detect Content button --}}
                <div x-show="pressRelease.public_url" x-cloak>
                    <button @click="detectPressReleaseContent()" :disabled="pressReleaseDetectingContent" type="button" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2 font-medium">
                        <svg x-show="pressReleaseDetectingContent" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="pressReleaseDetectingContent ? 'Detecting Content...' : 'Detect Content'"></span>
                    </button>
                </div>

                {{-- Content detection activity log --}}
                <div x-show="pressRelease.content_detect_log?.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Content Detection Log</div>
                    <template x-for="(entry, idx) in (pressRelease.content_detect_log || [])" :key="idx">
                        <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                            <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                            <span class="break-words" :class="{
                                'text-green-400': entry.type === 'success',
                                'text-red-400': entry.type === 'error',
                                'text-blue-400': entry.type === 'info',
                                'text-gray-300': entry.type === 'step',
                            }" x-text="entry.message"></span>
                        </div>
                    </template>
                </div>

                {{-- Detected content output --}}
                <div x-show="pressRelease.detected_content" x-cloak class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-gray-700">Detected Content</h5>
                        <span class="text-xs text-gray-400" x-text="pressRelease.detected_word_count ? pressRelease.detected_word_count + ' words' : ''"></span>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto">
                        <div class="text-sm text-gray-800 whitespace-pre-wrap break-words leading-relaxed" x-html="pressRelease.detected_content_html || pressRelease.detected_content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Sub-card 3: Photos ═══ --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 bg-green-50 border-b border-green-100">
            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">Photos</p>
                <p class="text-xs text-gray-400">Provide photos for the press release</p>
            </div>
            <span x-show="pressReleasePhotoAssets.length > 0" x-cloak class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700" x-text="pressReleasePhotoAssets.length + ' photo(s)'"></span>
        </div>
        <div class="px-5 py-5 space-y-4">
            <div x-show="isPressReleaseNotionImport()" x-cloak class="rounded-xl border border-green-200 bg-green-50/70 px-4 py-3 text-sm text-green-900">
                <span x-show="pressRelease.submit_method === 'notion-podcast'" x-cloak>
                    Podcast press releases now take media from Notion directly: the episode <span class="font-semibold">Featured Image URL</span> drives the featured image, and the linked guest <span class="font-semibold">Person</span> record supplies the inline guest/client image. Google Drive is only a manual fallback, not the primary asset source.
                </span>
                <span x-show="pressRelease.submit_method === 'notion-book'" x-cloak>
                    Book press releases now take media from Notion directly: the imported <span class="font-semibold">Book Cover URL</span> drives the featured image, and the linked <span class="font-semibold">Person</span> record supplies the inline author photo. Imported Google Drive assets remain attached automatically.
                </span>
            </div>
            <div class="grid grid-cols-1 gap-3" :class="pressRelease.submit_method === 'public-url' ? 'md:grid-cols-3' : 'md:grid-cols-2'">
                <button x-show="!isPressReleaseNotionImport()" x-cloak @click="pressRelease.photo_method = 'google-drive'; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'google-drive' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                    <p class="text-sm font-semibold text-gray-800">Google Drive URL</p>
                    <p class="mt-1 text-xs text-gray-500">Supply a public Google Drive folder or file link.</p>
                </button>
                <button @click="pressRelease.photo_method = 'upload-files'; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'upload-files' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                    <p class="text-sm font-semibold text-gray-800">Upload Photos</p>
                    <p class="mt-1 text-xs text-gray-500">Use the multi-photo upload tool for local image files.</p>
                </button>
                <button x-show="pressRelease.submit_method === 'public-url'" x-cloak @click="pressRelease.photo_method = 'detect-from-public-url'; if (!pressRelease.photo_public_url && pressRelease.public_url) pressRelease.photo_public_url = pressRelease.public_url; savePipelineState()" type="button" class="rounded-xl border p-4 text-left transition-colors" :class="pressRelease.photo_method === 'detect-from-public-url' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 hover:border-blue-300'">
                    <p class="text-sm font-semibold text-gray-800">Detect From URL</p>
                    <p class="mt-1 text-xs text-gray-500">Extract photos from the public press release page.</p>
                </button>
            </div>

            <div x-show="pressRelease.photo_method === 'google-drive' && !isPressReleaseNotionImport()" x-cloak class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Google Drive URL <span class="text-red-500">*</span></label>
                    <input type="url" x-model="pressRelease.google_drive_url" @input.debounce.400ms="clearPressReleaseFieldError('google_drive_url'); savePipelineState()" class="w-full border rounded-lg px-3 py-2 text-sm" :class="pressReleaseInputBorderClass('google_drive_url')" placeholder="Public Google Drive URL (must have public permissions)">
                    <p x-show="pressReleaseFieldHasError('google_drive_url')" x-cloak class="mt-1 text-xs font-medium text-red-600">Enter a public Google Drive folder URL before continuing.</p>
                </div>
                <div x-show="pressRelease.google_drive_url" x-cloak class="flex justify-end">
                    <button @click="fetchPressReleaseDrivePhotos()" :disabled="pressReleaseFetchingDrivePhotos" type="button" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="pressReleaseFetchingDrivePhotos" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="pressReleaseFetchingDrivePhotos ? 'Fetching...' : 'Fetch Photos from Drive'"></span>
                    </button>
                </div>
            </div>

        <div x-show="pressRelease.photo_method === 'upload-files'" x-cloak class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-gray-500">Upload image files, then refresh the synced list below.</p>
                <button @click="refreshPressReleasePhotoFiles()" type="button" class="text-xs text-blue-600 hover:text-blue-800">Refresh Uploaded Photos</button>
            </div>
            <template x-if="pressRelease.photo_method === 'upload-files'">
                <div>
                    @include('upload-portal::components.upload-portal', ['context' => 'press-release-photo', 'contextId' => $draftId, 'multi' => true])
                </div>
            </template>
        </div>

            <div x-show="pressRelease.photo_method === 'detect-from-public-url'" x-cloak class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Public Press Release URL <span class="text-red-500">*</span></label>
                    <input type="url" x-model="pressRelease.photo_public_url" @input.debounce.400ms="savePipelineState()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Leave blank to reuse the submit URL">
                </div>
                <div class="flex justify-end">
                    <button @click="detectPressReleasePhotos()" :disabled="pressReleaseDetectingPhotos" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="pressReleaseDetectingPhotos" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="pressReleaseDetectingPhotos ? 'Detecting...' : 'Detect Photos From URL'"></span>
                    </button>
                </div>
            </div>

            {{-- Photo detection activity log --}}
            <div x-show="pressRelease.photo_detect_log?.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Photo Detection Log</div>
                <template x-for="(entry, idx) in (pressRelease.photo_detect_log || [])" :key="idx">
                    <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span class="break-words" :class="{
                            'text-green-400': entry.type === 'success',
                            'text-red-400': entry.type === 'error',
                            'text-blue-400': entry.type === 'info',
                            'text-gray-300': entry.type === 'step',
                        }" x-text="entry.message"></span>
                    </div>
                </template>
            </div>

            <div x-show="pressReleasePhotoAssets.length > 0" x-cloak>
                @include('app-publish::publishing.pipeline.partials.shared-media-picker', [
                    'title' => 'Photo Assets',
                    'description' => 'All candidate photos are shown once. Set one featured image and choose multiple inline photos from the same gallery.',
                    'assetsExpression' => 'pressReleasePhotoAssets',
                    'assetKeyExpression' => 'asset.key',
                    'featuredSelectedExpression' => 'pressReleaseAssetIsFeatured(asset)',
                    'inlineSelectedExpression' => 'pressReleaseAssetIsInline(asset)',
                    'thumbUrlExpression' => 'pressReleaseEditorPreviewUrl(asset) || asset.preview_url || asset.thumbnailLink || asset.thumbnail_url || asset.url',
                    'labelExpression' => 'asset.label',
                    'sourceLabelExpression' => 'asset.source_label || asset.source || "detected"',
                    'sourceMetaHtmlExpression' => 'asset.source_meta_html || ""',
                    'downloadUrlExpression' => 'asset.download_url || asset.url',
                    'viewUrlExpression' => 'asset.view_url || asset.url',
                    'setFeaturedAction' => 'setPressReleaseFeaturedPhoto(asset)',
                    'toggleInlineAction' => 'togglePressReleaseInlinePhotoSelection(asset)',
                    'featuredButtonTextExpression' => 'pressReleaseAssetIsFeatured(asset) ? "Featured Image" : "Set Featured"',
                    'inlineButtonTextExpression' => 'pressReleaseAssetIsFeatured(asset) ? "Featured image" : (pressReleaseAssetIsInline(asset) ? "Inline Selected" : "Add Inline")',
                    'inlineButtonDisabledExpression' => 'pressReleaseAssetIsFeatured(asset)',
                    'countBadgeExpression' => 'pressReleasePhotoAssets.length + " photo(s)"',
                    'featuredBadgeExpression' => 'pressRelease.featured_photo_key ? "Featured selected" : "Featured not set"',
                    'inlineBadgeExpression' => '"Inline selected: " + Object.keys(pressRelease.inline_photo_keys || {}).filter((key) => pressRelease.inline_photo_keys[key]).length',
                    'showSelectAll' => true,
                    'selectAllAction' => 'selectAllPressReleaseInlinePhotos()',
                    'clearInlineAction' => 'clearPressReleaseInlinePhotos()',
                ])
            </div>
        </div>
    </div>

    {{-- ═══ Sub-card 2: Validate Release Details ═══ --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 bg-blue-50 border-b border-blue-100">
            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">Validate Release Details</p>
                <p class="text-xs text-gray-400">Review and auto-detect date, location, contact from the submission</p>
            </div>
            <button @click="detectPressReleaseFields()" :disabled="pressReleaseDetectingFields" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="pressReleaseDetectingFields" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="pressReleaseDetectingFields ? 'Detecting...' : 'Auto Detect'"></span>
            </button>
        </div>
        <div class="px-5 py-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Press Release Date <span class="text-red-500">*</span></label>
                    <input type="text" x-model="pressRelease.details.date" @input.debounce.400ms="clearPressReleaseFieldError('date'); savePipelineState()" class="w-full border rounded-lg px-3 py-2 text-sm" :class="pressReleaseInputBorderClass('date')" placeholder="e.g. April 11, 2026">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location <span class="text-red-500">*</span></label>
                    <input type="text" x-model="pressRelease.details.location" @input.debounce.400ms="clearPressReleaseFieldError('location'); savePipelineState()" class="w-full border rounded-lg px-3 py-2 text-sm" :class="pressReleaseInputBorderClass('location')" placeholder="e.g. Miami, Florida">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact <span class="text-red-500">*</span></label>
                    <input type="text" x-model="pressRelease.details.contact" @input.debounce.400ms="clearPressReleaseFieldError('contact'); savePipelineState()" class="w-full border rounded-lg px-3 py-2 text-sm" :class="pressReleaseInputBorderClass('contact')" placeholder="e.g. Sarah Smith, media contact">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact URL <span class="text-red-500">*</span></label>
                    <input type="url" x-model="pressRelease.details.contact_url" @input.debounce.400ms="clearPressReleaseFieldError('contact_url'); savePipelineState()" class="w-full border rounded-lg px-3 py-2 text-sm" :class="pressReleaseInputBorderClass('contact_url')" placeholder="https://example.com/contact">
                </div>
            </div>

            {{-- Validation activity log --}}
            <div x-show="pressRelease.activity_log.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 max-h-48 overflow-y-auto">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Detection Log</div>
                <template x-for="(entry, idx) in pressRelease.activity_log" :key="idx">
                    <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <span class="text-gray-500 flex-shrink-0" x-text="new Date(entry.timestamp).toLocaleTimeString('en-US', { hour12: false })"></span>
                        <span class="break-words" :class="{
                            'text-green-400': entry.type === 'success',
                            'text-red-400': entry.type === 'error',
                            'text-yellow-400': entry.type === 'warning',
                            'text-blue-400': entry.type === 'info',
                            'text-gray-300': entry.type === 'step'
                        }" x-text="entry.message"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Polish toggle --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <label class="inline-flex items-center gap-3 cursor-pointer">
            <input type="checkbox" x-model="pressRelease.polish_only" @change="savePipelineState(); invalidatePromptPreview('press_release_polish_toggle', { fetch: true })" class="rounded border-gray-300 text-purple-600">
            <span class="text-sm text-gray-700">Polish grammar and structure but do not spin with AI.</span>
        </label>
    </div>

    {{-- Continue button --}}
    <div class="flex items-center justify-end">
        <button @click="continuePressReleaseStep4()" type="button" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-blue-700 font-medium" x-text="pressRelease.polish_only ? 'Continue to Polish →' : 'Continue to AI & Spin →'"></button>
    </div>
</div>
