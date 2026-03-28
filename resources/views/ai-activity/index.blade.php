@extends('layouts.app')
@section('title', 'AI Activity')
@section('header', 'AI Activity')

@section('content')
<div class="max-w-7xl mx-auto space-y-4" x-data="{ expandedRow: null }">

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_requests']) }}</p>
            <p class="text-xs text-gray-500">Total Requests</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_tokens']) }}</p>
            <p class="text-xs text-gray-500">Total Tokens</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">${{ number_format($summary['total_cost'], 4) }}</p>
            <p class="text-xs text-gray-500">Total Cost</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ number_format($summary['success_count']) }}</p>
            <p class="text-xs text-gray-500">Successful</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-red-600">{{ number_format($summary['error_count']) }}</p>
            <p class="text-xs text-gray-500">Errors</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('publish.ai-activity.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">User</label>
                <select name="user_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ ($filters['user_id'] ?? '') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Model</label>
                <select name="model" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Models</option>
                    @foreach($models as $model)
                        <option value="{{ $model }}" {{ ($filters['model'] ?? '') === $model ? 'selected' : '' }}>{{ $model }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Agent</label>
                <select name="agent" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Agents</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent }}" {{ ($filters['agent'] ?? '') === $agent ? 'selected' : '' }}>{{ $agent }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Filter</button>
            <a href="{{ route('publish.ai-activity.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">Clear</a>
        </form>
    </div>

    {{-- Activity Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">User</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Cost</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Date / Time</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Model</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Page</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Tokens</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">IP</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Key</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Agent</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                <tr class="hover:bg-gray-50 cursor-pointer {{ $log->success ? '' : 'bg-red-50' }}" @click="expandedRow = expandedRow === {{ $log->id }} ? null : {{ $log->id }}">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800">{{ $log->user?->name ?? 'System' }}</p>
                        <p class="text-xs text-gray-400">{{ $log->user?->email ?? '' }}</p>
                    </td>
                    <td class="px-4 py-3 text-right font-medium {{ $log->cost > 0.01 ? 'text-orange-600' : 'text-green-600' }}">${{ number_format($log->cost, 4) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <p class="text-gray-800">{{ $log->created_at->setTimezone(config('app.timezone', 'America/New_York'))->format('M j, g:ia') }} <span class="text-xs text-gray-400">{{ $log->created_at->setTimezone(config('app.timezone', 'America/New_York'))->format('T') }}</span></p>
                        <p class="text-xs text-gray-400">{{ $log->created_at->utc()->format('H:i') }} UTC</p>
                    </td>
                    <td class="px-4 py-3 text-gray-700 text-xs font-mono">{{ $log->model }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 break-all max-w-[150px]">{{ $log->request_url ? basename(parse_url($log->request_url, PHP_URL_PATH)) : '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-gray-800">{{ number_format($log->total_tokens) }}</p>
                        <p class="text-xs text-gray-400">{{ number_format($log->prompt_tokens) }}+{{ number_format($log->completion_tokens) }}</p>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $log->ip_address }}</td>
                    <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $log->api_key_masked }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ $log->agent }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expandedRow === {{ $log->id }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </td>
                </tr>
                <tr x-show="expandedRow === {{ $log->id }}" x-cloak>
                    <td colspan="10" class="px-4 py-4 bg-gray-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">System Prompt</p>
                                <pre class="text-xs text-gray-600 bg-white rounded-lg p-3 border border-gray-200 max-h-48 overflow-y-auto whitespace-pre-wrap break-words">{{ $log->system_prompt ?: '—' }}</pre>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Response</p>
                                <pre class="text-xs text-gray-600 bg-white rounded-lg p-3 border border-gray-200 max-h-48 overflow-y-auto whitespace-pre-wrap break-words">{{ Str::limit(strip_tags($log->response_content), 2000) ?: '—' }}</pre>
                            </div>
                        </div>
                        @if($log->request_url)
                            <p class="mt-2 text-xs text-gray-400">Full URL: {{ $log->request_url }}</p>
                        @endif
                        @if($log->error_message)
                            <div class="mt-2 text-xs text-red-600 bg-red-50 rounded-lg p-2">{{ $log->error_message }}</div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400">No AI activity logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">{{ $logs->withQueryString()->links() }}</div>
        @endif
    </div>

    {{-- Cost Summary by Model & Time Period --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Cost Summary by Model</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Model / Provider</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">4 Hours</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">24 Hours</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">1 Week</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">1 Month</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">1 Year</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($costSummary as $row)
                <tr class="{{ $row['type'] === 'provider' ? 'bg-gray-50 font-semibold' : '' }}">
                    <td class="px-4 py-2 {{ $row['type'] === 'model' ? 'pl-8 text-gray-600 font-mono text-xs' : 'text-gray-800' }}">{{ $row['label'] }}</td>
                    <td class="px-4 py-2 text-right {{ $row['4h'] > 0 ? 'text-gray-800' : 'text-gray-300' }}">${{ number_format($row['4h'], 4) }}</td>
                    <td class="px-4 py-2 text-right {{ $row['24h'] > 0 ? 'text-gray-800' : 'text-gray-300' }}">${{ number_format($row['24h'], 4) }}</td>
                    <td class="px-4 py-2 text-right {{ $row['1w'] > 0 ? 'text-gray-800' : 'text-gray-300' }}">${{ number_format($row['1w'], 4) }}</td>
                    <td class="px-4 py-2 text-right {{ $row['1m'] > 0 ? 'text-gray-800' : 'text-gray-300' }}">${{ number_format($row['1m'], 4) }}</td>
                    <td class="px-4 py-2 text-right {{ $row['1y'] > 0 ? 'text-gray-800' : 'text-gray-300' }}">${{ number_format($row['1y'], 4) }}</td>
                </tr>
                @endforeach
                @if(empty($costSummary))
                <tr><td colspan="6" class="px-4 py-4 text-center text-gray-400">No cost data yet.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
