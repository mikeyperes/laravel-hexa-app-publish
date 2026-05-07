<?php

use hexa_app_publish\Publishing\Articles\Http\Controllers\DraftApprovalEmailPublicController;

Route::get('/article/articles/approval/{token}', [DraftApprovalEmailPublicController::class, 'show'])->name('publish.drafts.approval.public.show');
Route::get('/article/articles/approval/{token}/track', [DraftApprovalEmailPublicController::class, 'track'])->name('publish.drafts.approval.public.track');
Route::post('/article/articles/approval/{token}/review', [DraftApprovalEmailPublicController::class, 'review'])->name('publish.drafts.approval.public.review');
