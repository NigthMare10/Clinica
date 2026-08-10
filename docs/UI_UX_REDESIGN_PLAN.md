# Plan de rediseno UI/UX

## Objetivo

Transformar la experiencia publica y operativa de Clinica Medica Santa Ana en una interfaz medica editorial, confiable y eficiente, sin cambiar Laravel, Vue, Inertia ni el ciclo documental existente.

## Principios

- Claridad clinica antes que decoracion.
- Confianza mediante jerarquia, lenguaje preciso y estados verificables.
- Fotografia real solo cuando tenga licencia, consentimiento y aprobacion institucional.
- Los recursos actuales permanecen identificados como placeholders con `data-placeholder="true"`.
- Movimiento discreto, progresivo y desactivable con `prefers-reduced-motion`.
- Operaciones sensibles siempre requieren acciones explicitas y estados inequivos.
- Ninguna vista publica expone datos clinicos, credenciales privadas o rutas de almacenamiento.

## Sistema visual

| Capa | Direccion |
|---|---|
| Color | Azul tinta, azul clinico, cian moderado, superficies marfil y hielo |
| Tipografia | Serif editorial para titulares y sans legible para operacion |
| Forma | Radios contenidos, bordes finos y vidrio suave sin perder contraste |
| Profundidad | Sombras amplias de baja opacidad y capas con separacion clara |
| Iconografia | SVG consistente; no depender de simbolos Unicode |
| Fotografia | Encuadres humanos y arquitectonicos aprobados, con tamanos responsivos |

## Arquitectura de interfaz

### Publico

- `PublicLayout`: cabecera persistente, navegacion activa, menu accesible y footer institucional.
- Home: propuesta de valor, especialidades, red clinica, profesionales, validacion documental y CTA.
- Especialidades y medicos: catalogo filtrable y fichas editoriales.
- Clinica: contenido CMS y evidencia institucional confirmada.
- Clinicas: mapa de Honduras y listado por departamento sin direcciones inventadas.
- Verificacion: codigo, archivo y escaneo QR con estados de privacidad claros.
- Login: acceso privado sobrio, sin registro publico.

### Privado

- `AdminLayout`: sidebar por capacidades, buscador global, contexto de clinica y acciones de pagina.
- Dashboard: metricas operativas, pendientes, actividad y accesos directos.
- Pacientes: indice, perfil autorizado, expediente documental y linea de tiempo.
- Documentos: carga, revision, emision, descarga, revocacion y reemision existentes.
- Generador: constancia e incapacidad convergen en el mismo agregado `MedicalDocument`.

## Animacion

- GSAP y ScrollTrigger solo en paginas publicas y elementos no esenciales.
- Revelados con desplazamientos cortos, opacidad y duraciones menores a un segundo.
- Sin animar tablas, diagnosticos, alertas o controles criticos.
- Carga diferida del codigo de animacion donde sea posible.
- Desmontar triggers al navegar con Inertia.

## Responsive y accesibilidad

- Puntos de control: 360, 480, 760, 1000, 1280 y 1440 px.
- Navegacion por teclado, foco visible, enlace de salto y landmarks.
- Controles tactiles de al menos 44 px cuando sean acciones principales.
- Tablas medicas conservan densidad y scroll local; no se convierten automaticamente en tarjetas.
- Imagenes con dimensiones, `loading`, `decoding` y `srcset` cuando existan activos reales.
- Contraste objetivo WCAG AA.

## Fases

1. Tokens, CSS mantenible, layouts y componentes base.
2. Home, catalogos publicos, clinica, clinicas y verificacion.
3. Login, dashboard, buscador y navegacion operativa.
4. Perfil de paciente, expediente y documentos privados.
5. Generador documental y confirmacion final.
6. Build, TypeScript, PHPUnit, Pint y revision manual sin Playwright.

## Criterios de aceptacion

- No se modifica la responsabilidad de OCR, qpdf, Poppler, Tesseract, FPDI, QR o hash.
- No se publica contenido institucional no aprobado.
- No hay secretos en codigo, HTML, documentacion o logs.
- Todos los flujos existentes mantienen sus rutas y controles de autorizacion o reciben una migracion explicita.
- La aplicacion compila y la suite PHP conserva sus contratos de seguridad.

## Estado

Documento de arquitectura aprobado para implementacion incremental. La certificacion visual final requiere revision manual institucional.
