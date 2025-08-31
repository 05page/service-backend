<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Fournisseur;
use App\Models\Fournisseurs;

class FournisseurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // On prend un admin comme créateur
        $admin = User::where('role', 'admin')->first();

        // Fournisseurs à insérer
        $fournisseurs = [
            [
                'nom_fournisseurs' => 'Netflix',
                'email' => 'contact@netflix.com',
                'telephone' => '0600000001',
                'adresse' => 'Yopougon',
                'description' => 'Fournit des comptes Netflix premium',
                'created_by' => $admin->id,
                'actif' => true,
            ],
            [
                'nom_fournisseurs' => 'MyCanal',
                'email' => 'mycanal@gmail.com.com',
                'telephone' => '0600000002',
                'adresse' => 'Angré',
                'description' => 'Fournit des comptes MyCanal',
                'created_by' => $admin->id,
                'actif' => true,
            ],
            [
                'nom_fournisseurs' => 'Vergine',
                'email' => 'vergine@gmail.com',
                'telephone' => '0600000003',
                'adresse' => 'Cocody',
                'description' => 'Fournit des mocassins',
                'created_by' => $admin->id,
                'actif' => true,
            ],
        ];

        foreach ($fournisseurs as $data) {
            Fournisseurs::create($data);
        }
    }
}
