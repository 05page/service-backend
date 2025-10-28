<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Relation:: morphMap([
        'vente'=> \App\Models\Ventes::class,
        'achat' => \App\Models\Achats::class,
        // 'commission' => \App\Models\Commission::class,
        ]);
    }
}
