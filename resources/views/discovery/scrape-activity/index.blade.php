@extends('layouts.app')
@section('title', 'Scrape Activity')
@section('header', 'Scrape Activity')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="scrapeActivity()">

    {{-- Domain Stats Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Source Domains</h3>
            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" onchange="const url = new URL(window.location.href); if (this.checked) { url.searchParams.set('failures_only', '1'); } else { url.searchParams.delete('failures_only'); } window.location.href = url.toString();" {{ request('failures_only') ? 'checked' : '' }} class="rounded border-gray-300 text-red-600">
                    Failures only
                </label>
                <select onchange="const url = new URL(window.location.href); if (this.value) { url.searchParams.set('domain', this.value); } else { url.searchParams.delete('domain'); } window.location.href = url.toString();" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                    <option value="">All domains</option>
                    @foreach($domainStats as $ds)
                        <option value="{{ $ds->domain }}" {{ $filterDomain === $ds->domain ? 'selected' : '' }}>{{ $ds->domain }} ({{ $ds->fails }} fails / {{ $ds->total }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500 uppercase">
                        <th class="py-2 pr-4">Domain</th>
                        <th class="py-2 pr-4">Total</th>
                        <th class="py-2 pr-4">Passes</th>
                        <th class="py-2 pr-4">Fails</th>
                        <th class="py-2 pr-4">Fail Rate</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($domainStats as $ds)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-2 pr-4 font-mono text-gray-800">{{ $ds->domain }}</td>
                        <td class="py-2 pr-4">{{ $ds->total }}</td>
                        <td class="py-2 pr-4 text-green-600">{{ $ds->passes }}</td>
                        <td class="py-2 pr-4 text-red-600">{{ $ds->fails }}</td>
                        <td class="py-2 pr-4">
                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $ds->total > 0 && ($ds->fails / $ds->total) > 0.5 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                {{ $ds->total > 0 ? round(($ds->fails / $ds->total) * 100) : 0 }}%
                            </span>
                        </td>
                        <td class="py-2 pr-4">
                            @if(in_array($ds->domain, $bannedDomains))
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Banned</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @endif
                        </td>
                        <td class="py-2 flex items-center gap-2">
                            <a href="?domain={{ $ds->domain }}" class="text-xs text-blue-600 hover:text-blue-800">View logs</a>
                            @if(in_array($ds->domain, $bannedDomains))
                                <button @click="unban('{{ $ds->domain }}')" class="text-xs text-green-600 hover:text-green-800">Unban</button>
                            @else
                                <button @click="ban('{{ $ds->domain }}')" class="text-xs text-red-600 hover:text-red-800">Ban</button>
                            @endif
                            <button @click="openNotes('{{ $ds->domain }}')" class="text-xs text-purple-600 hover:text-purple-800">Notes</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Activity Log --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h3 class="text-lg font-bold text-gray-800">
                Fetch Log
                @if($filterDomain) <span class="text-sm font-normal text-gray-500">— {{ $filterDomain }}</span> @endif
                <span class="text-sm font-normal text-gray-400">({{ $logs->total() }} entries)</span>
            </h3>
            @if($filterDomain)
                <span class="text-xs text-blue-600 bg-blue-50 border border-blue-200 rounded-full px-3 py-1">Detailed mode: request logs expanded</span>
            @endif
        </div>
        <div class="space-y-3">
            @forelse($logs as $log)
            @php
                $requestMeta = is_array($log->request_meta) ? $log->request_meta : [];
                $responseMeta = is_array($log->response_meta) ? $log->response_meta : [];
                $attemptLog = is_array($log->attempt_log) ? $log->attempt_log : [];
                $requestHeaders = is_array($log->request_headers) ? $log->request_headers : [];
                $responseHeaders = is_array($log->response_headers) ? $log->response_headers : [];
                $fetchInfo = is_array($log->fetch_info) ? $log->fetch_info : [];
                $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
                $detailsOpen = !empty($filterDomain);
            @endphp
            <details class="border border-gray-200 rounded-xl overflow-hidden {{ $log->success ? 'bg-white' : 'bg-red-50 border-red-200' }}" {{ $detailsOpen ? 'open' : '' }}>
                <summary class="list-none cursor-pointer px-4 py-4">
                    <div class="flex items-start gap-3 text-sm">
                        <div class="flex-shrink-0 pt-0.5">
                            @if($log->success)
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @else
                                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-mono text-xs text-gray-500 break-all">{{ $log->url }}</p>
                            <div class="flex flex-wrap gap-3 mt-1 text-xs text-gray-500">
                                <span>{{ $log->created_at->format('M j, Y g:ia') }}</span>
                                <span>Extractor: <strong>{{ $log->method ?? 'auto' }}</strong></span>
                                <span>HTTP Method: <strong>{{ $log->http_method ?? 'GET' }}</strong></span>
                                <span>UA: <strong>{{ $log->user_agent ?? 'chrome' }}</strong></span>
                                @if($log->http_status) <span>HTTP: <strong>{{ $log->http_status }}</strong></span> @endif
                                @if($log->response_reason) <span>Reason: <strong>{{ $log->response_reason }}</strong></span> @endif
                                @if($log->response_time_ms) <span>Time: <strong>{{ $log->response_time_ms }}ms</strong></span> @endif
                                @if($log->word_count) <span>Words: <strong>{{ $log->word_count }}</strong></span> @endif
                                @if($log->fallback_used) <span class="text-orange-600">Fallback: {{ $log->fallback_used }}</span> @endif
                                @if(!empty($attemptLog)) <span>Attempts: <strong>{{ count($attemptLog) }}</strong></span> @endif
                            </div>
                            @if(!$log->success && $log->error_message)
                                <p class="text-xs text-red-600 mt-1 break-words">{{ $log->error_message }}</p>
                            @endif
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <span class="font-mono text-xs text-gray-400">{{ $log->domain }}</span>
                            <p class="text-[11px] text-blue-600 mt-1">{{ $detailsOpen ? 'Expanded' : 'Show request details' }}</p>
                        </div>
                    </div>
                </summary>

                <div class="border-t border-gray-200 px-4 py-4 grid grid-cols-1 xl:grid-cols-2 gap-4 text-sm bg-white">
                    <div class="space-y-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Request</h4>
                            <dl class="space-y-2 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-500">Logged At</dt>
                                    <dd class="text-gray-800">{{ $log->created_at->format('M j, Y g:ia T') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">Requested URL</dt>
                                    <dd class="font-mono text-xs text-gray-700 break-all">{{ $log->url }}</dd>
                                </div>
                                @if($log->effective_url)
                                <div>
                                    <dt class="text-xs text-gray-500">Effective URL</dt>
                                    <dd class="font-mono text-xs text-gray-700 break-all">{{ $log->effective_url }}</dd>
                                </div>
                                @endif
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <dt class="text-xs text-gray-500">Extractor</dt>
                                        <dd class="text-gray-800">{{ $log->method ?? 'auto' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">HTTP Method</dt>
                                        <dd class="text-gray-800">{{ $log->http_method ?? 'GET' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">User Agent Preset</dt>
                                        <dd class="text-gray-800">{{ $log->user_agent ?? 'chrome' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Source</dt>
                                        <dd class="text-gray-800">{{ $log->source ?? 'pipeline' }}</dd>
                                    </div>
                                </div>
                                @if(!empty($requestMeta['resolved_user_agent']))
                                <div>
                                    <dt class="text-xs text-gray-500">Resolved User Agent</dt>
                                    <dd class="font-mono text-xs text-gray-700 break-all">{{ $requestMeta['resolved_user_agent'] }}</dd>
                                </div>
                                @endif
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <dt class="text-xs text-gray-500">Timeout</dt>
                                        <dd class="text-gray-800">{{ $log->timeout ? $log->timeout . 's' : '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Retries</dt>
                                        <dd class="text-gray-800">{{ $log->retries ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Attempts</dt>
                                        <dd class="text-gray-800">{{ $requestMeta['attempts'] ?? (count($attemptLog) ?: '—') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Follow Redirects</dt>
                                        <dd class="text-gray-800">
                                            @if(array_key_exists('follow_redirects', $requestMeta))
                                                {{ $requestMeta['follow_redirects'] ? 'Yes' : 'No' }}
                                            @else
                                                —
                                            @endif
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Response</h4>
                            <dl class="space-y-2 text-sm">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <dt class="text-xs text-gray-500">HTTP Status</dt>
                                        <dd class="text-gray-800">{{ $log->http_status ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Reason</dt>
                                        <dd class="text-gray-800">{{ $log->response_reason ?? ($responseMeta['reason'] ?? '—') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Response Time</dt>
                                        <dd class="text-gray-800">{{ $log->response_time_ms ? $log->response_time_ms . 'ms' : '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Word Count</dt>
                                        <dd class="text-gray-800">{{ $log->word_count ?: '—' }}</dd>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <dt class="text-xs text-gray-500">Content Type</dt>
                                        <dd class="text-gray-800 break-all">{{ $responseMeta['content_type'] ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Content Length</dt>
                                        <dd class="text-gray-800">{{ $responseMeta['content_length'] ?? '—' }}</dd>
                                    </div>
                                </div>
                                @if(!empty($responseMeta['failure_reason']))
                                <div>
                                    <dt class="text-xs text-gray-500">Failure Reason</dt>
                                    <dd class="text-red-700">{{ $responseMeta['failure_reason'] }}</dd>
                                </div>
                                @endif
                                @if(!empty($responseMeta['suggestion']))
                                <div>
                                    <dt class="text-xs text-gray-500">Suggested Next Action</dt>
                                    <dd class="text-orange-700">{{ $responseMeta['suggestion'] }}</dd>
                                </div>
                                @endif
                                @if(!empty($responseMeta['fallback_source']) || $log->fallback_used)
                                <div>
                                    <dt class="text-xs text-gray-500">Fallback</dt>
                                    <dd class="text-orange-700">{{ $log->fallback_used ?? $responseMeta['fallback_source'] }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        @if(!empty($attemptLog))
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Attempt History</h4>
                            <div class="space-y-3">
                                @foreach($attemptLog as $attempt)
                                    @php($attemptResponseHeaders = is_array($attempt['response_headers'] ?? null) ? $attempt['response_headers'] : [])
                                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600 mb-2">
                                            <span class="font-semibold text-gray-800">Attempt {{ $attempt['attempt'] ?? ($loop->index + 1) }}</span>
                                            @if(!empty($attempt['started_at'])) <span>{{ $attempt['started_at'] }}</span> @endif
                                            @if(array_key_exists('status', $attempt)) <span>HTTP {{ $attempt['status'] ?: '—' }}</span> @endif
                                            @if(!empty($attempt['reason'])) <span>{{ $attempt['reason'] }}</span> @endif
                                            @if(!empty($attempt['response_time_ms'])) <span>{{ $attempt['response_time_ms'] }}ms</span> @endif
                                        </div>
                                        @if(!empty($attempt['request_url']))
                                            <p class="font-mono text-xs text-gray-600 break-all">{{ $attempt['request_url'] }}</p>
                                        @endif
                                        @if(!empty($attempt['effective_url']) && $attempt['effective_url'] !== ($attempt['request_url'] ?? null))
                                            <p class="font-mono text-xs text-blue-700 break-all mt-1">Effective: {{ $attempt['effective_url'] }}</p>
                                        @endif
                                        @if(!empty($attempt['error']))
                                            <p class="text-xs text-red-700 mt-2 break-words">{{ $attempt['error'] }}</p>
                                        @endif
                                        @if(!empty($attempt['content_type']) || !empty($attempt['content_length']))
                                            <p class="text-xs text-gray-500 mt-2">
                                                @if(!empty($attempt['content_type'])) Type: {{ $attempt['content_type'] }} @endif
                                                @if(!empty($attempt['content_length']))<span class="ml-3">Length: {{ $attempt['content_length'] }}</span>@endif
                                            </p>
                                        @endif
                                        @if(!empty($attemptResponseHeaders) || !empty($attempt['body_snippet']))
                                            <details class="mt-2">
                                                <summary class="text-xs text-blue-600 cursor-pointer">Attempt headers and snippet</summary>
                                                @if(!empty($attemptResponseHeaders))
                                                    <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-3 text-[11px] overflow-x-auto whitespace-pre-wrap break-all">{{ json_encode($attemptResponseHeaders, $jsonFlags) }}</pre>
                                                @endif
                                                @if(!empty($attempt['body_snippet']))
                                                    <pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 text-[11px] text-gray-700 overflow-x-auto whitespace-pre-wrap break-all">{{ $attempt['body_snippet'] }}</pre>
                                                @endif
                                            </details>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Headers</h4>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Request Headers</p>
                                    <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 text-[11px] overflow-x-auto whitespace-pre-wrap break-all">{{ !empty($requestHeaders) ? json_encode($requestHeaders, $jsonFlags) : 'No request headers captured.' }}</pre>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Response Headers</p>
                                    <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 text-[11px] overflow-x-auto whitespace-pre-wrap break-all">{{ !empty($responseHeaders) ? json_encode($responseHeaders, $jsonFlags) : 'No response headers captured.' }}</pre>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Response Body Snippet</h4>
                            <pre class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-[11px] text-gray-700 overflow-x-auto whitespace-pre-wrap break-all">{{ $log->response_body_snippet ?: 'No response body snippet captured.' }}</pre>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Raw Diagnostics</h4>
                            <pre class="bg-gray-900 text-gray-100 rounded-lg p-3 text-[11px] overflow-x-auto whitespace-pre-wrap break-all">{{ !empty($fetchInfo) ? json_encode($fetchInfo, $jsonFlags) : 'No raw fetch diagnostics captured.' }}</pre>
                        </div>
                    </div>
                </div>
            </details>
            @empty
            <p class="text-sm text-gray-400 py-4">No scrape activity logged yet.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $logs->withQueryString()->links() }}</div>
    </div>

    {{-- Notes Modal --}}
    <div x-show="notesOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="notesOpen = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6" @click.stop>
            <h4 class="text-lg font-bold text-gray-800 mb-1">Source Notes</h4>
            <p class="text-sm text-gray-500 mb-4 font-mono" x-text="notesDomain"></p>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <textarea x-model="notesData.notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="General notes about this source..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Recommended Method</label>
                        <select x-model="notesData.recommended_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">None</option>
                            <option value="auto">Auto</option>
                            <option value="readability">Readability</option>
                            <option value="structured">Structured Data</option>
                            <option value="heuristic">DOM Heuristic</option>
                            <option value="regex">Regex</option>
                            <option value="css">CSS Extractor</option>
                            <option value="jina">Jina Reader</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Recommended UA</label>
                        <select x-model="notesData.recommended_ua" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">None</option>
                            <option value="chrome">Chrome Desktop</option>
                            <option value="mobile">Mobile Chrome</option>
                            <option value="firefox">Firefox</option>
                            <option value="safari">Safari</option>
                            <option value="googlebot">Googlebot</option>
                            <option value="bingbot">Bingbot</option>
                            <option value="bot">Generic Bot</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Working Instructions</label>
                    <textarea x-model="notesData.working_instructions" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Step-by-step instructions that work for this source..."></textarea>
                    <p class="mt-1 text-[11px] text-gray-400">These notes feed the live source access strategy so future scrape attempts can prioritize the right method and user agent.</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button @click="notesOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                <button @click="saveNotes()" :disabled="savingNotes" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50">
                    <span x-text="savingNotes ? 'Saving...' : 'Save Notes'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function scrapeActivity() {
    return {
        notesOpen: false,
        notesDomain: '',
        notesData: { notes: '', recommended_method: '', recommended_ua: '', working_instructions: '' },
        savingNotes: false,

        async ban(domain) {
            if (!confirm('Ban ' + domain + ' from all future scraping?')) return;
            await fetch('/publish/scrape-activity/ban', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ domain })
            });
            location.reload();
        },

        async unban(domain) {
            await fetch('/publish/scrape-activity/unban', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ domain })
            });
            location.reload();
        },

        async openNotes(domain) {
            this.notesDomain = domain;
            this.notesData = { notes: '', recommended_method: '', recommended_ua: '', working_instructions: '' };
            const resp = await fetch('/publish/scrape-activity/note?domain=' + encodeURIComponent(domain), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await resp.json();
            if (data.note) {
                this.notesData = {
                    notes: data.note.notes || '',
                    recommended_method: data.note.recommended_method || '',
                    recommended_ua: data.note.recommended_ua || '',
                    working_instructions: data.note.working_instructions || '',
                };
            }
            this.notesOpen = true;
        },

        async saveNotes() {
            this.savingNotes = true;
            await fetch('/publish/scrape-activity/note', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ domain: this.notesDomain, ...this.notesData })
            });
            this.savingNotes = false;
            this.notesOpen = false;
        },
    };
}
</script>
@endsection
