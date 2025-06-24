<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\Adicional;
use App\Models\GrupoAdicional;
use Illuminate\Database\Seeder;

class ProductoAdicionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener productos por nombre (ya que aún no tenemos categorías específicas)
        $productos = Producto::where('nombre', 'like', '%burguer%')
            ->orWhere('nombre', 'like', '%hamburguesa%')
            ->orWhere('nombre', 'like', '%pizza%')
            ->orWhere('nombre', 'like', '%pollo%')
            ->get();

        // Si no hay productos específicos, usar los primeros productos disponibles
        if ($productos->isEmpty()) {
            $productos = Producto::take(5)->get();
        }

        // Obtener adicionales por tipo
        $salsas = Adicional::where('tipo', 'salsa')->get();
        $quesos = Adicional::where('tipo', 'queso')->get();
        $carnes = Adicional::where('tipo', 'carne')->get();
        $vegetales = Adicional::where('tipo', 'vegetal')->get();

        // Obtener grupos
        $grupoSalsas = GrupoAdicional::where('slug', 'salsas')->first();
        $grupoQuesos = GrupoAdicional::where('slug', 'quesos')->first();
        $grupoProteinas = GrupoAdicional::where('slug', 'proteinas-extra')->first();
        $grupoVegetales = GrupoAdicional::where('slug', 'vegetales-premium')->first();

        foreach ($productos as $producto) {
            // Relacionar producto con adicionales individuales
            foreach ($salsas as $index => $salsa) {
                $producto->adicionales()->attach($salsa->id, [
                    'precio_personalizado' => null, // Usar precio del adicional
                    'obligatorio' => false,
                    'multiple' => true,
                    'cantidad_minima' => 0,
                    'cantidad_maxima' => 3,
                    'incluido_gratis' => false,
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($quesos as $index => $queso) {
                $producto->adicionales()->attach($queso->id, [
                    'precio_personalizado' => $queso->precio - 0.50, // Descuento pequeño
                    'obligatorio' => false,
                    'multiple' => true,
                    'cantidad_minima' => 0,
                    'cantidad_maxima' => 2,
                    'incluido_gratis' => false,
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($carnes as $index => $carne) {
                $producto->adicionales()->attach($carne->id, [
                    'precio_personalizado' => null,
                    'obligatorio' => false,
                    'multiple' => false, // Solo una porción de carne adicional
                    'cantidad_minima' => 0,
                    'cantidad_maxima' => 1,
                    'incluido_gratis' => false,
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($vegetales as $index => $vegetal) {
                $producto->adicionales()->attach($vegetal->id, [
                    'precio_personalizado' => null,
                    'obligatorio' => false,
                    'multiple' => true,
                    'cantidad_minima' => 0,
                    'cantidad_maxima' => null, // Sin límite en vegetales
                    'incluido_gratis' => $index < 2, // Los primeros 2 vegetales gratis
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Relacionar producto con grupos de adicionales
            if ($grupoSalsas) {
                $producto->gruposAdicionales()->attach($grupoSalsas->id, [
                    'obligatorio' => false,
                    'minimo_selecciones' => null, // Usar configuración del grupo
                    'maximo_selecciones' => 3, // Override: máximo 3 salsas
                    'orden' => 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($grupoQuesos) {
                $producto->gruposAdicionales()->attach($grupoQuesos->id, [
                    'obligatorio' => false,
                    'minimo_selecciones' => null,
                    'maximo_selecciones' => 2, // Override: máximo 2 quesos
                    'orden' => 2,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($grupoProteinas) {
                $producto->gruposAdicionales()->attach($grupoProteinas->id, [
                    'obligatorio' => false,
                    'minimo_selecciones' => null,
                    'maximo_selecciones' => 1, // Solo una proteína adicional
                    'orden' => 3,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($grupoVegetales) {
                $producto->gruposAdicionales()->attach($grupoVegetales->id, [
                    'obligatorio' => false,
                    'minimo_selecciones' => null,
                    'maximo_selecciones' => null, // Sin límite en vegetales
                    'orden' => 4,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
