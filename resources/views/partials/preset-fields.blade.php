{{--
    Shared preset fields — sectioned using the server schema metadata.
--}}

@php
    $p = $prefix ?? 'template';
    $exclude = array_values($excludeFields ?? []);
@endphp

<div x-show="getPresetFieldCount('{{ $p }}', @js($exclude)) > 0" x-cloak class="mt-5">
    <div class="rounded-3xl border border-slate-200 bg-gradient-to-b from-slate-50 via-white to-white p-5 space-y-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                @if(!empty($eyebrow ?? null))
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $eyebrow }}</p>
                @endif
                <h4 class="mt-1 text-base font-semibold text-slate-900">{{ $title ?? $label ?? 'Selected Preset' }} <span class="text-slate-400 font-normal" x-text="'(' + getPresetFieldCount('{{ $p }}', @js($exclude)) + ')' "></span></h4>
                @if(!empty($description ?? null))
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <span x-show="Object.keys({{ $p }}_dirty).length > 0" x-cloak class="text-xs text-orange-600 font-medium">Modified</span>
                <button x-show="Object.keys({{ $p }}_dirty).length > 0" x-cloak @click="restorePresetDefaults('{{ $p }}')" class="text-xs text-blue-600 hover:text-blue-800">Restore Defaults</button>
            </div>
        </div>

        <template x-for="section in getPresetSections('{{ $p }}', @js($exclude), @js($sectionLabels ?? []), @js($sectionDescriptions ?? []))" :key="section.key">
            <section class="rounded-3xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-4 py-3">
                    <div>
                        <h5 class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" x-text="section.label"></h5>
                        <p x-show="section.description" x-cloak class="mt-1 text-sm text-slate-500" x-text="section.description"></p>
                    </div>
                    <span class="text-[11px] text-slate-400" x-text="section.fields.length + ' field' + (section.fields.length === 1 ? '' : 's')"></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                    <template x-for="field in section.fields" :key="field.name">
                        <div class="rounded-2xl border px-4 py-4"
                            :class="[
                                field.columns && field.columns.includes('md:col-span-2') ? 'md:col-span-2' : '',
                                isPresetDirty('{{ $p }}', field.name) ? 'border-orange-300 bg-orange-50/70' : 'border-slate-200 bg-slate-50/30'
                            ]">
                            <div class="flex items-start justify-between gap-3 mb-1.5">
                                <label class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500" x-text="field.label"></label>
                                <span x-show="isPresetDirty('{{ $p }}', field.name)" x-cloak class="text-[10px] font-medium text-orange-600">Changed</span>
                            </div>
                            <p x-show="field.help" x-cloak class="mb-2 text-xs text-slate-500 leading-relaxed" x-text="field.help"></p>

                            <template x-if="field.type === 'select'">
                                <select :value="getPresetValue('{{ $p }}', field.name)"
                                    @change="{{ $p }}_overrides[field.name] = $event.target.value; {{ $p }}_dirty[field.name] = true"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">
                                    <option x-show="field.emptyLabel" x-cloak value="" x-text="field.emptyLabel"></option>
                                    <template x-for="[optVal, optLabel] in Object.entries(field.options || {})" :key="optVal">
                                        <option :value="optVal" x-text="optLabel"></option>
                                    </template>
                                </select>
                            </template>

                            <template x-if="field.type === 'number'">
                                <input type="number" :value="getPresetValue('{{ $p }}', field.name)"
                                    @input="{{ $p }}_overrides[field.name] = $event.target.value === '' ? '' : Number($event.target.value); {{ $p }}_dirty[field.name] = true"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">
                            </template>

                            <template x-if="field.type === 'time'">
                                <input type="time" :value="getPresetValue('{{ $p }}', field.name)"
                                    @input="{{ $p }}_overrides[field.name] = $event.target.value; {{ $p }}_dirty[field.name] = true"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">
                            </template>

                            <template x-if="field.type === 'array'">
                                <input type="text" :value="(getPresetValue('{{ $p }}', field.name) || []).join(', ')"
                                    @input="{{ $p }}_overrides[field.name] = $event.target.value.split(',').map(s => s.trim()).filter(Boolean); {{ $p }}_dirty[field.name] = true"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Comma separated values">
                            </template>

                            <template x-if="field.type === 'textarea'">
                                <textarea :value="getPresetValue('{{ $p }}', field.name)"
                                    @input="{{ $p }}_overrides[field.name] = $event.target.value; {{ $p }}_dirty[field.name] = true"
                                    :rows="field.rows || 4"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white font-mono"></textarea>
                            </template>

                            <template x-if="field.type === 'checkbox'">
                                <div class="flex flex-wrap gap-3">
                                    <template x-for="[optVal, optLabel] in Object.entries(field.options || {})" :key="optVal">
                                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                                            <input type="checkbox"
                                                :checked="(getPresetValue('{{ $p }}', field.name) || []).includes(optVal)"
                                                @change="
                                                    let arr = [...({{ $p }}_overrides[field.name] || getPresetValue('{{ $p }}', field.name) || [])];
                                                    const i = arr.indexOf(optVal);
                                                    if (i === -1) arr.push(optVal); else arr.splice(i, 1);
                                                    {{ $p }}_overrides[field.name] = arr;
                                                    {{ $p }}_dirty[field.name] = true;
                                                "
                                                class="rounded border-slate-300 text-blue-600">
                                            <span x-text="optLabel"></span>
                                        </label>
                                    </template>
                                </div>
                            </template>

                            <template x-if="field.type === 'boolean'">
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                        @click="{{ $p }}_overrides[field.name] = !getPresetValue('{{ $p }}', field.name); {{ $p }}_dirty[field.name] = true"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="getPresetValue('{{ $p }}', field.name) ? 'bg-green-500' : 'bg-slate-300'" role="switch">
                                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200"
                                            :class="getPresetValue('{{ $p }}', field.name) ? 'translate-x-5' : 'translate-x-0'"></span>
                                    </button>
                                    <span class="text-sm text-slate-600" x-text="getPresetValue('{{ $p }}', field.name) ? 'Enabled' : 'Disabled'"></span>
                                </div>
                            </template>

                            <template x-if="!['select', 'number', 'time', 'array', 'textarea', 'checkbox', 'boolean'].includes(field.type)">
                                <input type="text" :value="getPresetValue('{{ $p }}', field.name)"
                                    @input="{{ $p }}_overrides[field.name] = $event.target.value; {{ $p }}_dirty[field.name] = true"
                                    :placeholder="field.placeholder || ''"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">
                            </template>
                        </div>
                    </template>
                </div>
            </section>
        </template>
    </div>
</div>
