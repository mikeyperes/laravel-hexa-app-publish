{{-- Legacy /publish/users/{id} page — kept as alias until the new merged profile at /settings/users/{id} is confirmed. --}}
@extends('layouts.app')
@section('title', $user->name . ' — Publishing Profile')
@section('header', $user->name . ' — Publishing Profile')

@section('content')
    {{-- Legacy header card (core's profile page shows its own, this one is only for the legacy URL) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $user->name }}</h2>
                <p class="text-sm text-gray-500 break-words mt-1">{{ $user->email }}</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 mt-1">{{ $user->role ?? 'user' }}</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('settings.users.show', $user->id) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">&rarr; Open canonical profile</a>
                @if(Route::has('settings.users.edit'))
                    <a href="{{ route('settings.users.edit', $user->id) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Edit User</a>
                @endif
            </div>
        </div>
    </div>

    @include('app-publish::publishing.partials.user-profile-sections')
@endsection
