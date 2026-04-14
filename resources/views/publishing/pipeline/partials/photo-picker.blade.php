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

<div x-data="{
    pickerId: '{{ $pickerId }}',
    stockQuery: '{{ $searchQuery }}',
    googleQuery: '{{ $searchQuery }}',
    stockResults: [],
    googleResults: [],
    stockSearching: false,
    googleSearching: false,
    stockTimings: {},
    googleTiming: 0,
    stockError: '',
    googleError: '',

    init() {
        @if($autoLoadStock)
        this.$nextTick(() => {
            if (!this.stockQuery) {
                const card = this.$el.closest('.border');
                if (card) {
                    const termEl = card.querySelector('.text-purple-700');
                    if (termEl) {
                        this.stockQuery = termEl.textContent.trim();
                        this.googleQuery = termEl.textContent.trim();
                    }
                }
            }
            if (this.stockQuery.trim()) this.searchStock();
        });
        @endif
    },

    async searchStock() {
        if (!this.stockQuery.trim()) return;
        this.stockSearching = true;
        this.stockResults = [];
        this.stockError = '';
        try {
            const resp = await fetch('{{ route('publish.search.images.post') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
                body: JSON.stringify({ query: this.stockQuery, per_page: 6, sources: ['pexels', 'unsplash', 'pixabay'] })
            });
            const data = await resp.json();
            this.stockResults = data.data?.photos || [];
            this.stockTimings = data.data?.timings || {};
            if (!data.success && data.data?.errors?.length) this.stockError = data.data.errors.join(', ');
        } catch (e) { this.stockError = e.message; }
        this.stockSearching = false;
    },

    async searchGoogle() {
        if (!this.googleQuery.trim()) return;
        this.googleSearching = true;
        this.googleResults = [];
        this.googleError = '';
        const start = Date.now();
        try {
            const resp = await fetch('{{ route('publish.search.google-images') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
                body: JSON.stringify({ query: this.googleQuery, per_page: 8 })
            });
            const data = await resp.json();
            this.googleTiming = Date.now() - start;
            if (data.success) { this.googleResults = data.data?.photos || []; }
            else { this.googleError = data.message || 'Search failed'; }
        } catch (e) { this.googleError = e.message; }
        this.googleSearching = false;
    },

    pickPhoto(photo) {
        {{ $onSelect }}(photo);
    }
}" class="space-y-4">

    {{-- Google Images Section (TOP — primary) --}}
    <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Google Images <span class="font-normal text-gray-400">(SerpAPI / Google CSE)</span></p>
        <div class="flex items-center gap-2 mb-2">
            <input type="text" x-model="googleQuery" @keydown.enter.prevent="searchGoogle()" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search Google Images...">
            <button type="button" @click.stop="searchGoogle()" :disabled="googleSearching || !googleQuery.trim()" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 flex-shrink-0 whitespace-nowrap">Search</button>
        </div>
        <div x-show="googleError" x-cloak class="text-xs text-red-600 mb-2" x-text="googleError"></div>
        <div x-show="googleTiming > 0 && googleResults.length > 0" x-cloak class="text-[10px] text-gray-400 mb-1" x-text="googleResults.length + ' photos in ' + googleTiming + 'ms'"></div>
        <div x-show="googleResults.length > 0" x-cloak class="grid grid-cols-3 gap-2">
            <template x-for="(photo, idx) in googleResults" :key="'g'+idx">
                <div @click="pickPhoto(photo)" class="cursor-pointer rounded-lg overflow-hidden border-2 transition-colors" :class="photo.copyright_flag ? 'border-red-300 hover:border-red-500' : 'border-gray-200 hover:border-blue-400'">
                    <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-32 object-cover" loading="lazy">
                    <div class="px-1.5 py-1 bg-white">
                        <p class="text-[9px] font-medium text-gray-700 break-words" x-text="(photo.alt || '').substring(0, 40)"></p>
                        <p class="text-[9px] text-gray-400" x-text="photo.domain + ' — ' + (photo.width || '?') + 'x' + (photo.height || '?')"></p>
                        <div x-show="photo.copyright_flag" x-cloak class="text-[8px] text-red-500" x-text="photo.copyright_reason"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Stock Photos Section (BOTTOM — secondary) --}}
    <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Stock Photos <span class="font-normal text-gray-400">(Pexels, Unsplash, Pixabay)</span></p>
        <div class="flex items-center gap-2 mb-2">
            <input type="text" x-model="stockQuery" @keydown.enter.prevent="searchStock()" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-1.5 text-sm" placeholder="Search stock photos...">
            <button type="button" @click.stop="searchStock()" :disabled="stockSearching || !stockQuery.trim()" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 flex-shrink-0 whitespace-nowrap">Search</button>
        </div>
        <div x-show="stockError" x-cloak class="text-xs text-red-600 mb-2" x-text="stockError"></div>
        <div x-show="Object.keys(stockTimings).length > 0 && stockResults.length > 0" x-cloak class="text-[10px] text-gray-400 mb-1" x-text="stockResults.length + ' photos (' + Object.entries(stockTimings).map(([k,v]) => k + ':' + v + 'ms').join(', ') + ')'"></div>
        <div x-show="stockResults.length > 0" x-cloak class="grid grid-cols-3 gap-2">
            <template x-for="(photo, idx) in stockResults" :key="'s'+idx">
                <div @click="pickPhoto(photo)" class="cursor-pointer rounded-lg overflow-hidden border-2 border-gray-200 hover:border-purple-400 transition-colors">
                    <img :src="photo.url_thumb" :alt="photo.alt || ''" class="w-full h-32 object-cover" loading="lazy">
                    <div class="px-1.5 py-1 bg-white">
                        <p class="text-[9px] text-gray-500" x-text="photo.source + ' — ' + (photo.width || '?') + 'x' + (photo.height || '?')"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
