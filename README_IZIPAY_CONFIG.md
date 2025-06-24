# Configuración de Izipay para Tienda Virtual

## Descripción

Esta guía te ayudará a configurar correctamente Izipay en tu proyecto de tienda virtual.

## Credenciales de Prueba

Para configurar el entorno de pruebas, necesitas las siguientes credenciales de Izipay:

### Variables de Entorno (.env)

Agrega las siguientes variables a tu archivo `.env`:

```bash
# Configuraciones de Izipay para TEST
IZIPAY_USERNAME=69876357
IZIPAY_PASSWORD=testpassword_7vAtvN49E8Ad6e6ihMqIOvOHC6QV5YKmIXgxr2y9dQ8JQ
IZIPAY_PUBLIC_KEY=69876357:testpublickey_TxzPjl9xKlhM0a6tfSVNilHj2d1Le5Gr8t3JQpqyOhNUf
IZIPAY_SHA256_KEY=testsha256key_Ri54fR3VDBvjECztJ23wFQOSJ5AJeWKaTSTkR64ixKHjK
IZIPAY_API_URL=https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment
IZIPAY_ENDPOINT=https://static.micuentaweb.pe
```

### Obtener Credenciales Reales

Para obtener tus credenciales reales de Izipay:

1. Ingresa al Back Office de Izipay
2. Ve a `Configuración > Tienda > Claves API`
3. Copia las credenciales correspondientes

## Verificar Configuración

Para verificar que tu configuración está correcta, ejecuta:

```bash
# Endpoint para verificar configuración
GET /api/checkout/izipay/configuracion
```

## Errores Comunes

### 1. "The selected datos personales.documento tipo is invalid"

**Solución**: Asegúrate de que el campo `documento_tipo` sea exactamente uno de: `DNI`, `CE`, `Pasaporte`

### 2. "The selected pedido id is invalid"

**Solución**: Verifica que el pedido existe y está en estado 'pendiente' antes de generar el formToken

### 3. "Izipay: Credenciales no configuradas"

**Solución**: Revisa que todas las variables de entorno estén configuradas en el archivo `.env`

## Flujo de Integración

1. **Crear Pedido**: Se crea un pedido en estado 'pendiente'
2. **Generar FormToken**: Se solicita el formToken a Izipay con los datos del pedido
3. **Mostrar Formulario**: Se carga el formulario de pago de Izipay
4. **Procesar Pago**: El usuario completa el pago
5. **Validar Respuesta**: Se valida la respuesta y se actualiza el estado del pedido

## Tarjetas de Prueba

Para testing, puedes usar estas tarjetas:

-   **Visa**: 4970100000000055
-   **Mastercard**: 5555555555554444
-   **Fecha**: Cualquier fecha futura
-   **CVV**: 123

## Referencias

-   [Documentación Oficial Izipay](https://secure.micuentaweb.pe/doc/es-PE/rest/V4.0/javascript/guide/start.html)
-   [Obtener Credenciales](https://github.com/izipay-pe/obtener-credenciales-de-conexion)
