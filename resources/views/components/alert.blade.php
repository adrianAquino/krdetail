@props(['type' => 'info', 'title' => null, 'message' => null, 'dismissible' => false])

@php
    $configs = [
        'success' => [
            'class' => 'bg-success/10 border-success/30 text-success',
            'icon' => 'check-circle'
        ],
        'warning' => [
            'class' => 'bg-warning/10 border-warning/30 text-warning',
            'icon' => 'alert-triangle'
        ],
        'error' => [
            'class' => 'bg-destructive/10 border-destructive/30 text-destructive',
            'icon' => 'x-circle'
        ],
        'info' => [
            'class' => 'bg-info/10 border-info/30 text-info',
            'icon' => 'shield'
        ],
    ];

    $cfg = $configs[$type] ?? $configs['info'];
@endphp

<div {{ $attributes->merge(['class' => "flex items-start gap-3 p-4 rounded-xl border {$cfg['class']}"]) }}>
    <x-icon :name="$cfg['icon']" class="w-5 h-5 shrink-0 mt-0.5" />
    <div class="flex-1 text-sm">
        @if($title)
            <h4 class="font-semibold {{ ($message || !$slot->isEmpty()) ? 'mb-1' : '' }} text-foreground">{{ $title }}</h4>
        @endif
        @if($message)
            <div class="text-foreground/90 leading-relaxed">{{ $message }}</div>
        @elseif(!$slot->isEmpty())
            <div class="text-foreground/90 leading-relaxed">{{ $slot }}</div>
        @endif
    </div>
    @if($dismissible)
        <button type="button" onclick="this.parentElement.remove()" class="text-muted-foreground hover:text-foreground transition-colors cursor-pointer">
            <x-icon name="x" class="w-4 h-4" />
        </button>
    @endif
</div>
