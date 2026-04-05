@php
    $options = $field->resolveOptions(['context' => $context, 'field' => $field]);
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
        x-model="selectedCompany"
        @change="if (!((companyModels[selectedCompany] || []).includes(formAiEngine))) { formAiEngine = ''; }"
        @disabled($field->isDisabled())
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
    >
        <option value="">{{ $field->metaValue('empty_label', 'Select...') }}</option>
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) ($value ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if($field->helpText())
        <p class="mt-1 text-xs text-gray-500">{{ $field->helpText() }}</p>
    @endif
</div>
