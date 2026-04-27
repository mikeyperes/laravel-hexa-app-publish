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
    draftId: (() => {
        const root = document.querySelector('[data-publish-draft-id]');
        const raw = root ? root.getAttribute('data-publish-draft-id') : '';
        return raw && raw !== '0' ? raw : null;
    })(),
    stockQuery: '',
    serpApiQuery: '',
    serperQuery: '',
    stockResults: [],
    serpApiResults: [],
    serperResults: [],
    stockSearching: false,
    serpApiSearching: false,
    serperSearching: false,
    stockLoadingMore: false,
    serpApiLoadingMore: false,
    serperLoadingMore: false,
    stockTimings: {},
    serpApiTiming: 0,
    serperTiming: 0,
    stockError: '',
    serpApiError: '',
    serperError: '',
    instagramPostUrl: '',
    instagramImporting: false,
    instagramImportError: '',
    instagramResults: [],
    stockPage: 1,
    serpApiPage: 1,
    serperPage: 1,
    selectedPhotoKey: null,

    loadTerm(term) {
        this.stockQuery = term;
        this.serpApiQuery = term;
        this.serperQuery = term;
    },

    async searchStock(loadMore = false) {
        if (!this.stockQuery.trim()) return;
        if (loadMore) { this.stockLoadingMore = true; this.stockPage++; }
        else { this.stockSearching = true; this.stockResults = []; this.stockPage = 1; this.selectedPhotoKey = null; }
        this.stockError = '';
        try {
            const resp = await fetch('{{ route('publish.search.images.post') }}', {
                method: 'POST',
                headers: window.hexaRequestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ query: this.stockQuery, per_page: 4, page: this.stockPage, sources: ['pexels', 'unsplash', 'pixabay'], draft_id: this.draftId })
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

    async searchSerpApi(loadMore = false) {
        if (!this.serpApiQuery.trim()) return;
        if (loadMore) { this.serpApiLoadingMore = true; this.serpApiPage++; }
        else { this.serpApiSearching = true; this.serpApiResults = []; this.serpApiPage = 1; this.selectedPhotoKey = null; }
        this.serpApiError = '';
        const start = Date.now();
        try {
            const resp = await fetch('{{ route('publish.search.google-images') }}', {
                method: 'POST',
                headers: window.hexaRequestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ query: this.serpApiQuery, per_page: 12, start: (this.serpApiPage - 1) * 12, draft_id: this.draftId, provider: 'serpapi' })
            });
            const data = await resp.json();
            this.serpApiTiming = Date.now() - start;
            const photos = data.data?.photos || [];
            if (loadMore) { this.serpApiResults = [...this.serpApiResults, ...photos]; }
            else if (data.success) { this.serpApiResults = photos; }
            else { this.serpApiError = data.message || 'Search failed'; }
        } catch (e) { this.serpApiError = e.message; }
        this.serpApiSearching = false;
        this.serpApiLoadingMore = false;
    },

    async searchSerper(loadMore = false) {
        if (!this.serperQuery.trim()) return;
        if (loadMore) { this.serperLoadingMore = true; this.serperPage++; }
        else { this.serperSearching = true; this.serperResults = []; this.serperPage = 1; this.selectedPhotoKey = null; }
        this.serperError = '';
        const start = Date.now();
        try {
            const resp = await fetch('{{ route('publish.search.google-images') }}', {
                method: 'POST',
                headers: window.hexaRequestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ query: this.serperQuery, per_page: 12, start: (this.serperPage - 1) * 12, draft_id: this.draftId, provider: 'serper' })
            });
            const data = await resp.json();
            this.serperTiming = Date.now() - start;
            const photos = data.data?.photos || [];
            if (loadMore) { this.serperResults = [...this.serperResults, ...photos]; }
            else if (data.success) { this.serperResults = photos; }
            else { this.serperError = data.message || 'Search failed'; }
        } catch (e) { this.serperError = e.message; }
        this.serperSearching = false;
        this.serperLoadingMore = false;
    },

    async importInstagramPost() {
        if (!this.instagramPostUrl.trim()) return;
        this.instagramImporting = true;
        this.instagramImportError = '';
        this.instagramResults = [];
        try {
            const resp = await fetch('{{ route('content-extractor.import') }}', {
                method: 'POST',
                headers: window.hexaRequestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ url: this.instagramPostUrl.trim(), include_image_data: false })
            });
            const data = await resp.json();
            if (!data.success || !data.data?.image_url) {
                this.instagramImportError = data.message || 'Instagram import failed';
                return;
            }
            this.instagramResults = [{
                source: 'instagram',
                source_url: data.data.source_url,
                url_large: data.data.image_url,
                url_thumb: data.data.thumbnail_url || data.data.image_url,
                alt: data.data.caption || data.data.title || data.data.author_name || 'Instagram post image',
                title: data.data.title || '',
                width: data.data.width || 0,
                height: data.data.height || 0,
                domain: 'instagram.com',
                copyright_flag: false,
                attribution: data.data.author_name || '',
                provider: 'instagram',
            }];
        } catch (e) {
            this.instagramImportError = e.message;
        }
        this.instagramImporting = false;
    },


    pickPhoto(photo, key) {
        this.selectedPhotoKey = key;
        ({{ $onSelect }})(photo);
    }
}" class="space-y-5">

    <div class="space-y-4">
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Google Images via SerpAPI</p>
            <div class="flex items-center gap-2 mb-2">
                <input type="text" x-model="serpApiQuery" @keydown.enter.prevent="searchSerpApi()" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search Google Images with SerpAPI...">
                <button type="button" @click.stop="searchSerpApi()" :disabled="serpApiSearching || !serpApiQuery.trim()" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex-shrink-0 whitespace-nowrap inline-flex items-center gap-1">
                    <svg x-show="serpApiSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="serpApiSearching ? 'Searching...' : 'Search'"/>
                </button>
            </div>
            <div x-show="serpApiError" x-cloak class="text-xs text-red-600 mb-2" x-text="serpApiError"></div>
            <div x-show="serpApiTiming > 0 && serpApiResults.length > 0" x-cloak class="text-[10px] text-gray-400 mb-1" x-text="serpApiResults.length + ' photos in ' + serpApiTiming + 'ms'"></div>
            <div x-show="serpApiResults.length > 0" x-cloak class="grid grid-cols-4 gap-2">
                <template x-for="(photo, idx) in serpApiResults" :key="'ap'+idx">
                    <div @click="pickPhoto(photo, 'ap'+idx)" class="cursor-pointer rounded-lg overflow-hidden border-2 transition-all relative" :class="selectedPhotoKey === 'ap'+idx ? 'border-green-500 ring-2 ring-green-300' : (photo.copyright_flag ? 'border-red-600 ring-2 ring-red-200 bg-red-50 hover:border-red-700' : 'border-gray-200 hover:border-blue-400')">
                        <div class="relative">
                            <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-64 object-cover" loading="lazy">
                            <div x-show="photo.copyright_flag" x-cloak class="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-red-700 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-white shadow">
                                <span>Banned Domain</span>
                            </div>
                            <div x-show="selectedPhotoKey === 'ap'+idx" x-cloak class="absolute top-2 right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white z-10">
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
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="serpApiResults.length > 0" x-cloak class="mt-2 text-center">
                <button type="button" @click.stop="searchSerpApi(true)" :disabled="serpApiLoadingMore" class="text-xs text-blue-600 hover:text-blue-800 px-4 py-1.5 border border-blue-200 rounded-lg hover:bg-blue-50 inline-flex items-center gap-1 disabled:opacity-50">
                    <svg x-show="serpApiLoadingMore" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="serpApiLoadingMore ? 'Loading...' : 'Load More SerpAPI Images'"></span>
                </button>
            </div>
        </div>

        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Google Images via Serper.dev</p>
            <div class="flex items-center gap-2 mb-2">
                <input type="text" x-model="serperQuery" @keydown.enter.prevent="searchSerper()" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search Google Images with Serper.dev...">
                <button type="button" @click.stop="searchSerper()" :disabled="serperSearching || !serperQuery.trim()" class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50 flex-shrink-0 whitespace-nowrap inline-flex items-center gap-1">
                    <svg x-show="serperSearching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="serperSearching ? 'Searching...' : 'Search'"/>
                </button>
            </div>
            <div x-show="serperError" x-cloak class="text-xs text-red-600 mb-2" x-text="serperError"></div>
            <div x-show="serperTiming > 0 && serperResults.length > 0" x-cloak class="text-[10px] text-gray-400 mb-1" x-text="serperResults.length + ' photos in ' + serperTiming + 'ms'"></div>
            <div x-show="serperResults.length > 0" x-cloak class="grid grid-cols-4 gap-2">
                <template x-for="(photo, idx) in serperResults" :key="'sp'+idx">
                    <div @click="pickPhoto(photo, 'sp'+idx)" class="cursor-pointer rounded-lg overflow-hidden border-2 transition-all relative" :class="selectedPhotoKey === 'sp'+idx ? 'border-green-500 ring-2 ring-green-300' : (photo.copyright_flag ? 'border-red-600 ring-2 ring-red-200 bg-red-50 hover:border-red-700' : 'border-gray-200 hover:border-indigo-400')">
                        <div class="relative">
                            <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-64 object-cover" loading="lazy">
                            <div x-show="photo.copyright_flag" x-cloak class="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-red-700 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-white shadow">
                                <span>Banned Domain</span>
                            </div>
                            <div x-show="selectedPhotoKey === 'sp'+idx" x-cloak class="absolute top-2 right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white z-10">
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
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="serperResults.length > 0" x-cloak class="mt-2 text-center">
                <button type="button" @click.stop="searchSerper(true)" :disabled="serperLoadingMore" class="text-xs text-indigo-600 hover:text-indigo-800 px-4 py-1.5 border border-indigo-200 rounded-lg hover:bg-indigo-50 inline-flex items-center gap-1 disabled:opacity-50">
                    <svg x-show="serperLoadingMore" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="serperLoadingMore ? 'Loading...' : 'Load More Serper.dev Images'"></span>
                </button>
            </div>
        </div>
    </div>


    <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Instagram Post Import</p>
        <div class="flex items-center gap-2 mb-2">
            <input type="text" x-model="instagramPostUrl" @keydown.enter.prevent="importInstagramPost()" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Paste a public Instagram post URL...">
            <button type="button" @click.stop="importInstagramPost()" :disabled="instagramImporting || !instagramPostUrl.trim()" class="bg-pink-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-pink-700 disabled:opacity-50 flex-shrink-0 whitespace-nowrap inline-flex items-center gap-1">
                <svg x-show="instagramImporting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="instagramImporting ? 'Importing...' : 'Import'"/>
            </button>
        </div>
        <div x-show="instagramImportError" x-cloak class="text-xs text-red-600 mb-2" x-text="instagramImportError"></div>
        <div x-show="instagramResults.length > 0" x-cloak class="grid grid-cols-4 gap-2">
            <template x-for="(photo, idx) in instagramResults" :key="'ig'+idx">
                <div @click="pickPhoto(photo, 'ig'+idx)" class="cursor-pointer rounded-lg overflow-hidden border-2 transition-all relative" :class="selectedPhotoKey === 'ig'+idx ? 'border-green-500 ring-2 ring-green-300' : 'border-gray-200 hover:border-pink-400'">
                    <div class="relative">
                        <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-64 object-cover" loading="lazy">
                        <div x-show="selectedPhotoKey === 'ig'+idx" x-cloak class="absolute top-2 right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white z-10">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                    <div class="px-1.5 py-1 bg-white space-y-0.5">
                        <p class="text-[9px] font-medium text-gray-700 break-words" x-text="(photo.alt || '').substring(0, 60)"></p>
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="text-[9px] text-gray-500" x-text="'instagram.com' + (photo.attribution ? ' — @' + photo.attribution : '')"></span>
                            <template x-if="photo.width && photo.height">
                                <span class="text-[8px] font-medium px-1 rounded" :class="(() => { const r = photo.width/photo.height; return r >= 1.2 ? 'bg-green-100 text-green-700' : (r >= 0.8 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); })()" x-text="(photo.width/photo.height).toFixed(1) + ':1'"></span>
                            </template>
                        </div>
                        <a :href="photo.source_url" target="_blank" @click.stop class="text-[8px] text-blue-500 hover:underline break-all block" x-text="photo.source_url"></a>
                    </div>
                </div>
            </template>
        </div>
    </div>

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
                        <div x-show="selectedPhotoKey === 's'+idx" x-cloak class="absolute top-2 right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white z-10">
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
