# API de Clientes - Documentación

## Descripción General

La API de clientes proporciona endpoints completos para la gestión de clientes del sistema, incluyendo operaciones CRUD, filtros avanzados, búsquedas y estadísticas.

## Autenticación

Todas las rutas requieren autenticación mediante el middleware `jwt.verify`.

```
Authorization: Bearer {token}
```

## Endpoints Disponibles

### 1. Listar Clientes

**GET** `/api/admin/clientes`

Obtiene una lista paginada de clientes con filtros opcionales.

#### Parámetros de Query

| Parámetro                   | Tipo    | Descripción                                           |
| --------------------------- | ------- | ----------------------------------------------------- |
| `page`                      | integer | Número de página (defecto: 1)                         |
| `per_page`                  | integer | Elementos por página (defecto: 15, max: 100)          |
| `estado`                    | string  | Filtrar por estado: `activo`, `inactivo`, `bloqueado` |
| `verificado`                | boolean | Filtrar por clientes verificados                      |
| `con_credito`               | boolean | Filtrar por clientes con crédito                      |
| `genero`                    | string  | Filtrar por género: `M`, `F`, `O`                     |
| `fecha_desde`               | date    | Filtrar por fecha de registro desde                   |
| `fecha_hasta`               | date    | Filtrar por fecha de registro hasta                   |
| `edad_min`                  | integer | Edad mínima                                           |
| `edad_max`                  | integer | Edad máxima                                           |
| `buscar`                    | string  | Búsqueda en nombre, DNI, teléfono, email              |
| `sort_by`                   | string  | Campo de ordenamiento                                 |
| `sort_direction`            | string  | Dirección: `asc`, `desc`                              |
| `simple`                    | boolean | Respuesta simplificada                                |
| `sin_paginacion`            | boolean | Sin paginación (max 1000)                             |
| `incluir_datos_facturacion` | boolean | Incluir datos de facturación                          |
| `incluir_contadores`        | boolean | Incluir contadores                                    |

#### Ejemplo de Respuesta

```json
{
    "data": [
        {
            "id": 1,
            "user_id": 11,
            "dni": "12345678",
            "telefono": "987654321",
            "direccion": "Av. Javier Prado 123, San Isidro",
            "nombre_completo": "Juan Carlos",
            "apellidos": "Pérez González",
            "fecha_nacimiento": "1985-05-15",
            "genero": "M",
            "limite_credito": 5000.0,
            "credito_disponible": 5000.0,
            "tiene_credito": true,
            "verificado": true,
            "estado": "activo",
            "is_activo": true,
            "is_verificado": true,
            "referido_por": null,
            "profesion": "Ingeniero",
            "empresa": "TechCorp S.A.C.",
            "ingresos_mensuales": 4500.0,
            "preferencias": {
                "categorias_favoritas": [1, 3, 5],
                "notificaciones_email": true,
                "notificaciones_sms": false
            },
            "metadata": {
                "fuente_registro": "web",
                "utm_source": "google",
                "utm_campaign": "verano2024"
            },
            "nombre_completo_formateado": "Juan Carlos Pérez González",
            "edad": 38,
            "usuario": {
                "id": 11,
                "name": "Juan Carlos",
                "email": "juan.perez@email.com",
                "rol": "cliente",
                "avatar": null,
                "ultimo_login": "2024-01-15 10:30:00",
                "email_verified_at": "2024-01-10 09:15:00"
            },
            "created_at": "2024-01-10 09:15:00",
            "updated_at": "2024-01-15 10:30:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 45,
        "filtros_aplicados": {
            "estado": "activo",
            "verificado": true
        }
    },
    "links": {
        "first": "http://localhost:8000/api/admin/clientes?page=1",
        "last": "http://localhost:8000/api/admin/clientes?page=3",
        "prev": null,
        "next": "http://localhost:8000/api/admin/clientes?page=2"
    }
}
```

### 2. Obtener Cliente Específico

**GET** `/api/admin/clientes/{id}`

#### Parámetros de Query

| Parámetro                 | Tipo    | Descripción                    |
| ------------------------- | ------- | ------------------------------ |
| `incluir_datos_completos` | boolean | Incluir todas las relaciones   |
| `incluir_resumen`         | boolean | Incluir información contextual |

#### Ejemplo de Respuesta

```json
{
    "data": {
        "id": 1,
        // ... todos los campos del cliente
        "resumen": {
            "edad_anos": 38,
            "tiempo_como_cliente": "hace 5 días",
            "estado_credito": "Con crédito",
            "estado_verificacion": "Verificado",
            "origen_registro": "web"
        }
    }
}
```

### 3. Crear Cliente

**POST** `/api/admin/clientes`

#### Cuerpo de la Petición

```json
{
    "user_id": 11,
    "dni": "12345678",
    "telefono": "+51987654321",
    "direccion": "Av. Javier Prado 123, San Isidro",
    "nombre_completo": "Juan Carlos",
    "apellidos": "Pérez González",
    "fecha_nacimiento": "1985-05-15",
    "genero": "M",
    "limite_credito": 5000.0,
    "verificado": false,
    "referido_por": "María García",
    "profesion": "Ingeniero",
    "empresa": "TechCorp S.A.C.",
    "ingresos_mensuales": 4500.0,
    "preferencias": {
        "categorias_favoritas": [1, 3, 5],
        "notificaciones_email": true,
        "notificaciones_sms": false
    },
    "metadata": {
        "fuente_registro": "web",
        "utm_source": "google",
        "utm_campaign": "verano2024"
    },
    "estado": "activo"
}
```

#### Respuesta de Éxito (201)

```json
{
    "message": "Cliente creado exitosamente",
    "data": {
        // ... datos del cliente creado
    }
}
```

### 4. Actualizar Cliente

**PUT/PATCH** `/api/admin/clientes/{id}`

#### Cuerpo de la Petición

```json
{
    "telefono": "+51999888777",
    "direccion": "Nueva dirección 456",
    "limite_credito": 7500.0,
    "preferencias": {
        "categorias_favoritas": [1, 2, 3, 7],
        "notificaciones_email": true,
        "notificaciones_sms": true
    }
}
```

### 5. Eliminar Cliente

**DELETE** `/api/admin/clientes/{id}`

#### Respuesta de Éxito (200)

```json
{
    "message": "Cliente eliminado exitosamente"
}
```

### 6. Estadísticas de Clientes

**GET** `/api/admin/clientes/statistics`

#### Respuesta

```json
{
    "data": {
        "total": 150,
        "activos": 142,
        "verificados": 128,
        "con_credito": 95,
        "por_estado": {
            "activo": 142,
            "inactivo": 6,
            "bloqueado": 2
        },
        "por_genero": {
            "M": 78,
            "F": 65,
            "O": 2
        },
        "limite_credito_total": 875000.0,
        "promedio_edad": 34.5,
        "nuevos_ultimo_mes": 12
    }
}
```

### 7. Cambiar Estado del Cliente

**POST** `/api/admin/clientes/{id}/cambiar-estado`

#### Cuerpo de la Petición

```json
{
    "estado": "bloqueado"
}
```

#### Respuesta

```json
{
    "message": "Estado del cliente actualizado exitosamente",
    "data": {
        // ... datos del cliente actualizado
    }
}
```

### 8. Verificar Cliente

**POST** `/api/admin/clientes/{id}/verificar`

#### Respuesta

```json
{
    "message": "Cliente verificado exitosamente",
    "data": {
        // ... datos del cliente verificado
    }
}
```

## Códigos de Error

| Código | Descripción                     |
| ------ | ------------------------------- |
| 200    | Operación exitosa               |
| 201    | Cliente creado exitosamente     |
| 404    | Cliente no encontrado           |
| 422    | Datos de validación incorrectos |
| 500    | Error interno del servidor      |

## Ejemplos de Uso con cURL

### Listar clientes activos verificados

```bash
curl -X GET "http://localhost:8000/api/admin/clientes?estado=activo&verificado=1&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Buscar clientes por nombre

```bash
curl -X GET "http://localhost:8000/api/admin/clientes?buscar=Juan&simple=1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Crear un nuevo cliente

```bash
curl -X POST "http://localhost:8000/api/admin/clientes" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "user_id": 15,
    "dni": "87654321",
    "telefono": "999888777",
    "nombre_completo": "María García",
    "apellidos": "López Martínez",
    "fecha_nacimiento": "1990-03-20",
    "genero": "F",
    "limite_credito": 3000.00
  }'
```

### Obtener estadísticas

```bash
curl -X GET "http://localhost:8000/api/admin/clientes/statistics" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Verificar un cliente

```bash
curl -X POST "http://localhost:8000/api/admin/clientes/1/verificar" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## Validaciones

### Campos requeridos para creación

-   `user_id`: Debe existir en la tabla users y ser único

### Validaciones de formato

-   `dni`: Solo números, máximo 12 caracteres, único
-   `telefono`: Formato de teléfono válido con +, números, espacios, guiones y paréntesis
-   `fecha_nacimiento`: Fecha válida, anterior a hoy, posterior a 1900
-   `genero`: Solo valores: M, F, O
-   `limite_credito`: Número positivo, máximo 999,999.99
-   `ingresos_mensuales`: Número positivo, máximo 999,999.99
-   `estado`: Solo valores: activo, inactivo, bloqueado

### Campos opcionales con validaciones

-   `preferencias.categorias_favoritas.*`: Deben existir en la tabla categorias
-   `email` en metadata: Formato de email válido

## Notas Importantes

1. **Autorización**: Todas las rutas requieren autenticación JWT
2. **Paginación**: Por defecto 15 elementos por página, máximo 100
3. **Filtros**: Se pueden combinar múltiples filtros
4. **Búsqueda**: Busca en nombre, DNI, teléfono y email del usuario
5. **Relaciones**: Se cargan selectivamente para optimizar rendimiento
6. **Logs**: Todas las operaciones importantes se registran en logs
7. **Transacciones**: Las operaciones de escritura usan transacciones de base de datos
8. **Formateo automático**: DNI y teléfono se formatean automáticamente al guardar
