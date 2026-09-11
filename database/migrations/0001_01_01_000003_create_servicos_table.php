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
        Schema::create('servicos', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('preco_base', 10, 2);
            $table->unsignedInteger('tempo_estimado_minutos')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index('ativo');
            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
