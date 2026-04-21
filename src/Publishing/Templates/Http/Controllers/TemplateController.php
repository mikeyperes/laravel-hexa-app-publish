<?php

namespace hexa_app_publish\Publishing\Templates\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Forms\Services\FormHydrationService;
use hexa_core\Forms\Services\FormRegistryService;
use hexa_core\Forms\Services\FormValidationService;
use hexa_core\Services\GenericService;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm;
use hexa_app_publish\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TemplateController extends Controller
{
    private const JSON_CACHE_TTL_SECONDS = 600;
    private const JSON_CACHE_VERSION_KEY = 'publish:templates:json:version';

    protected GenericService $generic;
    protected PublishService $publishService;
    protected FormRegistryService $formRegistry;
    protected FormValidationService $formValidation;
    protected FormHydrationService $formHydration;

    /**
     */
    public function __construct(
        GenericService $generic,
        PublishService $publishService,
        FormRegistryService $formRegistry,
        FormValidationService $formValidation,
        FormHydrationService $formHydration
    )
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
        $this->formRegistry = $formRegistry;
        $this->formValidation = $formValidation;
        $this->formHydration = $formHydration;
    }

    /**
     * List all templates, optionally filtered by account.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson() || $request->filled('format')) {
            return response()->json($this->jsonTemplates(
                $request->integer('user_id') ?: null,
                $request->integer('account_id') ?: null,
                $request->string('article_type')->trim()->value() ?: null,
            ));
        }

        $query = PublishTemplate::with(['account', 'campaigns']);

        if ($request->filled('account_id')) {
            $query->where('publish_account_id', $request->input('account_id'));
        }

        if ($request->filled('article_type')) {
            $query->where('article_type', $request->input('article_type'));
        }

        $templates = $query->orderByDesc('created_at')->get();

        $accounts = PublishAccount::orderBy('name')->get();

        return view('app-publish::publishing.templates.index', [
            'templates' => $templates,
            'accounts' => $accounts,
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
        $form = $this->resolveArticlePresetForm('create');

        return view('app-publish::publishing.templates.create', [
            'form' => $form,
            'formValues' => ArticlePresetForm::values(null, [
                'publish_account_id' => $request->input('account_id'),
            ]),
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
        $form = $this->resolveArticlePresetForm('create');
        $payload = $this->normalizedFormPayload($request, $form);
        $validated = $this->formValidation->validate($payload, $form, ['mode' => 'create', 'context' => 'create']);
        $data = $this->formHydration->dehydrate($form, $validated);
        $data['status'] = $this->validatedStatus($request, 'draft');
        $data['ai_engine'] = $data['spinning_agent'] ?? ($data['ai_engine'] ?? null);
        $data['photos_per_article'] = max(
            (int) ($data['inline_photo_min'] ?? 2),
            (int) ($data['inline_photo_max'] ?? 3)
        );
        $data['photo_sources'] = array_values((array) ($data['photo_sources'] ?? config('hws-publish.photo_sources', [])));

        if (!empty($data['is_default'])) {
            PublishTemplate::where('publish_account_id', $data['publish_account_id'])->update(['is_default' => false]);
        }

        $template = PublishTemplate::create($data);
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'template_created', "Template created: {$template->name}");

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
        $template = PublishTemplate::with(['account', 'campaigns.site'])->findOrFail($id);

        return view('app-publish::publishing.templates.show', [
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
        $template = PublishTemplate::with('account')->findOrFail($id);
        $form = $this->resolveArticlePresetForm('edit', $template);

        return view('app-publish::publishing.templates.edit', [
            'template' => $template,
            'form' => $form,
            'formValues' => ArticlePresetForm::values($template),
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
        $form = $this->resolveArticlePresetForm('edit', $template);
        $payload = $this->normalizedFormPayload($request, $form);
        $validated = $this->formValidation->validate($payload, $form, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $template,
        ]);
        $data = $this->formHydration->dehydrate($form, $validated);
        $data['status'] = $this->validatedStatus($request, $template->status ?? 'draft');
        $data['ai_engine'] = $data['spinning_agent'] ?? ($data['ai_engine'] ?? $template->ai_engine);
        $data['photos_per_article'] = max(
            (int) ($data['inline_photo_min'] ?? $template->inline_photo_min ?? 2),
            (int) ($data['inline_photo_max'] ?? $template->inline_photo_max ?? 3)
        );
        $data['photo_sources'] = array_values((array) ($data['photo_sources'] ?? $template->photo_sources ?? config('hws-publish.photo_sources', [])));

        if (!empty($data['is_default'])) {
            $accountId = $data['publish_account_id'] ?? $template->publish_account_id;
            PublishTemplate::where('publish_account_id', $accountId)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($data);
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'template_updated', "Template updated: {$template->name}");

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
        $this->bumpJsonCacheVersion();

        hexaLog('publish', 'template_deleted', "Template deleted: {$name}");

        return response()->json([
            'success' => true,
            'message' => "Template '{$name}' deleted successfully.",
        ]);
    }

    protected function resolveArticlePresetForm(string $mode, ?PublishTemplate $template = null)
    {
        return $this->formRegistry->resolve(ArticlePresetForm::FORM_KEY, [
            'mode' => $mode,
            'context' => $mode,
            'record' => $template,
        ]);
    }

    protected function normalizedFormPayload(Request $request, $form): array
    {
        $payload = $request->all();

        foreach ($form->getFields() as $field) {
            if (($field->isMultiple() || in_array($field->type(), ['checkbox_group', 'multiselect', 'array'], true))
                && !$request->has($field->name())
            ) {
                $payload[$field->name()] = [];
            }

            if (in_array($field->type(), ['boolean', 'toggle'], true) && !$request->has($field->name())) {
                $payload[$field->name()] = false;
            }
        }

        return $payload;
    }

    protected function validatedStatus(Request $request, string $default = 'draft'): string
    {
        $status = $request->input('status', $default);

        return in_array($status, ['draft', 'active'], true) ? $status : $default;
    }

    private function jsonTemplates(?int $userId = null, ?int $accountId = null, ?string $articleType = null): array
    {
        $cacheKey = implode(':', [
            'publish',
            'templates',
            'json',
            'v' . $this->jsonCacheVersion(),
            'user',
            $userId ?: 'all',
            'account',
            $accountId ?: 'all',
            'type',
            $articleType ?: 'all',
        ]);

        return Cache::remember($cacheKey, now()->addSeconds(self::JSON_CACHE_TTL_SECONDS), function () use ($userId, $accountId, $articleType) {
            $query = PublishTemplate::query()
                ->select([
                    'id',
                    'publish_account_id',
                    'name',
                    'status',
                    'is_default',
                    'article_type',
                    'description',
                    'ai_prompt',
                    'headline_rules',
                    'ai_engine',
                    'tone',
                    'word_count_min',
                    'word_count_max',
                    'photos_per_article',
                    'photo_sources',
                    'max_links',
                    'search_online_for_additional_context',
                    'online_search_model_fallback',
                    'scrape_ai_model_fallback',
                    'spin_model_fallback',
                    'h2_notation',
                    'inline_photo_min',
                    'inline_photo_max',
                    'featured_image_required',
                    'featured_image_must_be_landscape',
                    'structure',
                    'rules',
                    'searching_agent',
                    'scraping_agent',
                    'spinning_agent',
                    'created_at',
                    'updated_at',
                ])
                ->with(['account:id,name,owner_user_id'])
                ->orderByDesc('updated_at');

            if ($accountId) {
                $query->where('publish_account_id', $accountId);
            }

            if ($articleType) {
                $query->where('article_type', $articleType);
            }

            if ($userId) {
                $query->where(function ($accountScope) use ($userId) {
                    $accountScope->whereNull('publish_account_id')
                        ->orWhereHas('account', function ($query) use ($userId) {
                            $query->where('owner_user_id', $userId)
                                ->orWhereHas('users', fn ($users) => $users->where('user_id', $userId));
                        });
                });
            }

            return $query->get()->map(fn (PublishTemplate $template) => $template->toArray())->all();
        });
    }

    private function jsonCacheVersion(): int
    {
        return (int) Cache::get(self::JSON_CACHE_VERSION_KEY, 1);
    }

    private function bumpJsonCacheVersion(): void
    {
        if (!Cache::has(self::JSON_CACHE_VERSION_KEY)) {
            Cache::forever(self::JSON_CACHE_VERSION_KEY, 2);
            return;
        }

        Cache::increment(self::JSON_CACHE_VERSION_KEY);
    }
}
