<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishTemplateController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;

    /**
     * @param GenericService $generic
     * @param PublishService $publishService
     */
    public function __construct(GenericService $generic, PublishService $publishService)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
    }

    /**
     * List all templates, optionally filtered by account.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishTemplate::with(['user', 'campaigns']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('article_type')) {
            $query->where('article_type', $request->input('article_type'));
        }

        $templates = $query->orderByDesc('created_at')->get();
        $users = User::orderBy('name')->get();

        return view('app-publish::templates.index', [
            'templates' => $templates,
            'users' => $users,
            'articleTypes' => config('hws-publish.article_types', []),
        ]);
    }

    /**
     * Show create template form.
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $users = User::orderBy('name')->get();

        return view('app-publish::templates.create', [
            'users' => $users,
            'articleTypes' => config('hws-publish.article_types', []),
            'aiEngines' => config('hws-publish.ai_engines', []),
            'photoSources' => config('hws-publish.photo_sources', []),
            'preselected_user_id' => $request->input('user_id'),
        ]);
    }

    /**
     * Store a new template.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'article_type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'ai_prompt' => 'nullable|string',
            'ai_engine' => 'nullable|in:anthropic,chatgpt',
            'tone' => 'nullable|string|max:100',
            'word_count_min' => 'nullable|integer|min:100',
            'word_count_max' => 'nullable|integer|min:100',
            'photos_per_article' => 'nullable|integer|min:0|max:20',
            'photo_sources' => 'nullable|array',
            'max_links' => 'nullable|integer|min:0',
            'structure' => 'nullable|array',
            'rules' => 'nullable|array',
        ]);

        $template = PublishTemplate::create($validated);

        activity_log('publish', 'template_created', "Template created: {$template->name}");

        return response()->json([
            'success' => true,
            'message' => "Template '{$template->name}' created successfully.",
            'template' => $template,
            'redirect' => route('publish.templates.show', $template->id),
        ]);
    }

    /**
     * Show a single template.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $template = PublishTemplate::with(['user', 'campaigns.site'])->findOrFail($id);

        return view('app-publish::templates.show', [
            'template' => $template,
        ]);
    }

    /**
     * Show edit form for a template.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $template = PublishTemplate::with('user')->findOrFail($id);
        $users = User::orderBy('name')->get();

        return view('app-publish::templates.edit', [
            'template' => $template,
            'users' => $users,
            'articleTypes' => config('hws-publish.article_types', []),
            'aiEngines' => config('hws-publish.ai_engines', []),
            'photoSources' => config('hws-publish.photo_sources', []),
        ]);
    }

    /**
     * Update a template.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = PublishTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'article_type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'ai_prompt' => 'nullable|string',
            'ai_engine' => 'nullable|in:anthropic,chatgpt',
            'tone' => 'nullable|string|max:100',
            'word_count_min' => 'nullable|integer|min:100',
            'word_count_max' => 'nullable|integer|min:100',
            'photos_per_article' => 'nullable|integer|min:0|max:20',
            'photo_sources' => 'nullable|array',
            'max_links' => 'nullable|integer|min:0',
            'structure' => 'nullable|array',
            'rules' => 'nullable|array',
        ]);

        $template->update($validated);

        activity_log('publish', 'template_updated', "Template updated: {$template->name}");

        return response()->json([
            'success' => true,
            'message' => "Template '{$template->name}' updated successfully.",
        ]);
    }

    /**
     * Delete a template.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $template = PublishTemplate::findOrFail($id);
        $name = $template->name;

        $template->delete();

        activity_log('publish', 'template_deleted', "Template deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Template '{$name}' deleted successfully.",
        ]);
    }
}
