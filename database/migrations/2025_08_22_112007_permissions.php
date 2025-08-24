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
        Schema::create('permissions', function(Blueprint $table){
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // ID de celui qui a créé
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Admin qui l'a créé
            // $table->string('name')->unique(); // ex: 'add_suppliers', 'view_sales'
            $table->string('description'); // Description lisible
            $table->enum('module', [
                'fournisseurs', 
                'services', 
                'stock', 
                'ventes', 
                'achats', 
                'factures'
            ]); // Enum pour contraindre les valeurs // Module concerné : 'fournisseurs', 'ventes', etc.
            $table->boolean('active')->default(true); // Permet de désactiver une permission
            $table->timestamps();

            // Index pour optimiser les recherches
            $table->index(['module', 'active']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //

        Schema::dropIfExists('permissions');
    }
};
