@extends('layouts.app')
@section('title', 'Campaign Presets')
@section('header', 'Campaign Presets')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    @if(session('status'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editPreset ? 'Edit Campaign Preset' : 'New Campaign Preset' }}</h3>
                    <p class="mt-1 text-sm text-gray-500">This form is rendered from the registered Hexa Core form definition. No hardcoded campaign preset field list remains here.</p>
                </div>
                @if($editPreset)
                    <a href="{{ route('campaigns.presets.index') }}" class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:border-gray-300">New Preset</a>
                @endif
            </div>

            <x-hexa-form :form="$form" :values="$formValues" :context="$formContext" render-tag>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        {{ $editPreset ? 'Update Preset' : 'Create Preset' }}
                    </button>
                    @if($editPreset)
                        <a href="{{ route('campaigns.presets.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Cancel</a>
                    @endif
                </div>
            </x-hexa-form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900">Preset Summary</h3>
            @if($editPreset)
                <div class="mt-4 space-y-3 text-sm text-gray-600">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400">Name</p>
                        <p class="mt-1 font-medium text-gray-900">{{ $editPreset->name }}</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Source Method</p>
                            <p class="mt-1">{{ $editPreset->source_method ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Final Article Method</p>
                            <p class="mt-1">{{ $editPreset->final_article_method ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Frequency</p>
                            <p class="mt-1">{{ ucfirst($editPreset->frequency ?: 'daily') }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Posts Per Run</p>
                            <p class="mt-1">{{ $editPreset->posts_per_run ?: 1 }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400">Queries</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse(($editPreset->search_queries ?: $editPreset->keywords ?: []) as $query)
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs text-blue-700">{{ $query }}</span>
                            @empty
                                <span class="text-gray-400">No queries configured.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-2">
                        @if($editPreset->is_default)
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">Default</span>
                        @endif
                        <span class="inline-flex items-center rounded-full {{ $editPreset->is_active ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }} px-2.5 py-1 text-xs font-medium">
                            {{ $editPreset->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @if($editPreset->user)
                            <span class="text-xs text-gray-500">User: {{ $editPreset->user->name }}</span>
                        @endif
                    </div>
                </div>
            @else
                <div class="mt-4 space-y-3 text-sm text-gray-600">
                    <p>Campaign presets now come from the shared form system. Add new fields in the registered form definition and they will render here automatically.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li>Fields, defaults, options, and validation come from the form registry.</li>
                        <li>Create and edit use the same form definition.</li>
                        <li>The campaign page can now reuse this preset shape instead of hardcoding fields.</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Saved Presets</h3>
                <p class="text-sm text-gray-500">{{ $presets->total() }} preset{{ $presets->total() === 1 ? '' : 's' }} on production.</p>
            </div>
            <a href="{{ route('campaigns.presets.index') }}" class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:border-gray-300">Reset View</a>
        </div>

        @forelse($presets as $preset)
            <div class="border-b border-gray-100 px-6 py-5 last:border-b-0">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900">{{ $preset->name }}</p>
                            @if($preset->is_default)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700">Default</span>
                            @endif
                            <span class="inline-flex items-center rounded-full {{ $preset->is_active ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }} px-2 py-0.5 text-[11px] font-medium">{{ $preset->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ ucfirst($preset->source_method ?: 'keyword') }} → {{ ucfirst(str_replace('-', ' ', $preset->final_article_method ?: 'news-search')) }}
                            · {{ $preset->posts_per_run ?: 1 }} post(s)
                            · {{ ucfirst($preset->frequency ?: 'daily') }}
                            @if($preset->run_at_time) · {{ $preset->run_at_time }} @endif
                            · Drip {{ $preset->drip_minutes ?: 60 }} min
                            @if($preset->user) · User: {{ $preset->user->name }} @endif
                        </p>

                        @php($queries = $preset->search_queries ?: $preset->keywords ?: [])
                        @if(!empty($queries))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($queries as $query)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs text-blue-700">{{ $query }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if($preset->campaign_instructions || $preset->ai_instructions)
                            <p class="mt-3 max-w-3xl text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($preset->campaign_instructions ?: $preset->ai_instructions, 240) }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('campaigns.presets.index', ['id' => $preset->id]) }}" class="inline-flex items-center rounded-lg border border-blue-200 px-3 py-2 text-xs font-medium text-blue-700 hover:border-blue-300 hover:text-blue-900">Edit</a>
                        <a href="{{ route('campaigns.presets.index', ['id' => $preset->id]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:border-gray-300 hover:text-gray-900">Open</a>

                        <form method="POST" action="{{ route('campaigns.presets.toggle-default', $preset->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-emerald-200 px-3 py-2 text-xs font-medium text-emerald-700 hover:border-emerald-300 hover:text-emerald-900">
                                {{ $preset->is_default ? 'Unset Default' : 'Set Default' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('campaigns.presets.destroy', $preset->id) }}" onsubmit="return confirm('Delete preset {{ addslashes($preset->name) }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:border-red-300 hover:text-red-800">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-12 text-center text-sm text-gray-400">No campaign presets exist yet.</div>
        @endforelse
    </div>

    @if(method_exists($presets, 'links'))
        <div>
            {{ $presets->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
