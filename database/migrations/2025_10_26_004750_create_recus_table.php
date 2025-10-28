<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recus', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu')->unique();
            $table->foreignId('vente_id')->constrained('ventes')->onDelete('cascade');
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->onDelete('set null');
            $table->decimal('montant_paye', 10, 2); // Montant de CE paiement
            $table->decimal('montant_cumule', 10, 2); // Total payé jusqu'à ce reçu
            $table->decimal('reste_a_payer', 10, 2); // Montant restant
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recus');
    }
};