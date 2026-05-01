@php
    $title = $title ?? 'Photo Assets';
    $description = $description ?? 'Choose one featured image and one or more inline photos from the same gallery.';
    $assetsExpression = $assetsExpression ?? '[]';
    $assetsShowExpression = $assetsShowExpression ?? '(' . $assetsExpression . ').length > 0';
    $assetKeyExpression = $assetKeyExpression ?? 'asset.id || asset.key || assetIdx';
    $featuredSelectedExpression = $featuredSelectedExpression ?? 'false';
    $inlineSelectedExpression = $inlineSelectedExpression ?? 'false';
    $thumbUrlExpression = $thumbUrlExpression ?? 'asset.thumbnail_url || asset.thumbnailLink || asset.url || asset.webContentLink || asset.webViewLink';
    $labelExpression = $labelExpression ?? 'asset.label || asset.name || "Photo"';
    $sourceLabelExpression = $sourceLabelExpression ?? 'asset.source_label || asset.source || "source"';
    $downloadUrlExpression = $downloadUrlExpression ?? 'asset.download_url || asset.webContentLink || asset.url || asset.webViewLink || asset.thumbnailLink';
    $viewUrlExpression = $viewUrlExpression ?? 'asset.view_url || asset.source_url || asset.webViewLink || asset.url || asset.webContentLink';
    $setFeaturedAction = $setFeaturedAction ?? '';
    $toggleInlineAction = $toggleInlineAction ?? '';
    $featuredButtonTextExpression = $featuredButtonTextExpression ?? '(' . $featuredSelectedExpression . ' ? "Featured Image" : "Set Featured")';
    $inlineButtonTextExpression = $inlineButtonTextExpression ?? '(' . $inlineSelectedExpression . ' ? "Inline Selected" : "Add Inline")';
    $inlineButtonDisabledExpression = $inlineButtonDisabledExpression ?? 'false';
    $countBadgeExpression = $countBadgeExpression ?? '(' . $assetsExpression . ').length + " photo(s)"';
    $featuredBadgeExpression = $featuredBadgeExpression ?? '"Featured not set"';
    $inlineBadgeExpression = $inlineBadgeExpression ?? '"Inline selected: 0"';
    $showSelectAll = $showSelectAll ?? false;
    $selectAllAction = $selectAllAction ?? '';
    $clearInlineAction = $clearInlineAction ?? '';
@endphp

<div x-show="{!! $assetsShowExpression !!}" x-cloak>
    <div class="flex flex-col gap-2 mb-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h5 class="text-sm font-semibold text-gray-700">{{ $title }}</h5>
            <p class="text-xs text-gray-500">{{ $description }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600" x-text="{!! $countBadgeExpression !!}"></span>
            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 font-medium text-indigo-700" x-text="{!! $featuredBadgeExpression !!}"></span>
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-700" x-text="{!! $inlineBadgeExpression !!}"></span>
            @if($showSelectAll)
                <button @click.prevent="{!! $selectAllAction !!}" type="button" class="text-indigo-600 hover:text-indigo-800 font-medium">Select all inline</button>
                <button @click.prevent="{!! $clearInlineAction !!}" type="button" class="text-gray-500 hover:text-gray-700 font-medium">Select none</button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <template x-for="(asset, assetIdx) in {!! $assetsExpression !!}" :key="{!! $assetKeyExpression !!}">
            <div class="rounded-xl overflow-hidden border-2 bg-white transition-all"
                :class="{!! '(' . $featuredSelectedExpression . ' ? \"border-indigo-500 ring-2 ring-indigo-200\" : (' . $inlineSelectedExpression . ' ? \"border-emerald-500 ring-2 ring-emerald-200\" : \"border-gray-200 hover:border-gray-300\"))' !!}">
                <div class="relative select-none">
                    <div class="absolute inset-0 hidden items-center justify-center px-3 text-center text-[11px] font-medium text-gray-500 bg-gray-50 js-shared-photo-fallback">Image blocked by source</div>
                    <img :src="{!! $thumbUrlExpression !!}" :alt="{!! $labelExpression !!}" class="w-full h-56 object-cover" loading="lazy" onerror="this.style.display='none'; this.parentElement.querySelector('.js-shared-photo-fallback')?.classList.remove('hidden'); this.parentElement.querySelector('.js-shared-photo-fallback')?.classList.add('flex');">
                    <div class="absolute left-2 top-2 flex flex-wrap gap-1">
                        <span x-show="{!! $featuredSelectedExpression !!}" x-cloak class="inline-flex items-center rounded-full bg-indigo-500 px-2 py-1 text-[10px] font-semibold text-white shadow-sm">Featured</span>
                        <span x-show="{!! $inlineSelectedExpression !!}" x-cloak class="inline-flex items-center rounded-full bg-emerald-500 px-2 py-1 text-[10px] font-semibold text-white shadow-sm">Inline</span>
                    </div>
                </div>
                <div class="p-3">
                    <p class="text-sm font-medium text-gray-800 break-words" x-text="{!! $labelExpression !!}"></p>
                    <p class="text-xs text-gray-400 mt-0.5 break-words" x-text="{!! $sourceLabelExpression !!}"></p>
                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <button @click.prevent="{!! $setFeaturedAction !!}" type="button" class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition-colors"
                            :class="{!! '(' . $featuredSelectedExpression . ' ? \"bg-indigo-600 text-white\" : \"bg-indigo-50 text-indigo-700 hover:bg-indigo-100\")' !!}"
                            x-text="{!! $featuredButtonTextExpression !!}"></button>
                        <button @click.prevent="{!! $toggleInlineAction !!}" type="button" class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="{!! $inlineButtonDisabledExpression !!}"
                            :class="{!! '(' . $inlineSelectedExpression . ' ? \"bg-emerald-600 text-white\" : \"bg-emerald-50 text-emerald-700 hover:bg-emerald-100\")' !!}"
                            x-text="{!! $inlineButtonTextExpression !!}"></button>
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                        <a :href="{!! $downloadUrlExpression !!}" target="_blank" @click.stop class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download
                        </a>
                        <a :href="{!! $viewUrlExpression !!}" target="_blank" @click.stop class="inline-flex items-center gap-1 text-xs text-gray-500 hover:underline">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Open
                        </a>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
