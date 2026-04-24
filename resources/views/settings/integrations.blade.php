{{-- Publish API key settings — pushed into @stack('integrations-modules') --}}

{{-- ═══ Pexels ═══ --}}
@php $pexelsKey = \hexa_core\Models\Setting::getValue('pexels_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('pexels', '{{ $pexelsKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Pexels</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Sign up at <a href="https://www.pexels.com/api/" target="_blank" class="text-blue-600 underline">pexels.com/api &#8599;</a> (free)</li>
            <li>Copy your API key from the dashboard</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Free tier: 200 requests/hour, 20,000/month. No attribution required.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'pexels_api_key', 'name' => 'Pexels'])</div>
    </template>
</div>

{{-- ═══ Unsplash ═══ --}}
@php $unsplashKey = \hexa_core\Models\Setting::getValue('unsplash_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('unsplash', '{{ $unsplashKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Unsplash</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Sign up at <a href="https://unsplash.com/developers" target="_blank" class="text-blue-600 underline">unsplash.com/developers &#8599;</a></li>
            <li>Create a new application</li>
            <li>Copy your Access Key (not Secret Key)</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Demo: 50 req/hour. Production: apply for 5,000 req/hour. Attribution required.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'unsplash_api_key', 'name' => 'Unsplash'])</div>
    </template>
</div>

{{-- ═══ Pixabay ═══ --}}
@php $pixabayKey = \hexa_core\Models\Setting::getValue('pixabay_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('pixabay', '{{ $pixabayKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Pixabay</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Sign up at <a href="https://pixabay.com/api/docs/" target="_blank" class="text-blue-600 underline">pixabay.com/api/docs &#8599;</a> (free)</li>
            <li>Your API key is shown on the docs page after login</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Free tier: 100 req/min. Must cache results 24hrs. No attribution required.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'pixabay_api_key', 'name' => 'Pixabay'])</div>
    </template>
</div>

{{-- ═══ Shared Image Copyright Blacklist ═══ --}}
@php
    $imageCopyrightBlacklist = app(\hexa_core\Services\ImageCopyrightBlacklistService::class);
    $imageCopyrightBlacklistRaw = $imageCopyrightBlacklist->raw();
    $imageCopyrightKeywords = $imageCopyrightBlacklist->keywords();
@endphp
<div id="image-copyright-blacklist"
     data-integration-section="image-copyright-blacklist"
     class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6"
     x-data="{
         keywords: @js($imageCopyrightBlacklistRaw),
         saving: false,
         resultMessage: '',
         resultSuccess: false,
         count: {{ count($imageCopyrightKeywords) }},
         async saveBlacklist() {
             this.saving = true;
             this.resultMessage = '';
             try {
                 const res = await fetch('{{ route('settings.image-copyright-blacklist.save') }}', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content,
                         'Accept': 'application/json'
                     },
                     body: JSON.stringify({ keywords: this.keywords })
                 });
                 const data = await res.json();
                 this.resultSuccess = data.success !== false;
                 this.resultMessage = data.message || 'Saved.';
                 this.count = Number(data.count || 0);
                 if (typeof data.raw === 'string') {
                     this.keywords = data.raw;
                 }
             } catch (e) {
                 this.resultSuccess = false;
                 this.resultMessage = 'Error: ' + e.message;
             }
             this.saving = false;
         }
     }">
    <div class="flex items-start justify-between gap-4 mb-3">
        <div>
            <h2 class="font-semibold text-gray-800 mb-1">Image Copyright Blacklist</h2>
            <p class="text-xs text-gray-500">Shared red-flag keyword list used by image-search packages to warn on likely protected sources.</p>
        </div>
        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-[11px] font-medium text-amber-800" x-text="count + ' keyword' + (count === 1 ? '' : 's')"></span>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3 text-xs text-amber-900 space-y-1.5">
        <p class="font-semibold text-amber-800">Used by:</p>
        <p>Google CSE and SerpAPI image search results read this exact list and mark matching domains in red. Edit it here once and every package uses the same setting.</p>
    </div>

    <textarea x-model="keywords"
              rows="5"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
              placeholder="reuters&#10;ap.com&#10;gettyimages&#10;shutterstock&#10;alamy"></textarea>

    <div class="flex flex-wrap items-center gap-3 mt-3">
        <button @click="saveBlacklist()"
                :disabled="saving"
                type="button"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
            <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="saving ? 'Saving...' : 'Save Blacklist'"></span>
        </button>
        <span x-show="resultMessage" x-cloak class="text-xs" :class="resultSuccess ? 'text-green-700' : 'text-red-700'" x-text="resultMessage"></span>
    </div>
</div>

{{-- ═══ GNews ═══ --}}
@php $gnewsKey = \hexa_core\Models\Setting::getValue('gnews_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('gnews', '{{ $gnewsKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">GNews</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Sign up at <a href="https://gnews.io/" target="_blank" class="text-blue-600 underline">gnews.io &#8599;</a></li>
            <li>Copy your API token from the dashboard</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Free tier: 100 req/day, 10 articles/request. Production-friendly.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'gnews_api_key', 'name' => 'GNews'])</div>
    </template>
</div>

{{-- ═══ NewsData ═══ --}}
@php $newsdataKey = \hexa_core\Models\Setting::getValue('newsdata_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('newsdata', '{{ $newsdataKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">NewsData.io</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Sign up at <a href="https://newsdata.io/" target="_blank" class="text-blue-600 underline">newsdata.io &#8599;</a></li>
            <li>Copy your API key from the dashboard</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Free tier: 200 credits/day (~2,000 articles). Snippets only on free.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'newsdata_api_key', 'name' => 'NewsData'])</div>
    </template>
</div>

{{-- ═══ Sapling AI Detection ═══ --}}
@php $saplingKey = \hexa_core\Models\Setting::getValue('sapling_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('sapling', '{{ $saplingKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Sapling AI Detection</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Sign up at <a href="https://sapling.ai/" target="_blank" class="text-blue-600 underline">sapling.ai &#8599;</a></li>
            <li>Go to API Keys in your dashboard</li>
            <li>Create and copy your API key</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Free tier: 50,000 characters/day, 250,000/month. No credit card needed.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'sapling_api_key', 'name' => 'Sapling'])</div>
    </template>
</div>

{{-- ═══ Anthropic ═══ --}}
@php $anthropicKey = \hexa_core\Models\Setting::getValue('anthropic_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('anthropic', '{{ $anthropicKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Anthropic (Claude)</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Go to <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-blue-600 underline">console.anthropic.com/settings/keys &#8599;</a></li>
            <li>Create a new API key</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Used for AI article spinning and content rewriting. Billed per token usage.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'anthropic_api_key', 'name' => 'Anthropic'])</div>
    </template>
</div>

{{-- ═══ ChatGPT / OpenAI ═══ --}}
@php $chatgptKey = \hexa_core\Models\Setting::getValue('chatgpt_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('chatgpt', '{{ $chatgptKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">ChatGPT / OpenAI</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Go to <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600 underline">platform.openai.com/api-keys &#8599;</a></li>
            <li>Create a new secret key</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Used as an alternative AI engine for spinning. Default model: gpt-4o. Billed per token.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'chatgpt_api_key', 'name' => 'ChatGPT'])</div>
    </template>
</div>

{{-- ═══ Grok ═══ --}}
@php $grokKey = app(\hexa_core\Services\CredentialService::class)->getMasked('grok', 'api_key') ?: \hexa_core\Models\Setting::getValue('grok_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('grok', '{{ $grokKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Grok</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Go to <a href="https://console.x.ai/" target="_blank" class="text-blue-600 underline">console.x.ai &#8599;</a></li>
            <li>Create a new API key</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Used for Grok-backed search and generation workflows.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'grok_api_key', 'name' => 'Grok'])</div>
    </template>
</div>

{{-- ═══ Gemini ═══ --}}
@php $geminiKey = app(\hexa_core\Services\CredentialService::class)->getMasked('gemini', 'api_key') ?: \hexa_core\Models\Setting::getValue('gemini_api_key', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('gemini', '{{ $geminiKey }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Gemini</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'API Key Configured' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Go to <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-blue-600 underline">Google AI Studio &#8599;</a></li>
            <li>Create a Gemini API key</li>
            <li>Paste it below and save</li>
        </ol>
        <p class="text-gray-500 mt-1">Used for Gemini grounded search, URL extraction, and article generation workflows.</p>
    </div>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'gemini_api_key', 'name' => 'Gemini'])</div>
    </template>
</div>

{{-- ═══ Telegram ═══ --}}
@php $telegramToken = \hexa_core\Models\Setting::getValue('telegram_bot_token', ''); @endphp
@php $telegramChatId = \hexa_core\Models\Setting::getValue('telegram_chat_id', ''); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="integrationField('telegram', '{{ $telegramToken }}', '{{ route('settings.update') }}', '{{ route('settings.test-integration') }}')">
    <h2 class="font-semibold text-gray-800 mb-2">Telegram</h2>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-2 h-2 rounded-full" :class="storedKey ? 'bg-green-400' : 'bg-yellow-400'"></span>
        <span class="text-sm font-medium" :class="storedKey ? 'text-green-700' : 'text-yellow-700'" x-text="storedKey ? 'Bot Connected' : 'Not Configured'"></span>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 text-xs text-gray-600 space-y-1.5">
        <p class="font-semibold text-gray-700">Setup steps:</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Message <a href="https://t.me/BotFather" target="_blank" class="text-blue-600 underline">@BotFather &#8599;</a> on Telegram</li>
            <li>Send <code class="bg-gray-200 px-1 rounded">/newbot</code> and follow the prompts</li>
            <li>Copy the bot token and paste it below</li>
            <li>To get your Chat ID: message <a href="https://t.me/userinfobot" target="_blank" class="text-blue-600 underline">@userinfobot &#8599;</a> or forward a message to <a href="https://t.me/RawDataBot" target="_blank" class="text-blue-600 underline">@RawDataBot &#8599;</a></li>
            <li>For group chats: add the bot to the group, then get the group chat ID (negative number)</li>
        </ol>
        <p class="text-gray-500 mt-1">Used for publish notifications, article approval requests, and draft commands.</p>
    </div>

    {{-- Bot Token --}}
    <p class="text-xs font-medium text-gray-500 uppercase mb-1 mt-4">Bot Token</p>
    <template x-if="true">
        <div>@include('app-publish::settings.partials.api-key-field', ['settingKey' => 'telegram_bot_token', 'name' => 'Telegram'])</div>
    </template>

    {{-- Chat ID (separate field) --}}
    <div class="mt-4" x-data="{ chatId: '{{ $telegramChatId }}', savingChat: false, chatResult: '', chatSuccess: false }">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">Default Chat ID</p>
        <div class="flex items-center gap-2">
            <input type="text" x-model="chatId" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="e.g. 123456789 or -1001234567890">
            <button @click="saveChatId()" :disabled="savingChat" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="savingChat" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="savingChat ? 'Saving...' : 'Save'"></span>
            </button>
        </div>
        <div x-show="chatResult" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm" :class="chatSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <span x-text="chatResult"></span>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function saveChatId() {
    this.savingChat = true; this.chatResult = '';
    try {
        const res = await fetch('{{ route("settings.update") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ key: 'telegram_chat_id', value: this.chatId })
        });
        const data = await res.json();
        this.chatSuccess = data.success !== false;
        this.chatResult = data.message || 'Saved.';
    } catch (e) { this.chatSuccess = false; this.chatResult = 'Error: ' + e.message; }
    this.savingChat = false;
}
</script>
@endpush
