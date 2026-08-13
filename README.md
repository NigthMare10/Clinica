# Clinica Medica Santa Ana

Aplicacion Laravel 13 con Inertia y Vue 3 para el sitio publico de la clinica y la gestion privada de documentos medicos. Implementa carga privada de PDF, procesamiento en cola, extraccion de texto/OCR, revision humana, emision con QR, verificacion publica, revocacion, reemision y auditoria.

La implementacion existe, pero el procesamiento documental real todavia requiere instalar qpdf y Poppler y calibrar las reglas con muestras autorizadas. Consulte `docs/IMPLEMENTATION_PROGRESS.md` antes de usar el sistema fuera de desarrollo.

## Requisitos previos

- PHP 8.3 o superior, con las extensiones habituales de Laravel y `pdo_mysql`.
- Composer.
- Node.js y npm.
- MySQL.
- qpdf, disponible como `qpdf` o mediante `QPDF_BINARY`.
- Poppler, al menos `pdftotext` y `pdftoppm`, disponibles en `PATH` o mediante sus variables `*_BINARY`.
- Tesseract OCR con los idiomas `spa` y `eng`; `tesseract --list-langs` debe mostrar ambos.
- Para las pruebas PHP configuradas en `phpunit.xml`, `pdo_sqlite`.
- Para E2E, Chromium de Playwright.

Compruebe las herramientas documentales antes de procesar archivos:

```powershell
qpdf --version
pdftotext -v
pdftoppm -v
tesseract --version
tesseract --list-langs
```

En Windows, consulte `docs/WINDOWS_DOCUMENT_TOOLS_SETUP.md`. Si los ejecutables no estan en `PATH`, configure rutas absolutas en `QPDF_BINARY`, `PDFTOTEXT_BINARY`, `PDFTOPPM_BINARY` y `TESSERACT_BINARY`.

## Instalacion

### 1. Crear la base MySQL

Ejecute con una cuenta MySQL autorizada y use credenciales propias del entorno:

```sql
CREATE DATABASE clinic_santa_ana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'clinic_app'@'localhost' IDENTIFIED BY '<contrasena-segura>';
GRANT ALL PRIVILEGES ON clinic_santa_ana.* TO 'clinic_app'@'localhost';
FLUSH PRIVILEGES;
```

No reutilice el usuario `root` en produccion.

### 2. Copiar y configurar el entorno

```powershell
Copy-Item .env.example .env
```

Edite `.env` y configure, como minimo:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=clinic_santa_ana
DB_USERNAME=clinic_app
DB_PASSWORD=

QUEUE_CONNECTION=database
MEDICAL_OCR_LANGUAGES=spa+eng
MEDICAL_PDF_ENCRYPTION=false
INSTITUTIONAL_PDF_PASSWORD=
```

Asigne `DB_PASSWORD` localmente; no publique el valor. Configure `INSTITUTIONAL_PDF_PASSWORD` mediante el entorno o gestor de secretos para proteger los permisos de los PDF finales. Nunca coloque la clave en el repositorio, comandos compartidos, capturas ni documentacion.

### 3. Configurar el administrador inicial

El seeder crea o actualiza un usuario `SUPER_ADMIN` solo si existen ambas variables:

```dotenv
ADMIN_NAME="Administrador principal"
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

Complete el correo y la contrasena exclusivamente en el entorno local o gestor de secretos. Este README no contiene ni debe contener credenciales. El registro publico esta deshabilitado, por lo que se necesita este seeding u otro procedimiento administrativo controlado.

### 4. Instalar, generar clave y preparar datos

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
```

El seeder registra el catalogo inicial de especialidades como no publico, ajustes conservadores de verificacion y, si se configuraron `ADMIN_EMAIL` y `ADMIN_PASSWORD`, el administrador inicial.

## Ejecucion

Para desarrollo completo, el script de Composer inicia servidor, trabajador de cola, logs y Vite:

```powershell
composer run dev
```

Alternativamente, use terminales separadas:

```powershell
php artisan serve
php artisan queue:work --tries=2 --timeout=120
npm run dev
```

Para generar frontend de produccion:

```powershell
npm run build
```

El trabajador de cola es obligatorio: sin el, una carga queda en procesamiento y no llega a revision. En produccion use un supervisor, una cuenta sin privilegios, reinicio controlado de workers tras despliegues y almacenamiento privado persistente.

## Flujo documental completo

1. **Cargar:** inicie sesion y abra `/admin/documents/create`. Seleccione el tipo y cargue un PDF genuino. El backend valida archivo, limite configurado, MIME y cabecera `%PDF-`, guarda el original en el disco privado, calcula SHA-256, registra version y auditoria, y envia `ProcessMedicalDocument` a la cola.
2. **Procesar:** el worker intenta descifrar con qpdf cuando detecta `/Encrypt`, detecta firmas digitales, extrae texto con `pdftotext` y usa `pdftoppm` mas Tesseract `spa+eng` cuando la calidad es insuficiente. Guarda candidatos, calidad, advertencias e inconsistencias y pasa a revision o marca el proceso como fallido.
3. **Revisar:** abra `/admin/documents/{documento}/review`, contraste el PDF original y los campos extraidos, corrija valores y apruebe. Los campos obligatorios, conflictos, fechas, dias y credencial medica pueden bloquear la aprobacion. La emision exige revision humana y estado `READY`.
4. **Emitir:** desde la revision, confirme la emision. El servicio vuelve a comprobar el hash inmutable del original, bloqueos y firma digital; genera token opaco, codigo publico y QR, estampa una copia nueva y, si esta habilitado, la cifra. El original no se reemplaza.
5. **Verificar:** pruebe el QR/token en `GET /verificar/{token}`, el codigo en `POST /verificar/codigo` o el archivo emitido en `POST /verificar/archivo`. La respuesta puede ser valido, revocado, no emitido, no encontrado o requerir los ultimos cuatro digitos, segun ajustes. Cada intento queda registrado.
6. **Revocar:** desde el documento emitido, indique un motivo obligatorio y confirme. La verificacion publica pasa a mostrar estado revocado; la evidencia y auditoria se conservan.
7. **Reemitir:** desde un documento emitido o revocado, cree una reemision. Se crea un documento relacionado que reutiliza la referencia al original inmutable, vuelve al pipeline en cola, requiere otra revision humana y debe emitirse como una version nueva con token/codigo propios.

La existencia de este flujo en codigo no sustituye la calibracion con PDF representativos ni una validacion operativa de extremo a extremo.

## Rutas principales

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/` | Inicio publico |
| GET | `/especialidades` | Catalogo de especialidades |
| GET | `/especialidades/{slug}` | Detalle de especialidad |
| GET | `/medicos` | Catalogo medico |
| GET | `/medicos/{medico}` | Perfil medico |
| GET | `/clinica` | Informacion institucional |
| GET | `/contacto` | Contacto |
| GET | `/verificar` | Formulario de verificacion |
| GET | `/verificar/{token}` | Verificacion por token/QR |
| POST | `/verificar/codigo` | Verificacion por codigo publico |
| POST | `/verificar/archivo` | Verificacion por hash del PDF emitido |
| GET | `/login` | Inicio de sesion |
| GET | `/admin` | Panel privado |
| GET/POST | `/admin/documents` | Listado y carga documental |
| GET/PUT | `/admin/documents/{documento}/review` | Revision y confirmacion humana |
| POST | `/admin/documents/{documento}/issue` | Emision |
| POST | `/admin/documents/{documento}/revoke` | Revocacion |
| POST | `/admin/documents/{documento}/reissue` | Creacion de reemision |
| GET | `/admin/documents/{documento}/download/{original|issued}` | Descarga privada autorizada |
| GET | `/admin/specialties` | Catalogo privado de especialidades |
| GET | `/admin/doctors` | Catalogo privado de medicos |
| GET | `/admin/patients` | Catalogo privado de pacientes |
| GET | `/admin/templates` | Plantillas PDF |
| GET | `/admin/audit` | Auditoria |
| GET | `/admin/settings` | Ajustes |

Las rutas `/admin` requieren autenticacion, correo verificado, rol permitido y cabeceras `noindex`. Emision y revocacion estan restringidas por politicas; no exponga descargas privadas como archivos publicos.

## Pruebas

Pruebas PHP completas:

```powershell
composer test
```

Solo pruebas unitarias, que no requieren SQLite:

```powershell
php artisan test --testsuite=Unit
```

`phpunit.xml` usa SQLite en memoria. Si el host Windows tiene `php_pdo_sqlite.dll` pero no esta habilitado, puede comprobarlo de forma transitoria sin editar `php.ini`:

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
```

Instale el navegador y enumere/ejecute Playwright:

```powershell
npx playwright install chromium
npm run test:e2e -- --list
npm run test:e2e
```

Los escenarios autenticados y documentales usan, cuando corresponda, `E2E_ADMIN_EMAIL`, `E2E_ADMIN_PASSWORD`, `E2E_PDF_PATH`, `E2E_DOCUMENT_ID`, `E2E_ISSUED_DOCUMENT_ID` y `E2E_VERIFICATION_TOKEN`. Use una base de prueba aislada, datos sinteticos y un PDF de prueba genuino. No apunte E2E a produccion. `E2E_BASE_URL` permite usar un servidor ya preparado; de lo contrario Playwright inicia Laravel en `http://127.0.0.1:8017`.

## Precauciones operativas

- No use datos clinicos reales hasta aprobar privacidad, jurisdiccion, retencion, respaldo, restauracion, incidentes y control de acceso.
- No considere el parser ni OCR calibrados sin un corpus autorizado, desidentificado y representativo; una extraccion automatica nunca reemplaza la revision humana.
- Mantenga `storage` fuera del web root, con ACL minimas, cifrado de disco y copias de seguridad probadas. No registre texto medico, tokens ni contrasenas en logs.
- El original es evidencia inmutable. No lo sobrescriba al emitir, revocar o reemitir; verifique hashes y conserve auditoria.
- qpdf y Poppler son dependencias operativas del pipeline. La mera presencia de Tesseract no permite completar procesamiento ni emision real.
- La deteccion de firma digital bloquea la emision porque estampar una copia puede invalidar su semantica. Defina un procedimiento institucional antes de cambiar esta regla.
- Ajuste `APP_DEBUG=false`, HTTPS, cookies seguras, limites de carga, timeouts, rate limiting, rotacion de secretos y permisos del worker antes de produccion.
- Las imagenes actuales son placeholders abstractos generados por programa, no fotografias de la clinica, sus instalaciones o personas. Consulte `docs/GENERATED_IMAGES_MANIFEST.md`.

## Documentacion

- `docs/IMPLEMENTATION_PROGRESS.md`: estado verificable y bloqueos.
- `docs/PDF_PROCESSING_PIPELINE.md`: pipeline y calibracion.
- `docs/SECURITY_AND_PRIVACY.md`: controles y decisiones pendientes.
- `docs/MEDICAL_DOCUMENT_VERIFICATION_ARCHITECTURE.md`: verificacion y confianza.
- `docs/GENERATED_IMAGES_MANIFEST.md`: inventario visual real.
