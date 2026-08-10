# Diseño de base de datos propuesto

## Estado actual

El scaffold contiene únicamente las migraciones estándar para `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` y `failed_jobs`. No existen tablas clínicas, de documentos, verificación ni auditoría. Lo siguiente es un diseño lógico pendiente de validación e implementación.

El motor de producción no está decidido. Los tipos, índices y estrategias de partición deben confirmarse contra el motor elegido, carga esperada y requisitos de recuperación.

## Convenciones

- Claves internas no semánticas; considerar UUID/ULID para recursos expuestos.
- Fechas en UTC y conversión solo en presentación.
- Restricciones `NOT NULL`, únicas y foráneas donde expresen invariantes reales.
- Valores clínicos categóricos mediante catálogos/versiones, no `enum` rígido sin evaluar evolución.
- JSON solo para datos variables o procedencia; no ocultar relaciones consultables.
- Borrado lógico no es una política de retención. Definir archivo, anonimización y eliminación por separado.
- Datos sensibles fuera de nombres de tabla/columna, logs de consultas y payloads de cola.

## Entidades de identidad

### `users`

La tabla actual tiene nombre, correo, contraseña y verificación de correo. Debe revisarse si representa personal, pacientes o ambos; no se debe asumir. Campos futuros posibles: estado, último acceso y requisitos de MFA, sujetos a diseño de identidad.

### `roles`, `permissions`, `role_user`, `permission_role`

Modelo RBAC propuesto para capacidades generales. El acceso a un expediente también requiere autorización contextual; un rol global por sí solo no debe conceder acceso indiscriminado.

### `access_assignments`

Asocia usuario, alcance autorizado, vigencia, motivo y otorgante. El modelo exacto depende de sedes/equipos/episodios reales, todavía desconocidos.

## Entidades clínicas mínimas

### `patients`

Identidad interna del sujeto. No se especifican campos demográficos hasta recibir requisitos y base legal. Los identificadores externos se separan para limitar exposición y admitir múltiples emisores.

### `patient_identifiers`

`patient_id`, sistema/emisor, valor protegido, estado y vigencia. Aplicar unicidad por emisor cuando proceda. Evaluar cifrado o búsqueda mediante índice ciego según caso de uso y modelo de amenazas.

### `records`

Contenedor lógico de documentos para un contexto aprobado. No se presupone equivalencia con consulta, episodio o historia clínica; su semántica debe definirse.

## Entidades documentales

### `documents`

Identidad lógica, asociación autorizada, categoría, estado actual, creador y marcas de tiempo. No almacena el binario.

### `document_versions`

Una fila por carga: `document_id`, número de versión, hash SHA-256, bytes, MIME declarado/detectado, nombre original protegido, clave de objeto, estado de cuarentena y autor. Restricción única `(document_id, version_number)` y, si es apropiado, sobre la clave de objeto. El hash no debe usarse como autorización.

### `document_pages`

Página, dimensiones, rotación detectada, método de extracción y estado. Restricción única `(document_version_id, page_number)` y validación de página positiva.

### `processing_runs`

Ejecución de una etapa: procesador, versión, parámetros, estado, intentos, inicio/fin, código de error y correlación. Restricción de idempotencia por documento/etapa/versión/parámetros.

### `artifacts`

Derivados como texto bruto, texto normalizado, imagen de página o vista saneada. Incluye hash, tamaño, MIME, clave privada, procedencia y retención. El contenido grande se mantiene en almacenamiento de objetos; la base conserva metadatos.

## Verificación

### `rule_sets` y `rules`

Conjuntos versionados e inmutables después de activación. Cada regla tiene código estable, versión, aplicabilidad, severidad y configuración validada. La lógica ejecutable permanece en código revisado; no almacenar código arbitrario en la base.

### `verification_runs`

Documento/versión, conjunto de reglas, estado, resultado, inicio/fin y ejecución previa opcional. Una nueva evaluación crea una fila, nunca reescribe historia.

### `verification_findings`

Regla, resultado, severidad, explicación segura, página/región opcional y referencia a evidencia. Índices por ejecución y código de regla.

### `reviews`

Ejecución revisada, revisor, decisión, motivo, inicio/fin y versión concurrente. Impedir que una actualización atrasada sobrescriba otra decisión. Según riesgo, modelar asignación y segunda revisión.

## Auditoría

### `audit_events`

Evento append-only con actor, acción, tipo/id de recurso, resultado, instante, correlación, origen técnico reducido y metadatos permitidos. No guardar texto clínico ni secretos. Proteger integridad con permisos de base separados y, si el riesgo lo exige, encadenamiento hash o exportación a almacenamiento inmutable.

### `access_events`

Puede ser una categoría dentro de `audit_events` o tabla optimizada para lecturas/descargas. Debe registrar propósito cuando la política lo exija. La decisión depende del volumen y consulta esperados.

## Relaciones resumidas

```text
patients 1---N patient_identifiers
patients 1---N records
records  1---N documents
documents 1---N document_versions
document_versions 1---N document_pages
document_versions 1---N processing_runs
document_versions 1---N artifacts
document_versions 1---N verification_runs
verification_runs 1---N verification_findings
verification_runs 1---N reviews
users 1---N documents/reviews/audit_events (según acción)
```

## Integridad y transacciones

- Crear registro de versión y referencia de carga antes del procesamiento, con compensación si falla el objeto.
- Publicar estado final y eventos de salida en la misma transacción.
- Usar comparación de versión o bloqueo al resolver revisiones.
- No hacer llamadas prolongadas a OCR dentro de transacciones.
- Verificar periódicamente que hash y ubicación de objetos correspondan a metadatos.

## Índices y minimización

Indexar foráneas, estados usados por trabajadores y marcas de tiempo de colas operativas. Evitar índices innecesarios sobre datos sensibles: aumentan copias y costo de eliminación. Revisar planes con datos representativos antes de añadir índices compuestos.

## Retención y borrado

Definir por entidad base legal, plazo, evento inicial, suspensión de borrado y responsable. Eliminar derivados temporales antes que evidencia requerida. Una eliminación debe cubrir objetos, réplicas, índices de búsqueda y ciclo de respaldos. No hay plazos aprobados actualmente.

## Migración por fases

1. Identidad/autorización y auditoría mínima.
2. Documentos/versiones con almacenamiento privado y cuarentena.
3. Procesamiento, páginas y artefactos.
4. Reglas, ejecuciones, hallazgos y revisión.
5. Retención, reportes y optimización basada en medición.

Cada fase requiere migraciones reversibles cuando sea seguro, pruebas de restricciones y datos sintéticos; nunca usar información real en seeds de desarrollo.
