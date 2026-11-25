@props(['tone' => 'neutral'])
<span {{ $attributes->class(['vt-badge', 'vt-badge--'.$tone]) }}>
    {{ $slot }}
</span>
