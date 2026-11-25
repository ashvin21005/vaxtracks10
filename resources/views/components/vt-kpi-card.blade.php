@props(['label', 'value', 'icon' => 'activity', 'trend' => null, 'href' => null, 'tone' => 'teal'])
@php($classes = ['vt-kpi', 'vt-kpi--'.$tone, $href ? 'vt-kpi--clickable' : ''])
@if ($href)
<a href="{{ $href }}" {{ $attributes->class($classes) }}>
    <span class="vt-kpi__icon" aria-hidden="true"><i data-lucide="{{ $icon }}"></i></span>
    <span class="vt-kpi__label">{{ $label }}</span>
    <strong class="vt-kpi__value">{{ $value }}</strong>
    @if ($trend)
        <span class="vt-kpi__trend">{{ $trend }}</span>
    @endif
</a>
@else
<div {{ $attributes->class($classes) }}>
    <span class="vt-kpi__icon" aria-hidden="true"><i data-lucide="{{ $icon }}"></i></span>
    <span class="vt-kpi__label">{{ $label }}</span>
    <strong class="vt-kpi__value">{{ $value }}</strong>
    @if ($trend)
        <span class="vt-kpi__trend">{{ $trend }}</span>
    @endif
</div>
@endif
