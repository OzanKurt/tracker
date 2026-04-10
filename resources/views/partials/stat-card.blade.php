@props(['label', 'value', 'sublabel' => null])

<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <dt class="text-sm font-medium text-slate-500">{{ $label }}</dt>
    <dd class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $value }}</dd>
    @if ($sublabel)
        <dd class="mt-1 text-xs text-slate-500">{{ $sublabel }}</dd>
    @endif
</div>
