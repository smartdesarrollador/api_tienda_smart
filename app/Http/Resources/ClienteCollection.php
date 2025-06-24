<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClienteCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_clientes' => $this->collection->count(),
                'clientes_activos' => $this->collection->where('estado', 'activo')->count(),
                'clientes_verificados' => $this->collection->where('verificado', true)->count(),
                'clientes_con_credito' => $this->collection->where('limite_credito', '>', 0)->count(),
                'limite_credito_total' => $this->collection->sum('limite_credito'),
                'estadisticas_por_estado' => $this->getEstadisticasPorEstado(),
                'estadisticas_por_genero' => $this->getEstadisticasPorGenero(),
                'version' => '1.0',
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'links' => [
                'self' => $request->url(),
            ],
            'filters_applied' => $this->getFiltersApplied($request),
        ];
    }

    /**
     * Obtener estadísticas por estado
     */
    private function getEstadisticasPorEstado(): array
    {
        return $this->collection->groupBy('estado')->map(function ($clientes, $estado) {
            return [
                'count' => $clientes->count(),
                'percentage' => round(($clientes->count() / $this->collection->count()) * 100, 2),
            ];
        })->toArray();
    }

    /**
     * Obtener estadísticas por género
     */
    private function getEstadisticasPorGenero(): array
    {
        return $this->collection->groupBy('genero')->map(function ($clientes, $genero) {
            return [
                'count' => $clientes->count(),
                'percentage' => round(($clientes->count() / $this->collection->count()) * 100, 2),
            ];
        })->toArray();
    }

    /**
     * Obtener filtros aplicados
     */
    private function getFiltersApplied(Request $request): array
    {
        $filters = [];

        if ($request->has('estado')) {
            $filters['estado'] = $request->get('estado');
        }

        if ($request->has('verificado')) {
            $filters['verificado'] = $request->boolean('verificado');
        }

        if ($request->has('con_credito')) {
            $filters['con_credito'] = $request->boolean('con_credito');
        }

        if ($request->has('genero')) {
            $filters['genero'] = $request->get('genero');
        }

        if ($request->has('buscar')) {
            $filters['buscar'] = $request->get('buscar');
        }

        return $filters;
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'ClienteCollection');
        $response->header('X-Total-Count', (string) $this->collection->count());
    }
}
