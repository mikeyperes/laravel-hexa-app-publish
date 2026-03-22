{{-- Sites list --}}
@extends('layouts.app')
@section('title', 'Sites')
@section('header', 'WordPress Sites')

@section('content')
<div class="space-y-4">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.sites.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48" placeholder="Search name/URL...">
            <select name="account_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Accounts</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Statuses</option>
                <option value="connected" {{ request('status') === 'connected' ? 'selected' : '' }}>Connected</option>
                <option value="disconnected" {{ request('status') === 'disconnected' ? 'selected' : '' }}>Disconnected</option>
                <option value="error" {{ request('status') === 'error' ? 'selected' : '' }}>Error</option>
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
        <a href="{{ route('publish.sites.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Add Site</a>
    </div>

    <p class="text-sm text-gray-500">{{ $sites->count() }} site(s)</p>

    @if($sites->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No sites added yet.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Connection</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Campaigns</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Articles</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sites as $site)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('publish.sites.show', $site->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $site->name }}</a>
                            <p class="text-xs text-gray-400 break-words"><a href="{{ $site->url }}" target="_blank" class="hover:text-blue-500">{{ $site->url }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></p>
                        </td>
                        <td class="px-5 py-3 text-gray-600 break-words">
                            <a href="{{ route('publish.accounts.show', $site->account->id) }}" class="hover:text-blue-600">{{ $site->account->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $site->connection_type === 'wptoolkit' ? 'WP Toolkit' : 'REST API' }}</td>
                        <td class="px-5 py-3">
                            @if($site->status === 'connected')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Connected</span>
                            @elseif($site->status === 'error')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Error</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Disconnected</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $site->campaigns->count() }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $site->articles->count() }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('publish.sites.show', $site->id) }}" class="text-gray-400 hover:text-blue-600" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('publish.sites.edit', $site->id) }}" class="text-gray-400 hover:text-blue-600" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
