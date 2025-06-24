<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'user_id',
        'dni',
        'telefono',
        'direccion',
        'nombre_completo',
        'apellidos',
        'fecha_nacimiento',
        'genero',
        'limite_credito',
        'verificado',
        'referido_por',
        'profesion',
        'empresa',
        'ingresos_mensuales',
        'preferencias',
        'metadata',
        'estado',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'limite_credito' => 'decimal:2',
        'ingresos_mensuales' => 'decimal:2',
        'verificado' => 'boolean',
        'preferencias' => 'array',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'limite_credito' => 0,
        'verificado' => false,
        'estado' => 'activo',
    ];

    // Relaciones

    /**
     * Relación con User (1:1)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con DatosFacturacion (1:N)
     */
    public function datosFacturacion(): HasMany
    {
        return $this->hasMany(DatosFacturacion::class);
    }

    /**
     * Datos de facturación predeterminados (1:1)
     */
    public function datoFacturacionPredeterminado(): HasOne
    {
        return $this->hasOne(DatosFacturacion::class)
                    ->where('predeterminado', true)
                    ->where('activo', true);
    }

    /**
     * Datos de facturación activos (1:N)
     */
    public function datosFacturacionActivos(): HasMany
    {
        return $this->hasMany(DatosFacturacion::class)
                    ->where('activo', true);
    }

    // Scopes

    /**
     * Scope para clientes activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para clientes verificados
     */
    public function scopeVerificados($query)
    {
        return $query->where('verificado', true);
    }

    /**
     * Scope para clientes con crédito disponible
     */
    public function scopeConCredito($query)
    {
        return $query->where('limite_credito', '>', 0);
    }

    // Accessors

    /**
     * Obtener el nombre completo formateado
     */
    public function getNombreCompletoFormateadoAttribute(): string
    {
        return $this->nombre_completo ?? ($this->user->name ?? 'Sin nombre');
    }

    /**
     * Obtener edad del cliente
     */
    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }

    /**
     * Verificar si tiene crédito disponible
     */
    public function getTieneCreditoAttribute(): bool
    {
        return $this->limite_credito > 0;
    }

    // Mutators

    /**
     * Formatear DNI antes de guardar
     */
    public function setDniAttribute($value): void
    {
        $this->attributes['dni'] = preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Formatear teléfono antes de guardar
     */
    public function setTelefonoAttribute($value): void
    {
        $this->attributes['telefono'] = preg_replace('/[^0-9+]/', '', $value);
    }

    // Métodos adicionales

    /**
     * Verificar si el cliente está activo
     */
    public function isActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /**
     * Verificar si el cliente está verificado
     */
    public function isVerificado(): bool
    {
        return $this->verificado === true;
    }

    /**
     * Obtener límite de crédito disponible
     */
    public function getCreditoDisponible(): float
    {
        // Aquí podrías calcular el crédito usado menos el límite
        return (float) $this->limite_credito;
    }

    /**
     * Activar cliente
     */
    public function activar(): bool
    {
        return $this->update(['estado' => 'activo']);
    }

    /**
     * Desactivar cliente
     */
    public function desactivar(): bool
    {
        return $this->update(['estado' => 'inactivo']);
    }

    /**
     * Bloquear cliente
     */
    public function bloquear(): bool
    {
        return $this->update(['estado' => 'bloqueado']);
    }

    /**
     * Verificar cliente
     */
    public function verificar(): bool
    {
        return $this->update(['verificado' => true]);
    }
}
