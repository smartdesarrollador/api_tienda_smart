<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodosPago = [
            // Tarjetas de Crédito
            [
                'nombre' => 'Visa',
                'slug' => 'visa',
                'tipo' => MetodoPago::TIPO_TARJETA_CREDITO,
                'descripcion' => 'Tarjeta de crédito Visa',
                'logo' => 'assets/metodos-pago/visa.png',
                'activo' => true,
                'requiere_verificacion' => true,
                'comision_porcentaje' => 3.500,
                'comision_fija' => 0.50,
                'monto_minimo' => 10.00,
                'monto_maximo' => 10000.00,
                'orden' => 1,
                'proveedor' => MetodoPago::PROVEEDOR_CULQI,
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => true,
                'cuotas_maximas' => 12,
                'instrucciones' => 'Ingrese los datos de su tarjeta de crédito Visa',
                'icono_clase' => 'fab fa-cc-visa',
                'color_primario' => '#1A1F71',
                'tiempo_procesamiento' => 5,
                'configuracion' => [
                    'public_key' => 'pk_test_visa',
                    'private_key' => 'sk_test_visa',
                    'webhook_url' => 'https://api.mitienda.com/webhooks/culqi'
                ],
                'paises_disponibles' => ['PE', 'CO', 'MX', 'CL']
            ],
            [
                'nombre' => 'Mastercard',
                'slug' => 'mastercard',
                'tipo' => MetodoPago::TIPO_TARJETA_CREDITO,
                'descripcion' => 'Tarjeta de crédito Mastercard',
                'logo' => 'assets/metodos-pago/mastercard.png',
                'activo' => true,
                'requiere_verificacion' => true,
                'comision_porcentaje' => 3.500,
                'comision_fija' => 0.50,
                'monto_minimo' => 10.00,
                'monto_maximo' => 10000.00,
                'orden' => 2,
                'proveedor' => MetodoPago::PROVEEDOR_CULQI,
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => true,
                'cuotas_maximas' => 12,
                'instrucciones' => 'Ingrese los datos de su tarjeta de crédito Mastercard',
                'icono_clase' => 'fab fa-cc-mastercard',
                'color_primario' => '#EB001B',
                'tiempo_procesamiento' => 5,
                'configuracion' => [
                    'public_key' => 'pk_test_mastercard',
                    'private_key' => 'sk_test_mastercard',
                    'webhook_url' => 'https://api.mitienda.com/webhooks/culqi'
                ],
                'paises_disponibles' => ['PE', 'CO', 'MX', 'CL']
            ],
            
            // Tarjetas de Débito
            [
                'nombre' => 'Visa Débito',
                'slug' => 'visa-debito',
                'tipo' => MetodoPago::TIPO_TARJETA_DEBITO,
                'descripcion' => 'Tarjeta de débito Visa',
                'logo' => 'assets/metodos-pago/visa-debito.png',
                'activo' => true,
                'requiere_verificacion' => true,
                'comision_porcentaje' => 2.500,
                'comision_fija' => 0.30,
                'monto_minimo' => 5.00,
                'monto_maximo' => 10000.00,
                'orden' => 3,
                'proveedor' => MetodoPago::PROVEEDOR_CULQI,
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => false,
                'instrucciones' => 'Ingrese los datos de su tarjeta de débito Visa',
                'icono_clase' => 'fab fa-cc-visa',
                'color_primario' => '#1A1F71',
                'tiempo_procesamiento' => 2,
                'configuracion' => [
                    'public_key' => 'pk_test_visa_debito',
                    'private_key' => 'sk_test_visa_debito'
                ],
                'paises_disponibles' => ['PE']
            ],

            // Billeteras Digitales
            [
                'nombre' => 'Yape',
                'slug' => 'yape',
                'tipo' => MetodoPago::TIPO_BILLETERA_DIGITAL,
                'descripcion' => 'Billetera digital Yape del BCP',
                'logo' => 'assets/metodos-pago/yape.png',
                'activo' => true,
                'requiere_verificacion' => false,
                'comision_porcentaje' => 0.000,
                'comision_fija' => 0.00,
                'monto_minimo' => 1.00,
                'monto_maximo' => 500.00,
                'orden' => 4,
                'proveedor' => 'yape',
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => false,
                'instrucciones' => 'Escanee el código QR desde su app Yape',
                'icono_clase' => 'fas fa-mobile-alt',
                'color_primario' => '#722ED1',
                'tiempo_procesamiento' => 1,
                'configuracion' => [
                    'numero_yape' => '999999999',
                    'nombre_comercio' => 'Mi Tienda Virtual'
                ],
                'paises_disponibles' => ['PE']
            ],
            [
                'nombre' => 'Plin',
                'slug' => 'plin',
                'tipo' => MetodoPago::TIPO_BILLETERA_DIGITAL,
                'descripcion' => 'Billetera digital Plin',
                'logo' => 'assets/metodos-pago/plin.png',
                'activo' => true,
                'requiere_verificacion' => false,
                'comision_porcentaje' => 0.000,
                'comision_fija' => 0.00,
                'monto_minimo' => 1.00,
                'monto_maximo' => 500.00,
                'orden' => 5,
                'proveedor' => 'plin',
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => false,
                'instrucciones' => 'Escanee el código QR desde su app Plin',
                'icono_clase' => 'fas fa-mobile-alt',
                'color_primario' => '#00D4AA',
                'tiempo_procesamiento' => 1,
                'configuracion' => [
                    'numero_plin' => '999999999',
                    'nombre_comercio' => 'Mi Tienda Virtual'
                ],
                'paises_disponibles' => ['PE']
            ],

            // Transferencias Bancarias
            [
                'nombre' => 'Transferencia BCP',
                'slug' => 'transferencia-bcp',
                'tipo' => MetodoPago::TIPO_TRANSFERENCIA,
                'descripcion' => 'Transferencia bancaria - Banco de Crédito del Perú',
                'logo' => 'assets/metodos-pago/bcp.png',
                'activo' => true,
                'requiere_verificacion' => true,
                'comision_porcentaje' => 0.000,
                'comision_fija' => 5.00,
                'monto_minimo' => 20.00,
                'orden' => 6,
                'proveedor' => 'manual',
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => false,
                'instrucciones' => 'Realice la transferencia a la cuenta corriente y envíe el comprobante',
                'icono_clase' => 'fas fa-university',
                'color_primario' => '#002A8D',
                'tiempo_procesamiento' => 60,
                'configuracion' => [
                    'numero_cuenta' => '194-123456789-0-12',
                    'banco' => 'Banco de Crédito del Perú',
                    'titular' => 'Mi Empresa SAC',
                    'ruc' => '20123456789',
                    'cci' => '00219400123456789012'
                ],
                'paises_disponibles' => ['PE']
            ],
            [
                'nombre' => 'Transferencia Interbank',
                'slug' => 'transferencia-interbank',
                'tipo' => MetodoPago::TIPO_TRANSFERENCIA,
                'descripcion' => 'Transferencia bancaria - Interbank',
                'logo' => 'assets/metodos-pago/interbank.png',
                'activo' => true,
                'requiere_verificacion' => true,
                'comision_porcentaje' => 0.000,
                'comision_fija' => 5.00,
                'monto_minimo' => 20.00,
                'orden' => 7,
                'proveedor' => 'manual',
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => false,
                'instrucciones' => 'Realice la transferencia a la cuenta corriente y envíe el comprobante',
                'icono_clase' => 'fas fa-university',
                'color_primario' => '#00A859',
                'tiempo_procesamiento' => 60,
                'configuracion' => [
                    'numero_cuenta' => '898-123456789',
                    'banco' => 'Interbank',
                    'titular' => 'Mi Empresa SAC',
                    'ruc' => '20123456789',
                    'cci' => '00389800123456789012'
                ],
                'paises_disponibles' => ['PE']
            ],

            // PayPal
            [
                'nombre' => 'PayPal',
                'slug' => 'paypal',
                'tipo' => MetodoPago::TIPO_BILLETERA_DIGITAL,
                'descripcion' => 'Pague con PayPal de forma segura',
                'logo' => 'assets/metodos-pago/paypal.png',
                'activo' => true,
                'requiere_verificacion' => true,
                'comision_porcentaje' => 5.000,
                'comision_fija' => 1.00,
                'monto_minimo' => 5.00,
                'monto_maximo' => 10000.00,
                'orden' => 8,
                'proveedor' => MetodoPago::PROVEEDOR_PAYPAL,
                'moneda_soportada' => 'USD',
                'permite_cuotas' => false,
                'instrucciones' => 'Será redirigido a PayPal para completar el pago',
                'icono_clase' => 'fab fa-paypal',
                'color_primario' => '#003087',
                'tiempo_procesamiento' => 10,
                'configuracion' => [
                    'client_id' => 'paypal_client_id',
                    'client_secret' => 'paypal_client_secret',
                    'sandbox' => true,
                    'webhook_url' => 'https://api.mitienda.com/webhooks/paypal'
                ],
                'paises_disponibles' => ['PE', 'CO', 'MX', 'CL', 'US', 'ES']
            ],

            // Efectivo
            [
                'nombre' => 'Pago en Efectivo',
                'slug' => 'efectivo',
                'tipo' => MetodoPago::TIPO_EFECTIVO,
                'descripcion' => 'Pago contra entrega en efectivo',
                'logo' => 'assets/metodos-pago/efectivo.png',
                'activo' => true,
                'requiere_verificacion' => false,
                'comision_porcentaje' => 0.000,
                'comision_fija' => 0.00,
                'monto_minimo' => 10.00,
                'monto_maximo' => 1000.00,
                'orden' => 9,
                'proveedor' => 'manual',
                'moneda_soportada' => 'PEN',
                'permite_cuotas' => false,
                'instrucciones' => 'Pague en efectivo al momento de la entrega. Tenga el monto exacto.',
                'icono_clase' => 'fas fa-money-bill-wave',
                'color_primario' => '#52C41A',
                'tiempo_procesamiento' => 0,
                'configuracion' => [
                    'requiere_cambio' => true,
                    'denominaciones_aceptadas' => [10, 20, 50, 100, 200]
                ],
                'paises_disponibles' => ['PE']
            ]
        ];

        foreach ($metodosPago as $metodo) {
            MetodoPago::create($metodo);
        }
    }
} 