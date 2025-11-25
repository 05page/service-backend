<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achat_photos', function (Blueprint $table) {
            // Ajouter la colonne achat_item_id
            $table->foreignId('achat_item_id')
                  ->nullable()
                  ->after('achat_id')
                  ->constrained('achat_items')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('achat_photos', function (Blueprint $table) {
            $table->dropForeign(['achat_item_id']);
            $table->dropColumn('achat_item_id');
        });
    }
};