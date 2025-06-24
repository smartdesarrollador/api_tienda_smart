<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EnvioService
{
    private const ENVIO_GRATIS_MONTO = 150.0;
    private const PESO_MAXIMO_NORMAL = 10.0; // kg

    /**
     * Calcular opciones de envío disponibles
     */
    public function calcularOpciones(
        string $departamento,
        string $provincia,
        string $distrito,
        float $pesoTotal,
        float $valorTotal
    ): array {
        $opciones = [];

        // Determinar si es Lima Metropolitana
        $esLimaMetropolitana = $this->esLimaMetropolitana($departamento, $provincia, $distrito);

        // Envío estándar
        $opciones[] = $this->crearOpcionEstandar($esLimaMetropolitana, $pesoTotal, $valorTotal);

        // Envío express (solo Lima)
        if ($esLimaMetropolitana) {
            $opciones[] = $this->crearOpcionExpress($pesoTotal, $valorTotal);
        }

        // Envío rápido nacional
        if (!$esLimaMetropolitana) {
            $opciones[] = $this->crearOpcionRapidoNacional($pesoTotal, $valorTotal);
        }

        // Envío premium
        $opciones[] = $this->crearOpcionPremium($esLimaMetropolitana, $pesoTotal, $valorTotal);

        // Aplicar descuentos por monto
        return array_map(function ($opcion) use ($valorTotal) {
            return $this->aplicarDescuentosPorMonto($opcion, $valorTotal);
        }, $opciones);
    }

    /**
     * Verificar si la dirección es Lima Metropolitana
     */
    private function esLimaMetropolitana(string $departamento, string $provincia, string $distrito): bool
    {
        $departamento = strtolower(trim($departamento));
        $provincia = strtolower(trim($provincia));

        return $departamento === 'lima' && $provincia === 'lima';
    }

    /**
     * Crear opción de envío estándar
     */
    private function crearOpcionEstandar(bool $esLima, float $peso, float $valor): array
    {
        if ($esLima) {
            $precio = $peso <= 5 ? 10.0 : 15.0;
            $tiempoMin = 24;
            $tiempoMax = 48;
        } else {
            $precio = $peso <= 5 ? 20.0 : 30.0;
            $tiempoMin = 72;
            $tiempoMax = 120;
        }

        return [
            'id' => 'estandar',
            'nombre' => 'Envío Estándar',
            'descripcion' => $esLima 
                ? 'Entrega en Lima Metropolitana en 1-2 días hábiles'
                : 'Entrega a nivel nacional en 3-5 días hábiles',
            'precio' => $precio,
            'tiempo_entrega_min' => $tiempoMin,
            'tiempo_entrega_max' => $tiempoMax,
            'tiempo_unidad' => 'horas',
            'empresa' => 'Courier Express',
            'incluye_seguro' => false,
            'incluye_tracking' => true,
            'logo_empresa' => '/assets/empresas/courier-express.png',
            'gratis_desde' => self::ENVIO_GRATIS_MONTO,
            'disponible' => true,
            'transportista' => 'Courier Express',
            'icono' => '📦'
        ];
    }

    /**
     * Crear opción de envío express (solo Lima)
     */
    private function crearOpcionExpress(float $peso, float $valor): array
    {
        $precio = $peso <= 3 ? 25.0 : 35.0;

        return [
            'id' => 'express',
            'nombre' => 'Envío Express',
            'descripcion' => 'Entrega el mismo día en Lima Metropolitana',
            'precio' => $precio,
            'tiempo_entrega_min' => 4,
            'tiempo_entrega_max' => 8,
            'tiempo_unidad' => 'horas',
            'empresa' => 'Express Lima',
            'incluye_seguro' => true,
            'incluye_tracking' => true,
            'logo_empresa' => '/assets/empresas/express-lima.png',
            'gratis_desde' => 300.0, // Solo gratis desde S/ 300
            'disponible' => $peso <= 8,
            'transportista' => 'Express Lima',
            'icono' => '⚡'
        ];
    }

    /**
     * Crear opción de envío rápido nacional
     */
    private function crearOpcionRapidoNacional(float $peso, float $valor): array
    {
        $precio = $peso <= 5 ? 35.0 : 50.0;

        return [
            'id' => 'rapido_nacional',
            'nombre' => 'Envío Rápido Nacional',
            'descripcion' => 'Entrega rápida a nivel nacional en 2-3 días hábiles',
            'precio' => $precio,
            'tiempo_entrega_min' => 48,
            'tiempo_entrega_max' => 72,
            'tiempo_unidad' => 'horas',
            'empresa' => 'Olva Courier',
            'incluye_seguro' => true,
            'incluye_tracking' => true,
            'logo_empresa' => '/assets/empresas/olva-courier.png',
            'gratis_desde' => 200.0,
            'disponible' => true,
            'transportista' => 'Olva Courier',
            'icono' => '🚀'
        ];
    }

    /**
     * Crear opción de envío premium
     */
    private function crearOpcionPremium(bool $esLima, float $peso, float $valor): array
    {
        if ($esLima) {
            $precio = 50.0;
            $tiempoMin = 2;
            $tiempoMax = 4;
            $descripcion = 'Entrega premium en 2-4 horas con atención personalizada';
        } else {
            $precio = 80.0;
            $tiempoMin = 24;
            $tiempoMax = 48;
            $descripcion = 'Envío premium nacional con seguro completo';
        }

        return [
            'id' => 'premium',
            'nombre' => 'Envío Premium',
            'descripcion' => $descripcion,
            'precio' => $precio,
            'tiempo_entrega_min' => $tiempoMin,
            'tiempo_entrega_max' => $tiempoMax,
            'tiempo_unidad' => 'horas',
            'empresa' => 'Premium Delivery',
            'incluye_seguro' => true,
            'incluye_tracking' => true,
            'logo_empresa' => '/assets/empresas/premium-delivery.png',
            'gratis_desde' => 500.0,
            'disponible' => $peso <= self::PESO_MAXIMO_NORMAL,
            'transportista' => 'Premium Delivery',
            'icono' => '👑'
        ];
    }

    /**
     * Aplicar descuentos por monto de compra
     */
    private function aplicarDescuentosPorMonto(array $opcion, float $valorTotal): array
    {
        // Envío gratis si alcanza el monto mínimo
        if ($valorTotal >= $opcion['gratis_desde']) {
            $opcion['precio'] = 0.0;
            $opcion['es_gratis'] = true;
            $opcion['motivo_gratis'] = "Envío gratis por compras mayores a S/ {$opcion['gratis_desde']}";
        } else {
            $opcion['es_gratis'] = false;
            $faltante = $opcion['gratis_desde'] - $valorTotal;
            $opcion['falta_para_gratis'] = round($faltante, 2);
        }

        return $opcion;
    }

    /**
     * Obtener costo de envío por opción seleccionada
     */
    public function obtenerCostoEnvio(string $opcionId, string $departamento, string $provincia, string $distrito, float $peso, float $valor): float
    {
        $opciones = $this->calcularOpciones($departamento, $provincia, $distrito, $peso, $valor);
        
        $opcionSeleccionada = collect($opciones)->firstWhere('id', $opcionId);
        
        return $opcionSeleccionada ? $opcionSeleccionada['precio'] : 0.0;
    }

    /**
     * Validar disponibilidad de envío para una dirección
     */
    public function validarDisponibilidad(string $departamento, string $provincia, string $distrito): array
    {
        // Lista de departamentos/provincias donde no llegamos
        $zonasNoDisponibles = [
            'loreto' => ['requena', 'ucayali'],
            'madre de dios' => ['tahuamanu'],
            // Agregar más zonas según necesidad
        ];

        $departamentoLower = strtolower(trim($departamento));
        $provinciaLower = strtolower(trim($provincia));

        $disponible = true;
        $mensaje = '';

        if (isset($zonasNoDisponibles[$departamentoLower])) {
            if (in_array($provinciaLower, $zonasNoDisponibles[$departamentoLower])) {
                $disponible = false;
                $mensaje = "Actualmente no realizamos envíos a {$provincia}, {$departamento}. Contáctanos para opciones especiales.";
            }
        }

        return [
            'disponible' => $disponible,
            'mensaje' => $mensaje,
            'requiere_coordenacion' => !$disponible
        ];
    }

    /**
     * Calcular tiempo estimado de entrega
     */
    public function calcularTiempoEntrega(string $opcionId, string $departamento): string
    {
        $esLima = strtolower(trim($departamento)) === 'lima';

        switch ($opcionId) {
            case 'express':
                return $esLima ? 'Hoy mismo (4-8 horas)' : 'No disponible';
            case 'estandar':
                return $esLima ? '1-2 días hábiles' : '3-5 días hábiles';
            case 'rapido_nacional':
                return $esLima ? '1 día hábil' : '2-3 días hábiles';
            case 'premium':
                return $esLima ? '2-4 horas' : '1-2 días hábiles';
            default:
                return '3-5 días hábiles';
        }
    }

    /**
     * Obtener restricciones por peso y dimensiones
     */
    public function obtenerRestricciones(): array
    {
        return [
            'peso_maximo_normal' => self::PESO_MAXIMO_NORMAL,
            'peso_maximo_especial' => 50.0,
            'dimensiones_maximas' => [
                'largo' => 100, // cm
                'ancho' => 80,  // cm
                'alto' => 60    // cm
            ],
            'productos_restringidos' => [
                'liquidos',
                'aerosoles',
                'productos_quimicos',
                'articulos_fragiles_sin_empaque'
            ]
        ];
    }
} 