<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@krdetail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('12345678'),
                'telefone' => '(11) 99999-9999',
                'tipo_usuario' => 'administrador',
                'ativo' => true,
            ]
        );
    }
}
