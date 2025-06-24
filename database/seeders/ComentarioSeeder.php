<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comentario;
use App\Models\User;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class ComentarioSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('rol', 'cliente')->get();
        $productos = Producto::all();

        // Comentarios por producto
        foreach ($productos as $producto) {
            // Cada producto tiene entre 3 y 12 comentarios
            $cantidadComentarios = rand(3, 12);
            
            for ($i = 0; $i < $cantidadComentarios; $i++) {
                $cliente = $clientes->random();
                $calificacion = $this->generarCalificacion();
                
                Comentario::create([
                    'user_id' => $cliente->id,
                    'producto_id' => $producto->id,
                    'comentario' => $this->generarComentario($producto->nombre, $calificacion),
                    'calificacion' => $calificacion,
                    'aprobado' => rand(1, 100) <= 85, // 85% aprobados
                    'titulo' => $this->generarTitulo($calificacion),
                    'respuesta_admin' => rand(1, 100) <= 30 ? $this->generarRespuestaAdmin() : null,
                    'created_at' => now()->subDays(rand(1, 180)),
                ]);
            }
        }
    }

    private function generarCalificacion(): int
    {
        // Distribución realista de calificaciones (más 4 y 5 estrellas)
        $distribucion = [
            5 => 40, // 40%
            4 => 30, // 30%
            3 => 15, // 15%
            2 => 10, // 10%
            1 => 5,  // 5%
        ];

        $random = rand(1, 100);
        $acumulado = 0;

        foreach ($distribucion as $calificacion => $porcentaje) {
            $acumulado += $porcentaje;
            if ($random <= $acumulado) {
                return $calificacion;
            }
        }

        return 5;
    }

    private function generarTitulo(int $calificacion): string
    {
        $titulos = [
            5 => [
                'Excelente producto',
                '¡Lo recomiendo 100%!',
                'Superó mis expectativas',
                'Muy buen producto',
                'Calidad excepcional',
            ],
            4 => [
                'Buen producto',
                'Muy recomendable',
                'Buena calidad',
                'Satisfecho con la compra',
                'Vale la pena',
            ],
            3 => [
                'Producto decente',
                'Cumple con lo esperado',
                'Está bien',
                'Regular',
                'Podría mejorar',
            ],
            2 => [
                'No cumplió expectativas',
                'Producto regular',
                'Tiene fallas',
                'Decepcionante',
                'No muy recomendable',
            ],
            1 => [
                'Muy malo',
                'No funciona bien',
                'Pésima calidad',
                'No lo recomiendo',
                'Dinero perdido',
            ],
        ];

        return collect($titulos[$calificacion])->random();
    }

    private function generarComentario(string $nombreProducto, int $calificacion): string
    {
        $comentarios = [
            5 => [
                "Excelente {$nombreProducto}, funciona perfectamente y la calidad es superior a lo esperado. Llegó rápido y en perfecto estado.",
                "Muy contento con la compra del {$nombreProducto}. La calidad es excelente y el precio muy competitivo. Lo recomiendo totalmente.",
                "El {$nombreProducto} superó todas mis expectativas. Funciona de maravilla y la batería dura mucho. Servicio de entrega excelente.",
                "Increíble {$nombreProducto}! La calidad de construcción es premium y todas las funciones trabajan perfectamente. Muy recomendado.",
            ],
            4 => [
                "Buen {$nombreProducto}, cumple con lo prometido. La calidad es buena aunque podría mejorar en algunos detalles menores.",
                "El {$nombreProducto} funciona bien y la relación calidad-precio es aceptable. Llegó en el tiempo prometido.",
                "Satisfecho con el {$nombreProducto}. Funciona como se esperaba, aunque la batería podría durar un poco más.",
                "Recomendable el {$nombreProducto}. Buena calidad en general, solo algunos detalles menores por mejorar.",
            ],
            3 => [
                "El {$nombreProducto} está bien, cumple con lo básico pero nada extraordinario. Por el precio está aceptable.",
                "Producto promedio. El {$nombreProducto} funciona pero hay mejores opciones en el mercado por precio similar.",
                "El {$nombreProducto} es decente, aunque esperaba un poco más por el precio pagado. Funciona correctamente.",
                "Regular el {$nombreProducto}. Cumple su función pero la calidad podría ser mejor.",
            ],
            2 => [
                "El {$nombreProducto} no cumplió mis expectativas. Tiene varios problemas y la calidad no es la esperada.",
                "Decepcionado con el {$nombreProducto}. Por el precio pagado esperaba mucho más. Tiene fallas frecuentes.",
                "El {$nombreProducto} funciona pero presenta problemas. No estoy conforme con la calidad recibida.",
                "No muy recomendable el {$nombreProducto}. Tiene fallas y la calidad es inferior a lo esperado.",
            ],
            1 => [
                "Muy malo el {$nombreProducto}. No funciona como debería y la calidad es pésima. No lo recomiendo para nada.",
                "El {$nombreProducto} llegó con fallas y no funciona correctamente. Muy decepcionado con la compra.",
                "Pésima calidad del {$nombreProducto}. Dejó de funcionar a los pocos días. Dinero mal invertido.",
                "No compren el {$nombreProducto}. Muy mala calidad y lleno de problemas desde el primer día.",
            ],
        ];

        return collect($comentarios[$calificacion])->random();
    }

    private function generarRespuestaAdmin(): string
    {
        $respuestas = [
            'Gracias por tu comentario. Nos alegra saber que estás satisfecho con tu compra.',
            'Agradecemos tu feedback. Tomamos nota de tus observaciones para mejorar.',
            'Muchas gracias por elegirnos y por tomarte el tiempo de comentar.',
            'Lamentamos cualquier inconveniente. Por favor contáctanos para solucionarlo.',
            'Gracias por tu reseña. Seguimos trabajando para mejorar nuestros productos.',
            'Apreciamos tu opinión. Si tienes algún problema, no dudes en contactarnos.',
        ];

        return collect($respuestas)->random();
    }
} 