@props([
    'action',
    'empresas' => collect(),
    'empresaSelecionadaId' => null,
    'preserve' => [],
    'permitirTodas' => true,
    'label' => 'Empresa (Visão do Administrador)',
    'paramName' => 'empresa_id',
])

@php
    $usuario = auth()->user();
    $exibir = $usuario && $usuario->isAdministrador();
    $empresasLista = collect($empresas);
    $empresaAtualFiltro = $empresaSelecionadaId
        ? $empresasLista->firstWhere('id', (int) $empresaSelecionadaId)
        : null;
@endphp

@if($exibir)
    <div class="mb-5 rounded-xl border border-primary/25 bg-primary/5 px-4 py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary border border-primary/25">
                <x-icon name="briefcase" class="w-4 h-4" />
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">{{ $label }}</p>
                <p class="text-xs text-muted-foreground truncate">
                    @if($empresaAtualFiltro)
                        Exibindo somente dados de <strong class="text-foreground">{{ $empresaAtualFiltro->nome_fantasia }}</strong>
                    @else
                        Exibindo <strong class="text-foreground">Visão Geral</strong> (todas as {{ $empresasLista->count() }} empresas)
                    @endif
                </p>
            </div>
        </div>

        <form method="GET" action="{{ $action }}" class="flex flex-wrap items-center gap-2 shrink-0">
            @foreach($preserve as $chave => $valor)
                @if($valor !== null && $valor !== '' && $chave !== $paramName && $chave !== 'page')
                    <input type="hidden" name="{{ $chave }}" value="{{ $valor }}" />
                @endif
            @endforeach

            <select
                name="{{ $paramName }}"
                aria-label="{{ $label }}"
                onchange="this.form.submit()"
                class="rounded-lg border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30 cursor-pointer min-w-[230px]"
            >
                @if($permitirTodas)
                    <option value="">Todas as empresas (Visão Geral)</option>
                @endif
                @foreach($empresasLista as $empOpcao)
                    <option value="{{ $empOpcao->id }}" {{ (int) $empresaSelecionadaId === (int) $empOpcao->id ? 'selected' : '' }}>
                        {{ $empOpcao->nome_fantasia }}
                    </option>
                @endforeach
            </select>

            @if($permitirTodas && $empresaSelecionadaId)
                @php
                    $resetParams = collect($preserve)
                        ->reject(fn ($v, $k) => $v === null || $v === '' || $k === $paramName || $k === 'page')
                        ->all();
                @endphp
                <a
                    href="{{ $action . (! empty($resetParams) ? '?' . http_build_query($resetParams) : '') }}"
                    class="px-2.5 py-2 rounded-lg border border-border bg-secondary/60 hover:bg-secondary text-xs font-medium text-foreground transition-colors"
                >
                    Ver Geral
                </a>
            @endif
        </form>
    </div>
@endif
