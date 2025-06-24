<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductoCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => ProductoSimpleResource::collection($this->collection),
            'meta' => [
                'total' => $this->collection->count(),
                'categorias_representadas' => $this->getCategorias(),
                'rango_precios' => $this->getRangoPrecios(),
                'productos_destacados' => $this->collection->where('destacado', true)->count(),
                'productos_con_oferta' => $this->collection->whereNotNull('precio_oferta')->count(),
                'stock_total' => $this->collection->sum('stock'),
            ],
            'filtros' => [
                'marcas_disponibles' => $this->getMarcas(),
                'precios' => [
                    'min' => $this->collection->min('precio'),
                    'max' => $this->collection->max('precio'),
                ],
                'estados_stock' => $this->getEstadosStock(),
            ]
        ];
    }
    
    private function getCategorias(): array
    {
        return $this->collection
            ->groupBy('categoria_id')
            ->map(function ($productos, $categoriaId) {
                $primerProducto = $productos->first();
                return [
                    'id' => $categoriaId,
                    'nombre' => $primerProducto->categoria->nombre ?? 'Sin categoría',
                    'productos_count' => $productos->count(),
                ];
            })
            ->values()
            ->toArray();
    }
    
    private function getRangoPrecios(): array
    {
        $precios = $this->collection->pluck('precio');
        
        return [
            'min' => $precios->min(),
            'max' => $precios->max(),
            'promedio' => round($precios->avg(), 2),
        ];
    }
    
    private function getMarcas(): array
    {
        return $this->collection
            ->pluck('marca')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
    
    private function getEstadosStock(): array
    {
        return [
            'disponible' => $this->collection->where('stock', '>', 20)->count(),
            'stock_limitado' => $this->collection->whereBetween('stock', [6, 20])->count(),
            'stock_bajo' => $this->collection->whereBetween('stock', [1, 5])->count(),
            'agotado' => $this->collection->where('stock', 0)->count(),
        ];
    }
} 