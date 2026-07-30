<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MovimentacaoEstoque extends Model
{
    use HasFactory;

    protected $table = "movimentacoes_estoque";

    protected $fillable = [
    'produto_id',
    'tipo_movimentacao',
    'quantidade',
    'motivo',
    'data_movimentacao',
    ];
}
