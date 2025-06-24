<?php

namespace App\Traits;

use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

trait JwtTrait
{
    /**
     * Genera un token JWT para un usuario válido
     *
     * @param array $credentials
     * @return array|bool
     */
    protected function generateToken(array $credentials)
    {
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return false;
            }
            
            // Obtener el usuario autenticado
            $user = JWTAuth::user();
            
            // Generar un token personalizado con datos del usuario
            $customClaims = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s')
            ];
            
            // Invalidar el token generado anteriormente solo si existe
            try {
                if (JWTAuth::getToken()) {
                    JWTAuth::invalidate(JWTAuth::getToken());
                }
            } catch (JWTException $e) {
                // Ignoramos este error porque podría no haber token para invalidar
            }
            
            // Crear un nuevo token con los claims personalizados
            $token = JWTAuth::claims($customClaims)->fromUser($user);

            return [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ];
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * Invalida un token JWT
     *
     * @return bool
     */
    protected function invalidateToken()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return true;
        } catch (TokenExpiredException $e) {
            return true; // Si ya expiró, lo consideramos como invalidado
        } catch (TokenInvalidException $e) {
            return false;
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * Obtiene el usuario autenticado con JWT
     *
     * @return \App\Models\User|null
     */
    protected function getAuthenticatedUser()
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return null;
        } catch (TokenInvalidException $e) {
            return null;
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Refresca un token JWT
     *
     * @return array|bool
     */
    protected function refreshToken()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ];
        } catch (TokenExpiredException $e) {
            return false;
        } catch (TokenInvalidException $e) {
            return false;
        } catch (JWTException $e) {
            return false;
        }
    }
} 