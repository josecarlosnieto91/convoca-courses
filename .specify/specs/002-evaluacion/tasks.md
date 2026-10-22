# Tareas — spec 002 (evaluación)

Marcar solo lo que esté **demostrado** con un comando o una medición.

## F0 — Spec y línea base
- [x] `spec.md`, `plan.md`, `tasks.md` en `.specify/specs/002-evaluacion/`.
- [x] Línea base medida: 0.2.3, 54+10 tests, cobertura CI 11,2 % (suelo 9,1 %), CI y Cobertura en verde.

## F1 — Esquema y migración · CERRADA (22-10-2026)
- [x] `Installer::schema()` puro con las cuatro tablas nuevas (sentencias `CREATE TABLE`).
- [x] `CONVOCA_COURSES_DB_VERSION` a `2.0.0` en código, bootstraps de pruebas y `phpstan-bootstrap.php`.
- [x] `maybe_upgrade()` idempotente: aplica una vez, no repite, no toca la tabla de progreso.
- [x] Tests unitarios del esquema (tablas, columnas, índices, tipos) y de `needs_upgrade()`:
      `tests/Unit/InstallerSchemaTest.php`, 11 tests / 64 aserciones.
- [x] Medición real en WordPress 7.1.3 + MariaDB 11 (contenedor desechable): las cinco tablas creadas por
      `dbDelta`, columnas exactas, índice único `intento (exam_id,user_id,intento)`; actualización desde 0.2.3
      con progreso previo: **2 filas intactas**, opción a `2.0.0` y segunda pasada idempotente.
      **17 comprobaciones, 0 fallos.**
- [x] Negativo: quitando la columna `correctas` del esquema, el test cae («Falta la columna correctas»).

## F2 — Desbloqueo y acceso
- [ ] Secuencial por defecto en cursos nuevos + aviso en el admin.
- [ ] `Access::can_take_exam()` con `todas|modulo|porcentaje`, comprobado en servidor.
- [ ] `is_expired()` / `is_enrollment_open()`; solo lectura al vencer.
- [ ] Negativos: examen bloqueado no se fuerza por URL; curso caducado no avanza; matrícula fuera de ventana.

## F3 — Banco de preguntas y examen
- [ ] CPT `convoca_examen` + metabox de configuración y de preguntas.
- [ ] `Questions`: alta/edición/borrado con validación por tipo.
- [ ] `Quiz::grade()` y `Quiz::pick()` puros, con tests de los tres tipos y de los casos límite.

## F4 — Intentos
- [ ] Shortcode `[convoca_curso_examen]` + `Handlers` (abrir, enviar, nonce).
- [ ] Tiempo máximo validado en servidor; intentos limitados; índice único anti-carrera.
- [ ] Negativos: sin matrícula no abre; agotados los intentos, rechaza.

## F5 — Tareas
- [ ] CPT `convoca_tarea`, entrega de texto o fichero, plazo y estados.
- [ ] Corrección en el admin con nota y comentario; reapertura.
- [ ] Aviso por la cola de correo del núcleo, verificado leyendo la cola.

## F6 — Notas y certificado
- [ ] `Gradebook` (pesos, nota por pieza, nota final, aprobado) con tests puros.
- [ ] Vistas alumno/profesor y exportación CSV.
- [ ] `Certificate`: puerta de aprobado, nota en el PDF y retrocompatibilidad.
- [ ] Negativo: por debajo del mínimo no emite.

## F7 — PRO y precio de socio
- [ ] Filtro `convoca_es_socio_activo` en `members` (`Integracion_Contratos`).
- [ ] `Pricing` en `courses` + `Course_Payment` usando el precio efectivo y guardando el motivo.
- [ ] Puertas de licencia para las siete funciones PRO, probadas en los dos sentidos.
- [ ] Cuatro casos de precio medidos con importe real.

## F8 — Cierre
- [ ] 0.3.0, CHANGELOG, tag `v0.3.0`, `.pot` regenerado y guardián en verde.
- [ ] Documentación en `convoca-docs`, publicada y verificada.
- [ ] Informe por fase con su negativo y su número.
