# Plan de generación de imágenes

## Objetivo

Planificar recursos visuales para la interfaz sin inventar identidad, instalaciones, personal, pacientes, servicios ni hechos de la clínica. La generación está pendiente: en esta sesión no se expone ninguna herramienta o MCP de generación de imágenes.

## Principios

- No crear fotografías que puedan interpretarse como instalaciones o profesionales reales.
- No representar pacientes identificables, documentos legibles, resultados médicos ni datos personales.
- Preferir ilustración abstracta, iconografía y diagramas sobre escenas clínicas genéricas.
- Mantener función informativa: una imagen no reemplaza etiqueta, estado o instrucción.
- Cumplir contraste, texto alternativo y comportamiento adaptable.
- Documentar herramienta, prompt, fecha, licencia/condiciones y revisiones humanas.

## Recursos propuestos

### Ilustración de acceso

Composición abstracta de archivo seguro y verificación documental. Sin logotipos, edificios, uniformes identificables ni texto generado. Uso opcional en autenticación, evitando desplazar el formulario en móvil.

### Estado vacío de documentos

Ilustración simple de hojas y bandeja, con estética neutral. Debe comunicar ausencia de elementos, no éxito ni error. El CTA y explicación son HTML, nunca texto incrustado en la imagen.

### Procesamiento en curso

Diagrama o animación CSS de etapas: recepción, inspección, extracción y revisión. Preferir componentes vectoriales propios a una imagen generada para conservar accesibilidad y reflejar estados reales.

### Revisión documental

Iconos para hallazgo, advertencia, bloqueo y no determinable. Deben diseñarse como sistema coherente y no depender solo del color. Para estos elementos conviene SVG manual/revisado, no generación raster.

### Página informativa

Una textura abstracta opcional, sin cruces médicas, símbolos regulados ni promesas visuales de especialidades. Solo se producirá después de disponer de identidad visual aprobada.

## Especificación de entrega

- Formato preferido: SVG revisado para ilustraciones planas; WebP/AVIF con fallback si se aprueba raster.
- Variantes: clara y oscura solo si el producto soporta ambos temas.
- Tamaños: fuente maestra y exportaciones responsivas basadas en uso real.
- Fondo: transparente cuando facilite reutilización.
- Sin texto dentro de la imagen salvo marca aprobada.
- Metadatos innecesarios eliminados; hash y licencia registrados.
- Texto alternativo definido por contexto; `alt=""` para decoración pura.

## Flujo de aprobación

1. Confirmar identidad visual, tono, audiencia y pantallas reales.
2. Elaborar brief sin hechos no verificados.
3. Generar opciones con una herramienta aprobada y condiciones de uso revisadas.
4. Revisar artefactos, anatomía, sesgos, símbolos, privacidad y similitudes de marca.
5. Validar accesibilidad y uso en móvil/escritorio.
6. Optimizar y registrar en el manifiesto.
7. Integrar solo después de aprobación explícita.

## Prompts preliminares seguros

Estos prompts son borradores y no se han ejecutado:

```text
Ilustración editorial vectorial abstracta sobre custodia y verificación de documentos,
formas geométricas sobrias, sin personas, sin edificios, sin logotipos, sin texto,
sin datos médicos visibles, fondo transparente, composición accesible y limpia.
```

```text
Ilustración vectorial de estado vacío con una bandeja y hojas abstractas,
tono neutral y profesional, sin símbolos de una institución real, sin texto,
sin información personal, pocos detalles, fondo transparente.
```

La paleta y tipografía no se especifican porque no existe una guía de marca aprobada.

## Riesgos y mitigaciones

- **Representación falsa:** usar abstracción y aprobación institucional.
- **Datos ficticios legibles:** no solicitar ni aceptar texto/documentos en la imagen.
- **Sesgo o estereotipo:** evitar personas salvo necesidad validada y revisión diversa.
- **Licencia incierta:** conservar términos y procedencia de la herramienta.
- **Inconsistencia visual:** generar desde un brief y tokens aprobados, no prompts aislados.
- **Peso excesivo:** límites de bytes, dimensiones y pruebas de rendimiento.

## Condición de inicio

La generación puede comenzar cuando haya herramienta autorizada, guía visual mínima, inventario de pantallas y responsable de aprobación. Hasta entonces no existen imágenes generadas ni rutas de assets comprometidas.
