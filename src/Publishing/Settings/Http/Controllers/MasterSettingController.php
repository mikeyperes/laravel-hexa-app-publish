<?php

namespace hexa_app_publish\Publishing\Settings\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\Setting;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishMasterSettingController — CRUD for system-wide publishing guidelines.
 */
class MasterSettingController extends Controller
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

        $aiPrompts = PublishMasterSetting::whereIn('type', [
            'ai_system_prompt', 'ai_html_format', 'ai_spin_instruction',
            'ai_change_instruction', 'ai_metadata_prompt',
        ])->orderBy('sort_order')->get();

        $pressReleaseSources = \hexa_app_publish\Publishing\Sites\Models\PublishSite::where('is_press_release_source', true)
            ->orderBy('name')
            ->get();
        $allSites = \hexa_app_publish\Publishing\Sites\Models\PublishSite::where('status', 'connected')
            ->orderBy('name')
            ->get();

        return view('app-publish::publishing.settings.index', [
            'wordpressGuidelines'  => $wordpressGuidelines,
            'spinningGuidelines'   => $spinningGuidelines,
            'aiPrompts'            => $aiPrompts,
            'pressReleaseSources'  => $pressReleaseSources,
            'allSites'             => $allSites,
        ]);
    }

    /**
     * Toggle a site's press release source flag.
     *
     * @param int $site
     * @return \Illuminate\Http\JsonResponse
     */
    public function togglePressReleaseSource(int $site): \Illuminate\Http\JsonResponse
    {
        $site = \hexa_app_publish\Publishing\Sites\Models\PublishSite::findOrFail($site);
        $site->is_press_release_source = !$site->is_press_release_source;
        $site->save();

        return response()->json([
            'success' => true,
            'message' => $site->is_press_release_source ? 'Added as press release source.' : 'Removed as press release source.',
            'is_press_release_source' => $site->is_press_release_source,
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
            'type'       => 'required|in:wordpress_guidelines,spinning_guidelines,ai_system_prompt,ai_html_format,ai_spin_instruction,ai_change_instruction,ai_metadata_prompt',
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

    /**
     * Save the master spin prompt.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function savePrompt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $existing = PublishMasterSetting::where('type', 'master_spin_prompt')->first();
        if ($existing) {
            $existing->update(['content' => $validated['content']]);
        } else {
            PublishMasterSetting::create([
                'name' => 'Master Spin Prompt',
                'type' => 'master_spin_prompt',
                'content' => $validated['content'],
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        hexaLog('publish', 'prompt_updated', 'Master spin prompt updated');

        return response()->json(['success' => true, 'message' => 'Prompt saved.']);
    }

    /**
     * Save a key/value setting via Setting::setValue().
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveSetting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'setting_key'   => 'required|string|max:255',
            'setting_value' => 'required|string|max:1000',
        ]);

        Setting::setValue($validated['setting_key'], $validated['setting_value']);

        hexaLog('publish', 'setting_saved', "Setting updated: {$validated['setting_key']} = {$validated['setting_value']}");

        return response()->json(['success' => true, 'message' => 'Setting saved.']);
    }
}
