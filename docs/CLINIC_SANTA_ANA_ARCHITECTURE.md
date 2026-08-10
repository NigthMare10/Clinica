# Arquitectura propuesta del sistema

## Estado y alcance

Este documento describe una arquitectura objetivo, no una implementación existente. El repositorio contiene al 10 de agosto de 2026 un scaffold de Laravel 13.24.0; no contiene módulos clínicos. El nombre de trabajo del proyecto no permite inferir ubicación, especialidades, tamaño, procesos, propietarios ni obligaciones regulatorias de una clínica. Esos datos deben obtenerse y aprobarse antes de implementar.

El alcance propuesto es administrar documentos médicos y su verificación, con trazabilidad y controles de acceso. No se presupone que el sistema sea una historia clínica electrónica completa, un sistema de diagnóstico ni una fuente de decisiones médicas automatizadas.

## Principios

- Modularidad dentro de un monolito Laravel para reducir complejidad operativa inicial.
- Procesamiento pesado y falible mediante colas, nunca dentro de la solicitud HTTP.
- Separación entre archivo original, derivados, texto extraído y resultados de verificación.
- Inmutabilidad lógica del original y trazabilidad de cada transición.
- Menor privilegio, denegación por defecto y acceso por contexto clínico autorizado.
- Intervención humana cuando una conclusión pueda afectar un expediente o proceso clínico.
- Observabilidad sin exponer contenido médico en logs, métricas o mensajes de error.
- Portabilidad del almacenamiento mediante interfaces de Laravel y servicios sustituibles.

## Contexto de contenedores

```text
[Navegador]
     |
     | HTTPS
     v
[Aplicación Laravel]
  | autenticación/autorización
  | expedientes y metadatos
  | orquestación de verificaciones
  | auditoría
  +------> [Base de datos relacional]
  +------> [Almacenamiento privado de objetos]
  +------> [Cola de trabajos]
                 |
                 v
          [Trabajadores aislados]
          | validación PDF
          | extracción/OCR
          | reglas de verificación
          +----> [Herramientas locales de documentos]
```

En producción, base de datos, cola y almacenamiento deben seleccionarse según requisitos confirmados de disponibilidad, residencia de datos, respaldo y recuperación. SQLite sirve para desarrollo del scaffold, pero no se declara adecuado para producción sin evaluación.

## Módulos de dominio propuestos

### Identidad y acceso

Gestiona usuarios, roles, permisos, sesiones y autenticación reforzada. Debe admitir desactivación inmediata, revisión periódica de accesos y políticas para operaciones sensibles.

### Personas y expedientes

Mantiene identificadores internos y la asociación mínima necesaria entre una persona y sus episodios o expedientes. Los campos demográficos y clínicos exactos quedan pendientes del levantamiento funcional. No deben agregarse datos “por si acaso”.

### Documentos médicos

Registra carga, almacenamiento, versión, hash criptográfico, tipo declarado/detectado, estado de procesamiento y retención. El binario original se conserva privado y no se reemplaza durante OCR o normalización.

### Procesamiento documental

Coordina inspección, saneamiento cuando sea aprobado, extracción de texto, rasterización, OCR y generación de derivados. Cada etapa debe ser repetible, idempotente y registrar versión de herramienta y parámetros.

### Verificación

Ejecuta reglas deterministas y controles humanos. Una verificación produce hallazgos y evidencia, no una afirmación clínica automática. El diseño detallado está en `MEDICAL_DOCUMENT_VERIFICATION_ARCHITECTURE.md`.

### Auditoría y cumplimiento

Registra accesos y cambios relevantes en una bitácora protegida contra alteración. La política jurídica concreta depende de jurisdicción, contratos y evaluación de privacidad todavía no proporcionados.

## Capas internas

- **HTTP/UI:** validación de entrada, presentación y traducción de errores; sin lógica documental pesada.
- **Aplicación:** casos de uso y transacciones, por ejemplo iniciar carga, solicitar verificación y resolver revisión.
- **Dominio:** estados, invariantes y políticas independientes del framework cuando aporten claridad.
- **Infraestructura:** Eloquent, almacenamiento, colas, antivirus y adaptadores de herramientas PDF/OCR.

Los controladores deben delegar en casos de uso. Los trabajos de cola reciben identificadores, vuelven a consultar el estado y no serializan contenido médico completo en el payload.

## Flujo principal propuesto

1. El usuario autorizado solicita una carga asociada a un contexto permitido.
2. La aplicación valida tamaño, extensión y tipo básico, crea el registro y guarda el original en almacenamiento privado.
3. Se calcula SHA-256 durante o inmediatamente después de la escritura y se confirma integridad.
4. El documento pasa a cuarentena y se encola la inspección.
5. Trabajadores ejecutan etapas con límites de CPU, memoria y tiempo.
6. Las reglas generan hallazgos con severidad, evidencia y versión.
7. Si corresponde, un revisor humano decide y deja motivo auditable.
8. Solo los derivados aprobados se habilitan para consulta; la descarga siempre revalida autorización.

## Estados del documento

Estados sugeridos: `uploaded`, `quarantined`, `processing`, `requires_review`, `verified`, `rejected`, `failed` y `archived`. Las transiciones deben estar enumeradas; no se deben aceptar cambios arbitrarios desde formularios. `failed` representa una falla técnica, no invalidez médica. `rejected` requiere una razón humana o regla explícita aprobada.

## Contratos y eventos

Eventos internos sugeridos: `DocumentUploaded`, `DocumentInspectionPassed`, `TextExtracted`, `VerificationCompleted` y `ReviewResolved`. Los eventos no deben incluir texto clínico ni rutas físicas. Para integraciones futuras, usar una bandeja de salida transaccional antes de publicar externamente; no existe necesidad confirmada de integraciones en el estado actual.

## Disponibilidad y recuperación

- Definir RPO y RTO con responsables antes de seleccionar topología.
- Respaldar base de datos y objetos con cifrado y restauraciones probadas.
- Evitar que un respaldo de metadatos apunte a objetos no respaldados; usar un procedimiento coordinado.
- Diseñar trabajos idempotentes y reintentables con cola de fallos.
- Documentar recuperación de claves por separado y nunca incluirlas en el respaldo ordinario sin protección adecuada.

## Observabilidad

Las métricas deben cubrir profundidad de cola, latencia por etapa, tasa de fallos, reintentos y capacidad. Los logs deben usar identificadores técnicos opacos y códigos de error. El texto extraído, nombre de paciente, número de documento, diagnóstico y binarios están prohibidos en telemetría general.

## Decisiones pendientes

- Jurisdicción y marco normativo aplicable.
- Roles reales, matriz de acceso y responsables de aprobación.
- Taxonomía documental y criterios de verificación.
- Volumen, tamaño máximo, concurrencia, RPO, RTO y retención.
- Motor de base de datos, cola, antivirus y almacenamiento de producción.
- Necesidad de firma electrónica, interoperabilidad o sistemas externos.
- Requisitos de accesibilidad, idiomas y canales de notificación.

Toda decisión pendiente requiere evidencia y aprobación; este documento no inventa respuestas.
