@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'value' => null,
    'rows' => 3,
    'placeholder' => null,
    'required' => false,
    'hint' => null,
])

@php
    $textareaId = $id ?? $name ?? 'textarea-' . uniqid();
    $hasError = $name && $errors->has($name);
@endphp

<div class="space-y-1.5 w-full">
    @if($label)
        <label for="{{ $textareaId }}" class="block text-xs font-medium text-foreground">
            {{ $label }}
            @if($required)
                <span class="text-destructive">*</span>
            @endif
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $textareaId }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg bg-input/40 border ' . 
                ($hasError ? 'border-destructive focus:ring-destructive focus:border-destructive ' : 'border-border focus:border-ring focus:ring-ring/40 ') . 
                'px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 transition-all resize-y'
        ]) }}
    >{{ old($name, $value) }}</textarea>

    @if($hint && !$hasError)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @if($hasError)
        <p class="text-xs text-destructive mt-1">{{ $errors->first($name) }}</p>
    @endif
</div>