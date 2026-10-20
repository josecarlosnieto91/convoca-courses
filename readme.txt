=== Convoca Courses ===
Contributors: josecarlosnieto
Tags: courses, elearning, lessons, certificate, enrollment
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cursos online con módulos, lecciones, matrícula, progreso y certificado verificable.

== Description ==

Convoca Courses convierte cualquier WordPress en una plataforma de cursos sin atarse a un tema ni a
otro plugin: crea cursos con módulos y lecciones, matricula a las personas, guarda su progreso y emite
un certificado verificable por URL.

La única dependencia obligatoria es **Convoca Core**. Si además está activo Convoca Gateway, se puede
cobrar la matrícula; sin él, el curso es gratuito o se cobra fuera y se marca a mano.

**Incluido (gratis)**

* Cursos con módulos y lecciones ordenadas.
* Lecciones de texto, de adjunto y de PDF.
* Matrícula desde un formulario que funciona sin JavaScript (con o sin cobro).
* Progreso por persona y por curso, con porcentaje y desbloqueo secuencial opcional.
* Plazas opcionales con reserva atómica: dos personas no pueden coger la última a la vez.
* Certificado al completar el curso y verificación pública en `/verificar-curso/?codigo=...`.

**Cobro (opcional, con Convoca Gateway)**

* Si Convoca Gateway está activo, el curso con precio crea el pago y manda a la pasarela; la
  matrícula se activa al confirmarse el pago y se queda pendiente si el pago falla.
* Sin gateway, el curso es gratuito o se cobra fuera y se marca a mano.

**PRO**

* Lecciones de vídeo con URL firmada de corta duración contra un almacenamiento compatible con S3
  (Cloudflare R2, Backblaze B2, Wasabi, MinIO...) o con una URL externa.
* El vídeo no se aloja en el plugin: no hay transcodificación, ni DRM, ni bloqueo de descarga.

**Requisitos**

* WordPress 6.4 o superior.
* PHP 8.1 o superior.
* Convoca Core activo.

== Installation ==

1. Sube la carpeta `convoca-courses` a `/wp-content/plugins/` o instala el zip desde *Plugins → Añadir nuevo*.
2. Activa **Convoca Core** y después **Convoca Courses**.
3. Crea un curso en *Cursos → Añadir curso*, asígnale módulos y lecciones.
4. Publica una página con `[convoca_curso id="123"]` para la portada del curso y
   `[convoca_curso_leccion id="456"]` para cada lección.

== Frequently Asked Questions ==

= ¿Necesito Convoca Core? =

Sí. Es la única dependencia obligatoria: aporta utilidades, registro y el circuito de licencias. El
resto de plugins del ecosistema son opcionales.

= ¿Dónde se guarda el progreso? =

En una tabla propia (`{prefijo}convoca_course_progress`) con índice único por persona y lección. No se
usa `postmeta` porque el progreso se consulta constantemente y ahí sería un escaneo.

= ¿Qué pasa si se caduca la licencia PRO? =

No se borra nada. El texto, los adjuntos y los PDF siguen disponibles; el vídeo deja de ofrecerse
pasados 30 días de gracia.

= ¿Se puede usar sin JavaScript? =

Sí. La matrícula y el progreso se envían por `admin-post.php` con nonce y respuesta por redirección.
El JavaScript solo mejora la experiencia.

= ¿Los certificados se pueden falsificar? =

Cada certificado tiene un código único y una página pública de verificación. Un código inventado se
rechaza.

== Screenshots ==

1. Portada del curso con progreso y contenido.
2. Índice de lecciones con lecciones bloqueadas.
3. Certificado en PDF.

== Changelog ==

= 0.1.0 =
* Primera versión: cursos, módulos, lecciones, matrícula, progreso, certificado y vídeo PRO.
