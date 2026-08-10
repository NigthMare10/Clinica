# Progreso de implementacion

## Actualizacion de red y experiencia - 10 de agosto de 2026

- Creados `UI_UX_REDESIGN_PLAN.md`, `PATIENT_DOCUMENT_WORKFLOW.md`, `DOCUMENT_GENERATOR_ARCHITECTURE.md` y `CLINIC_NETWORK_MAP.md`.
- Agregada migracion aditiva para 18 coberturas, membresias, pacientes por clinica y procedencia de documentos generados.
- Implementados home editorial, `/clinicas` con Leaflet diferido, GSAP/ScrollTrigger respetando movimiento reducido y placeholders declarados.
- Ampliada verificacion publica con camara QR progresiva y validacion de mismo origen.
- Implementados buscador global, perfil documental de paciente, linea de tiempo y dashboard operativo.
- Implementado generador de constancia/incapacidad con confirmacion, PDF fuente privado, SHA-256, version original, auditoria y revision humana obligatoria.
- Las 18 coberturas conceptuales permanecen `PLANNED`, sin direccion, coordenadas o contacto; no pueden generar documentos hasta ser activadas con informacion aprobada.
- Validacion final: build Vite/TypeScript con 907 modulos y PHPUnit con 53/53 pruebas, 183 aserciones.

## Corte

Estado comprobado el 10 de agosto de 2026 mediante lectura de codigo y ejecuciones locales. `COMPLETED` significa implementado y comprobable en codigo o por una validacion que no depende del toolchain pendiente; no significa listo para produccion. `PARTIAL` identifica implementacion existente con validacion incompleta. `BLOCKED` identifica trabajo que no puede validarse honestamente en el entorno actual.

## Estado

| Area | Estado | Evidencia y limite actual |
|---|---|---|
| Backend y dominio clinico | COMPLETED | Modelos, enums, relaciones, migracion de dominio, factories, politicas, requests, servicios y auditoria implementados en `app/` y `database/`. |
| Autenticacion y roles | COMPLETED | Login, recuperacion, verificacion de correo, perfil, middleware de rol y roles `SUPER_ADMIN`, `ADMINISTRATOR`, `DOCTOR`, `DOCUMENT_OPERATOR`, `AUDITOR`; registro publico deshabilitado. |
| Seeding | COMPLETED | Catalogo inicial, ajustes conservadores y alta/actualizacion condicional de `SUPER_ADMIN` desde `ADMIN_*`; no hay credenciales incluidas. |
| Rutas publicas, privadas y de verificacion | COMPLETED | `php artisan route:list --except-vendor` enumera 79 rutas, incluidas `/clinicas`, busqueda, perfil de paciente y generador documental. |
| UI Vue/Inertia | COMPLETED | Pantallas publicas, autenticacion, panel, catalogos, mapa, expedientes, carga, generacion, revision, emision, revocacion, reemision y verificacion presentes. `npm run build` finalizo correctamente con 907 modulos transformados. |
| Carga y custodia del original | COMPLETED | Validacion MIME/magic, limite, almacenamiento privado, nombre aleatorio, SHA-256, version original, auditoria y prueba de no sustitucion del original. |
| Pipeline asincrono | COMPLETED | `ProcessMedicalDocument` implementa cola, estados, descifrado condicional, extraccion, fallback OCR, parsing, consistencia, persistencia de resultados, fallos y limpieza temporal. Requiere worker activo. |
| Revision humana y reglas | COMPLETED | UI y endpoint de confirmacion, candidatos duplicados, campos obligatorios, coherencia de fechas/dias/edad, credencial, bloqueos y estado `READY`. La precision no esta calibrada con documentos reales. |
| Emision, QR y versiones | COMPLETED | Guardas de revision, firma y consistencia; integridad, QR/token opaco, codigo publico, estampado, renderizado Poppler a 240 DPI y decodificacion local exacta antes de guardar, cifrado opcional, version y auditoria. Sin Poppler la emision se bloquea. |
| Verificacion publica | COMPLETED | Token, codigo y archivo/hash; estados `VALID`, `REVOKED`, `NOT_ISSUED`, `NOT_FOUND` e `IDENTITY_REQUIRED`; exposicion controlada por ajustes y logs de intentos. |
| Revocacion y reemision | COMPLETED | Motivo obligatorio, estado revocado y auditoria; reemision relacionada que conserva referencia al original y vuelve a cola/revision. |
| Pruebas unitarias PHP | COMPLETED | `php artisan test --testsuite=Unit`: 6 pruebas y 22 aserciones aprobadas. |
| Pruebas PHP | COMPLETED | El host no carga normalmente `pdo_sqlite`, pero la DLL funciona de forma transitoria. La invocacion directa de PHPUnit ejecuta 53 pruebas: 53 aprobadas y 183 aserciones. |
| Playwright | COMPLETED | `npm run test:e2e` ejecuta 24 escenarios Chromium con SQLite, fixtures ficticios y cache en memoria: 24 aprobados. Incluye CRUD, roles, responsive, emision, QR, descarga, revocacion, reemision y verificacion por hash. |
| Tesseract OCR | COMPLETED | Tesseract 5.4.0 presente; `--list-langs` confirma `spa`, `eng` y `osd`. |
| qpdf y Poppler | COMPLETED | qpdf 12.3.2 y Poppler 25.07.0 detectados por ruta configurable/autodescubrimiento; descifrado, normalizacion, extraccion, render y QR comprobados. |
| Analisis y calibracion PDF | PARTIAL | Una muestra autorizada fue analizada sin registrar valores personales: texto digital, 18 candidatos, bloqueos conservadores y QR renderizado/decodificado. Falta un corpus representativo para calibracion estadistica. |
| Flujo real carga-revision-emision-verificacion-revocacion-reemision | PARTIAL | La compatibilidad tecnica de una muestra y el flujo E2E ficticio estan comprobados. La muestra real queda bloqueada correctamente por consistencia; faltan revision humana aprobada y MySQL local preparado. |
| Placeholders visuales WebP | COMPLETED | Existen 44 WebP abstractos generados programaticamente y documentados en `GENERATED_IMAGES_MANIFEST.md`; los nombres requeridos actualmente por la UI existen. |
| Fotografias generadas | BLOCKED | Los WebP no son fotos ni salida fotorrealista. No hay generador de imagen ni MCP de generacion expuesto; siguen bloqueadas fotografias de personas, profesionales o instalaciones. |
| Preparacion de produccion | PARTIAL | Hay cabeceras, `noindex`, throttling, autorizacion, hashes y auditoria. Siguen pendientes decisiones normativas, retencion, infraestructura, backups/restauracion, monitoreo, aislamiento y pruebas de seguridad. |

## Entorno observado

- PHP 8.5.6; requisito del proyecto `^8.3`.
- `pdo_mysql` cargado; `pdo_sqlite` ausente de la configuracion normal de PHP CLI.
- La DLL de SQLite puede cargarse transitoriamente con `-d extension=pdo_sqlite`, pero `artisan test` crea un subproceso que no hereda ese argumento; la invocacion directa de PHPUnit si lo conserva.
- Node.js 24.15 y npm 11.14 observados previamente; dependencias PHP y Node instaladas.
- Tesseract 5.4.0 con `spa+eng` disponible.
- qpdf 12.3.2 y Poppler 25.07.0 estan instalados y se autodetectan aunque no todos esten en `PATH`.
- Existe una muestra autorizada analizada localmente; no se incorpora al repositorio y no constituye un corpus de calibracion.
- No hay herramienta/MCP de generacion de imagen expuesta.

## Validaciones ejecutadas

| Comando | Resultado |
|---|---|
| `php artisan route:list --except-vendor` | 79 rutas enumeradas |
| `npm run build` | Aprobado; Vue TypeScript y Vite completan build |
| `php artisan test --testsuite=Unit` | 6/6 aprobadas, 22 aserciones |
| `php artisan test` | No verde: 7 aprobadas, 28 errores por `pdo_sqlite` ausente |
| `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit` | 53/53 aprobadas; 183 aserciones |
| `npm run test:e2e` | 24/24 escenarios Chromium aprobados |
| `tesseract --version` / `--list-langs` | 5.4.0; `spa`, `eng`, `osd` |
| `scripts/check-document-tools.ps1` | qpdf 12.3.2, Poppler 25.07.0 y Tesseract 5.4 detectados |
| `scripts/verify-local-qa-qr.php` | QR estampado en muestra normalizada, renderizado a 240 DPI y decodificado exactamente |

## Bloqueos y siguientes salidas

| Prioridad | Bloqueo | Criterio de salida |
|---|---|---|
| 1 | Muestras | Ampliar a un corpus autorizado y desidentificado que represente tipos, escaneos, cifrado y calidad reales. |
| 2 | Calibracion | Medir extraccion/OCR, ajustar umbral y reglas, versionar criterios y aprobar limites de error. |
| 3 | MySQL local | Proporcionar credenciales administrativas, crear la base y ejecutar migraciones/seeders sin exponer secretos. |
| 4 | Runner PHP | Habilitar `pdo_sqlite` normalmente para que `php artisan test` funcione sin la invocacion transitoria ya verificada. |
| 5 | E2E | Preparar MySQL aislado, admin/fixtures, worker y PDF genuino; ejecutar los 12 escenarios y corregir cualquier desalineacion ruta/UI. |
| 6 | Operacion | Aprobar privacidad/retencion, backups, restauracion, monitoreo, secretos, aislamiento del worker e incidentes. |
| 7 | Fotografia | Proveer activos aprobados o exponer un generador autorizado con revision de procedencia, privacidad y representacion. |

## Nota de alcance

La arquitectura objetivo descrita en otros documentos puede contener controles o estructuras mas amplios que la implementacion actual. El codigo implementa el flujo principal; ninguna afirmacion de precision PDF, compatibilidad con documentos reales, cumplimiento normativo o preparacion de produccion es valida hasta cerrar calibracion y operacion.

## Regla de actualizacion

Actualizar este documento solo con evidencia verificable: codigo leido, migracion, prueba ejecutada, herramienta comprobada o decision aprobada. Mantener separadas implementacion, validacion tecnica, calibracion y aprobacion operativa.
