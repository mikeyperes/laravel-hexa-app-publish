@foreach($articles as $article)
@php
    $thumb = null;
    if ($article->wp_images && is_array($article->wp_images)) {
        $featured = collect($article->wp_images)->firstWhere('is_featured', true);
        if (!$featured) $featured = collect($article->wp_images)->first();
        if ($featured && is_array($featured)) {
            $thumb = $featured['sizes']['thumbnail'] ?? $featured['sizes']['medium'] ?? $featured['media_url'] ?? null;
        }
    }
    if (!$thumb && $article->photos && is_array($article->photos)) {
        $firstPhoto = collect($article->photos)->first();
        if (is_array($firstPhoto)) {
            $thumb = $firstPhoto['sizes']['thumbnail'] ?? $firstPhoto['url'] ?? $firstPhoto['src'] ?? null;
        }
    }
    $sourceUrl = null;
    if (!$article->wp_post_url && $article->source_articles && is_array($article->source_articles)) {
        $firstSource = collect($article->source_articles)->first();
        if (is_array($firstSource)) {
            $sourceUrl = $firstSource['url'] ?? $firstSource['link'] ?? null;
        }
    }
    $siteUrl = $article->site->url ?? null;
    if ($siteUrl) $siteUrl = rtrim($siteUrl, '/');
    $authorUrl = ($siteUrl && $article->author) ? $siteUrl . '/author/' . Str::slug($article->author) . '/' : null;
@endphp
<div class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50/50 transition-colors border-b border-gray-100 last:border-b-0">
    {{-- Thumbnail --}}
    <a href="{{ route('publish.pipeline', ['id' => $article->id]) }}" class="flex-shrink-0 w-16 h-16 rounded-lg bg-gray-100 overflow-hidden block">
        @if($thumb)
            <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover" loading="lazy"
                onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div class="w-full h-full items-center justify-center text-gray-300" style="display:none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        @endif
    </a>

    {{-- Content --}}
    <div class="flex-1 min-w-0 pt-0.5">
        {{-- Title + badges on same line --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('publish.pipeline', ['id' => $article->id]) }}" class="text-sm font-semibold text-gray-900 hover:text-blue-600 break-words leading-snug truncate">{{ $article->title ?: 'Untitled' }}</a>
            @if($article->article_type)
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 whitespace-nowrap">{{ ucwords(str_replace(['-', '_'], ' ', $article->article_type)) }}</span>
            @endif
            @if($article->status === 'published')
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-700 whitespace-nowrap">Published</span>
            @elseif($article->status === 'completed')
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 whitespace-nowrap">Completed</span>
            @elseif($article->status === 'review')
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 whitespace-nowrap">Review</span>
            @elseif($article->status === 'failed')
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-700 whitespace-nowrap">Failed</span>
            @elseif($article->status === 'ready')
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700 whitespace-nowrap">Ready</span>
            @elseif($article->status === 'drafting')
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-500 whitespace-nowrap">Draft</span>
            @else
                <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-500 whitespace-nowrap">{{ ucfirst($article->status) }}</span>
            @endif
        </div>

        {{-- URL --}}
        @if($article->wp_post_url)
            <a href="{{ $article->wp_post_url }}" target="_blank" rel="noopener" class="text-xs text-blue-500 hover:text-blue-700 hover:underline break-all leading-snug mt-1 inline-block">{{ $article->wp_post_url }} <span class="text-blue-400">&nearr;</span></a>
        @elseif($sourceUrl)
            <p class="text-xs text-gray-400 break-all leading-snug mt-1">{{ $sourceUrl }}</p>
        @endif

        {{-- Author --}}
        @if($article->author)
            <p class="text-xs text-gray-500 mt-0.5">By
                @if($authorUrl)
                    <a href="{{ $authorUrl }}" target="_blank" rel="noopener" class="font-medium text-gray-600 hover:text-blue-600 hover:underline">{{ $article->author }} <span class="text-gray-400">&nearr;</span></a>
                @else
                    <span class="font-medium text-gray-600">{{ $article->author }}</span>
                @endif
            </p>
        @endif

        {{-- Meta line 1: identity + stats --}}
        <div class="mt-2 text-[11px] text-gray-400 leading-relaxed">
            <span class="font-mono">{{ $article->article_id }}</span>
            @if($article->site)
                <span class="text-gray-300 mx-1.5">&middot;</span>
                <span>{{ $article->site->name }}</span>
            @endif
            @if($article->word_count)
                <span class="text-gray-300 mx-1.5">&middot;</span>
                <span>{{ number_format($article->word_count) }} words</span>
            @endif
            @if($article->ai_detection_score !== null && $article->ai_detection_score !== '')
                <span class="text-gray-300 mx-1.5">&middot;</span>
                <span class="{{ (float) $article->ai_detection_score > 50 ? 'text-red-500' : ((float) $article->ai_detection_score > 20 ? 'text-yellow-600' : 'text-green-600') }}">AI {{ number_format($article->ai_detection_score, 0) }}%</span>
            @endif
            @if($article->ai_engine_used)
                <span class="text-gray-300 mx-1.5">&middot;</span>
                <span>{{ $article->ai_engine_used }}</span>
            @endif
        </div>
        {{-- Meta line 2: people + dates --}}
        <div class="text-[11px] text-gray-400 leading-relaxed">
            @if($article->creator)
                <span>{{ $article->creator->name }}</span>
                <span class="text-gray-300 mx-1.5">&middot;</span>
            @endif
            <span>{{ $article->created_at ? $article->created_at->diffForHumans() : '' }}</span>
            @if($article->published_at)
                <span class="text-gray-300 mx-1.5">&middot;</span>
                <span class="text-green-600">Published {{ $article->published_at->format('M j, Y g:ia') }}</span>
            @endif
            @if($article->wp_post_id)
                <span class="text-gray-300 mx-1.5">&middot;</span>
                <span>WP #{{ $article->wp_post_id }}</span>
            @endif
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex-shrink-0 flex items-center gap-1 pt-1">
        <a href="{{ route('publish.pipeline', ['id' => $article->id]) }}" class="p-1.5 rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Open in Pipeline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </a>
        @if($article->wp_post_url)
            <a href="{{ $article->wp_post_url }}" target="_blank" rel="noopener" class="p-1.5 rounded text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors" title="View on WordPress">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        @endif
        @if($article->status === 'deleted')
            <button onclick="restoreArticle({{ $article->id }}, this)" class="p-1.5 rounded text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors" title="Restore">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 010 10H9m4-10l-4-4m4 4l-4 4"/></svg>
            </button>
        @else
            <button onclick="trashArticle({{ $article->id }}, this)" class="p-1.5 rounded text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors" title="Move to Deleted">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        @endif
    </div>
</div>
@endforeach
