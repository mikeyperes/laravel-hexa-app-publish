<?php

namespace hexa_app_publish\Publishing\Campaigns\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset;
use hexa_app_publish\Publishing\Campaigns\Services\NewsDiscoveryOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CampaignPresetController — CRUD for campaign presets.
 */
class CampaignPresetController extends Controller
{
    public function __construct(protected NewsDiscoveryOptionsService $newsOptions)
    {
    }

    /**
     * List all campaign presets.
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request): View|JsonResponse
    {
        $presets = CampaignPreset::with('user')->orderByDesc('updated_at')->paginate(50);
        $newsCategories = $this->newsOptions->newsCategories();
        $discoveryModes = $this->newsOptions->discoveryModes();
        $finalArticleMethods = $this->newsOptions->finalArticleMethods();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $presets,
                'news_categories' => $newsCategories,
                'discovery_modes' => $discoveryModes,
                'final_article_methods' => $finalArticleMethods,
            ]);
        }

        $editPreset = $request->filled('id') ? CampaignPreset::find($request->input('id')) : null;

        return view('app-publish::publishing.campaigns.presets.index', [
            'presets' => $presets,
            'newsCategories' => $newsCategories,
            'discoveryModes' => $discoveryModes,
            'finalArticleMethods' => $finalArticleMethods,
            'editPreset' => $editPreset,
        ]);
    }

    /**
     * Store a new campaign preset.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'final_article_method' => 'required|in:' . implode(',', $this->newsOptions->finalArticleMethods()),
            'keywords' => 'nullable|array',
            'local_preference' => 'nullable|string|max:255',
            'source_method' => 'required|in:' . implode(',', $this->newsOptions->discoveryModes()),
            'genre' => 'nullable|string|max:100',
            'trending_categories' => 'nullable|array',
            'auto_select_sources' => 'nullable|boolean',
            'ai_instructions' => 'nullable|string|max:5000',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['auto_select_sources'] = $validated['auto_select_sources'] ?? false;

        $preset = CampaignPreset::create($validated);

        hexaLog('campaigns', 'preset_created', "Campaign preset created: {$preset->name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$preset->name}' created.",
            'preset' => $preset,
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
        $preset = CampaignPreset::findOrFail($id);
        return response()->json(['success' => true, 'preset' => $preset]);
    }

    /**
     * Update a campaign preset.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $preset = CampaignPreset::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'final_article_method' => 'required|in:' . implode(',', $this->newsOptions->finalArticleMethods()),
            'keywords' => 'nullable|array',
            'local_preference' => 'nullable|string|max:255',
            'source_method' => 'required|in:' . implode(',', $this->newsOptions->discoveryModes()),
            'genre' => 'nullable|string|max:100',
            'trending_categories' => 'nullable|array',
            'auto_select_sources' => 'nullable|boolean',
            'ai_instructions' => 'nullable|string|max:5000',
        ]);

        $preset->update($validated);

        hexaLog('campaigns', 'preset_updated', "Campaign preset updated: {$preset->name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$preset->name}' updated.",
            'preset' => $preset,
        ]);
    }

    /**
     * Delete a campaign preset.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $preset = CampaignPreset::findOrFail($id);
        $name = $preset->name;
        $preset->delete();

        hexaLog('campaigns', 'preset_deleted', "Campaign preset deleted: {$name}");

        return response()->json(['success' => true, 'message' => "Preset '{$name}' deleted."]);
    }

    /**
     * Toggle default status.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function toggleDefault(int $id): JsonResponse
    {
        $preset = CampaignPreset::findOrFail($id);
        if (!$preset->is_default) {
            CampaignPreset::where('is_default', true)->update(['is_default' => false]);
        }
        $preset->update(['is_default' => !$preset->is_default]);

        return response()->json(['success' => true, 'message' => $preset->is_default ? "'{$preset->name}' set as default." : "Default cleared."]);
    }
}
