@props(['class' => ''])
<div {{ $attributes->class(['vt-table-wrap', $class]) }}>
    {{ $slot }}
</div>
