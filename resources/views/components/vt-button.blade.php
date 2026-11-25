@props(['href' => null, 'variant' => 'primary', 'type' => 'button'])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['vt-button', 'vt-button--'.$variant]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class(['vt-button', 'vt-button--'.$variant]) }}>{{ $slot }}</button>
@endif
