# Guía de Pruebas Postman - ValorAtributoController

## 🚀 **Configuración Inicial**

### **1. Variables de Entorno**

Crear una **Environment** en Postman llamada "Tienda Virtual - Local":

```json
{
    "base_url": "http://localhost:8000/api",
    "token": "",
    "atributo_id": "",
    "valor_atributo_id": ""
}
```

### **2. Configuración de Autenticación**

#### **Pre-request Script Global** (En la Collection):

```javascript
// Verificar si tenemos token válido
if (!pm.environment.get("token")) {
    console.log("⚠️ No hay token. Debes autenticarte primero.");
}
```

#### **Headers Globales**:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{token}}
```

---

## 🔐 **Paso 1: Autenticación**

### **Login para obtener Token**

```http
POST {{base_url}}/auth/login
```

**Body (JSON):**

```json
{
    "email": "admin@test.com",
    "password": "password123"
}
```

**Test Script:**

```javascript
if (pm.response.code === 200) {
    const response = pm.response.json();
    pm.environment.set("token", response.access_token);
    console.log("✅ Token guardado exitosamente");
} else {
    console.log("❌ Error en login:", pm.response.text());
}
```

---

## 📋 **Paso 2: Crear Atributo de Prueba**

### **Crear Atributo Color**

```http
POST {{base_url}}/admin/atributos
```

**Body (JSON):**

```json
{
    "nombre": "Color Test",
    "slug": "color-test",
    "tipo": "color",
    "descripcion": "Atributo de color para pruebas",
    "filtrable": true,
    "visible": true,
    "requerido": false
}
```

**Test Script:**

```javascript
if (pm.response.code === 201) {
    const response = pm.response.json();
    pm.environment.set("atributo_id", response.data.id);
    console.log("✅ Atributo creado con ID:", response.data.id);
}
```

### **Crear Atributo Tamaño**

```http
POST {{base_url}}/admin/atributos
```

**Body (JSON):**

```json
{
    "nombre": "Talla Test",
    "slug": "talla-test",
    "tipo": "tamaño",
    "descripcion": "Atributo de talla para pruebas",
    "filtrable": true,
    "visible": true,
    "requerido": true
}
```

---

## 🎯 **Pruebas por Endpoint**

## **1. 📝 Listar Valores de Atributo**

### **Caso 1.1: Listado Básico**

```http
GET {{base_url}}/admin/valores-atributo
```

**Test Script:**

```javascript
pm.test("Status es 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Respuesta tiene estructura correcta", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property("data");
    pm.expect(jsonData).to.have.property("links");
    pm.expect(jsonData).to.have.property("meta");
});
```

### **Caso 1.2: Listado con Paginación**

```http
GET {{base_url}}/admin/valores-atributo?per_page=5&page=1
```

### **Caso 1.3: Filtro por Atributo**

```http
GET {{base_url}}/admin/valores-atributo?atributo_id={{atributo_id}}
```

### **Caso 1.4: Búsqueda por Valor**

```http
GET {{base_url}}/admin/valores-atributo?valor=Rojo
```

### **Caso 1.5: Filtro por Tipo de Atributo**

```http
GET {{base_url}}/admin/valores-atributo?tipo_atributo=color
```

### **Caso 1.6: Filtro con Imagen**

```http
GET {{base_url}}/admin/valores-atributo?con_imagen=true
```

### **Caso 1.7: Incluir Uso en Variaciones**

```http
GET {{base_url}}/admin/valores-atributo?include_usage=true
```

### **Caso 1.8: Ordenamiento**

```http
GET {{base_url}}/admin/valores-atributo?order_by=valor&order_direction=desc
```

---

## **2. ➕ Crear Valor de Atributo**

### **Caso 2.1: Crear Color Básico**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (JSON):**

```json
{
    "atributo_id": {{atributo_id}},
    "valor": "Rojo Ferrari",
    "codigo": "FF0000"
}
```

**Test Script:**

```javascript
if (pm.response.code === 201) {
    const response = pm.response.json();
    pm.environment.set("valor_atributo_id", response.data.id);
    console.log("✅ Valor de atributo creado con ID:", response.data.id);
}

pm.test("Código se formateó correctamente", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.data.codigo).to.eql("#FF0000");
});
```

### **Caso 2.2: Crear Color con Código Hexadecimal**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (JSON):**

```json
{
    "atributo_id": {{atributo_id}},
    "valor": "Azul Océano",
    "codigo": "#0066CC"
}
```

### **Caso 2.3: Crear Valor con Imagen (Form-Data)**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (form-data):**

```
atributo_id: {{atributo_id}}
valor: Verde Esmeralda
codigo: #00FF66
imagen: [seleccionar archivo de imagen]
```

**Headers:**

```
Content-Type: multipart/form-data
Authorization: Bearer {{token}}
```

### **Caso 2.4: Validación - Atributo Requerido**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (JSON):**

```json
{
    "valor": "Color sin atributo"
}
```

**Test Script:**

```javascript
pm.test("Error de validación por atributo faltante", function () {
    pm.response.to.have.status(422);
    const jsonData = pm.response.json();
    pm.expect(jsonData.errors).to.have.property("atributo_id");
});
```

### **Caso 2.5: Validación - Valor Duplicado**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (JSON):**

```json
{
    "atributo_id": {{atributo_id}},
    "valor": "Rojo Ferrari"
}
```

**Test Script:**

```javascript
pm.test("Error por valor duplicado", function () {
    pm.response.to.have.status(422);
    const jsonData = pm.response.json();
    pm.expect(jsonData.errors).to.have.property("valor");
});
```

---

## **3. 👁️ Mostrar Valor Específico**

### **Caso 3.1: Mostrar Valor Existente**

```http
GET {{base_url}}/admin/valores-atributo/{{valor_atributo_id}}
```

**Test Script:**

```javascript
pm.test("Valor se muestra correctamente", function () {
    pm.response.to.have.status(200);
    const jsonData = pm.response.json();
    pm.expect(jsonData.data).to.have.property("id");
    pm.expect(jsonData.data).to.have.property("atributo");
});
```

### **Caso 3.2: Mostrar Valor Inexistente**

```http
GET {{base_url}}/admin/valores-atributo/99999
```

**Test Script:**

```javascript
pm.test("Error 404 para valor inexistente", function () {
    pm.response.to.have.status(404);
});
```

---

## **4. ✏️ Actualizar Valor de Atributo**

### **Caso 4.1: Actualizar Valor**

```http
PUT {{base_url}}/admin/valores-atributo/{{valor_atributo_id}}
```

**Body (JSON):**

```json
{
    "valor": "Rojo Ferrari Modificado",
    "codigo": "#CC0000"
}
```

### **Caso 4.2: Actualizar Solo Código**

```http
PATCH {{base_url}}/admin/valores-atributo/{{valor_atributo_id}}
```

**Body (JSON):**

```json
{
    "codigo": "#FF6666"
}
```

### **Caso 4.3: Actualizar con Nueva Imagen**

```http
PUT {{base_url}}/admin/valores-atributo/{{valor_atributo_id}}
```

**Body (form-data):**

```
valor: Rojo Ferrari Premium
imagen: [nueva imagen]
```

---

## **5. 🗑️ Eliminar Valor de Atributo**

### **Caso 5.1: Eliminar Valor No Usado**

```http
DELETE {{base_url}}/admin/valores-atributo/{{valor_atributo_id}}
```

**Test Script:**

```javascript
pm.test("Eliminación exitosa", function () {
    pm.response.to.have.status(204);
});
```

### **Caso 5.2: Intentar Eliminar Valor en Uso**

```http
DELETE {{base_url}}/admin/valores-atributo/1
```

**Test Script:**

```javascript
pm.test("Error 409 para valor en uso", function () {
    pm.response.to.have.status(409);
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property("variaciones_count");
});
```

---

## **6. 🎯 Valores por Atributo**

### **Caso 6.1: Obtener Valores de Atributo Color**

```http
GET {{base_url}}/admin/valores-atributo/atributo/{{atributo_id}}
```

**Test Script:**

```javascript
pm.test("Valores filtrados por atributo", function () {
    pm.response.to.have.status(200);
    const jsonData = pm.response.json();
    jsonData.data.forEach(function (item) {
        pm.expect(item.atributo_id).to.eql(
            parseInt(pm.environment.get("atributo_id"))
        );
    });
});
```

---

## **7. 🚀 Creación Masiva**

### **Caso 7.1: Crear Colores Básicos en Lote**

```http
POST {{base_url}}/admin/valores-atributo/atributo/{{atributo_id}}/bulk
```

**Body (JSON):**

```json
{
    "valores": [
        { "valor": "Rojo", "codigo": "#FF0000" },
        { "valor": "Verde", "codigo": "#00FF00" },
        { "valor": "Azul", "codigo": "#0000FF" },
        { "valor": "Negro", "codigo": "#000000" },
        { "valor": "Blanco", "codigo": "#FFFFFF" }
    ]
}
```

**Test Script:**

```javascript
pm.test("Creación masiva exitosa", function () {
    pm.response.to.have.status(201);
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property("total_creados");
    pm.expect(jsonData).to.have.property("total_errores");
    pm.expect(jsonData.total_creados).to.be.above(0);
});
```

### **Caso 7.2: Creación Masiva para Tallas**

Primero cambiar el atributo_id por uno de tipo "tamaño":

```http
POST {{base_url}}/admin/valores-atributo/atributo/2/bulk
```

**Body (JSON):**

```json
{
    "valores": [
        { "valor": "xs" },
        { "valor": "s" },
        { "valor": "m" },
        { "valor": "l" },
        { "valor": "xl" },
        { "valor": "xxl" }
    ]
}
```

### **Caso 7.3: Creación Masiva con Errores**

```http
POST {{base_url}}/admin/valores-atributo/atributo/{{atributo_id}}/bulk
```

**Body (JSON):**

```json
{
    "valores": [
        { "valor": "Rojo" },
        { "valor": "Rojo" },
        { "valor": "Amarillo", "codigo": "#FFFF00" },
        { "valor": "" }
    ]
}
```

---

## **8. 🖼️ Gestión de Imágenes**

### **Caso 8.1: Eliminar Solo Imagen**

```http
DELETE {{base_url}}/admin/valores-atributo/{{valor_atributo_id}}/imagen
```

**Test Script:**

```javascript
pm.test("Imagen eliminada correctamente", function () {
    pm.response.to.have.status(200);
    const jsonData = pm.response.json();
    pm.expect(jsonData.data.imagen).to.be.null;
});
```

### **Caso 8.2: Eliminar Imagen Inexistente**

```http
DELETE {{base_url}}/admin/valores-atributo/1/imagen
```

**Test Script:**

```javascript
pm.test("Error al eliminar imagen inexistente", function () {
    pm.response.to.have.status(400);
});
```

---

## **9. 📊 Estadísticas**

### **Caso 9.1: Obtener Estadísticas Completas**

```http
GET {{base_url}}/admin/valores-atributo/statistics
```

**Test Script:**

```javascript
pm.test("Estadísticas completas", function () {
    pm.response.to.have.status(200);
    const jsonData = pm.response.json();
    const stats = jsonData.data;

    pm.expect(stats).to.have.property("total_valores");
    pm.expect(stats).to.have.property("valores_con_imagen");
    pm.expect(stats).to.have.property("valores_con_codigo");
    pm.expect(stats).to.have.property("valores_en_uso");
    pm.expect(stats).to.have.property("por_tipo_atributo");
    pm.expect(stats).to.have.property("top_atributos");

    pm.expect(stats.total_valores).to.be.a("number");
});
```

---

## **🔧 Validaciones Específicas por Tipo**

### **Validación Color - Código Hexadecimal**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (JSON):**

```json
{
    "atributo_id": {{atributo_id}},
    "valor": "Color Inválido",
    "codigo": "GGGGGG"
}
```

**Expected:** Error 422 - código hexadecimal inválido

### **Validación Tamaño - Patrón**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (JSON):**

```json
{
    "atributo_id": 2,
    "valor": "Talla Inválida XXXXXXXXL"
}
```

**Expected:** Error 422 - patrón de talla inválido

---

## **🚫 Casos de Error**

### **Sin Autenticación**

```http
GET {{base_url}}/admin/valores-atributo
```

**Headers:** (Sin Authorization)

**Expected:** Error 401

### **Token Inválido**

```http
GET {{base_url}}/admin/valores-atributo
```

**Headers:**

```
Authorization: Bearer token_invalido
```

**Expected:** Error 401

### **Archivo de Imagen Muy Grande**

```http
POST {{base_url}}/admin/valores-atributo
```

**Body (form-data):**

```
atributo_id: {{atributo_id}}
valor: Test Imagen Grande
imagen: [archivo > 2MB]
```

**Expected:** Error 422 - imagen muy grande

---

## **📝 Collection Tests Script**

Agregar este script al nivel de **Collection** para validaciones globales:

```javascript
// Validar que todas las respuestas exitosas tengan estructura correcta
pm.test("Response time bajo 2000ms", function () {
    pm.expect(pm.response.responseTime).to.be.below(2000);
});

// Validar headers de seguridad
pm.test("Headers de seguridad presentes", function () {
    pm.expect(pm.response.headers.get("Content-Type")).to.include(
        "application/json"
    );
});

// Log de errores para debugging
if (pm.response.code >= 400) {
    console.log("❌ Error:", pm.response.code, pm.response.text());
}
```

---

## **🎯 Flujo de Prueba Completo**

### **Runner Sequence:**

1. **Autenticación** → Login
2. **Setup** → Crear atributos de prueba
3. **CRUD Básico** → Crear, listar, mostrar, actualizar, eliminar
4. **Funcionalidades Avanzadas** → Creación masiva, filtros, estadísticas
5. **Validaciones** → Casos de error y validaciones específicas
6. **Cleanup** → Limpiar datos de prueba

### **Variables Dinámicas:**

```javascript
// Pre-request script para generar datos aleatorios
pm.environment.set(
    "random_color",
    "Color_" + Math.random().toString(36).substring(7)
);
pm.environment.set(
    "random_hex",
    "#" +
        Math.floor(Math.random() * 16777215)
            .toString(16)
            .padStart(6, "0")
);
```

---

## **📋 Checklist de Pruebas**

-   [ ] ✅ Autenticación funciona
-   [ ] ✅ CRUD básico completo
-   [ ] ✅ Filtros y búsquedas
-   [ ] ✅ Paginación
-   [ ] ✅ Validaciones por tipo de atributo
-   [ ] ✅ Manejo de imágenes
-   [ ] ✅ Creación masiva
-   [ ] ✅ Estadísticas
-   [ ] ✅ Casos de error
-   [ ] ✅ Performance (< 2s por request)
-   [ ] ✅ Validaciones de seguridad

**🎉 Con estas pruebas tienes cobertura completa del ValorAtributoController en Postman!**
