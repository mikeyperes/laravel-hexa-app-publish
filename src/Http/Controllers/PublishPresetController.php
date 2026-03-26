<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\ListRegistry\Models\ListItem;
use hexa_app_publish\Models\PublishPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishPresetController — CRUD for article publishing presets.
 */
class PublishPresetController extends Controller
{
    /**
     * List presets, optionally filtered by user. Load list values for dropdowns.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = PublishPreset::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $presets = $query->orderByDesc('updated_at')->get();

        if ($request->wantsJson() || $request->filled('format')) {
            return response()->json($presets);
        }

        $editingPreset = null;
        if ($request->filled('edit')) {
            $editingPreset = PublishPreset::with('user')->find($request->input('edit'));
        }

        return view('app-publish::publishing.presets.index', [
            'presets'          => $presets,
            'editingPreset'    => $editingPreset,
            'tones'            => ListItem::getValues('tones'),
            'imagePreferences' => ListItem::getValues('image_preferences'),
            'imageLayouts'     => ListItem::getValues('image_layout_rules'),
            'publishActions'   => [
                'publish_immediate' => 'Publish Immediately',
                'draft_local'       => 'Save as Local Draft',
                'draft_wordpress'   => 'Save as WordPress Draft',
                'schedule'          => 'Schedule for Later',
            ],
        ]);
    }

    /**
     * Create a new preset.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'status'                 => 'nullable|in:draft,active',
            'is_default'             => 'nullable|boolean',
            'user_id'                => 'nullable|integer|exists:users,id',
            'default_site_id'        => 'nullable|integer',
            'follow_links'           => 'nullable|in:follow,nofollow',
            'tone'                   => 'nullable|string|max:100',
            'image_preference'       => 'nullable|string|max:100',
            'default_publish_action' => 'nullable|in:publish_immediate,draft_local,draft_wordpress,schedule',
            'default_category_count' => 'nullable|integer|min:0|max:20',
            'default_tag_count'      => 'nullable|integer|min:0|max:50',
            'image_layout'           => 'nullable|string|max:100',
        ]);

        $validated['user_id'] = $validated['user_id'] ?? auth()->id();
        $validated['status'] = $validated['status'] ?? 'draft';

        if (!empty($validated['is_default'])) {
            PublishPreset::where('user_id', $validated['user_id'])->update(['is_default' => false]);
        }

        $preset = PublishPreset::create($validated);

        hexaLog('publish', 'preset_created', "Preset created: {$preset->name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$preset->name}' created successfully.",
            'preset'  => $preset->load('user'),
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
            'preset'  => $preset,
        ]);
    }

    /**
     * Update a preset.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $preset = PublishPreset::findOrFail($id);

        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'status'                 => 'nullable|in:draft,active',
            'is_default'             => 'nullable|boolean',
            'user_id'                => 'nullable|integer|exists:users,id',
            'default_site_id'        => 'nullable|integer',
            'follow_links'           => 'nullable|in:follow,nofollow',
            'tone'                   => 'nullable|string|max:100',
            'image_preference'       => 'nullable|string|max:100',
            'default_publish_action' => 'nullable|in:publish_immediate,draft_local,draft_wordpress,schedule',
            'default_category_count' => 'nullable|integer|min:0|max:20',
            'default_tag_count'      => 'nullable|integer|min:0|max:50',
            'image_layout'           => 'nullable|string|max:100',
        ]);

        if (!empty($validated['is_default'])) {
            PublishPreset::where('user_id', $preset->user_id)->where('id', '!=', $preset->id)->update(['is_default' => false]);
        }

        $preset->update($validated);

        hexaLog('publish', 'preset_updated', "Preset updated: {$preset->name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$preset->name}' updated successfully.",
        ]);
    }

    /**
     * Toggle a preset as default for its user. Unsets all other defaults for that user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function toggleDefault(int $id): JsonResponse
    {
        $preset = PublishPreset::findOrFail($id);

        if ($preset->is_default) {
            $preset->update(['is_default' => false]);
            hexaLog('publish', 'preset_default_removed', "Default removed: {$preset->name}");
            return response()->json(['success' => true, 'message' => "'{$preset->name}' is no longer the default.", 'is_default' => false]);
        }

        // Unset all other defaults for this user
        PublishPreset::where('user_id', $preset->user_id)->where('id', '!=', $preset->id)->update(['is_default' => false]);
        $preset->update(['is_default' => true]);

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

        hexaLog('publish', 'preset_deleted', "Preset deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$name}' deleted successfully.",
        ]);
    }
}
