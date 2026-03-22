{{-- Publish Article Pipeline --}}
@extends('layouts.app')
@section('title', 'Publish Article')
@section('header', 'Publish Article')

@section('content')
<div class="space-y-6">

    {{-- Pipeline visual flow --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Publishing Pipeline</h3>
        <p class="text-sm text-gray-500 mb-6">Each article flows through these steps from creation to publication.</p>

        <div class="flex flex-wrap items-center gap-2">
            @php
                $steps = [
                    ['num' => 1, 'label' => 'User', 'desc' => 'Select user'],
                    ['num' => 2, 'label' => 'Preset', 'desc' => 'Choose preset'],
                    ['num' => 3, 'label' => 'Website', 'desc' => 'Target site'],
                    ['num' => 4, 'label' => 'Sources', 'desc' => 'Gather content'],
                    ['num' => 5, 'label' => 'Check', 'desc' => 'Verify sources'],
                    ['num' => 6, 'label' => 'Prompt', 'desc' => 'AI instructions'],
                    ['num' => 7, 'label' => 'AI Model', 'desc' => 'Generate article'],
                    ['num' => 8, 'label' => 'Spin', 'desc' => 'Rewrite content'],
                    ['num' => 9, 'label' => 'Editor', 'desc' => 'Review & edit'],
                    ['num' => 10, 'label' => 'Prepare', 'desc' => 'Format & images'],
                    ['num' => 11, 'label' => 'Publish', 'desc' => 'Push to WordPress'],
                ];
            @endphp

            @foreach($steps as $i => $step)
                <div class="flex items-center gap-2">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-300 flex items-center justify-center text-sm font-bold text-gray-500">
                            {{ $step['num'] }}
                        </div>
                        <span class="text-xs font-medium text-gray-700 mt-1">{{ $step['label'] }}</span>
                        <span class="text-xs text-gray-400">{{ $step['desc'] }}</span>
                    </div>
                    @if($i < count($steps) - 1)
                        <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Coming soon message --}}
    <div class="bg-blue-50 rounded-xl border border-blue-200 p-6 text-center">
        <svg class="w-12 h-12 text-blue-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <h3 class="text-lg font-semibold text-blue-800 mb-2">Pipeline Coming Soon</h3>
        <p class="text-sm text-blue-600">The full interactive publishing pipeline will be built in Phase 3. For now, use the individual sections in the sidebar to manage drafts, bookmarks, prompts, presets, and settings.</p>
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('publish.drafts.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <h4 class="font-medium text-gray-800">Drafted Articles</h4>
            <p class="text-sm text-gray-500 mt-1">View and manage your article drafts.</p>
        </a>
        <a href="{{ route('publish.presets.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <h4 class="font-medium text-gray-800">Article Presets</h4>
            <p class="text-sm text-gray-500 mt-1">Configure default publishing settings.</p>
        </a>
        <a href="{{ route('publish.prompts.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <h4 class="font-medium text-gray-800">AI Prompts</h4>
            <p class="text-sm text-gray-500 mt-1">Manage reusable AI generation prompts.</p>
        </a>
    </div>
</div>
@endsection
