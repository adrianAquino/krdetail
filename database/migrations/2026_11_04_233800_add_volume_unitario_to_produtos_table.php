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
        if (!Schema::hasColumn('produtos', 'volume_unitario')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->decimal('volume_unitario', 12, 3)->default(1)->after('unidade_medida');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('produtos', 'volume_unitario')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropColumn('volume_unitario');
            });
        }
    }
};

