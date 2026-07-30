<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Produto extends Model
{
    //
    use HasFactory;
    protected $table = 'produtos';
    protected $fillable = [
        'nome',
        'descricao',
        'marca',
        'categoria',
        'quantidade_atual',
        'custo_unitario',
        'preco_unitario',
        'estoque_minimo',
        'status',
    ];
}
