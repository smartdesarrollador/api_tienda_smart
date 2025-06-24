# ValorAtributoController - Documentación

## Descripción General

El `ValorAtributoController` gestiona los valores específicos de los atributos de productos en el sistema. Permite crear, leer, actualizar y eliminar valores de atributos como colores específicos, tallas, materiales, etc.

## Características Principales

### ✅ **Funcionalidades Implementadas**

-   ✅ **CRUD Completo**: Crear, leer, actualizar y eliminar valores de atributos
-   ✅ **Validaciones Avanzadas**: Validaciones específicas por tipo de atributo
-   ✅ **Manejo de Imágenes**: Subida, actualización y eliminación de imágenes
-   ✅ **Filtros Múltiples**: Por atributo, valor, código, tipo, imagen
-   ✅ **Búsqueda**: Búsqueda parcial en valores y códigos
-   ✅ **Paginación**: Control de cantidad de resultados por página
-   ✅ **Ordenamiento**: Por valor, código, fecha de creación, atributo
-   ✅ **Operaciones en Lote**: Creación masiva de valores
-   ✅ **Estadísticas**: Métricas y resúmenes de valores
-   ✅ **Manejo de Errores**: Respuestas de error consistentes y logging
-   ✅ **Transacciones**: Operaciones atómicas con rollback automático
-   ✅ **Autorización**: Control de acceso basado en roles

## Endpoints Disponibles

### 📋 **Operaciones CRUD Básicas**

#### 1. **Listar Valores de Atributo**

```http
GET /api/admin/valores-atributo
```

**Parámetros de Query:**

-   `per_page` (int): Cantidad por página (máximo 100, default 15)
-   `atributo_id` (int): Filtrar por atributo específico
-   `valor` (string): Búsqueda parcial en el valor
-   `codigo` (string): Búsqueda parcial en el código
-   `tipo_atributo` (string): Filtrar por tipo de atributo (color, tamaño, etc.)
-   `con_imagen` (boolean): Filtrar valores con/sin imagen
-   `include_usage` (boolean): Incluir conteo de variaciones que usan el valor
-   `order_by` (string): Campo de ordenamiento (valor, codigo, created_at, atributo_id)
-   `order_direction` (string): Dirección (asc, desc)

**Ejemplo de respuesta:**

```json
{
  "data": [
    {
      "id": 1,
      "atributo_id": 1,
      "valor": "Rojo",
      "codigo": "#FF0000",
      "imagen": "valores-atributo/valor_attr_123.jpg",
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 10:30:00",
      "valor_formateado": "Rojo (#FF0000)",
      "es_color": true,
      "tiene_imagen": true,
      "atributo": {
        "id": 1,
        "nombre": "Color",
        "slug": "color",
        "tipo": "color"
      }
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### 2. **Crear Valor de Atributo**

```http
POST /api/admin/valores-atributo
```

**Body (form-data o JSON):**

```json
{
    "atributo_id": 1,
    "valor": "Azul Marino",
    "codigo": "#000080",
    "imagen": "archivo_imagen.jpg" // opcional, multipart/form-data
}
```

**Validaciones:**

-   `atributo_id`: Requerido, debe existir en la tabla atributos
-   `valor`: Requerido, máximo 255 caracteres, único por atributo
-   `codigo`: Opcional, máximo 50 caracteres, patrón específico según tipo
-   `imagen`: Opcional, archivo de imagen (jpeg,jpg,png,gif,webp), máximo 2MB

#### 3. **Mostrar Valor Específico**

```http
GET /api/admin/valores-atributo/{id}
```

#### 4. **Actualizar Valor de Atributo**

```http
PUT/PATCH /api/admin/valores-atributo/{id}
```

**Body:**

```json
{
    "valor": "Azul Oceáno",
    "codigo": "#006994"
}
```

#### 5. **Eliminar Valor de Atributo**

```http
DELETE /api/admin/valores-atributo/{id}
```

**Restricciones:**

-   No se puede eliminar si está siendo usado por variaciones de productos
-   Se eliminará automáticamente la imagen asociada si existe

### 🎯 **Endpoints Especializados**

#### 6. **Obtener Valores por Atributo**

```http
GET /api/admin/valores-atributo/atributo/{atributo_id}
```

Obtiene todos los valores de un atributo específico, útil para formularios dinámicos.

#### 7. **Creación Masiva de Valores**

```http
POST /api/admin/valores-atributo/atributo/{atributo_id}/bulk
```

**Body:**

```json
{
    "valores": [
        { "valor": "Rojo", "codigo": "#FF0000" },
        { "valor": "Verde", "codigo": "#00FF00" },
        { "valor": "Azul", "codigo": "#0000FF" }
    ]
}
```

**Respuesta:**

```json
{
  "message": "Proceso de creación masiva completado",
  "creados": [...],
  "errores": [],
  "total_creados": 3,
  "total_errores": 0
}
```

#### 8. **Eliminar Imagen de Valor**

```http
DELETE /api/admin/valores-atributo/{id}/imagen
```

Elimina solo la imagen del valor, manteniendo el registro.

#### 9. **Estadísticas de Valores**

```http
GET /api/admin/valores-atributo/statistics
```

**Respuesta:**

```json
{
    "data": {
        "total_valores": 45,
        "valores_con_imagen": 23,
        "valores_con_codigo": 38,
        "valores_en_uso": 31,
        "por_tipo_atributo": {
            "color": 15,
            "tamaño": 12,
            "material": 8
        },
        "top_atributos": [
            { "nombre": "Color", "total_valores": 15 },
            { "nombre": "Talla", "total_valores": 12 }
        ]
    }
}
```

## Validaciones Específicas por Tipo

### 🎨 **Atributos de Color**

-   **Código**: Debe ser hexadecimal válido (`#RRGGBB`)
-   **Valor**: Se normaliza con primera letra mayúscula
-   **Auto-formato**: Si no se incluye `#` al inicio del código, se agrega automáticamente

### 📏 **Atributos de Tamaño**

-   **Valor**: Se acepta XS, S, M, L, XL, XXL, XXXL, números, fracciones
-   **Normalización**: Se convierte automáticamente a mayúsculas
-   **Patrón**: `/^(XXS|XS|S|M|L|XL|XXL|XXXL|\d+(\.\d+)?|\d+\/\d+)$/i`

### 🔢 **Atributos Numéricos**

-   **Valor**: Debe ser un número válido
-   **Validación**: `is_numeric()` aplicada

### ✅ **Atributos Booleanos**

-   **Valores permitidos**: Sí, No, Verdadero, Falso, Activado, Desactivado, 1, 0

## Manejo de Imágenes

### 📸 **Subida de Imágenes**

-   **Formatos soportados**: JPEG, JPG, PNG, GIF, WebP
-   **Tamaño máximo**: 2MB
-   **Almacenamiento**: `storage/app/public/valores-atributo/`
-   **Nomenclatura**: `valor_attr_{uniqid}.{extension}`

### 🗑️ **Eliminación de Imágenes**

-   **Automática**: Al eliminar valor o actualizar imagen
-   **Manual**: Endpoint específico para eliminar solo imagen
-   **Segura**: Verificación de existencia antes de eliminar

## Ejemplos de Uso

### 💡 **Escenario 1: Gestión de Colores**

```bash
# 1. Crear atributo Color
POST /api/admin/atributos
{
  "nombre": "Color",
  "tipo": "color",
  "filtrable": true,
  "visible": true
}

# 2. Agregar valores de color
POST /api/admin/valores-atributo
{
  "atributo_id": 1,
  "valor": "Rojo Ferrari",
  "codigo": "FF0000"  // Se convertirá a #FF0000
}

# 3. Creación masiva de colores básicos
POST /api/admin/valores-atributo/atributo/1/bulk
{
  "valores": [
    {"valor": "Rojo", "codigo": "#FF0000"},
    {"valor": "Verde", "codigo": "#00FF00"},
    {"valor": "Azul", "codigo": "#0000FF"},
    {"valor": "Negro", "codigo": "#000000"},
    {"valor": "Blanco", "codigo": "#FFFFFF"}
  ]
}
```

### 👕 **Escenario 2: Gestión de Tallas**

```bash
# 1. Crear atributo Talla
POST /api/admin/atributos
{
  "nombre": "Talla",
  "tipo": "tamaño",
  "filtrable": true,
  "visible": true
}

# 2. Agregar tallas estándar
POST /api/admin/valores-atributo/atributo/2/bulk
{
  "valores": [
    {"valor": "xs"},     // Se normalizará a "XS"
    {"valor": "s"},      // Se normalizará a "S"
    {"valor": "m"},      // Se normalizará a "M"
    {"valor": "l"},      // Se normalizará a "L"
    {"valor": "xl"},     // Se normalizará a "XL"
    {"valor": "xxl"}     // Se normalizará a "XXL"
  ]
}
```

### 🧶 **Escenario 3: Material con Imágenes**

```bash
# 1. Crear valor con imagen (multipart/form-data)
POST /api/admin/valores-atributo
Content-Type: multipart/form-data

atributo_id: 3
valor: Algodón Orgánico
imagen: [archivo_textura_algodon.jpg]

# 2. Actualizar imagen posteriormente
PUT /api/admin/valores-atributo/15
Content-Type: multipart/form-data

imagen: [nueva_textura.jpg]
```

## Códigos de Respuesta HTTP

| Código | Significado           | Cuándo se usa                        |
| ------ | --------------------- | ------------------------------------ |
| 200    | OK                    | Listado, mostrar, actualizar exitoso |
| 201    | Created               | Creación exitosa                     |
| 204    | No Content            | Eliminación exitosa                  |
| 400    | Bad Request           | Solicitud malformada                 |
| 401    | Unauthorized          | No autenticado                       |
| 403    | Forbidden             | Sin permisos                         |
| 404    | Not Found             | Recurso no encontrado                |
| 409    | Conflict              | No se puede eliminar (en uso)        |
| 422    | Unprocessable Entity  | Errores de validación                |
| 500    | Internal Server Error | Error del servidor                   |

## Logging y Monitoreo

### 📊 **Eventos Registrados**

-   Creación de valores de atributo
-   Actualización con lista de campos modificados
-   Eliminación con información del valor
-   Creación masiva con estadísticas
-   Errores de operación con contexto

### 🚨 **Logs de Error**

-   Errores de validación
-   Problemas de subida de archivos
-   Errores de base de datos
-   Problemas de eliminación de imágenes

## Consideraciones de Rendimiento

### ⚡ **Optimizaciones Implementadas**

-   **Eager Loading**: Relaciones cargadas eficientemente
-   **Joins Optimizados**: Para ordenamiento por atributo
-   **Límites de Paginación**: Máximo 100 elementos por página
-   **Índices de Base de Datos**: En campos de búsqueda frecuente
-   **Transacciones**: Para operaciones múltiples

### 📈 **Métricas Recomendadas**

-   Tiempo de respuesta por endpoint
-   Uso de memoria en operaciones masivas
-   Frecuencia de uso de filtros
-   Tamaño promedio de imágenes subidas

## Seguridad

### 🔒 **Medidas Implementadas**

-   **Autenticación JWT**: Requerida para todos los endpoints
-   **Validación de Archivos**: Tipo y tamaño de imágenes
-   **Sanitización**: Datos normalizados antes de almacenar
-   **Control de Acceso**: Solo administradores
-   **Validación de Entrada**: Form Requests robustas
-   **Rate Limiting**: A través de middleware Laravel

### 🛡️ **Protecciones Adicionales**

-   Nombres de archivo únicos para prevenir colisiones
-   Validación de existencia de atributos padre
-   Prevención de eliminación de valores en uso
-   Rollback automático en caso de errores

## Dependencias

### 🔗 **Modelos Relacionados**

-   `Atributo`: Relación padre obligatoria
-   `VariacionProducto`: Relación many-to-many (previene eliminación)

### 📦 **Servicios Utilizados**

-   `Storage` (Laravel): Manejo de archivos
-   `DB` (Laravel): Transacciones
-   `Log` (Laravel): Registro de eventos
-   `Validator` (Laravel): Validaciones custom

## Próximas Mejoras

### 🚀 **Funcionalidades Planificadas**

-   [ ] Importación desde CSV/Excel
-   [ ] Optimización de imágenes automática (resize, compresión)
-   [ ] Versionado de valores de atributo
-   [ ] Cache de valores frecuentemente consultados
-   [ ] API endpoints públicos para frontend
-   [ ] Búsqueda con Elasticsearch
-   [ ] Reportes de uso detallados

### 🔧 **Mejoras Técnicas**

-   [ ] Queue jobs para procesamiento de imágenes
-   [ ] CDN para distribución de imágenes
-   [ ] Pruebas de carga automatizadas
-   [ ] Métricas de performance integradas
-   [ ] Documentación OpenAPI/Swagger

---

**Creado por:** Sistema de Tienda Virtual
**Versión:** 1.0
**Última actualización:** 2024-01-15
