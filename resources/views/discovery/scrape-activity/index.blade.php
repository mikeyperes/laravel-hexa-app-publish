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
                    <input type="checkbox" onchange="window.location.href = this.checked ? '?failures_only=1' : '?'" {{ request('failures_only') ? 'checked' : '' }} class="rounded border-gray-300 text-red-600">
                    Failures only
                </label>
                <select onchange="if(this.value) window.location.href='?domain='+this.value; else window.location.href='?';" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
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
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            Fetch Log
            @if($filterDomain) <span class="text-sm font-normal text-gray-500">— {{ $filterDomain }}</span> @endif
            <span class="text-sm font-normal text-gray-400">({{ $logs->total() }} entries)</span>
        </h3>
        <div class="space-y-2">
            @forelse($logs as $log)
            <div class="flex items-start gap-3 py-3 border-b border-gray-100 text-sm {{ $log->success ? '' : 'bg-red-50 rounded-lg px-3 -mx-3' }}">
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
                        <span>{{ $log->created_at->format('M j, g:ia') }}</span>
                        <span>Method: <strong>{{ $log->method ?? 'auto' }}</strong></span>
                        <span>UA: <strong>{{ $log->user_agent ?? 'chrome' }}</strong></span>
                        @if($log->http_status) <span>HTTP: <strong>{{ $log->http_status }}</strong></span> @endif
                        @if($log->response_time_ms) <span>Time: <strong>{{ $log->response_time_ms }}ms</strong></span> @endif
                        @if($log->word_count) <span>Words: <strong>{{ $log->word_count }}</strong></span> @endif
                        @if($log->fallback_used) <span class="text-orange-600">Fallback: {{ $log->fallback_used }}</span> @endif
                    </div>
                    @if(!$log->success && $log->error_message)
                        <p class="text-xs text-red-600 mt-1 break-words">{{ $log->error_message }}</p>
                    @endif
                </div>
                <div class="flex-shrink-0">
                    <span class="font-mono text-xs text-gray-400">{{ $log->domain }}</span>
                </div>
            </div>
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
                            <option value="jina">Jina Reader</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Recommended UA</label>
                        <select x-model="notesData.recommended_ua" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">None</option>
                            <option value="chrome">Chrome Desktop</option>
                            <option value="googlebot">Googlebot</option>
                            <option value="bingbot">Bingbot</option>
                            <option value="mobile">Mobile Chrome</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Working Instructions</label>
                    <textarea x-model="notesData.working_instructions" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Step-by-step instructions that work for this source..."></textarea>
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
