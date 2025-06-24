<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\ValorAtributo;
use Illuminate\Database\Seeder;

class VariacionProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener productos para crear variaciones
        $iphone15ProMax = Producto::where('slug', 'iphone-15-pro-max')->first();
        $iphone14 = Producto::where('slug', 'iphone-14')->first();
        $galaxyS24Ultra = Producto::where('slug', 'samsung-galaxy-s24-ultra')->first();
        $macbookAir = Producto::where('slug', 'macbook-air-m2')->first();

        // Obtener valores de atributos
        $colores = ValorAtributo::whereHas('atributo', function($query) {
            $query->where('slug', 'color');
        })->get();

        $almacenamientos = ValorAtributo::whereHas('atributo', function($query) {
            $query->where('slug', 'almacenamiento');
        })->get();

        // Variaciones para iPhone 15 Pro Max
        if ($iphone15ProMax && $colores->count() > 0 && $almacenamientos->count() > 0) {
            $coloresIphone = $colores->whereIn('valor', ['Negro', 'Blanco', 'Azul', 'Dorado'])->take(4);
            $almacenamientosIphone = $almacenamientos->whereIn('valor', ['256GB', '512GB', '1TB'])->take(3);

            foreach ($coloresIphone as $color) {
                foreach ($almacenamientosIphone as $almacenamiento) {
                    $precioBase = $iphone15ProMax->precio;
                    $incremento = match ($almacenamiento->valor) {
                        '256GB' => 0,
                        '512GB' => 500,
                        '1TB' => 1000,
                        default => 0,
                    };

                    $variacion = VariacionProducto::create([
                        'producto_id' => $iphone15ProMax->id,
                        'sku' => 'IPH15PM-' . strtoupper(substr($color->valor, 0, 3)) . '-' . $almacenamiento->valor,
                        'precio' => $precioBase + $incremento,
                        'precio_oferta' => $iphone15ProMax->precio_oferta ? $iphone15ProMax->precio_oferta + $incremento : null,
                        'stock' => rand(5, 20),
                        'activo' => true,
                        'atributos' => json_encode([
                            'color' => $color->valor,
                            'almacenamiento' => $almacenamiento->valor,
                        ]),
                    ]);

                    // Asociar valores de atributos a la variación
                    $variacion->valoresAtributos()->attach([$color->id, $almacenamiento->id]);
                }
            }
        }

        // Variaciones para iPhone 14
        if ($iphone14 && $colores->count() > 0 && $almacenamientos->count() > 0) {
            $coloresIphone14 = $colores->whereIn('valor', ['Negro', 'Blanco', 'Rojo', 'Azul'])->take(4);
            $almacenamientosIphone14 = $almacenamientos->whereIn('valor', ['128GB', '256GB', '512GB'])->take(3);

            foreach ($coloresIphone14 as $color) {
                foreach ($almacenamientosIphone14 as $almacenamiento) {
                    $precioBase = $iphone14->precio;
                    $incremento = match ($almacenamiento->valor) {
                        '128GB' => 0,
                        '256GB' => 300,
                        '512GB' => 700,
                        default => 0,
                    };

                    $variacion = VariacionProducto::create([
                        'producto_id' => $iphone14->id,
                        'sku' => 'IPH14-' . strtoupper(substr($color->valor, 0, 3)) . '-' . $almacenamiento->valor,
                        'precio' => $precioBase + $incremento,
                        'precio_oferta' => $iphone14->precio_oferta ? $iphone14->precio_oferta + $incremento : null,
                        'stock' => rand(10, 30),
                        'activo' => true,
                        'atributos' => json_encode([
                            'color' => $color->valor,
                            'almacenamiento' => $almacenamiento->valor,
                        ]),
                    ]);

                    $variacion->valoresAtributos()->attach([$color->id, $almacenamiento->id]);
                }
            }
        }

        // Variaciones para Galaxy S24 Ultra
        if ($galaxyS24Ultra && $colores->count() > 0 && $almacenamientos->count() > 0) {
            $coloresGalaxy = $colores->whereIn('valor', ['Negro', 'Plata', 'Dorado'])->take(3);
            $almacenamientosGalaxy = $almacenamientos->whereIn('valor', ['256GB', '512GB', '1TB'])->take(3);

            foreach ($coloresGalaxy as $color) {
                foreach ($almacenamientosGalaxy as $almacenamiento) {
                    $precioBase = $galaxyS24Ultra->precio;
                    $incremento = match ($almacenamiento->valor) {
                        '256GB' => 0,
                        '512GB' => 400,
                        '1TB' => 800,
                        default => 0,
                    };

                    $variacion = VariacionProducto::create([
                        'producto_id' => $galaxyS24Ultra->id,
                        'sku' => 'SGS24U-' . strtoupper(substr($color->valor, 0, 3)) . '-' . $almacenamiento->valor,
                        'precio' => $precioBase + $incremento,
                        'stock' => rand(8, 25),
                        'activo' => true,
                        'atributos' => json_encode([
                            'color' => $color->valor,
                            'almacenamiento' => $almacenamiento->valor,
                        ]),
                    ]);

                    $variacion->valoresAtributos()->attach([$color->id, $almacenamiento->id]);
                }
            }
        }

        // Variaciones para MacBook Air M2
        if ($macbookAir && $colores->count() > 0 && $almacenamientos->count() > 0) {
            $coloresMac = $colores->whereIn('valor', ['Plata', 'Dorado'])->take(2);
            $almacenamientosMac = $almacenamientos->whereIn('valor', ['256GB', '512GB', '1TB'])->take(3);

            foreach ($coloresMac as $color) {
                foreach ($almacenamientosMac as $almacenamiento) {
                    $precioBase = $macbookAir->precio;
                    $incremento = match ($almacenamiento->valor) {
                        '256GB' => 0,
                        '512GB' => 600,
                        '1TB' => 1200,
                        default => 0,
                    };

                    $variacion = VariacionProducto::create([
                        'producto_id' => $macbookAir->id,
                        'sku' => 'MBAM2-' . strtoupper(substr($color->valor, 0, 3)) . '-' . $almacenamiento->valor,
                        'precio' => $precioBase + $incremento,
                        'precio_oferta' => $macbookAir->precio_oferta ? $macbookAir->precio_oferta + $incremento : null,
                        'stock' => rand(5, 15),
                        'activo' => true,
                        'atributos' => json_encode([
                            'color' => $color->valor,
                            'almacenamiento' => $almacenamiento->valor,
                        ]),
                    ]);

                    $variacion->valoresAtributos()->attach([$color->id, $almacenamiento->id]);
                }
            }
        }
    }
}
