{{-- Article Editor with TinyMCE + AI tools --}}
@extends('layouts.app')
@section('title', 'Edit: ' . ($article->title ?? 'Untitled'))
@section('header', 'Article Editor')

@section('content')
<div x-data="articleEditor()">

    {{-- Top bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('publish.articles.show', $article->id) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <span class="text-xs font-mono text-gray-400">{{ $article->article_id }}</span>
            @if($article->status === 'published')
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Published</span>
            @elseif($article->status === 'completed')
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600">Completed</span>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600" x-text="form.status.charAt(0).toUpperCase() + form.status.slice(1)"></span>
            @endif
            <span class="text-xs text-gray-400" x-show="form.word_count > 0" x-text="form.word_count.toLocaleString() + ' words'"></span>
        </div>
        <div class="flex items-center gap-2">
            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="saving ? 'Saving...' : 'Save'"></span>
            </button>
            @if(!in_array($article->status, ['published', 'completed']))
            <button @click="publish()" :disabled="publishing" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60 inline-flex items-center gap-2">
                <svg x-show="publishing" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="publishing ? 'Publishing...' : 'Publish to WordPress'"></span>
            </button>
            @endif
        </div>
    </div>

    {{-- Result banner --}}
    <div x-show="resultMessage" x-cloak class="rounded-lg px-4 py-3 text-sm mb-4" :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
        <span x-text="resultMessage"></span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

        {{-- Main editor (3/4) --}}
        <div class="lg:col-span-3 space-y-4">
            {{-- Title --}}
            <input type="text" x-model="form.title" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg font-semibold focus:ring-blue-500 focus:border-blue-500" placeholder="Article title">

            {{-- TinyMCE editor --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <textarea id="article-editor">{{ $article->body }}</textarea>
            </div>

            {{-- Excerpt --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <label class="block text-xs font-medium text-gray-500 uppercase mb-2">Excerpt</label>
                <textarea x-model="form.excerpt" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Short summary for the article..."></textarea>
            </div>
        </div>

        {{-- Sidebar tools (1/4) --}}
        <div class="space-y-4">

            {{-- Article settings --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-3">
                <h4 class="text-xs font-medium text-gray-500 uppercase">Settings</h4>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Status</label>
                    <select x-model="form.status" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        @foreach($articleTypes as $s)
                        @endforeach
                        <option value="drafting">Drafting</option>
                        <option value="review">Review</option>
                        <option value="ready">Ready</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Article Type</label>
                    <select x-model="form.article_type" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        <option value="">— None —</option>
                        @foreach($articleTypes as $type)
                            <option value="{{ $type }}">{{ ucwords(str_replace('-', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Delivery</label>
                    <select x-model="form.delivery_mode" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        <option value="draft-local">Draft Local</option>
                        <option value="draft-wordpress">Draft WordPress</option>
                        <option value="auto-publish">Auto Publish</option>
                        <option value="review">Review</option>
                        <option value="notify">Notify</option>
                    </select>
                </div>
            </div>

            {{-- AI Tools --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-3">
                <h4 class="text-xs font-medium text-gray-500 uppercase">AI Tools</h4>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">AI Engine</label>
                    <select x-model="aiEngine" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        @foreach($aiEngines as $engine)
                            <option value="{{ $engine }}">{{ ucfirst($engine) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Instruction</label>
                    <textarea x-model="aiInstruction" rows="3" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono" placeholder="e.g. optimize for SEO, fix grammar, adjust tone to casual..."></textarea>
                </div>
                <button @click="spin()" :disabled="spinning" class="w-full bg-purple-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                    <svg x-show="spinning" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="spinning ? 'Processing...' : 'Run AI'"></span>
                </button>
            </div>

            {{-- Quality checks --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-3">
                <h4 class="text-xs font-medium text-gray-500 uppercase">Quality Checks</h4>
                <button @click="aiCheck()" :disabled="checking" class="w-full bg-orange-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-orange-600 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                    <svg x-show="checking" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="checking ? 'Checking...' : 'AI Detection Check'"></span>
                </button>
                <button @click="seoCheck()" :disabled="seoChecking" class="w-full bg-teal-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-teal-700 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                    <svg x-show="seoChecking" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="seoChecking ? 'Analyzing...' : 'SEO Analysis'"></span>
                </button>

                {{-- Scores display --}}
                <div class="text-sm space-y-1 pt-2 border-t border-gray-100">
                    <div class="flex justify-between">
                        <span class="text-gray-500">AI Detection:</span>
                        <span :class="scores.ai !== null ? (scores.ai < 30 ? 'text-green-600 font-medium' : (scores.ai < 60 ? 'text-yellow-600 font-medium' : 'text-red-600 font-medium')) : 'text-gray-400'" x-text="scores.ai !== null ? scores.ai + '%' : '—'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">SEO Score:</span>
                        <span :class="scores.seo !== null ? (scores.seo >= 70 ? 'text-green-600 font-medium' : (scores.seo >= 40 ? 'text-yellow-600 font-medium' : 'text-red-600 font-medium')) : 'text-gray-400'" x-text="scores.seo !== null ? scores.seo : '—'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Words:</span>
                        <span class="text-gray-700" x-text="form.word_count > 0 ? form.word_count.toLocaleString() : '—'"></span>
                    </div>
                </div>
            </div>

            {{-- Meta --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-xs text-gray-400 space-y-1">
                <p>Site: {{ $article->site->name }}</p>
                @if($article->campaign)<p>Campaign: {{ $article->campaign->name }}</p>@endif
                @if($article->template)<p>Template: {{ $article->template->name }}</p>@endif
                <p>Created: {{ $article->created_at->format('M j, Y g:i A') }}</p>
                @if($article->published_at)<p>Published: {{ $article->published_at->format('M j, Y g:i A') }}</p>@endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- TinyMCE CDN (free, no API key needed for self-hosted) --}}
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
function articleEditor() {
    return {
        form: {
            title: @json($article->title ?? ''),
            body: @json($article->body ?? ''),
            excerpt: @json($article->excerpt ?? ''),
            status: @json($article->status),
            article_type: @json($article->article_type ?? ''),
            delivery_mode: @json($article->delivery_mode ?? 'review'),
            word_count: @json($article->word_count ?? 0),
        },
        aiEngine: 'anthropic',
        aiInstruction: '',
        scores: {
            ai: @json($article->ai_detection_score),
            seo: @json($article->seo_score),
        },
        saving: false, publishing: false, spinning: false, checking: false, seoChecking: false,
        resultMessage: '', resultSuccess: false,
        editor: null,
        init() {
            this.$nextTick(() => {
                tinymce.init({
                    selector: '#article-editor',
                    height: 500,
                    menubar: 'file edit view insert format tools table',
                    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount',
                    toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | removeformat code fullscreen | wordcount',
                    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; line-height: 1.6; }',
                    setup: (editor) => {
                        this.editor = editor;
                        editor.on('input change', () => {
                            this.form.body = editor.getContent();
                            const text = editor.getContent({ format: 'text' });
                            this.form.word_count = text.trim() ? text.trim().split(/\s+/).length : 0;
                        });
                    }
                });
            });
        },
        getBody() {
            if (this.editor) this.form.body = this.editor.getContent();
            return this.form.body;
        },
        async save() {
            this.saving = true; this.resultMessage = '';
            this.getBody();
            try {
                const res = await fetch('{{ route("publish.articles.update", $article->id) }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message || 'Saved.';
            } catch (e) { this.resultSuccess = false; this.resultMessage = 'Error: ' + e.message; }
            this.saving = false;
        },
        async publish() {
            if (!confirm('Publish this article to WordPress?')) return;
            this.publishing = true; this.resultMessage = '';
            await this.save();
            try {
                const res = await fetch('{{ route("publish.articles.publish", $article->id) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message;
                if (data.success) setTimeout(() => location.reload(), 1000);
            } catch (e) { this.resultSuccess = false; this.resultMessage = 'Error: ' + e.message; }
            this.publishing = false;
        },
        async spin() {
            this.spinning = true; this.resultMessage = '';
            await this.save();
            try {
                const res = await fetch('{{ route("publish.articles.spin", $article->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ ai_engine: this.aiEngine, instruction: this.aiInstruction })
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message;
                if (data.success && data.body && this.editor) {
                    this.editor.setContent(data.body);
                    this.form.body = data.body;
                    this.form.word_count = data.word_count || 0;
                }
            } catch (e) { this.resultSuccess = false; this.resultMessage = 'Error: ' + e.message; }
            this.spinning = false;
        },
        async aiCheck() {
            this.checking = true; this.resultMessage = '';
            await this.save();
            try {
                const res = await fetch('{{ route("publish.articles.ai-check", $article->id) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message;
                if (data.score !== undefined) this.scores.ai = data.score;
            } catch (e) { this.resultSuccess = false; this.resultMessage = 'Error: ' + e.message; }
            this.checking = false;
        },
        async seoCheck() {
            this.seoChecking = true; this.resultMessage = '';
            await this.save();
            try {
                const res = await fetch('{{ route("publish.articles.seo-check", $article->id) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message;
                if (data.score !== undefined) this.scores.seo = data.score;
            } catch (e) { this.resultSuccess = false; this.resultMessage = 'Error: ' + e.message; }
            this.seoChecking = false;
        }
    };
}
</script>
@endpush
