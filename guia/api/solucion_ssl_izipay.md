# Solución de Problemas SSL con Izipay en VPS Ubuntu

## ⚠️ **PROBLEMA PRINCIPAL IDENTIFICADO: Configuración de Dominio**

**El problema más probable es que las credenciales de Izipay están configuradas para `senshi.pe` pero estás intentando usarlas desde `tienda.smartdigitaltec.com`.**

### Verificación rápida del problema de dominio

```bash
GET https://tienda.smartdigitaltec.com/api/checkout/test-domain-config
```

## Problema

El método de pago Izipay funciona en desarrollo local pero falla en el VPS Ubuntu con error 500, probablemente debido a problemas SSL/TLS **Y configuración de dominio**.

## 🎯 **Solución Principal: Configurar Dominio en Izipay**

### **Paso 1: Acceder al Back Office de Izipay**

1. Ir a: https://secure.micuentaweb.pe/vads-merchant/
2. Iniciar sesión con las credenciales del comercio
3. Ir a **Configuración** → **Configuración de la tienda**

### **Paso 2: Actualizar URLs en Izipay**

**Cambiar la configuración actual:**

-   ❌ **URL actual:** `https://senshi.pe`
-   ✅ **URL nueva:** `https://tienda.smartdigitaltec.com`

**URLs específicas a configurar:**

1. **URL principal de la tienda:**

    ```
    https://tienda.smartdigitaltec.com
    ```

2. **URL de retorno en modo test:**

    ```
    https://tienda.smartdigitaltec.com/checkout/success
    ```

3. **URL de retorno en modo producción:**

    ```
    https://tienda.smartdigitaltec.com/checkout/success
    ```

4. **URL de notificación IPN:**

    ```
    https://tienda.smartdigitaltec.com/api/checkout/izipay/ipn
    ```

5. **URLs de error/cancelación:**
    ```
    https://tienda.smartdigitaltec.com/checkout/error
    https://tienda.smartdigitaltec.com/checkout/cancel
    ```

### **Paso 3: Verificar configuración**

Después de guardar los cambios en Izipay, verificar con:

```bash
curl -X GET https://tienda.smartdigitaltec.com/api/checkout/test-domain-config
```

## 🔧 **Alternativas si no puedes cambiar la configuración de Izipay:**

### **Opción A: Solicitar configuración adicional**

Contactar al soporte de Izipay para:

-   Agregar `tienda.smartdigitaltec.com` como dominio autorizado adicional
-   Crear una configuración separada para tu dominio

### **Opción B: Usar subdomain de senshi.pe (temporal)**

Si tienes control del dominio senshi.pe:

-   Crear un subdomain: `tienda.senshi.pe`
-   Configurar un proxy o redirect hacia tu VPS

## Diagnóstico

### 1. Verificar configuración SSL

Ejecuta el endpoint de diagnóstico:

```bash
GET https://tudominio.com/api/checkout/diagnostico
```

### 2. Verificar conectividad SSL específica

```bash
GET https://tudominio.com/api/checkout/diagnostico-ssl
```

## Soluciones

### Solución 1: Actualizar certificados CA en Ubuntu

```bash
# Actualizar paquetes del sistema
sudo apt update

# Instalar/actualizar certificados CA
sudo apt install ca-certificates

# Actualizar certificados
sudo update-ca-certificates

# Verificar que existen los certificados
ls -la /etc/ssl/certs/ca-certificates.crt
```

### Solución 2: Instalar curl y OpenSSL actualizados

```bash
# Instalar curl con soporte SSL
sudo apt install curl libcurl4-openssl-dev

# Verificar versión de curl
curl --version

# Verificar soporte SSL
curl -I https://api.micuentaweb.pe
```

### Solución 3: Configurar PHP con extensiones SSL

```bash
# Instalar extensiones PHP necesarias
sudo apt install php-curl php-openssl

# Verificar que están habilitadas
php -m | grep curl
php -m | grep openssl

# Reiniciar PHP-FPM (ajustar versión según corresponda)
sudo systemctl restart php8.1-fpm
```

### Solución 4: Configuración temporal para deshabilitar SSL (solo para pruebas)

Agrega al archivo `.env` de producción:

```env
# SOLO PARA PRUEBAS - No usar en producción final
IZIPAY_DISABLE_SSL_VERIFY=true
IZIPAY_DEBUG_MODE=true
```

### Solución 5: Configuración de firewall

```bash
# Verificar que el firewall permite conexiones HTTPS salientes
sudo ufw status

# Si está bloqueado, permitir conexiones HTTPS salientes
sudo ufw allow out 443/tcp
```

### Solución 6: Configuración avanzada de cURL

Agrega al archivo `.env`:

```env
# Configuración de timeouts
IZIPAY_TIMEOUT=120
IZIPAY_CONNECT_TIMEOUT=60
IZIPAY_RETRY_ATTEMPTS=5
IZIPAY_RETRY_DELAY=3000
```

### Solución 7: Verificar DNS y conectividad

```bash
# Verificar que resuelve DNS
nslookup api.micuentaweb.pe

# Probar conectividad directa
curl -v https://api.micuentaweb.pe

# Probar con certificados específicos
curl --cacert /etc/ssl/certs/ca-certificates.crt https://api.micuentaweb.pe
```

## Verificación de la solución

### 1. Probar diagnóstico después de cambios

```bash
curl -X GET https://tudominio.com/api/checkout/diagnostico
```

### 2. Revisar logs de Laravel

```bash
tail -f storage/logs/laravel.log
```

### 3. Probar checkout completo

Realizar una prueba de checkout desde el frontend y verificar que no hay errores 500.

## Configuración recomendada para producción

Una vez solucionado el problema SSL, usar esta configuración en `.env`:

```env
# Configuración de Izipay para producción
IZIPAY_USERNAME=tu_username
IZIPAY_PASSWORD=tu_password
IZIPAY_PUBLIC_KEY=tu_public_key
IZIPAY_SHA256_KEY=tu_sha256_key
IZIPAY_API_URL=https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment
IZIPAY_ENDPOINT=https://api.micuentaweb.pe
IZIPAY_DISABLE_SSL_VERIFY=false
IZIPAY_TIMEOUT=60
IZIPAY_CONNECT_TIMEOUT=30
IZIPAY_RETRY_ATTEMPTS=3
IZIPAY_RETRY_DELAY=2000
IZIPAY_DEBUG_MODE=false
```

## Logs importantes a verificar

### Laravel logs

```bash
tail -f storage/logs/laravel.log | grep -i izipay
```

### Nginx/Apache logs

```bash
# Nginx
sudo tail -f /var/log/nginx/error.log

# Apache
sudo tail -f /var/log/apache2/error.log
```

### System logs

```bash
sudo tail -f /var/log/syslog | grep -i ssl
```

## Comandos de diagnóstico adicionales

```bash
# Verificar versión de OpenSSL
openssl version -a

# Verificar certificados del sistema
openssl x509 -in /etc/ssl/certs/ca-certificates.crt -text -noout | head -20

# Probar conexión SSL específica
openssl s_client -connect api.micuentaweb.pe:443 -servername api.micuentaweb.pe

# Verificar configuración de PHP
php -i | grep -i ssl
php -i | grep -i curl
```

## Notas importantes

1. **Nunca deshabilites SSL en producción** excepto para diagnóstico temporal
2. **Mantén actualizados** los certificados CA del sistema
3. **Verifica regularmente** que las conexiones SSL funcionan correctamente
4. **Usa logs detallados** para identificar problemas específicos
5. **Prueba después de cada cambio** para confirmar que funciona

## Contacto de soporte

Si ninguna solución funciona, contactar al equipo de soporte de Izipay con:

-   Logs detallados del error
-   Información del servidor (OS, PHP, curl versions)
-   Resultados del diagnóstico SSL
