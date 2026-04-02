{{-- Article Full Report --}}
@extends('layouts.app')
@section('title', ($article->title ?? 'Article') . ' — #' . $article->article_id)
@section('header', 'Article Report — ' . $article->article_id)

@section('content')
<div class="max-w-5xl mx-auto space-y-4" x-data="articleReport()">

    {{-- ═══ Header ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <p class="text-xs font-mono text-gray-400">#{{ $article->id }} &middot; {{ $article->article_id }}</p>
                    @if($article->status === 'completed' || $article->status === 'published')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-800">{{ ucfirst($article->status) }}</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600">{{ ucfirst($article->status) }}</span>
                    @endif
                </div>
                <h2 class="text-xl font-semibold text-gray-800 break-words">{{ $article->title ?? '(untitled)' }}</h2>
                @if($article->excerpt)
                    <p class="text-sm text-gray-500 mt-1 break-words">{{ $article->excerpt }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('publish.drafts.index') }}" class="text-xs text-gray-400 hover:text-gray-600">&larr; All Articles</a>
                <button @click="confirmDelete = true" class="text-xs text-red-400 hover:text-red-600 px-2 py-1 border border-red-200 rounded-lg hover:bg-red-50">Delete</button>
            </div>
        </div>
    </div>

    {{-- ═══ Details (row layout) ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-2">
        <h3 class="font-semibold text-gray-800 mb-3">Details</h3>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Site</span><p class="text-sm text-gray-800">@if($article->site)<a href="{{ $article->site->url }}" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1">{{ $article->site->name }} <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> <span class="text-xs text-gray-400 break-all">({{ $article->site->url }})</span>@else — @endif</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Author</span><p class="text-sm text-gray-800">{{ $article->author ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Created By</span><p class="text-sm text-gray-800">@if($article->creator){{ $article->creator->name }} ({{ $article->creator->email }})@else — @endif</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Word Count</span><p class="text-sm font-bold text-gray-800">{{ $article->word_count ? number_format($article->word_count) : '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Status</span><p class="text-sm text-gray-800">{{ ucfirst($article->status ?? 'unknown') }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">WP Post ID</span><p class="text-sm font-mono text-gray-800">{{ $article->wp_post_id ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">WP Post URL</span><p class="text-sm text-gray-800">@if($article->wp_post_url)<a href="{{ $article->wp_post_url }}" target="_blank" class="text-blue-600 hover:underline break-all inline-flex items-center gap-1">{{ $article->wp_post_url }} <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>@else — @endif</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">WP Status</span><p class="text-sm text-gray-800">{{ ucfirst($article->wp_status ?? '—') }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Created</span><p class="text-sm text-gray-800">{{ $article->created_at ? $article->created_at->setTimezone(config('app.timezone', 'America/New_York'))->format('M j, Y g:i A T') : '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Published</span><p class="text-sm text-gray-800">{{ $article->published_at ? $article->published_at->setTimezone(config('app.timezone', 'America/New_York'))->format('M j, Y g:i A T') : '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">IP</span><p class="text-sm font-mono text-gray-800">{{ $article->user_ip ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Delivery Mode</span><p class="text-sm text-gray-800">{{ $article->delivery_mode ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Preset</span><p class="text-sm text-gray-800">{{ $article->preset_id ? '#' . $article->preset_id : '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Featured Image</span><p class="text-sm text-gray-800">{{ $article->featured_image_search ?? '—' }}</p></div>
    </div>

    {{-- ═══ SEO ═══ --}}
    @php $seo = $article->seo_data ?? []; @endphp
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-6 space-y-2">
        <h3 class="font-semibold text-gray-700 mb-3">SEO</h3>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Meta Title</span><p class="text-sm text-blue-700 font-medium break-words">{{ $article->title ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Meta Description</span><p class="text-sm text-gray-600 break-words">{{ $article->excerpt ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">OG Title</span><p class="text-sm text-gray-700 break-words">{{ $seo['og_title'] ?? $article->title ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">OG Description</span><p class="text-sm text-gray-600 break-words">{{ $seo['og_description'] ?? $article->excerpt ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">OG Type</span><p class="text-sm text-gray-600">{{ $seo['og_type'] ?? 'article' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Twitter Card</span><p class="text-sm text-gray-600">{{ $seo['twitter_card'] ?? 'summary_large_image' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Focus Keyphrase</span><p class="text-sm text-gray-700 break-words">{{ $seo['focus_keyphrase'] ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5 border-b border-gray-100"><span class="text-xs text-gray-400 w-28 flex-shrink-0">Canonical Slug</span><p class="text-sm font-mono text-gray-600">{{ $seo['canonical_slug'] ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1.5"><span class="text-xs text-gray-400 w-28 flex-shrink-0">SEO Score</span><p class="text-sm text-gray-800">{{ $article->seo_score ? $article->seo_score . '%' : '—' }}</p></div>
    </div>

    {{-- ═══ Categories & Tags ═══ --}}
    @if(($article->categories && count($article->categories) > 0) || ($article->tags && count($article->tags) > 0))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3">
        @if($article->categories && count($article->categories) > 0)
        <div class="flex items-start gap-3 py-1.5">
            <span class="text-xs text-gray-400 w-28 flex-shrink-0">Categories ({{ count($article->categories) }})</span>
            <div class="flex flex-wrap gap-1">
                @foreach($article->categories as $cat)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">{{ is_string($cat) ? $cat : ($cat['name'] ?? json_encode($cat)) }}</span>
                @endforeach
            </div>
        </div>
        @endif
        @if($article->tags && count($article->tags) > 0)
        <div class="flex items-start gap-3 py-1.5">
            <span class="text-xs text-gray-400 w-28 flex-shrink-0">Tags ({{ count($article->tags) }})</span>
            <div class="flex flex-wrap gap-1">
                @foreach($article->tags as $tag)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">{{ is_string($tag) ? $tag : ($tag['name'] ?? json_encode($tag)) }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ Source Articles (before) ═══ --}}
    @if($article->source_articles && count($article->source_articles) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-data="{ showSources: false }">
        <button @click="showSources = !showSources" class="w-full flex items-center justify-between text-left">
            <h3 class="font-semibold text-gray-800">Source Articles — Before ({{ count($article->source_articles) }})</h3>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="showSources ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="showSources" x-cloak class="mt-3 space-y-3">
            @foreach($article->source_articles as $src)
            <div class="border border-gray-100 rounded-lg p-3">
                <p class="text-sm font-medium text-gray-900 break-words">{{ $src['title'] ?? 'Untitled Source' }}</p>
                @if(isset($src['url']))
                    <a href="{{ $src['url'] }}" target="_blank" class="text-xs text-blue-600 hover:underline break-all inline-flex items-center gap-1 mt-1">{{ $src['url'] }} <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                @endif
                @if(isset($src['text']))
                    <div class="mt-2 text-xs text-gray-500 break-words" style="white-space: pre-wrap;">{{ \Illuminate\Support\Str::limit($src['text'], 500) }}</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══ Spun Article (after) ═══ --}}
    @if($article->body)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-data="{ showBody: true }">
        <button @click="showBody = !showBody" class="w-full flex items-center justify-between text-left">
            <h3 class="font-semibold text-gray-800">Spun Article — After</h3>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="showBody ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        @php $cleanBody = preg_replace('/<div[^>]*class="photo-placeholder"[^>]*>.*?<\/div>/s', '', $article->body); @endphp
        <div x-show="showBody" x-cloak class="mt-3 prose max-w-none text-sm break-words">{!! $cleanBody !!}</div>
    </div>
    @endif

    {{-- ═══ WordPress Photos ═══ --}}
    @if($article->wp_images && count($article->wp_images) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">WordPress Photos ({{ count($article->wp_images) }})</h3>
        <div class="space-y-4">
            @foreach($article->wp_images as $img)
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start gap-4">
                    <img src="{{ $img['sizes']['thumbnail'] ?? $img['media_url'] ?? '' }}" alt="{{ $img['alt_text'] ?? '' }}" class="w-24 h-24 object-cover rounded-lg flex-shrink-0 border">
                    <div class="flex-1 min-w-0 space-y-1 text-xs">
                        <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Filename</span><span class="font-mono text-gray-800 break-all">{{ $img['filename'] ?? '—' }}</span></div>
                        <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Media ID</span><span class="text-gray-800">{{ $img['media_id'] ?? '—' }}</span></div>
                        <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Path</span><span class="font-mono text-gray-600 break-all">{{ $img['file_path'] ?? '—' }}</span></div>
                        <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">File Size</span><span class="text-gray-800">{{ isset($img['file_size']) && $img['file_size'] ? round($img['file_size'] / 1024) . ' KB' : '—' }}</span></div>
                        <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Alt Text</span><span class="text-gray-700 break-words">{{ $img['alt_text'] ?? '—' }}</span></div>
                        <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Caption</span><span class="text-gray-700 break-words">{{ $img['caption'] ?? '—' }}</span></div>
                        <div class="flex items-start gap-3"><span class="text-gray-400 w-20 flex-shrink-0">Description</span><span class="text-gray-700 break-words">{{ $img['description'] ?? '—' }}</span></div>
                        @if(!empty($img['sizes']))
                        <div class="mt-1 pt-1 border-t border-gray-100">
                            <p class="text-gray-400 mb-1">WordPress Sizes:</p>
                            @foreach($img['sizes'] as $sizeName => $sizeUrl)
                            <div class="flex items-start gap-3 py-0.5"><span class="text-gray-400 w-20 flex-shrink-0">{{ $sizeName }}</span><a href="{{ $sizeUrl }}" target="_blank" class="text-blue-600 hover:underline break-all">{{ $sizeUrl }}</a></div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══ AI Generation Details (dark theme) ═══ --}}
    <div class="bg-gray-900 rounded-xl border border-gray-700 p-6 space-y-2">
        <h3 class="font-semibold text-white mb-3">AI Generation</h3>
        <div class="flex items-start gap-3 py-1"><span class="text-xs text-gray-500 w-28 flex-shrink-0">Model</span><p class="text-sm text-purple-400 font-mono">{{ $article->ai_engine_used ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1"><span class="text-xs text-gray-500 w-28 flex-shrink-0">Provider</span><p class="text-sm text-blue-400">{{ $article->ai_provider ?? '—' }}</p></div>
        <div class="flex items-start gap-3 py-1"><span class="text-xs text-gray-500 w-28 flex-shrink-0">Cost</span><p class="text-sm text-yellow-400">${{ $article->ai_cost ? number_format($article->ai_cost, 4) : '0.00' }}</p></div>
        <div class="flex items-start gap-3 py-1"><span class="text-xs text-gray-500 w-28 flex-shrink-0">Tokens (in+out)</span><p class="text-sm text-green-400">{{ number_format($article->ai_tokens_input ?? 0) }} + {{ number_format($article->ai_tokens_output ?? 0) }} = {{ number_format(($article->ai_tokens_input ?? 0) + ($article->ai_tokens_output ?? 0)) }}</p></div>
        <div class="flex items-start gap-3 py-1"><span class="text-xs text-gray-500 w-28 flex-shrink-0">AI Detection</span><p class="text-sm text-gray-300">{{ $article->ai_detection_score !== null ? $article->ai_detection_score . '% AI' : '—' }}</p></div>
        @if($article->resolved_prompt)
        <div class="mt-3" x-data="{ showPrompt: false }">
            <button @click="showPrompt = !showPrompt" class="text-xs text-gray-400 hover:text-gray-200 flex items-center gap-1">
                <svg class="w-3 h-3 transition-transform" :class="showPrompt ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                Full AI Prompt ({{ number_format(strlen($article->resolved_prompt)) }} chars)
            </button>
            <pre x-show="showPrompt" x-cloak class="mt-2 text-xs text-green-300 bg-gray-800 rounded-lg p-4 whitespace-pre-wrap break-words overflow-y-auto" style="max-height:500px;">{{ $article->resolved_prompt }}</pre>
        </div>
        @endif
    </div>

    {{-- ═══ Delete Confirmation Modal ═══ --}}
    <div x-show="confirmDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-red-700 mb-2">Delete Article</h3>
            <p class="text-sm text-gray-600 mb-4">This will permanently delete article <strong>{{ $article->article_id }}</strong> and remove all associated WordPress photos. This cannot be undone.</p>

            <div x-show="deleteLog.length > 0" class="bg-gray-900 rounded-lg p-3 mb-4 max-h-32 overflow-y-auto">
                <template x-for="(entry, idx) in deleteLog" :key="idx">
                    <p class="text-xs font-mono" :class="{'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-gray-400': entry.type === 'step'}" x-text="entry.message"></p>
                </template>
            </div>

            <div class="flex gap-3">
                <button @click="deleteArticle()" :disabled="deleting" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50 inline-flex items-center gap-2">
                    <svg x-show="deleting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="deleting ? 'Deleting...' : 'Delete Permanently'"></span>
                </button>
                <button @click="confirmDelete = false" :disabled="deleting" class="text-gray-500 hover:text-gray-700 px-4 py-2 text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function articleReport() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    return {
        confirmDelete: false,
        deleting: false,
        deleteLog: [],

        async deleteArticle() {
            this.deleting = true;
            this.deleteLog = [{ type: 'step', message: 'Deleting article...' }];
            try {
                const r = await fetch('{{ route("publish.drafts.destroy", $article->id) }}', {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                });
                const d = await r.json();
                if (d.success) {
                    this.deleteLog.push({ type: 'success', message: 'Article deleted.' });
                    setTimeout(() => window.location.href = '{{ route("publish.drafts.index") }}', 1000);
                } else {
                    this.deleteLog.push({ type: 'error', message: d.message || 'Delete failed.' });
                    this.deleting = false;
                }
            } catch(e) {
                this.deleteLog.push({ type: 'error', message: 'Network error: ' + e.message });
                this.deleting = false;
            }
        },
    };
}
</script>
@endpush
@endsection
