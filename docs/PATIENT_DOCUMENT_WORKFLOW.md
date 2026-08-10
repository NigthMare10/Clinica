# Flujo de paciente y expediente documental

## Alcance

El expediente inicial es documental, no una historia clinica electronica completa. Reune documentos autorizados del paciente y sus eventos de ciclo de vida sin crear consultas, diagnosticos o notas que no existan en el sistema.

## Identidad y acceso

- `Patient` representa una identidad de red.
- `patient_clinic` asocia al paciente con una o mas clinicas y puede contener numero de expediente local.
- La coincidencia nunca se realiza solo por nombre u OCR.
- `SUPER_ADMIN` puede operar a nivel de red; los demas usuarios requieren membresia activa de clinica.
- La politica del paciente limita tanto el perfil como cada documento de la linea de tiempo.
- El frontend recibe DTO explicitos; no se serializan modelos sensibles completos.

## Flujo de alta

1. El operador selecciona la clinica activa.
2. Busca una identidad existente con los identificadores permitidos.
3. Si existe, crea solamente la asociacion clinica tras verificarla.
4. Si no existe, registra el paciente con los datos minimos autorizados.
5. Se asigna el numero de expediente local si la institucion lo provee.
6. Se registra auditoria sin copiar valores clinicos innecesarios.

## Perfil autorizado

El perfil muestra:

- identidad minima necesaria;
- clinicas visibles para el usuario;
- numero de expediente por clinica;
- conteos por estado documental;
- documentos paginados por fecha clinica y fecha de emision;
- relacion de revocacion, reemplazo y reemision;
- acciones permitidas por politica.

No muestra por defecto:

- texto OCR completo;
- secretos, token hash o paths privados;
- diagnosticos en buscadores o vistas agregadas;
- datos de clinicas fuera del alcance del usuario.

## Linea de tiempo

La primera version se deriva de `medical_documents` y `document_audit_logs` autorizados. No se crea una tabla generica de eventos hasta que existan eventos clinicos no documentales aprobados.

Orden recomendado:

1. `consultation_date` descendente.
2. `issued_at` descendente.
3. `created_at` descendente.

Eventos visibles: carga, revision, aprobacion, emision, revocacion y reemplazo. Los cambios de campos sensibles se resumen sin exponer valores anteriores o nuevos.

## Carga documental

1. Seleccionar paciente, clinica, medico, tipo y plantilla compatible.
2. Validar PDF y almacenar original privado e inmutable.
3. Calcular SHA-256 y crear version original.
4. Procesar texto digital u OCR en cola.
5. Presentar candidatos y conflictos al revisor.
6. Confirmar identidad del paciente y profesional.
7. Alcanzar `READY` solo sin bloqueos.
8. Emitir mediante el servicio existente, estampar QR y comprobar lectura.

## Privacidad

- Respuestas privadas con `no-store` y `noindex`.
- Descargas siempre pasan por politica y almacenamiento privado.
- El buscador no indexa OCR, diagnostico, sintomas ni valores de auditoria.
- Las consultas de busqueda no deben registrarse en logs de aplicacion.
- Temporales de PDF y OCR se eliminan recursivamente al finalizar.

## Pruebas requeridas

- Negacion entre clinicas.
- Paciente compartido visible solo dentro del alcance autorizado.
- Orden y paginacion de linea de tiempo.
- Descarga privada y rol medico.
- Busqueda global sin filtraciones.
- Asociacion paciente/documento consistente con datos confirmados.

## Estado

La relacion paciente-documento ya existe. La asociacion multi-clinica, el perfil y la linea de tiempo son extensiones planificadas y deben introducirse mediante migracion hacia adelante.
