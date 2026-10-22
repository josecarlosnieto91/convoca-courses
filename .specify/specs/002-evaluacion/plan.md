# Plan técnico — spec 002 (evaluación)

## Diseño en una frase

Cuatro tablas propias y un puñado de clases con la lógica cara en **funciones puras** (corrección, nota,
precio), para que lo que cuesta dinero se pruebe sin WordPress. El acceso se decide **siempre en servidor**.

## Esquema (F1)

`Installer` gana un constructor de esquema **puro** (`schema()`: array de sentencias `CREATE TABLE`), que usa
`create_tables()` con `dbDelta`. Así el SQL se prueba en unitarias (columnas, índices, tipos) sin base de datos,
y la base de datos se mide aparte contra un WordPress real.

Tablas (prefijo del sitio):

| Tabla | Clave | Índices |
|---|---|---|
| `convoca_course_questions` | `id` | `(course_id, exam_id)`, `(exam_id)` |
| `convoca_course_attempts` | `id` | `UNIQUE (exam_id, user_id, attempt_no)`, `(course_id, user_id)` |
| `convoca_course_answers` | `id` | `UNIQUE (attempt_id, question_id)` |
| `convoca_course_submissions` | `id` | `UNIQUE (task_id, user_id)`, `(course_id, user_id)` |

`CONVOCA_COURSES_DB_VERSION` pasa a `2.0.0`. `maybe_upgrade()` compara versión y aplica una sola vez; la
migración **no destruye** la tabla de progreso.

## Acceso (F2)

- `CPT_Curso::is_sequential()` pasa a `true` por defecto **en cursos nuevos**, escribiendo el metadato al
  crear. Los cursos existentes sin metadato siguen abiertos y el admin lo advierte.
- `Access::can_take_exam( $user_id, $exam_id )`: matriculado + no caducado + modo (`todas|modulo|porcentaje`).
- `Access::is_expired( $enrollment_id )` y `Access::is_enrollment_open( $course_id )`.
- Al vencer: `can_view_lesson()` sigue permitiendo **ver** lo ya completado y las notas; no avanza.

## Corrección (F3, F4)

`Quiz::grade( array $questions, array $answers, float $min_score ): array` — puro:
recibe preguntas y respuestas, devuelve `score`, `passed`, detalle por pregunta y puntos. Reglas: única y V/F
todo o nada; múltiple todo o nada (una de más anula); en blanco 0.

`Quiz::pick( array $question_ids, int $n, bool $shuffle ): array` — selección del intento.

## Notas (F6)

`Gradebook::final( array $pieces, array $weights ): float` — puro. `$pieces` = nota por examen y por tarea;
`$weights` = pesos del curso. Valida que los pesos sumen 100 y reparte proporcionalmente si falta una pieza.

## Precio de socio (F7)

`Pricing::effective( float $general, ?float $socio, float $descuento, bool $gratis, bool $es_socio ): array`
— puro, devuelve `['precio' => float, 'motivo' => string]`. Prioridad: gratis > precio socio > descuento >
general. `Members_Bridge::es_socio_activo( $user_id )` consume el filtro `convoca_es_socio_activo`.

## Pruebas

- **Unitarias** (sin WordPress): esquema, corrección, selección, nota, precio, reglas de acceso puras.
- **Integración con dobles** (lo que ya hay): flujos del módulo sin base de datos.
- **Medición real** (contenedor WordPress + MySQL, como en `enroll`): instalar, actualizar desde 0.2.3,
  comprobar tablas e índices con `SHOW CREATE TABLE`, y ejecutar el flujo completo. El resultado se anota en el
  informe de la fase; el test que toca la base de datos se salta **con motivo** donde no hay WordPress.

## Riesgos

| Riesgo | Mitigación |
|---|---|
| Dos envíos simultáneos crean dos intentos | índice `UNIQUE (exam_id, user_id, attempt_no)` + comprobación en servidor |
| Cambiar el defecto de secuencial encierra alumnos | el cambio solo aplica a cursos nuevos; aviso en el admin |
| Migración que se come datos | `dbDelta` no borra; test de actualización desde 0.2.3 con datos previos |
| `wp_mail` directo se pierde | avisos por la cola del núcleo; si no está, se registra en vez de perder |
