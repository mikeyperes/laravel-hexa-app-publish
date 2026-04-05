@php
    /** @var \hexa_core\Forms\Definitions\FormDefinition $form */
    $mode = $mode ?? 'create';
    $resolvedValues = app(\hexa_core\Forms\Services\FormHydrationService::class)->hydrate(
        $form,
        $formValues ?? [],
        [],
        ['context' => $mode, 'mode' => $mode, 'record' => $preset ?? null]
    );
    $fields = collect($form->fieldsForContext($mode));
    $groupedFields = $fields->groupBy(fn ($field) => $field->metaValue('section', 'additional'));
    $sectionOrder = ['account', 'basic', 'content', 'defaults', 'additional'];
    $orderedSections = array_values(array_unique(array_merge($sectionOrder, $groupedFields->keys()->all())));
    $sectionClasses = [
        'account' => 'grid gap-4 grid-cols-1 md:grid-cols-2 pb-4 border-b border-gray-200',
        'basic' => 'grid gap-4 grid-cols-1 md:grid-cols-2',
        'content' => 'grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
        'defaults' => 'grid gap-4 grid-cols-1 md:grid-cols-2 pt-4 border-t border-gray-200',
        'additional' => 'grid gap-4 grid-cols-1 md:grid-cols-2',
    ];
    $sectionTitles = [
        'content' => 'Preset Settings',
        'defaults' => 'Defaults',
    ];
@endphp

<div class="max-w-3xl" x-data="wpPresetFormPage(@js(['mode' => $mode]))">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        <form x-ref="form" action="{{ $form->actionUrl() ?? '#' }}" method="POST" class="space-y-5">
            @csrf
            @if(!in_array($form->httpMethod(), ['GET', 'POST'], true))
                @method($form->httpMethod())
            @endif

            @foreach($orderedSections as $section)
                @continue(!$groupedFields->has($section))

                <div>
                    @if(isset($sectionTitles[$section]))
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">{{ $sectionTitles[$section] }}</h3>
                        </div>
                    @endif

                    <div class="{{ $sectionClasses[$section] ?? 'grid gap-4 grid-cols-1 md:grid-cols-2' }}">
                        @foreach($groupedFields[$section] as $field)
                            <x-hexa-form-field
                                :field="$field"
                                :value="$resolvedValues[$field->name()] ?? null"
                                :context="$mode"
                            />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </form>

        <div class="flex items-center gap-3">
            <button type="button" @click="save('active')" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving && saveType === 'active'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving && saveType === 'active' ? '{{ $mode === 'edit' ? 'Saving...' : 'Creating...' }}' : '{{ $mode === 'edit' ? 'Save Changes' : 'Create Preset' }}'"></span>
            </button>
            <button type="button" @click="save('draft')" :disabled="saving" class="bg-gray-200 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving && saveType === 'draft'" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving && saveType === 'draft' ? 'Saving Draft...' : 'Save as Draft'"></span>
            </button>
            <a href="{{ $cancelUrl }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

        <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
            <span x-text="resultMessage"></span>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    function wpPresetFormPage(config) {
        return {
            saving: false,
            saveType: '',
            resultMessage: '',
            resultSuccess: false,
            async save(status) {
                this.saving = true;
                this.saveType = status;
                this.resultMessage = '';

                try {
                    const formData = new FormData(this.$refs.form);
                    formData.set('status', status);

                    const response = await fetch(this.$refs.form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await response.json();
                    this.resultSuccess = !!data.success;
                    this.resultMessage = data.message || (data.success ? 'Saved.' : 'Failed.');

                    if (!response.ok && data.errors) {
                        const firstError = Object.values(data.errors).flat()[0];
                        if (firstError) this.resultMessage = firstError;
                    }

                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 600);
                    }
                } catch (error) {
                    this.resultSuccess = false;
                    this.resultMessage = 'Error: ' + error.message;
                } finally {
                    this.saving = false;
                }
            }
        };
    }
    </script>
    @endpush
@endonce
