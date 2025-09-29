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
        Schema::create('achats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->string('nom_service');
            $table->integer('quantite');$table->decimal('prix_unitaire', 10, 2)->nullable(); // Prix à l'unité
            $table->decimal('prix_total', 10, 2); // Total de l'achat (votre champ existant)
            // Numérotation et références
            $table->string('numero_achat')->unique()->nullable();
            // Dates importantes
            $table->date('date_commande')->nullable(); 
            $table->date('date_livraison')->nullable();
             $table->enum('statut', [
                'commande',     // Commandé
                'confirme',     // Confirmé par le fournisseur
                'paye',         // Payé
                'annule'        // Annulé
            ])->default('commande');
            $table->enum('mode_paiement', [
                'virement', 'mobile_money', 'especes'
            ])->nullable();
            $table->text('description')->nullable(); // Commentaire sur l'achat
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('achats');
    }
};
