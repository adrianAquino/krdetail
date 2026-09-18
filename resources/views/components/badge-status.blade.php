@props(['status' => null, 'label' => null, 'variant' => null])

@php
    $statusKey = strtolower((string) ($status ?? $variant ?? ''));

    $configs = [
        // Agendamento / OS / Tarefa
        'agendado' => ['label' => 'Agendado', 'class' => 'bg-info/15 text-info border-info/30'],
        'confirmado' => ['label' => 'Confirmado', 'class' => 'bg-primary/15 text-primary border-primary/30'],
        'aberta' => ['label' => 'Aberta', 'class' => 'bg-info/15 text-info border-info/30'],
        'em_andamento' => ['label' => 'Em Andamento', 'class' => 'bg-warning/15 text-warning border-warning/30'],
        'concluido' => ['label' => 'Concluído', 'class' => 'bg-success/15 text-success border-success/30'],
        'concluida' => ['label' => 'Concluída', 'class' => 'bg-success/15 text-success border-success/30'],
        'cancelado' => ['label' => 'Cancelado', 'class' => 'bg-destructive/15 text-destructive border-destructive/30'],
        'cancelada' => ['label' => 'Cancelada', 'class' => 'bg-destructive/15 text-destructive border-destructive/30'],
        'pendente' => ['label' => 'Pendente', 'class' => 'bg-secondary text-muted-foreground border-border'],

        // Acesso de Cliente (User account)
        'acesso_ativo' => ['label' => 'Acesso Ativo', 'class' => 'bg-success/15 text-success border-success/30'],
        'sem_acesso' => ['label' => 'Sem Acesso', 'class' => 'bg-secondary text-muted-foreground border-border'],

        // Status Ativo / Inativo
        'ativo' => ['label' => 'Ativo', 'class' => 'bg-success/15 text-success border-success/30'],
        'inativo' => ['label' => 'Inativo', 'class' => 'bg-muted/40 text-muted-foreground border-border'],

        // Estoque
        'estoque_normal' => ['label' => 'Estoque Normal', 'class' => 'bg-success/15 text-success border-success/30'],
        'estoque_baixo' => ['label' => 'Estoque Baixo', 'class' => 'bg-warning/15 text-warning border-warning/30'],
        'estoque_critico' => ['label' => 'Estoque Crítico', 'class' => 'bg-destructive/15 text-destructive border-destructive/30'],
        'entrada' => ['label' => 'Entrada', 'class' => 'bg-success/15 text-success border-success/30'],
        'saida' => ['label' => 'Saída', 'class' => 'bg-destructive/15 text-destructive border-destructive/30'],
        'ajuste' => ['label' => 'Ajuste', 'class' => 'bg-info/15 text-info border-info/30'],

        // Notificações
        'enviada' => ['label' => 'Enviada', 'class' => 'bg-info/15 text-info border-info/30'],
        'lida' => ['label' => 'Lida', 'class' => 'bg-success/15 text-success border-success/30'],
        'falha' => ['label' => 'Falha', 'class' => 'bg-destructive/15 text-destructive border-destructive/30'],
    ];

    $cfg = $configs[$statusKey] ?? ['label' => ucfirst(str_replace('_', ' ', $statusKey)), 'class' => 'bg-secondary text-muted-foreground border-border'];
    $displayText = $label ?? $cfg['label'];
    $colorClass = $cfg['class'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border {$colorClass}"]) }}>
    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-75"></span>
    {{ $displayText }}
</span>