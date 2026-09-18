@props(['id', 'title' => null, 'maxWidth' => 'max-w-lg'])

<div
    id="{{ $id }}"
    class="fixed inset-0 z-50 hidden items-center justify-center p-4 overflow-y-auto bg-background/80 backdrop-blur-xs transition-all"
    data-modal
>
    <div class="relative w-full {{ $maxWidth }} bg-card border border-border rounded-xl shadow-2xl overflow-hidden my-8 transform transition-all animate-in fade-in zoom-in-95">
        @if($title || isset($header))
            <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                @if(isset($header))
                    {{ $header }}
                @else
                    <h3 class="text-base font-semibold text-foreground">{{ $title }}</h3>
                @endif
                <button
                    type="button"
                    onclick="window.closeModal('{{ $id }}')"
                    class="text-muted-foreground hover:text-foreground p-1 rounded-md hover:bg-secondary transition-colors"
                >
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>
        @endif

        <div class="p-5">
            {{ $slot }}
        </div>

        @if(isset($footer))
            <div class="px-5 py-3.5 bg-secondary/20 border-t border-border flex items-center justify-end gap-2">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>