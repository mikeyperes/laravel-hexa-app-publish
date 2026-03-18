{{-- cPanel Accounts list grouped by server --}}
@extends('layouts.app')
@section('title', 'cPanel Accounts')
@section('header', 'cPanel Accounts')

@section('content')
<div class="space-y-4">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.servers.accounts') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48" placeholder="Search domain/user/owner...">
            <select name="server" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Servers</option>
                @foreach($servers as $s)
                    <option value="{{ $s->id }}" {{ request('server') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
    </div>

    {{-- Stats --}}
    <div class="flex flex-wrap gap-4 text-sm text-gray-500">
        <span>{{ count($accounts) }} account(s)</span>
        <span>{{ collect($accounts)->where('status', 'active')->count() }} active</span>
        <span>across {{ $servers->count() }} server(s)</span>
    </div>

    @if(collect($accounts)->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No cPanel accounts found. Click <strong>Sync</strong> on a server to pull accounts from WHM.</p>
            <a href="{{ route('publish.servers.index') }}" class="text-blue-600 hover:text-blue-800 text-sm mt-2 inline-block">Go to Servers</a>
        </div>
    @else
        @foreach($servers as $server)
            @php $serverAccounts = $accountsByServer->get($server->id, collect()); @endphp
            @if($serverAccounts->isEmpty()) @continue @endif

            <div class="space-y-0">
                {{-- Server header --}}
                <div class="bg-gray-800 text-white rounded-t-xl px-5 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h3 class="font-semibold">{{ $server->name }}</h3>
                        <span class="text-xs bg-gray-700 px-2 py-0.5 rounded">{{ $serverAccounts->count() }} accounts</span>
                        <code class="text-xs text-gray-400">{{ $server->hostname }}:{{ $server->port }}</code>
                    </div>
                    <div class="flex items-center gap-2" x-data="{ syncing: false, syncResult: null }">
                        <button type="button" @click="
                            syncing = true; syncResult = null;
                            fetch('{{ route('publish.servers.sync-accounts', $server) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                            }).then(r => r.json()).then(d => { syncResult = d; syncing = false; if (d.success) setTimeout(() => location.reload(), 800); })
                            .catch(e => { syncResult = { success: false, message: e.message }; syncing = false; });
                        " :disabled="syncing" class="text-xs bg-gray-700 text-gray-200 px-3 py-1 rounded hover:bg-gray-600 disabled:opacity-60 inline-flex items-center gap-1.5">
                            <svg x-show="syncing" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="syncing ? 'Syncing...' : 'Sync'"></span>
                        </button>
                        @if($server->last_synced_at)
                            <span class="text-xs text-gray-400">Synced {{ $server->last_synced_at->diffForHumans() }}</span>
                        @endif
                        <div x-show="syncResult" x-cloak class="text-xs px-2 py-1 rounded" :class="syncResult?.success ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
                            <span x-text="syncResult?.message"></span>
                        </div>
                    </div>
                </div>

                {{-- Accounts table --}}
                <div class="bg-white rounded-b-xl shadow-sm border border-gray-200 border-t-0">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Domain</th>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Username</th>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Owner</th>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Package</th>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Disk Used</th>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Disk Limit</th>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($serverAccounts->sortBy('domain') as $acct)
                            @php
                                $diskPct = $acct->disk_limit_mb > 0 ? round(($acct->disk_used_mb / $acct->disk_limit_mb) * 100, 1) : 0;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $acct->status === 'suspended' ? 'opacity-50' : '' }}">
                                <td class="px-5 py-2">
                                    <a href="https://{{ $acct->domain }}" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $acct->domain }} <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                                </td>
                                <td class="px-5 py-2 font-mono text-xs text-gray-600">{{ $acct->username }}</td>
                                <td class="px-5 py-2 text-gray-600 text-xs">{{ $acct->owner }}</td>
                                <td class="px-5 py-2 text-gray-500 text-xs break-words">{{ $acct->package ?? '—' }}</td>
                                <td class="px-5 py-2 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="{{ $diskPct > 90 ? 'text-red-600 font-semibold' : ($diskPct > 70 ? 'text-yellow-600' : 'text-gray-600') }}">
                                            {{ $acct->disk_used_mb > 1024 ? number_format($acct->disk_used_mb / 1024, 1) . ' GB' : $acct->disk_used_mb . ' MB' }}
                                        </span>
                                        @if($acct->disk_limit_mb > 0)
                                            <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full {{ $diskPct > 90 ? 'bg-red-500' : ($diskPct > 70 ? 'bg-yellow-500' : 'bg-green-500') }}" style="width: {{ min($diskPct, 100) }}%"></div>
                                            </div>
                                            <span class="text-gray-400">{{ $diskPct }}%</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-2 text-xs text-gray-500">
                                    {{ $acct->disk_limit_mb > 0 ? ($acct->disk_limit_mb > 1024 ? number_format($acct->disk_limit_mb / 1024, 1) . ' GB' : $acct->disk_limit_mb . ' MB') : 'Unlimited' }}
                                </td>
                                <td class="px-5 py-2">
                                    @if($acct->status === 'active')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                                    @elseif($acct->status === 'suspended')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800" title="{{ $acct->suspend_reason }}">Suspended</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($acct->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2 text-xs text-gray-400 font-mono">{{ $acct->ip_address ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
