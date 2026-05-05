<?php

namespace hexa_app_publish\Publishing\Templates\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Forms\Services\FormRegistryService;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TemplateController extends Controller
{
    private const JSON_CACHE_TTL_SECONDS = 600;
    private const JSON_CACHE_VERSION_KEY = 'publish:templates:json:version';

    public function __construct(
        private PublishAccessService $access,
        private FormRegistryService $formRegistry,
        private FormRuntimeService $formRuntime
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson() || $request->filled('format')) {
            return response()->json($this->jsonTemplates(
                $request->user(),
                $request->integer('account_id') ?: null,
                $request->string('article_type')->trim()->value() ?: null,
            ));
        }

        $query = $this->access->templateQuery($request->user())->with(['account', 'campaigns']);

        if ($request->filled('account_id')) {
            $query->where('publish_account_id', (int) $request->input('account_id'));
        }

        if ($request->filled('article_type')) {
            $query->where('article_type', $request->input('article_type'));
        }

        $templates = $query->orderByDesc('created_at')->get();
        $accounts = $this->access->accountQuery($request->user())->orderBy('name')->get();

        return view('app-publish::publishing.templates.index', [
            'templates' => $templates,
            'accounts' => $accounts,
            'articleTypes' => config('hws-publish.article_types', []),
        ]);
    }

    public function create(Request $request): View
    {
        $form = $this->resolveArticlePresetForm('create');

        return view('app-publish::publishing.templates.create', [
            'form' => $form,
            'formValues' => ArticlePresetForm::values(null, [
                'publish_account_id' => $this->resolveRequestedAccountId($request, $request->integer('account_id') ?: null),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->resolveArticlePresetForm('create');
        $validated = $this->formRuntime->validate($form, $request, ['mode' => 'create', 'context' => 'create']);
        $data = $this->formRuntime->dehydrate($form, $validated, ['mode' => 'create', 'context' => 'create']);
        $data['status'] = $this->validatedStatus($request, 'draft');
        $data['ai_engine'] = $data['spinning_agent'] ?? ($data['ai_engine'] ?? null);
        $data['photos_per_article'] = max(
            (int) ($data['inline_photo_min'] ?? 2),
            (int) ($data['inline_photo_max'] ?? 3)
        );
        $data['photo_sources'] = array_values((array) ($data['photo_sources'] ?? config('hws-publish.photo_sources', [])));
        $data['publish_account_id'] = $this->resolveRequestedAccountId($request, isset($data['publish_account_id']) ? (int) $data['publish_account_id'] : null);
        if (!$this->access->isAdmin($request->user())) {
            $data['user_id'] = $request->user()->id;
        }

        if (!empty($data['is_default']) && !empty($data['publish_account_id'])) {
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

    public function show(Request $request, int $id): View
    {
        $template = $this->access->resolveTemplateOrFail($request->user(), $id)->load(['account', 'campaigns.site']);

        return view('app-publish::publishing.templates.show', [
            'template' => $template,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $template = $this->access->resolveTemplateOrFail($request->user(), $id)->load('account');
        $form = $this->resolveArticlePresetForm('edit', $template);

        return view('app-publish::publishing.templates.edit', [
            'template' => $template,
            'form' => $form,
            'formValues' => ArticlePresetForm::values($template),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = $this->access->resolveTemplateOrFail($request->user(), $id);
        $form = $this->resolveArticlePresetForm('edit', $template);
        $validated = $this->formRuntime->validate($form, $request, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $template,
        ]);
        $data = $this->formRuntime->dehydrate($form, $validated, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $template,
        ]);
        $data['status'] = $this->validatedStatus($request, $template->status ?? 'draft');
        $data['ai_engine'] = $data['spinning_agent'] ?? ($data['ai_engine'] ?? $template->ai_engine);
        $data['photos_per_article'] = max(
            (int) ($data['inline_photo_min'] ?? $template->inline_photo_min ?? 2),
            (int) ($data['inline_photo_max'] ?? $template->inline_photo_max ?? 3)
        );
        $data['photo_sources'] = array_values((array) ($data['photo_sources'] ?? $template->photo_sources ?? config('hws-publish.photo_sources', [])));
        $data['publish_account_id'] = $this->resolveRequestedAccountId($request, isset($data['publish_account_id']) ? (int) $data['publish_account_id'] : (int) $template->publish_account_id);
        if (!$this->access->isAdmin($request->user())) {
            $data['user_id'] = $template->user_id ?: $request->user()->id;
        }

        if (!empty($data['is_default']) && !empty($data['publish_account_id'])) {
            PublishTemplate::where('publish_account_id', $data['publish_account_id'])
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

    public function destroy(Request $request, int $id): JsonResponse
    {
        $template = $this->access->resolveTemplateOrFail($request->user(), $id);
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

    protected function validatedStatus(Request $request, string $default = 'draft'): string
    {
        $status = $request->input('status', $default);

        return in_array($status, ['draft', 'active'], true) ? $status : $default;
    }

    private function jsonTemplates($user, ?int $accountId = null, ?string $articleType = null): array
    {
        $userId = $user?->id ?: 0;
        $cacheKey = implode(':', [
            'publish',
            'templates',
            'json',
            'v' . $this->jsonCacheVersion(),
            'user',
            $userId ?: 'guest',
            'account',
            $accountId ?: 'all',
            'type',
            $articleType ?: 'all',
        ]);

        return Cache::remember($cacheKey, now()->addSeconds(self::JSON_CACHE_TTL_SECONDS), function () use ($user, $accountId, $articleType) {
            $query = $this->access->templateQuery($user)
                ->select([
                    'id',
                    'user_id',
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

    private function resolveRequestedAccountId(Request $request, ?int $requestedAccountId): ?int
    {
        if ($this->access->isAdmin($request->user())) {
            return $requestedAccountId;
        }

        $accountIds = $this->access->accessibleAccountIds($request->user());
        abort_unless($accountIds !== [], 403, 'No publish account is assigned to this user.');

        if ($requestedAccountId !== null) {
            abort_unless(in_array($requestedAccountId, $accountIds, true), 403);
            return $requestedAccountId;
        }

        return $accountIds[0];
    }
}
