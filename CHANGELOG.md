# Changelog

Todas las versiones de Convoca Courses. El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el proyecto usa [Versionado Semántico](https://semver.org/lang/es/).

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

[No publicado]: https://github.com/josecarlosnieto91/convoca-courses/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/josecarlosnieto91/convoca-courses/releases/tag/v0.1.0
