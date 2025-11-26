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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Informations générales
            $table->string('fullname');
            $table->string('email')->unique();
            $table->string('telephone');
            $table->string('adresse');
            $table->enum('role', ['admin', 'employe', 'intermediaire'])->default('admin');

            // Authentification
            $table->string('password')->nullable(); // optionnel si tu veux gérer un mot de passe aussi
            $table->string('activation_code')->nullable(); // code temporaire envoyé par mail
            $table->timestamp('activated_at')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            // Traçabilité
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
