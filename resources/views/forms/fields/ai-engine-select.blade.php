@php
    $companyModels = $field->metaValue('company_models', []);
    $currentValue = (string) ($value ?? '');
@endphp

<div class="{{ $field->columnClasses() }}">
    <label for="{{ $field->htmlId($namePrefix) }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $field->label() }}
        @if($field->isRequired())
            <span class="text-red-500">*</span>
        @endif
    </label>
    <select
        id="{{ $field->htmlId($namePrefix) }}"
        name="{{ str_replace('[]', '', $field->inputName($namePrefix)) }}"
        x-model="formAiEngine"
        :disabled="!selectedCompany"
        @disabled($field->isDisabled())
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
    >
        <option value="">{{ $field->metaValue('empty_label', 'Select...') }}</option>
        @foreach($companyModels as $company => $models)
            @foreach($models as $model)
                <option
                    value="{{ $model }}"
                    x-show="selectedCompany === '{{ $company }}'"
                    @selected($currentValue === $model)
                >
                    {{ $model }}
                </option>
            @endforeach
        @endforeach
    </select>
    @if($field->helpText())
        <p class="mt-1 text-xs text-gray-500">{{ $field->helpText() }}</p>
    @endif
</div>
