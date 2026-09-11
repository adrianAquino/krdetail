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
        Schema::create('tarefas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained('ordem_servicos')->cascadeOnDelete();
            $table->foreignId('agendamento_servico_id')->unique()->constrained('agendamento_servico')->restrictOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->enum('status', ['pendente', 'em_andamento', 'concluida'])->default('pendente');
            $table->dateTime('data_inicio')->nullable();
            $table->dateTime('data_conclusao')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['ordem_servico_id', 'status']);
            $table->index(['funcionario_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarefas');
    }
};
