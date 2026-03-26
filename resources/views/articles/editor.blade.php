{{-- Article Editor — WordPress-style TinyMCE editor --}}
@extends('layouts.app')
@section('title', $article ? 'Edit: ' . $article->title : 'Article Editor')
@section('header', $article ? 'Edit: ' . $article->title : 'Article Editor')

@section('content')
<div class="max-w-6xl mx-auto space-y-4" x-data="articleEditor()">

    {{-- Load Draft / Title bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Load Draft</label>
                <select x-model="loadDraftId" @change="loadDraft()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- New article --</option>
                    @foreach($drafts as $draft)
                        <option value="{{ $draft->id }}" {{ $article && $article->id === $draft->id ? 'selected' : '' }}>{{ $draft->title ?: 'Untitled' }} ({{ $draft->updated_at->format('M j, g:ia') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[300px]">
                <label class="block text-xs text-gray-500 mb-1">Title</label>
                <input type="text" x-model="articleTitle" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Article title...">
            </div>
            <div class="flex items-center gap-2">
                <button @click="saveArticle()" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                    <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="saving ? 'Saving...' : (articleId ? 'Save Changes' : 'Save as Draft')"></span>
                </button>
                <span x-show="saveResult" x-cloak x-transition class="text-xs" :class="saveSuccess ? 'text-green-600' : 'text-red-600'" x-text="saveResult"></span>
            </div>
        </div>
    </div>

    {{-- Editor --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <span>Words: <span class="font-medium text-gray-700" x-text="wordCount"></span></span>
                    <span>Reading time: <span class="font-medium text-gray-700" x-text="readingTime + ' min'"></span></span>
                </div>
                <button @click="toggleSource()" class="text-xs px-2 py-1 rounded border" :class="showSource ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                    <span x-text="showSource ? 'Visual' : 'HTML Source'"></span>
                </button>
            </div>

            {{-- TinyMCE container --}}
            <div x-show="!showSource">
                <textarea id="wp-editor"></textarea>
            </div>

            {{-- Source view --}}
            <div x-show="showSource" x-cloak>
                <textarea x-model="htmlSource" rows="30" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono bg-gray-900 text-green-400" @input="syncFromSource()"></textarea>
            </div>
        </div>
    </div>

    {{-- Configuration section --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <button @click="showConfig = !showConfig" class="flex items-center gap-2 w-full text-left">
            <svg class="w-4 h-4 text-gray-500 transition-transform" :class="showConfig ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-sm font-semibold text-gray-700">Editor Configuration</span>
        </button>
        <div x-show="showConfig" x-cloak class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" x-model="config.wpAutop" @change="reinitEditor()" class="rounded border-gray-300 text-blue-600">
                Auto-paragraph (wpautop style)
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" x-model="config.pasteCleanup" @change="reinitEditor()" class="rounded border-gray-300 text-blue-600">
                Paste from Word cleanup
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" x-model="config.tables" @change="reinitEditor()" class="rounded border-gray-300 text-blue-600">
                Table support
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" x-model="config.advancedLinks" @change="reinitEditor()" class="rounded border-gray-300 text-blue-600">
                Advanced links (title, target, nofollow)
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" x-model="config.shortcodes" @change="reinitEditor()" class="rounded border-gray-300 text-blue-600">
                Preserve [shortcodes]
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" x-model="config.wpStyles" @change="reinitEditor()" class="rounded border-gray-300 text-blue-600">
                WordPress typography CSS
            </label>
        </div>
    </div>
</div>
@endsection

@php $tinymceKey = $tinymceKey ?? ''; @endphp
@if($tinymceKey)
<script src="https://cdn.tiny.cloud/1/{{ $tinymceKey }}/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
@else
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
@endif

@push('scripts')
<script>
function articleEditor() {
    return {
        articleId: {{ $article ? $article->id : 'null' }},
        articleTitle: @json($article ? $article->title : ''),
        loadDraftId: '{{ $article ? $article->id : '' }}',
        saving: false,
        saveResult: '',
        saveSuccess: false,
        wordCount: 0,
        readingTime: 0,
        showSource: false,
        htmlSource: '',
        showConfig: false,
        editorInstance: null,

        config: {
            wpAutop: true,
            pasteCleanup: true,
            tables: true,
            advancedLinks: true,
            shortcodes: true,
            wpStyles: true,
        },

        init() {
            this.initEditor();
        },

        /**
         * @return void
         */
        initEditor() {
            const self = this;
            const plugins = ['lists', 'link', 'image', 'media', 'fullscreen', 'wordcount', 'code', 'searchreplace', 'autolink'];
            if (this.config.tables) plugins.push('table');
            if (this.config.pasteCleanup) plugins.push('paste');

            const toolbar = 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist | link image media | blockquote hr | removeformat code fullscreen'
                + (this.config.tables ? ' | table' : '');

            const wpCss = this.config.wpStyles ? `
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif; font-size: 16px; line-height: 1.75; color: #1a1a1a; max-width: 720px; margin: 0 auto; padding: 16px; }
                h1 { font-size: 2em; margin: 0.67em 0; } h2 { font-size: 1.5em; margin: 0.75em 0; } h3 { font-size: 1.25em; margin: 0.83em 0; }
                p { margin: 0 0 1.5em; } blockquote { border-left: 4px solid #ccc; margin: 1.5em 0; padding: 0.5em 1em; color: #555; }
                img { max-width: 100%; height: auto; } a { color: #0073aa; } table { border-collapse: collapse; width: 100%; } td, th { border: 1px solid #ddd; padding: 8px; }
            ` : '';

            tinymce.init({
                selector: '#wp-editor',
                height: 600,
                menubar: 'file edit view insert format tools table',
                plugins: plugins.join(' '),
                toolbar: toolbar,
                block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre; Blockquote=blockquote',
                content_style: wpCss,
                forced_root_block: self.config.wpAutop ? 'p' : '',
                paste_as_text: !self.config.pasteCleanup,
                paste_word_valid_elements: self.config.pasteCleanup ? 'p,b,strong,i,em,h1,h2,h3,h4,h5,h6,ul,ol,li,a[href],img[src|alt],blockquote,table,tr,td,th,thead,tbody,br' : '',
                link_default_target: '_blank',
                link_title: self.config.advancedLinks,
                rel_list: self.config.advancedLinks ? [
                    { title: 'None', value: '' },
                    { title: 'nofollow', value: 'nofollow' },
                    { title: 'nofollow noopener', value: 'nofollow noopener' },
                ] : [],
                valid_elements: self.config.shortcodes ? '*[*]' : undefined,
                extended_valid_elements: self.config.shortcodes ? '+div[*],+span[*]' : undefined,
                custom_elements: self.config.shortcodes ? '' : undefined,
                protect: self.config.shortcodes ? [/\[[\w]+[^\]]*\][\s\S]*?\[\/[\w]+\]/g, /\[[\w]+[^\]]*\/?\]/g] : [],
                setup(editor) {
                    self.editorInstance = editor;
                    editor.on('init', () => {
                        @if($article && $article->body)
                            editor.setContent(@json($article->body));
                        @endif
                        self.updateWordCount();
                    });
                    editor.on('input change keyup', () => {
                        self.updateWordCount();
                    });
                },
            });
        },

        /**
         * @return void
         */
        updateWordCount() {
            if (!this.editorInstance) return;
            const text = this.editorInstance.getContent({ format: 'text' });
            this.wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
            this.readingTime = Math.max(1, Math.ceil(this.wordCount / 200));
        },

        /**
         * @return void
         */
        toggleSource() {
            if (this.showSource) {
                // Switch back to visual — push source changes to TinyMCE
                if (this.editorInstance) {
                    this.editorInstance.setContent(this.htmlSource);
                    this.updateWordCount();
                }
            } else {
                // Switch to source — get current TinyMCE content
                if (this.editorInstance) {
                    this.htmlSource = this.editorInstance.getContent();
                }
            }
            this.showSource = !this.showSource;
        },

        /**
         * @return void
         */
        syncFromSource() {
            // Update word count from source
            const tmp = document.createElement('div');
            tmp.innerHTML = this.htmlSource;
            const text = tmp.textContent || '';
            this.wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
            this.readingTime = Math.max(1, Math.ceil(this.wordCount / 200));
        },

        /**
         * @return void
         */
        reinitEditor() {
            if (this.editorInstance) {
                const content = this.editorInstance.getContent();
                tinymce.remove('#wp-editor');
                this.editorInstance = null;
                this.$nextTick(() => {
                    this.initEditor();
                    // Restore content after reinit
                    setTimeout(() => {
                        if (this.editorInstance) this.editorInstance.setContent(content);
                    }, 500);
                });
            }
        },

        /**
         * @return void
         */
        async loadDraft() {
            if (!this.loadDraftId) {
                this.articleId = null;
                this.articleTitle = '';
                if (this.editorInstance) this.editorInstance.setContent('');
                this.updateWordCount();
                // Update URL
                history.replaceState(null, '', '{{ route("publish.editor") }}');
                return;
            }
            try {
                const resp = await fetch('/article/drafts/' + this.loadDraftId, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                if (data.success !== false) {
                    const article = data.article || data;
                    this.articleId = article.id;
                    this.articleTitle = article.title || '';
                    if (this.editorInstance) this.editorInstance.setContent(article.body || '');
                    this.updateWordCount();
                    // Update URL
                    history.replaceState(null, '', '/article/editor/' + article.id);
                }
            } catch (e) {
                this.saveResult = 'Error loading draft: ' + e.message;
                this.saveSuccess = false;
            }
        },

        /**
         * @return void
         */
        async saveArticle() {
            this.saving = true;
            this.saveResult = '';
            const body = this.showSource ? this.htmlSource : (this.editorInstance ? this.editorInstance.getContent() : '');

            try {
                const url = this.articleId
                    ? '/article/drafts/' + this.articleId
                    : '/article/drafts';
                const method = this.articleId ? 'PUT' : 'POST';

                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        title: this.articleTitle || 'Untitled',
                        body: body,
                        status: 'draft',
                    }),
                });
                const data = await resp.json();
                this.saveSuccess = data.success !== false;
                this.saveResult = data.message || (this.saveSuccess ? 'Saved.' : 'Failed.');
                if (this.saveSuccess && data.article) {
                    this.articleId = data.article.id || this.articleId;
                    history.replaceState(null, '', '/article/editor/' + this.articleId);
                }
                setTimeout(() => this.saveResult = '', 3000);
            } catch (e) {
                this.saveSuccess = false;
                this.saveResult = 'Error: ' + e.message;
            }
            this.saving = false;
        },
    };
}
</script>
@endpush
