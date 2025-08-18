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
        Schema::create('employes_intermediaires', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['employe', 'intermediaire']);
            $table->string('nom_complet');
            $table->string('email')->unique();
            $table->string('telephone');
            $table->string('adresse');
            $table->string('code_activation');
            $table->timestamp('activated_at')->nullable();
            $table->json('permissions')->nullable(); // Stockage des permissions accordées
            $table->decimal('taux_commission', 5, 2)->nullable(); // Pour les intermédiaires seulement
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Admin qui l'a créé
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists("employes_intermediaires");
    }
};
