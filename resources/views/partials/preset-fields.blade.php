{{--
    Shared preset fields — types from server schema, no hardcoding.
--}}

@php $p = $prefix ?? 'template'; @endphp

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
            <div class="rounded-lg px-3 py-2" :class="[isPresetDirty('{{ $p }}', field) ? 'bg-orange-50 ring-1 ring-orange-200' : 'bg-gray-50', getPresetFieldType('{{ $p }}', field) === 'textarea' ? 'md:col-span-2' : '']">
                <label class="block text-[11px] text-gray-400 mb-1 uppercase tracking-wide" x-text="field.replace(/_/g, ' ')"></label>

                {{-- Select (options from server schema) --}}
                <template x-if="getPresetFieldType('{{ $p }}', field) === 'select'">
                    <select :value="getPresetValue('{{ $p }}', field)"
                        @change="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white">
                        <template x-for="[optVal, optLabel] in Object.entries(getPresetFieldOptions('{{ $p }}', field) || {})" :key="optVal">
                            <option :value="optVal" x-text="optLabel" :selected="getPresetValue('{{ $p }}', field) === optVal"></option>
                        </template>
                    </select>
                </template>

                {{-- Number --}}
                <template x-if="getPresetFieldType('{{ $p }}', field) === 'number'">
                    <input type="number" :value="getPresetValue('{{ $p }}', field)"
                        @input="{{ $p }}_overrides[field] = parseInt($event.target.value) || 0; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white" min="0">
                </template>

                {{-- Array --}}
                <template x-if="getPresetFieldType('{{ $p }}', field) === 'array'">
                    <input type="text" :value="(getPresetValue('{{ $p }}', field) || []).join(', ')"
                        @input="{{ $p }}_overrides[field] = $event.target.value.split(',').map(s => s.trim()).filter(Boolean); {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white" placeholder="Comma separated">
                </template>

                {{-- Textarea --}}
                <template x-if="getPresetFieldType('{{ $p }}', field) === 'textarea'">
                    <textarea :value="getPresetValue('{{ $p }}', field)"
                        @input="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        rows="3" class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white font-mono"></textarea>
                </template>

                {{-- Checkbox group (multi-select with predefined options) --}}
                <template x-if="getPresetFieldType('{{ $p }}', field) === 'checkbox'">
                    <div class="flex flex-wrap gap-3">
                        <template x-for="[optVal, optLabel] in Object.entries(getPresetFieldOptions('{{ $p }}', field) || {})" :key="optVal">
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                                <input type="checkbox"
                                    :checked="(getPresetValue('{{ $p }}', field) || []).includes(optVal)"
                                    @change="
                                        let arr = [...({{ $p }}_overrides[field] || getPresetValue('{{ $p }}', field) || [])];
                                        const i = arr.indexOf(optVal);
                                        if (i === -1) arr.push(optVal); else arr.splice(i, 1);
                                        {{ $p }}_overrides[field] = arr;
                                        {{ $p }}_dirty[field] = true;
                                    "
                                    class="rounded border-gray-300 text-blue-600">
                                <span x-text="optLabel"></span>
                            </label>
                        </template>
                    </div>
                </template>

                {{-- Boolean --}}
                <template x-if="getPresetFieldType('{{ $p }}', field) === 'boolean'">
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

                {{-- Text (default) --}}
                <template x-if="getPresetFieldType('{{ $p }}', field) === 'text'">
                    <input type="text" :value="getPresetValue('{{ $p }}', field)"
                        @input="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                        class="w-full border border-gray-200 rounded px-2.5 py-1.5 text-sm bg-white">
                </template>
            </div>
        </template>
    </div>
</div>
