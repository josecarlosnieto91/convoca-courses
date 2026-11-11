# Convoca Courses PRO — registro, SCORM, informes y encuesta

**Versión:** v0.1 · 2026-11-12
**Estado:** decisiones de producto cerradas; en construcción.
**Base:** `convoca-courses` 0.24.6 · 819 tests / 2.068 aserciones · CI y Cobertura verdes.

## Objetivo

Añadir cuatro funciones de pago al módulo, detrás de la licencia PRO existente
(`License::has_pro()`), sin degradar el producto gratuito:

1. Registro inalterable de conexiones.
2. Soporte SCORM 1.2 y SCORM 2004 4ª edición, con paquete subido y servido desde WordPress.
3. Informe por alumno en PDF y CSV.
4. Encuesta de calidad por curso, vinculada al alumno.

## Decisiones cerradas (JC, 2026-11-12)

- SCORM 1.2 + SCORM 2004 4ª ed.; paquete subido y servido desde WordPress.
- Registro: accesos, cierres, entregas, exámenes y SCORM; IP + user-agent; cadena HMAC;
  retención configurable, **365 días** por defecto.
- Informe: **PDF + CSV**, con progreso, notas, tiempo, conexiones, SCORM y encuesta.
- Encuesta: al completar el curso, **vinculada al alumno**, Likert 1–5 + comentario,
  configurable por curso.
- Las cuatro funciones van detrás del **PRO actual**; no hay add-on ni licencia nueva.

## Arquitectura

### 1. `Access_Log` — registro inalterable

Tabla `{prefijo}convoca_course_access_log`:

```
id, created_utc, event, user_id, identidad_id, course_id, lesson_id, object_id,
ip, user_agent, session_id, duration, data, prev_hash, hash
```

- `hash = HMAC-SHA256(secreto, campos canónicos + prev_hash)`.
- Secreto: `Profesorado::secreto_publico()`; nunca se guarda en la fila.
- Inserción bajo `GET_LOCK('convoca_courses_access_log', 5)` para que dos peticiones no
  lean el mismo `prev_hash`.
- `Access_Log::verify()` recorre la cadena y detecta cualquier fila alterada.
- Purga diaria por retención; antes de borrar guarda un ancla (`id`, `hash`) para que la
  cadena siga siendo verificable desde la primera fila conservada.
- Eventos: `login`, `logout`, `acceso_curso`, `acceso_leccion`, `entrega`, `examen`,
  `scorm_launch`, `scorm_commit`, `scorm_complete`.
- No rompe la petición si falla la escritura: se registra y se sigue.

### 2. `SCORM` — paquete y runtime

- Un paquete por lección, en `uploads/convoca-courses/scorm/{hash}/`.
- Metadatos en la lección: `_convoca_scorm_paquete` (dir, entry, version, title, hash).
- Subida desde *Cursos → SCORM*: curso, lección y ZIP; nonce, capability PRO.
- Validación del ZIP: `imsmanifest.xml`, entrada `resource@href`, sin extensiones
  ejecutables (`.php`, `.phtml`, `.phar`, `.cgi`, `.pl`, `.py`, `.sh`).
- Runtime propio:
  - `GET /?convoca_scorm={lesson_id}` sirve el player HTML.
  - API SCORM 1.2 (`window.API`) y 2004 (`window.API_1484_11`) en el padre.
  - El iframe apunta al `entry` del paquete, mismo origen.
  - Cada `SetValue`/`Commit` guarda estado por `(lesson_id, user_id, identidad_id)`.
- Tabla `{prefijo}convoca_course_scorm_state`:
  `lesson_status`, `score_raw`, `score_max`, `score_min`, `session_time`,
  `suspend_data`, `location`, `updated_utc`.
- `lesson_status` `completed`/`passed` marca la lección completada y registra el evento.
- El paquete no se sirve a quien no tiene acceso a la lección.

### 3. `Reports` — informe por alumno

`Reports::student($course_id, $user_id, $identidad_id)` devuelve:

- identidad, matrícula y estado;
- progreso y lecciones;
- notas (`Gradebook`);
- conexiones (`Access_Log::summary_for()`), última conexión y tiempo registrado;
- estados SCORM por lección;
- respuestas de la encuesta.

Salidas:

- CSV individual y CSV de curso (`Reports::csv()`).
- PDF A4 con la plantilla `informes/informe-alumno.php` (Dompdf, ya empaquetado).
- Pantalla *Cursos → Informes* con filtro por curso y descargas.

### 4. `Survey` — encuesta de calidad

- Metadatos del curso: `_convoca_encuesta` (`enabled`, `titulo`, `preguntas`,
  `comentario`, `obligatoria`).
- Preguntas por defecto (Likert 1–5) si no se configuran; texto editable una por línea.
- Tabla `{prefijo}convoca_course_surveys`:
  `course_id, user_id, identidad_id, answers, comentario, created_utc, updated_utc`,
  única por curso y persona.
- Se muestra al completar el curso (100 % de progreso) en la ficha y con shortcode
  `[convoca_encuesta_curso id="…"]`.
- `Survey::results($course_id)` calcula media por pregunta, participación y comentarios.

## Fases

1. **Esquema y registro**: tablas, `Access_Log`, hooks, retención, pantalla de registro.
2. **SCORM**: subida, parser, player, estado, access checks, pantalla.
3. **Encuesta**: metadatos, formulario, guardado, resultados.
4. **Informes**: agregación, CSV, PDF, pantalla.
5. **Cierre**: tests, PHPCS/PHPStan, versión 0.25.0, release y despliegue en los tres sitios.

## Verificación

- `make test` (PHPCS + PHPStan + PHPUnit + integración) verde.
- Pruebas nuevas:
  - hash chain: alterar una fila invalida `verify()`;
  - retención: purga y ancla;
  - parser SCORM 1.2 y 2004 con manifiestos de fixture;
  - ZIP con `.php` rechazado;
  - encuesta: respuestas 1–5, única por alumno, media;
  - informe: CSV con columnas y PDF no vacío.
- Prueba en vivo en `convoca-dev`: subir un ZIP SCORM de prueba, lanzar, completar,
  ver informe y encuesta; borrar los datos de prueba.
- No se toca producción hasta que CI/Cobertura estén verdes y JC lo pida.

## Riesgos

- **SCORM es un estándar laxo**: muchos paquetes usan rutas relativas y APIs con
  comportamientos distintos. El player cubre 1.2 y 2004; se documenta lo no soportado.
- **Paquetes ZIP**: se rechazan extensiones ejecutables y se sirven solo desde uploads.
- **Datos personales**: IP/UA en el registro; retención y acceso restringido a
  `convoca_manage_courses`. La encuesta es identificada por decisión de producto.
- **Dompdf** puede no estar disponible en una instalación mínima: el PDF degrada a error
  claro y el CSV sigue funcionando.
