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
        Schema::table('ventes', function (Blueprint $table) {
            //
            $table->decimal('montant_verse', 10,2)->after('prix_total');
            $table->boolean('reglement_statut')->default(0)->after('montant_verse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            //
            $table->dropColumn(['montant_verse', 'reglement_statut']);
        });
    }
};
