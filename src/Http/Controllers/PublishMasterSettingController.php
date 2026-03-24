<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishMasterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishMasterSettingController — CRUD for system-wide publishing guidelines.
 */
class PublishMasterSettingController extends Controller
{
    /**
     * Show all settings grouped by type.
     *
     * @return View
     */
    public function index(): View
    {
        $wordpressGuidelines = PublishMasterSetting::wordpressGuidelines()
            ->orderBy('sort_order')
            ->get();

        $spinningGuidelines = PublishMasterSetting::spinningGuidelines()
            ->orderBy('sort_order')
            ->get();

        return view('app-publish::publishing.settings.index', [
            'wordpressGuidelines' => $wordpressGuidelines,
            'spinningGuidelines'  => $spinningGuidelines,
        ]);
    }

    /**
     * Create a new setting document.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'type'       => 'required|in:wordpress_guidelines,spinning_guidelines',
            'content'    => 'nullable|string',
            'is_active'  => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $setting = PublishMasterSetting::create($validated);

        hexaLog('publish', 'master_setting_created', "Master setting created: {$setting->name} ({$setting->type})");

        return response()->json([
            'success' => true,
            'message' => "Setting '{$setting->name}' created successfully.",
            'setting' => $setting,
        ]);
    }

    /**
     * Update a setting document.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $setting = PublishMasterSetting::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'content'    => 'nullable|string',
            'is_active'  => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $setting->update($validated);

        hexaLog('publish', 'master_setting_updated', "Master setting updated: {$setting->name}");

        return response()->json([
            'success' => true,
            'message' => "Setting '{$setting->name}' updated successfully.",
        ]);
    }

    /**
     * Delete a setting document.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $setting = PublishMasterSetting::findOrFail($id);
        $name = $setting->name;

        $setting->delete();

        hexaLog('publish', 'master_setting_deleted', "Master setting deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Setting '{$name}' deleted successfully.",
        ]);
    }
}
