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
            $table->string('nom_fournisseurs');
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();
            $table->json('services')->nullable(); // Services proposés
            
            // Audit trail - Relation polymorphe
            $table->unsignedBigInteger('created_by'); // ID de celui qui a créé
            // Status
            $table->boolean('actif')->default(true);
            $table->timestamps();
            
            // Index pour les recherches
            $table->index(['actif']);
            $table->index(['nom_fournisseurs']);
            $table->index(['created_by']); // Index pour la relation polymorphe
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};
