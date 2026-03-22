{{-- Campaigns list --}}
@extends('layouts.app')
@section('title', 'Campaigns')
@section('header', 'Campaigns')

@section('content')
<div class="space-y-4">

    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.campaigns.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48" placeholder="Search name/topic...">
            <select name="user_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Statuses</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>Paused</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
        <a href="{{ route('publish.campaigns.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Campaign</a>
    </div>

    <p class="text-sm text-gray-500">{{ $campaigns->count() }} campaign(s)</p>

    @if($campaigns->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No campaigns created yet.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Campaign</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Schedule</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Mode</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Articles</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Next Run</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($campaigns as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('publish.campaigns.show', $c->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $c->name }}</a>
                            <p class="text-xs text-gray-400 font-mono">{{ $c->campaign_id }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-600 break-words">{{ $c->user->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-3 text-gray-600 break-words">{{ $c->site->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $c->articles_per_interval }}/{{ $c->interval_unit }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $c->delivery_mode }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $c->articles->count() }}</td>
                        <td class="px-5 py-3">
                            @if($c->status === 'active')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @elseif($c->status === 'paused')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Paused</span>
                            @elseif($c->status === 'draft')<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Draft</span>
                            @else <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($c->status) }}</span>@endif
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $c->next_run_at ? $c->next_run_at->diffForHumans() : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
