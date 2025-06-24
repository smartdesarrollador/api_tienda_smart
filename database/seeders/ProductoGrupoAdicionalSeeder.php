<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\GrupoAdicional;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoGrupoAdicionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener productos y grupos
        $productos = Producto::all();
        $grupos = GrupoAdicional::where('activo', true)->get();

        if ($productos->isEmpty() || $grupos->isEmpty()) {
            return; // No crear relaciones si no hay productos o grupos
        }

        // Limpiar tabla pivot si existe data previa
        DB::table('producto_grupo_adicional')->truncate();

        foreach ($productos as $producto) {
            $this->asignarGruposSegunProducto($producto, $grupos);
        }
    }

    private function asignarGruposSegunProducto(Producto $producto, $grupos): void
    {
        $categoriaProducto = strtolower($producto->categoria->nombre ?? '');
        $nombreProducto = strtolower($producto->nombre);

        // Determinar qué grupos son relevantes para este producto
        $gruposRelevantes = $this->determinarGruposRelevantes($categoriaProducto, $nombreProducto, $grupos);

        foreach ($gruposRelevantes as $grupoData) {
            DB::table('producto_grupo_adicional')->insert([
                'producto_id' => $producto->id,
                'grupo_adicional_id' => $grupoData['grupo']->id,
                'obligatorio' => $grupoData['obligatorio'],
                'minimo_selecciones' => $grupoData['minimo_selecciones'] ?? null,
                'maximo_selecciones' => $grupoData['maximo_selecciones'] ?? null,
                'orden' => $grupoData['orden'],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function determinarGruposRelevantes(string $categoria, string $nombre, $grupos): array
    {
        $relevantes = [];
        
        // Para hamburguesas y comidas principales
        if (str_contains($categoria, 'hamburguesa') || str_contains($nombre, 'burger') || 
            str_contains($categoria, 'comida') || str_contains($categoria, 'plato')) {
            
            // Grupo de salsas (siempre relevante)
            $grupoSalsas = $grupos->where('slug', 'salsas')->first();
            if ($grupoSalsas) {
                $relevantes[] = [
                    'grupo' => $grupoSalsas,
                    'obligatorio' => false,
                    'minimo_selecciones' => 0,
                    'maximo_selecciones' => 3,
                    'orden' => 1,
                ];
            }

            // Grupo de quesos
            $grupoQuesos = $grupos->where('slug', 'quesos')->first();
            if ($grupoQuesos) {
                $relevantes[] = [
                    'grupo' => $grupoQuesos,
                    'obligatorio' => false,
                    'minimo_selecciones' => 0,
                    'maximo_selecciones' => 2,
                    'orden' => 2,
                ];
            }

            // Grupo de proteínas extra
            $grupoProteinas = $grupos->where('slug', 'proteinas-extra')->first();
            if ($grupoProteinas) {
                $relevantes[] = [
                    'grupo' => $grupoProteinas,
                    'obligatorio' => false,
                    'minimo_selecciones' => 0,
                    'maximo_selecciones' => 1,
                    'orden' => 3,
                ];
            }

            // Grupo de vegetales premium
            $grupoVegetales = $grupos->where('slug', 'vegetales-premium')->first();
            if ($grupoVegetales) {
                $relevantes[] = [
                    'grupo' => $grupoVegetales,
                    'obligatorio' => false,
                    'minimo_selecciones' => 0,
                    'maximo_selecciones' => null,
                    'orden' => 4,
                ];
            }

            // Para combos familiares, agregar combo salsas obligatorio
            if (str_contains($nombre, 'combo') || str_contains($nombre, 'familiar')) {
                $grupoComboSalsas = $grupos->where('slug', 'combos-salsas')->first();
                if ($grupoComboSalsas) {
                    $relevantes[] = [
                        'grupo' => $grupoComboSalsas,
                        'obligatorio' => true,
                        'minimo_selecciones' => 1,
                        'maximo_selecciones' => 1,
                        'orden' => 0,
                    ];
                }
            }

        } else if (str_contains($categoria, 'bebida') || str_contains($nombre, 'bebida')) {
            // Para bebidas, menos opciones de adicionales
            $grupoSalsas = $grupos->where('slug', 'salsas')->first();
            if ($grupoSalsas) {
                $relevantes[] = [
                    'grupo' => $grupoSalsas,
                    'obligatorio' => false,
                    'minimo_selecciones' => 0,
                    'maximo_selecciones' => 1,
                    'orden' => 1,
                ];
            }

        } else if (str_contains($categoria, 'postre') || str_contains($nombre, 'postre')) {
            // Para postres, crear grupos especiales si no existen
            $this->crearGruposEspecialesPostres($grupos);
            
        } else {
            // Para otros productos, asignar grupos básicos
            $grupoSalsas = $grupos->where('slug', 'salsas')->first();
            if ($grupoSalsas) {
                $relevantes[] = [
                    'grupo' => $grupoSalsas,
                    'obligatorio' => false,
                    'minimo_selecciones' => 0,
                    'maximo_selecciones' => 2,
                    'orden' => 1,
                ];
            }
        }

        return $relevantes;
    }

    private function crearGruposEspecialesPostres($grupos): void
    {
        // Verificar si ya existe grupo para postres
        $grupoPostres = $grupos->where('slug', 'toppings-postres')->first();
        
        if (!$grupoPostres) {
            // Crear grupo especial para toppings de postres
            $grupoPostres = GrupoAdicional::create([
                'nombre' => 'Toppings para Postres',
                'slug' => 'toppings-postres',
                'descripcion' => 'Endulza aún más tu postre con estos toppings',
                'obligatorio' => false,
                'multiple_seleccion' => true,
                'minimo_selecciones' => 0,
                'maximo_selecciones' => 3,
                'orden' => 5,
                'activo' => true,
            ]);

            // Crear algunos toppings básicos para postres
            $toppings = [
                ['nombre' => 'Chocolate Extra', 'precio' => 2.00],
                ['nombre' => 'Crema Chantilly', 'precio' => 1.50],
                ['nombre' => 'Fresas Frescas', 'precio' => 3.00],
                ['nombre' => 'Nueces Picadas', 'precio' => 2.50],
            ];

            foreach ($toppings as $index => $toppingData) {
                $topping = \App\Models\Adicional::create([
                    'nombre' => $toppingData['nombre'],
                    'slug' => \Illuminate\Support\Str::slug($toppingData['nombre']),
                    'descripcion' => 'Delicioso topping para postres',
                    'precio' => $toppingData['precio'],
                    'tipo' => 'otro',
                    'disponible' => true,
                    'activo' => true,
                    'vegetariano' => true,
                    'vegano' => false,
                    'orden' => $index + 1,
                ]);

                // Relacionar con el grupo
                DB::table('adicional_grupo')->insert([
                    'adicional_id' => $topping->id,
                    'grupo_adicional_id' => $grupoPostres->id,
                    'orden' => $index + 1,
                    'precio_especial' => null,
                    'activo' => true,
                    'obligatorio_en_grupo' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
