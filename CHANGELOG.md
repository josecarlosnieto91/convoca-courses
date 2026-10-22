# Changelog

Todas las versiones de Convoca Courses. El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el proyecto usa [Versionado Semántico](https://semver.org/lang/es/).

## [0.3.0] — 2026-10-22

La evaluación, que es lo que convierte el módulo en un curso de verdad. Todo lo que sigue es **gratis**;
lo PRO es la capa de organización.

### Añadido

- **Exámenes tipo test.** CPT `convoca_examen` con curso y módulo, preguntas por intento, aleatorio,
  intentos, nota mínima, tiempo máximo, corrección visible y peso. Banco de preguntas en tabla propia con
  tres tipos: única, múltiple (una de más anula) y verdadero/falso.
- **Intentos con corrección en servidor.** El tiempo y el número de intentos no se negocian en el
  navegador: enviar tarde deja el intento `expirado` sin corregir, y el índice único
  `(exam_id, user_id, intento)` impide que dos envíos simultáneos creen dos intentos.
- **Tareas con entrega.** Texto o fichero por la biblioteca de medios, plazo, estados `entregada`,
  `fuera_de_plazo` y `corregida`, reentrega que reabre y limpia la nota anterior, y pantalla **Entregas**
  en el panel para corregir con nota y comentario.
- **Libro de notas.** Nota final con pesos por examen y por tarea (avisa si no suman 100), vista del
  alumno, acta del profesorado y **exportación CSV**.
- **Certificado con nota y puerta de aprobado.** Si el curso evalúa, el certificado exige aprobado y la
  nota se congela en el PDF. Curso sin evaluación: igual que antes.
- **Precio distinto para socios** (PRO): gratis, precio especial o descuento %, por curso, con la
  prioridad gratis > precio socio > descuento > general. La matrícula guarda **importe y motivo**.
- **Capa PRO de organización**: convocatorias con plazas y fechas, recordatorios automáticos de
  inactividad y examen pendiente, varios correctores (capability `convoca_grade_courses`), certificado con
  QR generado en el sitio, banco de preguntas compartido con importación/exportación CSV y correo al grupo
  de alumnado.
- Contrato `convoca_es_socio_activo` contestado por **Convoca Members** 2.9.18.

### Corregido

- `License::has_pro()` no aplicaba el filtro `convoca_courses_has_pro` cuando el núcleo no está: el PRO no
  se podía forzar en un sitio mínimo ni probar en los dos sentidos. Ahora el filtro manda siempre.
- `Access::can_submit_task()`: las tareas no se bloquean por progreso, solo por matrícula y caducidad.

### Notas

- 155 pruebas unitarias y 10 de integración; medición en WordPress real: 52 comprobaciones, 0 fallos.

## [0.2.3] — 2026-10-21

### Arreglado

- **Los oyentes del cobro usaban los nombres de gancho antiguos** (`convoca_payment_completed` y
  `convoca_payment_failed`). El gateway los sigue disparando por compatibilidad, así que **cada pago
  soltaba un aviso de hook deprecado**. Ahora escucha `convoca_gateway_payment_completed` y
  `convoca_gateway_payment_failed`, que es lo que dispara el gateway.

## [0.2.2] — 2026-10-20

### Añadido

- **Traducción completa al inglés (`en_US`)**: 124 cadenas, con `.po` y `.mo` versionados y
  compilados con `msgfmt -c`. Sin esto el módulo se vende en un directorio internacional con la
  interfaz solo en castellano. Quedan los textos de la interfaz; el contenido de los cursos lo pone
  cada sitio.

## [0.2.1] — 2026-10-20

### Añadido

- Enlace **«Ver el recibo del pago»** en la portada del curso cuando la matrícula está pagada. El
  recibo se lee por la fachada del núcleo (`Convoca\Core\Payments::receipt_url()`), así que el módulo
  no depende del generador de recibos del gateway.
- Dos pruebas de integración más del recibo (sale de la fachada; sin pago no se consulta).

## [0.2.0] — 2026-10-20

### Añadido

- **Cobro de la matrícula con Convoca Gateway** (opcional): con precio > 0 y el gateway activo, la
  matrícula nace `pendiente`, se crea el pago con origen `courses` y el formulario redirige a la
  pasarela. El pago confirmado activa la matrícula (`convoca_payment_completed`); el fallido la deja
  pendiente y anotado en el registro.
- Aviso en la portada del curso con el estado del pago y enlace para completarlo si quedó a medias.
- Estado de cobro en la columna de la lista de matrículas (`pendiente · pago #123`).
- Cuatro pruebas unitarias nuevas de la decisión de cobro y del redondeo a céntimos.

### Cambiado

- Sin gateway activo, el comportamiento es el de siempre: matrícula gratuita o cobrada fuera y marcada
  a mano.

## [0.1.2] — 2026-10-20

### Corregido

- Modo oscuro: el componente declara su propia paleta y, cuando el sitio está en oscuro, cambia los
  valores. Antes mezclaba tarjetas blancas con textos claros sobre fondo oscuro (contraste medido de
  1,13:1 en las etiquetas).
- El título del módulo sube de especificidad para que el tema no lo deje en naranja sobre blanco
  (1,9:1).

## [0.1.1] — 2026-10-20

### Corregido

- El CSS del componente fija su propio color en cada elemento de texto: el tema del sitio pintaba los
  `<li>` y los enlaces por su cuenta y dejaba el título de la lección en gris claro sobre blanco y el
  texto del botón en naranja claro sobre naranja.
- El botón sube de especificidad (clase repetida, sin `!important`) para que el tema no le cambie el
  color, y se añade `:focus-visible` con contorno visible.
- Los stubs de PHPStan dejan de viajar en el paquete de distribución.

## [No publicado]

## [0.1.0] — 2026-10-20

### Añadido

- Tipos de contenido `convoca_curso`, `convoca_modulo`, `convoca_leccion` y `convoca_matricula`.
- Lecciones de texto, adjunto y PDF.
- Matrícula por formulario sin JavaScript (`admin-post.php` + nonce + redirección) con estados,
  cancelación y plazas opcionales con reserva atómica.
- Progreso por persona y curso en tabla propia (`{prefijo}convoca_course_progress`) con índice único.
- Desbloqueo secuencial opcional por curso.
- Certificado al 100 % con numeración propia, PDF con dompdf y verificación pública por URL.
- Lecciones de vídeo (PRO) con URL firmada AWS V4 contra almacenamiento compatible con S3, o URL externa.
- Ajustes de vídeo con el secreto guardado cifrado.
- Shortcodes `[convoca_curso]`, `[convoca_curso_lecciones]` y `[convoca_curso_leccion]`.
- `uninstall.php` que respeta la opción de conservar datos.

[No publicado]: https://github.com/josecarlosnieto91/convoca-courses/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/josecarlosnieto91/convoca-courses/releases/tag/v0.2.0
[0.1.1]: https://github.com/josecarlosnieto91/convoca-courses/releases/tag/v0.1.1
[0.1.0]: https://github.com/josecarlosnieto91/convoca-courses/releases/tag/v0.1.0
