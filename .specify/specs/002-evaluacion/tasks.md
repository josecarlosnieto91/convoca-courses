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

## F2 — Desbloqueo y acceso · CERRADA (22-10-2026)
- [x] Secuencial por defecto en cursos nuevos (`CPT_Curso::is_sequential()` → `true`) y migración que deja
      como estaban los cursos anteriores (`Installer::migrate_sequential_default()`, marcados con `0`).
      Aviso en la caja del curso.
- [x] `Access::can_take_exam()` con `todas|modulo|porcentaje` y `scope_percent()` por módulo: la decisión se
      toma en el servidor (matrícula + caducidad + progreso).
- [x] `Access::is_expired()` / `is_enrollment_open()`; `can_view_lesson()` deja el curso caducado en solo
      lectura de lo ya completado.
- [x] Campos del curso en el panel: fechas de matrícula, fin, días de acceso, nota mínima y modo de acceso.
- [x] 16 pruebas unitarias de las reglas puras (`tests/Unit/AccessRulesTest.php`).
- [x] **Medición real en WordPress** (sonda de 22 comprobaciones, 0 fallos): curso nuevo secuencial; curso
      anterior migrado a `0` y el creado después secuencial; ventana de matrícula; caducidad a los 40 días con
      30 contratados y curso indefinido a los 400; examen cerrado con 0 y 1 de 3 lecciones, abierto en los
      tres modos, cerrado con el curso caducado aunque esté todo hecho; el alumnado no gestiona y el
      administrador sí.
- [x] Defecto encontrado y corregido en la medición: con saneador booleano, `update_post_meta( …, '0' )`
      guardaba cadena vacía y «no secuencial» quedaba indistinguible de «sin configurar»; ahora el meta se
      guarda como `1`/`0` explícitos.

## F3 — Banco de preguntas y examen · HECHA (22-10-2026)
- [x] CPT `convoca_examen` (`includes/CPT_Examen.php`) con la caja de configuración: curso, módulo,
      preguntas por intento, aleatorio, intentos, nota mínima, tiempo máximo, corrección visible y peso.
- [x] `Questions`: alta, edición y borrado con validación por tipo; 12 pruebas de la validación pura.
- [x] `Quiz::grade()` y `Quiz::pick()` puros: única, múltiple (una de más anula), V/F, en blanco, examen
      vacío, ponderación por puntos y mínimo configurable; 12 pruebas.
- [x] El borrado del examen se lleva sus preguntas (`Questions::delete_for_exam`).

## F4 — Intentos · HECHA (22-10-2026)
- [x] `Attempts`: abrir (selección aleatoria del examen + banco del curso), enviar, corregir, cerrar.
      El índice único `intento (exam_id,user_id,intento)` es la red anti-carrera.
- [x] Shortcode `[convoca_curso_examen]` y formulario sin JavaScript (`admin-post` + nonce).
- [x] Tiempo máximo e intentos validados **en el servidor**: envío tardío → `expirado`, sin corregir.
- [x] Negativos medidos en WordPress real: sin matrícula no abre (`convoca_courses_exam_locked`), con
      lecciones sin completar tampoco, agotados los intentos rechaza (`convoca_courses_no_attempts`) y el
      envío tardío queda `expirado` sin corregir. Sonda `sonda-evaluacion.php`, 52 comprobaciones, 0 fallos.

## F5 — Tareas · HECHA (22-10-2026)
- [x] CPT `convoca_tarea` (curso, plazo, peso, admite fichero) y tabla de entregas.
- [x] `Task`: entrega con texto o fichero por la biblioteca de medios, `fuera_de_plazo` por plazo,
      reentrega que reabre y limpia la nota anterior, corrección con nota y comentario.
- [x] Pantalla **Entregas** en el panel (bajo Cursos) con el formulario de corrección por entrega.
- [x] Aviso por la cola del ecosistema (`Convoca\Core\Mailer`); **sin núcleo no se pierde**: queda
      apuntado en `convoca_courses_pending_notices`. Medido: la sonda lee la cola después de corregir.

## F6 — Notas y certificado · HECHA (22-10-2026)
- [x] `Gradebook` con pesos, nota por pieza y nota final **puras** (9 pruebas): las piezas sin corregir no
      hunden la nota, se reparte entre las que hay, y sin nota no se aprueba.
- [x] Vistas `[convoca_curso_notas]` (alumno) y `[convoca_curso_acta]` (profesorado) + descarga CSV.
- [x] `Certificate`: puerta de aprobado cuando el curso evalúa, nota congelada en el PDF y aviso de emisión.
- [x] Negativos medidos: con 64,00 sobre mínimo 50 emite; con el mínimo a 90 **no** emite; curso sin
      evaluación sigue emitiendo por progreso.

## F7 — PRO y precio de socio · HECHA (22-10-2026)
- [x] Filtro `convoca_es_socio_activo` implementado en **`members`** 2.9.18 (`Integracion_Contratos`):
      ficha por `Roles_Miembro::ficha_de_usuario()`, `es_socio()` y estado `activo`.
- [x] `Pricing` (puro, 8 pruebas) con la prioridad gratis > precio socio > descuento > general;
      `Course_Payment` cobra el importe guardado en la matrícula y la matrícula guarda importe y motivo.
- [x] Siete puertas PRO comprobadas en servidor en los dos sentidos: precio de socio, convocatorias,
      recordatorios, tutores, certificado con QR, banco compartido y correo al grupo.
- [x] Cuatro importes medidos: no socio 100; socio con descuento 75; socio con precio 40; socio gratis 0.
      Sin el contrato de socios: 100 y sin fatal.
- [x] Defecto encontrado y corregido en la medición: `License::has_pro()` **no aplicaba** el filtro
      `convoca_courses_has_pro` cuando el núcleo no está, así que no había forma de probar el PRO en un
      sitio mínimo. Ahora el filtro manda siempre.

## F8 — Cierre
- [ ] 0.3.0, CHANGELOG, tag `v0.3.0`, `.pot` regenerado y guardián en verde.
- [ ] Documentación en `convoca-docs`, publicada y verificada.
- [ ] Informe por fase con su negativo y su número.

## Medición real (22-10-2026)

`sonda-evaluacion.php` en WordPress 7.1.3 + MariaDB 11 con el plugin cargado: **52 comprobaciones, 0 fallos**.
Cubre los criterios 1 y 3-10 de la spec, incluidos los cuatro importes de socio y las siete puertas PRO en
los dos sentidos. Salida: `~/.hermes/cache/scratch/courses-evaluacion/medicion-f3-f7.txt`.
