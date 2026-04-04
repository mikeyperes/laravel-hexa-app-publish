{{-- Users are managed in System > Users & Roles --}}
@extends('layouts.app')
@section('title', 'Users')
@section('header', 'Users')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
    <p class="text-gray-500 mb-3">Users are managed in System settings.</p>
    @if(Route::has('settings.users'))
        <a href="{{ route('settings.users') }}" class="text-blue-600 hover:text-blue-800 text-sm">Go to Users & Roles &rarr;</a>
    @endif
</div>
@endsection
