{{-- Site detail --}}
@extends('layouts.app')
@section('title', $site->name)
@section('header', $site->name)

@section('content')
<div class="space-y-6" x-data="siteActions()">

    {{-- Site header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">User: {{ $site->user->name ?? 'Unassigned' }}</p>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $site->name }}</h2>
                <p class="text-sm break-words mt-1"><a href="{{ $site->url }}" target="_blank" class="text-blue-600 hover:text-blue-800">{{ $site->url }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></p>
                <p class="text-xs text-gray-400 mt-2">Connection: {{ $site->connection_type === 'wptoolkit' ? 'WP Toolkit' : 'REST API' }}</p>
                @if($site->last_connected_at)
                    <p class="text-xs text-gray-400">Last connected: {{ $site->last_connected_at->diffForHumans() }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if($site->status === 'connected')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Connected</span>
                @elseif($site->status === 'error')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Error</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Disconnected</span>
                @endif
                <button @click="testConnection()" :disabled="testing" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="testing" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="testing ? 'Testing...' : 'Test Connection'"></span>
                </button>
                <a href="{{ route('publish.sites.edit', $site->id) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Edit</a>
            </div>
        </div>
        @if($site->last_error)
            <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-2 break-words">{{ $site->last_error }}</div>
        @endif
        <div x-show="testResult" x-cloak class="mt-3 rounded-lg px-4 py-2 text-sm" :class="testSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="testResult"></span>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $site->campaigns->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Campaigns</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $site->articles->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Articles</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $site->articles->where('status', 'published')->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Published</p>
        </div>
    </div>

    {{-- Campaigns on this site --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Campaigns</h3>
            <a href="{{ route('publish.campaigns.create', ['user_id' => $site->user_id, 'site_id' => $site->id]) }}" class="text-sm text-blue-600 hover:text-blue-800">+ New Campaign</a>
        </div>
        @if($site->campaigns->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No campaigns on this site yet.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Schedule</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Mode</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($site->campaigns as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2"><a href="{{ route('publish.campaigns.show', $c->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $c->name }}</a></td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $c->articles_per_interval }}/{{ $c->interval_unit }}</td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $c->delivery_mode }}</td>
                        <td class="px-5 py-2">
                            @if($c->status === 'active')<span class="text-xs text-green-600 font-medium">Active</span>
                            @elseif($c->status === 'paused')<span class="text-xs text-yellow-600 font-medium">Paused</span>
                            @else <span class="text-xs text-gray-400">{{ ucfirst($c->status) }}</span>@endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Recent articles --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Recent Articles</h3>
            <a href="{{ route('publish.articles.index', ['site_id' => $site->id]) }}" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
        </div>
        @if($site->articles->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No articles yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($site->articles->sortByDesc('created_at')->take(10) as $article)
                <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                    <div>
                        <a href="{{ route('publish.articles.show', $article->id) }}" class="text-sm text-blue-600 hover:text-blue-800 break-words">{{ $article->title ?? '(untitled)' }}</a>
                        <p class="text-xs text-gray-400">{{ $article->article_id }} &middot; {{ $article->created_at->diffForHumans() }}</p>
                    </div>
                    @if($article->status === 'published')<span class="text-xs text-green-600 font-medium">Published</span>
                    @elseif($article->status === 'completed')<span class="text-xs text-gray-400">Completed</span>
                    @elseif($article->status === 'review')<span class="text-xs text-yellow-600 font-medium">Review</span>
                    @else <span class="text-xs text-gray-400">{{ ucfirst($article->status) }}</span>@endif
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function siteActions() {
    return {
        testing: false, testResult: '', testSuccess: false,
        async testConnection() {
            this.testing = true; this.testResult = '';
            try {
                const res = await fetch('{{ route("publish.sites.test", $site->id) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.testSuccess = data.success;
                this.testResult = data.message;
                if (data.success) setTimeout(() => location.reload(), 1000);
            } catch (e) { this.testSuccess = false; this.testResult = 'Error: ' + e.message; }
            this.testing = false;
        }
    };
}
</script>
@endpush
