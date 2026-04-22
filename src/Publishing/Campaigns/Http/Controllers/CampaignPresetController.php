<?php

namespace hexa_app_publish\Publishing\Campaigns\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Forms\Services\FormRegistryService;
use hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm;
use hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignPresetController extends Controller
{
    protected FormRegistryService $formRegistry;
    protected FormRuntimeService $formRuntime;

    public function __construct(
        FormRegistryService $formRegistry,
        FormRuntimeService $formRuntime
    )
    {
        $this->formRegistry = $formRegistry;
        $this->formRuntime = $formRuntime;
    }

    public function index(Request $request): View|JsonResponse
    {
        $query = CampaignPreset::with('user')->orderByDesc('updated_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $presets = $query->paginate(50);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $presets,
            ]);
        }

        $editPreset = $request->filled('id')
            ? CampaignPreset::with('user')->findOrFail($request->integer('id'))
            : null;

        $formContext = $editPreset ? 'edit' : 'create';
        $runtimeContext = $this->formContext($formContext, $editPreset);
        $form = $this->resolveForm($formContext, $editPreset);

        return view('app-publish::publishing.campaigns.presets.index', [
            'presets' => $presets,
            'editPreset' => $editPreset,
            'form' => $form,
            'formContext' => $formContext,
            'formValues' => $this->formRuntime->hydrate($form, $editPreset, [], $runtimeContext),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $context = $this->formContext('create');
        $form = $this->resolveForm('create');
        $validated = $this->formRuntime->validate($form, $request, $context);
        $data = $this->normalizedPresetData($this->formRuntime->dehydrate($form, $validated, $context));
        $data['created_by'] = auth()->id();
        $data['user_id'] = $data['user_id'] ?? auth()->id();

        if (!empty($data['is_default'])) {
            CampaignPreset::query()->update(['is_default' => false]);
        }

        $preset = CampaignPreset::create($data);

        hexaLog('campaigns', 'preset_created', "Campaign preset created: {$preset->name}");

        return $this->respondAfterSave($request, $preset, "Preset '{$preset->name}' created.");
    }

    public function show(int $id): JsonResponse
    {
        $preset = CampaignPreset::with('user')->findOrFail($id);
        $context = $this->formContext('edit', $preset);

        return response()->json([
            'success' => true,
            'preset' => $preset,
            'form_values' => $this->formRuntime->hydrate(CampaignPresetForm::FORM_KEY, $preset, [], $context),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $preset = CampaignPreset::findOrFail($id);
        $context = $this->formContext('edit', $preset);
        $form = $this->resolveForm('edit', $preset);
        $validated = $this->formRuntime->validate($form, $request, $context);
        $data = $this->normalizedPresetData($this->formRuntime->dehydrate($form, $validated, $context), $preset);

        if (!empty($data['is_default'])) {
            CampaignPreset::where('id', '!=', $preset->id)->update(['is_default' => false]);
        }

        $preset->update($data);

        hexaLog('campaigns', 'preset_updated', "Campaign preset updated: {$preset->name}");

        return $this->respondAfterSave($request, $preset, "Preset '{$preset->name}' updated.");
    }

    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $preset = CampaignPreset::findOrFail($id);
        $name = $preset->name;
        $preset->delete();

        hexaLog('campaigns', 'preset_deleted', "Campaign preset deleted: {$name}");

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Preset '{$name}' deleted.",
            ]);
        }

        return redirect()->route('campaigns.presets.index')
            ->with('status', "Preset '{$name}' deleted.");
    }

    public function toggleDefault(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $preset = CampaignPreset::findOrFail($id);

        if (!$preset->is_default) {
            CampaignPreset::where('id', '!=', $preset->id)->update(['is_default' => false]);
        }

        $preset->update(['is_default' => !$preset->is_default]);

        $message = $preset->is_default
            ? "'{$preset->name}' set as default."
            : "Default cleared.";

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->route('campaigns.presets.index', ['id' => $preset->id])
            ->with('status', $message);
    }

    protected function resolveForm(string $mode, ?CampaignPreset $preset = null)
    {
        return $this->formRegistry->resolve(CampaignPresetForm::FORM_KEY, $this->formContext($mode, $preset));
    }

    protected function formContext(string $mode, ?CampaignPreset $preset = null): array
    {
        return [
            'mode' => $mode,
            'context' => $mode,
            'record' => $preset,
        ];
    }

    protected function normalizedPresetData(array $data, ?CampaignPreset $existing = null): array
    {
        $searchQueries = $this->normalizeQueries($data['search_queries'] ?? $data['keywords'] ?? []);
        $campaignInstructions = $this->nullableTrim($data['campaign_instructions'] ?? $data['ai_instructions'] ?? null);

        return [
            'user_id' => $data['user_id'] ?? $existing?->user_id ?? auth()->id(),
            'name' => trim((string) ($data['name'] ?? $existing?->name ?? 'Untitled Preset')),
            'source_method' => $data['source_method'] ?? $existing?->source_method ?? 'keyword',
            'final_article_method' => $data['final_article_method'] ?? $existing?->final_article_method ?? 'news-search',
            'search_queries' => $searchQueries,
            'keywords' => $searchQueries,
            'campaign_instructions' => $campaignInstructions,
            'ai_instructions' => $campaignInstructions,
            'local_preference' => $this->nullableTrim($data['local_preference'] ?? $existing?->local_preference ?? null),
            'genre' => $this->nullableTrim($data['genre'] ?? $existing?->genre ?? null),
            'trending_categories' => array_values(array_filter(array_map('strval', (array) ($data['trending_categories'] ?? $existing?->trending_categories ?? [])))),
            'auto_select_sources' => (bool) ($data['auto_select_sources'] ?? $existing?->auto_select_sources ?? false),
            'posts_per_run' => max(1, (int) ($data['posts_per_run'] ?? $existing?->posts_per_run ?? 1)),
            'frequency' => (string) ($data['frequency'] ?? $existing?->frequency ?? 'daily'),
            'run_at_time' => (string) ($data['run_at_time'] ?? $existing?->run_at_time ?? '09:00'),
            'drip_minutes' => max(1, (int) ($data['drip_minutes'] ?? $existing?->drip_minutes ?? 60)),
            'is_active' => (bool) ($data['is_active'] ?? $existing?->is_active ?? true),
            'is_default' => (bool) ($data['is_default'] ?? $existing?->is_default ?? false),
        ];
    }

    protected function normalizeQueries(array|string|null $queries): array
    {
        if (is_string($queries)) {
            $queries = preg_split('/\r\n|\r|\n/', $queries) ?: [];
        }

        return array_values(array_filter(array_map(
            fn ($query) => trim((string) $query),
            (array) $queries
        )));
    }

    protected function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function respondAfterSave(Request $request, CampaignPreset $preset, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'preset' => $preset->fresh(),
            ]);
        }

        return redirect()->route('campaigns.presets.index', ['id' => $preset->id])
            ->with('status', $message);
    }
}
