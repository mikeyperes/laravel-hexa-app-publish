{{--
    Shared preset fields — renders template/preset fields with correct input types.
--}}

@php
    $p = $prefix ?? 'template';
    $models = collect(config('anthropic.models', []))->pluck('name', 'id')->toArray();
    $articleTypes = ['editorial','opinion','news-report','local-news','expert-article','pr-full-feature','press-release','listicle','how-to','review'];
    $publishActions = ['auto-publish','draft-local','draft-wordpress','review','notify'];
    $photoSourceOptions = ['unsplash','pexels','pixabay'];
@endphp

<div x-show="Object.keys({{ $p }}_defaults).length > 0" x-cloak class="mt-3">
    <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $label ?? 'Settings' }} <span class="text-gray-400 font-normal" x-text="'(' + Object.keys({{ $p }}_defaults).length + ')'"></span></span>
        <div class="flex items-center gap-2">
            <span x-show="Object.keys({{ $p }}_dirty).length > 0" x-cloak class="text-xs text-orange-500 font-medium">Modified</span>
            <button x-show="Object.keys({{ $p }}_dirty).length > 0" x-cloak @click="restorePresetDefaults('{{ $p }}')" class="text-xs text-blue-600 hover:text-blue-800">Restore Defaults</button>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
        <template x-for="field in Object.keys({{ $p }}_defaults)" :key="field">
            <div class="rounded-lg px-3 py-2" :class="isPresetDirty('{{ $p }}', field) ? 'bg-orange-50 ring-1 ring-orange-200' : 'bg-gray-50'"
                 :class="{'md:col-span-2': field === 'ai_prompt'}">

                <label class="block text-[11px] text-gray-400 mb-1 uppercase tracking-wide" x-text="field.replace(/_/g, ' ')"></label>

                {{-- AI Engine — select dropdown --}}
                <template x-if="field === 'ai_engine'">
                    <select :value="getPresetValue('{{ $p }}', field)"
                        @change="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white">
                        @foreach($models as $id => $name)
                            <option value="{{ $id }}">{{ $name }} ({{ $id }})</option>
                        @endforeach
                    </select>
                </template>

                {{-- Article type — select --}}
                <template x-if="field === 'article_type'">
                    <select :value="getPresetValue('{{ $p }}', field)"
                        @change="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white">
                        <option value="">—</option>
                        @foreach($articleTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst(str_replace('-', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </template>

                {{-- Default publish action — select --}}
                <template x-if="field === 'default_publish_action'">
                    <select :value="getPresetValue('{{ $p }}', field)"
                        @change="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white">
                        @foreach($publishActions as $action)
                            <option value="{{ $action }}">{{ ucfirst(str_replace('-', ' ', $action)) }}</option>
                        @endforeach
                    </select>
                </template>

                {{-- AI Prompt — textarea (full width) --}}
                <template x-if="field === 'ai_prompt'">
                    <textarea :value="getPresetValue('{{ $p }}', field)"
                        @input="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        rows="4" class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white font-mono"></textarea>
                </template>

                {{-- Follow links — select --}}
                <template x-if="field === 'follow_links'">
                    <select :value="getPresetValue('{{ $p }}', field)"
                        @change="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white">
                        <option value="follow">Follow</option>
                        <option value="nofollow">Nofollow</option>
                        <option value="sponsored">Sponsored</option>
                        <option value="ugc">UGC</option>
                    </select>
                </template>

                {{-- Number fields --}}
                <template x-if="['word_count_min','word_count_max','max_links','photos_per_article','default_category_count','default_tag_count'].includes(field)">
                    <input type="number" :value="getPresetValue('{{ $p }}', field)"
                        @input="{{ $p }}_overrides[field] = parseInt($event.target.value) || 0; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white" min="0">
                </template>

                {{-- Array fields (tone, photo_sources) --}}
                <template x-if="Array.isArray({{ $p }}_defaults[field]) && !['word_count_min','word_count_max','max_links','photos_per_article','default_category_count','default_tag_count','ai_engine','article_type','default_publish_action','ai_prompt'].includes(field)">
                    <input type="text" :value="(getPresetValue('{{ $p }}', field) || []).join(', ')"
                        @input="{{ $p }}_overrides[field] = $event.target.value.split(',').map(s => s.trim()).filter(Boolean); {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white" placeholder="Comma separated">
                </template>

                {{-- Boolean fields --}}
                <template x-if="(typeof {{ $p }}_defaults[field] === 'boolean') && !['word_count_min','word_count_max','max_links','photos_per_article','default_category_count','default_tag_count','ai_engine','article_type','default_publish_action','ai_prompt'].includes(field) && !Array.isArray({{ $p }}_defaults[field])">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="{{ $p }}_overrides[field] = !getPresetValue('{{ $p }}', field); {{ $p }}_dirty[field] = true"
                            class="relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                            :class="getPresetValue('{{ $p }}', field) ? 'bg-green-500' : 'bg-gray-300'" role="switch">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                                :class="getPresetValue('{{ $p }}', field) ? 'translate-x-5' : 'translate-x-0'"></span>
                        </button>
                        <span class="text-xs text-gray-500" x-text="getPresetValue('{{ $p }}', field) ? 'Yes' : 'No'"></span>
                    </div>
                </template>

                {{-- Default: text input (for anything not matched above) --}}
                <template x-if="typeof {{ $p }}_defaults[field] === 'string' && !Array.isArray({{ $p }}_defaults[field]) && typeof {{ $p }}_defaults[field] !== 'boolean' && !['ai_engine','article_type','default_publish_action','ai_prompt','follow_links','word_count_min','word_count_max','max_links','photos_per_article','default_category_count','default_tag_count'].includes(field)">
                    <input type="text" :value="getPresetValue('{{ $p }}', field)"
                        @input="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white">
                </template>
            </div>
        </template>
    </div>
</div>
