@props(['id', 'title' => null, 'maxWidth' => 'max-w-lg'])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        default => $maxWidth,
    };

    $modalTargetOld = (string) old('_modal_target', '');
    $hasModalErrors = $errors->any() || session()->has('error');
    $shouldAutoOpen = $hasModalErrors
        && $modalTargetOld !== ''
        && ($modalTargetOld === $id || 'modal-' . $modalTargetOld === $id);
@endphp

<div
    id="{{ $id }}"
    class="fixed inset-0 z-50 {{ $shouldAutoOpen ? 'flex' : 'hidden' }} items-center justify-center px-4 py-8 sm:py-12 overflow-y-auto bg-background/80 backdrop-blur-xs transition-all"
    style="padding-top: 2.75rem; padding-bottom: 2.75rem;"
    data-modal
    @if($shouldAutoOpen) data-modal-auto-open="true" @endif
>
    <div
        class="relative w-full {{ $maxWidthClass }} max-h-[85vh] flex flex-col bg-card border border-border rounded-2xl shadow-2xl overflow-hidden my-auto transform transition-all animate-in fade-in zoom-in-95"
        style="max-height: 85vh; border-radius: 16px;"
    >
        @if($title || isset($header))
            <div class="px-5 py-4 border-b border-border flex items-center justify-between shrink-0">
                @if(isset($header))
                    {{ $header }}
                @else
                    <h3 class="text-base font-semibold text-foreground">{{ $title }}</h3>
                @endif
                <button
                    type="button"
                    onclick="window.closeModal('{{ $id }}')"
                    class="text-muted-foreground hover:text-foreground p-1 rounded-md hover:bg-secondary transition-colors cursor-pointer"
                >
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>
        @endif

        <div class="p-5 overflow-y-auto" data-modal-body>
            @if($shouldAutoOpen)
                @if($errors->any())
                    <div class="mb-4">
                        <x-alert type="error" title="Existem erros no formulário:">
                            <ul class="list-disc list-inside space-y-1 text-xs mt-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    </div>
                @elseif(session()->has('error'))
                    <div class="mb-4">
                        <x-alert type="error" :title="session('error')" />
                    </div>
                @endif
            @endif

            {{ $slot }}
        </div>

        @if(isset($footer))
            <div class="px-5 py-3.5 bg-secondary/20 border-t border-border flex items-center justify-end gap-2 shrink-0">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>