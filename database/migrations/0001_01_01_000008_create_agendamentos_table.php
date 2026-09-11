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
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim')->nullable();
            $table->enum('status', ['agendado', 'confirmado', 'em_andamento', 'concluido', 'cancelado'])->default('agendado');
            $table->string('origem', 50)->nullable(); //qualquer informação sobre a origem do agendamento (telefone, whatsapp, site, presencial, etc.)
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['data_inicio', 'status']);
            $table->index('cliente_id');
            $table->index('veiculo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
