<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Supprime l'utilisateur test s'il existe (idempotent)
        User::where('email', 'test@example.com')->delete();

        // Crée l'utilisateur test
        User::create([
            'name' => 'Utilisateur Test',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'), // mot de passe : Password123!
        ]);
    }
}
