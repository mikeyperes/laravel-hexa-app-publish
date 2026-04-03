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
             @hexa-search-selected.window="if ($event.detail.component_id === 'campaign-user') { form.user_id = $event.detail.item.id; loadUserPresets($event.detail.item.id); }"
             @hexa-search-cleared.window="if ($event.detail.component_id === 'campaign-user') form.user_id = null">
            @php
                $selectedUser = (isset($editCampaign) && $editCampaign->user) ? ['id' => $editCampaign->user->id, 'name' => $editCampaign->user->name, 'email' => $editCampaign->user->email] : null;
            @endphp
            <x-hexa-smart-search url="{{ route('api.search.users') }}" name="user_id" placeholder="Search users..." display-field="name" subtitle-field="email" value-field="id" id="campaign-user" show-id
                :selected="$selectedUser" />
        </div>
    </div>

    {{-- ═══ Campaign Preset ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Campaign Preset</h3>
            <div class="flex items-center gap-3">
                <button x-show="form.campaign_preset_id" @click="restoreCampaignPreset()" class="text-xs text-gray-400 hover:text-blue-600">Restore Defaults</button>
                <a href="{{ route('campaigns.presets.index') }}" class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
            </div>
        </div>
        <select x-model="form.campaign_preset_id" @change="loadPreset()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
            <option value="">-- No preset --</option>
            @foreach($campaignPresets as $cp)
                <option value="{{ $cp->id }}">{{ $cp->name }} ({{ ucfirst($cp->source_method) }}{{ $cp->genre ? ' — ' . ucfirst($cp->genre) : '' }})</option>
            @endforeach
        </select>
        <div x-show="form.campaign_preset_id" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
            <div><label class="block text-xs text-gray-400 mb-1">Source Method</label><select x-model="form.source_method" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm"><option value="trending">Trending</option><option value="genre">Genre</option><option value="local">Local</option></select></div>
            <div><label class="block text-xs text-gray-400 mb-1">Genre</label><input type="text" x-model="form.genre" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="e.g. technology"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Local Preference</label><input type="text" x-model="form.local_preference" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="City or state"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Keywords (comma sep)</label><input type="text" x-model="form.keywords_text" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="AI, tech, business"></div>
            <div class="md:col-span-2"><label class="block text-xs text-gray-400 mb-1">Trending Categories (comma sep)</label><input type="text" x-model="form.trending_categories_text" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="technology, business, education"></div>
            <div class="md:col-span-2"><label class="block text-xs text-gray-400 mb-1">AI Instructions</label><textarea x-model="form.cp_ai_instructions" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Custom AI instructions for this campaign..."></textarea></div>
            <div class="md:col-span-2"><label class="block text-xs text-gray-400 mb-1">Auto-publish</label>
                <div class="flex items-center gap-2"><button @click="form.auto_publish = !form.auto_publish" type="button" class="relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200" :class="form.auto_publish ? 'bg-green-500' : 'bg-gray-300'" role="switch"><span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200" :class="form.auto_publish ? 'translate-x-5' : 'translate-x-0'"></span></button><span class="text-xs text-gray-500" x-text="form.auto_publish ? 'System handles everything' : 'Manual review'"></span></div>
            </div>
        </div>
    </div>

    {{-- ═══ AI Template ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 inline-flex items-center gap-2">AI Template <svg x-show="loadingPresets" x-cloak class="w-3 h-3 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></h3>
            <div class="flex items-center gap-3">
                <button x-show="form.publish_template_id" @click="restoreAiTemplate()" class="text-xs text-gray-400 hover:text-blue-600">Restore Defaults</button>
                <a href="{{ route('publish.templates.index') }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
            </div>
        </div>
        <select x-model="form.publish_template_id" @change="loadAiTemplate()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
            <option value="">-- Default --</option>
            @foreach($aiTemplates as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
            @endforeach
        </select>
        <div x-show="form.publish_template_id" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
            <div><label class="block text-xs text-gray-400 mb-1">AI Engine</label><input type="text" x-model="form.ai_engine" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="claude-sonnet-4-6"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Article Type</label><input type="text" x-model="form.override_article_type" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="editorial, listicle, press-release"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Tone</label><input type="text" x-model="form.override_tone" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Professional, Conversational"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Word Count Range</label><div class="flex gap-2"><input type="number" x-model="form.override_word_min" class="w-24 border border-gray-200 rounded-lg px-2 py-1.5 text-sm" placeholder="Min"><span class="text-gray-400">—</span><input type="number" x-model="form.override_word_max" class="w-24 border border-gray-200 rounded-lg px-2 py-1.5 text-sm" placeholder="Max"></div></div>
            <div><label class="block text-xs text-gray-400 mb-1">Photos per Article</label><input type="number" x-model="form.override_photos_per_article" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" min="0" max="20" placeholder="3"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Photo Sources (comma sep)</label><input type="text" x-model="form.override_photo_sources_text" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="unsplash, pexels, pixabay"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Max Links</label><input type="number" x-model="form.override_max_links" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" min="0" max="50" placeholder="5"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Description</label><input type="text" x-model="form.override_ai_description" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Template description"></div>
            <div class="md:col-span-2"><label class="block text-xs text-gray-400 mb-1">AI Prompt</label><textarea x-model="form.override_ai_prompt" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Custom AI prompt override..."></textarea></div>
            <div><label class="block text-xs text-gray-400 mb-1">Structure</label><input type="text" x-model="form.override_structure" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="intro, body, conclusion"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Rules</label><input type="text" x-model="form.override_rules" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Custom rules"></div>
        </div>
    </div>

    {{-- ═══ WordPress Preset ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 inline-flex items-center gap-2">WordPress Preset <svg x-show="loadingPresets" x-cloak class="w-3 h-3 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></h3>
            <div class="flex items-center gap-3">
                <button x-show="form.preset_id" @click="restoreWpPreset()" class="text-xs text-gray-400 hover:text-blue-600">Restore Defaults</button>
                <a href="{{ route('publish.presets.index') }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
            </div>
        </div>
        <select x-model="form.preset_id" @change="loadWpPreset()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
            <option value="">-- Default --</option>
            @foreach($wpPresets as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <div x-show="form.preset_id" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
            <div><label class="block text-xs text-gray-400 mb-1">Publish Action</label><select x-model="form.delivery_mode" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm"><option value="auto-publish">Publish Immediately</option><option value="draft-local">Save as Local Draft</option><option value="draft-wordpress">Save as WordPress Draft</option><option value="review">Schedule for Later</option></select></div>
            <div><label class="block text-xs text-gray-400 mb-1">Follow Links</label><select x-model="form.override_follow_links" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm"><option value="">Default</option><option value="follow">Follow</option><option value="nofollow">Nofollow</option></select></div>
            <div><label class="block text-xs text-gray-400 mb-1">Article Format</label><input type="text" x-model="form.override_wp_article_format" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Editorial"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Tone</label><input type="text" x-model="form.override_wp_tone" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Authoritative"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Image Preference</label><input type="text" x-model="form.override_image_preference" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Abstract/Conceptual"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Image Layout</label><input type="text" x-model="form.override_image_layout" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="5 Photos Randomly Placed"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Category Count</label><input type="number" x-model="form.override_category_count" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" min="0" max="30" placeholder="3"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Tag Count</label><input type="number" x-model="form.override_tag_count" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" min="0" max="30" placeholder="5"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Default Site</label><select x-model="form.override_default_site_id" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm"><option value="">From campaign</option>@foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
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
                <button x-show="!siteTesting" @click="testSiteConnection()" class="text-xs text-blue-600 hover:text-blue-800 ml-2" x-text="siteStatus === true ? 'Retest' : 'Test'"></button>
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
        </div>

        {{-- Author (always visible when site selected) --}}
        <div x-show="form.publish_site_id" class="mt-4">
            <label class="block text-xs text-gray-500 mb-1">Author</label>
            <div x-show="siteAuthors.length > 0">
                <select x-model="form.author" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md">
                    <option value="">-- Default author --</option>
                    <template x-for="a in siteAuthors" :key="a.user_login">
                        <option :value="a.user_login" x-text="(a.display_name || a.user_login) + ' (' + a.user_login + ')'"></option>
                    </template>
                </select>
            </div>
            <div x-show="siteAuthors.length === 0">
                <div class="flex items-center gap-2">
                    <input type="text" x-model="form.author" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm max-w-md" placeholder="WordPress author username">
                    <span x-show="siteTesting" class="text-xs text-blue-500 flex items-center gap-1">
                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading authors...
                    </span>
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
    const aiTemplates = @json($aiTemplates);
    const wpPresets = @json($wpPresets);

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
                'delivery_mode' => $editCampaign->delivery_mode ?? 'draft-local',
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
        const initialForm = {
            user_id: null, campaign_preset_id: '', publish_template_id: '', preset_id: '', publish_site_id: '',
            name: '', description: '', topic: '', keywords: [], auto_publish: false,
            author: '', post_status: 'draft', delivery_mode: 'draft-local',
            source_method: 'trending', genre: '', local_preference: '', keywords_text: '', trending_categories_text: '', cp_ai_instructions: '', auto_publish: false,
            ai_engine: '', override_article_type: '', override_tone: '', override_word_min: '', override_word_max: '',
            override_photos_per_article: '', override_photo_sources_text: '', override_max_links: '', override_ai_description: '', override_ai_prompt: '', override_structure: '', override_rules: '',
            override_follow_links: '', override_wp_article_format: '', override_wp_tone: '', override_image_preference: '', override_image_layout: '', override_category_count: '', override_tag_count: '', override_default_site_id: '',
            articles_per_interval: 1, interval_unit: 'daily',
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
        loadingPresets: false,

        _saveTimer: null,

        _skipCount: 0,

        init() {
            // Auto-save on any form change — skip first 2 triggers (initial hydration)
            this.$watch('form', () => {
                this._skipCount++;
                if (this._skipCount <= 2) return;
                clearTimeout(this._saveTimer);
                this._saveTimer = setTimeout(() => this.autoSave(), 500);
            }, { deep: true });

            // Auto-select default presets if not already set
            const defaultPreset = presets.find(p => p.is_default);
            if (defaultPreset && !this.form.campaign_preset_id) {
                this.form.campaign_preset_id = String(defaultPreset.id);
                this.loadPreset();
            }

            // Populate override fields from already-selected presets
            if (this.form.campaign_preset_id) this.loadPreset();
            if (this.form.publish_template_id) this.loadAiTemplate();
            if (this.form.preset_id) this.loadWpPreset();

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
                            // Re-sync author dropdown — timeout for DOM to render options
                            const savedAuthor = this.form.author;
                            if (savedAuthor) {
                                setTimeout(() => { this.form.author = ''; setTimeout(() => { this.form.author = savedAuthor; }, 50); }, 200);
                            }
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

        async loadUserPresets(userId) {
            if (!userId) return;
            this.loadingPresets = true;
            try {
                // Load WP presets for this user and auto-select default
                const presetsResp = await fetch('{{ route("publish.presets.index") }}?user_id=' + userId + '&format=json', { headers: { 'Accept': 'application/json' } });
                const presetsData = await presetsResp.json();
                const userPresets = presetsData.data || presetsData || [];
                const defaultWp = userPresets.find(p => p.is_default);
                if (defaultWp && !this.form.preset_id) {
                    this.form.preset_id = String(defaultWp.id);
                }

                // Load AI templates for this user and auto-select default
                const templatesResp = await fetch('{{ route("publish.templates.index") }}?user_id=' + userId + '&format=json', { headers: { 'Accept': 'application/json' } });
                const templatesData = await templatesResp.json();
                const userTemplates = templatesData.data || templatesData || [];
                const defaultAi = userTemplates.find(t => t.is_default);
                if (defaultAi && !this.form.publish_template_id) {
                    this.form.publish_template_id = String(defaultAi.id);
                }
                // Populate fields from selected presets
                if (this.form.preset_id) this.loadWpPreset();
                if (this.form.publish_template_id) this.loadAiTemplate();
            } catch(e) { /* silent */ }
            this.loadingPresets = false;
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
                    // Re-sync author — timeout for DOM to render options
                    const savedAuthor = this.form.author;
                    if (savedAuthor) {
                        setTimeout(() => { this.form.author = ''; setTimeout(() => { this.form.author = savedAuthor; }, 50); }, 200);
                    }
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
                this.form.source_method = p.source_method || 'trending';
                this.form.genre = p.genre || '';
                this.form.local_preference = p.local_preference || '';
                this.form.keywords_text = (p.keywords || []).join(', ');
                this.form.keywords = p.keywords || [];
                this.form.trending_categories_text = (p.trending_categories || []).join(', ');
                this.form.auto_publish = p.auto_select_sources || false;
                this.form.cp_ai_instructions = p.ai_instructions || '';
                if (p.ai_instructions) this.form.notes = p.ai_instructions;
            }
        },
        restoreCampaignPreset() { this.loadPreset(); },

        loadAiTemplate() {
            const t = aiTemplates.find(x => x.id == this.form.publish_template_id);
            if (t) {
                this.form.ai_engine = t.ai_engine || '';
                this.form.override_article_type = t.article_type || '';
                this.form.override_tone = Array.isArray(t.tone) ? t.tone.join(', ') : (t.tone || '');
                this.form.override_word_min = t.word_count_min || '';
                this.form.override_word_max = t.word_count_max || '';
                this.form.override_photos_per_article = t.photos_per_article || '';
                this.form.override_photo_sources_text = (t.photo_sources || []).join(', ');
                this.form.override_max_links = t.max_links || '';
                this.form.override_ai_description = t.description || '';
                this.form.override_ai_prompt = t.ai_prompt || '';
                this.form.override_structure = t.structure || '';
                this.form.override_rules = t.rules || '';
            }
        },
        restoreAiTemplate() { this.loadAiTemplate(); },

        loadWpPreset() {
            const p = wpPresets.find(x => x.id == this.form.preset_id);
            if (p) {
                this.form.delivery_mode = p.default_publish_action || 'draft-local';
                this.form.override_follow_links = p.follow_links || '';
                this.form.override_wp_article_format = p.article_format || '';
                this.form.override_wp_tone = p.tone || '';
                this.form.override_image_preference = p.image_preference || '';
                this.form.override_image_layout = p.image_layout || '';
                this.form.override_category_count = p.default_category_count || '';
                this.form.override_tag_count = p.default_tag_count || '';
                this.form.override_default_site_id = p.default_site_id || '';
            }
        },
        restoreWpPreset() { this.loadWpPreset(); },

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
                    // Campaign is DB-backed, no localStorage needed
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
