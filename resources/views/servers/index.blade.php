{{-- WHM Servers list --}}
@extends('layouts.app')
@section('title', 'Servers')
@section('header', 'WHM Servers')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $servers->count() }} server(s) configured.</p>
    </div>

    @if($servers->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No WHM servers configured. Add servers via the WHM settings.</p>
        </div>
    @else
        @foreach($servers as $server)
            @include('app-publish::servers.partials.server-card', ['server' => $server])
        @endforeach
    @endif
</div>
@endsection
