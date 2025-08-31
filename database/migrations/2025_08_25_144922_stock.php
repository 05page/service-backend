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
        Schema::create('stock', function (Blueprint $table) {
            $table->id();
            $table->text('nom_produit');
            $table->string('code_produit')->unique();
            $table->string('categorie')->nullable();
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->integer('quantitié')->default(0);
            $table->integer('quantite_min')->default(0);
            $table->integer('prix_achat');
            $table->integer('prix_vente');
            $table->string('description')->nullable();
            $table->enum('statut', ['disponible', 'alerte', 'rupture']);

            $table->boolean('actif')->default(true);
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
        Schema::dropIfExists('stock');
    }
};
