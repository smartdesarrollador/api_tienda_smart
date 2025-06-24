<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Cliente;
use App\Models\DatosFacturacion;
use Illuminate\Support\Facades\DB;

class DatosFacturacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clientes = Cliente::all();
        
        if ($clientes->isEmpty()) {
            $this->command->warn('⚠️ No hay clientes. Ejecuta primero ClienteSeeder');
            return;
        }

        $datosFacturacion = [];

        foreach ($clientes as $cliente) {
            // Datos de facturación personal (DNI) - predeterminado
            $datosFacturacion[] = [
                'cliente_id' => $cliente->id,
                'tipo_documento' => DatosFacturacion::TIPO_DNI,
                'numero_documento' => $cliente->dni,
                'nombre_facturacion' => $cliente->nombre_completo,
                'razon_social' => null,
                'direccion_fiscal' => $this->extraerDireccion($cliente->direccion),
                'distrito_fiscal' => $this->extraerDistrito($cliente->direccion),
                'provincia_fiscal' => 'Lima',
                'departamento_fiscal' => 'Lima',
                'codigo_postal_fiscal' => $this->generarCodigoPostal(),
                'telefono_fiscal' => $cliente->telefono,
                'email_facturacion' => $cliente->user->email,
                'predeterminado' => true,
                'activo' => true,
                'contacto_empresa' => null,
                'giro_negocio' => null,
                'datos_adicionales' => json_encode([
                    'tipo_persona' => 'natural',
                    'estado_civil' => $this->generarEstadoCivil(),
                    'ocupacion' => $cliente->profesion
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Algunos clientes también tienen datos empresariales (RUC)
            if (in_array($cliente->id, [1, 2, 5, 7])) { // Solo algunos clientes
                $datosFacturacion[] = [
                    'cliente_id' => $cliente->id,
                    'tipo_documento' => DatosFacturacion::TIPO_RUC,
                    'numero_documento' => $this->generarRuc(),
                    'nombre_facturacion' => $this->generarRazonSocial($cliente->apellidos),
                    'razon_social' => $this->generarRazonSocial($cliente->apellidos),
                    'direccion_fiscal' => $this->generarDireccionEmpresarial(),
                    'distrito_fiscal' => $this->generarDistritoEmpresarial(),
                    'provincia_fiscal' => 'Lima',
                    'departamento_fiscal' => 'Lima',
                    'codigo_postal_fiscal' => $this->generarCodigoPostal(),
                    'telefono_fiscal' => $this->generarTelefonoEmpresarial(),
                    'email_facturacion' => $this->generarEmailEmpresarial($cliente->apellidos),
                    'predeterminado' => false,
                    'activo' => true,
                    'contacto_empresa' => $cliente->nombre_completo,
                    'giro_negocio' => $this->generarGiroNegocio($cliente->profesion),
                    'datos_adicionales' => json_encode([
                        'tipo_persona' => 'juridica',
                        'regimen_tributario' => 'General',
                        'representante_legal' => $cliente->nombre_completo,
                        'fecha_constitucion' => now()->subYears(rand(1, 10))->format('Y-m-d')
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Algunos clientes tienen documentos de extranjeros
            if (in_array($cliente->id, [4, 6])) { // Solo algunos clientes
                $tipoDoc = rand(0, 1) === 0 ? DatosFacturacion::TIPO_PASAPORTE : DatosFacturacion::TIPO_CARNET_EXTRANJERIA;
                $datosFacturacion[] = [
                    'cliente_id' => $cliente->id,
                    'tipo_documento' => $tipoDoc,
                    'numero_documento' => $this->generarDocumentoExtranjero($tipoDoc),
                    'nombre_facturacion' => $cliente->nombre_completo,
                    'razon_social' => null,
                    'direccion_fiscal' => $this->extraerDireccion($cliente->direccion),
                    'distrito_fiscal' => $this->extraerDistrito($cliente->direccion),
                    'provincia_fiscal' => 'Lima',
                    'departamento_fiscal' => 'Lima',
                    'codigo_postal_fiscal' => $this->generarCodigoPostal(),
                    'telefono_fiscal' => $cliente->telefono,
                    'email_facturacion' => $cliente->user->email,
                    'predeterminado' => false,
                    'activo' => true,
                    'contacto_empresa' => null,
                    'giro_negocio' => null,
                    'datos_adicionales' => json_encode([
                        'tipo_persona' => 'natural',
                        'nacionalidad' => $this->generarNacionalidad(),
                        'pais_origen' => $this->generarPaisOrigen()
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('datos_facturacion')->insert($datosFacturacion);
        
        $this->command->info('✅ Se crearon ' . count($datosFacturacion) . ' datos de facturación de prueba');
    }

    /**
     * Extraer dirección de la dirección completa
     */
    private function extraerDireccion(string $direccionCompleta): string
    {
        $partes = explode(',', $direccionCompleta);
        return trim($partes[0] ?? $direccionCompleta);
    }

    /**
     * Extraer distrito de la dirección completa
     */
    private function extraerDistrito(string $direccionCompleta): string
    {
        $partes = explode(',', $direccionCompleta);
        return trim($partes[1] ?? 'Lima');
    }

    /**
     * Generar código postal aleatorio
     */
    private function generarCodigoPostal(): string
    {
        return 'L' . str_pad((string)rand(1, 99), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Generar estado civil aleatorio
     */
    private function generarEstadoCivil(): string
    {
        $estados = ['soltero', 'casado', 'divorciado', 'viudo', 'conviviente'];
        return $estados[array_rand($estados)];
    }

    /**
     * Generar RUC válido
     */
    private function generarRuc(): string
    {
        // Generar RUC que empiece con 20 (empresa)
        $ruc = '20' . str_pad((string)rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        
        // Calcular dígito verificador
        $factor = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        
        for ($i = 0; $i < 10; $i++) {
            $suma += (int)$ruc[$i] * $factor[$i];
        }
        
        $resto = $suma % 11;
        $digitoVerificador = ($resto < 2) ? $resto : 11 - $resto;
        
        return $ruc . $digitoVerificador;
    }

    /**
     * Generar razón social
     */
    private function generarRazonSocial(string $apellidos): string
    {
        $tipos = ['SAC', 'SRL', 'EIRL', 'SA'];
        $prefijos = ['Empresa', 'Comercial', 'Corporación', 'Grupo', 'Inversiones'];
        
        $prefijo = $prefijos[array_rand($prefijos)];
        $tipo = $tipos[array_rand($tipos)];
        
        return "{$prefijo} {$apellidos} {$tipo}";
    }

    /**
     * Generar dirección empresarial
     */
    private function generarDireccionEmpresarial(): string
    {
        $avenidas = ['Av. Javier Prado', 'Av. Arequipa', 'Av. Brasil', 'Av. Universitaria', 'Av. Colonial'];
        $numero = rand(100, 9999);
        $oficina = rand(100, 999);
        
        return $avenidas[array_rand($avenidas)] . " {$numero} Oficina {$oficina}";
    }

    /**
     * Generar distrito empresarial
     */
    private function generarDistritoEmpresarial(): string
    {
        $distritos = ['San Isidro', 'Miraflores', 'Surco', 'La Molina', 'San Borja'];
        return $distritos[array_rand($distritos)];
    }

    /**
     * Generar teléfono empresarial
     */
    private function generarTelefonoEmpresarial(): string
    {
        return '01' . rand(200, 899) . rand(1000, 9999);
    }

    /**
     * Generar email empresarial
     */
    private function generarEmailEmpresarial(string $apellidos): string
    {
        $dominio = strtolower(str_replace(' ', '', $apellidos));
        return "facturacion@{$dominio}.com.pe";
    }

    /**
     * Generar giro de negocio
     */
    private function generarGiroNegocio(string $profesion): string
    {
        $giros = [
            'Ingeniero' => 'Servicios de Ingeniería y Consultoría',
            'Doctor' => 'Servicios Médicos y de Salud',
            'Abogado' => 'Servicios Legales y Asesoría Jurídica',
            'Contador' => 'Servicios Contables y Tributarios',
            'Diseñador' => 'Servicios de Diseño y Publicidad',
            'Comerciante' => 'Comercio al por Mayor y Menor',
        ];

        foreach ($giros as $prof => $giro) {
            if (str_contains($profesion, $prof)) {
                return $giro;
            }
        }

        return 'Actividades Empresariales Diversas';
    }

    /**
     * Generar documento de extranjero
     */
    private function generarDocumentoExtranjero(string $tipo): string
    {
        if ($tipo === DatosFacturacion::TIPO_PASAPORTE) {
            return strtoupper(chr(rand(65, 90)) . chr(rand(65, 90))) . rand(1000000, 9999999);
        }
        
        // Carnet de extranjería
        return str_pad((string)rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
    }

    /**
     * Generar nacionalidad
     */
    private function generarNacionalidad(): string
    {
        $nacionalidades = ['Colombiana', 'Argentina', 'Chilena', 'Ecuatoriana', 'Venezolana', 'Brasileña'];
        return $nacionalidades[array_rand($nacionalidades)];
    }

    /**
     * Generar país de origen
     */
    private function generarPaisOrigen(): string
    {
        $paises = ['Colombia', 'Argentina', 'Chile', 'Ecuador', 'Venezuela', 'Brasil'];
        return $paises[array_rand($paises)];
    }
}
