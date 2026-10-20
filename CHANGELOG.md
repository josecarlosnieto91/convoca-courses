# Changelog

Todas las versiones de Convoca Courses. El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el proyecto usa [Versionado Semántico](https://semver.org/lang/es/).

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
