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
            $table->foreignId('achat_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->string('code_produit')->unique();
            $table->string('categorie')->nullable();
            $table->integer('quantite')->default(0);
            $table->integer('quantite_min')->default(0);
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
