<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use Illuminate\Http\Request;

class PublishPipelineApiContext
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $stack = [];

    /**
     * @template TReturn
     *
     * @param  array<string, mixed>  $context
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withContext(array $context, callable $callback): mixed
    {
        $this->stack[] = $this->normalizeContext($context);

        try {
            return $callback();
        } finally {
            array_pop($this->stack);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $requestContext = $this->resolveFromRequest();
        $stackContext = end($this->stack);

        if (!is_array($stackContext)) {
            return $requestContext;
        }

        return array_merge($requestContext, array_filter($stackContext, static fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveFromRequest(?Request $request = null): array
    {
        $request ??= request();
        if (!$request instanceof Request) {
            return [];
        }

        $routeName = (string) optional($request->route())->getName();
        $runTrace = trim((string) ($request->headers->get('X-Pipeline-Run-Trace')
            ?: $request->headers->get('X-Pipeline-Client-Trace')
            ?: $request->input('client_trace')
            ?: ''));
        $requestTrace = trim((string) ($request->headers->get('X-Pipeline-Client-Trace') ?: ''));
        $draftId = (int) ($request->headers->get('X-Pipeline-Draft-Id')
            ?: $request->input('draft_id')
            ?: $request->query('draft_id')
            ?: $request->input('id')
            ?: $request->query('id')
            ?: $request->route('id')
            ?: $request->route('draft')
            ?: 0);

        $isPipelineRequest = str_starts_with($routeName, 'publish.pipeline');
        if ($draftId <= 0 && !$isPipelineRequest && $runTrace === '') {
            return [];
        }

        $user = auth()->user();

        return $this->normalizeContext([
            'draft_id' => $draftId > 0 ? $draftId : null,
            'client_trace' => $runTrace !== '' ? $runTrace : null,
            'trace_id' => $requestTrace !== '' ? $requestTrace : null,
            'debug_enabled' => $request->boolean('debug_mode')
                || $request->headers->get('X-Pipeline-Debug') === '1'
                || $request->query('debug') === '1',
            'workflow_type' => $request->input('article_type') ?: null,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'source' => $isPipelineRequest ? 'http_request' : 'implicit_request',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $draftId = (int) ($context['draft_id'] ?? 0);
        $userId = (int) ($context['user_id'] ?? 0);

        return [
            'draft_id' => $draftId > 0 ? $draftId : null,
            'client_trace' => $this->nullableString($context['client_trace'] ?? null),
            'trace_id' => $this->nullableString($context['trace_id'] ?? null),
            'debug_enabled' => (bool) ($context['debug_enabled'] ?? false),
            'workflow_type' => $this->nullableString($context['workflow_type'] ?? null),
            'user_id' => $userId > 0 ? $userId : null,
            'user_name' => $this->nullableString($context['user_name'] ?? null),
            'source' => $this->nullableString($context['source'] ?? null),
            'operation_type' => $this->nullableString($context['operation_type'] ?? null),
            'operation_id' => isset($context['operation_id']) ? (int) $context['operation_id'] : null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
