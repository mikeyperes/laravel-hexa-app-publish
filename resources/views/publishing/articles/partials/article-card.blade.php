{{--
    Shared Article Card partial.
    Used by /campaigns/{id} and /publish/articles (and anywhere else that lists articles).

    Required:
        $article  — PublishArticle model (should have site relation loaded for best output)

    Optional:
        $campaign — PublishCampaign (used for fallback site + timezone)
        $timezone — String timezone override (else campaign, else config)
        $showCampaignSite — bool: show the site chip (default true)
--}}

@once
<style>
    .hx-article-row { display:flex; flex-direction:column; border:1px solid #e5e7eb; border-radius:14px; background:#fff; overflow:hidden; transition:border-color 0.12s, box-shadow 0.12s; margin-bottom:14px; }
    .hx-article-row:hover { border-color:#cbd5e1; box-shadow:0 4px 12px rgba(15,23,42,0.06); }
    .hx-article-main { display:flex; gap:24px; padding:20px; align-items:flex-start; }
    .hx-article-thumb { width:260px; height:195px; border-radius:12px; overflow:hidden; background:#f1f5f9; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; }
    .hx-article-thumb img { width:100%; height:100%; object-fit:cover; }
    .hx-article-body { flex:1; min-width:0; display:flex; flex-direction:column; gap:12px; }
    .hx-article-title { font-size:22px; font-weight:700; color:#0f172a; line-height:1.3; margin:0; letter-spacing:-0.01em; }
    .hx-article-title a { color:inherit; }
    .hx-article-title a:hover { color:#2563eb; }
    .hx-article-pills { display:flex; flex-wrap:wrap; gap:8px; }
    .hx-article-pills .hx-tag { font-size:11px; padding:4px 10px; }
    .hx-article-meta { font-size:13px; color:#475569; line-height:1.55; }
    .hx-article-meta .hx-sep { color:#cbd5e1; margin:0 8px; user-select:none; }
    .hx-article-meta a { color:#2563eb; }
    .hx-article-meta a:hover { text-decoration:underline; }
    .hx-article-meta strong { color:#1e293b; font-weight:600; }
    .hx-article-cats { display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:12px; color:#64748b; }
    .hx-article-cats .hx-cat { background:#f1f5f9; color:#475569; padding:3px 9px; border-radius:6px; font-size:11px; font-weight:500; }
    .hx-article-footer { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 20px; border-top:1px solid #f1f5f9; background:#fafbfc; }
    .hx-article-tech { font-size:11px; color:#94a3b8; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; display:flex; flex-wrap:wrap; gap:10px; }
    .hx-article-tech .hx-sep { color:#e2e8f0; }
    .hx-article-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; flex-wrap:wrap; }
    .hx-article-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:7px; font-size:13px; font-weight:600; transition:background 0.1s; }
    .hx-article-btn-primary { background:#2563eb; color:#fff; }
    .hx-article-btn-primary:hover { background:#1d4ed8; color:#fff; }
    .hx-article-btn-secondary { background:#fff; color:#334155; border:1px solid #d1d5db; }
    .hx-article-btn-secondary:hover { background:#f8fafc; }
    .hx-tag { display:inline-flex; align-items:center; padding:2px 9px; border-radius:9999px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap; }
    .hx-tag.blue  { background:#dbeafe; color:#1d4ed8; }
    .hx-tag.green { background:#dcfce7; color:#166534; }
    .hx-tag.amber { background:#fef3c7; color:#92400e; }
    .hx-tag.red   { background:#fee2e2; color:#991b1b; }
    .hx-tag.gray  { background:#f3f4f6; color:#4b5563; }
    .hx-tag.slate { background:#f1f5f9; color:#334155; }
</style>
@endonce

@php
    $site = $article->site ?? ($campaign->site ?? null);
    $siteUrl = $site ? rtrim($site->url ?? '', '/') : '';
    $siteName = $site ? ($site->name ?? parse_url($siteUrl, PHP_URL_HOST) ?: '') : '';
    $authorSlug = $article->author ? \Illuminate\Support\Str::slug($article->author) : '';
    $authorWpUrl = ($siteUrl && $authorSlug) ? $siteUrl . '/author/' . $authorSlug . '/' : null;
    $tz = $timezone ?? ($campaign->timezone ?? null) ?? config('app.timezone', 'America/New_York');

    $campaignId = (int) ($campaign->id ?? request('campaign_id') ?? $article->publish_campaign_id ?? 0);
    $showUrl = $campaignId > 0
        ? route('publish.articles.show', ['id' => $article->id, 'campaign_id' => $campaignId, 'article_id' => $article->id])
        : route('publish.articles.show', $article->id);
    $editUrl = $campaignId > 0
        ? route('publish.articles.edit', ['id' => $article->id, 'campaign_id' => $campaignId, 'article_id' => $article->id])
        : route('publish.articles.edit', $article->id);

    $thumb = null;
    if ($article->wp_images && is_array($article->wp_images)) {
        $featured = collect($article->wp_images)->firstWhere('is_featured', true) ?: collect($article->wp_images)->first();
        if (is_array($featured)) {
            $thumb = $featured['sizes']['medium_large'] ?? $featured['sizes']['medium'] ?? $featured['sizes']['thumbnail'] ?? $featured['inline_url'] ?? $featured['media_url'] ?? null;
        }
    }
    if (!$thumb && $article->photos && is_array($article->photos)) {
        $firstPhoto = collect($article->photos)->first();
        if (is_array($firstPhoto)) {
            $thumb = $firstPhoto['sizes']['medium'] ?? $firstPhoto['sizes']['thumbnail'] ?? $firstPhoto['url'] ?? $firstPhoto['src'] ?? null;
        }
    }
    $sourceCount = count((array) ($article->source_articles ?? []));

    $pipelineTone = match($article->status) {
        'completed' => 'green',
        'failed', 'error' => 'red',
        'running', 'pending', 'queued', 'drafting', 'sourcing', 'spinning', 'review' => 'amber',
        default => 'slate',
    };
    $pipelineLabel = match($article->status) {
        'completed' => 'Generated',
        'running', 'drafting', 'sourcing', 'spinning' => 'In Progress',
        'review' => 'Ready for Review',
        'queued', 'pending' => 'Queued',
        'failed', 'error' => 'Failed',
        default => ucfirst((string) $article->status),
    };
    $placeholderTitle = trim((string) ($article->title ?? '')) === 'Campaign run starting...';
    $displayTitle = trim((string) ($article->title ?: ''));
    if ($placeholderTitle && in_array((string) $article->status, ['failed', 'error'], true)) {
        $displayTitle = 'Campaign run failed before article generation';
    } elseif ($placeholderTitle && in_array((string) $article->status, ['drafting', 'sourcing', 'running', 'queued', 'pending', 'spinning', 'review'], true)) {
        $displayTitle = 'Campaign article is still being built';
    } elseif ($displayTitle === '') {
        $displayTitle = 'Untitled Article';
    }
    $isDraft = in_array($article->wp_status, ['draft', 'pending', 'auto-draft', 'future'], true);
    $wpTone = match($article->wp_status) {
        'publish', 'published' => 'green',
        'draft', 'auto-draft' => 'gray',
        'pending', 'future' => 'amber',
        'trash' => 'red',
        default => 'slate',
    };
    $wpEditUrl = ($siteUrl && $article->wp_post_id)
        ? $siteUrl . '/wp-admin/post.php?post=' . $article->wp_post_id . '&action=edit'
        : null;
    $wpLink = $isDraft
        ? ($wpEditUrl ?: $article->wp_post_url)
        : ($article->wp_post_url ?: $wpEditUrl);
    $wpLinkLabel = $isDraft ? 'Edit in WordPress' : 'View on WordPress';

    $showSiteChip = $showCampaignSite ?? true;
    $thumbToneClass = in_array((string) $article->status, ['failed', 'error'], true)
        ? 'bg-red-50 text-red-400'
        : (in_array((string) $article->status, ['running', 'queued', 'pending', 'drafting', 'sourcing', 'spinning', 'review'], true)
            ? 'bg-blue-50 text-blue-400'
            : 'bg-slate-100 text-slate-400');
@endphp

<article class="hx-article-row" x-data="{ refreshing: false, refreshNotice: '', refreshError: '' }">
    <div class="hx-article-main">
        <a href="{{ $showUrl }}" target="_blank" rel="noopener" class="hx-article-thumb {{ $thumb ? '' : $thumbToneClass }}">
            @if($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy">
            @else
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            @endif
        </a>
        <div class="hx-article-body">
            <h3 class="hx-article-title">
                <a href="{{ $showUrl }}" target="_blank" rel="noopener">{{ $displayTitle }}</a>
            </h3>

            <div class="hx-article-pills">
                <span class="hx-tag {{ $pipelineTone }}" title="Internal pipeline generation state">Pipeline · {{ $pipelineLabel }}</span>
                @if($article->wp_status)
                    @if($wpLink)
                        <a href="{{ $wpLink }}" target="_blank" rel="noopener" class="hx-tag {{ $wpTone }}" title="{{ $wpLinkLabel }} on {{ $siteName ?: 'WordPress' }}">WordPress · {{ ucfirst($article->wp_status) }} ↗</a>
                    @else
                        <span class="hx-tag {{ $wpTone }}" title="WordPress post status (no URL available)">WordPress · {{ ucfirst($article->wp_status) }}</span>
                    @endif
                @endif
                @if($showSiteChip && $siteName)
                    <a href="{{ $siteUrl ?: '#' }}" target="_blank" rel="noopener" class="hx-tag blue" title="Open {{ $siteName }}">{{ $siteName }} ↗</a>
                @endif
            </div>

            <div class="hx-article-meta">
                @if($article->author && $authorWpUrl)
                    By&nbsp;<a href="{{ $authorWpUrl }}" target="_blank" rel="noopener" title="Open {{ $article->author }}'s WordPress profile"><strong>{{ $article->author }}</strong>&nbsp;↗</a>
                @elseif($article->author)
                    By&nbsp;<strong>{{ $article->author }}</strong>
                @else
                    By <span class="text-gray-400">—</span>
                @endif
                @if($article->word_count)
                    <span class="hx-sep">·</span><strong>{{ number_format($article->word_count) }}</strong> words
                @endif
                @if($sourceCount > 0)
                    <span class="hx-sep">·</span><strong>{{ $sourceCount }}</strong> source{{ $sourceCount === 1 ? '' : 's' }}
                @endif
                <span class="hx-sep">·</span>{{ $article->created_at?->setTimezone($tz)->format('M j, Y · g:i A') }}
            </div>

            @if(!empty($article->categories) && is_array($article->categories))
                <div class="hx-article-cats">
                    @foreach(array_slice((array) $article->categories, 0, 6) as $cat)
                        <span class="hx-cat">{{ $cat }}</span>
                    @endforeach
                    @if(count((array) $article->categories) > 6)
                        <span class="text-gray-400">+{{ count((array) $article->categories) - 6 }} more</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <div class="hx-article-footer">
        <div class="hx-article-tech">
            <span>{{ $article->article_id }}</span>
            @if($article->ai_engine_used)
                <span class="hx-sep">·</span><span>{{ $article->ai_engine_used }}</span>
            @endif
            @if($article->ai_cost)
                <span class="hx-sep">·</span><span>${{ number_format((float) $article->ai_cost, 4) }}</span>
            @endif
            @if($article->wp_post_id)
                <span class="hx-sep">·</span><span>WP #{{ $article->wp_post_id }}</span>
            @endif
        </div>
        <div class="hx-article-actions">
            <button type="button"
                @click="refreshing = true; refreshNotice = ''; refreshError = '';
                    fetch('/publish/articles/{{ $article->id }}/refresh-wp', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success || d.message) {
                            refreshNotice = d.message || 'Refreshed.';
                            setTimeout(() => window.location.reload(), 450);
                        } else {
                            refreshError = d.error || 'Refresh failed.';
                            refreshing = false;
                        }
                    })
                    .catch(e => { refreshError = e.message || 'Network error'; refreshing = false; })"
                :disabled="refreshing"
                class="hx-article-btn hx-article-btn-secondary"
                title="Pull latest WordPress status/title/url from the live site">
                <svg x-show="!refreshing" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg x-show="refreshing" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="refreshing ? 'Refreshing…' : 'Refresh'"></span>
            </button>
            <a href="{{ $editUrl }}" target="_blank" rel="noopener" class="hx-article-btn hx-article-btn-secondary" title="Open this article in the pipeline editor">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            @if($wpLink)
                <a href="{{ $wpLink }}" target="_blank" rel="noopener" class="hx-article-btn hx-article-btn-secondary">{{ $wpLinkLabel }}&nbsp;↗</a>
            @endif
            @if($article->wp_post_url && $isDraft && $wpLink !== $article->wp_post_url)
                <a href="{{ $article->wp_post_url }}?preview=true" target="_blank" rel="noopener" class="hx-article-btn hx-article-btn-secondary">Preview&nbsp;↗</a>
            @endif
            <a href="{{ $showUrl }}" target="_blank" rel="noopener" class="hx-article-btn hx-article-btn-primary">Open&nbsp;→</a>
        </div>
    </div>
    <div x-show="refreshNotice || refreshError" x-cloak class="px-5 pb-3 text-xs" :class="refreshError ? 'text-red-600' : 'text-green-600'">
        <span x-text="refreshError || refreshNotice"></span>
    </div>
</article>
