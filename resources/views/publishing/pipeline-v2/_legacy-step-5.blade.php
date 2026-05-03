    <div class="pipeline-step-card" :class="{ 'ring-2 ring-blue-400': currentStep === 5, 'opacity-50': !isStepAccessible(5) }">
        <button @click="toggleStep(5)" :disabled="!isStepAccessible(5)" class="pipeline-step-toggle disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(5) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(5)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(5)"><span>5</span></template>
                </span>
                <span class="font-semibold text-gray-800" x-text="stepLabels[4] || 'AI & Spin'"></span>
                <span x-show="selectedTemplate" x-cloak class="text-sm text-green-600" x-text="selectedTemplate?.name || ''"></span>
                <span x-show="Number(spunWordCount || 0) > 0" x-cloak class="text-sm text-green-600" x-text="spunWordCount + ' words'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(5) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(5)" x-cloak x-collapse class="pipeline-step-panel">

            {{-- Article Preset moved to Step 2 --}}
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">AI Model</label>
                    <select x-model="aiModel" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach(($aiModelGroups ?? []) as $company => $models)
                        <optgroup label="{{ $company }}">
                            @foreach($models as $model)
                                <option value="{{ $model['id'] }}">{{ $model['label'] }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                </div>
                <button @click="spinArticle()" :disabled="spinning" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2">
                    <svg x-show="spinning" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="spinning ? (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Polishing...' : 'Spinning...') : (_hasSpunThisSession ? (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Re-polish' : 'Re-spin') : (currentArticleType === 'press-release' && pressRelease.polish_only ? 'Polish Content' : 'Spin Article'))"></span>
                </button>
                <p x-show="_hasSpunThisSession && !spinning" x-cloak class="text-sm text-green-600 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Article spun successfully<span x-show="Number(spunWordCount || 0) > 0"> — <span x-text="spunWordCount + ' words'"></span></span>
                </p>
            </div>

            {{-- Custom prompt input — live-updates resolved prompt --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Custom Instructions <span class="text-gray-400">(takes precedence over template/preset)</span></label>
                <textarea x-model.debounce.300ms="customPrompt" @input.debounce.300ms="invalidatePromptPreview('custom_prompt', { fetch: true })" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Write in first person, focus on the financial impact, include expert quotes..."></textarea>
            </div>

            {{-- Web research toggle --}}
            <div class="mb-4">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="spinWebResearch" @change="invalidatePromptPreview('web_research_toggle', { fetch: true })" class="rounded border-gray-300 text-purple-600">
                    <span class="text-sm text-gray-700">Search online for additional supporting points</span>
                </label>
                <p x-show="spinWebResearch" x-cloak class="text-xs text-gray-400 mt-1 ml-6">The AI will search the web for real-time data, statistics, and expert opinions to strengthen the article.</p>
                <div x-show="spinWebResearch" x-cloak class="mt-2 ml-6">
                    <label class="block text-xs text-gray-500 mb-1">Supporting URL Type</label>
                    <select x-model="supportingUrlType" @change="invalidatePromptPreview('supporting_url_type', { fetch: true })" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
                        @foreach(config('hws-publish.supporting_url_types', []) as $optionKey => $option)
                            <option value="{{ $optionKey }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1" x-text="supportingUrlTypeDescription()"></p>
                </div>
            </div>

            {{-- Resolved Prompt — always visible, live-updating --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-500 font-medium">Resolved Prompt</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="refreshPromptPreview({ force: true, reason: 'manual_button' })" :disabled="promptLoading" class="text-[11px] text-blue-500 hover:text-blue-700 disabled:opacity-50">Refresh</button>
                        <span x-show="promptLoading" x-cloak class="text-xs text-blue-400 inline-flex items-center gap-1">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Updating...
                        </span>
                    </div>
                </div>
                <div class="bg-gray-900 text-gray-300 rounded-xl p-4 text-xs font-mono overflow-y-auto break-words whitespace-pre-wrap" style="max-height:400px;">
                    <pre x-show="resolvedPrompt" class="text-green-300 whitespace-pre-wrap break-words" x-text="resolvedPrompt"></pre>
                    <p x-show="!resolvedPrompt && promptLoading" x-cloak class="text-blue-400">Loading prompt...</p>
                    <p x-show="!resolvedPrompt && !promptLoading && promptPreviewDirty" x-cloak class="text-gray-500">Prompt preview is deferred until AI &amp; Spin is active or you refresh it manually.</p>
                    <p x-show="!resolvedPrompt && !promptLoading && !promptPreviewDirty" x-cloak class="text-gray-500">Prompt will load when a template or preset is selected.</p>
                </div>
            </div>

            {{-- ═══ Prompt Injections Log ═══ --}}
            <div x-show="promptLog.length > 0" x-cloak class="mb-4">
                <button @click="promptLogOpen = !promptLogOpen" class="w-full flex items-center justify-between bg-gray-800 hover:bg-gray-750 border border-gray-700 rounded-lg px-4 py-2.5 text-left transition-colors">
                    <span class="text-xs font-semibold text-gray-300 uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        Prompt Injections
                        <span class="text-gray-500 font-normal normal-case" x-text="'(' + promptLog.length + ' resolved)'"></span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="promptLogOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="promptLogOpen" x-cloak x-transition class="bg-gray-900 border border-gray-700 border-t-0 rounded-b-lg overflow-hidden">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-800">
                                <th class="text-left px-3 py-2 text-gray-500 font-medium w-40">Shortcode</th>
                                <th class="text-left px-3 py-2 text-gray-500 font-medium">Source</th>
                                <th class="text-left px-3 py-2 text-gray-500 font-medium">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(entry, idx) in promptLog" :key="idx">
                                <tr class="border-b border-gray-800/50 last:border-b-0 hover:bg-gray-800/30">
                                    <td class="px-3 py-1.5 align-top"><code class="text-blue-400 font-mono" x-text="entry.shortcode"></code></td>
                                    <td class="px-3 py-1.5 align-top text-gray-400" x-text="entry.source"></td>
                                    <td class="px-3 py-1.5 align-top">
                                        <span x-show="entry.value && entry.value !== ''" class="text-green-300 font-mono break-words" x-text="entry.value.length > 80 ? entry.value.substring(0, 80) + '...' : entry.value"></span>
                                        <span x-show="!entry.value || entry.value === ''" class="text-gray-600 italic">empty</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ═══ AI Detection Scan ═══ --}}
            <div class="mb-4 bg-gray-900 rounded-xl border border-gray-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h5 class="text-sm font-semibold text-white uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        AI Detection Scan
                    </h5>
                    <div class="flex items-center gap-3">
                        {{-- Enable/disable toggle (persisted) --}}
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400" x-text="aiDetectionEnabled ? 'Enabled' : 'Disabled'"></span>
                            <button @click="toggleAiDetection()" type="button"
                                class="relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="aiDetectionEnabled ? 'bg-purple-500' : 'bg-gray-600'"
                                role="switch" :aria-checked="aiDetectionEnabled">
                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="aiDetectionEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>
                        <span x-show="aiDetectionEnabled && aiDetectionThreshold !== null" x-cloak class="text-xs text-gray-400">Max AI: <span class="text-white" x-text="aiDetectionThreshold + '%'"></span></span>
                        <button x-show="aiDetectionEnabled && !aiDetecting && spunContent" @click="runAiDetection()" class="text-xs text-purple-400 hover:text-purple-300 inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Re-scan
                        </button>
                    </div>
                </div>

                {{-- Disabled state --}}
                <div x-show="!aiDetectionEnabled" x-cloak class="text-sm text-gray-500 text-center py-3">
                    AI detection is disabled. Toggle on to scan articles after spinning.
                </div>

                {{-- Enabled content --}}
                <div x-show="aiDetectionEnabled" x-cloak>
                    {{-- Detector rows --}}
                    <div class="space-y-2">
                        <template x-for="(det, key) in aiDetectionResults" :key="key">
                            <div class="bg-gray-800 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <template x-if="det.loading">
                                            <svg class="w-5 h-5 text-purple-400 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </template>
                                        <template x-if="!det.loading && det.success && det.passes">
                                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </template>
                                        <template x-if="!det.loading && det.success && !det.passes">
                                            <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </template>
                                        <template x-if="!det.loading && !det.success">
                                            <svg class="w-5 h-5 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        </template>
                                        <span class="text-sm font-medium text-white" x-text="det.name"></span>
                                        <span x-show="det.debug_mode" x-cloak class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-900 text-yellow-300">DEBUG</span>
                                        <a :href="'/' + key + '/settings'" target="_blank" class="text-gray-600 hover:text-gray-400" title="Settings">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        </a>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <template x-if="!det.loading && det.ai_score !== null && det.ai_score !== undefined">
                                            <span class="text-lg font-bold" :class="det.passes ? 'text-green-400' : 'text-red-400'" x-text="det.ai_score + '% AI'"></span>
                                        </template>
                                        <template x-if="!det.loading && (det.ai_score === null || det.ai_score === undefined) && !det.success">
                                            <span class="text-xs text-yellow-400">Error</span>
                                        </template>
                                        <template x-if="det.loading">
                                            <span class="text-xs text-gray-500">Scanning...</span>
                                        </template>
                                        <template x-if="!det.loading && det.success">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium" :class="det.passes ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'" x-text="det.passes ? 'PASS' : 'FAIL'"></span>
                                        </template>
                                        <button x-show="!det.loading && det.raw" @click="det.showRaw = !det.showRaw" class="text-xs text-gray-500 hover:text-gray-300" title="View raw response">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <p x-show="!det.loading && !det.success && det.message" x-cloak class="text-xs text-yellow-400 mt-1 break-words" x-text="det.message"></p>
                                {{-- Flagged sentences with checkboxes --}}
                                <div x-show="!det.loading && det.sentences && det.sentences.length > 0" x-cloak class="mt-2">
                                    <p class="text-xs text-red-400 mb-1">Flagged sentences:</p>
                                    <div class="space-y-1">
                                        <template x-for="(s, si) in (det.sentences || [])" :key="key + '_' + si">
                                            <label class="flex items-start gap-2 text-xs text-gray-400 bg-gray-900 rounded px-2 py-1.5 cursor-pointer hover:bg-gray-800">
                                                <input type="checkbox" :checked="selectedFlaggedSentences.includes(key + '_' + si)" @change="toggleFlaggedSentence(key + '_' + si, s)" class="mt-0.5 rounded border-gray-600 text-red-500 bg-gray-800">
                                                <span class="break-words" x-text="s"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                                <div x-show="det.showRaw" x-cloak class="mt-2 bg-gray-900 rounded-lg p-3 text-xs text-gray-400 font-mono break-words whitespace-pre-wrap" x-text="JSON.stringify(det.raw, null, 2)"></div>
                            </div>
                        </template>
                    </div>

                    {{-- Overall verdict --}}
                    <div x-show="!aiDetecting && Object.keys(aiDetectionResults).length > 0" x-cloak class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <template x-if="aiDetectionAllPass">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="text-sm font-semibold text-green-400">All detectors passed</span>
                                </div>
                            </template>
                            <template x-if="!aiDetectionAllPass">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    <span class="text-sm font-semibold text-red-400">Article flagged -- above AI threshold</span>
                                </div>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <button x-show="!aiDetectionAllPass" @click="processDetectionRespin()" :disabled="spinning" class="bg-red-600 text-white px-4 py-2 rounded-lg text-xs hover:bg-red-700 disabled:opacity-50 inline-flex items-center gap-2">
                                <svg x-show="spinning" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Process Changes & Respin
                            </button>
                            <button x-show="!aiDetectionAllPass" @click="ignoreDetection()" class="text-gray-400 hover:text-gray-200 px-3 py-2 text-xs">Ignore & Continue</button>
                        </div>
                    </div>

                    {{-- No detectors enabled --}}
                    <div x-show="!aiDetecting && Object.keys(aiDetectionResults).length === 0 && aiDetectionRan" x-cloak class="text-sm text-gray-500 text-center py-2">
                        No AI detectors are enabled. Configure in <a href="{{ route('publish.settings.master') }}" class="text-purple-400 hover:text-purple-300">Settings</a>.
                    </div>

                    {{-- Loading state --}}
                    <div x-show="aiDetecting" x-cloak class="text-xs text-purple-400 text-center py-2 inline-flex items-center justify-center gap-2 w-full">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Running AI detection scan...
                    </div>

                    {{-- Detector status — always visible before scan runs --}}
                    <div x-show="!aiDetectionRan && !aiDetecting" x-cloak class="space-y-2 py-2">
                        <p class="text-xs text-gray-500 mb-2">Detection runs automatically after spin completes.</p>
                        @foreach($aiDetectors as $key => $det)
                        <div class="flex items-center gap-3 bg-gray-800 rounded-lg px-3 py-2">
                            @if($det['enabled'] && $det['has_key'])
                                <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif(!$det['installed'])
                                <svg class="w-4 h-4 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            @else
                                <svg class="w-4 h-4 text-yellow-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            @endif
                            <span class="text-sm text-white font-medium">{{ $det['name'] }}</span>
                            @if(!$det['installed'])
                                <span class="text-[10px] text-gray-500">Not installed</span>
                            @elseif(!$det['enabled'])
                                <span class="text-[10px] text-gray-500">Disabled</span>
                            @elseif(!$det['has_key'])
                                <span class="text-[10px] text-yellow-400">No API key</span>
                            @else
                                <span class="text-[10px] text-green-400">Ready</span>
                            @endif
                            @if($det['debug_mode'])
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-900 text-yellow-300" title="Debug mode uses mock/test responses without consuming API credits">DEBUG</span>
                            @endif
                        </div>
                        @endforeach
                        @if(collect($aiDetectors)->where('enabled', true)->where('has_key', true)->isEmpty())
                            <p class="text-[11px] text-gray-500 mt-1">No detectors are ready. Configure API keys in <a href="{{ route('publish.settings.master') }}" class="text-purple-400 hover:text-purple-300">Settings</a>.</p>
                        @endif
                        <p class="text-[10px] text-gray-600 mt-1">Debug mode: uses mock/test responses without consuming API credits. Scores are simulated.</p>
                    </div>
                </div>
            </div>


            {{-- Spin error --}}
            <div x-show="spinError" x-cloak class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-red-700" x-text="spinError"></p>
            </div>

            {{-- AI Call Report --}}
            <div x-show="lastAiCall" x-cloak class="mb-4 bg-gray-900 rounded-xl border border-gray-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">AI Call Report</h5>
                    <span class="text-xs text-gray-500" x-text="lastAiCall?.timestamp_utc || ''"></span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="text-gray-500">Provider</span>
                        <p class="font-medium text-white" x-text="lastAiCall?.provider || '—'"></p>
                    </div>
                    <div>
                        <span class="text-gray-500">Model</span>
                        <p class="font-medium text-white" x-text="lastAiCall?.model || '—'"></p>
                    </div>
                    <div>
                        <span class="text-gray-500">Cost</span>
                        <p class="font-medium text-green-400" x-text="'$' + (lastAiCall?.cost || 0).toFixed(4)"></p>
                    </div>
                    <div>
                        <span class="text-gray-500">Tokens</span>
                        <p class="font-medium text-white" x-text="(lastAiCall?.usage?.input_tokens || 0) + ' in / ' + (lastAiCall?.usage?.output_tokens || 0) + ' out'"></p>
                    </div>
                </div>
            </div>

        </div>
    </div>

