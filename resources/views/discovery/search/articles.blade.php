@extends('layouts.app')

@section('title', 'Article Search — ' . config('hws.app_name'))
@section('header', 'Search > Articles')

@section('content')
<div class="space-y-6">

    {{-- Search Controls --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[250px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Query</label>
                <input type="text" id="search-query" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="e.g. artificial intelligence, climate change, stock market">
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
                    <span id="btn-text-search">Search Articles</span>
                </button>
            </div>
        </div>

        {{-- Source toggles --}}
        <div class="flex items-center gap-4 mt-4">
            <span class="text-xs text-gray-500">Sources:</span>
            @foreach($sources as $name => $configured)
            <label class="inline-flex items-center gap-1.5 text-sm">
                <input type="checkbox" class="source-toggle rounded" value="{{ $name }}" {{ $configured ? 'checked' : '' }} {{ !$configured ? 'disabled' : '' }}>
                <span class="{{ $configured ? 'text-gray-700' : 'text-gray-400' }}">{{ str_replace('_', ' ', ucfirst($name)) }}</span>
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

    {{-- Results --}}
    <div id="search-results" class="space-y-3"></div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    var sourceColors = {
        gnews: 'bg-emerald-100 text-emerald-700',
        newsdata: 'bg-blue-100 text-blue-700',
        currents_news: 'bg-orange-100 text-orange-700'
    };

    var sourceLabels = {
        gnews: 'GNews',
        newsdata: 'NewsData',
        currents_news: 'Currents'
    };

    // Enter key triggers search
    document.getElementById('search-query').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-search').click();
    });

    document.getElementById('btn-search').addEventListener('click', function() {
        var btn = this;
        var spinner = document.getElementById('spinner-search');
        var btnText = document.getElementById('btn-text-search');
        var query = document.getElementById('search-query').value.trim();
        var perPage = document.getElementById('search-per-page').value;
        var banner = document.getElementById('search-banner');
        var errorsDiv = document.getElementById('search-errors');
        var statsDiv = document.getElementById('search-stats');
        var resultsDiv = document.getElementById('search-results');

        if (!query) {
            banner.className = 'px-4 py-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
            banner.textContent = 'Enter a search query.';
            banner.classList.remove('hidden');
            return;
        }

        // Get selected sources
        var sources = [];
        document.querySelectorAll('.source-toggle:checked').forEach(function(cb) { sources.push(cb.value); });
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

        fetch('{{ route("publish.search.articles.post") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ query: query, per_page: parseInt(perPage), sources: sources }),
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var articles = data.data?.articles || [];
            var totals = data.data?.totals || {};
            var errors = data.data?.errors || [];

            // Banner
            if (articles.length > 0) {
                banner.className = 'px-4 py-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800';
                banner.textContent = data.message;
            } else {
                banner.className = 'px-4 py-3 rounded-lg text-sm bg-yellow-50 border border-yellow-200 text-yellow-800';
                banner.textContent = 'No articles found.';
            }
            banner.classList.remove('hidden');

            // Errors
            if (errors.length > 0) {
                errorsDiv.innerHTML = errors.map(function(e) { return '<div>' + esc(e) + '</div>'; }).join('');
                errorsDiv.classList.remove('hidden');
            }

            // Stats
            if (Object.keys(totals).length > 0) {
                var parts = Object.entries(totals).map(function(entry) {
                    return esc(sourceLabels[entry[0]] || entry[0]) + ': ' + entry[1].toLocaleString() + ' total';
                });
                statsDiv.innerHTML = 'Showing ' + articles.length + ' results &mdash; ' + parts.join(' | ');
                statsDiv.classList.remove('hidden');
            }

            // Render article cards
            articles.forEach(function(article) {
                var card = document.createElement('div');
                card.className = 'bg-white rounded-xl shadow-sm border border-gray-200 p-5';

                var srcClass = sourceColors[article.source_api] || 'bg-gray-100 text-gray-700';
                var srcLabel = sourceLabels[article.source_api] || article.source_api;
                var baseDomain = extractDomain(article.url);

                var html = '';

                // Header row: source badge + date
                html += '<div class="flex items-center justify-between mb-2">';
                html += '<span class="px-2 py-0.5 rounded text-xs font-semibold ' + srcClass + '">' + esc(srcLabel) + '</span>';
                if (article.published_at) {
                    html += '<span class="text-xs text-gray-400">' + formatDate(article.published_at) + '</span>';
                }
                html += '</div>';

                // Title
                html += '<h3 class="text-sm font-semibold text-gray-900 break-words">';
                if (article.url) {
                    html += '<a href="' + esc(article.url) + '" target="_blank" class="hover:text-purple-700 hover:underline">' + esc(article.title) + ' &#8599;</a>';
                } else {
                    html += esc(article.title);
                }
                html += '</h3>';

                // Description
                if (article.description) {
                    html += '<p class="text-xs text-gray-500 mt-1 break-words">' + esc(truncate(article.description, 300)) + '</p>';
                }

                // Metadata grid
                html += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 mt-3 text-xs">';

                // Source / Publication
                if (article.source_name) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-gray-400 flex-shrink-0 w-20">Source:</span>';
                    if (article.source_url) {
                        html += '<a href="' + esc(article.source_url) + '" target="_blank" class="text-blue-600 hover:underline break-words">' + esc(article.source_name) + ' &#8599;</a>';
                    } else {
                        html += '<span class="text-gray-700 break-words">' + esc(article.source_name) + '</span>';
                    }
                    html += '</div>';
                }

                // Base domain
                if (baseDomain) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-gray-400 flex-shrink-0 w-20">Domain:</span>';
                    html += '<span class="text-gray-700 break-words">' + esc(baseDomain) + '</span>';
                    html += '</div>';
                }

                // Author / Journalist
                if (article.author) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-gray-400 flex-shrink-0 w-20">Author:</span>';
                    html += '<span class="text-gray-700 break-words">' + esc(article.author) + '</span>';
                    html += '</div>';
                }

                // Language
                if (article.language) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-gray-400 flex-shrink-0 w-20">Language:</span>';
                    html += '<span class="text-gray-700">' + esc(article.language.toUpperCase()) + '</span>';
                    html += '</div>';
                }

                // Country
                if (article.country) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-gray-400 flex-shrink-0 w-20">Country:</span>';
                    html += '<span class="text-gray-700">' + esc(article.country.toUpperCase()) + '</span>';
                    html += '</div>';
                }

                // External link
                if (article.url) {
                    html += '<div class="flex items-start gap-1">';
                    html += '<span class="text-gray-400 flex-shrink-0 w-20">Link:</span>';
                    html += '<a href="' + esc(article.url) + '" target="_blank" class="text-blue-600 hover:underline break-all">' + esc(truncate(article.url, 80)) + ' &#8599;</a>';
                    html += '</div>';
                }

                html += '</div>';

                // Categories + Keywords row
                if ((article.categories && article.categories.length > 0) || (article.keywords && article.keywords.length > 0)) {
                    html += '<div class="flex flex-wrap gap-1 mt-2">';
                    if (article.categories && article.categories.length > 0) {
                        article.categories.forEach(function(cat) {
                            if (cat) html += '<span class="px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded text-xs">' + esc(cat) + '</span>';
                        });
                    }
                    if (article.keywords && article.keywords.length > 0) {
                        article.keywords.forEach(function(kw) {
                            if (kw) html += '<span class="px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">' + esc(kw) + '</span>';
                        });
                    }
                    html += '</div>';
                }

                // Thumbnail
                if (article.image) {
                    html += '<div class="mt-3">';
                    html += '<img src="' + esc(article.image) + '" alt="" class="rounded-lg max-h-40 object-cover" loading="lazy" onerror="this.style.display=\'none\'">';
                    html += '</div>';
                }

                card.innerHTML = html;
                resultsDiv.appendChild(card);
            });
        })
        .catch(function(err) {
            banner.className = 'px-4 py-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
            banner.textContent = 'Request failed: ' + err.message;
            banner.classList.remove('hidden');
        })
        .finally(function() {
            btn.disabled = false;
            spinner.classList.add('hidden');
            btnText.textContent = 'Search Articles';
        });
    });

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function truncate(s, len) {
        if (!s) return '';
        return s.length > len ? s.substring(0, len) + '...' : s;
    }

    function extractDomain(url) {
        if (!url) return '';
        try {
            var u = new URL(url);
            return u.hostname.replace(/^www\./, '');
        } catch (e) {
            return '';
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        try {
            var d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return dateStr;
        }
    }
});
</script>
@endpush
@endsection
