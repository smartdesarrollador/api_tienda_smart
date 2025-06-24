<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'metodos_pago';

    protected $fillable = [
        'nombre',
        'slug',
        'tipo',
        'descripcion',
        'logo',
        'activo',
        'requiere_verificacion',
        'comision_porcentaje',
        'comision_fija',
        'monto_minimo',
        'monto_maximo',
        'orden',
        'configuracion',
        'paises_disponibles',
        'proveedor',
        'moneda_soportada',
        'permite_cuotas',
        'cuotas_maximas',
        'instrucciones',
        'icono_clase',
        'color_primario',
        'tiempo_procesamiento',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'requiere_verificacion' => 'boolean',
        'comision_porcentaje' => 'decimal:3',
        'comision_fija' => 'decimal:2',
        'monto_minimo' => 'decimal:2',
        'monto_maximo' => 'decimal:2',
        'orden' => 'integer',
        'configuracion' => 'array',
        'paises_disponibles' => 'array',
        'permite_cuotas' => 'boolean',
        'cuotas_maximas' => 'integer',
        'tiempo_procesamiento' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'requiere_verificacion' => false,
        'comision_porcentaje' => 0,
        'comision_fija' => 0,
        'orden' => 0,
        'moneda_soportada' => 'PEN',
        'permite_cuotas' => false,
    ];

    /**
     * Constantes para tipos de métodos de pago
     */
    public const TIPO_TARJETA_CREDITO = 'tarjeta_credito';
    public const TIPO_TARJETA_DEBITO = 'tarjeta_debito';
    public const TIPO_BILLETERA_DIGITAL = 'billetera_digital';
    public const TIPO_TRANSFERENCIA = 'transferencia';
    public const TIPO_EFECTIVO = 'efectivo';
    public const TIPO_CRIPTOMONEDA = 'criptomoneda';

    /**
     * Constantes para proveedores
     */
    public const PROVEEDOR_CULQI = 'culqi';
    public const PROVEEDOR_MERCADOPAGO = 'mercadopago';
    public const PROVEEDOR_PAYPAL = 'paypal';
    public const PROVEEDOR_STRIPE = 'stripe';
    public const PROVEEDOR_PAYU = 'payu';
    public const PROVEEDOR_NIUBIZ = 'niubiz';

    /**
     * Boot del modelo
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MetodoPago $metodoPago) {
            if (empty($metodoPago->slug)) {
                $metodoPago->slug = Str::slug($metodoPago->nombre);
            }
        });

        static::updating(function (MetodoPago $metodoPago) {
            if ($metodoPago->isDirty('nombre')) {
                $metodoPago->slug = Str::slug($metodoPago->nombre);
            }
        });
    }

    /**
     * Relación con pedidos
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'metodo_pago_id');
    }

    /**
     * Relación con pagos
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'metodo_pago_id');
    }

    /**
     * Scope para métodos activos
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para métodos por tipo
     */
    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para métodos ordenados
     */
    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    /**
     * Scope para métodos disponibles en un país
     */
    public function scopeDisponibleEnPais(Builder $query, string $pais): Builder
    {
        return $query->where(function ($q) use ($pais) {
            $q->whereJsonContains('paises_disponibles', $pais)
              ->orWhereNull('paises_disponibles');
        });
    }

    /**
     * Scope para métodos que soportan una moneda
     */
    public function scopeSoportaMoneda(Builder $query, string $moneda): Builder
    {
        return $query->where('moneda_soportada', $moneda);
    }

    /**
     * Scope para métodos disponibles para un monto
     */
    public function scopeDisponibleParaMonto(Builder $query, float $monto): Builder
    {
        return $query->where(function ($q) use ($monto) {
            $q->where(function ($subQ) use ($monto) {
                $subQ->whereNull('monto_minimo')
                     ->orWhere('monto_minimo', '<=', $monto);
            })
            ->where(function ($subQ) use ($monto) {
                $subQ->whereNull('monto_maximo')
                     ->orWhere('monto_maximo', '>=', $monto);
            });
        });
    }

    /**
     * Mutator para el slug
     */
    public function setNombreAttribute(string $value): void
    {
        $this->attributes['nombre'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

    /**
     * Accessor para obtener la URL completa del logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (Str::startsWith($this->logo, ['http://', 'https://'])) {
            return $this->logo;
        }

        return asset($this->logo);
    }





    /**
     * Método para calcular la comisión total para un monto
     */
    public function calcularComision(float $monto): float
    {
        $comisionPorcentaje = ($monto * $this->comision_porcentaje) / 100;
        $comisionTotal = $comisionPorcentaje + $this->comision_fija;

        return round($comisionTotal, 2);
    }

    /**
     * Método para verificar si el método está disponible para un monto
     */
    public function estaDisponibleParaMonto(float $monto): bool
    {
        if ($this->monto_minimo && $monto < $this->monto_minimo) {
            return false;
        }

        if ($this->monto_maximo && $monto > $this->monto_maximo) {
            return false;
        }

        return true;
    }

    /**
     * Método para verificar si el método está disponible en un país
     */
    public function estaDisponibleEnPais(string $pais): bool
    {
        if (!$this->paises_disponibles) {
            return true; // Si no hay restricción de países, está disponible en todos
        }

        return in_array($pais, $this->paises_disponibles);
    }

    /**
     * Método para obtener la configuración de un proveedor específico
     */
    public function getConfiguracion(string $clave, mixed $default = null): mixed
    {
        return data_get($this->configuracion, $clave, $default);
    }

    /**
     * Método para establecer una configuración específica
     */
    public function setConfiguracion(string $clave, mixed $valor): void
    {
        $configuracion = $this->configuracion ?? [];
        data_set($configuracion, $clave, $valor);
        $this->configuracion = $configuracion;
    }

    /**
     * Método para verificar si requiere verificación
     */
    public function requiereVerificacion(): bool
    {
        return $this->requiere_verificacion;
    }

    /**
     * Método para obtener el tiempo estimado de procesamiento en formato legible
     */
    public function getTiempoProcesamiento(): string
    {
        if (!$this->tiempo_procesamiento) {
            return 'Inmediato';
        }

        $horas = intdiv($this->tiempo_procesamiento, 60);
        $minutos = $this->tiempo_procesamiento % 60;

        if ($horas > 0) {
            return $horas . 'h ' . $minutos . 'm';
        }

        return $minutos . ' minutos';
    }

    /**
     * Método para obtener todos los tipos disponibles
     */
    public static function getTiposDisponibles(): array
    {
        return [
            self::TIPO_TARJETA_CREDITO => 'Tarjeta de Crédito',
            self::TIPO_TARJETA_DEBITO => 'Tarjeta de Débito',
            self::TIPO_BILLETERA_DIGITAL => 'Billetera Digital',
            self::TIPO_TRANSFERENCIA => 'Transferencia Bancaria',
            self::TIPO_EFECTIVO => 'Efectivo',
            self::TIPO_CRIPTOMONEDA => 'Criptomoneda',
        ];
    }

    /**
     * Método para obtener todos los proveedores disponibles
     */
    public static function getProveedoresDisponibles(): array
    {
        return [
            self::PROVEEDOR_CULQI => 'Culqi',
            self::PROVEEDOR_MERCADOPAGO => 'MercadoPago',
            self::PROVEEDOR_PAYPAL => 'PayPal',
            self::PROVEEDOR_STRIPE => 'Stripe',
            self::PROVEEDOR_PAYU => 'PayU',
            self::PROVEEDOR_NIUBIZ => 'Niubiz',
        ];
    }

    /**
     * Método para verificar si es un método de pago con tarjeta
     */
    public function esTarjeta(): bool
    {
        return in_array($this->tipo, [
            self::TIPO_TARJETA_CREDITO,
            self::TIPO_TARJETA_DEBITO
        ]);
    }

    /**
     * Método para verificar si es una billetera digital
     */
    public function esBilleteraDigital(): bool
    {
        return $this->tipo === self::TIPO_BILLETERA_DIGITAL;
    }

    /**
     * Método para verificar si es transferencia bancaria
     */
    public function esTransferencia(): bool
    {
        return $this->tipo === self::TIPO_TRANSFERENCIA;
    }

    /**
     * Método para verificar si es pago en efectivo
     */
    public function esEfectivo(): bool
    {
        return $this->tipo === self::TIPO_EFECTIVO;
    }
} 