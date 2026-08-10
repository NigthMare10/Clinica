# Activos visuales reales requeridos

## Estado

La interfaz usa placeholders WebP abstractos. No deben presentarse como fotografias reales de pacientes, profesionales, personal o instalaciones. No hay generador de imagen autorizado disponible en este entorno.

## Activos pendientes

| Categoria | Requisito |
|---|---|
| Fachada e instalaciones | Fotografias aprobadas de acceso, recepcion, espera y consultorios, sin datos personales visibles |
| Profesionales | Retratos con consentimiento, nombre/credencial validados y autorizacion de publicacion |
| Equipo de trabajo | Imagen institucional con consentimiento individual y vigencia definida |
| Especialidades | Imagenes propias o licenciadas que no prometan resultados clinicos ni representen procedimientos incorrectos |
| Accesibilidad | Texto alternativo factual, recorte responsive y contraste suficiente con texto superpuesto |

## Criterios de aceptacion

- Procedencia, licencia, consentimiento y fecha de aprobacion documentados.
- Sin pacientes identificables, expedientes, pantallas, placas, etiquetas o metadatos sensibles.
- Revision institucional de representacion, uniforme, instalaciones y credenciales.
- Variantes responsive optimizadas en WebP o AVIF, con dimensiones y peso definidos.
- Reemplazo explicito de cada placeholder registrado en `GENERATED_IMAGES_MANIFEST.md`.

Hasta recibir activos aprobados o una herramienta autorizada con revision humana, los placeholders deben conservar su caracter abstracto y no factual.
