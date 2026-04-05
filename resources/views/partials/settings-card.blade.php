@push('settings-cards')
@if(Route::has('publish.settings.master'))
<a href="{{ route('publish.settings.master') }}" class="group block bg-white rounded-xl border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200">
    <div class="flex items-start justify-between">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">v{{ config('app-publish.version', '?') }}</span>
    </div>
    <h3 class="mt-4 text-lg font-semibold text-gray-900 group-hover:text-blue-700 transition-colors">Publishing</h3>
    <p class="mt-1 text-sm text-gray-500">Article publishing pipeline, campaigns, templates, presets, and photo settings.</p>
</a>
@endif
@endpush
