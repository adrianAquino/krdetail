@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => 'Selecione uma opção',
])

@php
    $selectId = $id ?? $name ?? 'select-' . uniqid();
    $hasError = $name && $errors->has($name);
@endphp

<div class="space-y-1.5 w-full">
    @if($label)
        <label for="{{ $selectId }}" class="block text-xs font-medium text-foreground">
            {{ $label }}
            @if($required)
                <span class="text-destructive">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <select
            name="{{ $name }}"
            id="{{ $selectId }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes->merge([
                'class' => 'block w-full rounded-lg bg-input/40 border ' . 
                    ($hasError ? 'border-destructive focus:ring-destructive focus:border-destructive ' : 'border-border focus:border-ring focus:ring-ring/40 ') . 
                    'px-3 py-2 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 transition-all appearance-none cursor-pointer'
            ]) }}
        >
            @if($placeholder)
                <option value="" class="bg-card text-muted-foreground">{{ $placeholder }}</option>
            @endif
            {{ $slot }}
        </select>

        <div class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-muted-foreground">
            <x-icon name="chevron-down" class="w-4 h-4" />
        </div>
    </div>

    @if($hint && !$hasError)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @if($hasError)
        <p class="text-xs text-destructive mt-1">{{ $errors->first($name) }}</p>
    @endif
</div>