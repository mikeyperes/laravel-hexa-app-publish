@php
    $title = $title ?? 'Photo Assets';
    $description = $description ?? 'Choose one featured image and one or more inline photos from the same gallery.';
    $assetsExpression = $assetsExpression ?? '[]';
    $assetsShowExpression = $assetsShowExpression ?? '(' . $assetsExpression . ').length > 0';
    $assetKeyExpression = $assetKeyExpression ?? 'asset.id || asset.key || assetIdx';
    $featuredSelectedExpression = $featuredSelectedExpression ?? 'false';
    $inlineSelectedExpression = $inlineSelectedExpression ?? 'false';
    $thumbUrlExpression = $thumbUrlExpression ?? 'asset.thumbnailLink || asset.preview_url || asset.thumbnail_url || asset.url || asset.webContentLink || asset.webViewLink';
    $labelExpression = $labelExpression ?? 'asset.label || asset.name || "Photo"';
    $sourceLabelExpression = $sourceLabelExpression ?? 'asset.source_label || asset.source || "source"';
    $sourceMetaHtmlExpression = $sourceMetaHtmlExpression ?? '""';
    $downloadUrlExpression = $downloadUrlExpression ?? 'asset.download_url || asset.webContentLink || asset.url || asset.webViewLink || asset.thumbnailLink';
    $viewUrlExpression = $viewUrlExpression ?? 'asset.view_url || asset.source_url || asset.webViewLink || asset.url || asset.webContentLink';
    $setFeaturedAction = $setFeaturedAction ?? '';
    $toggleInlineAction = $toggleInlineAction ?? '';
    $featuredButtonTextExpression = $featuredButtonTextExpression ?? '(' . $featuredSelectedExpression . ' ? "Featured Image" : "Set Featured")';
    $inlineButtonTextExpression = $inlineButtonTextExpression ?? '(' . $inlineSelectedExpression . ' ? "Inline Selected" : "Add Inline")';
    $inlineButtonDisabledExpression = $inlineButtonDisabledExpression ?? 'false';
    $fallbackUrlsExpression = $fallbackUrlsExpression ?? '[asset.thumbnailLink, asset.preview_url, asset.thumbnail_url, asset.thumbnails?.["1280"], asset.thumbnails?.["640"], asset.quality_urls?.thumb_1280, asset.quality_urls?.thumb_640, asset.download_url, asset.source_url, asset.webContentLink, asset.webViewLink, asset.url].filter((value, index, arr) => value && arr.indexOf(value) === index)';
    $countBadgeExpression = $countBadgeExpression ?? '(' . $assetsExpression . ').length + " photo(s)"';
    $featuredBadgeExpression = $featuredBadgeExpression ?? '"Featured not set"';
    $inlineBadgeExpression = $inlineBadgeExpression ?? '"Inline selected: 0"';
    $showSelectAll = $showSelectAll ?? false;
    $selectAllAction = $selectAllAction ?? '';
    $clearInlineAction = $clearInlineAction ?? '';
@endphp

<div x-show='{!! $assetsShowExpression !!}' x-cloak>
    <div class="flex flex-col gap-2 mb-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h5 class="text-sm font-semibold text-gray-700">{{ $title }}</h5>
            <p class="text-xs text-gray-500">{{ $description }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-700 shadow-sm" x-text='{!! $countBadgeExpression !!}'></span>
            <span class="inline-flex items-center rounded-full border border-indigo-600 bg-indigo-600 px-3 py-1 text-[11px] font-semibold text-white shadow-sm" x-text='{!! $featuredBadgeExpression !!}'></span>
            <span class="inline-flex items-center rounded-full border border-emerald-600 bg-emerald-600 px-3 py-1 text-[11px] font-semibold text-white shadow-sm" x-text='{!! $inlineBadgeExpression !!}'></span>
            @if($showSelectAll)
                <button @click.prevent="{!! $selectAllAction !!}" type="button" class="text-indigo-600 hover:text-indigo-800 font-medium">Select all inline</button>
                <button @click.prevent="{!! $clearInlineAction !!}" type="button" class="text-gray-500 hover:text-gray-700 font-medium">Select none</button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <template x-for='(asset, assetIdx) in {!! $assetsExpression !!}' :key='{!! $assetKeyExpression !!}'>
            <div class="rounded-2xl overflow-hidden border-2 bg-white transition-all duration-200"
                :class='{!! '(' . $featuredSelectedExpression . ' ? "border-indigo-500 ring-4 ring-indigo-100 shadow-lg shadow-indigo-100/80" : (' . $inlineSelectedExpression . ' ? "border-emerald-500 ring-4 ring-emerald-100 shadow-lg shadow-emerald-100/80" : "border-gray-200 hover:border-gray-300 hover:shadow-md"))' !!}'>
                <div class="relative select-none">
                    <div class="absolute inset-0 hidden items-center justify-center px-3 text-center text-[11px] font-medium text-gray-500 bg-gray-50 js-shared-photo-fallback">Image blocked by source</div>
                    <img :src='{!! $thumbUrlExpression !!}' :data-fallback-urls='JSON.stringify({!! $fallbackUrlsExpression !!})' data-fallback-index="0" :alt='{!! $labelExpression !!}' class="w-full h-56 object-cover bg-gray-50" loading="eager" decoding="async" referrerpolicy="no-referrer" onerror="const urls = JSON.parse(this.dataset.fallbackUrls || '[]'); const current = this.currentSrc || this.src; let idx = Number(this.dataset.fallbackIndex || '0'); while (idx + 1 < urls.length) { idx += 1; this.dataset.fallbackIndex = String(idx); const next = urls[idx]; if (next && next !== current) { this.src = next; return; } } this.style.display='none'; this.parentElement.querySelector('.js-shared-photo-fallback')?.classList.remove('hidden'); this.parentElement.querySelector('.js-shared-photo-fallback')?.classList.add('flex');">
                    <div class="absolute flex flex-wrap" style="left:0.2rem;top:0.2rem;gap:0.25rem;">
                        <span x-show='{!! $featuredSelectedExpression !!}' x-cloak class="inline-flex items-center font-semibold tracking-wide text-white" style="border:1px solid rgba(165,180,252,0.9);background:#312e81;border-radius:9999px;padding:0.4rem 0.85rem;font-size:11px;line-height:1;box-shadow:0 8px 18px rgba(49,46,129,0.28);">Featured</span>
                        <span x-show='{!! $inlineSelectedExpression !!}' x-cloak class="inline-flex items-center font-semibold tracking-wide text-white" style="border:1px solid rgba(110,231,183,0.92);background:#065f46;border-radius:9999px;padding:0.4rem 0.85rem;font-size:11px;line-height:1;box-shadow:0 8px 18px rgba(6,95,70,0.24);">Inline</span>
                    </div>
                    <div class="absolute right-2 top-2">
                        <span x-show='{!! $featuredSelectedExpression . ' || ' . $inlineSelectedExpression !!}' x-cloak class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-white text-gray-900 shadow-md border border-gray-200">
                            <svg class="w-4 h-4" :class='{!! $featuredSelectedExpression !!} ? "text-indigo-600" : "text-emerald-600"' fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </span>
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-sm font-medium text-gray-800 break-words" x-text='{!! $labelExpression !!}'></p>
                    <p class="text-xs font-medium text-gray-500 mt-1 break-words" x-text='{!! $sourceLabelExpression !!}'></p>
                    <div x-show='{!! $sourceMetaHtmlExpression !!}' x-cloak class="mt-1 text-[11px] text-gray-600 break-words leading-relaxed" x-html='{!! $sourceMetaHtmlExpression !!}'></div>
                    <div class="flex items-center gap-2 mt-1 text-[11px] flex-wrap">
                        <span x-show='asset.width && asset.height' x-cloak class="text-gray-400" x-text='(asset.width || "") + "x" + (asset.height || "")'></span>
                        <template x-if='asset.width && asset.height'>
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium"
                                :class='(() => { const r = asset.width / asset.height; if (r >= 1.3 && r <= 2.0) return "bg-green-100 text-green-700"; if (r >= 1.0 && r < 1.3) return "bg-yellow-100 text-yellow-700"; if (r < 1.0) return "bg-red-100 text-red-700"; return "bg-red-100 text-red-700"; })()'
                                x-text='(() => { const r = asset.width / asset.height; const label = r.toFixed(2) + ":1"; if (r >= 1.3 && r <= 2.0) return label + " Landscape"; if (r >= 1.0 && r < 1.3) return label + " Square"; if (r < 1.0) return label + " Portrait"; return label + " Ultra-wide"; })()'>
                            </span>
                        </template>
                        <template x-if='asset.width && asset.height && (asset.width / asset.height) < 1.3'>
                            <span class="text-[10px] text-red-500">Bad for featured</span>
                        </template>
                    </div>
                    <div x-show='{!! $featuredSelectedExpression !!}' x-cloak class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700">
                        Selected as the featured image
                    </div>
                    <div x-show='!({!! $featuredSelectedExpression !!}) && ({!! $inlineSelectedExpression !!})' x-cloak class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                        Selected for inline body placement
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <button @click.prevent="{!! $setFeaturedAction !!}" type="button" class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition-colors"
                            :class='{!! '(' . $featuredSelectedExpression . ' ? "bg-indigo-600 text-white shadow-sm" : "bg-indigo-50 text-indigo-700 hover:bg-indigo-100")' !!}'
                            x-text='{!! $featuredButtonTextExpression !!}'></button>
                        <button @click.prevent="{!! $toggleInlineAction !!}" type="button" class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled='{!! $inlineButtonDisabledExpression !!}'
                            :class='{!! '(' . $inlineButtonDisabledExpression . ' ? "bg-slate-100 text-slate-500 border border-slate-200" : (' . $inlineSelectedExpression . ' ? "bg-emerald-600 text-white shadow-sm" : "bg-emerald-50 text-emerald-700 hover:bg-emerald-100"))' !!}'
                            x-text='{!! $inlineButtonTextExpression !!}'></button>
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                        <a :href='{!! $downloadUrlExpression !!}' target="_blank" @click.stop class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download
                        </a>
                        <a :href='{!! $viewUrlExpression !!}' target="_blank" @click.stop class="inline-flex items-center gap-1 text-xs text-gray-500 hover:underline">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Open
                        </a>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
