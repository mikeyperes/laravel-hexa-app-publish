@extends('layouts.app')

@section('title', 'Image Search — ' . config('hws.app_name'))
@section('header', 'Search > Images')

@section('content')
<div class="space-y-6">

    {{-- Search Controls --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[250px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Query</label>
                <input type="text" id="search-query" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="e.g. nature, business meeting, technology">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Per Source</label>
                <select id="search-per-page" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="15">15</option>
                    <option value="20">20</option>
                    <option value="30">30</option>
                </select>
            </div>
            <div>
                <button id="btn-search" class="bg-purple-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-purple-700 inline-flex items-center gap-2">
                    <svg id="spinner-search" class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span id="btn-text-search">Search Images</span>
                </button>
            </div>
        </div>

        {{-- Source toggles --}}
        <div class="flex items-center gap-4 mt-4">
            <span class="text-xs text-gray-500">Sources:</span>
            @foreach($sources as $name => $configured)
            <label class="inline-flex items-center gap-1.5 text-sm">
                <input type="checkbox" class="source-toggle rounded" value="{{ $name }}" {{ $configured ? 'checked' : '' }} {{ !$configured ? 'disabled' : '' }}>
                <span class="{{ $configured ? 'text-gray-700' : 'text-gray-400' }}">{{ ucfirst($name) }}</span>
                <span class="w-1.5 h-1.5 rounded-full {{ $configured ? 'bg-green-400' : 'bg-gray-300' }}"></span>
            </label>
            @endforeach
        </div>
    </div>

    {{-- Result summary --}}
    <div id="search-banner" class="hidden px-4 py-3 rounded-lg text-sm"></div>

    {{-- Error list --}}
    <div id="search-errors" class="hidden bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700"></div>

    {{-- Results count + source breakdown --}}
    <div id="search-stats" class="hidden text-sm text-gray-500"></div>

    {{-- Results grid --}}
    <div id="search-results" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    // Enter key triggers search
    document.getElementById('search-query').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-search').click();
    });

    document.getElementById('btn-search').addEventListener('click', function() {
        const btn = this;
        const spinner = document.getElementById('spinner-search');
        const btnText = document.getElementById('btn-text-search');
        const query = document.getElementById('search-query').value.trim();
        const perPage = document.getElementById('search-per-page').value;
        const banner = document.getElementById('search-banner');
        const errorsDiv = document.getElementById('search-errors');
        const statsDiv = document.getElementById('search-stats');
        const resultsDiv = document.getElementById('search-results');

        if (!query) {
            banner.className = 'px-4 py-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
            banner.textContent = 'Enter a search query.';
            banner.classList.remove('hidden');
            return;
        }

        // Get selected sources
        const sources = [];
        document.querySelectorAll('.source-toggle:checked').forEach(cb => sources.push(cb.value));
        if (sources.length === 0) {
            banner.className = 'px-4 py-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
            banner.textContent = 'Select at least one source.';
            banner.classList.remove('hidden');
            return;
        }

        btn.disabled = true;
        spinner.classList.remove('hidden');
        btnText.textContent = 'Searching...';
        banner.classList.add('hidden');
        errorsDiv.classList.add('hidden');
        statsDiv.classList.add('hidden');
        resultsDiv.innerHTML = '';

        fetch('{{ route("publish.search.images.post") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ query: query, per_page: parseInt(perPage), sources: sources }),
        })
        .then(r => r.json())
        .then(data => {
            const photos = data.data?.photos || [];
            const totals = data.data?.totals || {};
            const errors = data.data?.errors || [];

            // Banner
            if (photos.length > 0) {
                banner.className = 'px-4 py-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800';
                banner.textContent = data.message;
            } else {
                banner.className = 'px-4 py-3 rounded-lg text-sm bg-yellow-50 border border-yellow-200 text-yellow-800';
                banner.textContent = 'No photos found.';
            }
            banner.classList.remove('hidden');

            // Errors
            if (errors.length > 0) {
                errorsDiv.innerHTML = errors.map(e => '<div>' + esc(e) + '</div>').join('');
                errorsDiv.classList.remove('hidden');
            }

            // Stats
            if (Object.keys(totals).length > 0) {
                const parts = Object.entries(totals).map(([k, v]) => esc(k) + ': ' + v.toLocaleString() + ' total');
                statsDiv.innerHTML = 'Showing ' + photos.length + ' results &mdash; ' + parts.join(' | ');
                statsDiv.classList.remove('hidden');
            }

            // Render photo cards
            photos.forEach(function(photo) {
                const card = document.createElement('div');
                card.className = 'bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden';

                const sourceColors = { pexels: 'bg-green-100 text-green-700', unsplash: 'bg-blue-100 text-blue-700', pixabay: 'bg-orange-100 text-orange-700' };
                const sourceClass = sourceColors[photo.source] || 'bg-gray-100 text-gray-700';

                let html = '';

                // Image
                html += '<div class="relative">';
                html += '<img src="' + esc(photo.url_thumb) + '" alt="' + esc(photo.alt || '') + '" class="w-full h-48 object-cover" loading="lazy">';
                html += '<span class="absolute top-2 left-2 px-2 py-0.5 rounded text-xs font-semibold ' + sourceClass + '">' + esc(photo.source) + '</span>';
                html += '</div>';

                // Info
                html += '<div class="p-4 space-y-2">';

                // Photographer
                if (photo.photographer) {
                    html += '<div class="text-sm font-medium text-gray-900 break-words">';
                    if (photo.photographer_url) {
                        html += '<a href="' + esc(photo.photographer_url) + '" target="_blank" class="text-blue-600 hover:underline">' + esc(photo.photographer) + ' &#8599;</a>';
                    } else {
                        html += esc(photo.photographer);
                    }
                    html += '</div>';
                }

                // Resolution
                html += '<div class="text-xs text-gray-500">' + photo.width + ' x ' + photo.height + ' px</div>';

                // Alt/tags
                if (photo.alt) {
                    html += '<div class="text-xs text-gray-400 break-words">' + esc(photo.alt) + '</div>';
                }

                // URLs
                html += '<div class="space-y-1 pt-1">';

                // Full size URL
                html += '<div class="flex items-start gap-1">';
                html += '<span class="text-xs text-gray-400 flex-shrink-0 w-10">Full:</span>';
                html += '<a href="' + esc(photo.url_full) + '" target="_blank" class="text-xs text-blue-600 hover:underline break-all">' + esc(photo.url_full) + ' &#8599;</a>';
                html += '</div>';

                // Large URL
                if (photo.url_large && photo.url_large !== photo.url_full) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-xs text-gray-400 flex-shrink-0 w-10">Large:</span>';
                    html += '<a href="' + esc(photo.url_large) + '" target="_blank" class="text-xs text-blue-600 hover:underline break-all">' + esc(photo.url_large) + ' &#8599;</a>';
                    html += '</div>';
                }

                // Source page URL
                const pageUrl = photo.pexels_url || photo.unsplash_url || photo.pixabay_url || '';
                if (pageUrl) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-xs text-gray-400 flex-shrink-0 w-10">Page:</span>';
                    html += '<a href="' + esc(pageUrl) + '" target="_blank" class="text-xs text-blue-600 hover:underline break-all">' + esc(pageUrl) + ' &#8599;</a>';
                    html += '</div>';
                }

                html += '</div>';

                // Attribution note
                if (photo.attribution_required) {
                    html += '<div class="text-xs text-orange-600 mt-1">Attribution required</div>';
                }

                html += '</div>';

                card.innerHTML = html;
                resultsDiv.appendChild(card);
            });
        })
        .catch(err => {
            banner.className = 'px-4 py-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
            banner.textContent = 'Request failed: ' + err.message;
            banner.classList.remove('hidden');
        })
        .finally(() => {
            btn.disabled = false;
            spinner.classList.add('hidden');
            btnText.textContent = 'Search Images';
        });
    });

    function esc(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
});
</script>
@endpush
@endsection
