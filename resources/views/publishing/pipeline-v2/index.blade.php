{{-- Publish Article Pipeline v2 — clean redesign of /article/publish.
     Reuses the existing publishPipeline() Alpine state machine, all AJAX
     endpoints, persistence layer, and partials. The entire view layer is
     rebuilt around a sticky header, left progress rail, single-panel
     right pane, and slide-in drawers. The original /article/publish URL
     is left untouched. --}}
@extends('layouts.app')
@section('title', 'Publish Article — #' . $draftId)
@section('header', 'Publish Article — #' . $draftId)

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

    /* Legacy step-card CSS for embedded inline-step partials (Step 3/4/5) */
    .pipeline-step-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .pipeline-step-toggle { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 1rem; text-align: left; border-radius: .75rem; transition: background-color 150ms ease, color 150ms ease; }
    .pipeline-step-toggle:hover { background: #f9fafb; }
    .pipeline-step-panel { padding: 0 1rem 1rem; }

    [x-cloak] { display: none !important; }
</style>
@endonce

<script>
    window.featuredSearchAttempted = window.featuredSearchAttempted || false;
    window.featuredImageSearch = window.featuredImageSearch || '';
</script>

<div class="v2-shell"
     x-data="publishPipeline()"
     data-publish-draft-id="{{ $draftId }}"
     x-init="
        if (typeof $data.featuredSearchAttempted === 'undefined') { $data.featuredSearchAttempted = false; }
        if (typeof $data.featuredImageSearch === 'undefined') { $data.featuredImageSearch = ''; }
        $watch('savingDraft', (v) => { window.dispatchEvent(new CustomEvent('hexa-save-status:publish-pipeline-v2', { detail: { type: v ? 'saving' : 'saved' } })); });
        $watch('saveError', (msg) => { if (msg) window.dispatchEvent(new CustomEvent('hexa-save-status:publish-pipeline-v2', { detail: { type: 'error', message: msg } })); });
        $watch('notification', (note) => { if (note && note.show && note.type === 'error' && note.message) window.dispatchEvent(new CustomEvent('hexa-save-status:publish-pipeline-v2', { detail: { type: 'error', message: note.message } })); });
        $watch('featuredPhoto', (v) => { if (v && (!$data.featuredImageSearch || !String($data.featuredImageSearch).trim())) { $data.featuredImageSearch = String(v.alt || v.name || v.filename || v.title || 'featured'); } });
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
            <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}" target="_blank" rel="noopener" class="v2-btn v2-btn-secondary text-red-700">Open Isolated Draft</a>
            <button type="button" @click="_clearDraftSessionConflict()" class="v2-btn v2-btn-ghost text-white hover:bg-red-700">Dismiss</button>
        </div>
    </div>

    {{-- ═══════════════════ STICKY HEADER ═══════════════════ --}}
    <header class="v2-header">
        <div class="px-6 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('publish.drafts.index') }}" class="text-sm text-gray-500 hover:text-gray-800 inline-flex items-center gap-1 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Articles
                </a>
                <span class="font-mono text-xs text-gray-400 flex-shrink-0">#{{ $draftId }}</span>
                <div class="min-w-0 flex items-center gap-2">
                    <template x-if="!titleEditing">
                        <button type="button" @click="startTitleEditing()" class="min-w-0 truncate rounded px-1 py-0.5 text-left font-semibold text-gray-900 hover:bg-gray-100" :title="articleTitle || draftState?.articleTitle || 'Untitled'">
                            <span x-text="(articleTitle || draftState?.articleTitle || 'Untitled')"></span>
                        </button>
                    </template>
                    <template x-if="titleEditing">
                        <input x-ref="articleTitleInput" type="text" x-model="titleEditValue" @keydown.enter.prevent="commitTitleEditing()" @keydown.escape.prevent="cancelTitleEditing()" @blur="commitTitleEditing()" class="min-w-[240px] max-w-[520px] rounded border border-blue-300 px-2 py-1 text-sm font-semibold text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </template>
                    <button type="button" @click="startTitleEditing()" x-show="!titleEditing" x-cloak class="v2-btn v2-btn-ghost p-1 text-gray-400 hover:text-gray-700" aria-label="Edit draft title">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                </div>
                <span x-show="currentArticleType" x-cloak class="v2-pill v2-pill-blue flex-shrink-0" x-text="formatLabel ? formatLabel(currentArticleType) : currentArticleType"></span>
                <span x-show="selectedSite?.name" x-cloak class="text-xs text-gray-500 flex-shrink-0">→ <span x-text="selectedSite?.name"></span></span>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <x-hexa-save-status channel="publish-pipeline-v2" label="Draft" />
                <button type="button" @click="saveDraftNow()" :disabled="savingDraft" class="v2-btn v2-btn-secondary">
                    <svg x-show="savingDraft" x-cloak class="v2-spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="savingDraft ? 'Saving…' : 'Save Draft'"></span>
                </button>
                <div class="relative" x-data="{open: false}" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="v2-btn v2-btn-ghost p-2" aria-label="More actions">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><circle cx="4" cy="10" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg w-56 py-1 z-50">
                        <a href="{{ route('publish.pipeline', ['spawn' => 1]) }}" target="_blank" rel="noopener" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Open Isolated Draft (new tab)
                        </a>
                        <a href="{{ route('publish.pipeline.legacy', ['id' => $draftId]) }}" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50">
                            View in legacy /article/publish-legacy
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

    <div class="bg-red-600 border-b border-red-700 text-white">
        <div class="px-6 py-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-semibold">Legacy builder is still available</p>
                <p class="text-xs text-red-100">Publish is now the primary builder. Use the legacy screen only for comparison or fallback while we finish parity.</p>
            </div>
            <a href="{{ route('publish.pipeline.legacy', ['id' => $draftId]) }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-medium text-white hover:bg-white/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                Open legacy /article/publish-legacy
            </a>
        </div>
    </div>
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
                <button type="button" data-master-activity-toggle @click="masterActivityLogOpen = !masterActivityLogOpen">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Activity Log
                    </span>
                    <span class="v2-pill v2-pill-gray" x-text="masterActivityLog.length || ''"></span>
                </button>
                <button type="button" data-email-drawer-toggle @click="emailDrawerOpen = !emailDrawerOpen">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Email
                    </span>
                    <span x-show="approvalEmailLogs && approvalEmailLogs.length > 0" x-cloak class="v2-pill v2-pill-gray" x-text="approvalEmailLogs.length"></span>
                </button>
                <a href="{{ route('publish.pipeline.legacy', ['id' => $draftId]) }}">
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

            {{-- Step 1: Select user — legacy partial extracted verbatim from original lines 121..159 --}}
            <template x-if="currentStep === 1">
                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <span class="v2-pill v2-pill-blue">Step 1 of 7</span>
                        <h2 class="font-bold text-lg text-gray-900">Select user</h2>
                        <x-hexa-tooltip mode="hover" title="Why pick a user?" label="?" widthClass="w-80" position="bottom">
                            The selected user determines which article presets and WordPress templates are available, and is recorded as the article's author for reporting.
                        </x-hexa-tooltip>
                    </div>
                    @include('app-publish::publishing.pipeline-v2._legacy-step-1')
                </div>
            </template>

            {{-- Step 2: Article configuration — legacy partial extracted verbatim from original lines 160..280 (preserves article-type select, preset edit, template edit, etc) --}}
            <template x-if="currentStep === 2">
                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <span class="v2-pill v2-pill-blue">Step 2 of 7</span>
                        <h2 class="font-bold text-lg text-gray-900">Article configuration</h2>
                        <x-hexa-tooltip mode="hover" title="What is article configuration?" label="?" widthClass="w-80" position="bottom">
                            Choose your article type, the WordPress destination, the AI preset, and the publish template. The article-type dropdown drives whether the rest of the wizard runs the press-release flow or the spin pipeline.
                        </x-hexa-tooltip>
                    </div>
                    @include('app-publish::publishing.pipeline-v2._legacy-step-2')
                </div>
            </template>

            {{-- ─── PHASE 2 — SOURCE ─── --}}

            {{-- Step 3: Submit press release (press-release types) OR gather sources (spin) --}}
            <template x-if="currentStep === 3">
                <div class="relative">
                    <div class="v2-section">
                        <div class="v2-section-head">
                            <div class="v2-section-title">
                                <span class="v2-pill v2-pill-blue">Step 3 of 7</span>
                                <span x-text="currentArticleType === 'press-release' ? 'Submit your press release' : 'Gather source articles'"></span>
                                <x-hexa-tooltip mode="hover" title="What to do here" label="?" widthClass="w-80" position="bottom">
                                    Press release: pick a subject and supply your raw content (paste, upload, Notion, or Google Docs). Other types: collect 1-N source articles for the AI to spin.
                                </x-hexa-tooltip>
                            </div>
                            <span x-show="completedSteps.includes(3)" x-cloak class="v2-pill v2-pill-green">Completed</span>
                        </div>
                        <div class="v2-section-body">
                            {{-- Full Step 3 inline body extracted verbatim from the original index.blade.php (lines 284..1168). Includes both press-release submit content and spin-pipeline source-gathering UI, type-gated internally. --}}
                            @include('app-publish::publishing.pipeline-v2._legacy-step-3')

                            <div class="v2-action-bar">
                                <button type="button" @click="goToStep(2)" class="v2-btn v2-btn-secondary">Back</button>
                                <button type="button"
                                        @click="completeStep(3); openStep(4); goToStep(4)"
                                        class="v2-btn v2-btn-primary">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="pressReleaseImportingEpisodeId || pressReleaseImportingBookId || pressReleaseLoadingPersonBooks" x-cloak class="absolute inset-0 z-30 flex items-center justify-center rounded-xl bg-white/85">
                        <div class="flex flex-col items-center gap-3 rounded-xl border border-purple-200 bg-white px-6 py-5 shadow-lg">
                            <svg class="h-8 w-8 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <p class="text-sm font-semibold text-purple-900" x-text="pressReleaseImportingEpisodeId ? 'Importing podcast episode from Notion…' : (pressReleaseImportingBookId ? 'Importing book from Notion…' : 'Loading author and related books from Notion…')"></p>
                            <p class="text-center text-xs text-gray-500" x-text="pressReleaseImportingEpisodeId ? 'Pulling guest, host, episode metadata, photos and Drive assets.' : (pressReleaseImportingBookId ? 'Pulling author, book metadata, photos and Drive assets.' : 'Pulling author profile, biography, related books, covers and asset links.')"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Step 4: Validate (press release) / Fetch sources (spin) --}}
            <template x-if="currentStep === 4">
                <div>
                    <div class="v2-section">
                        <div class="v2-section-head">
                            <div class="v2-section-title">
                                <span class="v2-pill v2-pill-blue">Step 4 of 7</span>
                                <span x-text="currentArticleType === 'press-release' ? 'Validate press release details' : 'Fetch source articles'"></span>
                                <x-hexa-tooltip mode="hover" title="What to do here" label="?" widthClass="w-80" position="bottom">
                                    Confirm and clean up the auto-detected fields before AI generation runs against them.
                                </x-hexa-tooltip>
                            </div>
                            <span x-show="completedSteps.includes(4)" x-cloak class="v2-pill v2-pill-green">Completed</span>
                        </div>
                        <div class="v2-section-body">
                            {{-- Full Step 4 inline body extracted verbatim from the original (lines 1169..1393). Press-release type runs press-release-validate-step internally; spin-pipeline type runs the inline fetch-articles-from-source UI. --}}
                            @include('app-publish::publishing.pipeline-v2._legacy-step-4')

                            <div class="v2-action-bar">
                                <button type="button" @click="goToStep(3)" class="v2-btn v2-btn-secondary">Back</button>
                                <button type="button"
                                        @click="handleStepFourPrimaryAction()"
                                        :disabled="checking || (!hasVerifiedSourceArticles() && sources.length === 0)"
                                        class="v2-btn v2-btn-primary">
                                    <span x-text="stepFourPrimaryLabel()"></span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ─── PHASE 3 — GENERATE ─── --}}

            {{-- Step 5: AI Spin (inline UI in original — legacy fallback for now) --}}
            <template x-if="currentStep === 5">
                <div>
                    <div class="v2-section">
                        <div class="v2-section-head">
                            <div class="v2-section-title">
                                <span class="v2-pill v2-pill-blue">Step 5 of 7</span>
                                AI spin
                                <x-hexa-tooltip mode="hover" title="AI spin" label="?" widthClass="w-80" position="bottom">
                                    Choose the AI model, optional custom prompt, and generation options. Spin will produce the article body and metadata.
                                </x-hexa-tooltip>
                            </div>
                            <span x-show="completedSteps.includes(5)" x-cloak class="v2-pill v2-pill-green">Completed</span>
                        </div>
                        <div class="v2-section-body">
                            {{-- Full Step 5 inline body extracted verbatim from the original (lines 1394..1711). AI Spin model picker, custom prompt, web research, and real-time generation. --}}
                            @include('app-publish::publishing.pipeline-v2._legacy-step-5')

                            <div class="v2-action-bar">
                                <button type="button" @click="goToStep(4)" class="v2-btn v2-btn-secondary">Back</button>
                                <button type="button"
                                        @click="completeStep(5); openStep(6); goToStep(6)"
                                        :disabled="!hasSpinOutput()"
                                        class="v2-btn v2-btn-primary">
                                    <span x-text="hasSpinOutput() ? 'Continue to Create Article' : 'Spin Article First'"></span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Step 6: Create Article (full partial — TinyMCE editor + metadata + photos) --}}
            <template x-if="currentStep === 6">
                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <span class="v2-pill v2-pill-blue">Step 6 of 7</span>
                        <h2 class="font-bold text-lg text-gray-900">Create the article</h2>
                        <x-hexa-tooltip mode="hover" title="Create article" label="?" widthClass="w-80" position="bottom">
                            Edit the AI-generated body in TinyMCE, fine-tune metadata (title, categories, tags, slug), and review inline photo placements.
                        </x-hexa-tooltip>
                    </div>
                    {{-- Existing partial renders its full step card with its own header + body --}}
                    @include('app-publish::publishing.pipeline.partials.create-article-step')
                </div>
            </template>

            {{-- ─── PHASE 4 — PUBLISH ─── --}}

            {{-- Step 7: Review & Publish (full partial — SEO preview + publish actions) --}}
            <template x-if="currentStep === 7">
                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <span class="v2-pill v2-pill-blue">Step 7 of 7</span>
                        <h2 class="font-bold text-lg text-gray-900">Review &amp; publish</h2>
                        <x-hexa-tooltip mode="hover" title="Review and publish" label="?" widthClass="w-80" position="bottom">
                            Final SEO check, scheduling, and the WordPress send. Choose Draft, Schedule, or Publish — every option has its own loader.
                        </x-hexa-tooltip>
                    </div>
                    @include('app-publish::publishing.pipeline.partials.review-publish-step')
                </div>
            </template>

        </main>
    </div>

    {{-- ═══════════════════ ACTIVITY DRAWER ═══════════════════ --}}
    <div x-cloak class="v2-drawer-overlay" x-bind:style="masterActivityLogOpen ? 'display:block;' : 'display:none;'" @click="masterActivityLogOpen = false"></div>
    <aside x-cloak data-master-activity-drawer x-bind:style="masterActivityLogOpen ? 'display:flex;' : 'display:none;'" class="v2-drawer">
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

    {{-- ═══════════════════ EMAIL DRAWER ═══════════════════ --}}
    <div x-cloak class="v2-drawer-overlay" x-bind:style="emailDrawerOpen ? 'display:block;' : 'display:none;'" @click="emailDrawerOpen = false"></div>
    <aside x-cloak data-email-drawer x-bind:style="(emailDrawerOpen ? 'display:flex;' : 'display:none;') + (emailDrawerWidth === 'L' ? ' width: min(880px, 92vw);' : emailDrawerWidth === 'XL' ? ' width: min(1280px, 96vw);' : '')" class="v2-drawer" :class="{ 'v2-drawer--wide': emailDrawerWidth === 'L', 'v2-drawer--full': emailDrawerWidth === 'XL' }">
        @include('app-publish::publishing.pipeline-v2._email-drawer')
    </aside>

    {{-- ═══════════════════ EXISTING PARTIALS (overlays + mixins, untouched) ═══════════════════ --}}
    @include('app-publish::publishing.pipeline.partials.overlays')
    @include('app-publish::partials.site-connection-mixin')
    @include('app-publish::partials.preset-fields-mixin')

    {{-- ═══════════════════ ALPINE RUNTIME (extracted verbatim from original) ═══════════════════ --}}
    @include('app-publish::publishing.pipeline-v2._pipeline-runtime')

</div>

@endsection
