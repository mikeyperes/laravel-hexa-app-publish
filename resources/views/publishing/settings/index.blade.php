{{-- Master Publishing Settings --}}
@extends('layouts.app')
@section('title', 'Publishing Settings')
@section('header', 'Publishing Settings')

@section('content')
<div class="space-y-6" x-data="masterSettingsManager()">

    <div class="bg-yellow-50 rounded-lg border border-yellow-200 p-3 text-sm text-yellow-800">
        <strong>Admin only</strong> -- These guidelines are system-wide and control how AI generates and publishes content. Users cannot see these settings.
    </div>

    {{-- ═══ WordPress Publishing Guidelines ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">WordPress Publishing Guidelines</h3>
            <button @click="showNewWp = !showNewWp" class="text-sm text-blue-600 hover:text-blue-800">+ New Document</button>
        </div>

        {{-- New WP guideline form --}}
        <div x-show="showNewWp" x-cloak class="p-5 bg-gray-50 border-b border-gray-200">
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Document Name</label>
                <input type="text" x-model="newWp.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Default WordPress Content Rules">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Content</label>
                <textarea id="new-wp-editor" x-model="newWp.content" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Enter the WordPress publishing guidelines..."></textarea>
            </div>
            <div class="flex items-center gap-2">
                <button @click="saveNew('wordpress_guidelines', newWp)" :disabled="savingWp" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="savingWp" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="savingWp ? 'Creating...' : 'Create Document'"></span>
                </button>
            </div>
            <div x-show="wpResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="wpSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="wpResult"></span>
            </div>
        </div>

        @if($wordpressGuidelines->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No WordPress guidelines created yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($wordpressGuidelines as $doc)
                <div class="p-5" x-data="{ editing: false, docName: '{{ addslashes($doc->name) }}', docContent: {{ json_encode($doc->content ?? '') }}, docActive: {{ $doc->is_active ? 'true' : 'false' }}, docSaving: false, docResult: '', docSuccess: false }">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3 flex-1">
                            <template x-if="!editing">
                                <h4 class="font-medium text-gray-800 break-words">{{ $doc->name }}</h4>
                            </template>
                            <template x-if="editing">
                                <input type="text" x-model="docName" class="border border-gray-300 rounded-lg px-3 py-1 text-sm flex-1">
                            </template>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $doc->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $doc->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                            <button @click="editing = !editing" class="text-xs text-blue-600 hover:text-blue-800" x-text="editing ? 'Cancel' : 'Edit'"></button>
                            <button @click="toggleActive({{ $doc->id }}, !docActive)" class="text-xs" :class="docActive ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800'" x-text="docActive ? 'Deactivate' : 'Activate'"></button>
                            <button @click="deleteSetting({{ $doc->id }}, '{{ addslashes($doc->name) }}')" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                        </div>
                    </div>
                    <template x-if="!editing">
                        <div class="text-sm text-gray-600 break-words" style="white-space: pre-wrap;">{{ $doc->content }}</div>
                    </template>
                    <template x-if="editing">
                        <div>
                            <textarea x-model="docContent" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2"></textarea>
                            <div class="flex items-center gap-2">
                                <button @click="
                                    docSaving = true; docResult = '';
                                    fetch('/publishing/settings/{{ $doc->id }}', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ name: docName, content: docContent, is_active: docActive }) })
                                        .then(r => r.json()).then(d => { docSuccess = d.success; docResult = d.message; if (d.success) setTimeout(() => location.reload(), 600); })
                                        .catch(e => { docSuccess = false; docResult = 'Error: ' + e.message; })
                                        .finally(() => docSaving = false);
                                " :disabled="docSaving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                                    <svg x-show="docSaving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span x-text="docSaving ? 'Saving...' : 'Save Changes'"></span>
                                </button>
                            </div>
                            <div x-show="docResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="docSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                <span x-text="docResult"></span>
                            </div>
                        </div>
                    </template>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ AI Detection Threshold ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-data="{ threshold: {{ \hexa_core\Models\Setting::getValue('ai_detection_threshold', 10) }}, saving: false, saved: false }">
        <h3 class="font-semibold text-gray-800 mb-1">AI Detection Threshold</h3>
        <p class="text-sm text-gray-500 mb-3">Maximum AI percentage allowed. Articles above this threshold will be flagged for re-spinning. Example: 10 means up to 10% AI content is acceptable.</p>
        <div class="flex items-center gap-3">
            <input type="number" x-model="threshold" min="0" max="100" class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <span class="text-sm text-gray-500">%</span>
            <button @click="
                saving = true; saved = false;
                fetch('{{ route('publish.settings.master.save-setting') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }, body: JSON.stringify({ setting_key: 'ai_detection_threshold', setting_value: threshold.toString() }) })
                .then(r => r.json()).then(d => { saving = false; saved = d.success; setTimeout(() => saved = false, 3000); }).catch(() => saving = false);
            " :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="saving ? 'Saving...' : (saved ? 'Saved!' : 'Save')"></span>
            </button>
        </div>
    </div>

    {{-- ═══ WordPress Photo Filename Pattern ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-data="{ pattern: '{{ \hexa_core\Models\Setting::getValue('wp_photo_filename_pattern', 'hexa_{draft_id}_{seo_name}') }}', saving: false, saved: false }">
        <h3 class="font-semibold text-gray-800 mb-1">WordPress Photo Filename Pattern</h3>
        <p class="text-sm text-gray-500 mb-3">How photos are named when uploaded to WordPress. The extension (.jpg, .png) is auto-appended.</p>

        {{-- Available shortcodes --}}
        <div class="bg-gray-50 rounded-lg p-3 mb-3">
            <p class="text-xs font-semibold text-gray-600 mb-1.5">Available Shortcodes</p>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-1 text-xs">
                <div><code class="bg-gray-200 px-1 rounded">{draft_id}</code> — Draft/article ID</div>
                <div><code class="bg-gray-200 px-1 rounded">{seo_name}</code> — AI-generated SEO slug</div>
                <div><code class="bg-gray-200 px-1 rounded">{index}</code> — Photo position (1, 2, 3...)</div>
                <div><code class="bg-gray-200 px-1 rounded">{article_slug}</code> — Article title slugified</div>
                <div><code class="bg-gray-200 px-1 rounded">{date}</code> — Current date (YYYYMMDD)</div>
                <div><code class="bg-gray-200 px-1 rounded">{post_id}</code> — WordPress post ID (after publish)</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input type="text" x-model="pattern" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="hexa_{draft_id}_{seo_name}">
            <button @click="
                saving = true; saved = false;
                fetch('{{ route('publish.settings.master.save-setting') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }, body: JSON.stringify({ setting_key: 'wp_photo_filename_pattern', setting_value: pattern }) })
                .then(r => r.json()).then(d => { saving = false; saved = d.success; setTimeout(() => saved = false, 3000); }).catch(() => saving = false);
            " :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="saving ? 'Saving...' : (saved ? 'Saved!' : 'Save')"></span>
            </button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Preview: <span class="font-mono text-gray-600" x-text="pattern.replace('{draft_id}', '33').replace('{seo_name}', 'college-sports-reform').replace('{index}', '1').replace('{article_slug}', 'trump-signs-executive-order').replace('{date}', '20260404').replace('{post_id}', '1234') + '.jpg'"></span></p>
    </div>

    {{-- ═══ Master Spin Prompt ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="{ savingPrompt: false, promptSaved: false }">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Master Spin Prompt</h3>
            <p class="text-xs text-gray-500 mt-1">This is the EXACT prompt sent to AI when spinning articles. Use shortcodes for dynamic values.</p>
        </div>
        <div class="p-5">
            @php
                $masterSpinPrompt = \hexa_app_publish\Models\PublishMasterSetting::where('type', 'master_spin_prompt')->where('is_active', true)->value('content') ?? '';
            @endphp
            <textarea id="master-spin-prompt" rows="20" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm font-mono leading-relaxed" placeholder="Enter the master spin prompt...">{{ $masterSpinPrompt }}</textarea>

            <div class="mt-3 flex items-center gap-3">
                <button @click="
                    savingPrompt = true; promptSaved = false;
                    const content = document.getElementById('master-spin-prompt').value;
                    fetch('/publishing/settings/save-prompt', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
                        body: JSON.stringify({ content: content })
                    }).then(r => r.json()).then(d => { savingPrompt = false; promptSaved = d.success; setTimeout(() => promptSaved = false, 3000); }).catch(() => savingPrompt = false);
                " :disabled="savingPrompt" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                    <svg x-show="savingPrompt" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="savingPrompt ? 'Saving...' : (promptSaved ? 'Saved!' : 'Save Prompt')"></span>
                </button>
                <span x-show="promptSaved" x-cloak class="text-sm text-green-600 font-medium">Saved successfully</span>
            </div>

            {{-- Shortcode Reference --}}
            <div class="mt-4 bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Available Shortcodes</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-1 text-xs">
                    <div><code class="bg-gray-200 px-1 rounded">{custom_instructions}</code> — User's custom input from pipeline</div>
                    <div><code class="bg-gray-200 px-1 rounded">{wordpress_guidelines}</code> — WP publishing guidelines (from above)</div>
                    <div><code class="bg-gray-200 px-1 rounded">{spinning_guidelines}</code> — AI spinning guidelines (from below)</div>
                    <div><code class="bg-gray-200 px-1 rounded">{preset_config}</code> — WP preset: tone, format, links, images</div>
                    <div><code class="bg-gray-200 px-1 rounded">{template_config}</code> — AI template: prompt, tone, type, word count</div>
                    <div><code class="bg-gray-200 px-1 rounded">{photo_count}</code> — Number of photos (from template or default 2-4)</div>
                    <div><code class="bg-gray-200 px-1 rounded">{source_articles}</code> — The actual source article texts</div>
                    <div><code class="bg-gray-200 px-1 rounded">{featured_image_preference}</code> — Featured image preference from preset</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Master AI Spinning Guidelines ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Master AI Spinning Guidelines</h3>
            <button @click="showNewSpin = !showNewSpin" class="text-sm text-blue-600 hover:text-blue-800">+ New Document</button>
        </div>

        {{-- New Spinning guideline form --}}
        <div x-show="showNewSpin" x-cloak class="p-5 bg-gray-50 border-b border-gray-200">
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Document Name</label>
                <input type="text" x-model="newSpin.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Master Spinning Rules v1">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Content</label>
                <textarea id="new-spin-editor" x-model="newSpin.content" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Enter the AI spinning/rewriting guidelines..."></textarea>
            </div>
            <div class="flex items-center gap-2">
                <button @click="saveNew('spinning_guidelines', newSpin)" :disabled="savingSpin" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                    <svg x-show="savingSpin" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="savingSpin ? 'Creating...' : 'Create Document'"></span>
                </button>
            </div>
            <div x-show="spinResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="spinSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span x-text="spinResult"></span>
            </div>
        </div>

        @if($spinningGuidelines->isEmpty())
            <div class="p-5 text-center text-gray-500 text-sm">No spinning guidelines created yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($spinningGuidelines as $doc)
                <div class="p-5" x-data="{ editing: false, docName: '{{ addslashes($doc->name) }}', docContent: {{ json_encode($doc->content ?? '') }}, docActive: {{ $doc->is_active ? 'true' : 'false' }}, docSaving: false, docResult: '', docSuccess: false }">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3 flex-1">
                            <template x-if="!editing">
                                <h4 class="font-medium text-gray-800 break-words">{{ $doc->name }}</h4>
                            </template>
                            <template x-if="editing">
                                <input type="text" x-model="docName" class="border border-gray-300 rounded-lg px-3 py-1 text-sm flex-1">
                            </template>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $doc->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $doc->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                            <button @click="editing = !editing" class="text-xs text-blue-600 hover:text-blue-800" x-text="editing ? 'Cancel' : 'Edit'"></button>
                            <button @click="toggleActive({{ $doc->id }}, !docActive)" class="text-xs" :class="docActive ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800'" x-text="docActive ? 'Deactivate' : 'Activate'"></button>
                            <button @click="deleteSetting({{ $doc->id }}, '{{ addslashes($doc->name) }}')" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                        </div>
                    </div>
                    <template x-if="!editing">
                        <div class="text-sm text-gray-600 break-words" style="white-space: pre-wrap;">{{ $doc->content }}</div>
                    </template>
                    <template x-if="editing">
                        <div>
                            <textarea x-model="docContent" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2"></textarea>
                            <div class="flex items-center gap-2">
                                <button @click="
                                    docSaving = true; docResult = '';
                                    fetch('/publishing/settings/{{ $doc->id }}', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ name: docName, content: docContent, is_active: docActive }) })
                                        .then(r => r.json()).then(d => { docSuccess = d.success; docResult = d.message; if (d.success) setTimeout(() => location.reload(), 600); })
                                        .catch(e => { docSuccess = false; docResult = 'Error: ' + e.message; })
                                        .finally(() => docSaving = false);
                                " :disabled="docSaving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                                    <svg x-show="docSaving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span x-text="docSaving ? 'Saving...' : 'Save Changes'"></span>
                                </button>
                            </div>
                            <div x-show="docResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="docSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                <span x-text="docResult"></span>
                            </div>
                        </div>
                    </template>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ AI Prompts & Instructions ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-800">AI Prompts & Instructions</h3>
                <p class="text-xs text-gray-500 mt-1">These prompts control how the AI generates, formats, and edits content. Each section is documented below.</p>
            </div>
            <button @click="addSetting('ai_system_prompt')" class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">+ Add Prompt</button>
        </div>

        <div class="p-5 space-y-4">
            {{-- Documentation --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                <h4 class="font-semibold mb-2">Prompt Types Reference</h4>
                <dl class="space-y-2">
                    <div><dt class="font-medium">ai_system_prompt</dt><dd class="text-blue-700">The main system instruction sent to the AI before any article task. Controls tone, behavior, and output format.</dd></div>
                    <div><dt class="font-medium">ai_html_format</dt><dd class="text-blue-700">Instructions for HTML output formatting. Tells the AI to use proper HTML tags instead of markdown.</dd></div>
                    <div><dt class="font-medium">ai_spin_instruction</dt><dd class="text-blue-700">Instructions for the spin/rewrite task. Tells the AI what to do with source articles.</dd></div>
                    <div><dt class="font-medium">ai_change_instruction</dt><dd class="text-blue-700">Instructions for "Request Changes" edits. Controls how the AI applies user-requested modifications.</dd></div>
                    <div><dt class="font-medium">ai_metadata_prompt</dt><dd class="text-blue-700">Instructions for generating titles, categories, and tags from article content.</dd></div>
                </dl>
            </div>

            @if($aiPrompts->isEmpty())
                <p class="text-sm text-gray-400 py-4">No AI prompts configured. Using built-in defaults. Add prompts above to customize AI behavior.</p>
            @else
                @foreach($aiPrompts as $setting)
                <div class="border border-gray-200 rounded-lg p-4" x-data="{ editingAi: false }">
                    <div x-show="!editingAi" class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-medium text-gray-800">{{ $setting->name }}</h4>
                                <span class="text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-700 font-mono">{{ $setting->type }}</span>
                                @if(!$setting->is_active)
                                    <span class="text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Inactive</span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-600 mt-1 break-words whitespace-pre-wrap bg-gray-50 rounded p-3 max-h-48 overflow-y-auto">{{ $setting->content }}</div>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <button @click="editingAi = true" class="text-gray-400 hover:text-blue-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                            <button @click="deleteSetting({{ $setting->id }})" class="text-gray-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                        </div>
                    </div>
                    <div x-show="editingAi" x-cloak class="space-y-3">
                        <input type="text" value="{{ $setting->name }}" id="ai-name-{{ $setting->id }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <select id="ai-type-{{ $setting->id }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="ai_system_prompt" {{ $setting->type === 'ai_system_prompt' ? 'selected' : '' }}>System Prompt</option>
                            <option value="ai_html_format" {{ $setting->type === 'ai_html_format' ? 'selected' : '' }}>HTML Format</option>
                            <option value="ai_spin_instruction" {{ $setting->type === 'ai_spin_instruction' ? 'selected' : '' }}>Spin Instruction</option>
                            <option value="ai_change_instruction" {{ $setting->type === 'ai_change_instruction' ? 'selected' : '' }}>Change Instruction</option>
                            <option value="ai_metadata_prompt" {{ $setting->type === 'ai_metadata_prompt' ? 'selected' : '' }}>Metadata Prompt</option>
                        </select>
                        <textarea id="ai-content-{{ $setting->id }}" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">{{ $setting->content }}</textarea>
                        <div class="flex gap-2">
                            <button @click="updateSetting({{ $setting->id }}, document.getElementById('ai-name-{{ $setting->id }}').value, document.getElementById('ai-type-{{ $setting->id }}').value, document.getElementById('ai-content-{{ $setting->id }}').value)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Save</button>
                            <button @click="editingAi = false" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">Cancel</button>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function masterSettingsManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        showNewWp: false, showNewSpin: false,
        newWp: { name: '', content: '' },
        newSpin: { name: '', content: '' },
        savingWp: false, wpResult: '', wpSuccess: false,
        savingSpin: false, spinResult: '', spinSuccess: false,
        async saveNew(type, data) {
            const isWp = type === 'wordpress_guidelines';
            if (isWp) { this.savingWp = true; this.wpResult = ''; }
            else { this.savingSpin = true; this.spinResult = ''; }

            // Sync TinyMCE if active
            const editorId = isWp ? 'new-wp-editor' : 'new-spin-editor';
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                data.content = tinymce.get(editorId).getContent();
            }

            try {
                const r = await fetch('{{ route("publish.settings.master.store") }}', {
                    method: 'POST', headers, body: JSON.stringify({ ...data, type })
                });
                const d = await r.json();
                if (isWp) { this.wpSuccess = d.success; this.wpResult = d.message; }
                else { this.spinSuccess = d.success; this.spinResult = d.message; }
                if (d.success) setTimeout(() => location.reload(), 600);
            } catch(e) {
                if (isWp) { this.wpSuccess = false; this.wpResult = 'Error: ' + e.message; }
                else { this.spinSuccess = false; this.spinResult = 'Error: ' + e.message; }
            }
            if (isWp) this.savingWp = false;
            else this.savingSpin = false;
        },
        async toggleActive(id, newState) {
            try {
                const r = await fetch('/publishing/settings/' + id, {
                    method: 'PUT', headers,
                    body: JSON.stringify({ name: '', is_active: newState })
                });
                const d = await r.json();
                if (d.success) location.reload();
            } catch(e) { alert('Error: ' + e.message); }
        },
        async deleteSetting(id, name) {
            if (!confirm('Delete setting "' + name + '"?')) return;
            try { await fetch('/publishing/settings/' + id, { method: 'DELETE', headers }); location.reload(); } catch(e) { alert('Error: ' + e.message); }
        }
    };
}
</script>

{{-- TinyMCE CDN for WYSIWYG editing --}}
@php $tinymceKey = \hexa_core\Models\Setting::getValue('tinymce_api_key', ''); @endphp
@if($tinymceKey)
<script src="https://cdn.tiny.cloud/1/{{ $tinymceKey }}/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
@else
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#new-wp-editor, #new-spin-editor',
            height: 300,
            menubar: false,
            plugins: 'lists link code',
            toolbar: 'undo redo | bold italic | bullist numlist | link code',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; }',
        });
    }
});
</script>
@endpush
