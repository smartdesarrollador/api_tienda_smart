<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

/* use App\Models\Rol; */

//use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pedido> $pedidos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Favorito> $favoritos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Direccion> $direcciones
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notificacion> $notificaciones
 * @property-read \App\Models\Cliente|null $cliente
 */
class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'dni',
        'telefono',
        'direccion',
        'rol',
        'profile_image',
        'limite_credito',
        'verificado',
        'avatar',
        'referido_por',
        'ultimo_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'verificado' => 'boolean',
        'limite_credito' => 'decimal:2',
        'ultimo_login' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /* public function rol()
    {
        return $this->belongsToMany(Rol::class, 'roles_usuarios', 'id_user', 'id_rol');
    } */

    /* public function Empleador()
    {
        return $this->hasOne(Empleador::class);
    }

    public function Trabajador()
    {
        return $this->hasOne(Trabajador::class);
    } */

    /* public function posts()
    {
        return $this->hasMany(Post::class, 'id_autor');
    } */

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class);
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class);
    }

    public function direcciones()
    {
        return $this->hasMany(Direccion::class);
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class);
    }

    public function cupones()
    {
        return $this->belongsToMany(Cupon::class, 'cupon_usuario')
            ->withPivot('usado')
            ->withTimestamps();
    }

    public function logsAuditoria()
    {
        return $this->hasMany(LogAuditoria::class);
    }

    /**
     * Relación con Cliente (1:1)
     */
    public function cliente()
    {
        return $this->hasOne(Cliente::class);
    }

    public function carritoTemporal()
    {
        return $this->hasMany(CarritoTemporal::class);
    }

    public function seguimientosPedidos()
    {
        return $this->hasMany(SeguimientoPedido::class, 'usuario_cambio_id');
    }

    public function movimientosInventario()
    {
        return $this->hasMany(InventarioMovimiento::class, 'usuario_id');
    }

    public function programacionesEntrega()
    {
        return $this->hasMany(ProgramacionEntrega::class, 'repartidor_id');
    }

    public function pedidosComoRepartidor()
    {
        return $this->hasMany(Pedido::class, 'repartidor_id');
    }

    public function scopeVerificados($query)
    {
        return $query->where('verificado', true);
    }

    public function scopePorRol($query, $rol)
    {
        return $query->where('rol', $rol);
    }

    public function scopeClientes($query)
    {
        return $query->where('rol', 'cliente');
    }

    public function scopeAdministradores($query)
    {
        return $query->where('rol', 'administrador');
    }

    public function scopeRepartidores($query)
    {
        return $query->where('rol', 'repartidor');
    }

    // Métodos auxiliares para carrito temporal
    public function obtenerCarritoTemporal()
    {
        return $this->carritoTemporal()->noExpirado()->get();
    }

    public function limpiarCarritoTemporal(): void
    {
        $this->carritoTemporal()->delete();
    }

    public function calcularTotalCarritoTemporal(): float
    {
        return $this->carritoTemporal()
            ->noExpirado()
            ->get()
            ->sum(function ($item) {
                return $item->calcularSubtotal();
            });
    }

    // Métodos auxiliares para repartidores
    public function esRepartidor(): bool
    {
        return $this->rol === 'repartidor';
    }

    public function obtenerPedidosAsignados()
    {
        return $this->pedidosComoRepartidor()
            ->whereIn('estado', ['confirmado', 'preparando', 'enviado'])
            ->get();
    }

    public function obtenerProgramacionesDelDia(string $fecha)
    {
        return $this->programacionesEntrega()
            ->porFecha($fecha)
            ->ordenRuta()
            ->get();
    }
}
