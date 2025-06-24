<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Direccion extends Model
{
    use HasFactory;

    protected $table = 'direcciones';

    protected $fillable = [
        'user_id',
        'distrito_id',
        'direccion',
        'referencia',
        'codigo_postal',
        'numero_exterior',
        'numero_interior',
        'urbanizacion',
        'etapa',
        'manzana',
        'lote',
        'latitud',
        'longitud',
        'predeterminada',
        'validada',
        'alias',
        'instrucciones_entrega',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'distrito_id' => 'integer',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'predeterminada' => 'boolean',
        'validada' => 'boolean',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class);
    }

    public function direccionValidada(): HasOne
    {
        return $this->hasOne(DireccionValidada::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'direccion_validada_id');
    }

    // Scopes
    public function scopePorUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePredeterminada($query)
    {
        return $query->where('predeterminada', true);
    }

    public function scopeValidada($query)
    {
        return $query->where('validada', true);
    }

    public function scopePorDistrito($query, int $distritoId)
    {
        return $query->where('distrito_id', $distritoId);
    }

    // Métodos auxiliares
    public function getDireccionCompletaAttribute(): string
    {
        $direccion = $this->direccion;
        
        if ($this->numero_exterior) {
            $direccion .= ' ' . $this->numero_exterior;
        }
        
        if ($this->numero_interior) {
            $direccion .= ' Int. ' . $this->numero_interior;
        }
        
        if ($this->urbanizacion) {
            $direccion .= ', Urb. ' . $this->urbanizacion;
        }
        
        if ($this->etapa) {
            $direccion .= ', Etapa ' . $this->etapa;
        }
        
        if ($this->manzana) {
            $direccion .= ', Mz. ' . $this->manzana;
        }
        
        if ($this->lote) {
            $direccion .= ', Lote ' . $this->lote;
        }
        
        $direccion .= ', ' . $this->distrito->nombre;
        $direccion .= ', ' . $this->distrito->provincia->nombre;
        $direccion .= ', ' . $this->distrito->provincia->departamento->nombre;
        
        return $direccion;
    }

    public function getProvinciaAttribute()
    {
        return $this->distrito->provincia;
    }

    public function getDepartamentoAttribute()
    {
        return $this->distrito->provincia->departamento;
    }

    public function marcarComoPredeterminada(): void
    {
        // Desmarcar otras direcciones como predeterminadas del mismo usuario
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['predeterminada' => false]);
        
        // Marcar esta como predeterminada
        $this->update(['predeterminada' => true]);
    }

    public function validarDireccion(): void
    {
        // Aquí puedes implementar lógica de validación con servicios de geocodificación
        // Por ejemplo, usando Google Maps API o similar
        
        $this->update(['validada' => true]);
        
        // Crear o actualizar registro de direccion_validada
        $this->direccionValidada()->updateOrCreate(
            [],
            [
                'latitud' => $this->latitud,
                'longitud' => $this->longitud,
                'en_zona_cobertura' => $this->verificarCobertura(),
                'fecha_ultima_validacion' => now(),
            ]
        );
    }

    private function verificarCobertura(): bool
    {
        // Verificar si el distrito tiene delivery disponible
        if (!$this->distrito->disponible_delivery) {
            return false;
        }

        // Verificar si hay zonas de reparto que cubran este distrito
        return $this->distrito->zonasReparto()
            ->where('activo', true)
            ->exists();
    }

    public function calcularDistanciaATienda(): ?float
    {
        // Implementar cálculo de distancia usando coordenadas GPS
        // Por ahora retorna null, pero aquí implementarías la fórmula de Haversine
        // o usarías servicios como Google Distance Matrix API
        return null;
    }
} 