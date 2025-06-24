<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DatosFacturacion extends Model
{
    use HasFactory;

    protected $table = 'datos_facturacion';

    protected $fillable = [
        'cliente_id',
        'tipo_documento',
        'numero_documento',
        'nombre_facturacion',
        'razon_social',
        'direccion_fiscal',
        'distrito_fiscal',
        'provincia_fiscal',
        'departamento_fiscal',
        'codigo_postal_fiscal',
        'telefono_fiscal',
        'email_facturacion',
        'predeterminado',
        'activo',
        'contacto_empresa',
        'giro_negocio',
        'datos_adicionales',
    ];

    protected $casts = [
        'predeterminado' => 'boolean',
        'activo' => 'boolean',
        'datos_adicionales' => 'array',
    ];

    protected $attributes = [
        'predeterminado' => false,
        'activo' => true,
    ];

    // Constantes para tipos de documento
    public const TIPO_DNI = 'dni';
    public const TIPO_RUC = 'ruc';
    public const TIPO_PASAPORTE = 'pasaporte';
    public const TIPO_CARNET_EXTRANJERIA = 'carnet_extranjeria';

    public const TIPOS_DOCUMENTO = [
        self::TIPO_DNI,
        self::TIPO_RUC,
        self::TIPO_PASAPORTE,
        self::TIPO_CARNET_EXTRANJERIA,
    ];

    // Relaciones

    /**
     * Relación con Cliente (N:1)
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    // Scopes

    /**
     * Scope para datos activos
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para datos predeterminados
     */
    public function scopePredeterminados(Builder $query): Builder
    {
        return $query->where('predeterminado', true);
    }

    /**
     * Scope por tipo de documento
     */
    public function scopePorTipoDocumento(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo_documento', $tipo);
    }

    /**
     * Scope para documentos DNI
     */
    public function scopeDni(Builder $query): Builder
    {
        return $query->where('tipo_documento', self::TIPO_DNI);
    }

    /**
     * Scope para documentos RUC
     */
    public function scopeRuc(Builder $query): Builder
    {
        return $query->where('tipo_documento', self::TIPO_RUC);
    }

    // Accessors

    /**
     * Obtener el nombre completo para facturación
     */
    public function getNombreFacturacionCompletoAttribute(): string
    {
        if ($this->tipo_documento === self::TIPO_RUC && $this->razon_social) {
            return $this->razon_social;
        }
        
        return $this->nombre_facturacion;
    }

    /**
     * Obtener dirección fiscal completa
     */
    public function getDireccionFiscalCompletaAttribute(): string
    {
        $direccion = $this->direccion_fiscal;
        $direccion .= ', ' . $this->distrito_fiscal;
        $direccion .= ', ' . $this->provincia_fiscal;
        $direccion .= ', ' . $this->departamento_fiscal;
        
        if ($this->codigo_postal_fiscal) {
            $direccion .= ' - ' . $this->codigo_postal_fiscal;
        }
        
        return $direccion;
    }

    /**
     * Verificar si es empresa
     */
    public function getIsEmpresaAttribute(): bool
    {
        return $this->tipo_documento === self::TIPO_RUC;
    }

    /**
     * Verificar si es persona natural
     */
    public function getIsPersonaNaturalAttribute(): bool
    {
        return in_array($this->tipo_documento, [
            self::TIPO_DNI,
            self::TIPO_PASAPORTE,
            self::TIPO_CARNET_EXTRANJERIA
        ]);
    }

    // Mutators

    /**
     * Formatear número de documento antes de guardar
     */
    public function setNumeroDocumentoAttribute($value): void
    {
        $this->attributes['numero_documento'] = preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Formatear teléfono fiscal antes de guardar
     */
    public function setTelefonoFiscalAttribute($value): void
    {
        if ($value) {
            $this->attributes['telefono_fiscal'] = preg_replace('/[^0-9+]/', '', $value);
        } else {
            $this->attributes['telefono_fiscal'] = null;
        }
    }

    /**
     * Validar email de facturación antes de guardar
     */
    public function setEmailFacturacionAttribute($value): void
    {
        if ($value && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->attributes['email_facturacion'] = strtolower($value);
        } else {
            $this->attributes['email_facturacion'] = null;
        }
    }

    // Métodos adicionales

    /**
     * Verificar si está activo
     */
    public function isActivo(): bool
    {
        return $this->activo === true;
    }

    /**
     * Verificar si es predeterminado
     */
    public function isPredeterminado(): bool
    {
        return $this->predeterminado === true;
    }

    /**
     * Establecer como predeterminado
     */
    public function establecerComoPredeterminado(): bool
    {
        // Primero, quitar el predeterminado de otros registros del mismo cliente
        static::where('cliente_id', $this->cliente_id)
              ->where('id', '!=', $this->id)
              ->update(['predeterminado' => false]);

        // Luego establecer este como predeterminado
        return $this->update(['predeterminado' => true]);
    }

    /**
     * Activar datos de facturación
     */
    public function activar(): bool
    {
        return $this->update(['activo' => true]);
    }

    /**
     * Desactivar datos de facturación
     */
    public function desactivar(): bool
    {
        return $this->update(['activo' => false, 'predeterminado' => false]);
    }

    /**
     * Validar número de documento según tipo
     */
    public function validarNumeroDocumento(): bool
    {
        return match ($this->tipo_documento) {
            self::TIPO_DNI => $this->validarDni(),
            self::TIPO_RUC => $this->validarRuc(),
            default => strlen($this->numero_documento) >= 6
        };
    }

    /**
     * Validar DNI peruano
     */
    private function validarDni(): bool
    {
        return strlen($this->numero_documento) === 8 && is_numeric($this->numero_documento);
    }

    /**
     * Validar RUC peruano
     */
    private function validarRuc(): bool
    {
        if (strlen($this->numero_documento) !== 11 || !is_numeric($this->numero_documento)) {
            return false;
        }

        // Validación básica de RUC peruano
        $ruc = $this->numero_documento;
        $factor = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += (int)$ruc[$i] * $factor[$i];
        }

        $resto = $suma % 11;
        $digitoVerificador = ($resto < 2) ? $resto : 11 - $resto;

        return (int)$ruc[10] === $digitoVerificador;
    }

    /**
     * Obtener información de facturación formateada para comprobantes
     */
    public function toFacturacionArray(): array
    {
        return [
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'nombre_facturacion' => $this->nombre_facturacion_completo,
            'direccion_fiscal' => $this->direccion_fiscal_completa,
            'email_facturacion' => $this->email_facturacion,
            'telefono_fiscal' => $this->telefono_fiscal,
            'is_empresa' => $this->is_empresa,
        ];
    }
}
