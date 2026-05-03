{{-- Publish Article Pipeline v2 — clean redesign of /article/publish.
     Reuses the existing publishPipeline() Alpine state machine, all AJAX
     endpoints, persistence layer, and partials. The entire view layer is
     rebuilt around a sticky header, left progress rail, single-panel
     right pane, and slide-in drawers. The original /article/publish URL
     is left untouched. --}}
@extends('layouts.app')
@section('title', 'Publish Article v2 — #' . $draftId)
@section('header', 'Publish Article v2 — #' . $draftId)

@section('content')

@once
<style>
    .v2-shell { min-height: calc(100vh - 4rem); display: flex; flex-direction: column; }
    .v2-header { position: sticky; top: 0; z-index: 30; background: #fff; border-bottom: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .v2-body { display: flex; flex: 1; min-height: 0; }
    .v2-rail { width: 288px; flex-shrink: 0; border-right: 1px solid #e5e7eb; background: #f9fafb; padding: 1rem; overflow-y: auto; max-height: calc(100vh - 4rem); position: sticky; top: 4rem; align-self: flex-start; }
    .v2-pane { flex: 1; min-width: 0; padding: 1.5rem 2rem; }

    .v2-phase { margin-bottom: 1.25rem; }
    .v2-phase-label { display: flex; align-items: center; gap: .5rem; padding: 0 .5rem .25rem; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; }
    .v2-phase-num { display: inline-flex; align-items: center; justify-content: center; width: 1.25rem; height: 1.25rem; border-radius: 9999px; background: #e5e7eb; color: #374151; font-size: .65rem; font-weight: 700; }
    .v2-phase.is-current .v2-phase-num { background: #2563eb; color: #fff; }
    .v2-phase.is-done .v2-phase-num { background: #16a34a; color: #fff; }

    .v2-substep { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .5rem .75rem; border-radius: .5rem; font-size: .85rem; color: #4b5563; transition: background-color 100ms; cursor: pointer; border: 0; background: transparent; text-align: left; }
    .v2-substep:hover { background: #fff; color: #111827; }
    .v2-substep.is-current { background: #fff; color: #1d4ed8; font-weight: 600; box-shadow: 0 0 0 1px #bfdbfe inset; }
    .v2-substep.is-done { color: #15803d; }
    .v2-substep.is-locked { color: #9ca3af; cursor: not-allowed; padding-top: .25rem; padding-bottom: .25rem; font-size: .75rem; }
    .v2-substep.is-locked:hover { background: transparent; color: #9ca3af; }
    .v2-substep .v2-dot { display: inline-flex; align-items: center; justify-content: center; width: 1rem; height: 1rem; border-radius: 9999px; background: #e5e7eb; color: #6b7280; font-size: .65rem; flex-shrink: 0; }
    .v2-substep.is-current .v2-dot { background: #2563eb; color: #fff; }
    .v2-substep.is-done .v2-dot { background: #16a34a; color: #fff; }

    .v2-rail-footer { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; display: flex; flex-direction: column; gap: .375rem; }
    .v2-rail-footer button, .v2-rail-footer a { display: flex; align-items: center; justify-content: space-between; gap: .5rem; width: 100%; padding: .5rem .75rem; border-radius: .5rem; font-size: .8rem; color: #4b5563; background: transparent; border: 0; text-align: left; cursor: pointer; transition: background-color 100ms; }
    .v2-rail-footer button:hover, .v2-rail-footer a:hover { background: #fff; color: #111827; }

    .v2-section { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04); margin-bottom: 1rem; overflow: hidden; }
    .v2-section-head { padding: 1rem 1.25rem; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
    .v2-section-title { display: flex; align-items: center; gap: .5rem; font-size: .95rem; font-weight: 700; color: #111827; }
    .v2-section-body { padding: 1.25rem; }

    .v2-field { margin-bottom: 1.25rem; }
    .v2-field-label { display: flex; align-items: center; gap: .375rem; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .375rem; }
    .v2-field-help { font-size: .7rem; color: #6b7280; margin-top: .25rem; }

    .v2-action-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e5e7eb; padding: 1rem 2rem; margin: 1.5rem -2rem -1.5rem -2rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }

    .v2-btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .5rem 1rem; border-radius: .5rem; font-size: .85rem; font-weight: 600; transition: background-color 100ms, opacity 100ms; cursor: pointer; border: 1px solid transparent; }
    .v2-btn:disabled { opacity: .5; cursor: not-allowed; }
    .v2-btn-primary { background: #2563eb; color: #fff; }
    .v2-btn-primary:hover:not(:disabled) { background: #1d4ed8; }
    .v2-btn-secondary { background: #fff; color: #374151; border-color: #d1d5db; }
    .v2-btn-secondary:hover:not(:disabled) { background: #f9fafb; }
    .v2-btn-ghost { background: transparent; color: #6b7280; }
    .v2-btn-ghost:hover { color: #111827; background: #f3f4f6; }

    .v2-spinner { width: 1rem; height: 1rem; animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .v2-pill { display: inline-flex; align-items: center; gap: .25rem; padding: .125rem .5rem; border-radius: 9999px; font-size: .7rem; font-weight: 600; }
    .v2-pill-blue { background: #dbeafe; color: #1d4ed8; }
    .v2-pill-amber { background: #fef3c7; color: #92400e; }
    .v2-pill-green { background: #dcfce7; color: #166534; }
    .v2-pill-red { background: #fee2e2; color: #991b1b; }
    .v2-pill-gray { background: #f3f4f6; color: #4b5563; }

    .v2-drawer { position: fixed; right: 0; top: 0; bottom: 0; width: min(560px, 90vw); background: #fff; box-shadow: -8px 0 24px rgba(0,0,0,.08); z-index: 60; display: flex; flex-direction: column; }
    .v2-drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 55; }

    .v2-tab-lock { background: #b91c1c; color: #fff; padding: .75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }

    [x-cloak] { display: none !important; }
</style>
@endonce

<div class="v2-shell"
     x-data="publishPipeline()"
     data-publish-draft-id="{{ $draftId }}"
     x-init="
        $watch('savingDraft', (v) => { window.dispatchEvent(new CustomEvent('hexa-save-status:publish-pipeline-v2', { detail: { type: v ? 'saving' : 'saved' } })); });
        $watch('saveError', (msg) => { if (msg) window.dispatchEvent(new CustomEvent('hexa-save-status:publish-pipeline-v2', { detail: { type: 'error', message: msg } })); });
     "
     @hexa-form-changed.window="
        if ($event.detail.component_id === 'article-preset-form') {
            $data.template_overrides[$event.detail.field] = $event.detail.value;
            $data.template_dirty[$event.detail.field] = true;
            $data.savePipelineState();
        }
     ">

    {{-- ═══════════════════ TAB-LOCK BANNER (hard, top of page) ═══════════════════ --}}
    <div x-show="draftSessionConflict" x-cloak class="v2-tab-lock">
        <div class="flex items-center gap-3 min-w-0">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="min-w-0">
                <p class="font-semibold text-sm">Another tab owns this draft</p>
                <p class="text-xs text-red-100" x-text="draftSessionConflict?.message || 'Autosave is paused in this tab to avoid overwriting changes from the active tab.'"></p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="{{ route('publish.pipeline.v2', ['spawn' => 1]) }}" target="_blank" rel="noopener" class="v2-btn v2-btn-secondary text-red-700">Open Isolated Draft</a>
            <button type="button" @click="_clearDraftSessionConflict()" class="v2-btn v2-btn-ghost text-white hover:bg-red-700">Dismiss</button>
        </div>
    </div>

    {{-- ═══════════════════ STICKY HEADER ═══════════════════ --}}
    <header class="v2-header">
        <div class="px-6 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('publish.articles.index') }}" class="text-sm text-gray-500 hover:text-gray-800 inline-flex items-center gap-1 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Articles
                </a>
                <span class="font-mono text-xs text-gray-400 flex-shrink-0">#{{ $draftId }}</span>
                <span class="font-semibold text-gray-900 truncate" x-text="(articleTitle || draftState?.articleTitle || 'Untitled')"></span>
                <span x-show="currentArticleType" x-cloak class="v2-pill v2-pill-blue flex-shrink-0" x-text="formatLabel ? formatLabel(currentArticleType) : currentArticleType"></span>
                <span x-show="selectedSite?.name" x-cloak class="text-xs text-gray-500 flex-shrink-0">→ <span x-text="selectedSite?.name"></span></span>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <x-hexa-save-status channel="publish-pipeline-v2" label="Draft" />
                <div class="relative" x-data="{open: false}" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="v2-btn v2-btn-ghost p-2" aria-label="More actions">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><circle cx="4" cy="10" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg w-56 py-1 z-50">
                        <a href="{{ route('publish.pipeline.v2', ['spawn' => 1]) }}" target="_blank" rel="noopener" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Open Isolated Draft (new tab)
                        </a>
                        <a href="{{ route('publish.pipeline', ['id' => $draftId]) }}" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50">
                            View in legacy /article/publish
                        </a>
                        <hr class="my-1 border-gray-100">
                        <button type="button"
                                @click="if (confirm('Clear this draft and start over? This cannot be undone.')) { clearPipeline(); open = false; }"
                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            Clear & Start Over
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- ═══════════════════ TWO-COLUMN BODY ═══════════════════ --}}
    <div class="v2-body">

        {{-- ────────────── LEFT RAIL ────────────── --}}
        <aside class="v2-rail">
            {{-- Phase 1: Setup --}}
            <div class="v2-phase" :class="{
                    'is-current': [1,2].includes(currentStep),
                    'is-done': completedSteps.includes(1) && completedSteps.includes(2)
                }">
                <div class="v2-phase-label">
                    <span class="v2-phase-num">1</span>
                    <span>Setup</span>
                    <x-hexa-tooltip mode="emoji" emoji="?" title="Setup phase" position="right">
                        Pick the user this draft belongs to and configure the article type, site, and template.
                    </x-hexa-tooltip>
                </div>
                <button type="button" @click="goToStep(1)"
                        class="v2-substep"
                        :class="{
                            'is-current': currentStep === 1,
                            'is-done': completedSteps.includes(1)
                        }">
                    <span class="v2-dot">
                        <template x-if="completedSteps.includes(1)"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></template>
                        <template x-if="!completedSteps.includes(1)"><span>1</span></template>
                    </span>
                    Select user
                </button>
                <button type="button" @click="goToStep(2)"
                        :disabled="!isStepAccessible(2)"
                        class="v2-substep"
                        :class="{
                            'is-current': currentStep === 2,
                            'is-done': completedSteps.includes(2),
                            'is-locked': !isStepAccessible(2) && !completedSteps.includes(2)
                        }">
                    <span class="v2-dot">
                        <template x-if="completedSteps.includes(2)"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></template>
                        <template x-if="!completedSteps.includes(2)"><span>2</span></template>
                    </span>
                    Article configuration
                </button>
            </div>

            {{-- Phase 2: Source --}}
            <div class="v2-phase" :class="{
                    'is-current': [3,4].includes(currentStep),
                    'is-done': completedSteps.includes(3) && completedSteps.includes(4)
                }">
                <div class="v2-phase-label">
                    <span class="v2-phase-num">2</span>
                    <span>Source</span>
                    <x-hexa-tooltip mode="emoji" emoji="?" title="Source phase" position="right">
                        Press releases: submit your subject, content, and Notion context. Other types: gather source articles to spin from.
                    </x-hexa-tooltip>
                </div>
                <button type="button" @click="goToStep(3)"
                        :disabled="!isStepAccessible(3)"
                        class="v2-substep"
                        :class="{
                            'is-current': currentStep === 3,
                            'is-done': completedSteps.includes(3),
                            'is-locked': !isStepAccessible(3) && !completedSteps.includes(3)
                        }">
                    <span class="v2-dot"><template x-if="completedSteps.includes(3)"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></template><template x-if="!completedSteps.includes(3)"><span>3</span></template></span>
                    <span x-text="currentArticleType === 'press-release' ? 'Submit press release' : 'Gather sources'"></span>
                </button>
                <button type="button" @click="goToStep(4)"
                        x-show="currentArticleType !== 'press-release'"
                        x-cloak
                        :disabled="!isStepAccessible(4)"
                        class="v2-substep"
                        :class="{
                            'is-current': currentStep === 4,
                            'is-done': completedSteps.includes(4),
                            'is-locked': !isStepAccessible(4) && !completedSteps.includes(4)
                        }">
                    <span class="v2-dot"><template x-if="completedSteps.includes(4)"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></template><template x-if="!completedSteps.includes(4)"><span>4</span></template></span>
                    Fetch articles
                </button>
            </div>

            {{-- Phase 3: Generate --}}
            <div class="v2-phase" :class="{
                    'is-current': [5,6].includes(currentStep),
                    'is-done': completedSteps.includes(5) && completedSteps.includes(6)
                }">
                <div class="v2-phase-label">
                    <span class="v2-phase-num">3</span>
                    <span>Generate</span>
                    <x-hexa-tooltip mode="emoji" emoji="?" title="Generate phase" position="right">
                        AI spin and final article assembly. Choose your AI model, write a prompt, generate the body and metadata.
                    </x-hexa-tooltip>
                </div>
                <button type="button" @click="goToStep(5)"
                        :disabled="!isStepAccessible(5)"
                        class="v2-substep"
                        :class="{
                            'is-current': currentStep === 5,
                            'is-done': completedSteps.includes(5),
                            'is-locked': !isStepAccessible(5) && !completedSteps.includes(5)
                        }">
                    <span class="v2-dot"><template x-if="completedSteps.includes(5)"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></template><template x-if="!completedSteps.includes(5)"><span>5</span></template></span>
                    AI spin
                </button>
                <button type="button" @click="goToStep(6)"
                        :disabled="!isStepAccessible(6)"
                        class="v2-substep"
                        :class="{
                            'is-current': currentStep === 6,
                            'is-done': completedSteps.includes(6),
                            'is-locked': !isStepAccessible(6) && !completedSteps.includes(6)
                        }">
                    <span class="v2-dot"><template x-if="completedSteps.includes(6)"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></template><template x-if="!completedSteps.includes(6)"><span>6</span></template></span>
                    Create article
                </button>
            </div>

            {{-- Phase 4: Publish --}}
            <div class="v2-phase" :class="{
                    'is-current': currentStep === 7,
                    'is-done': completedSteps.includes(7)
                }">
                <div class="v2-phase-label">
                    <span class="v2-phase-num">4</span>
                    <span>Publish</span>
                    <x-hexa-tooltip mode="emoji" emoji="?" title="Publish phase" position="right">
                        Final review, SEO check, and push to WordPress as draft, scheduled, or live.
                    </x-hexa-tooltip>
                </div>
                <button type="button" @click="goToStep(7)"
                        :disabled="!isStepAccessible(7)"
                        class="v2-substep"
                        :class="{
                            'is-current': currentStep === 7,
                            'is-done': completedSteps.includes(7),
                            'is-locked': !isStepAccessible(7) && !completedSteps.includes(7)
                        }">
                    <span class="v2-dot"><template x-if="completedSteps.includes(7)"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></template><template x-if="!completedSteps.includes(7)"><span>7</span></template></span>
                    Review &amp; publish
                </button>
            </div>

            {{-- Rail footer: drawers --}}
            <div class="v2-rail-footer">
                <button type="button" @click="masterActivityLogOpen = !masterActivityLogOpen">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Activity Log
                    </span>
                    <span class="v2-pill v2-pill-gray" x-text="masterActivityLog.length || ''"></span>
                </button>
                <a href="{{ route('publish.pipeline', ['id' => $draftId]) }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Open in legacy
                    </span>
                </a>
            </div>
        </aside>

        {{-- ────────────── RIGHT PANE (phase router) ────────────── --}}
        <main class="v2-pane">

            {{-- ─── PHASE 1 — SETUP ─── --}}

            {{-- Step 1: Select user --}}
            <template x-if="currentStep === 1">
                <div>
                    <div class="v2-section">
                        <div class="v2-section-head">
                            <div class="v2-section-title">
                                <span class="v2-pill v2-pill-blue">Step 1 of 7</span>
                                Select the user this article is for
                                <x-hexa-tooltip mode="hover" title="Why pick a user?" label="?" position="right">
                                    The selected user determines which article presets and WordPress templates are available, and is recorded as the article's author for reporting.
                                </x-hexa-tooltip>
                            </div>
                        </div>
                        <div class="v2-section-body space-y-4">
                            <div class="v2-field">
                                <label class="v2-field-label">
                                    User
                                    <x-hexa-tooltip mode="emoji" emoji="?" title="User picker" position="right">
                                        Type a name or email. Search hits live users in your account.
                                    </x-hexa-tooltip>
                                </label>
                                <x-hexa-smart-search
                                    :url="route('publish.users.search')"
                                    placeholder="Search by name or email…"
                                    @hexa-search-selected="selectedUser = { id: $event.detail.item.id, name: $event.detail.item.name, email: $event.detail.item.email }; if (typeof saveDraft === 'function') saveDraft(true); if (typeof savePipelineState === 'function') savePipelineState();"
                                />
                                <p class="v2-field-help" x-show="selectedUser?.name" x-cloak>Selected: <strong x-text="selectedUser?.name"></strong></p>
                            </div>

                            <div class="v2-action-bar">
                                <span class="text-xs text-gray-500" x-show="!selectedUser" x-cloak>Pick a user to continue.</span>
                                <span></span>
                                <button type="button"
                                        :disabled="!selectedUser"
                                        @click="completeStep(1); openStep(2); goToStep(2)"
                                        class="v2-btn v2-btn-primary">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Step 2: Article configuration --}}
            <template x-if="currentStep === 2">
                <div>
                    <div class="v2-section">
                        <div class="v2-section-head">
                            <div class="v2-section-title">
                                <span class="v2-pill v2-pill-blue">Step 2 of 7</span>
                                Article configuration
                                <x-hexa-tooltip mode="hover" title="What is article configuration?" label="?" position="right">
                                    Choose which type of article you're producing, where it'll be published, and which preset/template controls the AI generation.
                                </x-hexa-tooltip>
                            </div>
                        </div>
                        <div class="v2-section-body space-y-5">

                            <div class="v2-field">
                                <label class="v2-field-label">
                                    Article preset
                                    <x-hexa-tooltip mode="emoji" emoji="?" title="Article presets" position="right">
                                        A preset bundles your article-type, prompt template, AI model defaults, and writing style. Pick one to drive the rest of the configuration.
                                    </x-hexa-tooltip>
                                </label>
                                <select x-model="selectedPresetId"
                                        @change="onPresetChange ? onPresetChange() : null"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                    <option value="">— Select a preset —</option>
                                    <template x-for="p in (presets || [])" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                                <p class="v2-field-help" x-show="selectedPreset?.article_type" x-cloak>
                                    Article type: <strong x-text="selectedPreset?.article_type"></strong>
                                </p>
                            </div>

                            <div class="v2-field">
                                <label class="v2-field-label">
                                    Publish destination
                                    <x-hexa-tooltip mode="emoji" emoji="?" title="WordPress destination" position="right">
                                        Choose which connected WordPress site this article will be sent to. Connection status appears below the picker.
                                    </x-hexa-tooltip>
                                </label>
                                <select x-model="selectedSiteId"
                                        @change="onSiteChange ? onSiteChange() : null"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                    <option value="">— Select a site —</option>
                                    <template x-for="s in (sites || [])" :key="s.id">
                                        <option :value="s.id" x-text="s.name + (s.url ? ' · ' + s.url : '')"></option>
                                    </template>
                                </select>
                                @include('app-publish::publishing.pipeline.partials.site-connection-status')
                            </div>

                            <div class="v2-field">
                                <label class="v2-field-label">
                                    WordPress template
                                    <x-hexa-tooltip mode="emoji" emoji="?" title="WordPress templates" position="right">
                                        A template defines author, default categories, and other WordPress-side defaults applied at publish time.
                                    </x-hexa-tooltip>
                                </label>
                                <select x-model="selectedTemplateId"
                                        @change="onTemplateChange ? onTemplateChange() : null"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                    <option value="">— Select a template —</option>
                                    <template x-for="t in (templates || [])" :key="t.id">
                                        <option :value="t.id" x-text="t.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="v2-action-bar">
                                <button type="button" @click="goToStep(1)" class="v2-btn v2-btn-secondary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                    Back
                                </button>
                                <button type="button"
                                        :disabled="!selectedPresetId || !selectedSiteId"
                                        @click="completeStep(2); openStep(3); goToStep(3)"
                                        class="v2-btn v2-btn-primary">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ─── PHASES 2 / 3 / 4 — TRANSITIONAL ─── --}}
            {{-- These phases include the existing functional partials inside the new shell.
                 Subsequent commits will rebuild each phase's UI to match Setup's quality. --}}

            <template x-if="currentStep === 3 || currentStep === 4 || currentStep === 5 || currentStep === 6 || currentStep === 7">
                <div>
                    <div class="v2-section">
                        <div class="v2-section-head">
                            <div class="v2-section-title">
                                <span class="v2-pill v2-pill-blue" x-text="'Step ' + currentStep + ' of 7'"></span>
                                <span x-text="(stepLabels && stepLabels[currentStep - 1]) || 'Working step'"></span>
                            </div>
                            <span class="v2-pill v2-pill-amber">Transitional view</span>
                        </div>
                        <div class="v2-section-body">
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 mb-4 text-sm text-amber-900">
                                <p class="font-medium mb-1">Phase rebuild in progress</p>
                                <p>This step's panel still uses the legacy markup while the v2 redesign rolls out phase by phase. All underlying logic, autosave, and AJAX work normally. To finish your draft right now, jump back to the legacy view and you'll land on this exact step.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <a :href="'{{ route('publish.pipeline') }}?id={{ $draftId }}&step=' + currentStep" class="v2-btn v2-btn-primary">
                                    Continue this step in legacy view
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <button type="button" @click="goToStep(2)" class="v2-btn v2-btn-secondary">Back to Article configuration</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

        </main>
    </div>

    {{-- ═══════════════════ ACTIVITY DRAWER ═══════════════════ --}}
    <div x-show="masterActivityLogOpen" x-cloak class="v2-drawer-overlay" @click="masterActivityLogOpen = false"></div>
    <aside x-show="masterActivityLogOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="v2-drawer">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Activity log</h3>
            <button type="button" @click="masterActivityLogOpen = false" class="v2-btn v2-btn-ghost p-1.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
            @include('app-publish::publishing.pipeline.partials.master-activity-log')
        </div>
    </aside>

    {{-- ═══════════════════ EXISTING PARTIALS (overlays + mixins, untouched) ═══════════════════ --}}
    @include('app-publish::publishing.pipeline.partials.overlays')
    @include('app-publish::partials.site-connection-mixin')
    @include('app-publish::partials.preset-fields-mixin')

    {{-- ═══════════════════ ALPINE RUNTIME (extracted verbatim from original) ═══════════════════ --}}
    @include('app-publish::publishing.pipeline-v2._pipeline-runtime')

</div>

@endsection
