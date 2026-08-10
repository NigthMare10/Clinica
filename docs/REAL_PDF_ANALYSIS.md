# Analisis de PDF real autorizado

## Alcance

Analisis local realizado el 10 de agosto de 2026 sobre una unica muestra medica autorizada. El archivo original no fue modificado, copiado al repositorio ni identificado por nombre en este documento. Los resultados no certifican precision clinica ni compatibilidad con un corpus completo.

## Propiedades observadas

| Propiedad | Resultado |
|---|---|
| Tamano original | 533,081 bytes |
| PDF | 1.7, cifrado |
| Paginas | 1 |
| Pagina | Letter, 612 x 792 pt, vertical, rotacion 0 |
| MediaBox / CropBox | 0, 0, 612, 792 |
| Texto digital | Disponible y util; 2,567 caracteres extraidos |
| OCR | No requerido para esta muestra |
| Formulario | AcroForm |
| JavaScript | No detectado |
| Firma digital | No detectada |
| Fuentes | Embebidas |
| Imagenes | 6 |

## Compatibilidad

La muestra usa flujos de objetos o referencias cruzadas comprimidas que el parser gratuito de FPDI no puede importar directamente. La fase temporal de descifrado ahora ejecuta qpdf con `--object-streams=disable`; esto normaliza la copia de trabajo sin modificar el original y permite el estampado posterior.

## Extraccion estructurada

El parser genero 18 candidatos distribuidos entre nombre de paciente, identificacion, edad, fecha de consulta, diagnostico, profesional, credencial y clinica. Solo se documentan tipos, cantidades y procedencia; no se registran valores medicos o identificadores personales.

| Campo | Cabecera | Cuerpo |
|---|---:|---:|
| `patient_name` | 2 | 1 |
| `patient_document` | 0 | 1 |
| `age` | 1 | 0 |
| `consultation_date` | 1 | 1 |
| `diagnosis` | 0 | 1 |
| `doctor_name` | 3 | 2 |
| `doctor_credential` | 0 | 1 |
| `clinic_name` | 1 | 3 |

## Consistencia

La validacion produjo `CONSULTATION_DATE_CONFLICT`, `conflicting_candidates` y `required_field_missing`. Existen bloqueos, por lo que esta muestra no debe emitirse automaticamente. El resultado valida el comportamiento conservador esperado: requiere revision humana y resolucion expresa antes de alcanzar `READY`.

## Limites

- Una muestra no permite medir sensibilidad, especificidad ni tasa de error.
- No se evaluaron escaneos degradados, multiples paginas, rotaciones, otros emisores ni otros tipos documentales.
- Los campos extraidos requieren verificacion humana; no son una interpretacion clinica aprobada.
- Los artefactos descifrados y estampados se mantuvieron fuera del repositorio para QA local.

## Reejecucion con muestra local autorizada

El 10 de agosto de 2026 se repitio la prueba con una muestra cifrada colocada localmente en `docs/` e ignorada por Git. No se registra su nombre, contrasena, texto extraido ni contenido clinico.

| Propiedad | Resultado |
|---|---|
| Descifrado y normalizacion | Aprobado mediante `PdfEncryptionService` |
| Texto digital | 2,465 caracteres |
| Candidatos estructurados | 17 |
| Campos detectados | paciente, identificacion, edad, fecha, diagnostico, profesional, credencial y clinica |
| Consistencia | `conflicting_candidates` y `required_field_missing` |
| Estado | Bloqueante; requiere revision humana |
| QR estampado y releido | Aprobado en pagina 1 |

La diferencia de conteos frente a la ejecucion anterior no cambia el criterio: el parser retiene ambiguedades y evita una emision automatica.
