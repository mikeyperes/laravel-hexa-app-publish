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
            "success" => true,
            "article" => $this->serializeArticle($article),
            "composer" => $this->composerConfig($article),
            "logs" => $this->serializeLogs($article),
        ]);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $article = $this->resolveArticle($request, $id);
        $input = $request->validate([
            "to" => "nullable|string|max:255",
            "cc" => "nullable|string|max:1000",
            "from_name" => "nullable|string|max:255",
            "from_email" => "nullable|string|max:255",
            "reply_to" => "nullable|string|max:255",
            "subject" => "nullable|string|max:255",
            "intro_html" => "nullable|string|max:50000",
            "image_mode" => "nullable|string|max:40",
        ]);

        $this->persistComposerState($article, $input);
        $preview = $this->approvalEmails->preview($article, $input);

        return response()->json([
            "success" => true,
            "composer" => array_merge($this->composerConfig($article), [
                "to" => (string) ($input["to"] ?? $preview["config"]["to"] ?? ""),
                "cc" => (string) ($input["cc"] ?? $preview["config"]["cc"] ?? ""),
                "from_name" => (string) ($input["from_name"] ?? $preview["config"]["from_name"] ?? ""),
                "from_email" => (string) ($input["from_email"] ?? $preview["config"]["from_email"] ?? ""),
                "reply_to" => (string) ($input["reply_to"] ?? $preview["config"]["reply_to"] ?? ""),
                "subject" => (string) ($input["subject"] ?? $preview["config"]["subject"] ?? ""),
                "intro_html" => (string) ($input["intro_html"] ?? $preview["config"]["intro_html"] ?? ""),
                "image_mode" => (string) ($input["image_mode"] ?? $preview["config"]["image_mode"] ?? "embed"),
            ]),
            "preview_html" => $preview["preview_html"],
            "warnings" => $preview["warnings"],
            "snapshot" => $preview["snapshot"],
            "headers" => $preview["headers"],
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $article = $this->resolveArticle($request, $id);
        $input = $request->validate([
            "to" => "nullable|string|max:255",
            "cc" => "nullable|string|max:1000",
            "from_name" => "nullable|string|max:255",
            "from_email" => "nullable|string|max:255",
            "reply_to" => "nullable|string|max:255",
            "subject" => "nullable|string|max:255",
            "intro_html" => "nullable|string|max:50000",
            "image_mode" => "nullable|string|max:40",
            "test_mode" => "nullable|boolean",
            "test_to" => "nullable|email|max:255",
        ]);

        $isTest = (bool) ($input["test_mode"] ?? false);
        $this->persistComposerState($article, $input);
        $email = $this->approvalEmails->send($article, $input, $request->user(), [
            "is_test" => $isTest,
            "test_to" => $input["test_to"] ?? null,
        ]);
        $actualRecipients = implode(", ", $email->to_recipients ?? []);

        return response()->json([
            "success" => $email->status === "sent",
            "message" => $email->status === "sent"
                ? ($isTest
                    ? ("Draft approval test email sent to " . ($actualRecipients !== "" ? $actualRecipients : "the selected recipient") . ".")
                    : "Draft approval email sent.")
                : ($email->error ?: ($isTest ? "Draft approval test email failed." : "Draft approval email failed.")),
            "email" => $this->serializeLog($email),
            "logs" => $this->serializeLogs($article),
        ], $email->status === "sent" ? 200 : 422);
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        $article = $this->resolveArticle($request, $id);

        return response()->json([
            "success" => true,
            "logs" => $this->serializeLogs($article),
        ]);
    }

    private function resolveArticle(Request $request, int $id): PublishArticle
    {
        $article = $this->access->resolveArticleOrFail($request->user(), $id);
        $article->loadMissing(["site", "creator", "pipelineState", "latestApprovalEmail.sender"]);

        return $article;
    }

    private function composerConfig(PublishArticle $article): array
    {
        $config = $this->approvalEmails->defaultComposerConfig($article);
        $payload = $this->pipelineState->payload($article);
        $map = [
            "to" => "approvalEmailTo",
            "cc" => "approvalEmailCc",
            "from_name" => "approvalEmailFromName",
            "from_email" => "approvalEmailFromEmail",
            "reply_to" => "approvalEmailReplyTo",
            "subject" => "approvalEmailSubject",
            "intro_html" => "approvalEmailIntroHtml",
            "image_mode" => "approvalEmailImageMode",
        ];

        $toTouched = (bool) ($payload["approvalEmailToTouched"] ?? false);
        $ccTouched = (bool) ($payload["approvalEmailCcTouched"] ?? false);

        foreach ($map as $configKey => $payloadKey) {
            if (!array_key_exists($payloadKey, $payload) || $payload[$payloadKey] === null) {
                continue;
            }
            if ($configKey === "to" && !$toTouched) {
                continue;
            }
            if ($configKey === "cc" && !$ccTouched) {
                continue;
            }

            $value = is_string($payload[$payloadKey])
                ? $payload[$payloadKey]
                : (string) $payload[$payloadKey];

            if ($configKey !== "intro_html") {
                $value = trim($value);
            }

            if (in_array($configKey, ["to", "cc", "from_name", "from_email", "reply_to", "subject", "image_mode"], true) && $value === "") {
                continue;
            }

            $config[$configKey] = $value;
        }

        $creatorLogin = trim((string) ($article->creator?->email ?? ""));
        if ($creatorLogin !== "" && filter_var($creatorLogin, FILTER_VALIDATE_EMAIL) && !$toTouched) {
            $config["to"] = $creatorLogin;
        }

        $config["additional_ccs"] = trim((string) ($article->creator?->additional_contact_emails ?? ""));
        $config["smtp_settings_url"] = route("publish.settings.master");

        return $config;
    }

    private function persistComposerState(PublishArticle $article, array $input): void
    {
        $payload = $this->pipelineState->payload($article);
        $payload["approvalEmailTo"] = trim((string) ($input["to"] ?? ($payload["approvalEmailTo"] ?? "")));
        if (array_key_exists("to", $input)) {
            $payload["approvalEmailToTouched"] = trim((string) ($input["to"] ?? "")) !== "";
        }
        $payload["approvalEmailCc"] = trim((string) ($input["cc"] ?? ($payload["approvalEmailCc"] ?? "")));
        if (array_key_exists("cc", $input)) {
            $payload["approvalEmailCcTouched"] = trim((string) ($input["cc"] ?? "")) !== "";
        }
        $payload["approvalEmailFromName"] = trim((string) ($input["from_name"] ?? ($payload["approvalEmailFromName"] ?? "")));
        $payload["approvalEmailFromEmail"] = trim((string) ($input["from_email"] ?? ($payload["approvalEmailFromEmail"] ?? "")));
        $payload["approvalEmailReplyTo"] = trim((string) ($input["reply_to"] ?? ($payload["approvalEmailReplyTo"] ?? "")));
        $payload["approvalEmailSubject"] = trim((string) ($input["subject"] ?? ($payload["approvalEmailSubject"] ?? "")));
        $payload["approvalEmailIntroHtml"] = (string) ($input["intro_html"] ?? ($payload["approvalEmailIntroHtml"] ?? ""));
        $payload["approvalEmailImageMode"] = trim((string) ($input["image_mode"] ?? ($payload["approvalEmailImageMode"] ?? "embed")));
        $this->pipelineState->save($article, $payload);
    }

    private function serializeArticle(PublishArticle $article): array
    {
        return [
            "id" => $article->id,
            "article_id" => $article->article_id,
            "title" => $article->title,
            "status" => $article->status,
            "article_type" => $article->article_type,
            "featured_image_url" => $this->resolveFeaturedImage($article),
            "approval_emails_count" => $article->approval_emails_count ?? $article->approvalEmails()->count(),
            "latest_approval_email" => $article->latestApprovalEmail ? $this->serializeLog($article->latestApprovalEmail) : null,
        ];
    }

    private function resolveFeaturedImage(PublishArticle $article): ?string
    {
        $wp = $article->wp_images;
        if (is_array($wp) && !empty($wp)) {
            $featured = collect($wp)->firstWhere("is_featured", true) ?? collect($wp)->first();
            if (is_array($featured)) {
                return $featured["sizes"]["medium"]
                    ?? $featured["sizes"]["thumbnail"]
                    ?? $featured["media_url"]
                    ?? null;
            }
        }
        $photos = $article->photos;
        if (is_array($photos) && !empty($photos)) {
            $first = collect($photos)->first();
            if (is_array($first)) {
                return $first["sizes"]["medium"]
                    ?? $first["sizes"]["thumbnail"]
                    ?? $first["url"]
                    ?? $first["src"]
                    ?? $first["thumb"]
                    ?? null;
            }
            if (is_string($first)) {
                return $first;
            }
        }
        return null;
    }

    private function serializeLogs(PublishArticle $article): array
    {
        return $article->approvalEmails()
            ->with("sender")
            ->latest("id")
            ->get()
            ->map(fn (PublishArticleApprovalEmail $email) => $this->serializeLog($email))
            ->values()
            ->all();
    }

    private function serializeLog(PublishArticleApprovalEmail $email): array
    {
        $serialized = $this->approvalEmails->serializeLog($email);
        $context = "publish_draft_approval:" . $email->publish_article_id . ":" . $email->id;
        $emailLog = EmailLog::query()->where("context", $context)->latest("id")->first();

        $serialized["email_log_context"] = $context;
        $serialized["email_log"] = $emailLog ? [
            "id" => $emailLog->id,
            "status" => $emailLog->status,
            "error" => $emailLog->error,
            "from_email" => $emailLog->from_email,
            "from_name" => $emailLog->from_name,
            "smtp_account_id" => $emailLog->smtp_account_id,
            "created_at" => $emailLog->created_at?->toDateTimeString(),
        ] : null;

        return $serialized;
    }
}
