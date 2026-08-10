# Mapa de red de clinicas

## Alcance

La experiencia publica representa cobertura conceptual en los 18 departamentos de Honduras. No afirma sedes operativas, direcciones, telefonos, horarios ni coordenadas exactas sin aprobacion institucional.

## Departamentos

1. Atlantida
2. Colon
3. Comayagua
4. Copan
5. Cortes
6. Choluteca
7. El Paraiso
8. Francisco Morazan
9. Gracias a Dios
10. Intibuca
11. Islas de la Bahia
12. La Paz
13. Lempira
14. Ocotepeque
15. Olancho
16. Santa Barbara
17. Valle
18. Yoro

## Datos permitidos antes de confirmacion

- codigo estable del departamento;
- nombre del departamento;
- estado `PLANNED`, `CONFIRMED`, `ACTIVE` o `INACTIVE`;
- orden editorial;
- coordenadas nulas o configurables;
- mensaje institucional pendiente de confirmacion.

No se deben inventar nombres de sedes, direcciones, rutas, fotografias, profesionales, servicios locales ni testimonios.

## Modelo

`clinics` contiene una sede institucional confirmada. Para el seed conceptual, cada registro puede usar un nombre neutral derivado del departamento y estado `PLANNED`; no se publica como sede activa hasta recibir aprobacion.

Campos recomendados:

- `id`, `code`, `slug`, `name`, `department`;
- `latitude`, `longitude` anulables;
- `address`, `phone`, `hours` anulables;
- `status`, `is_public`, `sort_order`;
- timestamps.

Relaciones operativas:

- `clinic_user` para autorizacion;
- `clinic_doctor` para profesionales con multiples sedes;
- `patient_clinic` para expediente local;
- `clinic_id` en documentos y plantillas.

## Experiencia publica `/clinicas`

- Mapa Leaflet con tiles aprobados y atribucion visible.
- Sin coordenadas confirmadas, se muestra Honduras y el listado departamental sin marcadores falsos.
- Filtros por departamento y estado confirmado.
- Cada tarjeta diferencia claramente cobertura planificada de una sede activa.
- Vista accesible alternativa en lista; el mapa nunca es el unico medio de acceso.
- En movil, listado primero y mapa colapsable para reducir costo.

## Experiencia privada

- Selector de clinica visible en el shell administrativo.
- Resultados, metricas, pacientes y documentos limitados al alcance autorizado.
- `SUPER_ADMIN` puede seleccionar vista de red.
- Cambiar clinica invalida filtros y resultados previos para evitar confusion contextual.

## Leaflet y rendimiento

- Carga diferida en `/clinicas`.
- Un solo mapa por pagina y limpieza al desmontar el componente.
- Marcadores agrupados solo cuando existan coordenadas aprobadas.
- No enviar direcciones o coordenadas privadas al frontend.

## Seed conceptual

El seed crea exactamente 18 registros departamentales no publicos o planificados, con coordenadas y datos de contacto nulos. Una importacion administrativa posterior activa y completa cada sede con evidencia aprobada.

## Pruebas requeridas

- Exactamente 18 departamentos unicos.
- Ninguna sede conceptual publica direccion o coordenadas inventadas.
- Filtros y fallback sin JavaScript.
- Autorizacion entre clinicas en el panel.
- Atribucion de tiles y carga responsive.

## Estado

El mapa y el modelo multi-clinica son extensiones planificadas. La lista departamental es una taxonomia geografica, no evidencia de instalaciones existentes.
