{{-- Campaigns list --}}
@extends('layouts.app')
@section('title', 'Campaigns')
@section('header', 'Campaigns')

@section('content')
<div class="space-y-4">

    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('campaigns.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48" placeholder="Search name/topic...">
            <select name="account_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Accounts</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
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
        <a href="{{ route('campaigns.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Campaign</a>
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
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Schedule</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Mode</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Total Created</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase">Next Run</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($campaigns as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('campaigns.show', $c->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $c->name }}</a>
                            <p class="text-xs text-gray-400 font-mono">{{ $c->campaign_id }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-600 break-words">{{ $c->account->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-600 break-words">{{ $c->site->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $c->articles_per_interval }}/{{ $c->interval_unit }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $c->delivery_mode }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            <div class="font-medium">{{ number_format((int) ($c->articles_count ?? 0)) }}</div>
                            <div class="text-[11px] text-gray-400">historical total</div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2" x-data="{ enabled: {{ $c->status === 'active' ? 'true' : 'false' }}, toggling: false }">
                                <button @click="
                                    toggling = true;
                                    fetch('/campaigns/{{ $c->id }}/' + (enabled ? 'pause' : 'activate'), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } })
                                        .then(r => r.json()).then(d => { if (d.success) { enabled = !enabled; } toggling = false; })
                                        .catch(() => toggling = false);
                                " :disabled="toggling" type="button"
                                    class="relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:opacity-50"
                                    :class="enabled ? 'bg-green-500' : 'bg-gray-300'"
                                    role="switch" :aria-checked="enabled">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                </button>
                                <span class="text-xs" :class="enabled ? 'text-green-700' : 'text-gray-400'" x-text="enabled ? 'Active' : 'Paused'"></span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $c->next_run_at ? $c->next_run_at->diffForHumans() : '—' }}</td>
                        <td class="px-5 py-3" x-data="{ deleting: false }">
                            <button @click="
                                if (!confirm('Delete campaign {{ addslashes($c->name) }}?')) return;
                                deleting = true;
                                fetch('/campaigns/{{ $c->id }}', { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } })
                                    .then(r => r.json()).then(d => { if (d.success) location.reload(); else { alert(d.message); deleting = false; } })
                                    .catch(() => deleting = false);
                            " :disabled="deleting" class="text-gray-300 hover:text-red-500 transition-colors disabled:opacity-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
