<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Favorito;
use App\Models\User;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class FavoritoSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('rol', 'cliente')->get();
        $productos = Producto::all();

        foreach ($clientes as $cliente) {
            // Cada cliente tiene entre 2 y 8 productos favoritos
            $cantidadFavoritos = rand(2, 8);
            $productosSeleccionados = $productos->random($cantidadFavoritos);
            
            foreach ($productosSeleccionados as $producto) {
                Favorito::create([
                    'user_id' => $cliente->id,
                    'producto_id' => $producto->id,
                    'created_at' => now()->subDays(rand(1, 90)),
                ]);
            }
        }
    }
} 