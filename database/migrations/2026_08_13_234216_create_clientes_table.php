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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->string('nome', 255);
            $table->string('cpf', 14)->unique();
            $table->string('email', 255)->unique();
            $table->string('telefone', 20);
            $table->string('logradouro', 255);
            $table->string('numero', 10);
            $table->string('bairro', 255);
            $table->string('cidade', 255);
            $table->string('estado', 255);
            $table->string('cep', 10);
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
        Schema::dropIfExists('clientes');
    }
};
