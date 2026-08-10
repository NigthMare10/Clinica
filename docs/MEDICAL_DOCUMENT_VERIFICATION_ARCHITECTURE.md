# Arquitectura de verificación de documentos médicos

## Propósito

Definir un proceso técnico y humano para comprobar propiedades observables de un documento: integridad del archivo, legibilidad, presencia de campos requeridos y coherencia formal. “Verificado” no significa que el contenido sea médicamente verdadero, que una firma sea auténtica ni que el emisor esté habilitado, salvo que existan fuentes autorizadas y reglas aprobadas que lo demuestren.

No hay reglas de negocio implementadas ni muestras documentales disponibles. La taxonomía y los criterios descritos son una propuesta que debe validarse con personal clínico, legal, privacidad y seguridad.

## Límites de confianza

- El nombre del archivo, MIME enviado por el navegador y metadatos PDF no son confiables.
- El texto OCR es una observación probabilística y nunca sustituye al original.
- La ausencia de texto extraído no prueba que el documento esté vacío.
- Una imagen de firma o sello no demuestra autenticidad.
- Fechas, identificadores y nombres extraídos deben considerarse candidatos hasta ser confirmados.
- Una herramienta externa procesa entrada hostil y debe ejecutarse con aislamiento y límites.

## Tipos de control propuestos

### Controles técnicos

- Hash SHA-256 del original y verificación al recuperar.
- Detección de tipo por contenido, no solo por extensión.
- Límites de bytes, páginas, objetos, resolución y tiempo.
- Detección de cifrado, contraseña, corrupción, contenido activo y archivos incrustados.
- Análisis antimalware antes de exponer o transformar.
- Confirmación de que cada página puede analizarse o rasterizarse.

### Controles documentales

Los campos obligatorios dependen del tipo documental aprobado. El motor podría evaluar presencia de fecha, emisor, sujeto, páginas o identificador, pero ningún campo se declara obligatorio sin una especificación funcional. Las reglas deben almacenar versión, alcance, severidad y explicación.

### Controles de coherencia

Comparaciones posibles, sujetas a aprobación: orden temporal de fechas, consistencia de un identificador entre páginas, número de página y total, o correspondencia con metadatos ya confirmados. Las coincidencias difusas deben elevarse a revisión, no autocorregirse.

### Verificación externa

Consultar registros profesionales, firmas digitales o sistemas emisores solo mediante fuentes oficiales, base jurídica documentada y contratos aprobados. La arquitectura no presupone que estas fuentes existan.

## Modelo de evaluación

Cada ejecución crea una `verification_run` inmutable con:

- documento y versión evaluados;
- versión del conjunto de reglas;
- versiones de extractores y herramientas;
- inicio, fin, estado y error técnico sanitizado;
- hallazgos individuales y referencias a evidencia;
- decisión automática limitada y, si aplica, decisión humana.

Un hallazgo contiene código estable, severidad (`info`, `warning`, `error`, `blocking`), resultado (`pass`, `fail`, `unknown`, `not_applicable`), explicación y ubicación de evidencia. `unknown` es esencial cuando no se puede evaluar con confianza.

No se recomienda reducir inicialmente todo a una puntuación única: ocultaría la naturaleza de fallos y facilitaría umbrales arbitrarios. Si en el futuro se usa una puntuación, debe calibrarse con un corpus representativo etiquetado y conservar los hallazgos subyacentes.

## Flujo de verificación

1. Confirmar que el documento está en cuarentena y que el hash coincide.
2. Ejecutar controles de seguridad y estructura.
3. Extraer texto nativo por página; activar OCR solo donde sea necesario.
4. Medir calidad de extracción sin registrar contenido sensible en logs.
5. Clasificar el tipo documental únicamente si existe una taxonomía aprobada; si no, requerir selección humana.
6. Ejecutar reglas aplicables de manera determinista.
7. Crear hallazgos y evidencia mínima.
8. Resolver automáticamente solo casos explícitamente autorizados y de bajo riesgo.
9. Enviar casos ambiguos, contradictorios o de baja confianza a revisión humana.
10. Registrar decisión, actor, motivo y versión evaluada.

## Revisión humana

La interfaz debe mostrar original y derivado, resaltar evidencia y diferenciar texto nativo de OCR. El revisor debe poder confirmar, rechazar o marcar “no determinable”, con motivo obligatorio en decisiones sensibles. No debe poder editar silenciosamente el texto extraído; una corrección crea una anotación separada.

Para reducir sesgos, no presentar una puntuación como certeza. Cuando el proceso lo amerite, aplicar doble revisión o segregación de funciones. Los criterios para ello están pendientes de riesgo y normativa.

## Versionado y reevaluación

- Las reglas son inmutables una vez utilizadas; una modificación crea nueva versión.
- Una nueva versión no cambia resultados históricos.
- La reevaluación crea otra ejecución vinculada a la anterior.
- El documento original conserva su hash y no se sobrescribe.
- Cambios de OCR, parser o parámetros deben poder activar una reevaluación controlada.

## Manejo de errores

Separar `invalid_input`, `unsupported`, `security_rejected`, `processing_failed` y `inconclusive`. Un timeout o caída del OCR nunca debe transformarse en rechazo documental. Los reintentos deben ser limitados, con espera incremental y paso a revisión/operación cuando se agoten.

## Pruebas necesarias

- Archivos válidos, corruptos, truncados, cifrados y con extensión engañosa.
- PDFs con texto, escaneados, mixtos, rotados y multipágina.
- Entradas adversariales y bombas de descompresión dentro de un entorno aislado.
- Idempotencia y concurrencia de cada trabajo.
- Autorización para iniciar, ver y resolver verificaciones.
- Regresión por conjunto de reglas y versión de herramientas.
- Evaluación de falsos positivos y falsos negativos con corpus aprobado.

## Criterio de puesta en producción

Se requiere matriz de reglas aprobada, corpus de prueba desidentificado o sintético validado, umbrales medidos, procedimiento de revisión, monitoreo, restauración probada y evaluación de privacidad/seguridad. Actualmente ninguno de estos hitos está documentado como completado.
