@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div>
        @if($title)
            <h1 class="text-2xl font-bold tracking-tight text-foreground lg:text-3xl">{{ $title }}</h1>
        @endif
        @if($subtitle)
            <p class="mt-1 text-sm text-muted-foreground">{{ $subtitle }}</p>
        @endif
    </div>

    @if(isset($actions))
        <div class="flex flex-wrap items-center gap-2.5">
            {{ $actions }}
        </div>
    @endif
</div>