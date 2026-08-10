# Arquitectura del generador documental

## Decision principal

No se crea un segundo subsistema. Constancias e incapacidades generan un PDF fuente inmutable y convergen en `MedicalDocument` para revision, emision, QR, hash, verificacion, revocacion y reemision.

## Flujo

```text
Formulario estructurado
  -> validacion por tipo y autorizacion de clinica
  -> vista previa de datos
  -> confirmacion final explicita
  -> render de PDF fuente con plantilla versionada
  -> almacenamiento privado + SHA-256 + version original
  -> REVIEW_REQUIRED
  -> revision humana
  -> READY
  -> MedicalDocumentIssueService existente
  -> ISSUED + QR verificable
```

## Limites de responsabilidad

| Componente | Responsabilidad |
|---|---|
| `GenerateMedicalDocumentRequest` | Validacion por tipo, fechas y referencias |
| `GeneratedMedicalDocumentController` | Orquestacion HTTP y confirmacion |
| `GenerateMedicalDocumentService` | Transaccion de metadatos, archivo, hash, version y auditoria |
| `PdfTemplateRenderService` | Render determinista del PDF fuente |
| `MedicalDocumentIssueService` | Emision, QR, estampado, verificacion y version emitida |
| `MedicalDocumentConsistencyService` | Bloqueos de coherencia y profesional |

## Tipos

Se conserva `MEDICAL_CERTIFICATE` para compatibilidad. `certificate_kind` distingue:

- `CONSTANCIA`: fecha de atencion, proposito aprobado y recomendaciones opcionales.
- `INCAPACIDAD`: fecha inicial, final, cantidad de dias y motivo medico autorizado.

Los diagnosticos no son obligatorios para constancias salvo decision clinica/legal aprobada.

## Plantillas

- Una plantilla puede ser de red o especifica de clinica.
- Debe declarar tipo, subtipo, version, pagina base y esquema de campos.
- Una version utilizada no se modifica; se crea una nueva version.
- El documento conserva snapshot de plantilla y coordenadas para reproducibilidad.
- La firma grafica o sello no se aplican automaticamente sin proceso legal aprobado.
- El renderer debe soportar nombres y texto en espanol mediante fuente Unicode embebida.

## Modelo minimo

Extensiones a `medical_documents`:

- `clinic_id`
- `source_kind`: `UPLOADED` o `GENERATED`
- `certificate_kind`
- `template_snapshot`
- `generated_at`

Extensiones a `pdf_templates`:

- `clinic_id`
- `certificate_kind`
- `source_path`
- `version`
- `field_schema`
- `supersedes_id`

## Confirmacion final

Antes de generar se presenta una vista de solo lectura con paciente, medico, clinica, fechas, subtipo y contenido. La accion final debe indicar que crea un original privado sujeto a revision; no equivale a emision ni firma.

## Seguridad e integridad

- El PDF fuente se almacena en disco privado y nunca se sobrescribe.
- La contrasena PDF se obtiene solo de `.env`.
- Los archivos generados no pasan por OCR como fuente de verdad.
- La plantilla debe coincidir con clinica, tipo, subtipo y estado activo.
- Ningun token QR contiene PII.
- Los fallos eliminan artefactos parciales y no dejan documentos huerfanos.

## Pruebas requeridas

- Generacion de constancia e incapacidad.
- Coherencia entre rango de fechas y cantidad de dias.
- Rechazo de plantilla incompatible.
- Snapshot inmutable y original preservado.
- Aprobacion humana obligatoria.
- Emision idempotente, QR legible y reemision con procedencia.
- Caracteres Unicode en nombres y contenido.

## Estado

La convergencia con el pipeline existente esta definida. El renderer de plantillas y los campos de procedencia son trabajo incremental; no alteran los servicios OCR/QR existentes.
