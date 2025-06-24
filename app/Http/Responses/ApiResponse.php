<?php

namespace App\Http\Responses;

class ApiResponse
{
    /**
     * Success response with data
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function success($data = null, string $message = 'Operación exitosa', int $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Error response
     *
     * @param string $message
     * @param mixed $errors
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error(string $message = 'Ha ocurrido un error', $errors = null, int $code = 400)
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
} 