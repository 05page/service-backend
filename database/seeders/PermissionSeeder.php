<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Permissions;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer un admin qui va créer les permissions
        $admin = User::where('role', 'admin')->first();

        // Récupérer tous les employés
        $employes = User::where('role', 'employe')->get();

        foreach ($employes as $employe) {
            // Exemple : on donne à chaque employé toutes les permissions de base
            $modules = ['fournisseurs', 'services', 'stock', 'ventes', 'achats', 'factures'];

            foreach ($modules as $module) {
                Permissions::create([
                    'user_id'    => $employe->id,
                    'created_by' => $admin->id,
                    'description'=> "Accès au module $module",
                    'module'     => $module,
                    'active'     => true,
                ]);
            }
        }
    }
}
