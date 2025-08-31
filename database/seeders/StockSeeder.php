<?php

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $stocks = [
            [
                'service_id' => 4, 
                'fournisseur_id' => 1, 
                'quantite' => 10,// relation avec fournisseur
                'nom_produit' => 'Mocassin',
                'actif'=> 1,
                'created_by'=> 1
            ],
            [
                'service_id' => 4,
                'fournisseur_id' => 1,
                'quantite' => 20,
                'nom_produit' => 'Sandale',
                'actif'=> 1,
                'created_by'=> 1
            ],
        ];

         foreach ($stocks as $stock) {
            Stock::create($stock);
        }
    }
}
