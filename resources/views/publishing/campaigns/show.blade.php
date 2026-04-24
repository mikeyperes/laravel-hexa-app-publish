@extends('layouts.app')
@section('title', 'Campaign — ' . ($campaign->name ?: 'Untitled'))
@section('header', '')

@section('content')
<style>
    .hx-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:visible; margin-bottom:24px; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    .hx-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:20px 24px; border-bottom:1px solid #f1f5f9; }
    .hx-card-header.hx-clickable { cursor:pointer; user-select:none; transition:background 0.12s; }
    .hx-card-header.hx-clickable:hover { background:#fafbfc; }
    .hx-card-title-block { display:flex; align-items:flex-start; gap:14px; min-width:0; flex:1; }
    .hx-card-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:#eff6ff; color:#2563eb; }
    .hx-card-icon svg { width:20px; height:20px; }
    .hx-card-icon.green  { background:#f0fdf4; color:#16a34a; }
    .hx-card-icon.amber  { background:#fffbeb; color:#d97706; }
    .hx-card-icon.purple { background:#faf5ff; color:#9333ea; }
    .hx-card-icon.pink   { background:#fdf2f8; color:#db2777; }
    .hx-card-icon.slate  { background:#f1f5f9; color:#475569; }
    .hx-card-icon.indigo { background:#eef2ff; color:#4f46e5; }
    .hx-card-icon.red    { background:#fef2f2; color:#dc2626; }
    .hx-card-title { font-size:16px; font-weight:700; color:#111827; margin:0; line-height:1.3; }
    .hx-card-subtitle { font-size:13px; color:#6b7280; margin-top:4px; line-height:1.45; }
    .hx-card-body { padding:24px; }
    .hx-card-header-right { display:flex; align-items:center; gap:10px; flex-shrink:0; }
    .hx-tag { display:inline-flex; align-items:center; padding:2px 9px; border-radius:9999px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap; }
    .hx-tag.blue  { background:#dbeafe; color:#1d4ed8; }
    .hx-tag.green { background:#dcfce7; color:#166534; }
    .hx-tag.amber { background:#fef3c7; color:#92400e; }
    .hx-tag.red   { background:#fee2e2; color:#991b1b; }
    .hx-tag.gray  { background:#f3f4f6; color:#4b5563; }
    .hx-tag.slate { background:#f1f5f9; color:#334155; }
    .hx-label { display:block; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; }
    .hx-input, .hx-select, .hx-textarea { width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; background:#fff; color:#111827; transition:border-color 0.1s, box-shadow 0.1s; }
    .hx-input:focus, .hx-select:focus, .hx-textarea:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,0.2); }
    .hx-textarea { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; resize:vertical; min-height:72px; }
    .hx-select { appearance:none; -webkit-appearance:none; -moz-appearance:none; padding-right:36px; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:16px 16px; cursor:pointer; }
    .hx-select:hover { border-color:#94a3b8; }
    .hx-select option { background:#fff; color:#111827; }
    .hx-autocomplete { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:16px 16px; padding-right:36px; }
    .hx-field { margin-bottom:20px; }
    .hx-field:last-child { margin-bottom:0; }
    .hx-field-hint { font-size:12px; color:#9ca3af; margin-top:6px; }
    .hx-grid-2 { display:grid; grid-template-columns:1fr; gap:20px; }
    @media(min-width:640px) { .hx-grid-2 { grid-template-columns:1fr 1fr; } }
    .hx-grid-3 { display:grid; grid-template-columns:1fr; gap:20px; }
    @media(min-width:640px) { .hx-grid-3 { grid-template-columns:repeat(3, 1fr); } }
    .hx-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; font-size:13px; font-weight:500; border:1px solid transparent; cursor:pointer; transition:background 0.1s, opacity 0.1s; white-space:nowrap; }
    .hx-btn-primary { background:#2563eb; color:#fff; }
    .hx-btn-primary:hover:not(:disabled) { background:#1d4ed8; }
    .hx-btn-secondary { background:#fff; color:#374151; border-color:#d1d5db; }
    .hx-btn-secondary:hover:not(:disabled) { background:#f9fafb; }
    .hx-btn-green { background:#16a34a; color:#fff; }
    .hx-btn-green:hover:not(:disabled) { background:#15803d; }
    .hx-btn-amber { background:#d97706; color:#fff; }
    .hx-btn-amber:hover:not(:disabled) { background:#b45309; }
    .hx-btn-red { background:#fff; color:#b91c1c; border-color:#fecaca; }
    .hx-btn-red:hover:not(:disabled) { background:#fef2f2; }
    .hx-btn:disabled { opacity:0.55; cursor:not-allowed; }
    .hx-chev { transition:transform 0.15s; color:#9ca3af; }
    .hx-chev.open { transform:rotate(180deg); }
    .hx-article-row { display:flex; gap:18px; align-items:stretch; padding:16px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; transition:border-color 0.12s, box-shadow 0.12s; }
    .hx-article-row:hover { border-color:#cbd5e1; box-shadow:0 2px 6px rgba(15,23,42,0.06); }
    .hx-article-row + .hx-article-row { margin-top:12px; }
    .hx-article-thumb { width:180px; height:135px; border-radius:10px; overflow:hidden; background:#f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#9ca3af; }
    .hx-article-thumb img { width:100%; height:100%; object-fit:cover; }
    .hx-article-body { flex:1; min-width:0; display:flex; flex-direction:column; gap:6px; }
    .hx-article-title { font-size:18px; font-weight:700; color:#111827; line-height:1.3; margin:0; }
    .hx-article-title a:hover { color:#2563eb; }
    .hx-article-attrs { display:flex; flex-wrap:wrap; align-items:center; gap:6px 10px; font-size:12px; color:#64748b; margin-top:2px; }
    .hx-article-attrs > * { display:inline-flex; align-items:center; }
    .hx-article-attrs .hx-tag { font-size:10px; }
    .hx-article-attrs .hx-sep { color:#cbd5e1; user-select:none; }
    .hx-article-attrs strong { color:#334155; font-weight:600; }
    .hx-article-actions { flex-shrink:0; display:flex; flex-direction:column; align-items:flex-end; gap:10px; min-width:120px; }
    .hx-link { color:#2563eb; }
    .hx-link:hover { color:#1d4ed8; text-decoration:underline; }
    .hx-link-muted { color:#64748b; }
    .hx-link-muted:hover { color:#334155; text-decoration:underline; }
    [x-cloak] { display:none !important; }
</style>

<div class="max-w-6xl mx-auto pt-4" x-data="campaignDashboard()" x-init="init()">

    {{-- ───────────────────────────────────────────────
         HEADER CARD
         ─────────────────────────────────────────────── --}}
    <div class="hx-card">
        <div class="hx-card-header">
            <div class="hx-card-title-block">
                <div class="hx-card-icon amber">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <input type="text" x-model="form.name" class="w-full text-xl font-bold text-gray-900 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none focus:border-b focus:border-blue-500" placeholder="Untitled Campaign">
                    <div class="hx-card-subtitle flex flex-wrap items-center gap-2 mt-2">
                        <span class="hx-tag gray">{{ $campaign->campaign_id ?? ('#' . $campaign->id) }}</span>
                        <span class="hx-tag" :class="campaignStatus === 'active' ? 'green' : (campaignStatus === 'paused' ? 'amber' : (campaignStatus === 'archived' ? 'red' : 'gray'))" x-text="formatLabel(campaignStatus)"></span>
                        <span class="text-xs text-gray-500">Created by {{ $campaign->creator->name ?? '—' }} · {{ $campaign->created_at ? $campaign->created_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A') : '—' }}</span>
                    </div>
                </div>
            </div>
            <div class="hx-card-header-right">
                <button @click="runNow()" :disabled="runningNow" class="hx-btn hx-btn-primary">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <span x-show="!runningNow">Run Now</span>
                    <span x-show="runningNow" x-cloak>Running…</span>
                </button>
                <button x-show="campaignStatus === 'active'" x-cloak @click="pauseCampaign()" class="hx-btn hx-btn-amber">Pause</button>
                <button x-show="campaignStatus !== 'active'" x-cloak @click="activateCampaign()" class="hx-btn hx-btn-green">Activate</button>
                <button @click="duplicateCampaign()" class="hx-btn hx-btn-secondary">Duplicate</button>
                <button @click="deleteCampaign()" class="hx-btn hx-btn-red">Delete</button>
            </div>
        </div>
        <div class="px-5 py-2 text-xs border-t border-gray-100 flex items-center justify-between bg-gray-50/50 rounded-b-xl">
            <div class="text-gray-500">
                <span class="mr-4"><span class="text-gray-400">Last run:</span> <span class="font-semibold text-gray-800">{{ $campaign->last_run_at ? $campaign->last_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : 'Never' }}</span></span>
                <span><span class="text-gray-400">Next run:</span> <span class="font-semibold text-gray-800">{{ $campaign->next_run_at ? $campaign->next_run_at->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A T') : '—' }}</span></span>
            </div>
            <div>
                <span x-show="saving" x-cloak class="text-gray-400">Saving…</span>
                <span x-show="!saving && saveResult" x-cloak :class="saveSuccess ? 'text-green-600' : 'text-red-500'" x-text="saveResult"></span>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         CAMPAIGN DETAILS (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon blue">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9h6m-6 4h6"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Campaign Details</h3>
                    <p class="hx-card-subtitle">Internal name, description, and AI instructions for this campaign.</p>
                </div>
            </div>
            <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-grid-2">
                <div class="hx-field">
                    <label class="hx-label">Internal Description</label>
                    <textarea rows="3" x-model="form.description" class="hx-textarea" placeholder="Internal summary of the campaign's editorial goal."></textarea>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Campaign Instructions</label>
                    <textarea rows="3" x-model="form.ai_instructions" class="hx-textarea" placeholder="Editorial guardrails passed to the AI for each run."></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         SCHEDULE & CADENCE (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon purple">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Schedule &amp; Cadence</h3>
                    <p class="hx-card-subtitle">Owning user, frequency, posts per run, run time, and drip interval.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag slate" x-text="formatLabel(form.interval_unit || 'daily') + ' · ' + (form.articles_per_interval || 1) + '/run'"></span>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-field">
                <label class="hx-label">User</label>
                <div class="max-w-md"
                    @hexa-search-selected.window="if ($event.detail.component_id === 'campaign-user') form.user_id = $event.detail.item.id"
                    @hexa-search-cleared.window="if ($event.detail.component_id === 'campaign-user') form.user_id = null">
                    @php $currentUser = $campaign->user ? ['id' => $campaign->user->id, 'name' => $campaign->user->name, 'email' => $campaign->user->email] : null; @endphp
                    <x-hexa-smart-search url="{{ route('api.search.users') }}" name="user_id" placeholder="Search users..." display-field="name" subtitle-field="email" value-field="id" id="campaign-user" show-id :selected="$currentUser" />
                </div>
                <p class="hx-field-hint">Timezone: {{ $campaign->timezone ?? 'America/New_York' }} — inherited from the selected user.</p>
            </div>
            <div class="hx-grid-2">
                <div class="hx-field">
                    <label class="hx-label">Frequency</label>
                    <select x-model="form.interval_unit" class="hx-select">
                        <option value="hourly">Hourly</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Posts per run</label>
                    <input type="number" min="1" max="50" x-model.number="form.articles_per_interval" class="hx-input">
                </div>
                <div class="hx-field">
                    <label class="hx-label">Run at time</label>
                    <input type="time" x-model="form.run_at_time" class="hx-input">
                </div>
                <div class="hx-field">
                    <label class="hx-label">Drip interval (minutes between posts in a run)</label>
                    <input type="number" min="1" max="1440" x-model.number="form.drip_interval_minutes" class="hx-input">
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         CONTENT SOURCE — Campaign Preset (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon indigo">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Content Source — Campaign Preset</h3>
                    <p class="hx-card-subtitle">Search queries, discovery mode, and run-time instructions.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <a x-show="campaignPresetEditUrl" x-cloak :href="campaignPresetEditUrl" target="_blank" rel="noopener" @click.stop class="hx-link text-xs inline-flex items-center gap-1">Edit preset
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4h6m0 0v6m0-6L10 14M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                </a>
                <a href="{{ route('campaigns.presets.index') }}" target="_blank" rel="noopener" @click.stop class="text-xs text-gray-500 hover:text-gray-800">Manage</a>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-field">
                <label class="hx-label">Campaign preset</label>
                <select x-model="form.campaign_preset_id" @change="onCampaignPresetChange()" class="hx-select max-w-md">
                    <option value="">— No preset —</option>
                    @foreach($campaignPresets as $cp)
                        <option value="{{ $cp->id }}">{{ $cp->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('app-publish::partials.preset-fields', ['prefix' => 'campaignPreset', 'label' => 'Campaign Preset Fields'])
            <div class="pt-4 mt-4 border-t border-gray-100">
                <div class="hx-field">
                    <label class="hx-label">Search queries <span class="text-gray-400 normal-case font-normal">— one per line, overrides preset</span></label>
                    <textarea rows="5" x-model="termsText" class="hx-textarea" placeholder="Leave blank to use preset queries"></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         ARTICLE GENERATION — Article Preset (expandable)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon pink">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Article Generation — Article Preset</h3>
                    <p class="hx-card-subtitle">Tone, word count, models, and how each article is written.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <a x-show="articlePresetEditUrl" x-cloak :href="articlePresetEditUrl" target="_blank" rel="noopener" @click.stop class="hx-link text-xs inline-flex items-center gap-1">Edit preset
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4h6m0 0v6m0-6L10 14M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                </a>
                <a href="{{ route('publish.templates.index') }}" target="_blank" rel="noopener" @click.stop class="text-xs text-gray-500 hover:text-gray-800">Manage</a>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="hx-grid-2">
                <div class="hx-field">
                    <label class="hx-label">Article preset</label>
                    <select x-model="form.publish_template_id" @change="onArticlePresetChange()" class="hx-select">
                        <option value="">— No preset —</option>
                        @foreach($aiTemplates as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Article type override <span class="text-gray-400 normal-case font-normal">— blank uses preset</span></label>
                    <select x-model="form.article_type" class="hx-select">
                        <option value="">— Use preset's type —</option>
                        @foreach($articleTypes as $at)
                            <option value="{{ $at }}">{{ ucwords(str_replace('-', ' ', $at)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @include('app-publish::partials.preset-fields', ['prefix' => 'template', 'label' => 'Article Preset Fields'])
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         PUBLISHING TARGET
         ─────────────────────────────────────────────── --}}
    <div class="hx-card">
        <div class="hx-card-header">
            <div class="hx-card-title-block">
                <div class="hx-card-icon green">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Publishing Target</h3>
                    <p class="hx-card-subtitle">WordPress site, author, delivery mode, and post status.</p>
                </div>
            </div>
        </div>
        <div class="hx-card-body">
            <div class="hx-field">
                <label class="hx-label">WordPress site</label>
                <select x-model="form.publish_site_id" @change="doTestSite()" class="hx-select max-w-md">
                    <option value="">— Select site —</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->url }})</option>
                    @endforeach
                </select>
                <div x-show="form.publish_site_id" x-cloak class="mt-2 flex items-center gap-2 text-xs rounded-lg px-3 py-2 max-w-md" :class="siteConn.status === true ? 'bg-green-50 text-green-800' : (siteConn.status === false ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-600')">
                    <span x-show="siteConn.testing" x-cloak>Testing…</span>
                    <span x-show="!siteConn.testing" x-cloak x-text="siteConn.message || 'Click Test to verify site access.'"></span>
                    <button @click="doTestSite()" class="ml-auto hx-link">Test</button>
                </div>
            </div>
            <div class="hx-grid-3">
                <div class="hx-field relative" @click.outside="authorDropdownOpen = false">
                    <label class="hx-label">Author</label>
                    <input type="text" x-model="form.author"
                        @focus="authorDropdownOpen = true"
                        @input="authorDropdownOpen = true"
                        @keydown.escape="authorDropdownOpen = false"
                        autocomplete="off"
                        :placeholder="siteAuthorCount === 0 ? 'Test site connection to load authors, or type a name' : 'Type to search authors'"
                        class="hx-input hx-autocomplete">
                    <div x-show="authorDropdownOpen && filteredSiteAuthors.length > 0" x-cloak
                        class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                        <template x-for="(author, idx) in filteredSiteAuthors" :key="author.id ?? author.slug ?? author.username ?? idx">
                            <button type="button"
                                @mousedown.prevent="selectSiteAuthor(author)"
                                class="block w-full text-left px-3 py-2 text-sm hover:bg-blue-50 border-b border-gray-100 last:border-b-0">
                                <span class="font-medium text-gray-900" x-text="authorLabel(author)"></span>
                                <template x-if="authorSubLabel(author)">
                                    <span class="ml-2 text-xs text-gray-500" x-text="authorSubLabel(author)"></span>
                                </template>
                            </button>
                        </template>
                    </div>
                    <p class="hx-field-hint" x-show="siteAuthorCount === 0" x-cloak>Test the site connection above to load the WordPress author list into cache.</p>
                    <p class="hx-field-hint" x-show="siteAuthorCount > 0" x-cloak>
                        <span x-text="siteAuthorCount"></span> author<span x-show="siteAuthorCount !== 1">s</span> cached from WordPress. Type to filter.
                    </p>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Delivery mode</label>
                    <select x-model="form.delivery_mode" class="hx-select">
                        @foreach($deliveryModes as $m)
                            <option value="{{ $m }}">{{ ucwords(str_replace('-', ' ', $m)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hx-field">
                    <label class="hx-label">Post status</label>
                    <select x-model="form.post_status" class="hx-select">
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="publish">Publish</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         CAMPAIGN ARTICLES — 1 per row, consolidated
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: true}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon slate">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Campaign Articles</h3>
                    <p class="hx-card-subtitle">Every article this campaign has produced — most recent first.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag slate">{{ $campaign->articles->count() }} total</span>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            @forelse($campaign->articles as $article)
                @php
                    $thumb = null;
                    if ($article->wp_images && is_array($article->wp_images)) {
                        $featured = collect($article->wp_images)->firstWhere('is_featured', true) ?: collect($article->wp_images)->first();
                        if (is_array($featured)) {
                            $thumb = $featured['sizes']['thumbnail'] ?? $featured['sizes']['medium'] ?? $featured['inline_url'] ?? $featured['media_url'] ?? null;
                        }
                    }
                    if (!$thumb && $article->photos && is_array($article->photos)) {
                        $firstPhoto = collect($article->photos)->first();
                        if (is_array($firstPhoto)) {
                            $thumb = $firstPhoto['sizes']['thumbnail'] ?? $firstPhoto['url'] ?? $firstPhoto['src'] ?? null;
                        }
                    }
                    $sourceCount = count((array) ($article->source_articles ?? []));
                @endphp
                @php
                    $siteUrl = rtrim($campaign->site->url ?? '', '/');
                    $siteName = $campaign->site->name ?? parse_url($siteUrl, PHP_URL_HOST) ?: '';
                    $authorSlug = $article->author ? \Illuminate\Support\Str::slug($article->author) : '';
                    $authorWpUrl = ($siteUrl && $authorSlug) ? $siteUrl . '/author/' . $authorSlug . '/' : null;
                    $pipelineTone = match($article->status) {
                        'completed' => 'green',
                        'failed', 'error' => 'red',
                        'running', 'pending', 'queued' => 'amber',
                        default => 'slate',
                    };
                    $pipelineLabel = match($article->status) {
                        'completed' => 'Generated',
                        'running' => 'Generating',
                        'queued', 'pending' => 'Queued',
                        'failed', 'error' => 'Failed',
                        default => ucfirst((string) $article->status),
                    };
                    $isDraft = in_array($article->wp_status, ['draft', 'pending', 'auto-draft', 'future'], true);
                    $wpTone = match($article->wp_status) {
                        'publish', 'published' => 'green',
                        'draft', 'auto-draft' => 'gray',
                        'pending', 'future' => 'amber',
                        'trash' => 'red',
                        default => 'slate',
                    };
                    // Draft posts return 404 on the public ?p= URL — link to the WP admin edit screen instead.
                    $wpEditUrl = ($siteUrl && $article->wp_post_id)
                        ? $siteUrl . '/wp-admin/post.php?post=' . $article->wp_post_id . '&action=edit'
                        : null;
                    $wpLink = $isDraft
                        ? ($wpEditUrl ?: $article->wp_post_url)
                        : ($article->wp_post_url ?: $wpEditUrl);
                    $wpLinkLabel = $isDraft ? 'Edit in WordPress' : 'View on WordPress';
                @endphp
                <div class="hx-article-row">
                    <a href="{{ route('publish.articles.show', $article->id) }}" target="_blank" rel="noopener" class="hx-article-thumb">
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="" loading="lazy">
                        @else
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @endif
                    </a>
                    <div class="hx-article-body">
                        <h4 class="hx-article-title">
                            <a href="{{ route('publish.articles.show', $article->id) }}" target="_blank" rel="noopener" class="text-gray-900 hover:text-blue-700">{{ $article->title ?: 'Untitled Article' }}</a>
                        </h4>
                        <div class="hx-article-attrs">
                            <span class="hx-tag {{ $pipelineTone }}" title="Internal pipeline generation state">Pipeline: {{ $pipelineLabel }}</span>
                            @if($article->wp_status)
                                @if($wpLink)
                                    <a href="{{ $wpLink }}" target="_blank" rel="noopener" class="hx-tag {{ $wpTone }}" title="{{ $wpLinkLabel }} on {{ $siteName ?: 'WordPress' }}">WordPress: {{ ucfirst($article->wp_status) }} ↗</a>
                                @else
                                    <span class="hx-tag {{ $wpTone }}" title="WordPress post status (no URL available)">WordPress: {{ ucfirst($article->wp_status) }}</span>
                                @endif
                            @endif
                            @if($siteName)
                                <a href="{{ $siteUrl ?: '#' }}" target="_blank" rel="noopener" class="hx-tag blue" title="Open {{ $siteName }}">Site:&nbsp;{{ $siteName }} ↗</a>
                            @endif
                        </div>
                        <div class="hx-article-attrs">
                            <span>{{ $article->article_id }}</span>
                            <span class="hx-sep">·</span>
                            @if($article->author && $authorWpUrl)
                                <span>By&nbsp;<a href="{{ $authorWpUrl }}" target="_blank" rel="noopener" class="hx-link" title="Open {{ $article->author }}'s WordPress profile">{{ $article->author }}&nbsp;↗</a></span>
                            @elseif($article->author)
                                <span>By&nbsp;<strong>{{ $article->author }}</strong></span>
                            @else
                                <span>By <span class="text-gray-400">—</span></span>
                            @endif
                            @if($article->word_count)
                                <span class="hx-sep">·</span>
                                <span>{{ number_format($article->word_count) }} words</span>
                            @endif
                            @if($sourceCount > 0)
                                <span class="hx-sep">·</span>
                                <span>{{ $sourceCount }} source{{ $sourceCount === 1 ? '' : 's' }}</span>
                            @endif
                            @if($article->ai_engine_used)
                                <span class="hx-sep">·</span>
                                <span>{{ $article->ai_engine_used }}</span>
                            @endif
                            @if($article->ai_cost)
                                <span class="hx-sep">·</span>
                                <span>${{ number_format((float) $article->ai_cost, 4) }}</span>
                            @endif
                            <span class="hx-sep">·</span>
                            <span>{{ $article->created_at?->setTimezone($campaign->timezone ?? 'America/New_York')->format('M j, Y g:i A') }}</span>
                        </div>
                        @if(!empty($article->categories) && is_array($article->categories))
                            <div class="hx-article-attrs">
                                <span class="text-gray-400">Categories:</span>
                                <span>{{ implode(' · ', array_slice((array) $article->categories, 0, 5)) }}@if(count((array) $article->categories) > 5) <span class="text-gray-400">+{{ count((array) $article->categories) - 5 }}</span>@endif</span>
                            </div>
                        @endif
                    </div>
                    <div class="hx-article-actions">
                        <a href="{{ route('publish.articles.show', $article->id) }}" target="_blank" rel="noopener" class="hx-link text-sm font-semibold">Open →</a>
                        @if($wpLink)
                            <a href="{{ $wpLink }}" target="_blank" rel="noopener" class="hx-link-muted text-xs">{{ $wpLinkLabel }} ↗</a>
                        @endif
                        @if($article->wp_post_url && $isDraft && $wpLink !== $article->wp_post_url)
                            <a href="{{ $article->wp_post_url }}?preview=true" target="_blank" rel="noopener" class="hx-link-muted text-xs">Preview draft ↗</a>
                        @endif
                        @if($article->wp_post_id)
                            <span class="text-[10px] text-gray-400 font-mono">WP #{{ $article->wp_post_id }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-400">This campaign has not produced any articles yet.</div>
            @endforelse
        </div>
    </div>

    {{-- ───────────────────────────────────────────────
         ACTIVITY / RUNS LOG (expandable, collapsed by default)
         ─────────────────────────────────────────────── --}}
    <div class="hx-card" x-data="{open: false}">
        <div class="hx-card-header hx-clickable" @click="open = !open">
            <div class="hx-card-title-block">
                <div class="hx-card-icon red">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <h3 class="hx-card-title">Activity Log</h3>
                    <p class="hx-card-subtitle">Most recent campaign activity and run events.</p>
                </div>
            </div>
            <div class="hx-card-header-right">
                <span class="hx-tag slate">{{ count($runLogs ?? []) }} entr{{ count($runLogs ?? []) === 1 ? 'y' : 'ies' }}</span>
                <svg class="w-4 h-4 hx-chev" :class="{ 'open': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="hx-card-body">
            <div class="space-y-2 max-h-[28rem] overflow-auto">
                @forelse($runLogs as $log)
                    <div class="rounded-lg border border-gray-200 px-4 py-2">
                        <div class="flex items-center justify-between gap-3 text-xs text-gray-400">
                            <span>{{ \Illuminate\Support\Carbon::parse($log->created_at)->timezone($campaign->timezone ?? 'America/New_York')->format('M j, g:i:s A') }}</span>
                            <span>{{ $log->action }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700">{{ $log->message }}</p>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-400 text-center">No activity logged yet.</div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@include('app-publish::partials.preset-fields-mixin')
@include('app-publish::partials.site-connection-mixin')
<script>
function campaignDashboard() {
    const initialForm          = @json($campaignForm);
    const campaignPresetItems  = @json($campaignPresetItems);
    const articlePresetItems   = @json($aiTemplateItems);
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
        authorDropdownOpen: false,
        _saveTimer: null,
        _skipCount: 0,

        get siteAuthorCount() {
            return Array.isArray(this.siteConn?.authors) ? this.siteConn.authors.length : 0;
        },
        get filteredSiteAuthors() {
            if (!Array.isArray(this.siteConn?.authors)) return [];
            const q = (this.form.author || '').toLowerCase().trim();
            if (!q) return this.siteConn.authors.slice(0, 50);
            return this.siteConn.authors.filter(a => {
                const hay = [
                    a.display_name || a.name || '',
                    a.username || a.slug || '',
                    a.email || '',
                ].join(' ').toLowerCase();
                return hay.includes(q);
            }).slice(0, 50);
        },
        authorLabel(a) {
            if (!a) return '';
            return a.display_name || a.name || a.username || a.slug || a.email || '';
        },
        authorSubLabel(a) {
            if (!a) return '';
            const primary = this.authorLabel(a);
            const username = a.username || a.slug || '';
            if (username && username !== primary) return '@' + username;
            if (a.email && a.email !== primary) return a.email;
            return '';
        },
        selectSiteAuthor(a) {
            this.form.author = this.authorLabel(a);
            this.authorDropdownOpen = false;
        },

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
            this.$watch('termsText', v => { this.form.keywords = (v || '').split('\n').map(s => s.trim()).filter(Boolean); });
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
