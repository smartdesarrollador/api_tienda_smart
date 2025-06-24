<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfiguracionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Configuraciones del sitio
        $configuraciones = [
            // Configuraciones de logotipo
            [
                'clave' => 'logo_principal',
                'valor' => 'assets/configuraciones/apariencia/logo.jpg',
                'tipo' => 'imagen',
                'descripcion' => 'Logotipo principal del sitio',
                'grupo' => 'apariencia',
            ],
            [
                'clave' => 'logo_favicon',
                'valor' => 'assets/configuraciones/apariencia/1747008225_favicon.png',
                'tipo' => 'imagen',
                'descripcion' => 'Favicon del sitio',
                'grupo' => 'apariencia',
            ],
            [
                'clave' => 'logo_footer',
                'valor' => 'assets/configuraciones/apariencia/logo.jpg',
                'tipo' => 'imagen',
                'descripcion' => 'Logotipo del pie de página',
                'grupo' => 'apariencia',
            ],

            // Configuraciones de colores
            [
                'clave' => 'color_primario',
                'valor' => '#3B82F6',
                'tipo' => 'color',
                'descripcion' => 'Color primario para botones y elementos destacados',
                'grupo' => 'colores',
            ],
            [
                'clave' => 'color_secundario',
                'valor' => '#10B981',
                'tipo' => 'color',
                'descripcion' => 'Color secundario para acentos y elementos secundarios',
                'grupo' => 'colores',
            ],
            [
                'clave' => 'color_fondo',
                'valor' => '#F3F4F6',
                'tipo' => 'color',
                'descripcion' => 'Color de fondo general',
                'grupo' => 'colores',
            ],
            [
                'clave' => 'color_texto',
                'valor' => '#1F2937',
                'tipo' => 'color',
                'descripcion' => 'Color principal para textos',
                'grupo' => 'colores',
            ],
            [
                'clave' => 'color_enlaces',
                'valor' => '#2563EB',
                'tipo' => 'color',
                'descripcion' => 'Color para enlaces',
                'grupo' => 'colores',
            ],

            // Configuraciones de contacto
            [
                'clave' => 'email_contacto',
                'valor' => 'marketing@maquimotora.com',
                'tipo' => 'texto',
                'descripcion' => 'Email de contacto',
                'grupo' => 'contacto',
            ],
            [
                'clave' => 'telefono_contacto',
                'valor' => '981 593 347',
                'tipo' => 'texto',
                'descripcion' => 'Teléfono de contacto',
                'grupo' => 'contacto',
            ],
            [
                'clave' => 'direccion_principal',
                'valor' => 'Av. La Marina 1110 Pueblo Libre',
                'tipo' => 'texto',
                'descripcion' => 'Dirección principal',
                'grupo' => 'contacto',
            ],
            [
                'clave' => 'direccion_san_miguel_1',
                'valor' => 'Av. La Marina 2593 San Miguel',
                'tipo' => 'texto',
                'descripcion' => 'Dirección sucursal San Miguel 1',
                'grupo' => 'contacto',
            ],
            [
                'clave' => 'direccion_san_miguel_2',
                'valor' => 'Av. La Marina 1021 San Miguel',
                'tipo' => 'texto',
                'descripcion' => 'Dirección sucursal San Miguel 2',
                'grupo' => 'contacto',
            ],

            // Configuraciones de redes sociales
            [
                'clave' => 'redes_sociales',
                'valor' => json_encode([
                    'facebook' => 'https://facebook.com/miempresa',
                    'twitter' => 'https://twitter.com/miempresa',
                    'instagram' => 'https://instagram.com/miempresa'
                ]),
                'tipo' => 'json',
                'descripcion' => 'Enlaces a redes sociales',
                'grupo' => 'social',
            ],

            // Configuraciones generales
            [
                'clave' => 'nombre_sitio',
                'valor' => '',
                'tipo' => 'texto',
                'descripcion' => 'Nombre del sitio web',
                'grupo' => 'general',
            ],
            [
                'clave' => 'descripcion_sitio',
                'valor' => 'Descripción corta del sitio web',
                'tipo' => 'texto',
                'descripcion' => 'Meta descripción del sitio',
                'grupo' => 'general',
            ],
            [
                'clave' => 'modo_mantenimiento',
                'valor' => '0',
                'tipo' => 'booleano',
                'descripcion' => 'Activar modo mantenimiento',
                'grupo' => 'general',
            ],
            [
                'clave' => 'items_por_pagina',
                'valor' => '10',
                'tipo' => 'numero',
                'descripcion' => 'Número de elementos por página',
                'grupo' => 'general',
            ],

            // Configuraciones de fondo
            [
                'clave' => 'imagen_fondo',
                'valor' => 'assets/configuraciones/apariencia/1747008364_fondo.jpg',
                'tipo' => 'imagen',
                'descripcion' => 'Imagen de fondo para el sitio',
                'grupo' => 'apariencia',
            ],
            [
                'clave' => 'usar_gradiente',
                'valor' => '1',
                'tipo' => 'booleano',
                'descripcion' => 'Usar gradiente en lugar de imagen de fondo',
                'grupo' => 'apariencia',
            ],
            [
                'clave' => 'gradiente_colores',
                'valor' => json_encode([
                    'inicio' => '#4F46E5',
                    'fin' => '#10B981'
                ]),
                'tipo' => 'json',
                'descripcion' => 'Colores para el gradiente de fondo',
                'grupo' => 'apariencia',
            ],
        ];

        // Insertar configuraciones
        foreach ($configuraciones as $configuracion) {
            DB::table('configuraciones')->insert(array_merge($configuracion, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
