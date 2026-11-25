@props(['class' => ''])
<section {{ $attributes->class(['vt-card', $class]) }}>
    {{ $slot }}
</section>
