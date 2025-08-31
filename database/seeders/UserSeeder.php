<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Création de 2 Admins
        User::create([
            'fullname' => 'Admin Two',
            'email' => 'admin1@example.com',
            'telephone' => '0100000001',
            'adresse' => 'Adresse Admin 1',
            'role' => 'admin',
            'password' => Hash::make('password123'),
            'activation_code' => null,
            'active' => true,
        ]);

        User::create([
            'fullname' => 'Admin three',
            'email' => 'admin2@example.com',
            'telephone' => '0100000002',
            'adresse' => 'Adresse Admin 2',
            'role' => 'admin',
            'password' => Hash::make('password123'),
            'activation_code' => null,
            'active' => true,
        ]);

        // Création de 5 Employés
        for ($i = 1; $i <= 3; $i++) {
            User::create([
                'fullname' => "Employe $i",
                'email' => "employe$i@example.com",
                'telephone' => "020000000$i",
                'adresse' => "Adresse Employe $i",
                'role' => 'employe',
                'password' => Hash::make('password123'),
                'activation_code' => null,
                'active' => true,
            ]);
        }
    }
}
