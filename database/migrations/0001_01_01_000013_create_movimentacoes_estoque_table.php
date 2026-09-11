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
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo_movimentacao', ['entrada', 'saida', 'ajuste']);
            $table->decimal('quantidade', 12, 3);
            $table->decimal('quantidade_anterior', 12, 3);
            $table->decimal('quantidade_posterior', 12, 3);
            $table->string('motivo');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['produto_id', 'created_at']);
            $table->index('tipo_movimentacao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
