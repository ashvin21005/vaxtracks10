@props(['title', 'description' => null, 'action' => null, 'icon' => 'inbox'])
<div {{ $attributes->class(['vt-empty-state']) }}>
    <span class="vt-empty-state__icon" aria-hidden="true"><i data-lucide="{{ $icon }}"></i></span>
    <strong>{{ $title }}</strong>
    @if ($description)
        <span>{{ $description }}</span>
    @endif
    @if ($action)
        <a class="vt-button vt-button--primary" href="{{ $action }}">{{ $slot }}</a>
    @endif
</div>
