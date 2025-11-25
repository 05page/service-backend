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
        //
        Schema::create('achat_items', function(Blueprint $table){
            $table->id();
            $table->foreignId('achat_id')->constrained('achats')->onDelete('cascade');
            $table->string('nom_service');
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->decimal('prix_reel', 10, 2)->nullable();
            $table->date('date_commande')->nullable(); 
            $table->date('date_livraison')->nullable();
            $table->string('bon_reception')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('achat_items');
    }
};
