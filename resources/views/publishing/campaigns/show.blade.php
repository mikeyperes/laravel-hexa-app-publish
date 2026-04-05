@extends('layouts.app')
@section('title', $campaign->name . ' — Campaign')
@section('header', 'Campaign: ' . $campaign->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-4" x-data="campaignShow()">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <p class="text-xs font-mono text-gray-400">{{ $campaign->campaign_id }}</p>
                    @if($campaign->status === 'active')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700">Active</span>
                    @elseif($campaign->status === 'paused')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-yellow-100 text-yellow-700">Paused</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ ucfirst($campaign->status) }}</span>
                    @endif
                    @if($campaign->auto_publish)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-purple-100 text-purple-700">Auto-Publish</span>
                    @endif
                </div>
                <h2 class="text-xl font-semibold text-gray-800">{{ $campaign->name }}</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('campaigns.create', ['id' => $campaign->id]) }}" class="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 px-3 py-1.5 rounded-lg">Edit</a>
                <button @click="runNow('draft')" :disabled="running" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-1">
                    <svg x-show="running" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Run as Draft
                </button>
                <button @click="runNow('publish')" :disabled="running" class="text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 disabled:opacity-50 inline-flex items-center gap-1">
                    Instant Publish
                </button>
            </div>
        </div>
        <div x-show="runResult" x-cloak class="mt-3 p-3 rounded-lg text-sm border" :class="runSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" x-text="runResult"></div>
        <div x-show="runLog.length > 0" x-cloak class="mt-3 bg-gray-900 rounded-lg border border-gray-700 p-3 max-h-48 overflow-y-auto">
            <template x-for="(entry, idx) in runLog" :key="idx">
                <p class="text-xs font-mono py-0.5" :class="{'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-yellow-400': entry.type === 'warning', 'text-blue-400': entry.type === 'info', 'text-gray-400': entry.type === 'step'}">
                    <span class="text-gray-500" x-text="entry.time"></span> <span x-text="entry.message"></span>
                </p>
            </template>
        </div>
    </div>

    {{-- Details (row layout) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-2">
        <h3 class="font-semibold text-gray-800 mb-3">Details</h3>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">User</span><p class="text-sm text-gray-800">{{ $campaign->user->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Site</span><p class="text-sm text-gray-800">{{ $campaign->site->name ?? '—' }} @if($campaign->site)<span class="text-xs text-gray-400">({{ $campaign->site->url }})</span>@endif</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Campaign Preset</span><p class="text-sm text-gray-800">{{ $campaign->campaignPreset->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">AI Template</span><p class="text-sm text-gray-800">{{ $campaign->template->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">WP Preset</span><p class="text-sm text-gray-800">{{ $campaign->wpPreset->name ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Author</span><p class="text-sm text-gray-800">{{ $campaign->author ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Post Status</span><p class="text-sm text-gray-800">{{ ucfirst($campaign->post_status ?? 'draft') }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Schedule</span><p class="text-sm text-gray-800">{{ $campaign->articles_per_interval }} post(s) / {{ $campaign->interval_unit }}{{ $campaign->run_at_time ? ' at ' . $campaign->run_at_time : '' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Timezone</span><p class="text-sm text-gray-800">{{ $campaign->timezone ?? 'America/New_York' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Last Run</span><p class="text-sm text-gray-800">{{ $campaign->last_run_at ? $campaign->last_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') . ' (' . $campaign->last_run_at->utc()->format('H:i') . ' UTC)' : 'Never' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Next Run</span><p class="text-sm text-gray-800">{{ $campaign->next_run_at ? $campaign->next_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') . ' (' . $campaign->next_run_at->utc()->format('H:i') . ' UTC)' : '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Auto-Publish</span><p class="text-sm text-gray-800">{{ $campaign->auto_publish ? 'Yes — fully automated' : 'No — manual review' }}</p></div>
        <div class="flex items-start gap-3 py-1.5"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Created</span><p class="text-sm text-gray-800">{{ $campaign->created_at ? $campaign->created_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : '—' }} by {{ $campaign->creator->name ?? '—' }}</p></div>
    </div>

    {{-- Preset Settings (expandable) --}}
    @include('app-publish::partials.preset-fields', ['prefix' => 'template', 'label' => 'AI Template Settings'])
    @include('app-publish::partials.preset-fields', ['prefix' => 'preset', 'label' => 'WordPress Preset Settings'])

    {{-- Campaign History (articles) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Campaign History ({{ $campaign->articles->count() }} articles)</h3>
        </div>
        @forelse($campaign->articles as $article)
        <div class="p-4 border-b border-gray-100 hover:bg-gray-50 flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <a href="{{ route('publish.articles.show', $article->id) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 break-words">{{ $article->title ?: 'Untitled' }}</a>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $article->article_id }}
                    @if($article->wp_post_url) &middot; <a href="{{ $article->wp_post_url }}" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-0.5">WP Post <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> @endif
                    &middot; {{ $article->word_count ? number_format($article->word_count) . ' words' : '' }}
                    &middot; {{ $article->created_at ? $article->created_at->diffForHumans() : '' }}
                </p>
            </div>
            @if($article->status === 'completed' || $article->status === 'published')
                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700">{{ ucfirst($article->status) }}</span>
            @else
                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">{{ ucfirst($article->status) }}</span>
            @endif
        </div>
        @empty
        <div class="p-8 text-center text-gray-400 text-sm">No articles created by this campaign yet.</div>
        @endforelse
    </div>

    {{-- Run Logs --}}
    @if(isset($runLogs) && $runLogs->count() > 0)
    <div class="bg-gray-900 rounded-xl border border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-white uppercase tracking-wide mb-3">Cron Run Logs ({{ $runLogs->count() }})</h3>
        <div class="space-y-1 max-h-64 overflow-y-auto">
            @foreach($runLogs as $log)
            <div class="flex items-start gap-2 text-xs font-mono py-1 {{ !$loop->first ? 'border-t border-gray-800' : '' }}">
                <span class="text-gray-500 flex-shrink-0">{{ $log->created_at }}</span>
                <span class="text-gray-300 break-words">{{ $log->action }}: {{ $log->description }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Links --}}
    <div class="flex gap-4 text-xs text-gray-400">
        <a href="{{ route('campaigns.index') }}" class="hover:text-blue-600">&larr; All Campaigns</a>
        <a href="{{ route('campaigns.create', ['id' => $campaign->id]) }}" class="hover:text-blue-600">Edit Campaign</a>
        @if(Route::has('publish.schedule.index'))
            <a href="{{ route('publish.schedule.index') }}" class="hover:text-blue-600">Cron Schedule</a>
        @endif
    </div>
</div>

@push('scripts')
@include('app-publish::partials.preset-fields-mixin')
<script>
function campaignShow() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

    const templateData = @json($campaign->template);
    const presetData = @json($campaign->wpPreset);

    return {
        ...presetFieldsMixin('template'),
        ...presetFieldsMixin('preset'),
        template_schema: @json(\hexa_app_publish\Publishing\Templates\Models\PublishTemplate::getFieldSchema()),
        preset_schema: @json(\hexa_app_publish\Publishing\Presets\Models\PublishPreset::getFieldSchema()),
        ...presetFieldsMethods,

        running: false, runResult: '', runSuccess: false,
        runLog: [],

        init() {
            if (templateData) this.loadPresetFields('template', templateData);
            if (presetData) this.loadPresetFields('preset', presetData);
        },

        async runNow(mode) {
            if (!confirm('Run campaign now as ' + mode + '?')) return;
            this.running = true; this.runResult = ''; this.runLog = [];
            try {
                const r = await fetch('{{ route("campaigns.run-now", $campaign->id) }}', { method: 'POST', headers, body: JSON.stringify({ mode }) });
                const d = await r.json();
                this.runSuccess = d.success;
                this.runResult = d.message;
                this.runLog = d.log || [];
                if (d.success && d.article_url) {
                    this.runResult += ' — View: ' + d.article_url;
                }
            } catch(e) { this.runSuccess = false; this.runResult = 'Error: ' + e.message; }
            this.running = false;
        },
    };
}
</script>
@endpush
@endsection
