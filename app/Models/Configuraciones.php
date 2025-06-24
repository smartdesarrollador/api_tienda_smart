<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuraciones extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'configuraciones';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
        'grupo',
    ];

    /**
     * Los atributos que deben ser convertidos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtener un valor de configuración por su clave.
     *
     * @param string $clave
     * @param mixed $valorPredeterminado
     * @return mixed
     */
    public static function obtenerValor($clave, $valorPredeterminado = null)
    {
        $configuracion = static::where('clave', $clave)->first();

        if (!$configuracion) {
            return $valorPredeterminado;
        }

        // Procesar el valor según su tipo
        return static::procesarValorSegunTipo($configuracion);
    }

    /**
     * Obtener todas las configuraciones de un grupo específico.
     *
     * @param string $grupo
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function obtenerGrupo($grupo)
    {
        $configuraciones = static::where('grupo', $grupo)->get();
        
        return $configuraciones->map(function ($configuracion) {
            $configuracion->valor_procesado = static::procesarValorSegunTipo($configuracion);
            return $configuracion;
        });
    }

    /**
     * Procesar el valor según su tipo.
     *
     * @param Configuraciones $configuracion
     * @return mixed
     */
    protected static function procesarValorSegunTipo($configuracion)
    {
        switch ($configuracion->tipo) {
            case 'json':
                return json_decode($configuracion->valor, true);
            case 'booleano':
                return (bool) $configuracion->valor;
            case 'numero':
                return (int) $configuracion->valor;
            default:
                return $configuracion->valor;
        }
    }
}
