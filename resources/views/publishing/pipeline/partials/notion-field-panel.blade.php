@php
    $heading = $heading ?? 'Notion Fields';
    $rowsExpression = $rowsExpression ?? '[]';
    $rowKey = $rowKey ?? 'rowIdx';
    $labelExpression = $labelExpression ?? "row.field || row.notion_field || row.key || ''";
    $sourceExpression = $sourceExpression ?? "''";
    $valueExpression = $valueExpression ?? "row.value || row.display_value || ''";
    $linkUrls = $linkUrls ?? false;
    $panelClass = $panelClass ?? 'rounded-lg border border-white/70 bg-white/80 p-3';
@endphp
<div class="{{ $panelClass }}">
    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $heading }}</p>
    <div class="mt-3 space-y-3">
        <template x-for="(row, rowIdx) in {{ $rowsExpression }}" :key="{{ $rowKey }}">
            <div class="grid grid-cols-1 gap-1 md:grid-cols-[180px_minmax(0,1fr)]">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500" x-text="{{ $labelExpression }} || 'Field'"></p>
                <div>
                    <p x-show="{{ $sourceExpression }}" class="text-xs text-gray-400" x-text="{{ $sourceExpression }}"></p>
                    @if ($linkUrls)
                        <template x-if="String({{ $valueExpression }} || '').startsWith('http')">
                            <a :href="{{ $valueExpression }}" target="_blank" rel="noopener noreferrer" class="mt-1 block text-blue-600 hover:text-blue-800 break-all" x-text="{{ $valueExpression }}"></a>
                        </template>
                        <template x-if="!String({{ $valueExpression }} || '').startsWith('http')">
                            <p class="mt-1 text-sm text-gray-800 whitespace-pre-wrap break-words" x-text="{{ $valueExpression }}"></p>
                        </template>
                    @else
                        <p class="mt-1 text-sm text-gray-800 whitespace-pre-wrap break-words" x-text="{{ $valueExpression }}"></p>
                    @endif
                </div>
            </div>
        </template>
    </div>
</div>
