{{--
    Reusable Photo Picker — stock + Google Images in one component.
    Used for featured image, inline photo change, and overlay.
--}}
@php
    $pickerId = $pickerId ?? 'photo-picker-' . uniqid();
    $searchQuery = $searchQuery ?? '';
    $onSelect = $onSelect ?? 'null';
    $autoLoadStock = $autoLoadStock ?? false;
@endphp

<div data-photo-picker x-data="{
    pickerId: '{{ $pickerId }}',
    stockQuery: '',
    googleQuery: '',
    stockResults: [],
    googleResults: [],
    stockSearching: false,
    googleSearching: false,
    stockLoadingMore: false,
    googleLoadingMore: false,
    stockTimings: {},
    googleTiming: 0,
    stockError: '',
    googleError: '',
    stockPage: 1,
    googlePage: 1,
    selectedPhotoKey: null,

    loadTerm(term) {
        this.stockQuery = term;
        this.googleQuery = term;
    },

    async searchStock(loadMore = false) {
        if (!this.stockQuery.trim()) return;
        if (loadMore) { this.stockLoadingMore = true; this.stockPage++; }
        else { this.stockSearching = true; this.stockResults = []; this.stockPage = 1; this.selectedPhotoKey = null; }
        this.stockError = '';
        try {
            const resp = await fetch('{{ route('publish.search.images.post') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
                body: JSON.stringify({ query: this.stockQuery, per_page: 4, page: this.stockPage, sources: ['pexels', 'unsplash', 'pixabay'] })
            });
            const data = await resp.json();
            const photos = data.data?.photos || [];
            if (loadMore) { this.stockResults = [...this.stockResults, ...photos]; }
            else { this.stockResults = photos; }
            this.stockTimings = data.data?.timings || {};
            if (!data.success && data.data?.errors?.length) this.stockError = data.data.errors.join(', ');
        } catch (e) { this.stockError = e.message; }
        this.stockSearching = false;
        this.stockLoadingMore = false;
    },

    async searchGoogle(loadMore = false) {
        if (!this.googleQuery.trim()) return;
        if (loadMore) { this.googleLoadingMore = true; this.googlePage++; }
        else { this.googleSearching = true; this.googleResults = []; this.googlePage = 1; this.selectedPhotoKey = null; }
        this.googleError = '';
        const start = Date.now();
        try {
            const resp = await fetch('{{ route('publish.search.google-images') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
                body: JSON.stringify({ query: this.googleQuery, per_page: 12, start: (this.googlePage - 1) * 12 })
            });
            const data = await resp.json();
            this.googleTiming = Date.now() - start;
            const photos = data.data?.photos || [];
            if (loadMore) { this.googleResults = [...this.googleResults, ...photos]; }
            else if (data.success) { this.googleResults = photos; }
            else { this.googleError = data.message || 'Search failed'; }
        } catch (e) { this.googleError = e.message; }
        this.googleSearching = false;
        this.googleLoadingMore = false;
    },

    pickPhoto(photo, key) {
        this.selectedPhotoKey = key;
        ({{ $onSelect }})(photo);
    }
}" class="space-y-4">

    {{-- Google Images Section (TOP — primary) --}}
    <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Google Images <span class="font-normal text-gray-400">(SerpAPI / Google CSE)</span></p>
        <div class="flex items-center gap-2 mb-2">
            <input type="text" x-model="googleQuery" @keydown.enter.prevent="searchGoogle()" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search Google Images...">
            <button type="button" @click.stop="searchGoogle()" :disabled="googleSearching || !googleQuery.trim()" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex-shrink-0 whitespace-nowrap inline-flex items-center gap-1">
                <svg x-show="googleSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="googleSearching ? 'Searching...' : 'Search'"></span>
            </button>
        </div>
        <div x-show="googleError" x-cloak class="text-xs text-red-600 mb-2" x-text="googleError"></div>
        <div x-show="googleTiming > 0 && googleResults.length > 0" x-cloak class="text-[10px] text-gray-400 mb-1" x-text="googleResults.length + ' photos in ' + googleTiming + 'ms'"></div>
        <div x-show="googleResults.length > 0" x-cloak class="grid grid-cols-4 gap-2">
            <template x-for="(photo, idx) in googleResults" :key="'g'+idx">
                <div @click="pickPhoto(photo, 'g'+idx)" class="cursor-pointer rounded-lg overflow-hidden border-2 transition-all relative" :class="selectedPhotoKey === 'g'+idx ? 'border-green-500 ring-2 ring-green-300' : (photo.copyright_flag ? 'border-red-600 ring-2 ring-red-200 bg-red-50 hover:border-red-700' : 'border-gray-200 hover:border-blue-400')">
                    <div class="relative">
                        <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-64 object-cover" loading="lazy">
                        <div x-show="photo.copyright_flag" x-cloak class="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-red-700 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-white shadow">
                            <span>Banned Domain</span>
                        </div>
                        <div x-show="selectedPhotoKey === 'g'+idx" x-cloak class="absolute top-1.5 right-1.5 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                    <div class="px-1.5 py-1 space-y-0.5" :class="photo.copyright_flag ? 'bg-red-50' : 'bg-white'">
                        <p class="text-[9px] font-medium text-gray-700 break-words" x-text="(photo.alt || '').substring(0, 40)"></p>
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="text-[9px]" :class="photo.copyright_flag ? 'text-red-700 font-semibold' : 'text-gray-400'" x-text="(photo.domain || '') + ' — ' + (photo.width || '?') + 'x' + (photo.height || '?')"></span>
                            <template x-if="photo.width && photo.height">
                                <span class="text-[8px] font-medium px-1 rounded" :class="(() => { const r = photo.width/photo.height; return r >= 1.3 ? 'bg-green-100 text-green-700' : (r >= 1.0 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); })()" x-text="(photo.width/photo.height).toFixed(1) + ':1'"></span>
                            </template>
                        </div>
                        <a :href="photo.url_large || photo.source_url" target="_blank" @click.stop class="text-[8px] text-blue-500 hover:underline break-all block" :title="photo.url_large || photo.source_url" x-text="(() => { try { const u = new URL(photo.url_large || photo.source_url || ''); return u.hostname + u.pathname.substring(0, 30) + '...'; } catch(e) { return ''; } })()"></a>
                        <div x-show="photo.copyright_flag" x-cloak class="rounded border border-red-200 bg-red-100 px-1.5 py-1 text-[8px] font-semibold text-red-700">
                            <div class="uppercase tracking-wide">Blacklisted for photos</div>
                            <div class="font-medium normal-case" x-text="photo.copyright_reason"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div x-show="googleResults.length > 0" x-cloak class="mt-2 text-center">
            <button type="button" @click.stop="searchGoogle(true)" :disabled="googleLoadingMore" class="text-xs text-blue-600 hover:text-blue-800 px-4 py-1.5 border border-blue-200 rounded-lg hover:bg-blue-50 inline-flex items-center gap-1 disabled:opacity-50">
                <svg x-show="googleLoadingMore" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="googleLoadingMore ? 'Loading...' : 'Load More Google Images'"></span>
            </button>
        </div>
    </div>

    {{-- Stock Photos Section (BOTTOM — secondary) --}}
    <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Stock Photos <span class="font-normal text-gray-400">(Pexels, Unsplash, Pixabay)</span></p>
        <div class="flex items-center gap-2 mb-2">
            <input type="text" x-model="stockQuery" @keydown.enter.prevent="searchStock()" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search stock photos...">
            <button type="button" @click.stop="searchStock()" :disabled="stockSearching || !stockQuery.trim()" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex-shrink-0 whitespace-nowrap inline-flex items-center gap-1">
                <svg x-show="stockSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="stockSearching ? 'Searching...' : 'Search'"></span>
            </button>
        </div>
        <div x-show="stockError" x-cloak class="text-xs text-red-600 mb-2" x-text="stockError"></div>
        <div x-show="Object.keys(stockTimings).length > 0 && stockResults.length > 0" x-cloak class="text-[10px] text-gray-400 mb-1" x-text="stockResults.length + ' photos (' + Object.entries(stockTimings).map(([k,v]) => k + ':' + v + 'ms').join(', ') + ')'"></div>
        <div x-show="stockResults.length > 0" x-cloak class="grid grid-cols-4 gap-2">
            <template x-for="(photo, idx) in stockResults" :key="'s'+idx">
                <div @click="pickPhoto(photo, 's'+idx)" class="cursor-pointer rounded-lg overflow-hidden border-2 transition-all relative" :class="selectedPhotoKey === 's'+idx ? 'border-green-500 ring-2 ring-green-300' : 'border-gray-200 hover:border-purple-400'">
                    <div class="relative">
                        <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-64 object-cover" loading="lazy">
                        <div x-show="selectedPhotoKey === 's'+idx" x-cloak class="absolute top-1.5 right-1.5 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                    <div class="px-1.5 py-1 bg-white space-y-0.5">
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="text-[9px] text-gray-500" x-text="photo.source + ' — ' + (photo.width || '?') + 'x' + (photo.height || '?')"></span>
                            <template x-if="photo.width && photo.height">
                                <span class="text-[8px] font-medium px-1 rounded" :class="(() => { const r = photo.width/photo.height; return r >= 1.2 ? 'bg-green-100 text-green-700' : (r >= 0.8 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); })()" x-text="(photo.width/photo.height).toFixed(1) + ':1'"></span>
                            </template>
                        </div>
                        <a :href="photo.url_large" target="_blank" @click.stop class="text-[8px] text-blue-500 hover:underline break-all block" :title="photo.url_large" x-text="(() => { try { const u = new URL(photo.url_large || ''); return u.hostname + u.pathname.substring(0, 30) + '...'; } catch(e) { return ''; } })()"></a>
                    </div>
                </div>
            </template>
        </div>
        <div x-show="stockResults.length > 0" x-cloak class="mt-2 text-center">
            <button type="button" @click.stop="searchStock(true)" :disabled="stockLoadingMore" class="text-xs text-purple-600 hover:text-purple-800 px-4 py-1.5 border border-purple-200 rounded-lg hover:bg-purple-50 inline-flex items-center gap-1 disabled:opacity-50">
                <svg x-show="stockLoadingMore" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="stockLoadingMore ? 'Loading...' : 'Load More Stock Photos'"></span>
            </button>
        </div>
    </div>
</div>
