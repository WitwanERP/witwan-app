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
        Schema::table('colaevento', function (Blueprint $table) {
            $table->enum('frecuencia', ['minuto', 'horario', 'nocturno'])->default('minuto')->after('estado');
            $table->index('frecuencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colaevento', function (Blueprint $table) {
            $table->dropIndex(['frecuencia']);
            $table->dropColumn('frecuencia');
        });
    }
};
