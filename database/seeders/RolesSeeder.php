<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear roles básicos
        $roles = [
            [
                'name' => 'admin',
                'description' => 'Administrador del sistema',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'user',
                'description' => 'Usuario normal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insertar roles
        foreach ($roles as $role) {
            // Verificar si ya existe para evitar duplicados
            if (!DB::table('roles')->where('name', $role['name'])->exists()) {
                DB::table('roles')->insert($role);
            }
        }
    }
} 