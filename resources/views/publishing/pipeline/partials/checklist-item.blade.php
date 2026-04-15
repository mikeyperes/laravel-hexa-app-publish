{{-- Standard checklist item row — expects `item` in Alpine scope --}}
<div class="flex items-center gap-2.5 py-1.5 px-2 rounded-lg" :class="item.status === 'done' ? 'bg-green-50' : (item.status === 'failed' ? 'bg-red-50' : '')">
    @include('app-publish::publishing.pipeline.partials.checklist-icon')
    <div class="flex-1 min-w-0">
        <span class="text-sm font-medium" :class="{'text-gray-800': item.status === 'done', 'text-blue-700': item.status === 'running', 'text-red-700': item.status === 'failed', 'text-gray-400': item.status === 'pending', 'text-yellow-700': item.status === 'skipped'}" x-text="item.label"></span>
        <span x-show="item.detail" class="text-xs text-gray-400 ml-2" x-text="item.detail"></span>
    </div>
</div>
