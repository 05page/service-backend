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
        Schema::create('ventes', function(Blueprint $table){
            $table->id();
            $table->foreignId('stock_id')->constrained('stock')->onDelete('cascade');
            $table->string('reference')->unique();
            $table->string('nom_client');
            $table->string('numero');
            $table->integer('quantite')->nullable();
            $table->decimal('prix_total', 10,2);
            $table->enum('statut', ['en_attente', 'payé', 'annulé']);
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
        Schema::dropIfExists('ventes');
    }
};
