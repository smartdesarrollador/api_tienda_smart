<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Atributo;
use App\Models\ValorAtributo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ValorAtributoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Atributo $atributo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear usuario administrador para las pruebas
        $this->adminUser = User::factory()->create([
            'rol' => 'administrador',
            'email_verified_at' => now(),
        ]);

        // Crear un atributo de prueba
        $this->atributo = Atributo::factory()->create([
            'nombre' => 'Color',
            'tipo' => 'color',
            'filtrable' => true,
            'visible' => true,
        ]);
    }

    /** @test */
    public function puede_listar_valores_de_atributo(): void
    {
        // Crear algunos valores de atributo
        ValorAtributo::factory()->count(3)->create([
            'atributo_id' => $this->atributo->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/valores-atributo');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'atributo_id',
                        'valor',
                        'codigo',
                        'imagen',
                        'created_at',
                        'updated_at',
                        'atributo',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    /** @test */
    public function puede_crear_valor_de_atributo(): void
    {
        $datos = [
            'atributo_id' => $this->atributo->id,
            'valor' => 'Rojo',
            'codigo' => '#FF0000',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/valores-atributo', $datos);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'valor' => 'Rojo',
                'codigo' => '#FF0000',
            ]);

        $this->assertDatabaseHas('valores_atributo', [
            'atributo_id' => $this->atributo->id,
            'valor' => 'Rojo',
            'codigo' => '#FF0000',
        ]);
    }

    /** @test */
    public function puede_mostrar_valor_de_atributo_especifico(): void
    {
        $valor = ValorAtributo::factory()->create([
            'atributo_id' => $this->atributo->id,
            'valor' => 'Azul',
            'codigo' => '#0000FF',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/valores-atributo/{$valor->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $valor->id,
                'valor' => 'Azul',
                'codigo' => '#0000FF',
            ]);
    }

    /** @test */
    public function puede_actualizar_valor_de_atributo(): void
    {
        $valor = ValorAtributo::factory()->create([
            'atributo_id' => $this->atributo->id,
            'valor' => 'Verde Original',
        ]);

        $datosActualizacion = [
            'valor' => 'Verde Actualizado',
            'codigo' => '#00FF00',
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/valores-atributo/{$valor->id}", $datosActualizacion);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'valor' => 'Verde Actualizado',
                'codigo' => '#00FF00',
            ]);

        $this->assertDatabaseHas('valores_atributo', [
            'id' => $valor->id,
            'valor' => 'Verde Actualizado',
            'codigo' => '#00FF00',
        ]);
    }

    /** @test */
    public function puede_eliminar_valor_de_atributo(): void
    {
        $valor = ValorAtributo::factory()->create([
            'atributo_id' => $this->atributo->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/valores-atributo/{$valor->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('valores_atributo', [
            'id' => $valor->id,
        ]);
    }

    /** @test */
    public function puede_obtener_valores_por_atributo(): void
    {
        // Crear valores para este atributo
        ValorAtributo::factory()->count(3)->create([
            'atributo_id' => $this->atributo->id,
        ]);

        // Crear valores para otro atributo (no deben aparecer)
        $otroAtributo = Atributo::factory()->create();
        ValorAtributo::factory()->count(2)->create([
            'atributo_id' => $otroAtributo->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/valores-atributo/atributo/{$this->atributo->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function puede_crear_valores_masivamente(): void
    {
        $valores = [
            ['valor' => 'Rojo', 'codigo' => '#FF0000'],
            ['valor' => 'Verde', 'codigo' => '#00FF00'],
            ['valor' => 'Azul', 'codigo' => '#0000FF'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/valores-atributo/atributo/{$this->atributo->id}/bulk", [
                'valores' => $valores,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'creados',
                'errores',
                'total_creados',
                'total_errores',
            ]);

        foreach ($valores as $valor) {
            $this->assertDatabaseHas('valores_atributo', [
                'atributo_id' => $this->atributo->id,
                'valor' => $valor['valor'],
                'codigo' => $valor['codigo'],
            ]);
        }
    }

    /** @test */
    public function puede_obtener_estadisticas(): void
    {
        // Crear algunos valores de prueba
        ValorAtributo::factory()->count(5)->create([
            'atributo_id' => $this->atributo->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/valores-atributo/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_valores',
                    'valores_con_imagen',
                    'valores_con_codigo',
                    'valores_en_uso',
                    'por_tipo_atributo',
                    'top_atributos',
                ]
            ]);
    }

    /** @test */
    public function valida_datos_requeridos_al_crear(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/valores-atributo', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['atributo_id', 'valor']);
    }

    /** @test */
    public function valida_unicidad_de_valor_por_atributo(): void
    {
        // Crear un valor existente
        ValorAtributo::factory()->create([
            'atributo_id' => $this->atributo->id,
            'valor' => 'Rojo',
        ]);

        // Intentar crear otro valor con el mismo nombre para el mismo atributo
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/valores-atributo', [
                'atributo_id' => $this->atributo->id,
                'valor' => 'Rojo',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['valor']);
    }

    /** @test */
    public function permite_mismos_valores_en_diferentes_atributos(): void
    {
        $otroAtributo = Atributo::factory()->create([
            'nombre' => 'Tamaño',
            'tipo' => 'tamaño',
        ]);

        // Crear valor en primer atributo
        ValorAtributo::factory()->create([
            'atributo_id' => $this->atributo->id,
            'valor' => 'Grande',
        ]);

        // Crear el mismo valor en otro atributo (debe ser permitido)
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/valores-atributo', [
                'atributo_id' => $otroAtributo->id,
                'valor' => 'Grande',
            ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function filtra_valores_correctamente(): void
    {
        // Crear valores de prueba
        ValorAtributo::factory()->create([
            'atributo_id' => $this->atributo->id,
            'valor' => 'Rojo Oscuro',
            'codigo' => '#800000',
        ]);

        ValorAtributo::factory()->create([
            'atributo_id' => $this->atributo->id,
            'valor' => 'Azul Claro',
            'codigo' => '#ADD8E6',
        ]);

        // Filtrar por valor
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/valores-atributo?valor=Rojo');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Filtrar por atributo_id
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/valores-atributo?atributo_id={$this->atributo->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function requiere_autenticacion_para_acceder(): void
    {
        $response = $this->getJson('/api/admin/valores-atributo');
        
        $response->assertStatus(401);
    }
} 