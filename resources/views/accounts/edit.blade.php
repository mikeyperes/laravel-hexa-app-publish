{{-- User Publishing Settings --}}
@extends('layouts.app')
@section('title', 'Edit ' . $user->name . ' — Publishing')
@section('header', 'User: ' . $user->name)

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- User info (read-only) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">User Info</h3>
        <div class="space-y-2 text-sm">
            <div class="flex gap-2">
                <span class="text-gray-400 w-20">Name:</span>
                <span class="text-gray-800">{{ $user->name }}</span>
            </div>
            <div class="flex gap-2">
                <span class="text-gray-400 w-20">Email:</span>
                <span class="text-gray-800">{{ $user->email }}</span>
            </div>
            <div class="flex gap-2">
                <span class="text-gray-400 w-20">Role:</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $user->role ?? 'user' }}</span>
            </div>
        </div>
    </div>

    {{-- Sites --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Sites ({{ $sites->count() }})</h3>
            <a href="{{ route('publish.sites.create') }}" class="text-sm text-blue-600 hover:text-blue-800">+ Add Site</a>
        </div>
        @if($sites->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No sites assigned to this user.</div>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">URL</th>
                        <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sites as $site)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2"><a href="{{ route('publish.sites.show', $site->id) }}" class="text-blue-600 hover:text-blue-800 break-words">{{ $site->name }}</a></td>
                        <td class="px-5 py-2 text-gray-500 break-words"><a href="{{ $site->url }}" target="_blank" class="hover:text-blue-600">{{ $site->url }} &#8599;</a></td>
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

    {{-- Campaigns --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Campaigns ({{ $campaigns->count() }})</h3>
        </div>
        @if($campaigns->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No campaigns for this user.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($campaigns as $campaign)
                <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                    <a href="{{ route('publish.campaigns.show', $campaign->id) }}" class="text-blue-600 hover:text-blue-800 text-sm break-words">{{ $campaign->name }}</a>
                    <span class="text-xs text-gray-400">{{ $campaign->status ?? 'draft' }}</span>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex gap-3">
        <a href="{{ route('publish.accounts.show', $user->id) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to User</a>
    </div>
</div>
@endsection
