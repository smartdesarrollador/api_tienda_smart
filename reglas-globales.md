// Usa transacciones de base de datos para mantener la integridad de los datos.
// Utiliza el programador de tareas de Laravel para ejecutar tareas recurrentes.

# Manejo de Archivos e Imágenes

-   Utiliza siempre el método **POST** para las solicitudes que actualicen imágenes o cualquier tipo de archivo. Evita el uso de PUT para estas operaciones para asegurar compatibilidad con cómo los formularios HTML manejan los archivos (`multipart/form-data`).
-   Almacena todas las imágenes y archivos subidos por los usuarios en el directorio `public/assets/` dentro del storage de Laravel. Asegúrate de crear los subdirectorios necesarios dentro de `assets/` para organizar los archivos de manera lógica (ej. `public/assets/productos/`, `public/assets/avatares/`).
-   Utiliza el facade `Storage` de Laravel para todas las operaciones de archivos, configurando el disco `public` adecuadamente.
