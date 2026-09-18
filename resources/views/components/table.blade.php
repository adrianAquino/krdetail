@props(['emptyText' => 'Nenhum registro encontrado'])

<div class="overflow-x-auto w-full">
    <table {{ $attributes->merge(['class' => 'w-full text-left text-sm border-collapse']) }}>
        @if(isset($header))
            <thead class="border-b border-border bg-secondary/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                {{ $header }}
            </thead>
        @endif
        <tbody class="divide-y divide-border text-foreground">
            {{ $slot }}
        </tbody>
    </table>

    @if(isset($empty) || (!trim($slot) && $emptyText))
        <div class="py-12 px-4 text-center">
            @if(isset($empty))
                {{ $empty }}
            @else
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-secondary text-muted-foreground mb-3">
                    <x-icon name="search" class="w-5 h-5" />
                </div>
                <p class="text-sm font-medium text-foreground">{{ $emptyText }}</p>
                <p class="text-xs text-muted-foreground mt-1">Tente ajustar seus filtros ou cadastrar um novo item.</p>
            @endif
        </div>
    @endif
</div>