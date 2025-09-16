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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achat_id')->nullable()->constrained('achats')->onDelete('cascade');
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->onDelete('cascade');
            $table->string('numero_facture')->unique()->nullable();
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
        Schema::dropIfExists('factures');
    }
};
