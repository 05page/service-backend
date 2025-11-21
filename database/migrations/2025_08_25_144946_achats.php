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
            // Numérotation et références
            $table->string('numero_achat')->unique()->nullable();
             $table->enum('statut', [
                'commande',     // Commandé
                'reçu',     // Confirmé par le fournisseur
                'annule'        // Annulé
            ])->default('commande');
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
