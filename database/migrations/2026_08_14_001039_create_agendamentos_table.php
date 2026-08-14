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
            $table->id()->autoIncrement();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->date('data_agendamento');
            $table->time('hora_agendamento');
            $table->enum('status', ['pendente', 'confirmado', 'cancelado', 'concluido']);
            $table->enum('origem_agendamento', ['telefone', 'whatsapp', 'site', 'presencial']);
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
