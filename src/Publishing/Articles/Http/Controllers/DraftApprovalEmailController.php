<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishArticleApprovalEmail;
use hexa_app_publish\Publishing\Articles\Services\DraftApprovalEmailService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftApprovalEmailController extends Controller
{
    public function __construct(
        private PublishAccessService $access,
        private DraftApprovalEmailService $approvalEmails,
        private PipelineStateService $pipelineState,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $article = $this->resolveArticle($request, $id);

        return response()->json([
            'success' => true,
            'article' => $this->serializeArticle($article),
            'composer' => $this->composerConfig($article),
            'logs' => $this->serializeLogs($article),
        ]);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $article = $this->resolveArticle($request, $id);
        $input = $request->validate([
            'to' => 'nullable|string|max:255',
            'cc' => 'nullable|string|max:1000',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|string|max:255',
            'reply_to' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'image_mode' => 'nullable|string|max:40',
        ]);

        $this->persistComposerState($article, $input);
        $preview = $this->approvalEmails->preview($article, $input);

        return response()->json([
            'success' => true,
            'composer' => array_merge($this->composerConfig($article), [
                'to' => (string) ($input['to'] ?? $preview['config']['to'] ?? ''),
                'cc' => (string) ($input['cc'] ?? $preview['config']['cc'] ?? ''),
                'from_name' => (string) ($input['from_name'] ?? $preview['config']['from_name'] ?? ''),
                'from_email' => (string) ($input['from_email'] ?? $preview['config']['from_email'] ?? ''),
                'reply_to' => (string) ($input['reply_to'] ?? $preview['config']['reply_to'] ?? ''),
                'subject' => (string) ($input['subject'] ?? $preview['config']['subject'] ?? ''),
                'image_mode' => (string) ($input['image_mode'] ?? $preview['config']['image_mode'] ?? 'links'),
            ]),
            'preview_html' => $preview['preview_html'],
            'warnings' => $preview['warnings'],
            'snapshot' => $preview['snapshot'],
            'headers' => $preview['headers'],
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $article = $this->resolveArticle($request, $id);
        $input = $request->validate([
            'to' => 'required|string|max:255',
            'cc' => 'nullable|string|max:1000',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|string|max:255',
            'reply_to' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'image_mode' => 'nullable|string|max:40',
        ]);

        $this->persistComposerState($article, $input);
        $email = $this->approvalEmails->send($article, $input, $request->user());

        return response()->json([
            'success' => $email->status === 'sent',
            'message' => $email->status === 'sent'
                ? 'Draft approval email sent.'
                : ($email->error ?: 'Draft approval email failed.'),
            'email' => $this->serializeLog($email),
            'logs' => $this->serializeLogs($article),
        ], $email->status === 'sent' ? 200 : 422);
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        $article = $this->resolveArticle($request, $id);

        return response()->json([
            'success' => true,
            'logs' => $this->serializeLogs($article),
        ]);
    }

    private function resolveArticle(Request $request, int $id): PublishArticle
    {
        $article = $this->access->resolveArticleOrFail($request->user(), $id);
        $article->loadMissing(['site', 'creator', 'pipelineState', 'latestApprovalEmail.sender']);

        return $article;
    }

    private function composerConfig(PublishArticle $article): array
    {
        $config = $this->approvalEmails->defaultComposerConfig($article);
        $payload = $this->pipelineState->payload($article);
        $map = [
            'to' => 'approvalEmailTo',
            'cc' => 'approvalEmailCc',
            'from_name' => 'approvalEmailFromName',
            'from_email' => 'approvalEmailFromEmail',
            'reply_to' => 'approvalEmailReplyTo',
            'subject' => 'approvalEmailSubject',
            'image_mode' => 'approvalEmailImageMode',
        ];

        foreach ($map as $configKey => $payloadKey) {
            if (array_key_exists($payloadKey, $payload) && $payload[$payloadKey] !== null) {
                $config[$configKey] = is_string($payload[$payloadKey])
                    ? $payload[$payloadKey]
                    : (string) $payload[$payloadKey];
            }
        }

        return $config;
    }

    private function persistComposerState(PublishArticle $article, array $input): void
    {
        $payload = $this->pipelineState->payload($article);
        $payload['approvalEmailTo'] = trim((string) ($input['to'] ?? ($payload['approvalEmailTo'] ?? '')));
        $payload['approvalEmailCc'] = trim((string) ($input['cc'] ?? ($payload['approvalEmailCc'] ?? '')));
        $payload['approvalEmailFromName'] = trim((string) ($input['from_name'] ?? ($payload['approvalEmailFromName'] ?? '')));
        $payload['approvalEmailFromEmail'] = trim((string) ($input['from_email'] ?? ($payload['approvalEmailFromEmail'] ?? '')));
        $payload['approvalEmailReplyTo'] = trim((string) ($input['reply_to'] ?? ($payload['approvalEmailReplyTo'] ?? '')));
        $payload['approvalEmailSubject'] = trim((string) ($input['subject'] ?? ($payload['approvalEmailSubject'] ?? '')));
        $payload['approvalEmailImageMode'] = trim((string) ($input['image_mode'] ?? ($payload['approvalEmailImageMode'] ?? 'links')));
        $this->pipelineState->save($article, $payload);
    }

    private function serializeArticle(PublishArticle $article): array
    {
        return [
            'id' => $article->id,
            'article_id' => $article->article_id,
            'title' => $article->title,
            'status' => $article->status,
            'article_type' => $article->article_type,
            'approval_emails_count' => $article->approval_emails_count ?? $article->approvalEmails()->count(),
            'latest_approval_email' => $article->latestApprovalEmail ? $this->serializeLog($article->latestApprovalEmail) : null,
        ];
    }

    private function serializeLogs(PublishArticle $article): array
    {
        return $article->approvalEmails()
            ->with('sender')
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (PublishArticleApprovalEmail $email) => $this->serializeLog($email))
            ->values()
            ->all();
    }

    private function serializeLog(PublishArticleApprovalEmail $email): array
    {
        $serialized = $this->approvalEmails->serializeLog($email);
        $context = 'publish_draft_approval:' . $email->publish_article_id . ':' . $email->id;
        $emailLog = EmailLog::query()->where('context', $context)->latest('id')->first();

        $serialized['email_log_context'] = $context;
        $serialized['email_log'] = $emailLog ? [
            'id' => $emailLog->id,
            'status' => $emailLog->status,
            'error' => $emailLog->error,
            'from_email' => $emailLog->from_email,
            'from_name' => $emailLog->from_name,
            'smtp_account_id' => $emailLog->smtp_account_id,
            'created_at' => $emailLog->created_at?->toDateTimeString(),
        ] : null;

        return $serialized;
    }
}
