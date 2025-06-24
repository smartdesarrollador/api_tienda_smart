<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $horaActual = Carbon::now();
        
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            // Seeders básicos (sin dependencias)
            UserSeeder::class,
            
            // Seeders geográficos (orden importante por dependencias)
            DepartamentoSeeder::class,
            ProvinciaSeeder::class,
            DistritoSeeder::class,
            
            // Seeders de delivery y zonas
            ZonaRepartoSeeder::class,
            ZonaDistritoSeeder::class,
            HorarioZonaSeeder::class,
            CostoEnvioDinamicoSeeder::class,
            ExcepcionZonaSeeder::class,
            DireccionValidadaSeeder::class,
            
            // Seeders de productos y categorías
            CategoriaSeeder::class,
            AtributoSeeder::class,
            ValorAtributoSeeder::class,
            ProductoSeeder::class,
            VariacionProductoSeeder::class,
            ImagenProductoSeeder::class,
            
            // Seeders de adicionales (después de productos)
            AdicionalSeeder::class,
            GrupoAdicionalSeeder::class,
            ProductoAdicionalSeeder::class,
            AdicionalGrupoSeeder::class,
            ProductoGrupoAdicionalSeeder::class,
            
            // Seeders de promociones y cupones
            CuponSeeder::class,
            PromocionSeeder::class,
            
            // Seeders de pedidos y transacciones
            MetodoPagoSeeder::class,
            PedidoSeeder::class,
            DetallePedidoSeeder::class,
            DetalleAdicionalSeeder::class,
            PagoSeeder::class,
            CuotaCreditoSeeder::class,
            
            // Seeders de seguimiento y programación (después de pedidos)
            SeguimientoPedidoSeeder::class,
            ProgramacionEntregaSeeder::class,
            
            // Seeders de interacción de usuarios
            DireccionSeeder::class,
            NotificacionSeeder::class,
            FavoritoSeeder::class,
            ComentarioSeeder::class,
            ClienteSeeder::class,
            DatosFacturacionSeeder::class,
            
            // Seeders de inventario y carrito
            CarritoTemporalSeeder::class,
            InventarioMovimientoSeeder::class,
            
            // Seeders de SEO y métricas
            SeoProductoSeeder::class,
            MetricasNegocioSeeder::class,
            
            // Seeders de configuración
            FiltroAvanzadoSeeder::class,
            ConfiguracionesSeeder::class,
            BannerSeeder::class,
            
            // Seeders de auditoría (opcionales)
            // LogAuditoriaSeeder::class,
        ]);
    }
}
