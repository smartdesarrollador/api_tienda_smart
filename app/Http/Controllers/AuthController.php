<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Traits\JwtTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use JwtTrait;

    /**
     * Crear un nuevo usuario
     *
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => $request->rol ?? 'cliente',
        ]);

        $token = $this->generateToken([
            'email' => $request->email,
            'password' => $request->password
        ]);

        if (!$token) {
            return ApiResponse::error('No se pudo generar el token de autenticación', null, 500);
        }

        return ApiResponse::success([
            'user' => $user,
            'authorization' => $token
        ], 'Usuario registrado exitosamente', 201);
    }

    /**
     * Iniciar sesión y obtener token JWT
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $token = $this->generateToken($request->only('email', 'password'));

        if (!$token) {
            return ApiResponse::error('Credenciales inválidas', null, 401);
        }

        $user = $this->getAuthenticatedUser();

        return ApiResponse::success([
            'user' => $user,
            'authorization' => $token
        ], 'Inicio de sesión exitoso');
    }

    /**
     * Obtener información del usuario autenticado
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return ApiResponse::error('No autorizado', null, 401);
        }

        return ApiResponse::success($user, 'Perfil de usuario');
    }

    /**
     * Cerrar sesión (invalidar token JWT)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        if ($this->invalidateToken()) {
            return ApiResponse::success(null, 'Sesión cerrada exitosamente');
        } else {
            return ApiResponse::error('No se pudo cerrar la sesión', null, 500);
        }
    }

    /**
     * Refrescar token JWT
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $token = $this->refreshToken();

        if (!$token) {
            return ApiResponse::error('No se pudo refrescar el token', null, 401);
        }

        return ApiResponse::success(['authorization' => $token], 'Token refrescado exitosamente');
    }

    /**
     * Enviar email con enlace para restablecer contraseña
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $token = Str::random(64);
        $email = $request->email;

        // Eliminar tokens anteriores para este email
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Crear nuevo token de recuperación
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        // Enviar email
        try {
            Mail::send('emails.reset_password', ['token' => $token, 'email' => $email], function($message) use($email) {
                $message->to($email);
                $message->subject('Recuperación de contraseña');
            });

            return ApiResponse::success(null, 'Se ha enviado un correo de recuperación de contraseña');
        } catch (\Exception $e) {
            return ApiResponse::error('No se pudo enviar el correo de recuperación', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validar token de restablecimiento de contraseña
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateResetToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
        ]);

        $tokenData = DB::table('password_reset_tokens')
            ->where('token', $request->token)
            ->where('email', $request->email)
            ->first();

        if (!$tokenData) {
            return ApiResponse::error('Token de recuperación inválido', null, 400);
        }

        // Verificar si el token ha expirado (24 horas)
        $createdAt = Carbon::parse($tokenData->created_at);
        if (Carbon::now()->diffInHours($createdAt) > 24) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return ApiResponse::error('El token de recuperación ha expirado', null, 400);
        }

        return ApiResponse::success(null, 'Token válido');
    }

    /**
     * Restablecer contraseña con token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $tokenData = DB::table('password_reset_tokens')
            ->where('token', $request->token)
            ->where('email', $request->email)
            ->first();

        if (!$tokenData) {
            return ApiResponse::error('Token de recuperación inválido', null, 400);
        }

        // Actualizar contraseña
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Eliminar token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return ApiResponse::success(null, 'Contraseña restablecida exitosamente');
    }

    /**
     * Cambiar contraseña del usuario autenticado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
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
     * Actualizar el perfil del usuario autenticado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $userId = auth()->id();
        $user = User::find($userId);
        
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', null, 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'rol' => 'required|in:autor,administrador',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', $validator->errors(), 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->rol = $request->rol;
        $user->save();

        return ApiResponse::success($user, 'Perfil actualizado correctamente');
    }

    /**
     * Subir imagen de perfil
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadProfileImage(Request $request)
    {
        // Obtener el usuario por ID 
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