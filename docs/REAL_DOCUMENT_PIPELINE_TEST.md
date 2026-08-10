# Prueba del pipeline documental real

## Resultado

La muestra autorizada supero descifrado, normalizacion, inspeccion, extraccion de texto, parsing y una prueba tecnica independiente de estampado/lectura QR. La emision institucional real no se ejecuto porque la consistencia detecto bloqueos y MySQL no esta preparado con credenciales locales.

## Secuencia comprobada

1. `PdfEncryptionService` obtuvo qpdf mediante el resolvedor de herramientas.
2. La contrasena se entrego mediante archivo temporal restringido y nunca como argumento de proceso ni salida.
3. qpdf descifro y normalizo la copia temporal con `--object-streams=disable`.
4. Poppler 25.07.0 confirmo una pagina Letter y extrajo 2,567 caracteres con `pdftotext -layout`.
5. El parser genero 18 candidatos sin usar OCR.
6. La consistencia detecto conflictos y campos obligatorios pendientes, con resultado bloqueante.
7. Una copia normalizada se estampo con un token ficticio no persistido.
8. Poppler renderizo solo un recorte alrededor del QR a 240 DPI para evitar agotar memoria.
9. El lector QR recupero exactamente la URL esperada.

## Calibracion QR comprobada

| Parametro | Valor |
|---|---|
| Pagina | 1 |
| X | 165 mm |
| Y | 220 mm |
| Ancho / alto | 28 mm |
| Texto auxiliar | X 150 mm, Y 253 mm, 8 pt |
| Tamano fuente QR | 300 px |
| Margen fuente QR | 20 px |
| Render de verificacion | 240 DPI |
| Resultado de decodificacion | Aprobado, coincidencia exacta |
| Tamano de copia estampada | 644,659 bytes |

El margen predeterminado aumento de 10 a 20 px tras observar una decodificacion inestable con un token aleatorio. La verificacion final valida tanto el PNG fuente como el QR despues de incorporarlo y renderizarlo desde el PDF.

## Comandos locales reproducibles

Los paths deben apuntar a una muestra autorizada y a un directorio temporal fuera del repositorio:

```powershell
php scripts/decrypt-local-qa-pdf.php <input-cifrado> <copia-normalizada>
pdftotext -layout <copia-normalizada> <texto-temporal>
php scripts/inspect-local-qa-text.php <texto-temporal>
php scripts/verify-local-qa-qr.php <copia-normalizada> <copia-estampada>
```

## Criterio de seguridad

- No se almacenaron tokens, contrasenas, texto extraido ni valores clinicos en este documento.
- El script QR usa un modelo no persistido y no escribe la muestra en MySQL o SQLite.
- Los temporales internos del QR se eliminan en `finally`.
- El original permanece inmutable; todas las operaciones usan copias de trabajo.
- Los PDF estan ignorados por Git salvo fixtures sinteticos controlados.

## Estado de certificacion

La compatibilidad tecnica de esta muestra queda comprobada. El flujo real completo sigue `PARTIAL`: requiere resolver los bloqueos mediante revision humana, preparar MySQL y ejecutar carga, aprobacion, emision, verificacion, revocacion y reemision con controles operativos aprobados.

## Segunda ejecucion local

Una muestra cifrada autorizada en `docs/`, excluida por `*.pdf`, completo nuevamente:

1. Descifrado seguro con contrasena obtenida solo desde `.env`.
2. Normalizacion qpdf sin flujos de objetos incompatibles.
3. Extraccion de 2,465 caracteres mediante el resolvedor interno de Poppler.
4. Parsing de 17 candidatos y bloqueo conservador por conflictos/campos pendientes.
5. Estampado QR sobre copia temporal y lectura exacta desde pagina 1.
6. Eliminacion del directorio temporal completo al terminar.

La copia estampada midio 775,441 bytes. No se intento emitir ni persistir la muestra.
