<?php

namespace hexa_app_publish\Publishing\Presets\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Forms\Services\FormRegistryService;
use hexa_core\Forms\Services\FormHydrationService;
use hexa_core\Forms\Services\FormValidationService;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Presets\Forms\WordPressPresetForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * PublishPresetController — CRUD for WordPress publishing presets.
 * Uses the core form definition system for create, edit, store, update.
 */
class PresetController extends Controller
{
    private const JSON_CACHE_TTL_SECONDS = 600;
    private const JSON_CACHE_VERSION_KEY = 'publish:presets:json:version';

    public function __construct(
        private FormRegistryService $formRegistry,
        private FormHydrationService $formHydration,
        private FormValidationService $formValidation
    ) {}

    /**
     * List presets, optionally filtered by user.
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson() || $request->filled('format')) {
            return response()->json($this->jsonPresets($request->integer('user_id') ?: null));
        }

        $query = PublishPreset::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $presets = $query->orderByDesc('updated_at')->get();

        return view('app-publish::publishing.presets.index', [
            'presets' => $presets,
        ]);
    }

    /**
     * Show create form — uses form-definition system.
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $form = $this->resolveForm('create');

        return view('app-publish::publishing.presets.create', [
            'form' => $form,
            'formValues' => WordPressPresetForm::values(null, [
                'user_id' => $request->input('user_id'),
            ]),
        ]);
    }

    /**
     * Store a new preset — uses form registry + validation + dehydration.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $form = $this->resolveForm('create');
        $payload = $this->normalizedFormPayload($request, $form);
        $validated = $this->formValidation->validate($payload, $form, ['mode' => 'create', 'context' => 'create']);
        $data = $this->formHydration->dehydrate($form, $validated);
        $data['status'] = $this->validatedStatus($request, 'draft');
        $data['user_id'] = $data['user_id'] ?? auth()->id();

        if (!empty($data['is_default'])) {
            PublishPreset::where('user_id', $data['user_id'])->update(['is_default' => false]);
        }

        $preset = PublishPreset::create($data);
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'preset_created', "Preset created: {$preset->name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$preset->name}' created successfully.",
            'preset' => $preset->load('user'),
            'redirect' => route('publish.presets.edit', $preset->id),
        ]);
    }

    /**
     * Show a single preset.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $preset = PublishPreset::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'preset' => $preset,
        ]);
    }

    /**
     * Edit form — uses form-definition system.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $preset = PublishPreset::with('user')->findOrFail($id);
        $form = $this->resolveForm('edit', $preset);

        return view('app-publish::publishing.presets.edit', [
            'preset' => $preset,
            'form' => $form,
            'formValues' => WordPressPresetForm::values($preset),
        ]);
    }

    /**
     * Update a preset — uses form registry + validation + dehydration.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $preset = PublishPreset::findOrFail($id);
        $form = $this->resolveForm('edit', $preset);
        $payload = $this->normalizedFormPayload($request, $form);
        $validated = $this->formValidation->validate($payload, $form, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $preset,
        ]);
        $data = $this->formHydration->dehydrate($form, $validated);
        $data['status'] = $this->validatedStatus($request, $preset->status ?? 'draft');

        if (!empty($data['is_default'])) {
            PublishPreset::where('user_id', $preset->user_id)
                ->where('id', '!=', $preset->id)
                ->update(['is_default' => false]);
        }

        $preset->update($data);
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'preset_updated', "Preset updated: {$preset->name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$preset->name}' updated successfully.",
        ]);
    }

    /**
     * Toggle a preset as default for its user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function toggleDefault(int $id): JsonResponse
    {
        $preset = PublishPreset::findOrFail($id);

        if ($preset->is_default) {
            $preset->update(['is_default' => false]);
            $this->bumpJsonCacheVersion();
            hexaLog('publish', 'preset_default_removed', "Default removed: {$preset->name}");
            return response()->json(['success' => true, 'message' => "'{$preset->name}' is no longer the default.", 'is_default' => false]);
        }

        PublishPreset::where('user_id', $preset->user_id)->where('id', '!=', $preset->id)->update(['is_default' => false]);
        $preset->update(['is_default' => true]);
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'preset_default_set', "Default preset set: {$preset->name}");
        return response()->json(['success' => true, 'message' => "'{$preset->name}' is now the default.", 'is_default' => true]);
    }

    /**
     * Delete a preset.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $preset = PublishPreset::findOrFail($id);
        $name = $preset->name;

        $preset->delete();
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'preset_deleted', "Preset deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$name}' deleted successfully.",
        ]);
    }

    /**
     * Resolve the form definition from the registry.
     *
     * @param string $mode
     * @param PublishPreset|null $preset
     * @return \hexa_core\Forms\Definitions\FormDefinition
     */
    protected function resolveForm(string $mode, ?PublishPreset $preset = null)
    {
        return $this->formRegistry->resolve(WordPressPresetForm::FORM_KEY, [
            'mode' => $mode,
            'context' => $mode,
            'record' => $preset,
        ]);
    }

    /**
     * Normalize form payload — handle missing booleans/arrays.
     * Matches TemplateController::normalizedFormPayload() exactly.
     *
     * @param Request $request
     * @param mixed $form
     * @return array
     */
    protected function normalizedFormPayload(Request $request, $form): array
    {
        $payload = $request->all();

        foreach ($form->getFields() as $field) {
            if (($field->isMultiple() || in_array($field->type(), ['checkbox_group', 'multiselect', 'array'], true))
                && !$request->has($field->name())
            ) {
                $payload[$field->name()] = [];
            }

            if (in_array($field->type(), ['boolean', 'toggle'], true) && !$request->has($field->name())) {
                $payload[$field->name()] = false;
            }
        }

        return $payload;
    }

    /**
     * Validate and return the status field.
     *
     * @param Request $request
     * @param string $default
     * @return string
     */
    protected function validatedStatus(Request $request, string $default = 'draft'): string
    {
        $status = $request->input('status', $default);

        return in_array($status, ['draft', 'active'], true) ? $status : $default;
    }

    private function jsonPresets(?int $userId = null): array
    {
        $cacheKey = implode(':', [
            'publish',
            'presets',
            'json',
            'v' . $this->jsonCacheVersion(),
            'user',
            $userId ?: 'all',
        ]);

        return Cache::remember($cacheKey, now()->addSeconds(self::JSON_CACHE_TTL_SECONDS), function () use ($userId) {
            $query = PublishPreset::query()
                ->select([
                    'id',
                    'user_id',
                    'name',
                    'status',
                    'is_default',
                    'default_site_id',
                    'follow_links',
                    'article_format',
                    'tone',
                    'image_preference',
                    'default_publish_action',
                    'default_category_count',
                    'default_tag_count',
                    'image_layout',
                    'searching_agent',
                    'scraping_agent',
                    'spinning_agent',
                    'created_at',
                    'updated_at',
                ])
                ->orderByDesc('updated_at');

            if ($userId) {
                $query->where('user_id', $userId);
            }

            return $query->get()->map(fn (PublishPreset $preset) => $preset->toArray())->all();
        });
    }

    private function jsonCacheVersion(): int
    {
        return (int) Cache::get(self::JSON_CACHE_VERSION_KEY, 1);
    }

    private function bumpJsonCacheVersion(): void
    {
        if (!Cache::has(self::JSON_CACHE_VERSION_KEY)) {
            Cache::forever(self::JSON_CACHE_VERSION_KEY, 2);
            return;
        }

        Cache::increment(self::JSON_CACHE_VERSION_KEY);
    }
}
