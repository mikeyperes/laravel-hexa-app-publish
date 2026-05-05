<?php

namespace hexa_app_publish\Publishing\Presets\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Forms\Services\FormRegistryService;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Presets\Forms\WordPressPresetForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PresetController extends Controller
{
    private const JSON_CACHE_TTL_SECONDS = 600;
    private const JSON_CACHE_VERSION_KEY = 'publish:presets:json:version';

    public function __construct(
        private PublishAccessService $access,
        private FormRegistryService $formRegistry,
        private FormRuntimeService $formRuntime
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $jsonUserId = $this->access->isAdmin($user)
            ? ($request->integer('user_id') ?: null)
            : ($user?->id ?: null);

        if ($request->wantsJson() || $request->filled('format')) {
            return response()->json($this->jsonPresets($jsonUserId));
        }

        $query = $this->access->presetQuery($user)->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        $presets = $query->orderByDesc('updated_at')->get();

        return view('app-publish::publishing.presets.index', [
            'presets' => $presets,
        ]);
    }

    public function create(Request $request): View
    {
        $form = $this->resolveForm('create');
        $userId = $this->access->isAdmin($request->user())
            ? $request->input('user_id')
            : auth()->id();

        return view('app-publish::publishing.presets.create', [
            'form' => $form,
            'formValues' => WordPressPresetForm::values(null, [
                'user_id' => $userId,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->resolveForm('create');
        $validated = $this->formRuntime->validate($form, $request, ['mode' => 'create', 'context' => 'create']);
        $data = $this->formRuntime->dehydrate($form, $validated, ['mode' => 'create', 'context' => 'create']);
        $data['status'] = $this->validatedStatus($request, 'draft');
        $data['user_id'] = $this->access->isAdmin($request->user())
            ? ($data['user_id'] ?? auth()->id())
            : auth()->id();

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

    public function show(Request $request, int $id): JsonResponse
    {
        $preset = $this->access->resolvePresetOrFail($request->user(), $id)->load('user');

        return response()->json([
            'success' => true,
            'preset' => $preset,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $preset = $this->access->resolvePresetOrFail($request->user(), $id)->load('user');
        $form = $this->resolveForm('edit', $preset);

        return view('app-publish::publishing.presets.edit', [
            'preset' => $preset,
            'form' => $form,
            'formValues' => WordPressPresetForm::values($preset),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $preset = $this->access->resolvePresetOrFail($request->user(), $id);
        $form = $this->resolveForm('edit', $preset);
        $validated = $this->formRuntime->validate($form, $request, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $preset,
        ]);
        $data = $this->formRuntime->dehydrate($form, $validated, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $preset,
        ]);
        $data['status'] = $this->validatedStatus($request, $preset->status ?? 'draft');
        $data['user_id'] = $this->access->isAdmin($request->user())
            ? ($data['user_id'] ?? $preset->user_id)
            : $preset->user_id;

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

    public function toggleDefault(Request $request, int $id): JsonResponse
    {
        $preset = $this->access->resolvePresetOrFail($request->user(), $id);

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

    public function destroy(Request $request, int $id): JsonResponse
    {
        $preset = $this->access->resolvePresetOrFail($request->user(), $id);
        $name = $preset->name;

        $preset->delete();
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'preset_deleted', "Preset deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$name}' deleted successfully.",
        ]);
    }

    protected function resolveForm(string $mode, ?PublishPreset $preset = null)
    {
        return $this->formRegistry->resolve(WordPressPresetForm::FORM_KEY, [
            'mode' => $mode,
            'context' => $mode,
            'record' => $preset,
        ]);
    }

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
