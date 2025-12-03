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
            $table->string('numero_bon_reception')->nullable()->after('bon_reception');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achat_items', function (Blueprint $table) {
            //
            Schema::dropColumns('numero_bon_reception');
        });
    }
};
