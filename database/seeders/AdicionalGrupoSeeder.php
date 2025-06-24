<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Adicional;
use App\Models\GrupoAdicional;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdicionalGrupoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener grupos y adicionales
        $grupoSalsas = GrupoAdicional::where('slug', 'salsas')->first();
        $grupoQuesos = GrupoAdicional::where('slug', 'quesos')->first();
        $grupoProteinas = GrupoAdicional::where('slug', 'proteinas-extra')->first();
        $grupoVegetales = GrupoAdicional::where('slug', 'vegetales-premium')->first();
        $grupoComboSalsas = GrupoAdicional::where('slug', 'combos-salsas')->first();

        // Limpiar tabla pivot si existe data previa
        DB::table('adicional_grupo')->truncate();

        // Relacionar salsas con grupos
        $salsas = Adicional::where('tipo', 'salsa')->get();
        foreach ($salsas as $index => $salsa) {
            // Salsas van a grupo "salsas"
            if ($grupoSalsas) {
                DB::table('adicional_grupo')->insert([
                    'adicional_id' => $salsa->id,
                    'grupo_adicional_id' => $grupoSalsas->id,
                    'orden' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // También van a "combo salsas" (para productos que requieren salsa obligatoria)
            if ($grupoComboSalsas) {
                DB::table('adicional_grupo')->insert([
                    'adicional_id' => $salsa->id,
                    'grupo_adicional_id' => $grupoComboSalsas->id,
                    'orden' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Relacionar quesos con grupo quesos
        $quesos = Adicional::where('tipo', 'queso')->get();
        foreach ($quesos as $index => $queso) {
            if ($grupoQuesos) {
                DB::table('adicional_grupo')->insert([
                    'adicional_id' => $queso->id,
                    'grupo_adicional_id' => $grupoQuesos->id,
                    'orden' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Relacionar proteínas con grupo proteínas
        $proteinas = Adicional::where('tipo', 'carne')->get();
        foreach ($proteinas as $index => $proteina) {
            if ($grupoProteinas) {
                DB::table('adicional_grupo')->insert([
                    'adicional_id' => $proteina->id,
                    'grupo_adicional_id' => $grupoProteinas->id,
                    'orden' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Relacionar vegetales con grupo vegetales
        $vegetales = Adicional::where('tipo', 'vegetal')->get();
        foreach ($vegetales as $index => $vegetal) {
            if ($grupoVegetales) {
                DB::table('adicional_grupo')->insert([
                    'adicional_id' => $vegetal->id,
                    'grupo_adicional_id' => $grupoVegetales->id,
                    'orden' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Crear algunas relaciones especiales
        $this->crearRelacionesEspeciales();
    }

    private function crearRelacionesEspeciales(): void
    {
        // Crear grupo especial para combo "Todo Incluido"
        $grupoCompleto = GrupoAdicional::create([
            'nombre' => 'Combo Completo',
            'slug' => 'combo-completo',
            'descripcion' => 'Combo con todo incluido: queso, salsa y vegetal',
            'obligatorio' => false,
            'multiple_seleccion' => false,
            'minimo_selecciones' => 1,
            'maximo_selecciones' => 1,
            'orden' => 10,
            'activo' => true,
        ]);

        // Crear adicional especial para el combo
        $comboCompleto = Adicional::create([
            'nombre' => 'Combo Completo',
            'slug' => 'combo-completo',
            'descripcion' => 'Queso cheddar + salsa BBQ + cebolla caramelizada',
            'precio' => 8.50, // Precio especial del combo
            'tipo' => 'otro', // Usar tipo válido del enum
            'disponible' => true,
            'activo' => true,
            'stock' => null,
            'tiempo_preparacion' => 3,
            'calorias' => 180.0,
            'vegetariano' => true,
            'vegano' => false,
            'orden' => 1,
        ]);

        // Relacionar el combo con su grupo
        DB::table('adicional_grupo')->insert([
            'adicional_id' => $comboCompleto->id,
            'grupo_adicional_id' => $grupoCompleto->id,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
