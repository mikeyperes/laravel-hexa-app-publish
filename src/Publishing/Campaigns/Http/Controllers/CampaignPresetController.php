<?php

namespace hexa_app_publish\Publishing\Campaigns\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CampaignPresetController — CRUD for campaign presets.
 */
class CampaignPresetController extends Controller
{
    /**
     * List all campaign presets.
     *
     * @param Request $request
     * @return View|JsonResponse
     */
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

        $editPreset = $request->filled('id') ? CampaignPreset::find($request->input('id')) : null;

        return view('app-publish::publishing.campaigns.presets.index', [
            'presets' => $presets,
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
            'search_queries' => 'nullable|array',
            'campaign_instructions' => 'nullable|string|max:5000',
            'posts_per_run' => 'nullable|integer|min:1|max:50',
            'frequency' => 'nullable|in:hourly,daily,weekly,monthly',
            'run_at_time' => 'nullable|string|max:10',
            'drip_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['posts_per_run'] = $validated['posts_per_run'] ?? 1;
        $validated['frequency'] = $validated['frequency'] ?? 'daily';
        $validated['drip_minutes'] = $validated['drip_minutes'] ?? 60;
        $validated['keywords'] = $validated['search_queries'] ?? [];
        $validated['ai_instructions'] = $validated['campaign_instructions'] ?? null;

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
            'search_queries' => 'nullable|array',
            'campaign_instructions' => 'nullable|string|max:5000',
            'posts_per_run' => 'nullable|integer|min:1|max:50',
            'frequency' => 'nullable|in:hourly,daily,weekly,monthly',
            'run_at_time' => 'nullable|string|max:10',
            'drip_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $validated['posts_per_run'] = $validated['posts_per_run'] ?? 1;
        $validated['frequency'] = $validated['frequency'] ?? 'daily';
        $validated['drip_minutes'] = $validated['drip_minutes'] ?? 60;
        $validated['keywords'] = $validated['search_queries'] ?? [];
        $validated['ai_instructions'] = $validated['campaign_instructions'] ?? null;

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
