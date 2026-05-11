<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishArticleApprovalEmail;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_core\Models\Setting;
use hexa_core\Models\SmtpAccount;
use hexa_core\Models\User;
use hexa_core\Services\EmailService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DraftApprovalEmailService
{
    public function __construct(
        private PipelineStateService $pipelineState,
        private EmailService $emailService,
        private ArticleActivityService $articleActivity,
    ) {}

    public function defaultComposerConfig(PublishArticle $article): array
    {
        return $this->defaultComposerConfigFromSnapshot($this->resolveSnapshot($article));
    }

    public function preview(PublishArticle $article, array $input): array
    {
        $snapshot = $this->resolveSnapshot($article);
        $config = $this->normalizeComposerInput($snapshot, $input, false);
        $message = $this->buildMessage($snapshot, $config, null, false);

        return [
            'config' => $config,
            'preview_html' => $message['preview_html'],
            'warnings' => $message['warnings'],
            'snapshot' => $message['snapshot_summary'],
            'headers' => $message['headers'],
        ];
    }

    public function send(PublishArticle $article, array $input, ?User $actor = null, array $options = []): PublishArticleApprovalEmail
    {
        $snapshot = $this->resolveSnapshot($article);
        $isTest = (bool) Arr::get($options, 'is_test', false);
        $config = $this->normalizeComposerInput($snapshot, $input, !$isTest);
        $intendedRecipients = [
            'to' => $config['to'],
            'cc' => $config['cc_list'],
        ];

        if ($isTest) {
            $testRecipient = trim((string) Arr::get($options, 'test_to', ''));
            $config['to'] = $testRecipient !== '' ? $testRecipient : $this->testRecipientAddress();
            $config['cc'] = '';
            $config['cc_list'] = [];
        }

        $publicToken = Str::random(48);
        $message = $this->buildMessage($snapshot, $config, $publicToken, true);
        $smtpMeta = $this->resolveSmtpMeta();

        $record = PublishArticleApprovalEmail::create([
            'publish_article_id' => $article->id,
            'created_by' => $actor?->id,
            'smtp_account_id' => $smtpMeta['id'],
            'context' => $isTest ? 'draft-approval-test' : 'draft-approval',
            'status' => 'queued',
            'image_mode' => $config['image_mode'],
            'to_recipients' => [$config['to']],
            'cc_recipients' => $config['cc_list'],
            'from_email' => $config['from_email'],
            'from_name' => $config['from_name'],
            'reply_to' => $config['reply_to'],
            'subject' => $config['subject'],
            'body_html' => $message['body_html'],
            'body_text' => $message['body_text'],
            'preview_html' => $message['preview_html'],
            'headers' => $message['headers'],
            'diagnostics' => [
                'smtp_account' => $smtpMeta,
                'warnings' => $message['warnings'],
                'is_test' => $isTest,
                'intended_recipients' => $intendedRecipients,
            ],
            'snapshot' => $message['snapshot_summary'],
            'public_token' => $publicToken,
        ]);

        $startedAt = microtime(true);
        $result = $this->sendEmailThroughConfiguredTransport(
            config: $config,
            message: $message,
            smtpMeta: $smtpMeta,
            context: "publish_draft_approval:" . $article->id . ":" . $record->id,
        );
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        $success = (bool) ($result['success'] ?? false);
        $record->forceFill([
            'status' => $success ? 'sent' : 'failed',
            'error' => $success ? null : (string) ($result['message'] ?? 'Email send failed.'),
            'sent_at' => $success ? now() : null,
            'diagnostics' => array_merge($record->diagnostics ?? [], [
                'smtp_account' => $smtpMeta,
                'warnings' => $message['warnings'],
                'timing_ms' => $elapsedMs,
                'result_message' => (string) ($result['message'] ?? ''),
            ]),
        ])->save();

        $this->logActivity(
            article: $article,
            actor: $actor,
            email: $record,
            activityType: $isTest
                ? ($success ? 'email_test_sent' : 'email_test_failed')
                : ($success ? 'email_sent' : 'email_failed'),
            message: (string) ($result['message'] ?? ($success
                ? ($isTest ? 'Approval test email sent' : 'Approval email sent')
                : ($isTest ? 'Approval test email failed' : 'Approval email failed'))),
            meta: [
                'headers' => $message['headers'],
                'warnings' => $message['warnings'],
                'smtp_account' => $smtpMeta,
                'timing_ms' => $elapsedMs,
                'preview_html' => $message['preview_html'],
                'is_test' => $isTest,
                'intended_recipients' => $intendedRecipients,
            ],
            success: $success,
        );

        return $record->fresh(['sender']);
    }

    public function markViewed(PublishArticleApprovalEmail $email, string $method = 'page'): PublishArticleApprovalEmail
    {
        if (!$email->viewed_at) {
            $email->forceFill([
                'viewed_at' => now(),
                'status' => $email->reviewed_at ? 'reviewed' : 'viewed',
            ])->save();

            $this->logActivity($email->article, null, $email, 'email_viewed', 'Approval email viewed', [
                'method' => $method,
            ], true);
        }

        return $email->fresh();
    }

    public function markReviewed(PublishArticleApprovalEmail $email, array $reviewPayload): PublishArticleApprovalEmail
    {
        $payload = [
            'reviewer_name' => trim((string) ($reviewPayload['reviewer_name'] ?? '')),
            'reviewer_email' => trim((string) ($reviewPayload['reviewer_email'] ?? '')),
            'decision' => trim((string) ($reviewPayload['decision'] ?? 'reviewed')),
            'note' => trim((string) ($reviewPayload['note'] ?? '')),
            'reviewed_at_iso' => now()->toIso8601String(),
        ];

        $email->forceFill([
            'viewed_at' => $email->viewed_at ?: now(),
            'reviewed_at' => now(),
            'status' => 'reviewed',
            'review_payload' => $payload,
        ])->save();

        $this->logActivity($email->article, null, $email, 'email_reviewed', 'Approval email reviewed', $payload, true);

        return $email->fresh();
    }

    public function serializeLog(PublishArticleApprovalEmail $email): array
    {
        $smtpMeta = Arr::get($email->diagnostics ?? [], 'smtp_account', []);
        $sender = $email->relationLoaded('sender') ? $email->sender : $email->sender()->first();
        $isTest = $email->context === 'draft-approval-test' || (bool) Arr::get($email->diagnostics ?? [], 'is_test', false);

        return [
            'id' => $email->id,
            'context' => $email->context,
            'is_test' => $isTest,
            'status' => $email->status,
            'status_label' => $email->statusLabel(),
            'to' => implode(', ', $email->to_recipients ?? []),
            'cc' => implode(', ', $email->cc_recipients ?? []),
            'subject' => $email->subject,
            'from_email' => $email->from_email,
            'from_name' => $email->from_name,
            'reply_to' => $email->reply_to,
            'image_mode' => $email->image_mode,
            'error' => $email->error,
            'sent_at' => $email->sent_at?->toDateTimeString(),
            'viewed_at' => $email->viewed_at?->toDateTimeString(),
            'reviewed_at' => $email->reviewed_at?->toDateTimeString(),
            'created_at' => $email->created_at?->toDateTimeString(),
            'updated_at' => $email->updated_at?->toDateTimeString(),
            'preview_html' => $email->preview_html,
            'body_html' => $email->body_html,
            'body_text' => $email->body_text,
            'headers' => $email->headers,
            'diagnostics' => $email->diagnostics,
            'snapshot' => $email->snapshot,
            'review_payload' => $email->review_payload,
            'smtp_account' => $smtpMeta,
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => $sender->name,
                'email' => $sender->email,
            ] : null,
            'public_review_url' => route('publish.drafts.approval.public.show', ['token' => $email->public_token]),
        ];
    }

    private function defaultComposerConfigFromSnapshot(array $snapshot): array
    {
        return [
            'to' => $this->resolveDefaultRecipient($snapshot),
            'cc' => $this->resolveDefaultCc($snapshot),
            'from_name' => 'Scale My Publication',
            'from_email' => 'no-reply@scalemypublication.com',
            'reply_to' => 'support@scalemypublication.com',
            'subject' => $this->defaultSubject($snapshot),
            'intro_html' => '',
            'image_mode' => 'embed',
        ];
    }

    private function normalizeComposerInput(array $snapshot, array $input, bool $requireRecipient): array
    {
        $defaults = $this->defaultComposerConfigFromSnapshot($snapshot);
        $to = trim((string) ($input['to'] ?? $defaults['to']));
        $cc = $this->normalizeEmailListString((string) ($input['cc'] ?? $defaults['cc']));
        $fromName = trim((string) ($input['from_name'] ?? $defaults['from_name'])) ?: $defaults['from_name'];
        $fromEmail = trim((string) ($input['from_email'] ?? $defaults['from_email'])) ?: $defaults['from_email'];
        $replyTo = trim((string) ($input['reply_to'] ?? $defaults['reply_to'])) ?: $defaults['reply_to'];
        $subject = trim((string) ($input['subject'] ?? $defaults['subject'])) ?: $defaults['subject'];
        $introHtml = (string) ($input['intro_html'] ?? $defaults['intro_html']);
        $imageMode = trim((string) ($input['image_mode'] ?? $defaults['image_mode'])) ?: 'embed';

        if (($requireRecipient || $to !== '') && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['to' => 'Enter a valid recipient email address.']);
        }

        foreach (['from_email' => $fromEmail, 'reply_to' => $replyTo] as $field => $value) {
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([$field => 'Enter a valid email address.']);
            }
        }

        if (!in_array($imageMode, ['links', 'embed', 'wordpress'], true)) {
            throw ValidationException::withMessages(['image_mode' => 'Choose a valid image mode.']);
        }

        $ccList = $this->parseEmailList($cc);
        foreach ($ccList as $ccEmail) {
            if (!filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['cc' => 'One or more CC email addresses are invalid.']);
            }
        }

        return [
            'to' => $to,
            'cc' => $cc,
            'cc_list' => $ccList,
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'reply_to' => $replyTo,
            'subject' => $subject,
            'intro_html' => $introHtml,
            'image_mode' => $imageMode,
        ];
    }

    private function defaultSubject(array $snapshot): string
    {
        $title = trim((string) ($snapshot['title'] ?? 'Untitled')) ?: 'Untitled';

        return ($snapshot['article_type'] ?? '') === 'press-release'
            ? 'Your Press Release draft is ready: ' . $title
            : 'Your draft is ready: ' . $title;
    }

    private function resolveSnapshot(PublishArticle $article): array
    {
        $article->loadMissing(['site', 'creator', 'pipelineState']);
        $payload = $this->pipelineState->payload($article);
        $pressRelease = is_array($payload['pressRelease'] ?? null) ? $payload['pressRelease'] : [];
        $bodyHtml = trim((string) ($payload['editorContent'] ?? $payload['spunContent'] ?? $article->body ?? ($pressRelease['content_dump'] ?? '')));
        $preparedHtml = (string) (PublishPipelineOperation::query()
            ->where('publish_article_id', $article->id)
            ->where('operation_type', PublishPipelineOperation::TYPE_PREPARE)
            ->where('status', PublishPipelineOperation::STATUS_COMPLETED)
            ->latest('id')
            ->value('result_payload->html') ?? '');
        $title = trim((string) ($payload['articleTitle'] ?? $article->title ?? 'Untitled')) ?: 'Untitled';
        $excerpt = trim((string) ($payload['articleDescription'] ?? $article->excerpt ?? ''));
        $siteUrl = trim((string) ($article->site?->url ?? Arr::get($payload, 'selectedSite.url', '')));
        $featuredPhoto = is_array($payload['featuredPhoto'] ?? null) ? $payload['featuredPhoto'] : [];
        $creator = $article->creator;

        return [
            'article_id' => $article->id,
            'article_code' => $article->article_id,
            'article_type' => trim((string) ($payload['prArticle']['article_type'] ?? $payload['template_overrides']['article_type'] ?? $article->article_type ?? '')),
            'title' => $title,
            'excerpt' => $excerpt,
            'body_html' => $bodyHtml,
            'prepared_html' => $preparedHtml,
            'site_url' => $siteUrl,
            'site_name' => trim((string) ($article->site?->name ?? Arr::get($payload, 'selectedSite.name', ''))),
            'creator_name' => trim((string) ($creator?->name ?? '')),
            'creator_login_email' => trim((string) ($creator?->email ?? '')),
            'creator_contact_email' => trim((string) ($creator?->contact_email ?? '')),
            'creator_additional_contact_emails' => trim((string) ($creator?->additional_contact_emails ?? '')),
            'featured_url' => trim((string) ($featuredPhoto['url_full'] ?? $featuredPhoto['url_large'] ?? $featuredPhoto['url'] ?? $featuredPhoto['url_thumb'] ?? '')),
            'featured_alt' => trim((string) ($featuredPhoto['alt'] ?? 'Featured image')),
            'featured_caption' => trim((string) ($payload['featuredCaption'] ?? '')),
            'featured_wp_url' => trim((string) ($payload['preparedFeaturedWpUrl'] ?? $this->resolveWpFeaturedUrlFromArticle($article))),
        ];
    }

    private function buildMessage(array $snapshot, array $config, ?string $publicToken, bool $includeTracking): array
    {
        [$introHtml, $introWarnings] = $this->buildFragmentHtmlForMode((string) ($config['intro_html'] ?? ''), $snapshot, $config['image_mode']);
        [$articleHtml, $warnings] = $this->buildArticleHtmlForMode($snapshot, $config['image_mode']);
        $warnings = array_values(array_filter(array_merge($introWarnings, $warnings)));
        if (trim(strip_tags($articleHtml)) === '') {
            $warnings[] = 'The draft body is currently empty.';
        }

        $reviewUrl = $publicToken ? route('publish.drafts.approval.public.show', ['token' => $publicToken]) : '#';
        $trackingUrl = $publicToken ? route('publish.drafts.approval.public.track', ['token' => $publicToken]) : null;
        $featuredHtml = $this->renderFeaturedImage($snapshot, $config['image_mode']);
        $excerptHtml = $snapshot['excerpt'] !== ''
            ? '<p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#4b5563;">' . $this->escapeHtml($snapshot['excerpt']) . '</p>'
            : '';
        $introBlock = trim(strip_tags($introHtml)) !== ''
            ? '<div style="margin:0 0 18px; font-size:15px; line-height:1.7; color:#111827;">' . $introHtml . '</div>'
            : '';
        $trackingMarkup = $includeTracking && $trackingUrl
            ? '<img src="' . $this->escapeAttribute($trackingUrl) . '" alt="" width="1" height="1" style="display:none;" />'
            : '';

        $bodyHtml = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;color:#111827;max-width:760px;margin:0 auto;padding:24px;">
  <div style="margin:0 0 20px; padding:16px 18px; border:1px solid #dbeafe; border-radius:12px; background:#eff6ff;">
    <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#1d4ed8;">Approval draft</p>
    <h2 style="margin:0 0 8px; font-size:20px; line-height:1.3; color:#111827;">{$this->escapeHtml($config['subject'])}</h2>
    <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#475569;">This draft is ready for review. Use the hosted review page to confirm you’ve seen it and mark it reviewed.</p>
    <p style="margin:0;"><a href="{$this->escapeAttribute($reviewUrl)}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 14px;border-radius:8px;font-size:14px;font-weight:600;">Open review page</a></p>
  </div>
  {$introBlock}
  {$featuredHtml}
  <article style="font-size:15px; line-height:1.7; color:#111827;">
    <h1 style="font-size:30px; line-height:1.2; margin:0 0 12px;">{$this->escapeHtml($snapshot['title'])}</h1>
    {$excerptHtml}
    {$articleHtml}
  </article>
  <div style="margin-top:24px; padding-top:12px; border-top:1px solid #e5e7eb; font-size:12px; line-height:1.6; color:#6b7280;">
    <p style="margin:0;">Sent from Scale My Publication.</p>
  </div>
  {$trackingMarkup}
</div>
HTML;

        $headers = [
            'From' => trim($config['from_name'] . ' <' . $config['from_email'] . '>'),
            'Reply-To' => $config['reply_to'],
            'To' => $config['to'],
            'Cc' => implode(', ', $config['cc_list']),
            'Subject' => $config['subject'],
            'Image Mode' => $config['image_mode'],
        ];

        return [
            'body_html' => $bodyHtml,
            'body_text' => $this->htmlToText($bodyHtml),
            'preview_html' => $this->renderPreviewHtml($headers, $bodyHtml, $warnings, $reviewUrl, $trackingUrl),
            'headers' => $headers,
            'warnings' => $warnings,
            'snapshot_summary' => [
                'title' => $snapshot['title'],
                'article_type' => $snapshot['article_type'],
                'site_name' => $snapshot['site_name'],
                'site_url' => $snapshot['site_url'],
                'has_prepared_html' => $snapshot['prepared_html'] !== '',
                'has_featured_image' => ($snapshot['featured_url'] !== '' || $snapshot['featured_wp_url'] !== ''),
                'has_intro_html' => trim(strip_tags((string) ($config['intro_html'] ?? ''))) !== '',
            ],
        ];
    }

    private function buildArticleHtmlForMode(array $snapshot, string $imageMode): array
    {
        return $this->buildFragmentHtmlForMode((string) ($snapshot['body_html'] ?? ''), $snapshot, $imageMode);
    }

    private function buildFragmentHtmlForMode(string $html, array $snapshot, string $imageMode): array
    {
        $html = trim((string) $html);
        $html = preg_replace("/<span class=\"photo-(?:view|confirm|change|remove)\"[^>]*>.*?<\\/span>/si", "", $html);
        $html = preg_replace("/<div class=\"photo-placeholder\"([^>]*)style=\"[^\"]*\"([^>]*)>/i", "<div$1$2>", $html);
        $html = preg_replace("/\\scontenteditable=\"false\"/i", "", $html);
        $html = preg_replace("/\\sdata-[a-z0-9_-]+=\"[^\"]*\"/i", "", $html);
        $warnings = [];

        if ($imageMode === 'wordpress') {
            $html = trim((string) ($snapshot['prepared_html'] ?: $html));
            $html = preg_replace("/<span class=\"photo-(?:view|confirm|change|remove)\"[^>]*>.*?<\\/span>/si", "", $html);
            $html = preg_replace("/<div class=\"photo-placeholder\"([^>]*)style=\"[^\"]*\"([^>]*)>/i", "<div$1$2>", $html);
            $html = preg_replace("/\\scontenteditable=\"false\"/i", "", $html);
            $html = preg_replace("/\\sdata-[a-z0-9_-]+=\"[^\"]*\"/i", "", $html);
            $html = $this->absolutizeHtmlUrls($html, $snapshot['site_url']);
            if ($this->hasNonWordpressImages($html, $snapshot['site_url'])) {
                throw ValidationException::withMessages([
                    'image_mode' => 'Run Prepare first so inline images are uploaded to WordPress before sending with WordPress-hosted images.',
                ]);
            }

            return [$this->styleEmbeddedHtml($html), $warnings];
        }

        $html = $this->absolutizeHtmlUrls($html, $snapshot['site_url']);

        if ($imageMode === 'embed') {
            return [$this->styleEmbeddedHtml($html), $warnings];
        }

        return [$this->transformImagesToLinks($html), $warnings];
    }

    private function sendEmailThroughConfiguredTransport(array $config, array $message, array $smtpMeta, string $context): array
    {
        $service = trim((string) ($smtpMeta["service"] ?? "core-smtp")) ?: "core-smtp";

        if ($service === "smtp2go" && class_exists(\hexa_package_smtp2go\Services\Smtp2goService::class)) {
            $payload = [
                "sender" => $config["from_email"],
                "to" => [$config["to"]],
                "subject" => $config["subject"],
                "html_body" => $message["body_html"],
                "text_body" => $message["body_text"],
            ];
            if ($config["reply_to"] !== "") {
                $payload["reply_to"] = $config["reply_to"];
            }
            if (!empty($config["cc_list"])) {
                $payload["cc"] = array_values($config["cc_list"]);
            }

            $result = app(\hexa_package_smtp2go\Services\Smtp2goService::class)->sendTransactionalEmail($payload);

            return [
                "success" => (bool) ($result["success"] ?? false),
                "message" => (bool) ($result["success"] ?? false) ? "Email sent successfully to " . $config["to"] : ("Email failed: " . (string) ($result["error"] ?? $result["message"] ?? "SMTP2GO send failed.")),
                "diagnostics" => ["provider" => "smtp2go", "response" => $result["data"] ?? null],
            ];
        }

        if ($service === "brevo" && class_exists(\hexa_package_brevo\Services\BrevoService::class)) {
            $payload = [
                "sender" => ["name" => $config["from_name"], "email" => $config["from_email"]],
                "to" => [["email" => $config["to"]]],
                "subject" => $config["subject"],
                "htmlContent" => $message["body_html"],
                "textContent" => $message["body_text"],
            ];
            if ($config["reply_to"] !== "") {
                $payload["replyTo"] = ["email" => $config["reply_to"]];
            }
            if (!empty($config["cc_list"])) {
                $payload["cc"] = array_map(static fn (string $email): array => ["email" => $email], array_values($config["cc_list"]));
            }

            $result = app(\hexa_package_brevo\Services\BrevoService::class)->sendTransactionalEmail($payload);

            return [
                "success" => (bool) ($result["success"] ?? false),
                "message" => (bool) ($result["success"] ?? false) ? "Email sent successfully to " . $config["to"] : ("Email failed: " . (string) ($result["error"] ?? $result["message"] ?? "Brevo send failed.")),
                "diagnostics" => ["provider" => "brevo", "response" => $result["data"] ?? null],
            ];
        }

        return $this->emailService->send(
            to: $config["to"],
            subject: $config["subject"],
            body: $message["body_html"],
            fromName: $config["from_name"],
            fromEmail: $config["from_email"],
            replyTo: $config["reply_to"],
            cc: $config["cc"],
            smtpAccountId: $smtpMeta["id"],
            context: $context,
        );
    }

    private function resolveDefaultRecipient(array $snapshot): string
    {
        $contactEmail = trim((string) ($snapshot['creator_contact_email'] ?? ''));
        if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return $contactEmail;
        }

        $loginEmail = trim((string) ($snapshot['creator_login_email'] ?? ''));
        if ($loginEmail !== '' && filter_var($loginEmail, FILTER_VALIDATE_EMAIL)) {
            return $loginEmail;
        }

        return '';
    }

    private function resolveDefaultCc(array $snapshot): string
    {
        return implode(', ', $this->parseEmailList((string) ($snapshot['creator_additional_contact_emails'] ?? '')));
    }

    private function normalizeEmailListString(string $value): string
    {
        return implode(', ', $this->parseEmailList($value));
    }

    private function parseEmailList(string $value): array
    {
        $normalized = preg_replace('/[;\r\n]+/', ',', (string) $value);
        $items = array_filter(array_map('trim', explode(',', (string) $normalized)), static fn ($item) => $item !== '');
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            $key = strtolower($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }

    private function testRecipientAddress(): string
    {
        return 'michael@mike-ro-tech.com';
    }

    private function renderFeaturedImage(array $snapshot, string $imageMode): string
    {
        $url = $imageMode === 'wordpress'
            ? ($snapshot['featured_wp_url'] ?: '')
            : ($snapshot['featured_url'] ?: $snapshot['featured_wp_url']);
        if ($url === '') {
            return '';
        }

        $url = $this->absolutizeUrl($url, $snapshot['site_url']);
        $caption = trim((string) ($snapshot['featured_caption'] ?: $snapshot['featured_alt']));

        if ($imageMode === 'links') {
            $captionHtml = $caption !== ''
                ? '<p style="margin:6px 0 0; font-size:12px; color:#6b7280;">' . $this->escapeHtml($caption) . '</p>'
                : '';

            return '<div style="margin:0 0 20px;"><p style="margin:0;"><a href="' . $this->escapeAttribute($url) . '" target="_blank" rel="noopener" style="color:#2563eb; font-weight:600; text-decoration:none;">View featured image</a></p>' . $captionHtml . '</div>';
        }

        $captionHtml = $caption !== ''
            ? '<p style="margin:8px 0 0; font-size:12px; line-height:1.5; color:#6b7280;">' . $this->escapeHtml($caption) . '</p>'
            : '';

        return '<div style="margin:0 0 20px;"><img src="' . $this->escapeAttribute($url) . '" alt="' . $this->escapeAttribute($snapshot['featured_alt']) . '" style="display:block; width:100%; max-width:100%; height:auto; border-radius:12px;" />' . $captionHtml . '</div>';
    }

    private function renderPreviewHtml(array $headers, string $bodyHtml, array $warnings, string $reviewUrl, ?string $trackingUrl): string
    {
        $fromRaw = (string) ($headers['From'] ?? '');
        $fromName = $fromRaw;
        $fromEmail = '';
        if (preg_match('/^(.*?)\s*<([^>]+)>\s*$/', $fromRaw, $m)) {
            $fromName = trim($m[1]);
            $fromEmail = trim($m[2]);
        }
        $initials = '';
        if ($fromName !== '') {
            foreach (preg_split('/\s+/', $fromName) as $word) {
                if ($word !== '' && $initials === '') {
                    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
                } elseif ($word !== '' && mb_strlen($initials) < 2) {
                    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
                }
            }
        }
        if ($initials === '') { $initials = 'SP'; }

        $subject = (string) ($headers['Subject'] ?? '(no subject)');
        $to = (string) ($headers['To'] ?? '');
        $cc = (string) ($headers['Cc'] ?? '');
        $replyTo = (string) ($headers['Reply-To'] ?? '');
        $imageMode = (string) ($headers['Image Mode'] ?? '');

        // Inbox-style metadata strip
        $recipientLines = '';
        if ($to !== '') {
            $recipientLines .= '<div><span class="font-medium text-gray-600">To:</span> ' . $this->escapeHtml($to) . '</div>';
        }
        if ($cc !== '') {
            $recipientLines .= '<div><span class="font-medium text-gray-600">Cc:</span> ' . $this->escapeHtml($cc) . '</div>';
        }
        if ($replyTo !== '') {
            $recipientLines .= '<div><span class="font-medium text-gray-600">Reply-to:</span> ' . $this->escapeHtml($replyTo) . '</div>';
        }

        $metaStrip = '<div class="border-b border-gray-200 px-4 py-3 bg-white">'
            . '<div class="flex items-start gap-3">'
            . '<div class="w-9 h-9 rounded-full bg-blue-50 text-blue-700 font-semibold flex items-center justify-center text-sm flex-shrink-0">' . $this->escapeHtml($initials) . '</div>'
            . '<div class="flex-1 min-w-0">'
            . '<div class="flex items-baseline gap-2 flex-wrap">'
            . '<span class="font-semibold text-gray-900 text-sm">' . $this->escapeHtml($fromName !== '' ? $fromName : 'Unknown sender') . '</span>'
            . ($fromEmail !== '' ? '<span class="text-gray-500 text-xs">&lt;' . $this->escapeHtml($fromEmail) . '&gt;</span>' : '')
            . '</div>'
            . '<p class="mt-1 text-gray-900 font-semibold text-[15px] break-words">' . $this->escapeHtml($subject) . '</p>'
            . '<div class="mt-1.5 space-y-0.5 text-[11px] text-gray-500">'
            . $recipientLines
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        // Email body — rendered, edge to edge
        $body = '<div class="px-4 py-5 bg-white"><div class="max-w-none prose prose-sm">' . $bodyHtml . '</div></div>';

        // Warnings inline above body if any
        $warningList = '';
        if ($warnings !== []) {
            $warningItems = collect($warnings)->map(fn ($warning) => '<li>' . $this->escapeHtml($warning) . '</li>')->implode('');
            $warningList = '<div class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"><p class="font-semibold mb-1">Preview warnings</p><ul class="list-disc pl-5 space-y-0.5">' . $warningItems . '</ul></div>';
        }

        // Technical send details — collapsed, low contrast
        $detailsRows = '';
        if ($imageMode !== '') {
            $detailsRows .= '<p><span class="font-medium text-gray-600">Image mode:</span> ' . $this->escapeHtml($imageMode) . '</p>';
        }
        $detailsRows .= '<p><span class="font-medium text-gray-600">Hosted review URL:</span> ' . $this->escapeHtml($reviewUrl === '#' ? 'Generated on send' : $reviewUrl) . '</p>';
        $detailsRows .= '<p><span class="font-medium text-gray-600">Tracking pixel:</span> ' . $this->escapeHtml($trackingUrl ?: 'Generated on send') . '</p>';

        $detailsBlock = '<details class="border-t border-gray-100 bg-gray-50">'
            . '<summary class="cursor-pointer px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 hover:bg-gray-100 select-none">Send details</summary>'
            . '<div class="px-4 pb-3 pt-1 space-y-0.5 text-[11px] text-gray-500 break-all">' . $detailsRows . '</div>'
            . '</details>';

        return $metaStrip . $body . $warningList . $detailsBlock;
    }

    private function styleEmbeddedHtml(string $html): string
    {
        $dom = $this->loadHtml($html);
        if (!$dom) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//img') as $node) {
            if ($node instanceof DOMElement) {
                $node->setAttribute('style', trim($node->getAttribute('style') . ';display:block;max-width:100%;height:auto;border-radius:8px;'));
            }
        }
        foreach ($xpath->query('//table') as $node) {
            if ($node instanceof DOMElement) {
                $node->setAttribute('style', trim($node->getAttribute('style') . ';width:100%;border-collapse:collapse;'));
            }
        }

        return $this->extractInnerHtml($dom);
    }

    private function transformImagesToLinks(string $html): string
    {
        $dom = $this->loadHtml($html);
        if (!$dom) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        $images = [];
        foreach ($xpath->query('//img') as $node) {
            if ($node instanceof DOMElement) {
                $images[] = $node;
            }
        }

        foreach ($images as $img) {
            $src = trim((string) $img->getAttribute('src'));
            if ($src === '') {
                continue;
            }

            $caption = $this->extractImageCaption($img);
            $wrapper = $dom->createElement('div');
            $wrapper->setAttribute('style', 'margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;');
            $p = $dom->createElement('p');
            $p->setAttribute('style', 'margin:0;');
            $a = $dom->createElement('a', 'View image');
            $a->setAttribute('href', $src);
            $a->setAttribute('target', '_blank');
            $a->setAttribute('rel', 'noopener');
            $a->setAttribute('style', 'color:#2563eb;font-weight:600;text-decoration:none;');
            $p->appendChild($a);
            $wrapper->appendChild($p);

            if ($caption !== '') {
                $captionNode = $dom->createElement('p', $caption);
                $captionNode->setAttribute('style', 'margin:6px 0 0;font-size:12px;line-height:1.5;color:#6b7280;');
                $wrapper->appendChild($captionNode);
            }

            $replaceNode = ($img->parentNode instanceof DOMElement && strtolower($img->parentNode->tagName) === 'figure')
                ? $img->parentNode
                : $img;
            $replaceNode->parentNode?->replaceChild($wrapper, $replaceNode);
        }

        return $this->extractInnerHtml($dom);
    }

    private function hasNonWordpressImages(string $html, string $siteUrl): bool
    {
        $dom = $this->loadHtml($html);
        if (!$dom) {
            return false;
        }

        $siteHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//img') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $src = trim((string) $node->getAttribute('src'));
            if ($src === '') {
                continue;
            }
            $host = strtolower((string) parse_url($src, PHP_URL_HOST));
            if ($host !== '' && $siteHost !== '' && $host === $siteHost) {
                continue;
            }
            if (str_contains($src, '/wp-content/uploads/')) {
                continue;
            }
            return true;
        }

        return false;
    }

    private function extractImageCaption(DOMElement $img): string
    {
        $caption = trim((string) ($img->getAttribute('alt') ?: $img->getAttribute('title')));
        $parent = $img->parentNode;
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'figure') {
            foreach ($parent->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'figcaption') {
                    $caption = trim($child->textContent);
                    break;
                }
            }
        }

        return $caption;
    }

    private function loadHtml(string $html): ?DOMDocument
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $wrapped = '<!DOCTYPE html><html><body><div id="email-root">' . $html . '</div></body></html>';
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prior = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML("<?xml encoding=\"UTF-8\">" . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prior);

        return $loaded ? $dom : null;
    }

    private function extractInnerHtml(DOMDocument $dom): string
    {
        $root = $dom->getElementById('email-root');
        if (!$root) {
            return '';
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    private function absolutizeHtmlUrls(string $html, string $baseUrl): string
    {
        $dom = $this->loadHtml($html);
        if (!$dom) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        foreach (['src', 'href'] as $attribute) {
            foreach ($xpath->query('//*[@' . $attribute . ']') as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $value = trim((string) $node->getAttribute($attribute));
                if ($value === '') {
                    continue;
                }
                $node->setAttribute($attribute, $this->absolutizeUrl($value, $baseUrl));
            }
        }

        return $this->extractInnerHtml($dom);
    }

    private function absolutizeUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('~^(?:https?:|mailto:|tel:|data:)~i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if ($baseUrl === '') {
            return $url;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    private function resolveWpFeaturedUrlFromArticle(PublishArticle $article): string
    {
        $images = is_array($article->wp_images) ? $article->wp_images : [];
        $featured = collect($images)->firstWhere('is_featured', true) ?: collect($images)->first();
        if (is_array($featured)) {
            return trim((string) ($featured['sizes']['full'] ?? $featured['sizes']['large'] ?? $featured['media_url'] ?? ''));
        }

        return '';
    }

    private function resolveSmtpMeta(): array
    {
        $service = trim((string) Setting::getValue("system_sender_service", "core-smtp")) ?: "core-smtp";
        $fromEmail = trim((string) Setting::getValue("from_email", ""));
        $fromName = (string) (Setting::getValue("from_name", "") ?: "Scale My Publication");

        if ($service !== "core-smtp") {
            return [
                "id" => null,
                "name" => strtoupper($service),
                "from_email" => $fromEmail,
                "from_name" => $fromName,
                "host" => $service === "smtp2go"
                    ? trim((string) Setting::getValue("smtp2go_smtp_server", "mail.smtp2go.com"))
                    : "api.brevo.com",
                "source" => "system_sender_service",
                "service" => $service,
            ];
        }

        $systemAccountId = (int) Setting::getValue("system_smtp_account_id", "0");
        if ($systemAccountId > 0) {
            $systemAccount = SmtpAccount::query()->where("id", $systemAccountId)->where("is_active", true)->first();
            if ($systemAccount) {
                return [
                    "id" => $systemAccount->id,
                    "name" => $systemAccount->name,
                    "from_email" => $systemAccount->from_email,
                    "from_name" => $systemAccount->from_name ?: $fromName,
                    "host" => $systemAccount->host,
                    "source" => "system_smtp_account",
                    "service" => "core-smtp",
                ];
            }
        }

        $account = SmtpAccount::query()->where("is_system_sender", true)->where("is_active", true)->first()
            ?: SmtpAccount::query()->where("is_default", true)->where("is_active", true)->first();
        if ($account) {
            return [
                "id" => $account->id,
                "name" => $account->name,
                "from_email" => $account->from_email,
                "from_name" => $account->from_name ?: $fromName,
                "host" => $account->host,
                "source" => "smtp_account",
                "service" => "core-smtp",
            ];
        }

        return [
            "id" => null,
            "name" => $fromName ?: "Settings fallback",
            "from_email" => $fromEmail,
            "from_name" => $fromName,
            "host" => "",
            "source" => "settings",
            "service" => "core-smtp",
        ];
    }


    private function logActivity(
        PublishArticle $article,
        ?User $actor,
        PublishArticleApprovalEmail $email,
        string $activityType,
        string $message,
        array $meta = [],
        ?bool $success = null,
    ): void {
        $this->articleActivity->record($article, [
            'created_by' => $actor?->id,
            'activity_group' => 'approval_email',
            'activity_type' => 'lifecycle',
            'stage' => 'approval_email',
            'substage' => $activityType,
            'status' => $email->status,
            'success' => $success,
            'message' => $message,
            'title' => $email->subject,
            'meta' => array_merge($meta, [
                'approval_email_id' => $email->id,
                'to' => $email->to_recipients,
                'cc' => $email->cc_recipients,
                'image_mode' => $email->image_mode,
                'public_token' => $email->public_token,
            ]),
        ]);
    }

    private function htmlToText(string $html): string
    {
        $text = str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], ["\n", "\n", "\n", "\n\n", "\n\n"], $html);
        $text = trim(strip_tags($text));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }

    private function escapeHtml(string $value): string
    {
        return e($value);
    }

    private function escapeAttribute(string $value): string
    {
        return e($value);
    }
}
