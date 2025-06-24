<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuarios de prueba si no existen
        $usuarios = $this->crearUsuarios();
        
        $clientes = [
            [
                'user_id' => $usuarios[0]->id,
                'dni' => '12345678',
                'telefono' => '987654321',
                'direccion' => 'Av. Javier Prado 123, San Isidro',
                'nombre_completo' => 'Juan Carlos Pérez Mendoza',
                'apellidos' => 'Pérez Mendoza',
                'fecha_nacimiento' => '1985-03-15',
                'genero' => 'M',
                'limite_credito' => 5000.00,
                'verificado' => true,
                'referido_por' => null,
                'profesion' => 'Ingeniero de Sistemas',
                'empresa' => 'TechCorp SAC',
                'ingresos_mensuales' => 4000.00,
                'preferencias' => json_encode([
                    'categorias_favoritas' => ['tecnologia', 'hogar'],
                    'notificaciones_email' => true,
                    'notificaciones_sms' => false,
                    'metodo_pago_preferido' => 'tarjeta'
                ]),
                'metadata' => json_encode([
                    'fuente_registro' => 'web',
                    'ip_registro' => '192.168.1.1',
                    'dispositivo' => 'desktop',
                    'navegador' => 'Chrome',
                    'utm_source' => 'google'
                ]),
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $usuarios[1]->id,
                'dni' => '87654321',
                'telefono' => '912345678',
                'direccion' => 'Calle Los Olivos 456, Miraflores',
                'nombre_completo' => 'María Fernanda García Rodríguez',
                'apellidos' => 'García Rodríguez',
                'fecha_nacimiento' => '1990-07-22',
                'genero' => 'F',
                'limite_credito' => 3000.00,
                'verificado' => true,
                'referido_por' => 'juan.perez@email.com',
                'profesion' => 'Doctora',
                'empresa' => 'Hospital Nacional',
                'ingresos_mensuales' => 6000.00,
                'preferencias' => json_encode([
                    'categorias_favoritas' => ['salud', 'belleza', 'moda'],
                    'notificaciones_email' => true,
                    'notificaciones_sms' => true,
                    'metodo_pago_preferido' => 'yape'
                ]),
                'metadata' => json_encode([
                    'fuente_registro' => 'mobile_app',
                    'ip_registro' => '192.168.1.2',
                    'dispositivo' => 'smartphone',
                    'navegador' => 'Safari',
                    'utm_source' => 'facebook'
                ]),
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $usuarios[2]->id,
                'dni' => '11223344',
                'telefono' => '956789123',
                'direccion' => 'Jr. Amazonas 789, Cercado de Lima',
                'nombre_completo' => 'Carlos Alberto López Vásquez',
                'apellidos' => 'López Vásquez',
                'fecha_nacimiento' => '1978-12-05',
                'genero' => 'M',
                'limite_credito' => 2000.00,
                'verificado' => false,
                'referido_por' => null,
                'profesion' => 'Comerciante',
                'empresa' => 'Negocio Propio',
                'ingresos_mensuales' => 2500.00,
                'preferencias' => json_encode([
                    'categorias_favoritas' => ['electrodomesticos', 'herramientas'],
                    'notificaciones_email' => false,
                    'notificaciones_sms' => true,
                    'metodo_pago_preferido' => 'contado'
                ]),
                'metadata' => json_encode([
                    'fuente_registro' => 'referencia',
                    'ip_registro' => '192.168.1.3',
                    'dispositivo' => 'tablet',
                    'navegador' => 'Chrome',
                    'utm_source' => 'referencia'
                ]),
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $usuarios[3]->id,
                'dni' => '44556677',
                'telefono' => '923456789',
                'direccion' => 'Av. El Sol 321, San Borja',
                'nombre_completo' => 'Ana Lucia Torres Morales',
                'apellidos' => 'Torres Morales',
                'fecha_nacimiento' => '1995-04-18',
                'genero' => 'F',
                'limite_credito' => 1500.00,
                'verificado' => true,
                'referido_por' => 'maria.garcia@email.com',
                'profesion' => 'Diseñadora Gráfica',
                'empresa' => 'Creative Studio',
                'ingresos_mensuales' => 2800.00,
                'preferencias' => json_encode([
                    'categorias_favoritas' => ['arte', 'tecnologia', 'libros'],
                    'notificaciones_email' => true,
                    'notificaciones_sms' => false,
                    'metodo_pago_preferido' => 'transferencia'
                ]),
                'metadata' => json_encode([
                    'fuente_registro' => 'redes_sociales',
                    'ip_registro' => '192.168.1.4',
                    'dispositivo' => 'desktop',
                    'navegador' => 'Firefox',
                    'utm_source' => 'instagram'
                ]),
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $usuarios[4]->id,
                'dni' => '99887766',
                'telefono' => '934567890',
                'direccion' => 'Calle San Martín 654, Pueblo Libre',
                'nombre_completo' => 'Roberto Silva Castillo',
                'apellidos' => 'Silva Castillo',
                'fecha_nacimiento' => '1982-09-30',
                'genero' => 'M',
                'limite_credito' => 4000.00,
                'verificado' => true,
                'referido_por' => null,
                'profesion' => 'Abogado',
                'empresa' => 'Estudio Jurídico Silva',
                'ingresos_mensuales' => 5500.00,
                'preferencias' => json_encode([
                    'categorias_favoritas' => ['libros', 'oficina', 'deportes'],
                    'notificaciones_email' => true,
                    'notificaciones_sms' => true,
                    'metodo_pago_preferido' => 'credito'
                ]),
                'metadata' => json_encode([
                    'fuente_registro' => 'web',
                    'ip_registro' => '192.168.1.5',
                    'dispositivo' => 'desktop',
                    'navegador' => 'Chrome',
                    'utm_source' => 'linkedin'
                ]),
                'estado' => 'inactivo',
                'created_at' => now()->subDays(30),
                'updated_at' => now(),
            ],
            [
                'user_id' => $usuarios[5]->id,
                'dni' => '55667788',
                'telefono' => '945678901',
                'direccion' => 'Av. Larco 987, Miraflores',
                'nombre_completo' => 'Elena Patricia Ruiz Flores',
                'apellidos' => 'Ruiz Flores',
                'fecha_nacimiento' => '1988-11-12',
                'genero' => 'F',
                'limite_credito' => 3500.00,
                'verificado' => true,
                'referido_por' => 'ana.torres@email.com',
                'profesion' => 'Contadora',
                'empresa' => 'Contador Independiente',
                'ingresos_mensuales' => 3200.00,
                'preferencias' => json_encode([
                    'categorias_favoritas' => ['hogar', 'cocina', 'jardin'],
                    'notificaciones_email' => true,
                    'notificaciones_sms' => false,
                    'metodo_pago_preferido' => 'plin'
                ]),
                'metadata' => json_encode([
                    'fuente_registro' => 'mobile_app',
                    'ip_registro' => '192.168.1.6',
                    'dispositivo' => 'smartphone',
                    'navegador' => 'Chrome Mobile',
                    'utm_source' => 'whatsapp'
                ]),
                'estado' => 'activo',
                'created_at' => now()->subDays(15),
                'updated_at' => now(),
            ],
            [
                'user_id' => $usuarios[6]->id,
                'dni' => '22334455',
                'telefono' => '967890123',
                'direccion' => 'Calle Real 321, San Miguel',
                'nombre_completo' => 'Diego Alejandro Vargas Cruz',
                'apellidos' => 'Vargas Cruz',
                'fecha_nacimiento' => '1993-01-25',
                'genero' => 'M',
                'limite_credito' => 0.00,
                'verificado' => false,
                'referido_por' => null,
                'profesion' => 'Estudiante',
                'empresa' => null,
                'ingresos_mensuales' => 800.00,
                'preferencias' => json_encode([
                    'categorias_favoritas' => ['tecnologia', 'gaming', 'ropa'],
                    'notificaciones_email' => true,
                    'notificaciones_sms' => true,
                    'metodo_pago_preferido' => 'yape'
                ]),
                'metadata' => json_encode([
                    'fuente_registro' => 'web',
                    'ip_registro' => '192.168.1.7',
                    'dispositivo' => 'desktop',
                    'navegador' => 'Edge',
                    'utm_source' => 'tiktok'
                ]),
                'estado' => 'bloqueado',
                'created_at' => now()->subDays(5),
                'updated_at' => now(),
            ]
        ];

        DB::table('clientes')->insert($clientes);
        
        $this->command->info('✅ Se crearon ' . count($clientes) . ' clientes de prueba');
    }

    /**
     * Crear usuarios de prueba
     */
    private function crearUsuarios()
    {
        $usuariosData = [
            ['name' => 'Juan Pérez', 'email' => 'juan.perez@email.com'],
            ['name' => 'María García', 'email' => 'maria.garcia@email.com'],
            ['name' => 'Carlos López', 'email' => 'carlos.lopez@email.com'],
            ['name' => 'Ana Torres', 'email' => 'ana.torres@email.com'],
            ['name' => 'Roberto Silva', 'email' => 'roberto.silva@email.com'],
            ['name' => 'Elena Ruiz', 'email' => 'elena.ruiz@email.com'],
            ['name' => 'Diego Vargas', 'email' => 'diego.vargas@email.com'],
        ];

        $usuarios = collect();

        foreach ($usuariosData as $userData) {
            $usuario = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt('password'),
                    'rol' => 'cliente',
                    'email_verified_at' => now(),
                ]
            );
            $usuarios->push($usuario);
        }

        return $usuarios;
    }
}
