<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticleApprovalEmail;
use hexa_app_publish\Publishing\Articles\Services\DraftApprovalEmailService;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DraftApprovalEmailPublicController extends Controller
{
    public function __construct(private DraftApprovalEmailService $approvalEmails) {}

    public function show(string $token): View
    {
        $email = $this->resolveEmail($token);
        $email = $this->approvalEmails->markViewed($email, 'page');

        return view('app-publish::publishing.articles.drafts.approval-review', [
            'approvalEmail' => $email,
            'approvalEmailLog' => $this->approvalEmails->serializeLog($email),
            'article' => $email->article,
        ]);
    }

    public function track(string $token): Response
    {
        $email = $this->resolveEmail($token);
        $this->approvalEmails->markViewed($email, 'pixel');

        return response(base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function review(Request $request, string $token): RedirectResponse
    {
        $email = $this->resolveEmail($token);
        $validated = $request->validate([
            'reviewer_name' => 'nullable|string|max:255',
            'reviewer_email' => 'nullable|email|max:255',
            'decision' => 'required|string|in:approved,changes_requested,reviewed',
            'note' => 'nullable|string|max:5000',
        ]);

        $this->approvalEmails->markReviewed($email, $validated);

        return redirect()
            ->route('publish.drafts.approval.public.show', ['token' => $token])
            ->with('approval_review_status', 'Review response recorded.');
    }

    private function resolveEmail(string $token): PublishArticleApprovalEmail
    {
        return PublishArticleApprovalEmail::query()
            ->with(['article.site', 'article.creator', 'sender'])
            ->where('public_token', $token)
            ->firstOrFail();
    }
}
