<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario administrador
        User::create([
            'name' => 'Administrador Principal',
            'email' => 'admin@tiendavirtual.com',
            'email_verified_at' => now(),
            'password' => Hash::make('admin123'),
            'dni' => '12345678',
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123, Lima',
            'rol' => 'administrador',
            'limite_credito' => 0,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        // Usuario vendedor
        User::create([
            'name' => 'Carlos Vendedor',
            'email' => 'vendedor@tiendavirtual.com',
            'email_verified_at' => now(),
            'password' => Hash::make('vendedor123'),
            'dni' => '87654321',
            'telefono' => '987654322',
            'direccion' => 'Jr. Comercio 456, Lima',
            'rol' => 'vendedor',
            'limite_credito' => 0,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        // Usuario soporte
        User::create([
            'name' => 'Ana Soporte',
            'email' => 'soporte@tiendavirtual.com',
            'email_verified_at' => now(),
            'password' => Hash::make('soporte123'),
            'dni' => '11223344',
            'telefono' => '987654323',
            'direccion' => 'Av. Tecnología 789, Lima',
            'rol' => 'soporte',
            'limite_credito' => 0,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        // Usuario repartidor
        User::create([
            'name' => 'Miguel Repartidor',
            'email' => 'repartidor@tiendavirtual.com',
            'email_verified_at' => now(),
            'password' => Hash::make('repartidor123'),
            'dni' => '44332211',
            'telefono' => '987654324',
            'direccion' => 'Jr. Delivery 321, Lima',
            'rol' => 'repartidor',
            'limite_credito' => 0,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        // Clientes con diferentes límites de crédito
        User::create([
            'name' => 'María González',
            'email' => 'maria.gonzalez@email.com',
            'email_verified_at' => now(),
            'password' => Hash::make('cliente123'),
            'dni' => '55667788',
            'telefono' => '987654325',
            'direccion' => 'Av. Los Olivos 123, San Juan de Lurigancho',
            'rol' => 'cliente',
            'limite_credito' => 5000.00,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        User::create([
            'name' => 'José Pérez',
            'email' => 'jose.perez@email.com',
            'email_verified_at' => now(),
            'password' => Hash::make('cliente123'),
            'dni' => '99887766',
            'telefono' => '987654326',
            'direccion' => 'Jr. Las Flores 456, Villa El Salvador',
            'rol' => 'cliente',
            'limite_credito' => 3000.00,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        User::create([
            'name' => 'Carmen Silva',
            'email' => 'carmen.silva@email.com',
            'email_verified_at' => now(),
            'password' => Hash::make('cliente123'),
            'dni' => '66554433',
            'telefono' => '987654327',
            'direccion' => 'Av. Principal 789, Ate',
            'rol' => 'cliente',
            'limite_credito' => 8000.00,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        // Autores (usuarios básicos)
        User::create([
            'name' => 'Luis Autor',
            'email' => 'luis.autor@email.com',
            'email_verified_at' => now(),
            'password' => Hash::make('autor123'),
            'dni' => '33221100',
            'telefono' => '987654328',
            'direccion' => 'Jr. Contenido 654, Lima',
            'rol' => 'autor',
            'limite_credito' => 0,
            'verificado' => false,
            'ultimo_login' => now(),
        ]);

        // Clientes adicionales
        User::create([
            'name' => 'Patricia Morales',
            'email' => 'patricia.morales@email.com',
            'email_verified_at' => now(),
            'password' => Hash::make('cliente123'),
            'dni' => '77889900',
            'telefono' => '987654329',
            'direccion' => 'Av. Central 987, Comas',
            'rol' => 'cliente',
            'limite_credito' => 2500.00,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);

        User::create([
            'name' => 'Roberto Díaz',
            'email' => 'roberto.diaz@email.com',
            'email_verified_at' => now(),
            'password' => Hash::make('cliente123'),
            'dni' => '00998877',
            'telefono' => '987654330',
            'direccion' => 'Jr. Independencia 147, La Victoria',
            'rol' => 'cliente',
            'limite_credito' => 6000.00,
            'verificado' => true,
            'ultimo_login' => now(),
        ]);
    }
}
