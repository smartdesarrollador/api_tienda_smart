<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cupon;
use Illuminate\Database\Seeder;

class CuponSeeder extends Seeder
{
    public function run(): void
    {
        Cupon::create([
            'codigo' => 'BIENVENIDO10',
            'descuento' => 10.00,
            'tipo' => 'porcentaje',
            'fecha_inicio' => now()->subDays(7),
            'fecha_fin' => now()->addDays(30),
            'limite_uso' => 100,
            'usos' => 15,
            'activo' => true,
            'descripcion' => 'Descuento de bienvenida del 10% para nuevos clientes',
        ]);

        Cupon::create([
            'codigo' => 'VERANO2024',
            'descuento' => 15.00,
            'tipo' => 'porcentaje',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(45),
            'limite_uso' => 200,
            'usos' => 32,
            'activo' => true,
            'descripcion' => 'Promoción de verano 2024 - 15% de descuento',
        ]);

        Cupon::create([
            'codigo' => 'TECH100',
            'descuento' => 100.00,
            'tipo' => 'monto_fijo',
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addDays(60),
            'limite_uso' => 50,
            'usos' => 8,
            'activo' => true,
            'descripcion' => 'S/ 100 de descuento en productos tecnológicos',
        ]);

        Cupon::create([
            'codigo' => 'PRIMERACOMPRA',
            'descuento' => 50.00,
            'tipo' => 'monto_fijo',
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(90),
            'limite_uso' => null, // Sin límite
            'usos' => 45,
            'activo' => true,
            'descripcion' => 'S/ 50 de descuento en tu primera compra',
        ]);

        Cupon::create([
            'codigo' => 'NAVIDAD2023',
            'descuento' => 25.00,
            'tipo' => 'porcentaje',
            'fecha_inicio' => now()->subDays(60),
            'fecha_fin' => now()->subDays(10),
            'limite_uso' => 300,
            'usos' => 285,
            'activo' => false,
            'descripcion' => 'Promoción navideña 2023 - Ya expirada',
        ]);

        Cupon::create([
            'codigo' => 'BLACKFRIDAY',
            'descuento' => 30.00,
            'tipo' => 'porcentaje',
            'fecha_inicio' => now()->addDays(60),
            'fecha_fin' => now()->addDays(67),
            'limite_uso' => 500,
            'usos' => 0,
            'activo' => true,
            'descripcion' => 'Black Friday - 30% de descuento máximo',
        ]);

        Cupon::create([
            'codigo' => 'ESTUDIANTE20',
            'descuento' => 20.00,
            'tipo' => 'porcentaje',
            'fecha_inicio' => now()->subDays(15),
            'fecha_fin' => now()->addDays(120),
            'limite_uso' => 150,
            'usos' => 22,
            'activo' => true,
            'descripcion' => 'Descuento especial para estudiantes - 20%',
        ]);

        Cupon::create([
            'codigo' => 'CREDITO200',
            'descuento' => 200.00,
            'tipo' => 'monto_fijo',
            'fecha_inicio' => now()->subDays(3),
            'fecha_fin' => now()->addDays(30),
            'limite_uso' => 25,
            'usos' => 5,
            'activo' => true,
            'descripcion' => 'S/ 200 de descuento para compras a crédito',
        ]);
    }
} 