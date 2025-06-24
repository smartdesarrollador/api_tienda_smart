<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConfiguracionResource;
use App\Models\Configuraciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfiguracionesController extends Controller
{
    /**
     * Mostrar listado de todas las configuraciones.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        // Filtrar por grupo si se proporciona
        if ($request->has('grupo')) {
            $configuraciones = Configuraciones::where('grupo', $request->grupo)->get();
        } else {
            $configuraciones = Configuraciones::all();
        }
        
        return ConfiguracionResource::collection($configuraciones);
    }

    /**
     * Obtener todas las configuraciones agrupadas por grupo.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function grupos()
    {
        $grupos = Configuraciones::select('grupo')->distinct()->get()->pluck('grupo');
        
        $configuracionesPorGrupo = [];
        foreach ($grupos as $grupo) {
            $configuracionesPorGrupo[$grupo] = ConfiguracionResource::collection(
                Configuraciones::where('grupo', $grupo)->get()
            );
        }
        
        return response()->json([
            'grupos' => $grupos,
            'configuraciones' => $configuracionesPorGrupo
        ]);
    }

    /**
     * Mostrar los detalles de una configuración específica.
     *
     * @param  string  $clave
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($clave)
    {
        $configuracion = Configuraciones::where('clave', $clave)->first();
        
        if (!$configuracion) {
            return response()->json([
                'mensaje' => 'Configuración no encontrada'
            ], 404);
        }
        
        return new ConfiguracionResource($configuracion);
    }

    /**
     * Actualizar una configuración existente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $configuracion = Configuraciones::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'valor' => 'required_without:archivo',
            'archivo' => 'sometimes|file|max:10240', // 10MB máximo
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Si es una configuración de tipo imagen y hay un archivo
        if ($configuracion->tipo === 'imagen' && $request->hasFile('archivo')) {
            // Definir la ruta de destino
            $rutaDestino = 'assets/configuraciones/' . $configuracion->grupo;
            $rutaCompleta = public_path($rutaDestino);
            
            // Crear el directorio si no existe
            if (!File::exists($rutaCompleta)) {
                File::makeDirectory($rutaCompleta, 0755, true);
            }
            
            // Borrar la imagen anterior si existe
            if ($configuracion->valor && File::exists(public_path($configuracion->valor))) {
                File::delete(public_path($configuracion->valor));
            }
            
            // Guardar la nueva imagen
            $archivo = $request->file('archivo');
            $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
            $archivo->move($rutaCompleta, $nombreArchivo);
            
            $configuracion->valor = $rutaDestino . '/' . $nombreArchivo;
        } else {
            $configuracion->valor = $request->valor;
        }
        
        $configuracion->save();
        
        return new ConfiguracionResource($configuracion);
    }

    /**
     * Actualizar múltiples configuraciones a la vez.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'configuraciones' => 'required|array',
            'configuraciones.*.id' => 'required|exists:configuraciones,id',
            'configuraciones.*.valor' => 'required',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        foreach ($request->configuraciones as $item) {
            $configuracion = Configuraciones::findOrFail($item['id']);
            $configuracion->valor = $item['valor'];
            $configuracion->save();
        }
        
        return response()->json([
            'mensaje' => 'Configuraciones actualizadas correctamente'
        ]);
    }

    /**
     * Obtener todas las configuraciones como un objeto clave-valor.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerTodas()
    {
        $configuraciones = Configuraciones::all();
        
        $resultado = [];
        foreach ($configuraciones as $configuracion) {
            $valor = $configuracion->valor;
            
            // Procesar el valor según su tipo
            switch ($configuracion->tipo) {
                case 'json':
                    $valor = json_decode($configuracion->valor, true);
                    break;
                case 'booleano':
                    $valor = (bool) $configuracion->valor;
                    break;
                case 'numero':
                    $valor = (int) $configuracion->valor;
                    break;
            }
            
            $resultado[$configuracion->clave] = $valor;
        }
        
        return response()->json($resultado);
    }

    /**
     * Obtener una imagen de configuración.
     *
     * @param  string  $clave
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function obtenerImagen($clave)
    {
        $configuracion = Configuraciones::where('clave', $clave)
            ->where('tipo', 'imagen')
            ->first();
        
        if (!$configuracion) {
            return response()->json([
                'mensaje' => 'Imagen de configuración no encontrada'
            ], 404);
        }
        
        $rutaImagen = public_path($configuracion->valor);
        
        if (!File::exists($rutaImagen)) {
            return response()->json([
                'mensaje' => 'Archivo de imagen no encontrado'
            ], 404);
        }
        
        return response()->file($rutaImagen);
    }

    /**
     * Subir una imagen para una configuración mediante POST.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function subirImagen(Request $request, $id)
    {
        $configuracion = Configuraciones::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|max:10240', // 10MB máximo
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Verificar que la configuración sea de tipo imagen
        if ($configuracion->tipo !== 'imagen') {
            return response()->json([
                'mensaje' => 'Esta configuración no es de tipo imagen'
            ], 422);
        }
        
        // Definir la ruta de destino
        $rutaDestino = 'assets/configuraciones/' . $configuracion->grupo;
        $rutaCompleta = public_path($rutaDestino);
        
        // Crear el directorio si no existe
        if (!File::exists($rutaCompleta)) {
            File::makeDirectory($rutaCompleta, 0755, true);
        }
        
        // Borrar la imagen anterior si existe
        if ($configuracion->valor && File::exists(public_path($configuracion->valor))) {
            File::delete(public_path($configuracion->valor));
        }
        
        // Guardar la nueva imagen
        $archivo = $request->file('archivo');
        $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
        $archivo->move($rutaCompleta, $nombreArchivo);
        
        $configuracion->valor = $rutaDestino . '/' . $nombreArchivo;
        $configuracion->save();
        
        return new ConfiguracionResource($configuracion);
    }
}
