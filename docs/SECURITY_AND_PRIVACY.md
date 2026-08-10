# Seguridad y privacidad

## Alcance y supuestos

Los documentos médicos pueden contener datos personales y de salud de alta sensibilidad. Este documento define controles recomendados, no certifica cumplimiento. No se ha informado país, jurisdicción, responsable de tratamiento, proveedores, contratos ni política de retención; por ello no se afirma cumplimiento con ninguna norma concreta. Se requiere revisión jurídica y de privacidad antes de operar con datos reales.

## Clasificación de datos

- **Restringidos:** documentos originales, imágenes, texto extraído, identificadores de pacientes, hallazgos y decisiones.
- **Confidenciales:** cuentas de personal, asignaciones, auditoría de acceso y configuración operativa.
- **Internos:** métricas agregadas sin identificadores y documentación operativa no sensible.
- **Públicos:** solo contenido aprobado explícitamente para publicación.

La clasificación debe propagarse a respaldos, temporales, entornos de prueba, tickets y exportaciones.

## Modelo de amenazas inicial

Amenazas prioritarias: cuenta comprometida, acceso interno indebido, autorización rota entre expedientes, carga maliciosa, parser vulnerable, fuga por URL/log/cache/respaldo, ransomware, modificación del original, exfiltración por integración y eliminación incompleta. Debe elaborarse un modelado formal por flujo cuando los requisitos estén disponibles.

## Identidad y acceso

- Autenticación robusta y MFA para personal con acceso a datos restringidos.
- Contraseñas mediante el hasher de Laravel con parámetros revisados; nunca cifrado reversible.
- Sesiones seguras, rotación tras autenticación, expiración por inactividad y revocación.
- RBAC combinado con alcance contextual y políticas de Laravel por recurso.
- Denegación por defecto; autorización tanto en UI como en controlador, descarga, trabajo y API.
- Proceso de alta/cambio/baja con desactivación oportuna y revisiones periódicas.
- Acceso de emergencia, si se requiere, con motivo, duración limitada y alerta; no se presume necesario.

## Protección criptográfica

Usar TLS vigente para todo tránsito. Cifrar base, objetos y respaldos en reposo mediante servicios gestionados o controles del sistema. Separar claves de datos, configuración y respaldos; rotarlas con procedimiento probado. Los hashes de integridad no sustituyen cifrado ni firma. Los secretos deben residir en un gestor, no en repositorio, imagen, log o base de datos general.

El cifrado de campos debe decidirse según consultas necesarias y amenaza. Evitar prometer búsqueda segura sin analizar filtraciones de índices, frecuencias y metadatos.

## Carga y entrega de archivos

- Límites de tamaño y tasa antes de aceptar contenido.
- Verificación de tipo por contenido y extensión permitida.
- Nombre generado, almacenamiento privado y cuarentena.
- Antimalware y herramientas de parsing aisladas.
- Prohibir ejecución, inclusión directa y rutas controladas por usuario.
- Descargar mediante controlador o URL firmada de vida corta, siempre después de autorización.
- Cabeceras `Content-Disposition`, `nosniff`, CSP y caché privada/no-store según flujo.
- No colocar documentos bajo `public/` ni enviar rutas internas al cliente.

## Seguridad de aplicación

Mantener protección CSRF para sesiones web, validación centralizada, consultas parametrizadas/Eloquent y escape contextual de salida. Definir CSP estricta y evitar renderizar HTML extraído. Aplicar rate limiting a autenticación, carga, descarga y verificación. Mantener dependencias con revisión de alertas y parches; bloquear versiones reproducibles mediante lockfiles.

Los errores al usuario deben ser genéricos pero correlacionables. Las excepciones y payloads de trabajos pueden incluir datos sensibles; sanitizarlos antes de persistir en `failed_jobs` o monitoreo.

## Aislamiento del procesamiento

PDF y OCR deben ejecutarse como identidad sin privilegios, sin shell construido por concatenación, con red deshabilitada por defecto y límites de recursos. Mantener herramientas actualizadas. Los directorios temporales deben ser exclusivos, con ACL restrictiva y limpieza garantizada. Tratar imágenes y texto producido como sensibles.

## Auditoría

Registrar inicio/cierre de sesión, fallos relevantes, vista/descarga/carga/eliminación, cambio de permisos, ejecución y resolución de verificación, exportación y acceso excepcional. Cada evento incluye actor, acción, recurso opaco, resultado, tiempo y correlación. Evitar contenido médico y credenciales.

La bitácora debe ser append-only para la aplicación, con acceso muy limitado, reloj sincronizado, alertas y retención definida. Auditar a los administradores y revisiones de la propia auditoría.

## Privacidad por diseño

- Recopilar solo campos necesarios para finalidad declarada.
- Documentar finalidad y base legal por tratamiento.
- Separar uso operativo, analítica y desarrollo.
- Usar datos sintéticos para desarrollo y pruebas por defecto.
- Desidentificar muestras, reconociendo riesgo residual y posibilidad de reidentificación.
- Implementar acceso, corrección, exportación o eliminación únicamente conforme a proceso legal validado.
- Evaluar impacto de privacidad antes de incorporar OCR externo, IA o transferencia internacional.

No enviar documentos ni texto a servicios de terceros hasta aprobar proveedor, contrato, ubicación, retención, uso secundario, subencargados y mecanismo de eliminación.

## Logs, caché y notificaciones

No registrar nombres, identificadores, diagnósticos, texto OCR, contenido de formularios ni URLs firmadas. Usar IDs opacos y mensajes estructurados. No almacenar respuestas con datos restringidos en cachés compartidos. Correos y notificaciones deben contener la mínima información y dirigir al usuario autenticado al sistema, no adjuntar documentos salvo requisito y control específicos.

## Entornos y operación

- Separar desarrollo, pruebas y producción, incluidas cuentas, claves y almacenamiento.
- Prohibir copias de producción a entornos inferiores sin proceso aprobado de desidentificación.
- Aplicar mínimos privilegios a base, cola, almacenamiento y CI/CD.
- Respaldos cifrados, inventariados e incluidos en pruebas de restauración.
- Escaneo de dependencias y secretos, pruebas de autorización y revisión de configuración antes de cada versión.
- Administración desde dispositivos y redes controlados según evaluación de riesgo.

## Respuesta a incidentes

Preparar contactos, severidades, contención, preservación de evidencia, análisis, recuperación, comunicación y obligaciones de notificación según jurisdicción. Ensayar pérdida de credencial, exposición de objeto, malware y corrupción. No borrar evidencia durante contención sin aprobación responsable.

## Lista previa a datos reales

- Jurisdicción, roles legales y base de tratamiento aprobados.
- Inventario y diagrama de flujo de datos completados.
- Matriz de acceso y segregación probadas.
- Evaluación de amenazas y privacidad aprobada.
- Retención y eliminación definidas.
- Cifrado, claves, respaldo y restauración probados.
- Auditoría y alertas verificadas.
- Proveedores y transferencias evaluados.
- Plan de incidentes ensayado.
- Pruebas de penetración y corrección de hallazgos de alto riesgo.

Actualmente el scaffold no satisface por sí solo estos requisitos y no debe recibir datos médicos reales.
