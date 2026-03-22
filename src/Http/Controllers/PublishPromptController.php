<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishPrompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishPromptController — CRUD for reusable AI prompts.
 */
class PublishPromptController extends Controller
{
    /**
     * List prompts, optionally filtered by user.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishPrompt::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $prompts = $query->orderByDesc('updated_at')->get();

        return view('app-publish::publishing.prompts.index', [
            'prompts' => $prompts,
        ]);
    }

    /**
     * Create a new prompt.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
            'content' => 'required|string',
        ]);

        $validated['user_id'] = $validated['user_id'] ?? auth()->id();

        $prompt = PublishPrompt::create($validated);

        activity_log('publish', 'prompt_created', "Prompt created: {$prompt->name}");

        return response()->json([
            'success' => true,
            'message' => "Prompt '{$prompt->name}' created successfully.",
            'prompt'  => $prompt->load('user'),
        ]);
    }

    /**
     * Show a single prompt.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $prompt = PublishPrompt::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'prompt'  => $prompt,
        ]);
    }

    /**
     * Update a prompt.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $prompt = PublishPrompt::findOrFail($id);

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
            'content' => 'required|string',
        ]);

        $prompt->update($validated);

        activity_log('publish', 'prompt_updated', "Prompt updated: {$prompt->name}");

        return response()->json([
            'success' => true,
            'message' => "Prompt '{$prompt->name}' updated successfully.",
        ]);
    }

    /**
     * Delete a prompt.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $prompt = PublishPrompt::findOrFail($id);
        $name = $prompt->name;

        $prompt->delete();

        activity_log('publish', 'prompt_deleted', "Prompt deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Prompt '{$name}' deleted successfully.",
        ]);
    }
}
