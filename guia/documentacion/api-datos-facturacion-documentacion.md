# API de Gestión de Datos de Facturación

## Descripción General

Esta API proporciona endpoints completos para la gestión de datos de facturación de clientes, incluyendo diferentes tipos de documentos (DNI, RUC, Pasaporte, Carnet de Extranjería), validaciones específicas y gestión de estados.

## Base URL

```
/api/admin/datos-facturacion
```

## Autenticación

Todas las rutas requieren autenticación JWT válida en el header:

```
Authorization: Bearer {token}
```

---

## Endpoints Principales

### 1. Listar Datos de Facturación

**GET** `/api/admin/datos-facturacion`

Obtiene una lista paginada de todos los datos de facturación con filtros avanzados.

#### Parámetros de Query

| Parámetro             | Tipo    | Descripción                                                       |
| --------------------- | ------- | ----------------------------------------------------------------- |
| `page`                | integer | Número de página (defecto: 1)                                     |
| `per_page`            | integer | Elementos por página (defecto: 15, max: 100)                      |
| `cliente_id`          | integer | Filtrar por ID de cliente                                         |
| `tipo_documento`      | string  | Filtrar por tipo: `dni`, `ruc`, `pasaporte`, `carnet_extranjeria` |
| `activo`              | boolean | Filtrar por estado activo                                         |
| `predeterminado`      | boolean | Filtrar por predeterminados                                       |
| `fecha_desde`         | date    | Filtrar por fecha de creación desde                               |
| `fecha_hasta`         | date    | Filtrar por fecha de creación hasta                               |
| `departamento_fiscal` | string  | Filtrar por departamento fiscal                                   |
| `provincia_fiscal`    | string  | Filtrar por provincia fiscal                                      |
| `buscar`              | string  | Búsqueda en documento, nombre, dirección, email                   |
| `sort_by`             | string  | Campo de ordenamiento                                             |
| `sort_direction`      | string  | Dirección: `asc`, `desc`                                          |
| `sin_paginacion`      | boolean | Sin paginación (max 1000)                                         |

#### Ejemplo de Respuesta

```json
{
    "data": [
        {
            "id": 1,
            "cliente_id": 15,
            "tipo_documento": "dni",
            "numero_documento": "12345678",
            "numero_documento_formateado": "12345678",
            "nombre_facturacion": "Juan Carlos Pérez López",
            "razon_social": null,
            "nombre_facturacion_completo": "Juan Carlos Pérez López",
            "direccion_fiscal": "Av. Los Álamos 123",
            "distrito_fiscal": "San Isidro",
            "provincia_fiscal": "Lima",
            "departamento_fiscal": "Lima",
            "direccion_fiscal_completa": "Av. Los Álamos 123, San Isidro, Lima, Lima",
            "telefono_fiscal": "+51987654321",
            "email_facturacion": "juan.perez@email.com",
            "predeterminado": true,
            "activo": true,
            "is_predeterminado": true,
            "is_activo": true,
            "is_empresa": false,
            "is_persona_natural": true,
            "documento_valido": true,
            "tipo_documento_info": {
                "nombre": "DNI",
                "descripcion": "Documento Nacional de Identidad",
                "longitud": 8,
                "tipo_persona": "Natural"
            },
            "cliente": {
                "id": 15,
                "nombre_completo": "Juan Carlos Pérez López",
                "dni": "12345678",
                "telefono": "+51987654321",
                "estado": "activo"
            },
            "created_at": "2024-01-15 10:30:00",
            "updated_at": "2024-01-15 10:30:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 67,
        "filtros_aplicados": {
            "activo": true,
            "tipo_documento": "dni"
        }
    },
    "links": {
        "first": "http://localhost/api/admin/datos-facturacion?page=1",
        "last": "http://localhost/api/admin/datos-facturacion?page=5",
        "prev": null,
        "next": "http://localhost/api/admin/datos-facturacion?page=2"
    }
}
```

---

### 2. Crear Datos de Facturación

**POST** `/api/admin/datos-facturacion`

Crea nuevos datos de facturación para un cliente.

#### Cuerpo de la Petición

```json
{
    "cliente_id": 15,
    "tipo_documento": "ruc",
    "numero_documento": "20123456789",
    "nombre_facturacion": "EMPRESA DEMO S.A.C.",
    "razon_social": "EMPRESA DEMO SOCIEDAD ANÓNIMA CERRADA",
    "direccion_fiscal": "Av. Javier Prado Este 4200",
    "distrito_fiscal": "Surco",
    "provincia_fiscal": "Lima",
    "departamento_fiscal": "Lima",
    "codigo_postal_fiscal": "15048",
    "telefono_fiscal": "+51987654321",
    "email_facturacion": "facturacion@empresademo.com",
    "predeterminado": true,
    "activo": true,
    "contacto_empresa": "María González",
    "giro_negocio": "Comercio de productos tecnológicos",
    "datos_adicionales": {
        "referencia_fiscal": "Frente al centro comercial",
        "codigo_ubigeo": "150142",
        "agente_retencion": false
    }
}
```

#### Ejemplo de Respuesta (201)

```json
{
    "message": "Datos de facturación creados exitosamente",
    "data": {
        "id": 25,
        "cliente_id": 15,
        "tipo_documento": "ruc",
        "numero_documento": "20123456789",
        "numero_documento_formateado": "20-12345678-9",
        "nombre_facturacion": "EMPRESA DEMO S.A.C.",
        "razon_social": "EMPRESA DEMO SOCIEDAD ANÓNIMA CERRADA",
        "nombre_facturacion_completo": "EMPRESA DEMO SOCIEDAD ANÓNIMA CERRADA",
        "is_empresa": true,
        "documento_valido": true,
        "predeterminado": true,
        "activo": true,
        "created_at": "2024-01-15 14:25:00"
    }
}
```

---

### 3. Mostrar Datos de Facturación

**GET** `/api/admin/datos-facturacion/{id}`

Obtiene los datos de facturación específicos por ID.

#### Parámetros de Query

| Parámetro         | Tipo    | Descripción                    |
| ----------------- | ------- | ------------------------------ |
| `incluir_resumen` | boolean | Incluir información contextual |

#### Ejemplo de Respuesta

```json
{
    "data": {
        "id": 25,
        "cliente_id": 15,
        "tipo_documento": "ruc",
        "numero_documento": "20123456789",
        "numero_documento_formateado": "20-12345678-9",
        "nombre_facturacion": "EMPRESA DEMO S.A.C.",
        "razon_social": "EMPRESA DEMO SOCIEDAD ANÓNIMA CERRADA",
        "nombre_facturacion_completo": "EMPRESA DEMO SOCIEDAD ANÓNIMA CERRADA",
        "direccion_fiscal": "Av. Javier Prado Este 4200",
        "direccion_fiscal_completa": "Av. Javier Prado Este 4200, Surco, Lima, Lima - 15048",
        "contacto_empresa": "María González",
        "giro_negocio": "Comercio de productos tecnológicos",
        "is_empresa": true,
        "documento_valido": true,
        "tipo_documento_info": {
            "nombre": "RUC",
            "descripcion": "Registro Único de Contribuyentes",
            "longitud": 11,
            "tipo_persona": "Jurídica"
        },
        "cliente": {
            "id": 15,
            "nombre_completo": "Juan Carlos Pérez López",
            "dni": "12345678"
        },
        "resumen": {
            "tipo_persona": "Jurídica",
            "documento_descripcion": "Registro Único de Contribuyentes",
            "estado_texto": "Activo (Predeterminado)",
            "tiempo_registro": "hace 2 horas"
        }
    }
}
```

---

### 4. Actualizar Datos de Facturación

**PUT** `/api/admin/datos-facturacion/{id}`

Actualiza los datos de facturación existentes.

#### Cuerpo de la Petición

```json
{
    "nombre_facturacion": "EMPRESA DEMO ACTUALIZADA S.A.C.",
    "direccion_fiscal": "Av. Javier Prado Este 4500",
    "telefono_fiscal": "+51987654322",
    "email_facturacion": "nueva.facturacion@empresademo.com",
    "contacto_empresa": "Pedro Martínez"
}
```

#### Ejemplo de Respuesta

```json
{
    "message": "Datos de facturación actualizados exitosamente",
    "data": {
        "id": 25,
        "nombre_facturacion": "EMPRESA DEMO ACTUALIZADA S.A.C.",
        "direccion_fiscal": "Av. Javier Prado Este 4500",
        "telefono_fiscal": "+51987654322",
        "updated_at": "2024-01-15 16:45:00"
    }
}
```

---

### 5. Eliminar Datos de Facturación

**DELETE** `/api/admin/datos-facturacion/{id}`

Elimina los datos de facturación. No se permite eliminar si es el único dato del cliente.

#### Ejemplo de Respuesta

```json
{
    "message": "Datos de facturación eliminados exitosamente"
}
```

---

## Endpoints Especiales

### 6. Datos por Cliente

**GET** `/api/admin/datos-facturacion/cliente/{clienteId}`

Obtiene todos los datos de facturación de un cliente específico.

#### Parámetros de Query

| Parámetro             | Tipo    | Descripción                   |
| --------------------- | ------- | ----------------------------- |
| `solo_activos`        | boolean | Solo datos activos            |
| `solo_predeterminado` | boolean | Solo dato predeterminado      |
| `tipo_documento`      | string  | Filtrar por tipo de documento |

#### Ejemplo de Respuesta

```json
{
    "data": [
        {
            "id": 25,
            "tipo_documento": "ruc",
            "numero_documento_formateado": "20-12345678-9",
            "nombre_facturacion_completo": "EMPRESA DEMO S.A.C.",
            "predeterminado": true,
            "activo": true
        },
        {
            "id": 26,
            "tipo_documento": "dni",
            "numero_documento_formateado": "12345678",
            "nombre_facturacion_completo": "Juan Carlos Pérez López",
            "predeterminado": false,
            "activo": true
        }
    ],
    "cliente": {
        "id": 15,
        "nombre_completo": "Juan Carlos Pérez López",
        "dni": "12345678"
    },
    "meta": {
        "total": 2,
        "activos": 2,
        "predeterminado": 25
    }
}
```

---

### 7. Establecer como Predeterminado

**POST** `/api/admin/datos-facturacion/{id}/establecer-predeterminado`

Establece los datos de facturación como predeterminados para el cliente.

#### Ejemplo de Respuesta

```json
{
    "message": "Datos de facturación establecidos como predeterminados exitosamente",
    "data": {
        "id": 26,
        "predeterminado": true,
        "activo": true
    }
}
```

---

### 8. Activar Datos

**POST** `/api/admin/datos-facturacion/{id}/activar`

Activa los datos de facturación.

#### Ejemplo de Respuesta

```json
{
    "message": "Datos de facturación activados exitosamente",
    "data": {
        "id": 26,
        "activo": true
    }
}
```

---

### 9. Desactivar Datos

**POST** `/api/admin/datos-facturacion/{id}/desactivar`

Desactiva los datos de facturación. No se permite si es el único activo del cliente.

#### Ejemplo de Respuesta

```json
{
    "message": "Datos de facturación desactivados exitosamente",
    "data": {
        "id": 26,
        "activo": false,
        "predeterminado": false
    }
}
```

---

### 10. Validar Documento

**POST** `/api/admin/datos-facturacion/validar-documento`

Valida un número de documento según su tipo.

#### Cuerpo de la Petición

```json
{
    "tipo_documento": "ruc",
    "numero_documento": "20-123456789"
}
```

#### Ejemplo de Respuesta

```json
{
    "valido": true,
    "numero_documento_limpio": "20123456789",
    "tipo_documento": "ruc",
    "formato_esperado": "11 dígitos numéricos con dígito verificador"
}
```

---

### 11. Estadísticas

**GET** `/api/admin/datos-facturacion/statistics`

Obtiene estadísticas generales de los datos de facturación.

#### Ejemplo de Respuesta

```json
{
    "data": {
        "total": 156,
        "activos": 142,
        "predeterminados": 89,
        "por_tipo_documento": {
            "dni": 89,
            "ruc": 45,
            "pasaporte": 15,
            "carnet_extranjeria": 7
        },
        "por_estado": {
            "activos": 142,
            "inactivos": 14,
            "predeterminados": 89
        },
        "clientes_con_datos": 89,
        "promedio_por_cliente": 1.75,
        "documentos_validos": 149,
        "nuevos_ultimo_mes": 23
    }
}
```

---

## Códigos de Error

| Código | Descripción                         |
| ------ | ----------------------------------- |
| 200    | Operación exitosa                   |
| 201    | Datos creados exitosamente          |
| 404    | Datos de facturación no encontrados |
| 422    | Datos de validación incorrectos     |
| 500    | Error interno del servidor          |

---

## Ejemplos de Uso con cURL

### Crear datos con DNI

```bash
curl -X POST "http://localhost/api/admin/datos-facturacion" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 15,
    "tipo_documento": "dni",
    "numero_documento": "12345678",
    "nombre_facturacion": "Juan Carlos Pérez López",
    "direccion_fiscal": "Av. Los Álamos 123",
    "distrito_fiscal": "San Isidro",
    "provincia_fiscal": "Lima",
    "departamento_fiscal": "Lima",
    "telefono_fiscal": "+51987654321",
    "email_facturacion": "juan.perez@email.com",
    "predeterminado": true
  }'
```

### Crear datos con RUC

```bash
curl -X POST "http://localhost/api/admin/datos-facturacion" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 15,
    "tipo_documento": "ruc",
    "numero_documento": "20123456789",
    "nombre_facturacion": "EMPRESA DEMO S.A.C.",
    "razon_social": "EMPRESA DEMO SOCIEDAD ANÓNIMA CERRADA",
    "direccion_fiscal": "Av. Javier Prado Este 4200",
    "distrito_fiscal": "Surco",
    "provincia_fiscal": "Lima",
    "departamento_fiscal": "Lima",
    "contacto_empresa": "María González",
    "giro_negocio": "Comercio de productos tecnológicos",
    "predeterminado": true
  }'
```

### Validar documento

```bash
curl -X POST "http://localhost/api/admin/datos-facturacion/validar-documento" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_documento": "ruc",
    "numero_documento": "20123456789"
  }'
```

---

## Validaciones y Reglas de Negocio

### Campos requeridos para creación

-   `cliente_id`: Debe existir en la tabla clientes
-   `tipo_documento`: dni, ruc, pasaporte, carnet_extranjeria
-   `numero_documento`: Según validaciones específicas por tipo
-   `nombre_facturacion`: Obligatorio
-   `direccion_fiscal`, `distrito_fiscal`, `provincia_fiscal`, `departamento_fiscal`: Obligatorios

### Validaciones específicas por tipo de documento

-   **DNI**: Exactamente 8 dígitos numéricos
-   **RUC**: Exactamente 11 dígitos numéricos con validación de dígito verificador
-   **Carnet de Extranjería**: Exactamente 9 dígitos numéricos
-   **Pasaporte**: Entre 6 y 12 caracteres alfanuméricos

### Campos requeridos para RUC

-   `razon_social`: Obligatoria para documentos RUC
-   `contacto_empresa`: Obligatorio para documentos RUC
-   `giro_negocio`: Obligatorio para documentos RUC

### Reglas de unicidad

-   Solo puede haber un documento predeterminado activo por cliente
-   No se puede eliminar el único dato de facturación de un cliente
-   No se puede desactivar el único dato activo de un cliente

---

## Notas Importantes

1. **Autorización**: Todas las rutas requieren autenticación JWT válida
2. **Paginación**: Por defecto 15 elementos por página, máximo 100
3. **Filtros**: Múltiples filtros se pueden combinar
4. **Búsqueda**: Busca en número de documento, nombre, razón social, dirección y email
5. **Performance**: Use `sin_paginacion=1` solo para exportes o listas pequeñas
6. **Validación**: El sistema valida automáticamente el formato y dígito verificador de RUC
7. **Estados**: Un cliente debe tener siempre al menos un dato de facturación activo
8. **Predeterminado**: Solo puede haber un dato predeterminado por cliente
