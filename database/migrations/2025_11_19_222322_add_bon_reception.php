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
        Schema::table('achat_items', function (Blueprint $table) {
            //
            $table->text('bon_reception')->nullable()->after('date_commande');
            $table->date('date_reception')->nullable()->after('bon_reception');
            $table->enum('statut_item', [
                'en_attente',
                'partiellement_recu',
                'recu',
                'annule'
            ])->default('en_attente')->after('date_reception');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achat_items', function (Blueprint $table) {
            //
            Schema::dropIfExists('bon_reception', 'date_reception', 'statut_item');
        });
    }
};
