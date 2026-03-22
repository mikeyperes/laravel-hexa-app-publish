{{-- Publishing Users list --}}
@extends('layouts.app')
@section('title', 'Publishing Users')
@section('header', 'Publishing Users')

@section('content')
<div class="space-y-4">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('publish.accounts.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56" placeholder="Search name, email...">
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
    </div>

    {{-- Stats bar --}}
    <div class="flex flex-wrap gap-4 text-sm text-gray-500">
        <span>{{ $users->count() }} user(s)</span>
        <span>{{ $users->sum('sites_count') }} total sites</span>
        <span>{{ $users->sum('campaigns_count') }} total campaigns</span>
        <span>{{ $users->sum('articles_count') }} total articles</span>
    </div>

    {{-- Users table --}}
    @if($users->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No users found.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Sites</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Campaigns</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Articles</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('publish.accounts.show', $user->id) }}" class="text-blue-600 hover:text-blue-800 font-medium break-words">{{ $user->name }}</a>
                            <p class="text-xs text-gray-400 break-words">{{ $user->email }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $user->role ?? 'user' }}</span>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $user->sites_count }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $user->campaigns_count }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $user->articles_count }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $user->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('publish.accounts.show', $user->id) }}" class="text-gray-400 hover:text-blue-600" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('publish.accounts.edit', $user->id) }}" class="text-gray-400 hover:text-blue-600" title="Edit">
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
