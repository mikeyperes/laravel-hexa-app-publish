    <div class="pipeline-step-card" :class="currentStep === 1 ? 'ring-2 ring-blue-400' : ''">
        <button @click="toggleStep(1)" class="pipeline-step-toggle">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                      :class="completedSteps.includes(1) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'">
                    <template x-if="completedSteps.includes(1)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                    <template x-if="!completedSteps.includes(1)"><span>1</span></template>
                </span>
                <span class="font-semibold text-gray-800">Select User</span>
                <span x-show="selectedUser" x-cloak class="text-sm text-green-600" x-text="selectedUser ? selectedUser.name : ''"></span>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSteps.includes(1) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="openSteps.includes(1)" x-cloak x-collapse class="pipeline-step-panel">
            <div class="max-w-md"
                 @hexa-search-selected.window="if ($event.detail.component_id === 'pipeline-user' && !_restoring) selectUser($event.detail.item)"
                 @hexa-search-cleared.window="if ($event.detail.component_id === 'pipeline-user') clearUser()">
                @php
                    $pipelineSelectedUser = isset($draftUser) && $draftUser ? ['id' => $draftUser->id, 'name' => $draftUser->name] : null;
                @endphp
                <x-hexa-smart-search
                    url="{{ route('api.search.users') }}"
                    name="user_id"
                    label="Search by name or email"
                    placeholder="Type to search users..."
                    display-field="name"
                    subtitle-field="email"
                    value-field="id"
                    id="pipeline-user"
                    show-id
                    :selected="$pipelineSelectedUser"
                />
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Step 2: Article Configuration
         ══════════════════════════════════════════════════════════════ --}}
