<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Rol;
use App\Models\Permiso;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTExceptions;


use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Responses\ApiResponse;


class UserController extends Controller
{
    // Roles permitidos para acceder al panel de administración
    private $allowedRolesForAdmin = ['administrador', 'autor'];

    /* Funciones Blog */

    public function index(Request $request)
    {
        // Parámetros de paginación y filtrado
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');
        $rol = $request->get('rol', '');
        $page = $request->get('page', 1);

        // Construir la consulta base
        $query = User::query();

        // Aplicar filtro de búsqueda si existe
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Aplicar filtro por rol si existe
        if (!empty($rol)) {
            $query->where('rol', $rol);
        }

        // Ejecutar la consulta con paginación
        $users = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success([
            'data' => UserResource::collection($users->items()),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ], 'Lista de usuarios obtenida correctamente');
    }

    public function create()
    {
        // En una API RESTful, esta función no se utiliza normalmente.
        return response()->json(['message' => 'Method not allowed'], 405);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'rol' => 'required|in:autor,administrador,cliente',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', $validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => $request->rol,
        ]);

        return ApiResponse::success(new UserResource($user), 'Usuario creado correctamente', 201);
    }

    public function show(User $user)
    {
        return ApiResponse::success(new UserResource($user), 'Usuario obtenido correctamente');
    }

    public function edit(User $user)
    {
        // En una API RESTful, esta función no se utiliza normalmente.
        return response()->json(['message' => 'Method not allowed'], 405);
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8',
            'rol' => 'sometimes|required|in:autor,administrador,cliente',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', $validator->errors(), 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }
        
        if ($request->has('rol')) {
            $user->rol = $request->rol;
        }
        
        $user->save();

        return ApiResponse::success(new UserResource($user), 'Usuario actualizado correctamente');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return ApiResponse::success(null, 'Usuario eliminado correctamente');
    }
    
    /**
     * Actualizar el perfil del usuario autenticado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        // Obtener el usuario por ID en lugar de auth()->user()
        $userId = auth()->id();
        $user = User::find($userId);
        
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', null, 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'rol' => 'required|in:autor,administrador,cliente',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', $validator->errors(), 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->rol = $request->rol;
        $user->save();

        return ApiResponse::success(new UserResource($user), 'Perfil actualizado correctamente');
    }

    /**
     * Cambiar la contraseña del usuario autenticado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(Request $request)
    {
        // Obtener el usuario por ID en lugar de auth()->user()
        $userId = auth()->id();
        $user = User::find($userId);
        
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', null, 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', $validator->errors(), 422);
        }

        // Verificar que la contraseña actual es correcta
        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('La contraseña actual no es correcta', null, 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return ApiResponse::success(null, 'Contraseña actualizada correctamente');
    }

    /**
     * Verificar si un usuario tiene acceso al panel de administración
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAdminAccess(Request $request)
    {
        // Obtener el usuario autenticado
        $userId = auth()->id();
        $user = User::find($userId);
        
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', null, 401);
        }

        // Verificar si el rol del usuario está permitido para acceder al panel de admin
        $hasAccess = in_array($user->rol, $this->allowedRolesForAdmin);
        
        if (!$hasAccess) {
            return ApiResponse::error('Acceso denegado: No tienes permisos para acceder al panel de administración', null, 403);
        }

        return ApiResponse::success(['hasAccess' => true], 'Usuario tiene acceso al panel de administración');
    }

    /**
     * Subir imagen de perfil
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadProfileImage(Request $request)
    {
        // Obtener el usuario por ID en lugar de auth()->user()
        $userId = auth()->id();
        $user = User::find($userId);
        
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', null, 401);
        }

        $validator = Validator::make($request->all(), [
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', $validator->errors(), 422);
        }

        // Crear directorio si no existe
        $assetsDir = public_path('assets/images/profiles');
        if (!file_exists($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        // Eliminar imagen anterior si existe
        $oldImagePath = public_path('assets/images/profiles/' . $user->id . '.jpg');
        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }

        // Guardar nueva imagen
        $image = $request->file('profile_image');
        $image->move($assetsDir, $user->id . '.jpg');
        
        // Actualizar ruta en la base de datos
        $user->profile_image = 'assets/images/profiles/' . $user->id . '.jpg';
        $user->save();

        return ApiResponse::success(['image_path' => asset('assets/images/profiles/' . $user->id . '.jpg')], 'Imagen de perfil actualizada correctamente');
    }
    
    /**
     * Obtener imagen de perfil
     *
     * @param int $userId
     * @return \Illuminate\Http\Response
     */
    public function getProfileImage($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        
        $imagePath = public_path('assets/images/profiles/' . $user->id . '.jpg');
        
        if (!file_exists($imagePath)) {
            return response()->json(['error' => 'Imagen no encontrada'], 404);
        }
        
        return response()->file($imagePath);
    }
}
