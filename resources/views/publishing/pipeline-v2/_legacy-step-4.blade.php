
    {{-- ══════════════════════════════════════════════════════════════
         Step 4: Fetch Articles from Source
         ══════════════════════════════════════════════════════════════ --}}
    <template x-if="currentArticleType !== 'press-release'">
    <div class="pipeline-step-card" :class="{ 'ring-2 ring-blue-400': currentStep === 4, 'opacity-50': !isStepAccessible(4) }">
        <button @click="toggleStep(4)" :disabled="!isStepAccessible(4)" class="pipeline-step-toggle disabled:cursor-not-allowed">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(4) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(4)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(4)"><span>4</span></template>
                </span>
                <span class="font-semibold text-gray-800" x-text="stepLabels[3] || 'Fetch Articles from Source'"></span>
                <span x-show="currentArticleType !== 'press-release' && checkResults.length > 0" x-cloak class="text-sm" :class="checkPassCount === sources.length ? 'text-green-600' : 'text-yellow-600'"
                      x-text="checkPassCount + '/' + sources.length + ' extracted'"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(4) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(4)" x-cloak x-collapse class="pipeline-step-panel">
            @include('app-publish::publishing.pipeline.partials.press-release-validate-step')
            <div x-show="currentArticleType !== 'press-release'" x-cloak>
            {{-- Extraction Options --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 mb-4">
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Method</label>
                        <select x-model="extractMethod" class="border border-gray-300 rounded px-2 py-1 text-xs">
                            <option value="auto">Auto</option>
                            <option value="readability">Readability</option>
                            <option value="structured">Structured</option>
                            <option value="heuristic">Heuristic</option>
                            <option value="css">CSS Selector</option>
                            <option value="regex">Regex</option>
                            <option value="jina">Jina Reader</option>
                            <option value="claude">Claude AI</option>
                            <option value="gpt">GPT</option>
                            <option value="grok">Grok</option>
                            <option value="gemini">Gemini</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">User Agent</label>
                        <select x-model="checkUserAgent" class="border border-gray-300 rounded px-2 py-1 text-xs">
                            <option value="chrome">Chrome Desktop</option>
                            <option value="firefox">Firefox</option>
                            <option value="safari">Safari</option>
                            <option value="mobile">Mobile</option>
                            <option value="googlebot">Googlebot</option>
                            <option value="bingbot">Bingbot</option>
                            <option value="bot">HWS Bot</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Retries</label>
                        <select x-model="extractRetries" class="border border-gray-300 rounded px-2 py-1 text-xs w-14">
                            <option value="0">0</option>
                            <option value="1" selected>1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Timeout</label>
                        <select x-model="extractTimeout" class="border border-gray-300 rounded px-2 py-1 text-xs w-16">
                            <option value="10">10s</option>
                            <option value="20" selected>20s</option>
                            <option value="30">30s</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Min Words</label>
                        <input type="number" x-model="extractMinWords" class="border border-gray-300 rounded px-2 py-1 text-xs w-16" min="10" max="1000">
                    </div>
                    <label class="flex items-center gap-1 text-xs text-gray-500 pb-0.5">
                        <input type="checkbox" x-model="extractAutoFallback" class="rounded border-gray-300 text-blue-600 w-3.5 h-3.5">
                        Auto-fallback
                    </label>
                    <div class="ml-auto text-right">
                        <p class="text-[11px] text-gray-500 uppercase tracking-wide">Step action</p>
                        <p class="text-xs text-gray-400">Use the bottom action bar to fetch article(s) and continue.</p>
                    </div>
                </div>
            </div>

            {{-- Fetch Configuration Summary --}}
            <div class="bg-gray-900 rounded-lg border border-gray-700 p-4 mb-4">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Fetch Configuration</div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Method</span>
                        <span class="text-white font-medium" x-text="extractMethod === 'auto' ? 'Auto (Readability + Structured + Heuristic + Jina)' : extractMethod === 'readability' ? 'Readability' : extractMethod === 'structured' ? 'Structured Data' : extractMethod === 'heuristic' ? 'DOM Heuristic' : extractMethod === 'css' ? 'CSS Selector' : extractMethod === 'regex' ? 'Regex' : extractMethod === 'jina' ? 'Jina Reader' : extractMethod === 'claude' ? 'Claude AI' : extractMethod === 'gpt' ? 'GPT' : extractMethod === 'grok' ? 'Grok' : extractMethod === 'gemini' ? 'Gemini' : extractMethod"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">User Agent</span>
                        <span class="text-white font-medium" x-text="checkUserAgent === 'chrome' ? 'Chrome Desktop' : checkUserAgent === 'firefox' ? 'Firefox Desktop' : checkUserAgent === 'safari' ? 'Safari macOS' : checkUserAgent === 'mobile' ? 'Mobile Chrome' : checkUserAgent === 'googlebot' ? 'Googlebot' : checkUserAgent === 'bingbot' ? 'Bingbot' : 'HWS Bot'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Retries</span>
                        <span class="text-white font-medium" x-text="extractRetries + (extractRetries == 1 ? ' attempt' : ' attempts')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Timeout</span>
                        <span class="text-white font-medium" x-text="extractTimeout + 's per request'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Min Words</span>
                        <span class="text-white font-medium" x-text="extractMinWords + ' words'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Auto-fallback</span>
                        <span class="font-medium" :class="extractAutoFallback ? 'text-green-400' : 'text-red-400'" x-text="extractAutoFallback ? 'On — Googlebot retry' : 'Off'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Redirects</span>
                        <span class="text-green-400 font-medium">Follow</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Retry Delay</span>
                        <span class="text-white font-medium">Escalating (1s, 2s, 3s)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Max Wait</span>
                        <span class="text-white font-medium" x-text="(() => { let t = parseInt(extractTimeout); let r = parseInt(extractRetries); let wait = t; for(let i=1;i<=r;i++) wait += t + i; return wait + 's worst case'; })()"></span>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-700">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">UA String</span>
                        <span class="text-gray-500 font-mono break-all text-right" x-text="checkUserAgent === 'chrome' ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0' : checkUserAgent === 'firefox' ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Firefox/134.0' : checkUserAgent === 'safari' ? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7_4) Safari/605.1.15' : checkUserAgent === 'googlebot' ? 'Mozilla/5.0 (compatible; Googlebot/2.1)' : 'Mozilla/5.0 (compatible; HWSPublishBot/1.0)'"></span>
                    </div>
                </div>
            </div>

            {{-- Extraction Activity Log --}}
            <div x-show="checkLog.length > 0" x-cloak class="bg-gray-900 rounded-xl border border-gray-700 p-4 mb-4 max-h-48 overflow-y-auto">
                <template x-for="(entry, idx) in checkLog" :key="idx">
                    <div class="flex items-start gap-2 py-1 text-xs font-mono" :class="idx > 0 ? 'border-t border-gray-800' : ''">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span :class="{
                            'text-green-400': entry.type === 'success',
                            'text-red-400': entry.type === 'error',
                            'text-blue-400': entry.type === 'info',
                            'text-gray-400': entry.type === 'step',
                        }" x-text="entry.message" class="break-words"></span>
                    </div>
                </template>
            </div>

            <div x-show="checkResults.length > 0" x-cloak class="space-y-3">
                <template x-for="(result, idx) in checkResults" :key="idx">
                    <div class="rounded-lg border" :class="approvedSources.includes(idx) ? 'border-green-400 bg-green-50 ring-1 ring-green-300' : (discardedSources.includes(idx) ? 'border-gray-200 bg-gray-100 opacity-40' : (result.success ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'))">
                        {{-- Header row — click to expand --}}
                        <div @click="toggleSourceExpand(idx)" class="flex items-start gap-3 px-4 py-3 cursor-pointer">
                            <span class="mt-0.5 flex-shrink-0">
                                <svg x-show="result.success" class="w-7 h-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!result.success" class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p x-show="result.title" class="text-base font-semibold text-gray-900 break-words" x-text="result.title"></p>
                                <a :href="result.url" target="_blank" @click.stop class="text-xs break-all inline-flex items-center gap-1 hover:underline mt-1" :class="result.success ? 'text-blue-600' : 'text-red-600'">
                                    <span x-text="result.url"></span>
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <div x-show="result.success" class="flex items-center gap-3 mt-2">
                                    <span class="text-lg font-bold text-green-700" x-text="result.word_count + ' words'"></span>
                                    <span class="text-xs text-gray-400" x-text="result.message"></span>
                                </div>
                                <p x-show="!result.success" class="text-sm text-red-600 font-medium mt-1" x-text="'Extraction failed — ' + (result.message || 'unknown error')"></p>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <button x-show="!result.success" @click.stop="markFailedSource(idx)" :disabled="_markingBrokenIdx === idx" class="text-xs text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 px-2.5 py-1 rounded inline-flex items-center gap-1">
                                    <svg x-show="_markingBrokenIdx === idx" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Mark Broken
                                </button>
                                <button x-show="!result.success" @click.stop="searchFailedSource(idx)" class="text-xs text-blue-700 hover:text-blue-900 px-2.5 py-1 bg-blue-50 hover:bg-blue-100 rounded">Search Title</button>
                                <button x-show="!result.success" @click.stop="markAndSearchFailedSource(idx)" :disabled="_markingBrokenIdx === idx" class="text-xs text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 px-2.5 py-1 rounded inline-flex items-center gap-1">
                                    <svg x-show="_markingBrokenIdx === idx" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Broken + Search
                                </button>
                                <button x-show="!result.success" @click.stop="removeSource(idx)" class="text-xs text-gray-500 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50">Remove</button>
                                <svg x-show="result.success" class="w-5 h-5 text-gray-400 transition-transform" :class="expandedSources.includes(idx) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        {{-- Extracted article content — expand/collapse, default EXPANDED --}}
                        <div x-show="result.success && result.text" x-cloak class="border-t border-green-200">
                            <div class="bg-white rounded-b-lg shadow-sm">
                                <div class="flex items-center justify-between px-6 pt-4 pb-2">
                                    <button @click.stop="expandedSources.includes(idx) ? expandedSources = expandedSources.filter(i => i !== idx) : expandedSources.push(idx)" class="text-xs text-gray-400 hover:text-gray-600 inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 transition-transform" :class="expandedSources.includes(idx) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        <span x-text="expandedSources.includes(idx) ? 'Extracted content' : 'Show extracted content'"></span>
                                    </button>
                                </div>
                                {{-- Full content — NO scroll, NO iframe, NO max-height --}}
                                <div x-show="expandedSources.includes(idx)" class="px-6 pb-4" style="font-size:15px;line-height:1.8;color:#374151;">
                                    <div class="prose max-w-none break-words" x-html="(result.formatted_html || result.text || '').replace(/<img[^>]*>/gi, function(m) { return m.replace(/<img /i, '<img style=\'max-width:100%;height:auto;\' '); })"></div>
                                </div>
                                {{-- Action buttons with separator --}}
                                <div x-show="result.success" class="px-6 py-3 border-t border-gray-100 flex items-center gap-3">
                                    <button @click.stop="discardSource(idx)" class="text-sm font-medium px-4 py-2 rounded-lg inline-flex items-center gap-1.5 transition-colors" :class="discardedSources.includes(idx) ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span x-text="discardedSources.includes(idx) ? 'Discarded' : 'Discard'"></span>
                                    </button>
                                    <button @click.stop="flagAsBroken(idx)" class="text-sm font-medium px-4 py-2 rounded-lg inline-flex items-center gap-1.5 bg-orange-100 text-orange-700 hover:bg-orange-200 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        Flag as Broken
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            </div>
        </div>
    </div>
    </template>

    {{-- ══════════════════════════════════════════════════════════════
         Step 5: AI & Spin
         ══════════════════════════════════════════════════════════════ --}}
