{{--
    Shared preset fields partial — renders template/preset fields with override capability.

    Usage:
        @include('app-publish::partials.preset-fields', [
            'prefix' => 'template',  // Alpine data prefix: template_overrides.field
            'label' => 'AI Template Settings',
        ])

    Requires in Alpine component:
        ...presetFieldsMixin('template')
    Which provides:
        template_defaults: {},
        template_overrides: {},
        template_dirty: {},
        loadPresetFields(prefix, data),
        restorePresetDefaults(prefix),
        getPresetValue(prefix, field),
        isPresetDirty(prefix, field),
--}}

@php $p = $prefix ?? 'template'; @endphp

<div x-show="Object.keys({{ $p }}_defaults).length > 0" x-cloak class="mt-3 border border-gray-200 rounded-lg">
    <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 rounded-t-lg border-b border-gray-200 cursor-pointer" @click="{{ $p }}_expanded = !{{ $p }}_expanded">
        <span class="text-xs font-semibold text-gray-600 uppercase">{{ $label ?? 'Preset Settings' }}</span>
        <div class="flex items-center gap-2">
            <span x-show="Object.keys({{ $p }}_dirty).length > 0" x-cloak class="text-xs text-orange-500 font-medium">Modified</span>
            <button x-show="Object.keys({{ $p }}_dirty).length > 0" x-cloak @click.stop="restorePresetDefaults('{{ $p }}')" class="text-xs text-blue-600 hover:text-blue-800">Restore Defaults</button>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{{ $p }}_expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
    </div>
    <div x-show="{{ $p }}_expanded" x-cloak x-collapse class="px-4 py-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            {{-- Each field: shows default, allows override --}}
            <template x-for="[field, value] in Object.entries({{ $p }}_defaults)" :key="field">
                <div :class="isPresetDirty('{{ $p }}', field) ? 'ring-1 ring-orange-300 rounded-lg p-2 -m-1' : ''">
                    <label class="block text-xs text-gray-400 mb-1 capitalize" x-text="field.replace(/_/g, ' ')"></label>
                    <template x-if="typeof value === 'boolean' || value === true || value === false">
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
                    <template x-if="typeof value === 'number'">
                        <input type="number" :value="getPresetValue('{{ $p }}', field)"
                            @input="{{ $p }}_overrides[field] = parseInt($event.target.value); {{ $p }}_dirty[field] = true"
                            class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
                    </template>
                    <template x-if="typeof value === 'string' && value.length > 100">
                        <textarea :value="getPresetValue('{{ $p }}', field)"
                            @input="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                            rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm"></textarea>
                    </template>
                    <template x-if="typeof value === 'string' && value.length <= 100">
                        <input type="text" :value="getPresetValue('{{ $p }}', field)"
                            @input="{{ $p }}_overrides[field] = $event.target.value; {{ $p }}_dirty[field] = true"
                            class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
                    </template>
                    <template x-if="Array.isArray(value)">
                        <input type="text" :value="(getPresetValue('{{ $p }}', field) || []).join(', ')"
                            @input="{{ $p }}_overrides[field] = $event.target.value.split(',').map(s => s.trim()).filter(Boolean); {{ $p }}_dirty[field] = true"
                            class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Comma separated">
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
