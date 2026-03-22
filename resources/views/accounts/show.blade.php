{{-- Publishing User detail --}}
@extends('layouts.app')
@section('title', $user->name . ' — Publishing')
@section('header', $user->name)

@section('content')
<div class="space-y-6">

    {{-- User header card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $user->name }}</h2>
                <p class="text-sm text-gray-500 break-words mt-1">{{ $user->email }}</p>
                <p class="text-sm text-gray-500 mt-1">Role: <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $user->role ?? 'user' }}</span></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('publish.accounts.edit', $user->id) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Edit</a>
            </div>
        </div>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $sites->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Sites</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $campaigns->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Campaigns</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $articleStats['total'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Articles</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $articleStats['published'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Published</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-yellow-600">{{ $articleStats['review'] }}</p>
            <p class="text-xs text-gray-500 mt-1">In Review</p>
        </div>
    </div>

    {{-- Sites --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Sites ({{ $sites->count() }})</h3>
            <a href="{{ route('publish.sites.create') }}" class="text-sm text-blue-600 hover:text-blue-800">+ Add Site</a>
        </div>
        @if($sites->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No sites added yet.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">URL</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Connection</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sites as $site)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2"><a href="{{ route('publish.sites.show', $site->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $site->name }}</a></td>
                        <td class="px-5 py-2 text-gray-500 break-words"><a href="{{ $site->url }}" target="_blank" class="hover:text-blue-600">{{ $site->url }} &#8599;</a></td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $site->connection_type === 'wptoolkit' ? 'WP Toolkit' : 'REST API' }}</td>
                        <td class="px-5 py-2">
                            @if($site->status === 'connected')
                                <span class="text-xs text-green-600 font-medium">Connected</span>
                            @elseif($site->status === 'error')
                                <span class="text-xs text-red-600 font-medium">Error</span>
                            @else
                                <span class="text-xs text-gray-400">Disconnected</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Templates --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Templates ({{ $templates->count() }})</h3>
            <a href="{{ route('publish.templates.create') }}" class="text-sm text-blue-600 hover:text-blue-800">+ New Template</a>
        </div>
        @if($templates->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No templates created yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($templates as $template)
                <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                    <div>
                        <a href="{{ route('publish.templates.show', $template->id) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm break-words">{{ $template->name }}</a>
                        @if($template->article_type)
                            <span class="ml-2 text-xs text-gray-400">{{ $template->article_type }}</span>
                        @endif
                    </div>
                    <a href="{{ route('publish.templates.edit', $template->id) }}" class="text-gray-400 hover:text-blue-600 text-xs">Edit</a>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Campaigns --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Campaigns ({{ $campaigns->count() }})</h3>
            <a href="{{ route('publish.campaigns.create') }}" class="text-sm text-blue-600 hover:text-blue-800">+ New Campaign</a>
        </div>
        @if($campaigns->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No campaigns created yet.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Campaign</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Schedule</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Mode</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($campaigns as $campaign)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2"><a href="{{ route('publish.campaigns.show', $campaign->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $campaign->name }}</a></td>
                        <td class="px-5 py-2 text-gray-500 break-words">{{ $campaign->site->name ?? '—' }}</td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $campaign->articles_per_interval ?? '—' }}/{{ $campaign->interval_unit ?? '—' }}</td>
                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $campaign->delivery_mode ?? '—' }}</td>
                        <td class="px-5 py-2">
                            @if($campaign->status === 'active')
                                <span class="text-xs text-green-600 font-medium">Active</span>
                            @elseif($campaign->status === 'paused')
                                <span class="text-xs text-yellow-600 font-medium">Paused</span>
                            @else
                                <span class="text-xs text-gray-400">{{ ucfirst($campaign->status ?? 'draft') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
