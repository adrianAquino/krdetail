<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lancamentos_financeiros', function (Blueprint $table) {
            $table->id();
            $table->id();
            $table->foreignId('tarefa_id')->nullable()->unique()->constrained('tarefas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo', ['receita', 'despesa']);
            $table->string('categoria');
            $table->string('descricao');
            $table->decimal('valor', 10, 2);
            $table->date('data_lancamento');
            $table->timestamps();
            $table->index(['tipo', 'data_lancamento']);
            $table->index('categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lancamentos_financeiros');
    }
};
