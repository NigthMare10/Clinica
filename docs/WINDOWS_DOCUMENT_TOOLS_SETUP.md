# Preparación de herramientas documentales en Windows

## Inventario observado

Entorno informado y verificado para esta planificación:

| Componente | Estado |
|---|---|
| PHP | 8.5.6 disponible |
| Node.js | 24.15 disponible |
| npm | 11.14 disponible |
| Tesseract | 5.4 disponible |
| qpdf | No disponible |
| pdftotext | No disponible |
| pdftoppm | No disponible |
| pdfinfo | No disponible |
| ImageMagick | No disponible |

`pdftotext`, `pdftoppm` y `pdfinfo` normalmente se distribuyen con Poppler. La ubicación exacta de Tesseract y sus idiomas instalados no se documentan como confirmados.

## Política de instalación

Usar un método aprobado por la organización y verificar editor, firma/hash y licencia. No descargar binarios desde mirrors desconocidos. Fijar versiones por entorno y probar actualizaciones antes de producción. Los comandos siguientes son ejemplos; la disponibilidad de `winget` o Chocolatey no fue comprobada.

## Opción con winget

Buscar primero los identificadores actuales, porque pueden cambiar:

```powershell
winget search qpdf
winget search poppler
winget search ImageMagick
```

Instalar usando el identificador exacto mostrado por el catálogo aprobado:

```powershell
winget install --id <ID_QPDF> --exact
winget install --id <ID_POPPLER> --exact
winget install --id <ID_IMAGEMAGICK> --exact
```

No se proporcionan IDs inventados. Registrar versión y origen resueltos en la documentación operativa.

## Opción con Chocolatey

Si Chocolatey ya está aprobado y configurado:

```powershell
choco search qpdf --exact
choco search poppler
choco search imagemagick --exact
choco install qpdf poppler imagemagick
```

Confirmar nombres/paquetes antes de instalar y aplicar el procedimiento interno de elevación. No mezclar gestores para la misma herramienta.

## Configuración de PATH

Agregar únicamente los directorios `bin` instalados y reiniciar terminales/servicios. En producción es preferible configurar rutas absolutas mediante variables de entorno, por ejemplo `QPDF_BINARY`, `PDFINFO_BINARY`, `PDFTOTEXT_BINARY`, `PDFTOPPM_BINARY`, `TESSERACT_BINARY` e `IMAGEMAGICK_BINARY`, validadas al arrancar. No guardar rutas específicas de una estación en código.

## Verificación

En una terminal nueva:

```powershell
php --version
node --version
npm --version
tesseract --version
tesseract --list-langs
qpdf --version
pdfinfo -v
pdftotext -v
pdftoppm -v
magick -version
```

Algunas herramientas escriben versión en stderr; esto no implica fallo. Registrar salidas, arquitectura y rutas mediante `Get-Command <nombre>` en el runbook del entorno, sin incluir información sensible.

## Idiomas de Tesseract

Ejecutar `tesseract --list-langs`. Si se necesita español y `spa` no aparece, instalar el archivo oficial `spa.traineddata` compatible dentro del directorio `tessdata` aprobado o mediante el paquete correspondiente. Configurar `TESSDATA_PREFIX` solo cuando la distribución lo requiera. Validar hash y origen; no asumir que el idioma inglés sirve para documentos en español.

No se conoce aún el idioma real de las muestras, por lo que el conjunto definitivo queda pendiente.

## Prueba funcional posterior

La verificación de versiones no basta. Con un fixture sintético sin datos personales:

1. Ejecutar `qpdf --check` sobre un PDF válido y uno truncado.
2. Obtener páginas y cifrado con `pdfinfo`.
3. Extraer texto con `pdftotext` y confirmar separación de páginas.
4. Rasterizar una página con `pdftoppm` en un directorio temporal.
5. Ejecutar Tesseract sobre la imagen con idiomas explícitos.
6. Confirmar códigos de salida, timeout y limpieza de archivos.
7. Si se instala ImageMagick, comprobar políticas de seguridad antes de permitir PDF; no habilitar delegados PDF innecesarios.

## Integración segura desde Laravel

Usar el componente de procesos de Symfony/Laravel con argumentos separados, timeout y captura limitada. Nunca concatenar nombres suministrados por usuario ni invocar `cmd /c` o PowerShell para cada documento. Trabajar con rutas generadas y ACL privadas. Mapear códigos de salida a errores estables y no persistir contenido en `failed_jobs`.

Ejecutar trabajadores bajo una cuenta de servicio sin privilegios y, para producción, evaluar contenedores Linux o un servicio aislado: simplifica límites y reduce la exposición del servidor web. La decisión depende de infraestructura todavía no definida.

## Actualización y rollback

- Fijar versiones verificadas por entorno.
- Mantener fixtures de regresión sintéticos.
- Probar precisión, memoria, tiempo y seguridad antes de actualizar.
- Conservar instalador/version anterior durante una ventana controlada.
- Registrar cambios de versión en cada `processing_run`.
- Reprocesar solo mediante una operación explícita y auditable.

## Pendientes

- Elegir y aprobar el gestor/fuente de paquetes.
- Instalar qpdf y Poppler como mínimo para el pipeline propuesto.
- Decidir si ImageMagick es necesario después de calibración.
- Inventariar ruta e idiomas de Tesseract.
- Crear fixtures sintéticos y automatizar un health check.
- Definir aislamiento y cuenta de servicio para trabajadores.
