@extends('layouts.app')
@section('title', 'Draft Approval Review')
@section('header', 'Draft Approval Review')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 py-6">
    @if(session('approval_review_status'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('approval_review_status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">Draft Approval</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $approvalEmail->subject }}</h1>
            <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-500">
                <span class="rounded-full bg-white px-3 py-1 border border-gray-200">Status: {{ $approvalEmail->statusLabel() }}</span>
                <span class="rounded-full bg-white px-3 py-1 border border-gray-200">Sent: {{ $approvalEmail->sent_at?->format('M j, Y g:i A T') ?? 'Not sent' }}</span>
                <span class="rounded-full bg-white px-3 py-1 border border-gray-200">Draft #{{ $article->id }}</span>
                <span class="rounded-full bg-white px-3 py-1 border border-gray-200">{{ $article->title ?: 'Untitled' }}</span>
            </div>
        </div>

        <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,2fr),minmax(320px,1fr)]">
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">Rendered Email Preview</div>
                    <div class="p-4 max-w-none prose prose-sm">{!! $approvalEmail->preview_html !!}</div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">Respond to this draft</h2>
                    <p class="mt-1 text-xs text-gray-500">Mark the draft reviewed or request changes. This writes back to the draft-level email log.</p>
                    <form method="POST" action="{{ route('publish.drafts.approval.public.review', ['token' => $approvalEmail->public_token]) }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Your name</label>
                            <input type="text" name="reviewer_name" value="{{ old('reviewer_name') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Your email</label>
                            <input type="email" name="reviewer_email" value="{{ old('reviewer_email') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Decision</label>
                            <select name="decision" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="approved" {{ old('decision') === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="changes_requested" {{ old('decision') === 'changes_requested' ? 'selected' : '' }}>Changes requested</option>
                                <option value="reviewed" {{ old('decision') === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Notes</label>
                            <textarea name="note" rows="6" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('note') }}</textarea>
                        </div>
                        @if($errors->any())
                            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit Review</button>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
                    <h2 class="text-sm font-semibold text-gray-900">Header Debug</h2>
                    <dl class="space-y-2 text-xs text-gray-600">
                        <div class="grid grid-cols-[110px,1fr] gap-3"><dt class="font-semibold text-gray-500">From</dt><dd>{{ $approvalEmail->from_name }} &lt;{{ $approvalEmail->from_email }}&gt;</dd></div>
                        <div class="grid grid-cols-[110px,1fr] gap-3"><dt class="font-semibold text-gray-500">Reply-To</dt><dd>{{ $approvalEmail->reply_to ?: '—' }}</dd></div>
                        <div class="grid grid-cols-[110px,1fr] gap-3"><dt class="font-semibold text-gray-500">To</dt><dd>{{ implode(', ', $approvalEmail->to_recipients ?? []) ?: '—' }}</dd></div>
                        <div class="grid grid-cols-[110px,1fr] gap-3"><dt class="font-semibold text-gray-500">CC</dt><dd>{{ implode(', ', $approvalEmail->cc_recipients ?? []) ?: '—' }}</dd></div>
                        <div class="grid grid-cols-[110px,1fr] gap-3"><dt class="font-semibold text-gray-500">Image mode</dt><dd>{{ $approvalEmail->image_mode }}</dd></div>
                        <div class="grid grid-cols-[110px,1fr] gap-3"><dt class="font-semibold text-gray-500">Viewed</dt><dd>{{ $approvalEmail->viewed_at?->format('M j, Y g:i A T') ?: 'Not yet' }}</dd></div>
                        <div class="grid grid-cols-[110px,1fr] gap-3"><dt class="font-semibold text-gray-500">Reviewed</dt><dd>{{ $approvalEmail->reviewed_at?->format('M j, Y g:i A T') ?: 'Not yet' }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
