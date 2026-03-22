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
    public function index(Request $request): View
    {
        $query = PublishPreset::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $presets = $query->orderByDesc('updated_at')->get();

        return view('app-publish::publishing.presets.index', [
            'presets'          => $presets,
            'articleFormats'   => ListItem::getValues('article_formats'),
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
            'user_id'                => 'nullable|integer|exists:users,id',
            'default_site_id'        => 'nullable|integer',
            'follow_links'           => 'nullable|in:follow,nofollow',
            'article_format'         => 'nullable|string|max:100',
            'tone'                   => 'nullable|string|max:100',
            'image_preference'       => 'nullable|string|max:100',
            'default_publish_action' => 'nullable|in:publish_immediate,draft_local,draft_wordpress,schedule',
            'default_category_count' => 'nullable|integer|min:0|max:20',
            'default_tag_count'      => 'nullable|integer|min:0|max:50',
            'image_layout'           => 'nullable|string|max:100',
        ]);

        $validated['user_id'] = $validated['user_id'] ?? auth()->id();

        $preset = PublishPreset::create($validated);

        activity_log('publish', 'preset_created', "Preset created: {$preset->name}");

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
            'user_id'                => 'nullable|integer|exists:users,id',
            'default_site_id'        => 'nullable|integer',
            'follow_links'           => 'nullable|in:follow,nofollow',
            'article_format'         => 'nullable|string|max:100',
            'tone'                   => 'nullable|string|max:100',
            'image_preference'       => 'nullable|string|max:100',
            'default_publish_action' => 'nullable|in:publish_immediate,draft_local,draft_wordpress,schedule',
            'default_category_count' => 'nullable|integer|min:0|max:20',
            'default_tag_count'      => 'nullable|integer|min:0|max:50',
            'image_layout'           => 'nullable|string|max:100',
        ]);

        $preset->update($validated);

        activity_log('publish', 'preset_updated', "Preset updated: {$preset->name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$preset->name}' updated successfully.",
        ]);
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

        activity_log('publish', 'preset_deleted', "Preset deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Preset '{$name}' deleted successfully.",
        ]);
    }
}
