{{-- Campaign detail --}}
@extends('layouts.app')
@section('title', $campaign->name)
@section('header', $campaign->name)

@section('content')
<div class="space-y-6" x-data="campaignActions()">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-mono text-gray-400 mb-1">{{ $campaign->campaign_id }}</p>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $campaign->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">User: {{ $campaign->user->name ?? 'Unassigned' }}</p>
                <p class="text-sm text-gray-500">Site: <a href="{{ route('publish.sites.show', $campaign->site->id) }}" class="text-blue-600 hover:text-blue-800">{{ $campaign->site->name }}</a></p>
                @if($campaign->template)
                    <p class="text-sm text-gray-500">Template: <a href="{{ route('publish.templates.show', $campaign->template->id) }}" class="text-blue-600 hover:text-blue-800">{{ $campaign->template->name }}</a></p>
                @endif
                @if($campaign->description)
                    <p class="text-sm text-gray-500 mt-2 break-words">{{ $campaign->description }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if($campaign->status === 'active')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                    <button @click="pause()" :disabled="acting" class="bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-600 disabled:opacity-60 inline-flex items-center gap-2">
                        <svg x-show="acting" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="acting ? 'Pausing...' : 'Pause'"></span>
                    </button>
                @elseif($campaign->status === 'paused' || $campaign->status === 'draft')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $campaign->status === 'paused' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($campaign->status) }}</span>
                    <button @click="activate()" :disabled="acting" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60 inline-flex items-center gap-2">
                        <svg x-show="acting" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="acting ? 'Activating...' : 'Activate'"></span>
                    </button>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($campaign->status) }}</span>
                @endif
                <a href="{{ route('publish.campaigns.edit', $campaign->id) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Edit</a>
            </div>
        </div>
        <div x-show="actionResult" x-cloak class="mt-3 rounded-lg px-4 py-2 text-sm" :class="actionSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="actionResult"></span>
        </div>
    </div>

    {{-- Config --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Configuration</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><p class="text-xs text-gray-400 uppercase">Schedule</p><p class="text-gray-700 mt-1">{{ $campaign->articles_per_interval }}/{{ $campaign->interval_unit }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Delivery</p><p class="text-gray-700 mt-1">{{ ucwords(str_replace('-', ' ', $campaign->delivery_mode)) }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Topic</p><p class="text-gray-700 mt-1 break-words">{{ $campaign->topic ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Article Type</p><p class="text-gray-700 mt-1">{{ $campaign->article_type ? ucwords(str_replace('-', ' ', $campaign->article_type)) : 'Template default' }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">AI Engine</p><p class="text-gray-700 mt-1">{{ $campaign->ai_engine ? ucfirst($campaign->ai_engine) : 'Template default' }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Max Links</p><p class="text-gray-700 mt-1">{{ $campaign->max_links_per_article ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Last Run</p><p class="text-gray-700 mt-1">{{ $campaign->last_run_at ? $campaign->last_run_at->diffForHumans() : 'Never' }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Next Run</p><p class="text-gray-700 mt-1">{{ $campaign->next_run_at ? $campaign->next_run_at->diffForHumans() : '—' }}</p></div>
        </div>
    </div>

    {{-- Articles --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Articles ({{ $campaign->articles->count() }})</h3>
            <a href="{{ route('publish.articles.index', ['campaign_id' => $campaign->id]) }}" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
        </div>
        @if($campaign->articles->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No articles spawned yet.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Words</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($campaign->articles as $article)
                    <tr class="hover:bg-gray-50 {{ $article->status === 'completed' ? 'opacity-50' : '' }}">
                        <td class="px-5 py-2"><a href="{{ route('publish.articles.show', $article->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $article->title ?? '(untitled)' }}</a></td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $article->word_count ? number_format($article->word_count) : '—' }}</td>
                        <td class="px-5 py-2">
                            @if($article->status === 'published')<span class="text-xs text-green-600 font-medium">Published</span>
                            @elseif($article->status === 'review')<span class="text-xs text-yellow-600 font-medium">Review</span>
                            @elseif($article->status === 'completed')<span class="text-xs text-gray-400">Completed</span>
                            @else <span class="text-xs text-gray-400">{{ ucfirst($article->status) }}</span>@endif
                        </td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $article->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function campaignActions() {
    return {
        acting: false, actionResult: '', actionSuccess: false,
        async activate() { await this.doAction('{{ route("publish.campaigns.activate", $campaign->id) }}'); },
        async pause() { await this.doAction('{{ route("publish.campaigns.pause", $campaign->id) }}'); },
        async doAction(url) {
            this.acting = true; this.actionResult = '';
            try {
                const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } });
                const data = await res.json();
                this.actionSuccess = data.success; this.actionResult = data.message;
                if (data.success) setTimeout(() => location.reload(), 800);
            } catch (e) { this.actionSuccess = false; this.actionResult = 'Error: ' + e.message; }
            this.acting = false;
        }
    };
}
</script>
@endpush
