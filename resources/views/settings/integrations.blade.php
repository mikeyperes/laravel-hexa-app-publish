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
