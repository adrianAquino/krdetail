<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servico extends Model
{
    use HasFactory;

    protected $table = "servicos";
    protected $fillable = [
        "nome",
        "descricao",
        "preco_base",
        "tempo_estimado",
        "status" 

    ];
}
