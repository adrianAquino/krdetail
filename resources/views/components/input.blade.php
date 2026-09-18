@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'icon' => null,
    'hint' => null,
])

@php
    $inputId = $id ?? $name ?? 'input-' . uniqid();
    $hasError = $name && $errors->has($name);
@endphp

<div class="space-y-1.5 w-full">
    @if($label)
        <label for="{{ $inputId }}" class="block text-xs font-medium text-foreground">
            {{ $label }}
            @if($required)
                <span class="text-destructive">*</span>
            @endif
        </label>
    @endif

    <div class="relative rounded-lg">
        @if($icon)
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted-foreground">
                <x-icon :name="$icon" class="w-4 h-4" />
            </div>
        @endif

        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $inputId }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes->merge([
                'class' => 'block w-full rounded-lg bg-input/40 border ' . 
                    ($hasError ? 'border-destructive focus:ring-destructive focus:border-destructive ' : 'border-border focus:border-ring focus:ring-ring/40 ') . 
                    ($icon ? 'pl-9 ' : 'px-3 ') . 
                    'py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 transition-all disabled:opacity-50'
            ]) }}
        />
    </div>

    @if($hint && !$hasError)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @if($hasError)
        <p class="text-xs text-destructive mt-1">{{ $errors->first($name) }}</p>
    @endif
</div>