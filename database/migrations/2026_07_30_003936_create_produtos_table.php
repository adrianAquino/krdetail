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
        Schema::create('produtos', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->string('nome', 255);
            $table->string('descricao',255);
            $table->string('marca',255);
            $table->string('categoria',255);
            $table->integer('quantidade_atual');
            $table->decimal('custo_unitario', 10, 2);
            $table->decimal('preco_unitario', 10, 2);
            $table->integer('estoque_minimo');
            $table->enum('status', ['ativo', 'inativo']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
