<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DetalleAdicional;
use App\Models\DetallePedido;
use App\Models\Adicional;
use App\Models\Pedido;
use Illuminate\Database\Seeder;

class DetalleAdicionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener detalles de pedidos existentes
        $detallesPedidos = DetallePedido::with(['pedido', 'producto'])->get();
        $adicionales = Adicional::where('activo', true)->get();

        if ($detallesPedidos->isEmpty() || $adicionales->isEmpty()) {
            return; // No crear datos si no hay detalles de pedidos o adicionales
        }

        foreach ($detallesPedidos as $detallePedido) {
            // Solo algunos productos tendrán adicionales (60% probabilidad)
            if (mt_rand(1, 100) <= 60) {
                $this->crearAdicionalesParaDetalle($detallePedido, $adicionales);
            }
        }
    }

    private function crearAdicionalesParaDetalle(DetallePedido $detalle, $adicionales): void
    {
        // Determinar cantidad de adicionales (1-3 por producto)
        $cantidadAdicionales = mt_rand(1, 3);
        
        // Seleccionar adicionales relevantes según el tipo de producto
        $adicionalesRelevantes = $this->seleccionarAdicionalesRelevantes(
            $detalle->producto, 
            $adicionales, 
            $cantidadAdicionales
        );

        foreach ($adicionalesRelevantes as $adicionalData) {
            $adicional = $adicionalData['adicional'];
            $cantidad = $adicionalData['cantidad'];
            $precioUnitario = $adicionalData['precio_unitario'];
            
            DetalleAdicional::create([
                'detalle_pedido_id' => $detalle->id,
                'adicional_id' => $adicional->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $cantidad * $precioUnitario,
                'observaciones' => $this->generarObservaciones($adicional),
            ]);
        }
    }

    private function seleccionarAdicionalesRelevantes($producto, $adicionales, int $cantidad): array
    {
        $nombreProducto = strtolower($producto->nombre ?? '');
        $categoriaProducto = strtolower($producto->categoria->nombre ?? '');
        $relevantes = [];

        // Filtrar adicionales según el tipo de producto
        if (str_contains($nombreProducto, 'burger') || str_contains($nombreProducto, 'hamburguesa') ||
            str_contains($categoriaProducto, 'hamburguesa') || str_contains($categoriaProducto, 'comida')) {
            
            // Para hamburguesas: priorizar salsas, quesos y carnes
            $prioridades = [
                'salsa' => 0.5,    // 50% probabilidad
                'queso' => 0.3,    // 30% probabilidad
                'carne' => 0.2,    // 20% probabilidad
                'vegetal' => 0.15, // 15% probabilidad
            ];
            
        } else if (str_contains($nombreProducto, 'pollo') || str_contains($categoriaProducto, 'pollo')) {
            
            // Para pollo: más salsas y vegetales
            $prioridades = [
                'salsa' => 0.6,
                'vegetal' => 0.4,
                'queso' => 0.2,
                'carne' => 0.1,
            ];
            
        } else if (str_contains($categoriaProducto, 'postre')) {
            
            // Para postres: toppings especiales
            $prioridades = [
                'topping' => 0.7,
                'salsa' => 0.2,
            ];
            
        } else {
            
            // Para otros productos: distribución equilibrada
            $prioridades = [
                'salsa' => 0.4,
                'queso' => 0.3,
                'vegetal' => 0.2,
                'carne' => 0.15,
            ];
        }

        // Seleccionar adicionales basado en prioridades
        $adicionalesSeleccionados = collect();
        
        foreach ($prioridades as $tipo => $probabilidad) {
            if (mt_rand(1, 100) <= ($probabilidad * 100)) {
                $adicionalTipo = $adicionales->where('tipo', $tipo)->random();
                if ($adicionalTipo && !$adicionalesSeleccionados->contains('id', $adicionalTipo->id)) {
                    $adicionalesSeleccionados->push($adicionalTipo);
                }
            }
            
            if ($adicionalesSeleccionados->count() >= $cantidad) {
                break;
            }
        }

        // Si no se alcanzó la cantidad deseada, llenar con adicionales aleatorios
        while ($adicionalesSeleccionados->count() < $cantidad) {
            $adicionalAleatorio = $adicionales->random();
            if (!$adicionalesSeleccionados->contains('id', $adicionalAleatorio->id)) {
                $adicionalesSeleccionados->push($adicionalAleatorio);
            }
        }

        // Convertir a formato requerido
        foreach ($adicionalesSeleccionados as $adicional) {
            $cantidadAdicional = mt_rand(1, 2); // 1-2 unidades por adicional
            $precioUnitario = $this->calcularPrecioUnitario($adicional, $producto);
            
            $relevantes[] = [
                'adicional' => $adicional,
                'cantidad' => $cantidadAdicional,
                'precio_unitario' => $precioUnitario,
            ];
        }

        return $relevantes;
    }

    private function calcularPrecioUnitario(Adicional $adicional, $producto): float
    {
        $precioBase = (float) $adicional->precio;
        
        // Aplicar descuentos o recargos según contexto
        if ($adicional->tipo === 'salsa' && mt_rand(1, 10) <= 3) {
            // 30% de las salsas pueden ser gratis en combos
            return 0.00;
        }
        
        if ($adicional->tipo === 'queso' && mt_rand(1, 10) <= 2) {
            // 20% de los quesos tienen descuento especial
            return $precioBase * 0.8;
        }
        
        if ($adicional->tipo === 'carne') {
            // Las carnes pueden tener ligero recargo
            return $precioBase * mt_rand(100, 115) / 100;
        }
        
        return $precioBase;
    }

    private function generarObservaciones(Adicional $adicional): ?string
    {
        $observaciones = [
            'Sin ' . strtolower($adicional->nombre),
            'Extra ' . strtolower($adicional->nombre),
            'Al lado, no mezclado',
            'Poco cantidad',
            'Cantidad normal',
            null, // Sin observaciones
        ];
        
        // 70% probabilidad de no tener observaciones especiales
        if (mt_rand(1, 10) <= 7) {
            return null;
        }
        
        return $observaciones[array_rand($observaciones)];
    }

    private function esPersonalizado(Adicional $adicional): bool
    {
        // 20% de los adicionales son personalizados
        return mt_rand(1, 100) <= 20;
    }

    private function generarIngredientesExtra(Adicional $adicional): ?array
    {
        if (!$this->esPersonalizado($adicional)) {
            return null;
        }
        
        $ingredientesExtra = [
            'salsa' => ['picante extra', 'sin cebolla', 'más ajo'],
            'queso' => ['derretido extra', 'temperatura ambiente', 'doble porción'],
            'carne' => ['bien cocida', 'término medio', 'sin grasa'],
            'vegetal' => ['extra fresco', 'sin aliño', 'cortado fino'],
            'topping' => ['temperatura fría', 'sin azúcar extra', 'porción pequeña'],
        ];
        
        $tipo = $adicional->tipo;
        if (isset($ingredientesExtra[$tipo])) {
            $opciones = $ingredientesExtra[$tipo];
            return [array_rand(array_flip($opciones))];
        }
        
        return ['personalización especial'];
    }

    private function generarDatosPersonalizacion(Adicional $adicional): ?array
    {
        if (!$this->esPersonalizado($adicional)) {
            return null;
        }
        
        return [
            'tipo_personalizacion' => $adicional->tipo,
            'nivel_personalizado' => mt_rand(1, 3), // 1=ligero, 2=moderado, 3=intenso
            'tiempo_extra' => mt_rand(2, 8), // minutos extra de preparación
            'costo_personalizacion' => mt_rand(50, 200) / 100, // S/. 0.50 - S/. 2.00
            'instrucciones_chef' => $this->generarInstruccionesChef($adicional),
        ];
    }

    private function generarInstruccionesChef(Adicional $adicional): string
    {
        $instrucciones = [
            'salsa' => [
                'Aplicar con cuidado, no exceder cantidad',
                'Mezclar bien con otros ingredientes',
                'Servir al lado en recipiente pequeño',
                'Calentar ligeramente antes de servir'
            ],
            'queso' => [
                'Derretir completamente antes de servir',
                'No sobrecocinar para evitar textura gomosa',
                'Aplicar uniformemente sobre el producto',
                'Mantener temperatura hasta el momento de servir'
            ],
            'carne' => [
                'Cocinar al punto solicitado por el cliente',
                'Verificar temperatura interna',
                'Reposar 2 minutos antes de servir',
                'Condimentar según preferencias'
            ],
            'vegetal' => [
                'Mantener frescura y textura crujiente',
                'Lavar bien antes de preparar',
                'Cortar en tamaño uniforme',
                'No sobrecocinar si requiere cocción'
            ],
        ];
        
        $tipo = $adicional->tipo;
        if (isset($instrucciones[$tipo])) {
            $opciones = $instrucciones[$tipo];
            return $opciones[array_rand($opciones)];
        }
        
        return 'Preparar según estándares de calidad';
    }
}
