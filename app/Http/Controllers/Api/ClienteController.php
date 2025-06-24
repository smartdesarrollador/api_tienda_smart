<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\ClienteCollection;
use App\Http\Resources\ClienteSimpleResource;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Cliente::query();

            // Aplicar filtros
            $this->applyFilters($query, $request);

            // Aplicar búsqueda
            if ($request->filled('buscar')) {
                $this->applySearch($query, $request->input('buscar'));
            }

            // Aplicar ordenamiento
            $this->applyOrdering($query, $request);

            // Cargar relaciones por defecto
            $query->with(['user:id,name,email,rol,avatar']);

            // Cargar relaciones adicionales si se solicitan
            if ($request->boolean('incluir_datos_facturacion')) {
                $query->with(['datosFacturacion', 'datoFacturacionPredeterminado']);
            }

            if ($request->boolean('incluir_contadores')) {
                $query->withCount(['datosFacturacion', 'datosFacturacionActivos']);
            }

            // Determinar el tipo de response
            $perPage = min($request->input('per_page', 15), 100);
            
            if ($request->boolean('simple')) {
                // Respuesta simplificada para listas
                $clientes = $query->paginate($perPage);
                return response()->json([
                    'data' => ClienteSimpleResource::collection($clientes->items()),
                    'meta' => [
                        'current_page' => $clientes->currentPage(),
                        'last_page' => $clientes->lastPage(),
                        'per_page' => $clientes->perPage(),
                        'total' => $clientes->total(),
                    ],
                    'links' => [
                        'first' => $clientes->url(1),
                        'last' => $clientes->url($clientes->lastPage()),
                        'prev' => $clientes->previousPageUrl(),
                        'next' => $clientes->nextPageUrl(),
                    ]
                ]);
            }

            if ($request->boolean('sin_paginacion')) {
                // Sin paginación (para exportes, etc.)
                $clientes = $query->limit(1000)->get();
                return response()->json(new ClienteCollection($clientes));
            }

            // Respuesta paginada completa
            $clientes = $query->paginate($perPage);
            
            return response()->json([
                'data' => ClienteResource::collection($clientes->items()),
                'meta' => [
                    'current_page' => $clientes->currentPage(),
                    'last_page' => $clientes->lastPage(),
                    'per_page' => $clientes->perPage(),
                    'total' => $clientes->total(),
                    'filtros_aplicados' => $this->getAppliedFilters($request),
                ],
                'links' => [
                    'first' => $clientes->url(1),
                    'last' => $clientes->url($clientes->lastPage()),
                    'prev' => $clientes->previousPageUrl(),
                    'next' => $clientes->nextPageUrl(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener clientes: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener la lista de clientes',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClienteRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cliente = Cliente::create($request->validated());

            // Cargar relaciones para la respuesta
            $cliente->load(['user:id,name,email,rol,avatar']);

            DB::commit();

            Log::info('Cliente creado exitosamente', ['cliente_id' => $cliente->id]);

            return response()->json([
                'message' => 'Cliente creado exitosamente',
                'data' => new ClienteResource($cliente)
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear cliente: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al crear el cliente',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $query = Cliente::where('id', $id);

            // Cargar relaciones básicas
            $query->with([
                'user:id,name,email,rol,avatar,ultimo_login,email_verified_at',
                'datosFacturacion' => function ($query) {
                    $query->where('activo', true);
                },
                'datoFacturacionPredeterminado'
            ]);

            // Cargar relaciones adicionales si se solicitan
            if ($request->boolean('incluir_datos_completos')) {
                $query->with(['datosFacturacionActivos']);
                $query->withCount(['datosFacturacion', 'datosFacturacionActivos']);
            }

            $cliente = $query->firstOrFail();

            return response()->json([
                'data' => new ClienteResource($cliente)
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error al obtener cliente: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener el cliente',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClienteRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cliente = Cliente::findOrFail($id);
            $cliente->update($request->validated());

            // Recargar relaciones para la respuesta
            $cliente->refresh();
            $cliente->load(['user:id,name,email,rol,avatar']);

            DB::commit();

            Log::info('Cliente actualizado exitosamente', ['cliente_id' => $cliente->id]);

            return response()->json([
                'message' => 'Cliente actualizado exitosamente',
                'data' => new ClienteResource($cliente)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar cliente: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al actualizar el cliente',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener perfil del cliente autenticado
     */
    public function perfil(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->rol !== 'cliente') {
                return response()->json([
                    'message' => 'Usuario no autorizado'
                ], 403);
            }

            // Buscar cliente por user_id
            $cliente = Cliente::with([
                'user:id,name,email,rol,profile_image,ultimo_login,email_verified_at,created_at'
            ])->where('user_id', $user->id)->first();

            if (!$cliente) {
                // Si no existe cliente, crear uno básico
                $cliente = $this->crearClienteBasico($user);
            }

            return response()->json([
                'success' => true,
                'data' => new ClienteResource($cliente)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener perfil del cliente: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el perfil',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener configuración del cliente
     */
    public function configuracion(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->rol !== 'cliente') {
                return response()->json([
                    'message' => 'Usuario no autorizado'
                ], 403);
            }

            $cliente = Cliente::where('user_id', $user->id)->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            // Obtener configuración desde metadata
            $configuracion = $cliente->metadata ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'notificaciones' => $configuracion['notificaciones'] ?? $this->getDefaultNotifications(),
                    'privacidad' => $configuracion['privacidad'] ?? $this->getDefaultPrivacy(),
                    'preferencias' => $configuracion['preferencias'] ?? $this->getDefaultPreferences(),
                    'seguridad' => $configuracion['seguridad'] ?? $this->getDefaultSecurity(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener configuración del cliente: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la configuración',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar configuración de privacidad
     */
    public function actualizarPrivacidad(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->rol !== 'cliente') {
                return response()->json([
                    'message' => 'Usuario no autorizado'
                ], 403);
            }

            $cliente = Cliente::where('user_id', $user->id)->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            $request->validate([
                'privacidad.perfilPublico' => 'boolean',
                'privacidad.mostrarEmail' => 'boolean',
                'privacidad.mostrarTelefono' => 'boolean',
                'privacidad.permitirContacto' => 'boolean',
                'privacidad.compartirDatos' => 'boolean',
            ]);

            $metadata = $cliente->metadata ?? [];
            $metadata['privacidad'] = $request->input('privacidad');

            $cliente->update(['metadata' => $metadata]);

            return response()->json([
                'success' => true,
                'message' => 'Configuración de privacidad actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar privacidad: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la configuración',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar preferencias del cliente
     */
    public function actualizarPreferencias(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->rol !== 'cliente') {
                return response()->json([
                    'message' => 'Usuario no autorizado'
                ], 403);
            }

            $cliente = Cliente::where('user_id', $user->id)->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            $request->validate([
                'preferencias.idioma' => 'required|string|in:es,en,pt',
                'preferencias.moneda' => 'required|string|in:PEN,USD,EUR',
                'preferencias.zonaHoraria' => 'required|string',
                'preferencias.tema' => 'required|string|in:light,dark,auto',
                'preferencias.notificacionesEmail' => 'boolean',
                'preferencias.notificacionesPush' => 'boolean',
                'preferencias.newsletterMarketing' => 'boolean',
            ]);

            $metadata = $cliente->metadata ?? [];
            $metadata['preferencias'] = $request->input('preferencias');

            $cliente->update(['metadata' => $metadata]);

            return response()->json([
                'success' => true,
                'message' => 'Preferencias actualizadas correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar preferencias: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar las preferencias',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Crear cliente básico a partir del usuario
     */
    private function crearClienteBasico(User $user): Cliente
    {
        $nombres = explode(' ', $user->name);
        
        return Cliente::create([
            'user_id' => $user->id,
            'nombre_completo' => $nombres[0] ?? 'Usuario',
            'apellidos' => implode(' ', array_slice($nombres, 1)) ?: '',
            'estado' => 'activo',
            'verificado' => false,
            'limite_credito' => 0,
        ]);
    }

    /**
     * Obtener notificaciones por defecto
     */
    private function getDefaultNotifications(): array
    {
        return [
            [
                'id' => 'pedidos',
                'titulo' => 'Pedidos y Envíos',
                'descripcion' => 'Recibe actualizaciones sobre el estado de tus pedidos',
                'email' => true,
                'push' => true,
                'sms' => false,
            ],
            [
                'id' => 'ofertas',
                'titulo' => 'Ofertas y Promociones',
                'descripcion' => 'Entérate de las mejores ofertas y descuentos exclusivos',
                'email' => true,
                'push' => false,
                'sms' => false,
            ],
            [
                'id' => 'cuenta',
                'titulo' => 'Cuenta y Seguridad',
                'descripcion' => 'Notificaciones importantes sobre tu cuenta y seguridad',
                'email' => true,
                'push' => true,
                'sms' => true,
            ],
        ];
    }

    /**
     * Obtener configuración de privacidad por defecto
     */
    private function getDefaultPrivacy(): array
    {
        return [
            'perfilPublico' => false,
            'mostrarEmail' => false,
            'mostrarTelefono' => false,
            'permitirContacto' => true,
            'compartirDatos' => false,
        ];
    }

    /**
     * Obtener preferencias por defecto
     */
    private function getDefaultPreferences(): array
    {
        return [
            'idioma' => 'es',
            'moneda' => 'PEN',
            'zonaHoraria' => 'America/Lima',
            'tema' => 'light',
            'notificacionesEmail' => true,
            'notificacionesPush' => true,
            'newsletterMarketing' => false,
        ];
    }

    /**
     * Obtener configuración de seguridad por defecto
     */
    private function getDefaultSecurity(): array
    {
        return [
            'autenticacionDosFactor' => false,
            'sesionesActivas' => 1,
            'ultimoCambioPassword' => now()->toISOString(),
            'preguntasSeguridad' => false,
        ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cliente = Cliente::findOrFail($id);
            
            // Verificar si se puede eliminar (opcional: agregar lógica de negocio)
            if ($cliente->datosFacturacion()->where('activo', true)->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el cliente. Tiene datos de facturación activos.'
                ], 422);
            }

            $cliente->delete();

            DB::commit();

            Log::info('Cliente eliminado exitosamente', ['cliente_id' => $id]);

            return response()->json([
                'message' => 'Cliente eliminado exitosamente'
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar cliente: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al eliminar el cliente',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de clientes
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => Cliente::count(),
                'activos' => Cliente::activos()->count(),
                'verificados' => Cliente::verificados()->count(),
                'con_credito' => Cliente::conCredito()->count(),
                'por_estado' => Cliente::selectRaw('estado, COUNT(*) as total')
                    ->groupBy('estado')
                    ->pluck('total', 'estado'),
                'por_genero' => Cliente::selectRaw('genero, COUNT(*) as total')
                    ->whereNotNull('genero')
                    ->groupBy('genero')
                    ->pluck('total', 'genero'),
                'limite_credito_total' => Cliente::sum('limite_credito'),
                'promedio_edad' => Cliente::selectRaw('AVG(TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE())) as promedio')
                    ->whereNotNull('fecha_nacimiento')
                    ->value('promedio'),
                'nuevos_ultimo_mes' => Cliente::where('created_at', '>=', now()->subMonth())->count(),
            ];

            return response()->json([
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de clientes: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Cambiar estado del cliente
     */
    public function cambiarEstado(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:activo,inactivo,bloqueado'
        ]);

        try {
            DB::beginTransaction();

            $cliente = Cliente::findOrFail($id);
            $estadoAnterior = $cliente->estado;
            
            $cliente->update(['estado' => $request->input('estado')]);

            DB::commit();

            Log::info('Estado de cliente cambiado', [
                'cliente_id' => $id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $request->input('estado')
            ]);

            return response()->json([
                'message' => 'Estado del cliente actualizado exitosamente',
                'data' => new ClienteResource($cliente)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado del cliente: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al cambiar el estado del cliente',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verificar cliente
     */
    public function verificar(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cliente = Cliente::findOrFail($id);
            $cliente->verificar();

            DB::commit();

            Log::info('Cliente verificado exitosamente', ['cliente_id' => $id]);

            return response()->json([
                'message' => 'Cliente verificado exitosamente',
                'data' => new ClienteResource($cliente)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al verificar cliente: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al verificar el cliente',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Aplicar filtros a la consulta
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->has('verificado')) {
            $query->where('verificado', $request->boolean('verificado'));
        }

        if ($request->has('con_credito')) {
            if ($request->boolean('con_credito')) {
                $query->where('limite_credito', '>', 0);
            } else {
                $query->where('limite_credito', '<=', 0);
            }
        }

        if ($request->filled('genero')) {
            $query->where('genero', $request->input('genero'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('edad_min') || $request->filled('edad_max')) {
            $query->whereNotNull('fecha_nacimiento');
            
            if ($request->filled('edad_min')) {
                $query->whereRaw('TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) >= ?', [$request->input('edad_min')]);
            }
            
            if ($request->filled('edad_max')) {
                $query->whereRaw('TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) <= ?', [$request->input('edad_max')]);
            }
        }
    }

    /**
     * Aplicar búsqueda a la consulta
     */
    private function applySearch($query, string $search): void
    {
        $search = '%' . $search . '%';
        
        $query->where(function ($query) use ($search) {
            $query->where('nombre_completo', 'like', $search)
                  ->orWhere('apellidos', 'like', $search)
                  ->orWhere('dni', 'like', $search)
                  ->orWhere('telefono', 'like', $search)
                  ->orWhereHas('user', function ($query) use ($search) {
                      $query->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                  });
        });
    }

    /**
     * Aplicar ordenamiento a la consulta
     */
    private function applyOrdering($query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSorts = [
            'id', 'created_at', 'updated_at', 'nombre_completo', 
            'dni', 'fecha_nacimiento', 'limite_credito', 'estado'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Obtener filtros aplicados
     */
    private function getAppliedFilters(Request $request): array
    {
        $filters = [];
        
        if ($request->filled('estado')) $filters['estado'] = $request->input('estado');
        if ($request->has('verificado')) $filters['verificado'] = $request->boolean('verificado');
        if ($request->has('con_credito')) $filters['con_credito'] = $request->boolean('con_credito');
        if ($request->filled('genero')) $filters['genero'] = $request->input('genero');
        if ($request->filled('buscar')) $filters['buscar'] = $request->input('buscar');
        
        return $filters;
    }
}
