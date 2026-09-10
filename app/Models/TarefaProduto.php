<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarefaProduto extends Model
{
    use HasFactory;
    protected $table = 'tarefa_produto';
    protected $fillable = ['tarefa_id', 'produto_id', 'quantidade_utilizada', 'custo_unitario',];
    protected function casts(): array
    {
        return ['quantidade_utilizada' => 'decimal:3', 'custo_unitario' => 'decimal:4',];
    }
    /** * Tarefa na qual o produto foi utilizado. */ 
    public function tarefa()
    {
        return $this->belongsTo(Tarefa::class);
    }
    /** * Produto utilizado. */ 
    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
