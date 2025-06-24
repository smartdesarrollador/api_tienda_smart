<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FiltroAvanzado;
use App\Models\FiltroValor;
use Illuminate\Database\Seeder;

class FiltroAvanzadoSeeder extends Seeder
{
    public function run(): void
    {
        // Filtro de Rango de Precio
        $rangoPrecio = FiltroAvanzado::create([
            'nombre' => 'Rango de Precio',
            'slug' => 'rango-precio',
            'tipo' => 'rango',
            'activo' => true,
        ]);

        FiltroValor::create([
            'filtro_id' => $rangoPrecio->id,
            'valor' => 'Menos de S/ 500',
            'codigo' => '0-500',
        ]);

        FiltroValor::create([
            'filtro_id' => $rangoPrecio->id,
            'valor' => 'S/ 500 - S/ 1000',
            'codigo' => '500-1000',
        ]);

        FiltroValor::create([
            'filtro_id' => $rangoPrecio->id,
            'valor' => 'S/ 1000 - S/ 2000',
            'codigo' => '1000-2000',
        ]);

        FiltroValor::create([
            'filtro_id' => $rangoPrecio->id,
            'valor' => 'S/ 2000 - S/ 5000',
            'codigo' => '2000-5000',
        ]);

        FiltroValor::create([
            'filtro_id' => $rangoPrecio->id,
            'valor' => 'Más de S/ 5000',
            'codigo' => '5000+',
        ]);

        // Filtro de Estado del Producto
        $estadoProducto = FiltroAvanzado::create([
            'nombre' => 'Estado del Producto',
            'slug' => 'estado-producto',
            'tipo' => 'select',
            'activo' => true,
        ]);

        FiltroValor::create([
            'filtro_id' => $estadoProducto->id,
            'valor' => 'Nuevo',
            'codigo' => 'nuevo',
        ]);

        FiltroValor::create([
            'filtro_id' => $estadoProducto->id,
            'valor' => 'Reacondicionado',
            'codigo' => 'reacondicionado',
        ]);

        FiltroValor::create([
            'filtro_id' => $estadoProducto->id,
            'valor' => 'Usado',
            'codigo' => 'usado',
        ]);

        // Filtro de Disponibilidad
        $disponibilidad = FiltroAvanzado::create([
            'nombre' => 'Disponibilidad',
            'slug' => 'disponibilidad',
            'tipo' => 'checkbox',
            'activo' => true,
        ]);

        FiltroValor::create([
            'filtro_id' => $disponibilidad->id,
            'valor' => 'En Stock',
            'codigo' => 'en-stock',
        ]);

        FiltroValor::create([
            'filtro_id' => $disponibilidad->id,
            'valor' => 'Agotado',
            'codigo' => 'agotado',
        ]);

        FiltroValor::create([
            'filtro_id' => $disponibilidad->id,
            'valor' => 'Próximamente',
            'codigo' => 'proximamente',
        ]);

        // Filtro de Capacidad de Batería
        $bateria = FiltroAvanzado::create([
            'nombre' => 'Capacidad de Batería',
            'slug' => 'capacidad-bateria',
            'tipo' => 'select',
            'activo' => true,
        ]);

        FiltroValor::create([
            'filtro_id' => $bateria->id,
            'valor' => 'Menos de 3000mAh',
            'codigo' => '0-3000',
        ]);

        FiltroValor::create([
            'filtro_id' => $bateria->id,
            'valor' => '3000 - 4000mAh',
            'codigo' => '3000-4000',
        ]);

        FiltroValor::create([
            'filtro_id' => $bateria->id,
            'valor' => '4000 - 5000mAh',
            'codigo' => '4000-5000',
        ]);

        FiltroValor::create([
            'filtro_id' => $bateria->id,
            'valor' => 'Más de 5000mAh',
            'codigo' => '5000+',
        ]);

        // Filtro de Tipo de Pantalla
        $tipoPantalla = FiltroAvanzado::create([
            'nombre' => 'Tipo de Pantalla',
            'slug' => 'tipo-pantalla',
            'tipo' => 'checkbox',
            'activo' => true,
        ]);

        FiltroValor::create([
            'filtro_id' => $tipoPantalla->id,
            'valor' => 'AMOLED',
            'codigo' => 'amoled',
        ]);

        FiltroValor::create([
            'filtro_id' => $tipoPantalla->id,
            'valor' => 'Super AMOLED',
            'codigo' => 'super-amoled',
        ]);

        FiltroValor::create([
            'filtro_id' => $tipoPantalla->id,
            'valor' => 'OLED',
            'codigo' => 'oled',
        ]);

        FiltroValor::create([
            'filtro_id' => $tipoPantalla->id,
            'valor' => 'LCD',
            'codigo' => 'lcd',
        ]);

        FiltroValor::create([
            'filtro_id' => $tipoPantalla->id,
            'valor' => 'Retina',
            'codigo' => 'retina',
        ]);

        // Filtro de Cámara Principal
        $camaraPrincipal = FiltroAvanzado::create([
            'nombre' => 'Cámara Principal',
            'slug' => 'camara-principal',
            'tipo' => 'select',
            'activo' => true,
        ]);

        FiltroValor::create([
            'filtro_id' => $camaraPrincipal->id,
            'valor' => 'Menos de 12MP',
            'codigo' => '0-12',
        ]);

        FiltroValor::create([
            'filtro_id' => $camaraPrincipal->id,
            'valor' => '12MP - 48MP',
            'codigo' => '12-48',
        ]);

        FiltroValor::create([
            'filtro_id' => $camaraPrincipal->id,
            'valor' => '48MP - 108MP',
            'codigo' => '48-108',
        ]);

        FiltroValor::create([
            'filtro_id' => $camaraPrincipal->id,
            'valor' => 'Más de 108MP',
            'codigo' => '108+',
        ]);

        // Filtro de Certificación
        $certificacion = FiltroAvanzado::create([
            'nombre' => 'Certificación',
            'slug' => 'certificacion',
            'tipo' => 'checkbox',
            'activo' => true,
        ]);

        FiltroValor::create([
            'filtro_id' => $certificacion->id,
            'valor' => 'Resistente al Agua (IP67)',
            'codigo' => 'ip67',
        ]);

        FiltroValor::create([
            'filtro_id' => $certificacion->id,
            'valor' => 'Resistente al Agua (IP68)',
            'codigo' => 'ip68',
        ]);

        FiltroValor::create([
            'filtro_id' => $certificacion->id,
            'valor' => 'MIL-STD-810G',
            'codigo' => 'mil-std-810g',
        ]);

        FiltroValor::create([
            'filtro_id' => $certificacion->id,
            'valor' => 'Energy Star',
            'codigo' => 'energy-star',
        ]);
    }
} 