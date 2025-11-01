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
        // Ajouter nombre_copies à la table factures
        Schema::table('factures', function (Blueprint $table) {
            $table->integer('nombre_copies')->default(0)->after('numero_facture')
                ->comment('0 = ORIGINAL, 1+ = nombre de copies générées');
        });

        // Ajouter nombre_copies à la table recus
        Schema::table('recus', function (Blueprint $table) {
            $table->integer('nombre_copies')->default(0)->after('numero_recu')
                ->comment('0 = ORIGINAL, 1+ = nombre de copies générées');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('nombre_copies');
        });

        Schema::table('recus', function (Blueprint $table) {
            $table->dropColumn('nombre_copies');
        });
    }
};