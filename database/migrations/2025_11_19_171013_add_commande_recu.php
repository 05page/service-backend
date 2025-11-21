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
        Schema::table('achat_items', function (Blueprint $table) {
            //
            $table->integer('quantite_recu')->default(0)->after('quantite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achat_items', function (Blueprint $table) {
            //
            Schema::dropIfExists('quantite_recu');
        });
    }
};
