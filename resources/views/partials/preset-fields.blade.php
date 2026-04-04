{{--
    Shared preset fields — shows template/preset fields inline with override + restore.
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <template x-for="field in Object.keys({{ $p }}_defaults)" :key="field">
            <div class="rounded-lg px-3 py-2" :class="isPresetDirty('{{ $p }}', field) ? 'bg-orange-50 ring-1 ring-orange-200' : 'bg-gray-50'">
                <label class="block text-[11px] text-gray-400 mb-1 uppercase tracking-wide" x-text="field.replace(/_/g, ' ')"></label>
                <input
                    type="text"
                    :value="Array.isArray(getPresetValue('{{ $p }}', field)) ? getPresetValue('{{ $p }}', field).join(', ') : String(getPresetValue('{{ $p }}', field) ?? '')"
                    @input="
                        let val = $event.target.value;
                        if (Array.isArray({{ $p }}_defaults[field])) {
                            {{ $p }}_overrides[field] = val.split(',').map(s => s.trim()).filter(Boolean);
                        } else if (typeof {{ $p }}_defaults[field] === 'number') {
                            {{ $p }}_overrides[field] = parseInt(val) || 0;
                        } else {
                            {{ $p }}_overrides[field] = val;
                        }
                        {{ $p }}_dirty[field] = true;
                    "
                    class="w-full border border-gray-200 rounded px-2.5 py-1 text-sm bg-white">
            </div>
        </template>
    </div>
</div>
