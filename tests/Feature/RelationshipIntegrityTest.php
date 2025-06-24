<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\Categoria;
use App\Models\Atributo;
use App\Models\ValorAtributo;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Comentario;
use App\Models\Favorito;
use App\Models\ImagenProducto;
use App\Models\Direccion;
use App\Models\Notificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RelationshipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    /** @test */
    public function all_productos_belong_to_valid_categorias(): void
    {
        $productos = Producto::with('categoria')->get();
        
        $productos->each(function ($producto) {
            $this->assertNotNull($producto->categoria);
            $this->assertInstanceOf(Categoria::class, $producto->categoria);
            $this->assertEquals($producto->categoria_id, $producto->categoria->id);
        });
    }

    /** @test */
    public function all_variaciones_belong_to_valid_productos(): void
    {
        $variaciones = VariacionProducto::with('producto')->get();
        
        $variaciones->each(function ($variacion) {
            $this->assertNotNull($variacion->producto);
            $this->assertInstanceOf(Producto::class, $variacion->producto);
            $this->assertEquals($variacion->producto_id, $variacion->producto->id);
        });
    }

    /** @test */
    public function all_valores_atributo_belong_to_valid_atributos(): void
    {
        $valores = ValorAtributo::with('atributo')->get();
        
        $valores->each(function ($valor) {
            $this->assertNotNull($valor->atributo);
            $this->assertInstanceOf(Atributo::class, $valor->atributo);
            $this->assertEquals($valor->atributo_id, $valor->atributo->id);
        });
    }

    /** @test */
    public function variacion_valor_pivot_relationships_are_valid(): void
    {
        $variaciones = VariacionProducto::with('valoresAtributo.atributo')->get();
        
        $variaciones->each(function ($variacion) {
            $variacion->valoresAtributo->each(function ($valor) {
                $this->assertInstanceOf(ValorAtributo::class, $valor);
                $this->assertNotNull($valor->atributo);
                $this->assertInstanceOf(Atributo::class, $valor->atributo);
            });
        });
    }

    /** @test */
    public function all_pedidos_belong_to_valid_usuarios(): void
    {
        $pedidos = Pedido::with('usuario')->get();
        
        $pedidos->each(function ($pedido) {
            $this->assertNotNull($pedido->usuario);
            $this->assertInstanceOf(User::class, $pedido->usuario);
            $this->assertEquals($pedido->usuario_id, $pedido->usuario->id);
            $this->assertEquals('cliente', $pedido->usuario->rol);
        });
    }

    /** @test */
    public function all_detalles_pedido_have_valid_relationships(): void
    {
        $detalles = DetallePedido::with(['pedido', 'variacionProducto.producto'])->get();
        
        $detalles->each(function ($detalle) {
            // Verificar relación con pedido
            $this->assertNotNull($detalle->pedido);
            $this->assertInstanceOf(Pedido::class, $detalle->pedido);
            $this->assertEquals($detalle->pedido_id, $detalle->pedido->id);
            
            // Verificar relación con variación de producto
            $this->assertNotNull($detalle->variacionProducto);
            $this->assertInstanceOf(VariacionProducto::class, $detalle->variacionProducto);
            $this->assertEquals($detalle->variacion_producto_id, $detalle->variacionProducto->id);
            
            // Verificar relación indirecta con producto
            $this->assertNotNull($detalle->variacionProducto->producto);
            $this->assertInstanceOf(Producto::class, $detalle->variacionProducto->producto);
        });
    }

    /** @test */
    public function all_comentarios_have_valid_relationships(): void
    {
        $comentarios = Comentario::with(['usuario', 'producto'])->get();
        
        $comentarios->each(function ($comentario) {
            // Verificar relación con usuario
            $this->assertNotNull($comentario->usuario);
            $this->assertInstanceOf(User::class, $comentario->usuario);
            $this->assertEquals($comentario->usuario_id, $comentario->usuario->id);
            
            // Verificar relación con producto
            $this->assertNotNull($comentario->producto);
            $this->assertInstanceOf(Producto::class, $comentario->producto);
            $this->assertEquals($comentario->producto_id, $comentario->producto->id);
            
            // Verificar que el usuario es cliente
            $this->assertEquals('cliente', $comentario->usuario->rol);
        });
    }

    /** @test */
    public function all_favoritos_have_valid_relationships(): void
    {
        $favoritos = Favorito::with(['usuario', 'producto'])->get();
        
        $favoritos->each(function ($favorito) {
            // Verificar relación con usuario
            $this->assertNotNull($favorito->usuario);
            $this->assertInstanceOf(User::class, $favorito->usuario);
            $this->assertEquals($favorito->usuario_id, $favorito->usuario->id);
            
            // Verificar relación con producto
            $this->assertNotNull($favorito->producto);
            $this->assertInstanceOf(Producto::class, $favorito->producto);
            $this->assertEquals($favorito->producto_id, $favorito->producto->id);
            
            // Verificar que el usuario es cliente
            $this->assertEquals('cliente', $favorito->usuario->rol);
        });
    }

    /** @test */
    public function all_imagenes_productos_belong_to_valid_productos(): void
    {
        $imagenes = ImagenProducto::with('producto')->get();
        
        $imagenes->each(function ($imagen) {
            // Verificar relación directa con producto
            if ($imagen->producto_id) {
                $this->assertNotNull($imagen->producto);
                $this->assertInstanceOf(Producto::class, $imagen->producto);
                $this->assertEquals($imagen->producto_id, $imagen->producto->id);
            }
            
            // Verificar relación con variación si existe
            if ($imagen->variacion_producto_id) {
                $this->assertNotNull($imagen->variacionProducto);
                $this->assertInstanceOf(VariacionProducto::class, $imagen->variacionProducto);
                $this->assertEquals($imagen->variacion_producto_id, $imagen->variacionProducto->id);
            }
        });
    }

    /** @test */
    public function all_direcciones_belong_to_valid_usuarios(): void
    {
        $direcciones = Direccion::with('usuario')->get();
        
        $direcciones->each(function ($direccion) {
            $this->assertNotNull($direccion->usuario);
            $this->assertInstanceOf(User::class, $direccion->usuario);
            $this->assertEquals($direccion->usuario_id, $direccion->usuario->id);
            $this->assertEquals('cliente', $direccion->usuario->rol);
        });
    }

    /** @test */
    public function all_notificaciones_belong_to_valid_usuarios(): void
    {
        $notificaciones = Notificacion::with('usuario')->get();
        
        $notificaciones->each(function ($notificacion) {
            $this->assertNotNull($notificacion->usuario);
            $this->assertInstanceOf(User::class, $notificacion->usuario);
            $this->assertEquals($notificacion->usuario_id, $notificacion->usuario->id);
        });
    }

    /** @test */
    public function category_hierarchy_is_consistent(): void
    {
        $categorias = Categoria::with(['categoriaPadre', 'subcategorias'])->get();
        
        $categorias->each(function ($categoria) {
            // Si tiene categoría padre
            if ($categoria->categoria_padre_id) {
                $this->assertNotNull($categoria->categoriaPadre);
                $this->assertInstanceOf(Categoria::class, $categoria->categoriaPadre);
                $this->assertEquals($categoria->categoria_padre_id, $categoria->categoriaPadre->id);
                
                // Verificar que la categoría padre la incluye en sus subcategorías
                $this->assertTrue($categoria->categoriaPadre->subcategorias->contains($categoria));
            }
            
            // Verificar subcategorías
            $categoria->subcategorias->each(function ($subcategoria) use ($categoria) {
                $this->assertEquals($categoria->id, $subcategoria->categoria_padre_id);
            });
        });
    }

    /** @test */
    public function no_orphaned_records_exist(): void
    {
        // Verificar que no hay variaciones sin producto
        $variacionesHuerfanas = VariacionProducto::whereNotExists(function ($query) {
            $query->select('id')
                  ->from('productos')
                  ->whereColumn('productos.id', 'variaciones_productos.producto_id');
        })->count();
        
        $this->assertEquals(0, $variacionesHuerfanas);
        
        // Verificar que no hay valores de atributo sin atributo
        $valoresHuerfanos = ValorAtributo::whereNotExists(function ($query) {
            $query->select('id')
                  ->from('atributos')
                  ->whereColumn('atributos.id', 'valores_atributos.atributo_id');
        })->count();
        
        $this->assertEquals(0, $valoresHuerfanos);
        
        // Verificar que no hay detalles de pedido sin pedido
        $detallesHuerfanos = DetallePedido::whereNotExists(function ($query) {
            $query->select('id')
                  ->from('pedidos')
                  ->whereColumn('pedidos.id', 'detalles_pedidos.pedido_id');
        })->count();
        
        $this->assertEquals(0, $detallesHuerfanos);
    }

    /** @test */
    public function all_foreign_keys_have_valid_references(): void
    {
        // Test que verifica que todas las foreign keys apuntan a registros existentes
        
        // Productos -> Categorías
        $productosInvalidos = Producto::whereNotExists(function ($query) {
            $query->select('id')
                  ->from('categorias')
                  ->whereColumn('categorias.id', 'productos.categoria_id');
        })->count();
        $this->assertEquals(0, $productosInvalidos);
        
        // Pedidos -> Usuarios
        $pedidosInvalidos = Pedido::whereNotExists(function ($query) {
            $query->select('id')
                  ->from('users')
                  ->whereColumn('users.id', 'pedidos.usuario_id');
        })->count();
        $this->assertEquals(0, $pedidosInvalidos);
        
        // Comentarios -> Productos y Usuarios
        $comentariosInvalidos = Comentario::whereNotExists(function ($query) {
            $query->select('id')
                  ->from('productos')
                  ->whereColumn('productos.id', 'comentarios.producto_id');
        })->orWhereNotExists(function ($query) {
            $query->select('id')
                  ->from('users')
                  ->whereColumn('users.id', 'comentarios.usuario_id');
        })->count();
        $this->assertEquals(0, $comentariosInvalidos);
    }
} 