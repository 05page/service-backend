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
        Schema::create('stock_historiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stock')->onDelete('cascade');
            $table->foreignId('achat_id')->nullable()->constrained('achats')->onDelete('set null');
            $table->enum('type', ['entree', 'sortie', 'renouvellement', 'ajustement', 'creation']);
            $table->integer('quantite'); // Quantité du mouvement
            $table->integer('quantite_avant'); // Stock avant le mouvement
            $table->integer('quantite_apres'); // Stock après le mouvement
            $table->text('commentaire')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index(['stock_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_historiques');
    }
};