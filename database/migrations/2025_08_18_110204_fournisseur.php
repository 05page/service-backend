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
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('email')->nullable();
            $table->string('telephone');
            $table->text('adresse');
            $table->text('description');

            // Système d'approbation
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->text('commentaire_validation')->nullable(); // Raison du rejet/validation
            $table->timestamps();

            // Colonnes pour les clés étrangères
            $table->unsignedBigInteger('cree_par')->nullable();
            $table->unsignedBigInteger('valide_par')->nullable();
            // Clés étrangères
            $table->foreign('cree_par')->references('id')->on('users');
            $table->foreign('valide_par')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists(("fournisseurs"));
    }
};
