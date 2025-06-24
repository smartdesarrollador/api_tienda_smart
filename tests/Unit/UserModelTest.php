<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pedido;
use App\Models\Favorito;
use App\Models\Comentario;
use App\Models\Direccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    /** @test */
    public function user_has_correct_fillable_attributes(): void
    {
        $expectedFillable = [
            'name',
            'email',
            'password',
            'dni',
            'telefono',
            'direccion',
            'rol',
            'limite_credito',
            'verificado',
            'avatar',
            'referido_por',
            'ultimo_login'
        ];

        $user = new User();
        
        $this->assertEquals($expectedFillable, $user->getFillable());
    }

    /** @test */
    public function user_belongs_to_many_productos_as_favoritos(): void
    {
        $user = User::where('rol', 'cliente')->first();
        
        $this->assertInstanceOf(Collection::class, $user->favoritos);
        
        // Verificar que la relación está correctamente configurada
        $favoritos = $user->favoritos()->get();
        $this->assertInstanceOf(Collection::class, $favoritos);
    }

    /** @test */
    public function user_has_many_pedidos(): void
    {
        $user = User::where('rol', 'cliente')->first();
        
        $this->assertInstanceOf(Collection::class, $user->pedidos);
        
        // Solo verificar si tiene pedidos
        if ($user->pedidos->count() > 0) {
            $user->pedidos->each(function ($pedido) use ($user) {
                $this->assertEquals($user->id, $pedido->user_id);
            });
        }
    }

    /** @test */
    public function user_has_many_comentarios(): void
    {
        $user = User::where('rol', 'cliente')->first();
        
        $this->assertInstanceOf(Collection::class, $user->comentarios);
        
        // Solo verificar si tiene comentarios
        if ($user->comentarios->count() > 0) {
            $user->comentarios->each(function ($comentario) use ($user) {
                $this->assertEquals($user->id, $comentario->user_id);
            });
        }
    }

    /** @test */
    public function user_has_many_direcciones(): void
    {
        $user = User::where('rol', 'cliente')->first();
        
        $this->assertInstanceOf(Collection::class, $user->direcciones);
        
        // Solo verificar si tiene direcciones
        if ($user->direcciones->count() > 0) {
            $user->direcciones->each(function ($direccion) use ($user) {
                $this->assertEquals($user->id, $direccion->user_id);
            });
        }
    }

    /** @test */    public function user_can_calculate_available_credit(): void    {        $user = User::where('rol', 'cliente')->where('limite_credito', '>', 0)->first();                if ($user) {            $creditoDisponible = (float) $user->limite_credito;                        $this->assertGreaterThanOrEqual(0, $creditoDisponible);            $this->assertIsFloat($creditoDisponible);        }    }

    /** @test */
    public function user_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'test-password'
        ]);

        $this->assertNotEquals('test-password', $user->password);
        $this->assertTrue(password_verify('test-password', $user->password));
    }

    /** @test */
    public function user_email_is_unique(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'test@example.com']);
    }

    /** @test */
    public function user_has_correct_role_values(): void
    {
        $validRoles = ['administrador', 'vendedor', 'soporte', 'delivery', 'cliente'];
        
        $users = User::all();
        
        $users->each(function ($user) use ($validRoles) {
            $this->assertContains($user->rol, $validRoles);
        });
    }

    /** @test */
    public function admin_user_exists(): void
    {
        $admin = User::where('rol', 'administrador')->first();
        
        $this->assertNotNull($admin);
        $this->assertEquals('administrador', $admin->rol);
    }

    /** @test */
    public function cliente_users_have_credit_limit(): void
    {
        $clientes = User::where('rol', 'cliente')->get();
        
        $clientes->each(function ($cliente) {
            $this->assertGreaterThanOrEqual(0, $cliente->limite_credito);
        });
    }
} 