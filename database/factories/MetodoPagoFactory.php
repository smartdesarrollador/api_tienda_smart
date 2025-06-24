<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MetodoPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MetodoPago>
 */
class MetodoPagoFactory extends Factory
{
    protected $model = MetodoPago::class;

    public function definition(): array
    {
        $tipos = [
            MetodoPago::TIPO_TARJETA_CREDITO,
            MetodoPago::TIPO_TARJETA_DEBITO,
            MetodoPago::TIPO_BILLETERA_DIGITAL,
            MetodoPago::TIPO_TRANSFERENCIA,
            MetodoPago::TIPO_EFECTIVO,
        ];

        $proveedores = [
            MetodoPago::PROVEEDOR_CULQI,
            MetodoPago::PROVEEDOR_MERCADOPAGO,
            MetodoPago::PROVEEDOR_PAYPAL,
            MetodoPago::PROVEEDOR_STRIPE,
            'manual',
        ];

        $nombre = $this->faker->randomElement([
            'Visa',
            'Mastercard',
            'American Express',
            'Yape',
            'Plin',
            'PayPal',
            'Transferencia BCP',
            'Efectivo'
        ]);

        return [
            'nombre' => $nombre,
            'slug' => \Illuminate\Support\Str::slug($nombre),
            'tipo' => $this->faker->randomElement($tipos),
            'descripcion' => $this->faker->sentence(),
            'logo' => 'assets/metodos-pago/' . \Illuminate\Support\Str::slug($nombre) . '.png',
            'activo' => $this->faker->boolean(85), // 85% probabilidad de estar activo
            'requiere_verificacion' => $this->faker->boolean(60),
            'comision_porcentaje' => $this->faker->randomFloat(3, 0, 10), // Entre 0% y 10%
            'comision_fija' => $this->faker->randomFloat(2, 0, 10), // Entre S/ 0 y S/ 10
            'monto_minimo' => $this->faker->randomFloat(2, 1, 50),
            'monto_maximo' => $this->faker->randomFloat(2, 1000, 10000),
            'orden' => $this->faker->numberBetween(1, 20),
            'configuracion' => [
                'api_key' => $this->faker->uuid(),
                'secret_key' => $this->faker->sha256(),
                'webhook_url' => $this->faker->url(),
            ],
            'paises_disponibles' => $this->faker->randomElements(['PE', 'CO', 'MX', 'CL', 'US'], $this->faker->numberBetween(1, 3)),
            'proveedor' => $this->faker->randomElement($proveedores),
            'moneda_soportada' => $this->faker->randomElement(['PEN', 'USD', 'EUR']),
            'permite_cuotas' => $this->faker->boolean(40),
            'cuotas_maximas' => $this->faker->optional(0.4)->numberBetween(2, 24),
            'instrucciones' => $this->faker->paragraph(),
            'icono_clase' => 'fas fa-credit-card',
            'color_primario' => $this->faker->hexColor(),
            'tiempo_procesamiento' => $this->faker->optional(0.7)->numberBetween(1, 120), // En minutos
        ];
    }

    /**
     * Método de pago de tipo tarjeta de crédito
     */
    public function tarjetaCredito(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => MetodoPago::TIPO_TARJETA_CREDITO,
            'requiere_verificacion' => true,
            'permite_cuotas' => true,
            'cuotas_maximas' => $this->faker->numberBetween(2, 12),
            'comision_porcentaje' => $this->faker->randomFloat(3, 2.5, 5.0),
            'proveedor' => MetodoPago::PROVEEDOR_CULQI,
        ]);
    }

    /**
     * Método de pago de tipo billetera digital
     */
    public function billeteraDigital(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => MetodoPago::TIPO_BILLETERA_DIGITAL,
            'requiere_verificacion' => false,
            'permite_cuotas' => false,
            'comision_porcentaje' => $this->faker->randomFloat(3, 0, 2.0),
            'tiempo_procesamiento' => $this->faker->numberBetween(1, 5),
        ]);
    }

    /**
     * Método de pago en efectivo
     */
    public function efectivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => MetodoPago::TIPO_EFECTIVO,
            'requiere_verificacion' => false,
            'permite_cuotas' => false,
            'comision_porcentaje' => 0,
            'comision_fija' => 0,
            'proveedor' => 'manual',
            'tiempo_procesamiento' => 0,
        ]);
    }

    /**
     * Método de pago activo
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => true,
        ]);
    }

    /**
     * Método de pago inactivo
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * Método de pago para Perú
     */
    public function peru(): static
    {
        return $this->state(fn (array $attributes) => [
            'paises_disponibles' => ['PE'],
            'moneda_soportada' => 'PEN',
        ]);
    }

    /**
     * Método de pago con cuotas
     */
    public function conCuotas(): static
    {
        return $this->state(fn (array $attributes) => [
            'permite_cuotas' => true,
            'cuotas_maximas' => $this->faker->numberBetween(2, 24),
        ]);
    }

    /**
     * Método de pago sin comisiones
     */
    public function sinComisiones(): static
    {
        return $this->state(fn (array $attributes) => [
            'comision_porcentaje' => 0,
            'comision_fija' => 0,
        ]);
    }
} 