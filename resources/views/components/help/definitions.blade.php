{{-- A two-column reference list (term → meaning) that stacks on phones; use <x-help.definition> rows inside. --}}
<div {{ $attributes->merge(['class' => 'mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface']) }}>
    {{ $slot }}
</div>
