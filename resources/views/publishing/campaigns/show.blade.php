@extends('layouts.app')
@section('title', 'Campaign — ' . ($campaign->name ?: 'Untitled'))
@section('header', $campaign->name ?: 'Untitled Campaign')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="campaignDashboard()" x-init="init()">

    {{-- ════════════════════════════════════════════════════
         1. Header strip — name, status, actions
         ════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[280px]">
                <input type="text" x-model="form.name"
                    class="w-full text-lg font-semibold border-0 focus:ring-0 focus:outline-none focus:border-b-2 focus:border-blue-500 px-0 bg-transparent"
                    placeholder="Untitled Campaign">
                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $campaign->campaign_id ?? ('#' . $campaign->id) }}</p>
            </div>

            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                :class="{
                    'bg-green-100 text-green-800': campaignStatus === 'active',
                    'bg-yellow-100 text-yellow-800': campaignStatus === 'paused',
                    'bg-gray-100 text-gray-700': campaignStatus === 'draft',
                    'bg-red-100 text-red-700': campaignStatus === 'archived',
                }" x-text="formatLabel(campaignStatus)"></span>

            <div class="flex items-center gap-2">
                <button @click="runNow()" :disabled="runningNow"
                    class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <span x-show="!runningNow">Run Now</span>
                    <span x-show="runningNow" x-cloak>Running…</span>
                </button>
                <button x-show="campaignStatus === 'active'" x-cloak @click="pauseCampaign()"
                    class="bg-yellow-500 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-yellow-600">Pause</button>
                <button x-show="campaignStatus !== 'active'" x-cloak @click="activateCampaign()"
                    class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-green-700">Activate</button>
                <button @click="duplicateCampaign()" class="border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-50">Duplicate</button>
                <button @click="deleteCampaign()" class="border border-red-200 text-red-600 px-3 py-1.5 rounded-lg text-sm hover:bg-red-50">Delete</button>
            </div>
        </div>

        <div class="mt-2 flex items-center gap-2 text-xs">
            <span x-show="saving" x-cloak class="text-gray-400 inline-flex items-center gap-1">
                <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Saving…
            </span>
            <span x-show="!saving && saveResult" x-cloak :class="saveSuccess ? 'text-green-600' : 'text-red-500'" x-text="saveResult"></span>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         2. Timing & Cadence
         ════════════════════════════════════════════════════ --}}
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="text-base font-semibold text-gray-900">Timing &amp; Cadence</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">User</label>
                <div class="max-w-md"
                    @hexa-search-selected.window="if ($event.detail.component_id === 'campaign-user') form.user_id = $event.detail.item.id"
                    @hexa-search-cleared.window="if ($event.detail.component_id === 'campaign-user') form.user_id = null">
                    @php
                        $currentUser = $campaign->user ? ['id' => $campaign->user->id, 'name' => $campaign->user->name, 'email' => $campaign->user->email] : null;
                    @endphp
                    <x-hexa-smart-search url="{{ route('api.search.users') }}" name="user_id" placeholder="Search users..." display-field="name" subtitle-field="email" value-field="id" id="campaign-user" show-id :selected="$currentUser" />
                </div>
                <p class="text-xs text-gray-400 mt-1">Timezone: {{ $campaign->timezone ?? 'America/New_York' }} — set by selected user.</p>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Frequency</label>
                <select x-model="form.interval_unit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Posts per run</label>
                <input type="number" min="1" max="50" x-model.number="form.articles_per_interval" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Run at time</label>
                <input type="time" x-model="form.run_at_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Drip interval <span class="text-gray-400">(minutes between posts in a run)</span></label>
                <input type="number" min="1" max="1440" x-model.number="form.drip_interval_minutes" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="md:col-span-2 flex flex-wrap gap-6 text-sm text-gray-600 pt-2 border-t border-gray-100">
                <span><span class="text-gray-400">Last run:</span> {{ $campaign->last_run_at ? $campaign->last_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : 'Never' }}</span>
                <span><span class="text-gray-400">Next run:</span> {{ $campaign->next_run_at ? $campaign->next_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : '—' }}</span>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         3. Content Source — Campaign Preset + queries + instructions
         ════════════════════════════════════════════════════ --}}
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-base font-semibold text-gray-900">Content Source</h3>
            <div class="flex items-center gap-4 text-xs">
                <a x-show="campaignPresetEditUrl" x-cloak :href="campaignPresetEditUrl" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                    Edit preset
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
                <a href="{{ route('campaigns.presets.index') }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-gray-700">Manage presets</a>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Campaign preset</label>
            <select x-model="form.campaign_preset_id" @change="onCampaignPresetChange()" class="w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">— No preset —</option>
                @foreach($campaignPresets as $cp)
                    <option value="{{ $cp->id }}">{{ $cp->name }}</option>
                @endforeach
            </select>
        </div>

        @include('app-publish::partials.preset-fields', ['prefix' => 'campaignPreset', 'label' => 'Campaign Preset Fields'])

        <div class="pt-4 border-t border-gray-100 space-y-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Search queries <span class="text-gray-400">(one per line — overrides preset)</span></label>
                <textarea x-model="termsText" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="Leave blank to use preset queries"></textarea>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Campaign instructions</label>
                <textarea x-model="form.ai_instructions" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         4. Article Generation — Article Preset + article-type override
         ════════════════════════════════════════════════════ --}}
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-base font-semibold text-gray-900">Article Generation</h3>
            <div class="flex items-center gap-4 text-xs">
                <a x-show="articlePresetEditUrl" x-cloak :href="articlePresetEditUrl" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                    Edit preset
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
                <a href="{{ route('publish.templates.index') }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-gray-700">Manage presets</a>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Article preset</label>
            <select x-model="form.publish_template_id" @change="onArticlePresetChange()" class="w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">— No preset —</option>
                @foreach($aiTemplates as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Article type override <span class="text-gray-400">(leave blank to use preset)</span></label>
            <select x-model="form.article_type" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">— Use preset's article type —</option>
                @foreach($articleTypes as $at)
                    <option value="{{ $at }}">{{ ucwords(str_replace('-', ' ', $at)) }}</option>
                @endforeach
            </select>
        </div>

        @include('app-publish::partials.preset-fields', ['prefix' => 'template', 'label' => 'Article Preset Fields'])
    </section>

    {{-- ════════════════════════════════════════════════════
         5. Publishing Target
         ════════════════════════════════════════════════════ --}}
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="text-base font-semibold text-gray-900">Publishing Target</h3>

        <div>
            <label class="block text-xs text-gray-500 mb-1">WordPress site</label>
            <select x-model="form.publish_site_id" @change="doTestSite()" class="w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">— Select site —</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->url }})</option>
                @endforeach
            </select>

            <div x-show="form.publish_site_id" x-cloak class="mt-2">
                <div class="flex items-center gap-2 text-xs rounded-lg px-3 py-2" :class="siteConn.status === true ? 'bg-green-50 text-green-800' : (siteConn.status === false ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-600')">
                    <span x-show="siteConn.testing" x-cloak>Testing…</span>
                    <span x-show="!siteConn.testing" x-cloak x-text="siteConn.message || 'Click Test to verify site access.'"></span>
                    <button @click="doTestSite()" class="ml-auto text-blue-600 hover:text-blue-800">Test</button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Author</label>
                <input type="text" x-model="form.author" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Delivery mode</label>
                <select x-model="form.delivery_mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($deliveryModes as $m)
                        <option value="{{ $m }}">{{ ucwords(str_replace('-', ' ', $m)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Post status</label>
                <select x-model="form.post_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="publish">Publish</option>
                </select>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         6. Article History
         ════════════════════════════════════════════════════ --}}
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Recent Articles</h3>
            <span class="text-sm text-gray-500">{{ min($campaign->articles->count(), 8) }} of {{ $campaign->articles->count() }}</span>
        </div>
        <div class="mt-4 space-y-3">
            @forelse($campaign->articles->take(8) as $article)
                <div class="rounded-xl border border-gray-200 px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $article->title ?: 'Untitled Article' }}</p>
                            <div class="mt-1 flex flex-wrap gap-3 text-xs text-gray-500">
                                <span>{{ $article->article_id }}</span>
                                <span>{{ $article->status }}</span>
                                <span>{{ $article->created_at?->timezone(config('app.timezone'))->format('M j, g:i A') }}</span>
                                <span>{{ $article->ai_engine_used ?: '—' }}</span>
                                <span>{{ $article->wp_status ?: '—' }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-2 text-xs">
                            <a href="{{ route('publish.articles.show', $article->id) }}" class="text-blue-600 hover:text-blue-800">Open</a>
                            @if($article->wp_post_url)
                                <a href="{{ $article->wp_post_url }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-gray-800">WP Draft</a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-400">No articles produced yet.</div>
            @endforelse
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         7. Activity / Runs
         ════════════════════════════════════════════════════ --}}
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Campaign Activity</h3>
            <span class="text-sm text-gray-500">Latest 50 logs</span>
        </div>
        <div class="mt-4 space-y-2 max-h-[28rem] overflow-auto">
            @forelse($runLogs as $log)
                <div class="rounded-lg border border-gray-200 px-4 py-2">
                    <div class="flex items-center justify-between gap-3 text-xs text-gray-400">
                        <span>{{ \Illuminate\Support\Carbon::parse($log->created_at)->timezone(config('app.timezone'))->format('M j, g:i:s A') }}</span>
                        <span>{{ $log->action }}</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-700">{{ $log->message }}</p>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-200 px-4 py-4 text-sm text-gray-400">No campaign activity logged.</div>
            @endforelse
        </div>
    </section>

</div>
@endsection

@push('scripts')
@include('app-publish::partials.preset-fields-mixin')
@include('app-publish::partials.site-connection-mixin')
<script>
function campaignDashboard() {
    const initialForm         = @json($campaignForm);
    const campaignPresetItems = @json($campaignPresetItems);
    const articlePresetItems  = @json($aiTemplateItems);
    const campaignPresetSchema = @json($campaignPresetSchema ?? []);
    const articlePresetSchema  = @json($articlePresetSchema ?? []);
    const campaignPresetValues = @json($campaignPresetValues ?? []);
    const articlePresetValues  = @json($articlePresetValues ?? []);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

    return {
        ...presetFieldsMixin('campaignPreset'),
        ...presetFieldsMixin('template'),
        ...presetFieldsMethods,
        ...siteConnectionMixin(),

        editId: {{ (int) $campaign->id }},
        campaignStatus: @json($campaign->status ?? 'draft'),
        form: {
            ...initialForm,
            post_status: initialForm.post_status || @json($campaign->post_status ?? 'draft'),
        },
        termsText: Array.isArray(initialForm.keywords) ? initialForm.keywords.join('\n') : '',
        saving: false,
        saveResult: '',
        saveSuccess: true,
        runningNow: false,
        _saveTimer: null,
        _skipCount: 0,

        get campaignPresetEditUrl() {
            return this.form.campaign_preset_id
                ? @json(route('campaigns.presets.index')) + '?id=' + this.form.campaign_preset_id
                : null;
        },
        get articlePresetEditUrl() {
            return this.form.publish_template_id
                ? '/publish/article-presets/' + this.form.publish_template_id + '/edit'
                : null;
        },

        init() {
            this._loadCampaignPreset();
            this._loadArticlePreset();

            this.$watch('termsText', v => {
                this.form.keywords = (v || '').split('\n').map(s => s.trim()).filter(Boolean);
            });

            this.$watch('form', () => {
                this._skipCount++;
                if (this._skipCount <= 2) return;
                clearTimeout(this._saveTimer);
                this._saveTimer = setTimeout(() => this.autoSave(), 600);
            }, { deep: true });

            if (this.form.publish_site_id) {
                this.restoreSiteConnection(this.form.publish_site_id, 'campaignSiteConnection_' + this.editId);
            }
        },

        formatLabel(value) {
            return (value || '').replace(/[-_]/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
        },

        _loadCampaignPreset() {
            const p = campaignPresetItems.find(x => String(x.id) === String(this.form.campaign_preset_id));
            const values = p ? p.form_values : campaignPresetValues;
            _loadPresetFields(this, 'campaignPreset', values, campaignPresetSchema);
        },
        _loadArticlePreset() {
            const p = articlePresetItems.find(x => String(x.id) === String(this.form.publish_template_id));
            const values = p ? p.form_values : articlePresetValues;
            _loadPresetFields(this, 'template', values, articlePresetSchema);
        },

        onCampaignPresetChange() {
            this._loadCampaignPreset();
            const p = campaignPresetItems.find(x => String(x.id) === String(this.form.campaign_preset_id));
            if (!p) return;
            const v = p.form_values || {};
            if (!this.termsText.trim() && Array.isArray(v.search_queries) && v.search_queries.length) {
                this.termsText = v.search_queries.join('\n');
            }
            if (!this.form.ai_instructions && v.campaign_instructions) this.form.ai_instructions = v.campaign_instructions;
            if (v.posts_per_run && (this.form.articles_per_interval || 1) <= 1) this.form.articles_per_interval = v.posts_per_run;
            if (v.frequency && this.form.interval_unit === 'daily') this.form.interval_unit = v.frequency;
            if (v.run_at_time && this.form.run_at_time === '09:00') this.form.run_at_time = v.run_at_time;
            if (v.drip_minutes && (this.form.drip_interval_minutes || 60) === 60) this.form.drip_interval_minutes = v.drip_minutes;
        },
        onArticlePresetChange() { this._loadArticlePreset(); },

        async autoSave() {
            if (!this.editId) return;
            this.saving = true;
            try {
                const r = await fetch('/campaigns/' + this.editId, { method: 'PUT', headers, body: JSON.stringify(this.form) });
                const d = await r.json();
                this.saveSuccess = !!d.success;
                this.saveResult = d.success ? 'Saved ' + new Date().toLocaleTimeString() : (d.message || 'Save failed');
            } catch (e) {
                this.saveSuccess = false;
                this.saveResult = 'Network error';
            }
            this.saving = false;
        },

        async doTestSite() {
            if (!this.form.publish_site_id) return;
            await this.testSiteConnection(this.form.publish_site_id, csrf, {
                cacheKey: 'campaignSiteConnection_' + this.editId,
                onSuccess: (d) => { if (d.default_author && !this.form.author) this.form.author = d.default_author; },
            });
        },

        async runNow() {
            this.runningNow = true;
            try { await fetch('/campaigns/' + this.editId + '/run-now', { method: 'POST', headers }); } catch (e) {}
            this.runningNow = false;
            window.location.reload();
        },
        async activateCampaign() {
            try { await fetch('/campaigns/' + this.editId + '/activate', { method: 'POST', headers }); } catch (e) {}
            window.location.reload();
        },
        async pauseCampaign() {
            try { await fetch('/campaigns/' + this.editId + '/pause', { method: 'POST', headers }); } catch (e) {}
            window.location.reload();
        },
        async duplicateCampaign() {
            try {
                const r = await fetch('/campaigns/' + this.editId + '/duplicate', { method: 'POST', headers });
                const d = await r.json();
                if (d.success && d.campaign?.id) window.location.href = '/campaigns/' + d.campaign.id;
            } catch (e) {}
        },
        async deleteCampaign() {
            if (!confirm('Delete this campaign? This cannot be undone.')) return;
            try {
                await fetch('/campaigns/' + this.editId, { method: 'DELETE', headers });
                window.location.href = @json(route('campaigns.index'));
            } catch (e) {}
        },
    };
}
</script>
@endpush
