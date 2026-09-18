@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

@php
    $baseClass = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-ring/50 disabled:opacity-50 disabled:pointer-events-none cursor-pointer';

    $variants = [
        'primary' => 'bg-primary text-primary-foreground hover:bg-primary/90 font-semibold shadow-xs',
        'secondary' => 'bg-secondary text-foreground hover:bg-secondary/80 border border-border/60',
        'outline' => 'border border-border text-foreground hover:bg-secondary hover:text-foreground',
        'destructive' => 'bg-destructive/15 text-destructive border border-destructive/30 hover:bg-destructive/25',
        'ghost' => 'text-muted-foreground hover:text-foreground hover:bg-secondary',
        'link' => 'text-primary underline-offset-4 hover:underline p-0 h-auto',
    ];

    $sizes = [
        'sm' => 'h-8 px-3 text-xs gap-1.5',
        'md' => 'h-9 px-4 text-sm gap-2',
        'lg' => 'h-10 px-5 text-base gap-2.5',
        'icon' => 'h-9 w-9 p-0 justify-center',
        'icon-sm' => 'h-8 w-8 p-0 justify-center',
    ];

    $variantClass = $variants[$variant] ?? $variants['primary'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $classes = "{$baseClass} {$variantClass} {$sizeClass}";
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <x-icon :name="$icon" class="{{ $size === 'sm' || $size === 'icon-sm' ? 'w-3.5 h-3.5' : 'w-4 h-4' }}" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <x-icon :name="$icon" class="{{ $size === 'sm' || $size === 'icon-sm' ? 'w-3.5 h-3.5' : 'w-4 h-4' }}" />
        @endif
        {{ $slot }}
    </button>
@endif