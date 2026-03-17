{{-- Reusable API key field: masked display, change button, test button --}}
{{-- Requires parent x-data with integrationField() Alpine component --}}

<div x-show="!editing">
    <template x-if="storedKey">
        <input type="password" :value="storedKey" disabled
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500 mb-2">
    </template>
    <template x-if="!storedKey">
        <p class="text-xs text-gray-400 italic mb-2">No API key configured.</p>
    </template>
    <div class="flex items-center gap-2">
        <button type="button" @click="editing = true"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700"
            x-text="storedKey ? 'Change API Key' : 'Set API Key'"></button>
        <button type="button" @click="testKey()" :disabled="testing || !storedKey"
            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-50 inline-flex items-center gap-2">
            <svg x-show="testing" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <span x-text="testing ? 'Testing...' : 'Test'"></span>
        </button>
    </div>
</div>

<div x-show="editing" x-cloak>
    <input type="text" x-model="newKey"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2 font-mono"
        placeholder="Paste API key here...">
    <div class="flex items-center gap-2">
        <button type="button" @click="saveKey()" :disabled="saving || !newKey"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
            <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <span x-text="saving ? 'Saving...' : 'Save'"></span>
        </button>
        <button type="button" @click="editing = false; newKey = ''"
            class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
    </div>
</div>

<div x-show="resultMessage" x-cloak class="mt-2 rounded-lg px-3 py-2 text-sm"
    :class="resultSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
    <span x-text="resultMessage"></span>
</div>
