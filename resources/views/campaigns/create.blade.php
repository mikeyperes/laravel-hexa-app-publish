@extends('layouts.app')
@section('title', isset($editCampaign) ? 'Edit Campaign — ' . $editCampaign->name : 'Create Campaign')
@section('header', isset($editCampaign) && $editCampaign->name !== 'Untitled Campaign' ? $editCampaign->name : 'Create Campaign')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="campaignCreate()">

    {{-- Campaign Name --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <label class="block text-xs text-gray-500 mb-1">Campaign Name</label>
        <input type="text" x-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Daily Tech News for HerForward">
    </div>

    {{-- User --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Select User</h3>
        <div class="max-w-md"
             @hexa-search-selected.window="if ($event.detail.component_id === 'campaign-user') form.user_id = $event.detail.item.id"
             @hexa-search-cleared.window="if ($event.detail.component_id === 'campaign-user') form.user_id = null">
            @php
                $selectedUser = (isset($editCampaign) && $editCampaign->user) ? json_encode(['id' => $editCampaign->user->id, 'name' => $editCampaign->user->name, 'email' => $editCampaign->user->email]) : null;
            @endphp
            <x-hexa-smart-search url="{{ route('api.search.users') }}" name="user_id" placeholder="Search users..." display-field="name" subtitle-field="email" value-field="id" id="campaign-user" show-id
                :selected="$selectedUser" />
        </div>
    </div>

    {{-- Templates & Presets --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Templates & Presets</h3>

        {{-- Campaign Preset --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-xs text-gray-500">Campaign Preset</label>
                <a href="{{ route('campaigns.presets.index') }}" class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
            </div>
            <select x-model="form.campaign_preset_id" @change="loadPreset()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                <option value="">-- No preset --</option>
                @foreach($campaignPresets as $cp)
                    <option value="{{ $cp->id }}">{{ $cp->name }} ({{ ucfirst($cp->source_method) }}{{ $cp->genre ? ' — ' . ucfirst($cp->genre) : '' }})</option>
                @endforeach
            </select>
            <div x-show="presetInfo" x-cloak class="mt-1 text-xs text-gray-500 break-words" x-text="presetInfo"></div>
        </div>

        {{-- AI Template --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-xs text-gray-500">AI Template</label>
                <a href="{{ route('publish.templates.index') }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
            </div>
            <select x-model="form.publish_template_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                <option value="">-- Default --</option>
                @foreach($aiTemplates as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- WordPress Preset --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-xs text-gray-500">WordPress Preset</label>
                <a href="{{ route('publish.presets.index') }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
            </div>
            <select x-model="form.preset_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                <option value="">-- Default --</option>
                @foreach($wpPresets as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- WordPress Site --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">WordPress Site</h3>
        <select x-model="form.publish_site_id" @change="testSiteConnection()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
            <option value="">-- Select site --</option>
            @foreach($sites as $s)
                <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->url }})</option>
            @endforeach
        </select>
        {{-- Connection status --}}
        <div x-show="form.publish_site_id" x-cloak class="mt-3">
            <div class="flex items-center gap-2 rounded-lg px-3 py-2" :class="siteStatus === true ? 'bg-green-50' : (siteStatus === false ? 'bg-red-50' : 'bg-gray-50')">
                <template x-if="siteTesting"><svg class="w-4 h-4 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                <template x-if="!siteTesting && siteStatus === true"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                <template x-if="!siteTesting && siteStatus === false"><svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                <template x-if="!siteTesting && siteStatus === null"><svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></template>
                <span class="text-sm" :class="siteStatus === true ? 'text-green-800' : (siteStatus === false ? 'text-red-800' : 'text-gray-600')" x-text="siteMessage || 'Site selected — click Test to verify connection'"></span>
                <button x-show="!siteTesting && siteStatus !== true" @click="testSiteConnection()" class="text-xs text-blue-600 hover:text-blue-800 ml-2">Test</button>
            </div>
            {{-- Connection log --}}
            <div x-show="siteLog.length > 0" x-cloak class="mt-2 bg-gray-900 rounded-lg border border-gray-700 p-3 max-h-32 overflow-y-auto">
                <template x-for="(entry, idx) in siteLog" :key="idx">
                    <div class="flex items-start gap-2 py-0.5 text-xs font-mono">
                        <span class="text-gray-500 flex-shrink-0" x-text="entry.time"></span>
                        <span :class="{'text-green-400': entry.type === 'success', 'text-red-400': entry.type === 'error', 'text-blue-400': entry.type === 'info'}" x-text="entry.message" class="break-words"></span>
                    </div>
                </template>
            </div>
            {{-- Author --}}
            <div class="mt-2">
                <label class="block text-xs text-gray-500 mb-1">Author</label>
                <div x-show="siteAuthors.length > 0">
                    <select x-model="form.author" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                        <option value="">-- Default author --</option>
                        <template x-for="a in siteAuthors" :key="a.user_login">
                            <option :value="a.user_login" x-text="(a.display_name || a.user_login) + ' (' + a.user_login + ')'"></option>
                        </template>
                    </select>
                </div>
                <div x-show="siteAuthors.length === 0 && form.author">
                    <input type="text" x-model="form.author" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md" readonly>
                    <p class="text-xs text-gray-400 mt-1">Connect to site to load full author list</p>
                </div>
                <div x-show="siteAuthors.length === 0 && !form.author">
                    <p class="text-xs text-gray-400">Connect to WordPress site to load authors</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Scheduling --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Scheduling</h3>

        <div class="flex flex-wrap gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Posts per batch</label>
                <input type="number" x-model="form.articles_per_interval" min="1" max="50" class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Frequency</label>
                <select x-model="form.interval_unit" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">First post at</label>
                <input type="time" x-model="form.run_at_time" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Drip interval (minutes between posts)</label>
                <input type="number" x-model="form.drip_interval_minutes" min="1" max="1440" class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        {{-- Preview schedule --}}
        <div x-show="form.articles_per_interval > 1" x-cloak class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600">
            <p class="font-medium text-gray-700 mb-1">Post Schedule Preview:</p>
            <template x-for="i in Math.min(parseInt(form.articles_per_interval) || 1, 10)" :key="i">
                <p x-text="'Post ' + i + ': ' + calculatePostTime(i - 1)"></p>
            </template>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Timezone</label>
            <select x-model="form.timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                @foreach($timezones as $tz)
                    <option value="{{ $tz }}" {{ $tz === 'America/New_York' ? 'selected' : '' }}>{{ $tz }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Current UTC: <span x-text="new Date().toISOString().substring(11, 16)"></span> UTC</p>
        </div>
    </div>

    {{-- Publishing --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Publishing</h3>

        <div>
            <label class="block text-xs text-gray-500 mb-2">Post Status</label>
            <div class="space-y-2">
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" x-model="form.post_status" value="publish" class="text-blue-600"><span class="text-sm">Publish immediately</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" x-model="form.post_status" value="draft" class="text-blue-600"><span class="text-sm">Spawn as draft (pending approval)</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" x-model="form.post_status" value="pending" class="text-blue-600"><span class="text-sm">Pending review</span></label>
            </div>
        </div>

        {{-- Auto-publish is controlled by Campaign Preset --}}
    </div>

    {{-- AI Instructions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <label class="block text-xs text-gray-500 mb-1">AI Instructions</label>
        <textarea x-model="form.notes" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Custom instructions fed to AI when this campaign generates articles. e.g. Write in first person, focus on women in business, include expert quotes..."></textarea>
    </div>

    {{-- Actions --}}
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

    <div x-show="saveResult" x-cloak class="p-3 rounded-lg text-sm border" :class="saveSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" x-text="saveResult"></div>

    {{-- Links --}}
    <div class="flex gap-4 text-xs text-gray-400">
        <a href="{{ route('campaigns.presets.index') }}" class="hover:text-blue-600">Campaign Presets</a>
        @if(Route::has('publish.schedule.index'))
            <a href="{{ route('publish.schedule.index') }}" class="hover:text-blue-600">Cron Schedule</a>
        @endif
    </div>
</div>

@push('scripts')
<script>
function campaignCreate() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    const presets = @json($campaignPresets);

    @if(isset($editCampaign) && $editCampaign)
        @php
            $editData = [
                'user_id' => $editCampaign->user_id,
                'campaign_preset_id' => $editCampaign->campaign_preset_id ?? '',
                'publish_template_id' => $editCampaign->publish_template_id ?? '',
                'preset_id' => $editCampaign->preset_id ?? '',
                'publish_site_id' => $editCampaign->publish_site_id ?? '',
                'name' => $editCampaign->name ?? '',
                'description' => $editCampaign->description ?? '',
                'topic' => $editCampaign->topic ?? '',
                'keywords' => $editCampaign->keywords ?? [],
                'auto_publish' => $editCampaign->auto_publish ?? false,
                'author' => $editCampaign->author ?? '',
                'post_status' => $editCampaign->post_status ?? 'draft',
                'articles_per_interval' => $editCampaign->articles_per_interval ?? 1,
                'interval_unit' => $editCampaign->interval_unit ?? 'daily',
                'timezone' => $editCampaign->timezone ?? 'America/New_York',
                'run_at_time' => $editCampaign->run_at_time ?? '09:00',
                'drip_interval_minutes' => $editCampaign->drip_interval_minutes ?? 60,
                'notes' => $editCampaign->notes ?? '',
            ];
        @endphp
        const initialForm = {!! json_encode($editData) !!};
    @else
        const saved = localStorage.getItem('campaignCreateState');
        const initialForm = saved ? JSON.parse(saved) : {
            user_id: null, campaign_preset_id: '', publish_template_id: '', preset_id: '', publish_site_id: '',
            name: '', description: '', topic: '', keywords: [], auto_publish: false,
            author: '', post_status: 'draft', articles_per_interval: 1, interval_unit: 'daily',
            timezone: 'America/New_York', run_at_time: '09:00', drip_interval_minutes: 60, notes: ''
        };
    @endif

    return {
        editId: {{ isset($editCampaign) && $editCampaign ? $editCampaign->id : 'null' }},
        saving: false, saveResult: '', saveSuccess: false,
        runningNow: false,
        presetInfo: '',
        form: initialForm,

        // Site connection
        siteTesting: false,
        siteStatus: null,
        siteMessage: '',
        siteLog: [],
        siteAuthors: [],

        _saveTimer: null,

        init() {
            // Auto-save to DB on any form change (debounced 2s)
            this.$watch('form', () => {
                clearTimeout(this._saveTimer);
                this._saveTimer = setTimeout(() => this.autoSave(), 2000);
            }, { deep: true });

            // Auto-select default presets if not already set
            const defaultPreset = presets.find(p => p.is_default);
            if (defaultPreset && !this.form.campaign_preset_id) {
                this.form.campaign_preset_id = String(defaultPreset.id);
                this.loadPreset();
            }

            // If site is selected, restore cached connection or auto-test
            if (this.form.publish_site_id) {
                const savedConn = localStorage.getItem('campaignSiteConnection');
                let restored = false;
                if (savedConn) {
                    try {
                        const conn = JSON.parse(savedConn);
                        if (conn.site_id == this.form.publish_site_id && conn.status === true && conn.authors?.length > 0) {
                            this.siteStatus = conn.status;
                            this.siteMessage = conn.message;
                            this.siteAuthors = conn.authors;
                            restored = true;
                        }
                    } catch(e) {}
                }
                // If no valid cache with authors, run the test to get full connection info
                if (!restored) {
                    this.$nextTick(() => this.testSiteConnection());
                }
            }
        },

        async autoSave() {
            if (!this.editId) return;
            try {
                await fetch('/campaigns/' + this.editId, {
                    method: 'PUT', headers,
                    body: JSON.stringify(this.form)
                });
            } catch(e) { /* silent auto-save */ }
        },

        calculatePostTime(index) {
            if (!this.form.run_at_time) return 'TBD';
            const [h, m] = this.form.run_at_time.split(':').map(Number);
            const totalMinutes = h * 60 + m + (index * (parseInt(this.form.drip_interval_minutes) || 60));
            const hours = Math.floor(totalMinutes / 60) % 24;
            const mins = totalMinutes % 60;
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const h12 = hours % 12 || 12;
            return h12 + ':' + String(mins).padStart(2, '0') + ' ' + ampm + ' ' + (this.form.timezone || 'ET');
        },

        async testSiteConnection() {
            if (!this.form.publish_site_id) return;
            this.siteTesting = true;
            this.siteStatus = null;
            this.siteMessage = 'Connecting...';
            this.siteLog = [];
            this.siteAuthors = [];

            const time = () => new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.siteLog.push({ type: 'info', message: 'Testing WordPress connection...', time: time() });

            try {
                const r = await fetch('/publish/sites/' + this.form.publish_site_id + '/test-write', {
                    method: 'POST', headers
                });
                const d = await r.json();
                this.siteStatus = d.success;
                this.siteMessage = d.message || (d.success ? 'Connected' : 'Connection failed');
                this.siteLog.push({ type: d.success ? 'success' : 'error', message: this.siteMessage, time: time() });
                if (d.authors) {
                    this.siteAuthors = d.authors;
                    this.siteLog.push({ type: 'info', message: d.authors.length + ' authors loaded', time: time() });
                }
                if (d.default_author && !this.form.author) {
                    this.form.author = d.default_author;
                }
            } catch (e) {
                this.siteStatus = false;
                this.siteMessage = 'Network error';
                this.siteLog.push({ type: 'error', message: e.message, time: time() });
            }
            this.siteTesting = false;
            // Cache connection result
            localStorage.setItem('campaignSiteConnection', JSON.stringify({
                site_id: this.form.publish_site_id,
                status: this.siteStatus,
                message: this.siteMessage,
                authors: this.siteAuthors,
            }));
        },

        loadPreset() {
            const p = presets.find(x => x.id == this.form.campaign_preset_id);
            if (p) {
                this.presetInfo = 'Method: ' + p.source_method + (p.genre ? ' | Genre: ' + p.genre : '') + (p.local_preference ? ' | Local: ' + p.local_preference : '') + (p.keywords?.length ? ' | Keywords: ' + p.keywords.join(', ') : '');
                if (p.keywords) this.form.keywords = p.keywords;
                if (p.ai_instructions) this.form.topic = p.ai_instructions;
            } else {
                this.presetInfo = '';
            }
        },

        async saveCampaign() {
            this.saving = true; this.saveResult = '';
            const url = this.editId ? '/campaigns/' + this.editId : '{{ route("campaigns.store") }}';
            const method = this.editId ? 'PUT' : 'POST';
            try {
                const r = await fetch(url, { method, headers, body: JSON.stringify(this.form) });
                const d = await r.json();
                this.saveSuccess = d.success;
                this.saveResult = d.message || (d.success ? 'Saved.' : 'Error.');
                if (d.success && d.campaign?.id) {
                    this.editId = d.campaign.id;
                    history.replaceState(null, '', '{{ route("campaigns.create") }}?id=' + d.campaign.id);
                    localStorage.removeItem('campaignCreateState');
                }
            } catch(e) { this.saveSuccess = false; this.saveResult = 'Error: ' + e.message; }
            this.saving = false;
        },

        async runNow() {
            if (!this.editId) return;
            this.runningNow = true;
            alert('Run Now — coming in Phase 3 (campaign execution engine)');
            this.runningNow = false;
        },
    };
}
</script>
@endpush
@endsection
