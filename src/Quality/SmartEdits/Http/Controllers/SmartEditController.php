<?php

namespace hexa_app_publish\Quality\SmartEdits\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\AiSmartEditTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD for AI Smart Edit Templates — reusable prompts for article adjustments.
 */
class SmartEditController extends Controller
{
    /**
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request): View|JsonResponse
    {
        $templates = AiSmartEditTemplate::orderBy('sort_order')->get();

        if ($request->wantsJson() || $request->filled('format')) {
            return response()->json($templates);
        }

        return view('app-publish::quality.smart-edits.index', [
            'templates' => $templates,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'prompt'     => 'required|string',
            'category'   => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? AiSmartEditTemplate::max('sort_order') + 1;
        $template = AiSmartEditTemplate::create($validated);

        return response()->json(['success' => true, 'message' => "Template '{$template->name}' created.", 'template' => $template]);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = AiSmartEditTemplate::findOrFail($id);
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'prompt'     => 'required|string',
            'category'   => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $template->update($validated);

        return response()->json(['success' => true, 'message' => "Template '{$template->name}' updated."]);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $template = AiSmartEditTemplate::findOrFail($id);
        $name = $template->name;
        $template->delete();

        return response()->json(['success' => true, 'message' => "Template '{$name}' deleted."]);
    }
}
