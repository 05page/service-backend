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
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();

            // Clé polymorphe : peut pointer vers ventes, achats, commissions, etc.
            $table->morphs('payable'); // crée payable_id et payable_type

            $table->decimal('montant_verse', 10, 2);
            $table->timestamp('date_paiement')->useCurrent();
            $table->string('methode')->nullable(); // ex: "cash", "mobile money", "carte"
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
