@props(['href', 'icon', 'active' => false, 'label'])
<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class(['vt-nav__link', $active ? 'is-active' : '']) }}>
    <i data-lucide="{{ $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</a>
