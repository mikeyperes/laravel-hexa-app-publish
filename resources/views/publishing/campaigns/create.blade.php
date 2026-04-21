@extends('layouts.app')
@section('title', isset($editCampaign) ? 'Edit Campaign — ' . $editCampaign->name : 'Create Campaign')
@section('header', isset($editCampaign) && $editCampaign->name !== 'Untitled Campaign' ? $editCampaign->name : 'Create Campaign')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="campaignCreate()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <label class="block text-xs text-gray-500 mb-1">Campaign Name</label>
        <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Her Forward Celebrity Breaking">
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Select User</h3>
        <div class="max-w-md"
             @hexa-search-selected.window="if ($event.detail.component_id === 'campaign-user') { form.user_id = $event.detail.item.id; loadUserDefaults($event.detail.item.id); }"
             @hexa-search-cleared.window="if ($event.detail.component_id === 'campaign-user') form.user_id = null">
            @php
                $selectedUser = (isset($editCampaign) && $editCampaign->user) ? ['id' => $editCampaign->user->id, 'name' => $editCampaign->user->name, 'email' => $editCampaign->user->email] : null;
            @endphp
            <x-hexa-smart-search url="{{ route('api.search.users') }}" name="user_id" placeholder="Search users..." display-field="name" subtitle-field="email" value-field="id" id="campaign-user" show-id
                :selected="$selectedUser" />
        </div>
        <p class="text-xs text-gray-400 mt-2">Campaign runs use the selected user’s timezone automatically.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Campaign Preset</h3>
            <a href="{{ route('campaigns.presets.index') }}" class="text-xs text-blue-600 hover:text-blue-800">Manage Presets</a>
        </div>
        <select x-model="form.campaign_preset_id" @change="applyPresetDefaults()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
            <option value="">-- No preset --</option>
            @foreach($campaignPresets as $cp)
                <option value="{{ $cp->id }}">{{ $cp->name }}</option>
            @endforeach
        </select>

        <div x-show="selectedPreset()" x-cloak class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900 space-y-2">
            <div class="flex flex-wrap gap-4">
                <p><span class="text-blue-600">Queries:</span> <span x-text="(selectedPreset()?.search_queries || selectedPreset()?.keywords || []).length"></span></p>
                <p><span class="text-blue-600">Posts Per Run:</span> <span x-text="selectedPreset()?.posts_per_run || 1"></span></p>
                <p><span class="text-blue-600">Frequency:</span> <span x-text="formatLabel(selectedPreset()?.frequency || 'daily')"></span></p>
            </div>
            <p x-show="selectedPreset()?.run_at_time"><span class="text-blue-600">Run At:</span> <span x-text="selectedPreset()?.run_at_time"></span></p>
            <p x-show="selectedPreset()?.campaign_instructions || selectedPreset()?.ai_instructions"><span class="text-blue-600">Instructions:</span> <span x-text="selectedPreset()?.campaign_instructions || selectedPreset()?.ai_instructions"></span></p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Article Preset</h3>
            <a href="{{ route('publish.templates.index') }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800">Manage Presets</a>
        </div>
        <select x-model="form.publish_template_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
            <option value="">-- Default Article Preset --</option>
            @foreach($aiTemplates as $template)
                <option value="{{ $template->id }}">{{ $template->name }}</option>
            @endforeach
        </select>

        <div x-show="selectedTemplate()" x-cloak class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-xs text-gray-700 space-y-1">
            <p x-show="selectedTemplate()?.article_type"><span class="text-gray-500">Type:</span> <span x-text="formatLabel(selectedTemplate()?.article_type)"></span></p>
            <p><span class="text-gray-500">Words:</span> <span x-text="(selectedTemplate()?.word_count_min || '—') + ' - ' + (selectedTemplate()?.word_count_max || '—')"></span></p>
            <p><span class="text-gray-500">Search:</span> <span x-text="(selectedTemplate()?.searching_agent || defaultSearchModel || '—') + ' -> ' + (selectedTemplate()?.online_search_model_fallback || defaultSearchFallback || defaultSearchModel || '—')"></span></p>
            <p><span class="text-gray-500">Scrape:</span> <span x-text="(selectedTemplate()?.scraping_agent || defaultSearchModel || '—') + ' -> ' + (selectedTemplate()?.scrape_ai_model_fallback || defaultSearchFallback || defaultSearchModel || '—')"></span></p>
            <p><span class="text-gray-500">Spin:</span> <span x-text="(selectedTemplate()?.spinning_agent || selectedTemplate()?.ai_engine || defaultSpinModel || '—') + ' -> ' + (selectedTemplate()?.spin_model_fallback || defaultSpinFallback || defaultSpinModel || '—')"></span></p>
            <p><span class="text-gray-500">Photos:</span> Featured image is always Google searched. Inline photos are Google searched or stock.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Discovery And Angle</h3>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Search Queries <span class="text-gray-400">(one per line)</span></label>
            <textarea x-model="termsText" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="celebrity breaking news&#10;celebrity legal filing&#10;celebrity business launch"></textarea>
            <p class="text-xs text-gray-400 mt-1">Leave blank to use the selected campaign preset queries.</p>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Campaign Instructions</label>
            <textarea x-model="form.ai_instructions" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Prioritize real news pegs, not gossip filler."></textarea>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">WordPress Site</h3>
        <select x-model="form.publish_site_id" @change="doTestSite()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
            <option value="">-- Select site --</option>
            @foreach($sites as $site)
                <option value="{{ $site->id }}">{{ $site->name }} ({{ $site->url }})</option>
            @endforeach
        </select>

        <div x-show="form.publish_site_id" x-cloak class="mt-3">
            <div class="flex items-center gap-2 rounded-lg px-3 py-2" :class="siteConn.status === true ? 'bg-green-50' : (siteConn.status === false ? 'bg-red-50' : 'bg-gray-50')">
                <template x-if="siteConn.testing"><svg class="w-4 h-4 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                <template x-if="!siteConn.testing && siteConn.status === true"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                <template x-if="!siteConn.testing && siteConn.status === false"><svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                <template x-if="!siteConn.testing && siteConn.status === null"><svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                <span class="text-sm" :class="siteConn.status === true ? 'text-green-800' : (siteConn.status === false ? 'text-red-800' : 'text-gray-600')" x-text="siteConn.message || 'Site selected — click Test to verify connection'"></span>
                <button x-show="!siteConn.testing" @click="doTestSite()" class="text-xs text-blue-600 hover:text-blue-800 ml-2" x-text="siteConn.status === true ? 'Retest' : 'Test'"></button>
            </div>
        </div>

        <div x-show="form.publish_site_id" class="mt-4">
            <label class="block text-xs text-gray-500 mb-1">Author</label>
            <div x-show="siteConn.authors.length > 0">
                <select x-model="form.author" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                    <option value="">-- Default author --</option>
                    <template x-for="author in siteConn.authors" :key="author.user_login">
                        <option :value="author.user_login" x-text="(author.display_name || author.user_login) + ' (' + author.user_login + ')'"></option>
                    </template>
                </select>
            </div>
            <div x-show="siteConn.authors.length === 0">
                <input type="text" x-model="form.author" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md" placeholder="WordPress author username">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Schedule And Default Run Mode</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Posts Per Run</label>
                <input type="number" x-model="form.articles_per_interval" min="1" max="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
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
                <label class="block text-xs text-gray-500 mb-1">Run At</label>
                <input type="time" x-model="form.run_at_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Drip Minutes</label>
                <input type="number" x-model="form.drip_interval_minutes" min="1" max="1440" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div class="max-w-md">
            <label class="block text-xs text-gray-500 mb-1">Default Run Mode</label>
            <select x-model="form.delivery_mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach($deliveryModes as $mode)
                    <option value="{{ $mode }}">{{ ucwords(str_replace('-', ' ', $mode)) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex gap-3">
        <button @click="saveCampaign()" :disabled="saving" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
            <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="saving ? 'Saving...' : (editId ? 'Update Campaign' : 'Create Campaign')"></span>
        </button>
        <button x-show="editId" @click="runNow()" :disabled="runningNow" class="bg-green-600 text-white px-4 py-2.5 rounded-lg text-sm hover:bg-green-700 disabled:opacity-50 inline-flex items-center gap-2">
            <svg x-show="runningNow" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Run Now
        </button>
        <a href="{{ route('campaigns.index') }}" class="text-gray-500 hover:text-gray-700 px-4 py-2.5 text-sm">Cancel</a>
    </div>

    <div x-show="saveResult" x-cloak class="p-3 rounded-lg text-sm border" :class="saveSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
        <span x-html="saveResult"></span>
    </div>

    <div class="flex gap-4 text-xs text-gray-400">
        <a href="{{ route('campaigns.presets.index') }}" class="hover:text-blue-600">Campaign Presets</a>
        <a href="{{ route('publish.templates.index') }}" class="hover:text-blue-600">Article Presets</a>
        @if(Route::has('publish.schedule.index'))
            <a href="{{ route('publish.schedule.index') }}" class="hover:text-blue-600">Cron Schedule</a>
        @endif
    </div>
</div>

@push('scripts')
@include('app-publish::partials.site-connection-mixin')
<script>
function campaignCreate() {
    const defaultSearchModel = @json(app(\hexa_app_publish\Support\AiModelCatalog::class)->defaultSearchModel());
    const defaultSearchFallback = @json(app(\hexa_app_publish\Support\AiModelCatalog::class)->defaultSearchFallbackModel(app(\hexa_app_publish\Support\AiModelCatalog::class)->defaultSearchModel()));
    const defaultSpinModel = @json(app(\hexa_app_publish\Support\AiModelCatalog::class)->defaultSpinModel());
    const defaultSpinFallback = @json(app(\hexa_app_publish\Support\AiModelCatalog::class)->defaultSpinFallbackModel(app(\hexa_app_publish\Support\AiModelCatalog::class)->defaultSpinModel()));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    const presets = @json($campaignPresets);
    const aiTemplates = @json($aiTemplates);

    @if(isset($editCampaign) && $editCampaign)
        @php
            $editData = [
                'user_id' => $editCampaign->user_id,
                'campaign_preset_id' => $editCampaign->campaign_preset_id ?? '',
                'publish_template_id' => $editCampaign->publish_template_id ?? '',
                'publish_site_id' => $editCampaign->publish_site_id ?? '',
                'name' => $editCampaign->name ?? '',
                'description' => $editCampaign->description ?? '',
                'ai_instructions' => $editCampaign->ai_instructions ?? $editCampaign->notes ?? '',
                'keywords' => $editCampaign->keywords ?? [],
                'delivery_mode' => $editCampaign->delivery_mode ?? 'draft-local',
                'author' => $editCampaign->author ?? '',
                'articles_per_interval' => $editCampaign->articles_per_interval ?? 1,
                'interval_unit' => $editCampaign->interval_unit ?? 'daily',
                'run_at_time' => $editCampaign->run_at_time ?? '09:00',
                'drip_interval_minutes' => $editCampaign->drip_interval_minutes ?? 60,
            ];
            $editData['delivery_mode'] = app(\hexa_app_publish\Publishing\Campaigns\Services\CampaignModeResolver::class)->normalizeDeliveryMode($editData['delivery_mode']);
        @endphp
        const initialForm = {!! json_encode($editData) !!};
    @else
        const initialForm = {
            user_id: null,
            campaign_preset_id: '',
            publish_template_id: '',
            publish_site_id: '',
            name: '',
            description: '',
            ai_instructions: '',
            keywords: [],
            delivery_mode: 'draft-wordpress',
            author: '',
            articles_per_interval: 1,
            interval_unit: 'daily',
            run_at_time: '09:00',
            drip_interval_minutes: 60,
        };
    @endif

    return {
        ...siteConnectionMixin(),
        editId: {{ isset($editCampaign) && $editCampaign ? $editCampaign->id : 'null' }},
        saving: false,
        saveResult: '',
        saveSuccess: false,
        runningNow: false,
        loadingPresets: false,
        defaultSearchModel,
        defaultSearchFallback,
        defaultSpinModel,
        defaultSpinFallback,
        form: initialForm,
        termsText: (initialForm.keywords || []).join('\n'),
        _saveTimer: null,
        _skipCount: 0,

        init() {
            this.$watch('termsText', value => {
                this.form.keywords = this.parseTerms(value);
            });

            this.$watch('form', () => {
                this._skipCount++;
                if (this._skipCount <= 2) return;
                clearTimeout(this._saveTimer);
                this._saveTimer = setTimeout(() => this.autoSave(), 500);
            }, { deep: true });

            const defaultPreset = presets.find(p => p.is_default);
            if (defaultPreset && !this.form.campaign_preset_id) {
                this.form.campaign_preset_id = String(defaultPreset.id);
                this.applyPresetDefaults();
            }

            if (this.form.publish_site_id) {
                const cacheKey = 'campaignSiteConnection_' + (this.editId || 'new');
                const restored = this.restoreSiteConnection(this.form.publish_site_id, cacheKey);
                if (!restored) {
                    this.siteConn.message = 'Click "Test" to verify site access.';
                }
            }
        },

        parseTerms(value) {
            return (value || '')
                .split('\n')
                .map(item => item.trim())
                .filter(Boolean);
        },

        formatLabel(value) {
            return (value || '').replace(/[-_]/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
        },

        selectedPreset() {
            return presets.find(item => String(item.id) === String(this.form.campaign_preset_id)) || null;
        },

        selectedTemplate() {
            return aiTemplates.find(item => String(item.id) === String(this.form.publish_template_id)) || null;
        },

        applyPresetDefaults() {
            const preset = this.selectedPreset();
            if (!preset) return;

            const presetQueries = preset.search_queries || preset.keywords || [];
            if (!this.termsText.trim() && Array.isArray(presetQueries) && presetQueries.length > 0) {
                this.termsText = presetQueries.join('\n');
            }

            if (!this.form.ai_instructions && (preset.campaign_instructions || preset.ai_instructions)) {
                this.form.ai_instructions = preset.campaign_instructions || preset.ai_instructions;
            }

            if (!this.form.articles_per_interval || this.form.articles_per_interval === 1) {
                this.form.articles_per_interval = preset.posts_per_run || this.form.articles_per_interval || 1;
            }

            if (!this.form.interval_unit || this.form.interval_unit === 'daily') {
                this.form.interval_unit = preset.frequency || this.form.interval_unit || 'daily';
            }

            if ((!this.form.run_at_time || this.form.run_at_time === '09:00') && preset.run_at_time) {
                this.form.run_at_time = preset.run_at_time;
            }

            if ((!this.form.drip_interval_minutes || this.form.drip_interval_minutes === 60) && preset.drip_minutes) {
                this.form.drip_interval_minutes = preset.drip_minutes;
            }
        },

        async autoSave() {
            if (!this.editId) return;
            try {
                await fetch('/campaigns/' + this.editId, {
                    method: 'PUT',
                    headers,
                    body: JSON.stringify(this.form),
                });
            } catch (e) {}
        },

        async loadUserDefaults(userId) {
            if (!userId) return;
            this.loadingPresets = true;
            try {
                const campaignPresetsResp = await fetch('{{ route("campaigns.presets.index") }}?user_id=' + userId, { headers: { Accept: 'application/json' } });
                const campaignPresetsData = await campaignPresetsResp.json();
                const userCampaignPresets = campaignPresetsData.data?.data || campaignPresetsData.data || [];
                const defaultCampaignPreset = userCampaignPresets.find(item => item.is_default);
                if (defaultCampaignPreset && !this.form.campaign_preset_id) {
                    this.form.campaign_preset_id = String(defaultCampaignPreset.id);
                    this.applyPresetDefaults();
                }

                const templatesResp = await fetch('{{ route("publish.templates.index") }}?user_id=' + userId + '&format=json', { headers: { Accept: 'application/json' } });
                const templatesData = await templatesResp.json();
                const userTemplates = templatesData.data || templatesData || [];
                const defaultTemplate = userTemplates.find(item => item.is_default);
                if (defaultTemplate && !this.form.publish_template_id) {
                    this.form.publish_template_id = String(defaultTemplate.id);
                }
            } catch (e) {}
            this.loadingPresets = false;
        },

        async doTestSite() {
            await this.testSiteConnection(this.form.publish_site_id, csrf, {
                cacheKey: 'campaignSiteConnection_' + (this.editId || 'new'),
                onSuccess(d) {
                    if (d.default_author && !this.form.author) this.form.author = d.default_author;
                },
            });
        },

        async saveCampaign() {
            this.form.keywords = this.parseTerms(this.termsText);
            this.saving = true;
            this.saveResult = '';
            const url = this.editId ? '/campaigns/' + this.editId : '{{ route("campaigns.store") }}';
            const method = this.editId ? 'PUT' : 'POST';
            try {
                const response = await fetch(url, { method, headers, body: JSON.stringify(this.form) });
                const data = await response.json();
                this.saveSuccess = data.success;
                this.saveResult = data.message || (data.success ? 'Saved.' : 'Error.');
                if (data.success && data.campaign?.id) {
                    this.editId = data.campaign.id;
                    history.replaceState(null, '', '{{ route("campaigns.create") }}?id=' + data.campaign.id);
                }
            } catch (e) {
                this.saveSuccess = false;
                this.saveResult = 'Error: ' + e.message;
            }
            this.saving = false;
        },

        async runNow() {
            if (!this.editId) return;
            this.runningNow = true;
            this.saveResult = '';
            try {
                const response = await fetch('/campaigns/' + this.editId + '/run-now', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ mode: this.form.delivery_mode || 'draft-wordpress' }),
                });
                const data = await response.json();
                this.saveSuccess = data.success;
                this.saveResult = data.message || (data.success ? 'Campaign executed.' : 'Run failed.');
                if (data.article_url) {
                    this.saveResult += ' <a href="' + data.article_url + '" target="_blank" class="underline">View Article</a>';
                }
            } catch (e) {
                this.saveSuccess = false;
                this.saveResult = 'Error: ' + e.message;
            }
            this.runningNow = false;
        },
    };
}
</script>
@endpush
@endsection
