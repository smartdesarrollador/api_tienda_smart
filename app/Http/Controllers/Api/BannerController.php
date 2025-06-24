<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    /**
     * Mostrar listado de banners activos ordenados.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $banners = Banner::activos()->ordenados()->get();
        return BannerResource::collection($banners);
    }

    /**
     * Mostrar todos los banners (incluyendo inactivos) para panel de administración.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function admin()
    {
        $banners = Banner::ordenados()->get();
        return BannerResource::collection($banners);
    }

    /**
     * Almacenar un nuevo banner.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'texto_boton' => 'required|string|max:50',
            'enlace_boton' => 'required|string',
            'orden' => 'required|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        
        if ($request->hasFile('imagen')) {
            $imagen = $request->file('imagen');
            $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
            $imagen->move(public_path('assets/banners'), $nombreImagen);
            $data['imagen'] = 'assets/banners/' . $nombreImagen;
        }

        $banner = Banner::create($data);
        
        return (new BannerResource($banner))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Mostrar un banner específico.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\BannerResource
     */
    public function show($id)
    {
        $banner = Banner::find($id);
        
        if (!$banner) {
            return response()->json([
                'mensaje' => 'Banner no encontrado'
            ], 404);
        }
        
        return new BannerResource($banner);
    }

    /**
     * Actualizar un banner existente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\BannerResource
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::find($id);
        
        if (!$banner) {
            return response()->json([
                'mensaje' => 'Banner no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'sometimes|required|string|max:150',
            'descripcion' => 'nullable|string',
            'imagen' => 'sometimes|required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'texto_boton' => 'sometimes|required|string|max:50',
            'enlace_boton' => 'sometimes|required|string',
            'orden' => 'sometimes|required|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        
        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior si existe
            if ($banner->imagen && file_exists(public_path($banner->imagen))) {
                unlink(public_path($banner->imagen));
            }
            
            $imagen = $request->file('imagen');
            $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
            $imagen->move(public_path('assets/banners'), $nombreImagen);
            $data['imagen'] = 'assets/banners/' . $nombreImagen;
        }

        $banner->update($data);
        
        return new BannerResource($banner);
    }

    /**
     * Eliminar un banner.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $banner = Banner::find($id);
        
        if (!$banner) {
            return response()->json([
                'mensaje' => 'Banner no encontrado'
            ], 404);
        }

        $banner->delete();
        
        return response()->json([
            'mensaje' => 'Banner eliminado correctamente'
        ]);
    }
} 