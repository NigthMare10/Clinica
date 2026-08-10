# Canal de procesamiento de PDF

## Estado del análisis PDF

No se encontró ningún archivo PDF de muestra en el workspace al realizar el inventario. Por tanto, no se analizaron estructura, tipografías, formularios, firmas, resolución, orientación, idioma, calidad de escaneo ni campos. La calibración de extracción, rasterización, OCR, reglas, límites y umbrales está bloqueada hasta recibir una muestra autorizada y representativa. No se infiere ningún formato propio de la clínica.

Las muestras futuras deben estar desidentificadas cuando sea posible, transferirse por un canal aprobado y contar con autorización de uso. Una sola muestra no basta para afirmar cobertura.

## Objetivos

- Preservar exactamente el archivo recibido.
- Rechazar o aislar entradas peligrosas sin bloquear el proceso web.
- Extraer texto y propiedades por página de forma reproducible.
- Aplicar OCR solo cuando el texto nativo sea insuficiente.
- Conservar procedencia, versión de herramienta y métricas de calidad.
- Producir derivados privados y eliminables según retención.

## Etapas propuestas

### 1. Recepción

La aplicación aplica límites preliminares de tamaño, crea un identificador opaco y transmite al almacenamiento privado. Debe evitar cargar el archivo completo en memoria. El nombre original se guarda solo si es necesario y se escapa siempre al presentarlo.

### 2. Integridad

Calcular SHA-256 sobre bytes del original, registrar tamaño y confirmar una lectura posterior. El objeto se trata como inmutable. Una nueva carga, incluso con el mismo nombre, crea una nueva versión; la deduplicación física, si se adopta, no debe filtrar existencia entre usuarios.

### 3. Cuarentena y análisis de seguridad

Detectar tipo real y pasar antimalware. Inspeccionar cifrado, contraseña, JavaScript, acciones automáticas, adjuntos y estructuras anómalas. El archivo permanece inaccesible para visualización general hasta concluir. El saneamiento no reemplaza el original y requiere criterios de aceptación definidos.

### 4. Inspección estructural

Con `qpdf --check` y `pdfinfo`, cuando estén instalados, obtener validez básica, versión PDF, páginas, dimensiones y cifrado. Parsear salidas mediante adaptadores versionados, no mediante texto improvisado en controladores. Aplicar límites de páginas y geometría pendientes de calibración.

### 5. Extracción de texto nativo

Usar `pdftotext` preservando separación por página. Registrar método y conteos técnicos, no el contenido en logs. Detectar páginas sin texto o con texto aparentemente inutilizable mediante métricas calibradas; no asumir que un conteo bajo siempre implica escaneo.

### 6. Rasterización selectiva

Usar `pdftoppm` para páginas que requieren OCR o vista controlada. Ejecutar en un directorio temporal exclusivo, con DPI, dimensiones, memoria y timeout acotados. El DPI definitivo queda pendiente de muestra; debe equilibrar precisión, latencia y almacenamiento.

### 7. Preprocesamiento de imagen

Rotación, escala de grises, contraste o limpieza solo si pruebas muestran una mejora. ImageMagick no está disponible actualmente. Toda transformación debe conservar la imagen previa, parámetros y relación con página/original. Evitar filtros destructivos por defecto.

### 8. OCR

Tesseract 5.4 está disponible. Antes de usarlo se deben inventariar paquetes de idioma instalados; no se presume que exista `spa`. Ejecutar por página y guardar texto, confianza cuando sea accesible y cajas de ubicación si la revisión las necesita. Una falla de una página debe quedar localizada.

### 9. Normalización

Normalizar finales de línea y representación Unicode sin modificar semántica. Mantener texto bruto separado del normalizado. No corregir nombres, números o términos médicos automáticamente. Cualquier redacción de datos crea un derivado, no altera evidencia.

### 10. Evaluación y publicación

Las reglas consumen artefactos explícitos y producen hallazgos. Solo tras controles de seguridad se permite una vista. Las descargas usan autorización en tiempo real y respuestas privadas; nunca rutas públicas permanentes.

### 11. Limpieza y retención

Eliminar temporales aun cuando falle un proceso. La retención del original, rasterizaciones, texto y evidencia debe definirse por categoría y obligación aprobada. La eliminación debe ser verificable y considerar respaldos.

## Idempotencia y concurrencia

Cada etapa usa una clave compuesta por documento, versión, etapa, versión del procesador y parámetros. Antes de escribir, el trabajo comprueba si existe un resultado completo válido. Usar bloqueo con expiración o restricción única para evitar doble ejecución. Un artefacto se publica mediante escritura temporal y cambio atómico de estado.

## Aislamiento

- Ejecutar herramientas sin privilegios y sin acceso de red salvo necesidad aprobada.
- Usar rutas generadas por la aplicación; nunca interpolar nombres recibidos en un shell.
- Preferir API de procesos con argumentos separados.
- Limitar CPU, RAM, archivos, procesos, bytes de salida y tiempo.
- Separar temporal por trabajo y limpiar de forma segura.
- Considerar contenedor o host dedicado en producción.

## Salidas y procedencia

Cada artefacto registra `document_version_id`, página opcional, etapa, herramienta, versión, parámetros canónicos, hash, tamaño, MIME detectado, ubicación privada y marcas de tiempo. Los errores registran código estable y detalle técnico sanitizado.

## Métricas operativas

Medir latencia y fallos por etapa, páginas por minuto, activación de OCR, reintentos, timeouts y almacenamiento derivado. Alertar sobre cola estancada y cambios abruptos en tasas. No usar etiquetas con nombres, texto extraído ni identificadores externos.

## Calibración pendiente

Cuando exista un conjunto autorizado, registrar por categoría: proporción de PDFs nativos/escaneados/mixtos, idiomas, rango de páginas, DPI estimado, rotaciones, tablas, manuscritos, sellos y calidad. Comparar extracción con verdad de referencia y medir precisión de caracteres/campos, tiempo y memoria. Los valores de DPI, confianza mínima, máximo de páginas y timeout solo se fijarán después de esta prueba.
