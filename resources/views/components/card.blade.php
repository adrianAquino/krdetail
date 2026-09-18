@props(['title' => null, 'description' => null, 'action' => null])

<div {{ $attributes->merge(['class' => 'bg-card border border-border rounded-xl shadow-xs overflow-hidden transition-all']) }}>
    @if($title || $description || $action || isset($header))
        <div class="px-5 py-4 border-b border-border flex items-center justify-between gap-4">
            @if(isset($header))
                {{ $header }}
            @else
                <div>
                    @if($title)
                        <h3 class="text-base font-semibold text-foreground tracking-tight">{{ $title }}</h3>
                    @endif
                    @if($description)
                        <p class="text-xs text-muted-foreground mt-0.5">{{ $description }}</p>
                    @endif
                </div>
                @if($action)
                    <div>{{ $action }}</div>
                @endif
            @endif
        </div>
    @endif

    <div class="p-5">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="px-5 py-3.5 bg-secondary/20 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
            {{ $footer }}
        </div>
    @endif
</div>