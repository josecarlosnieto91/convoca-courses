## [0.18.2] — 2026-10-29

### Cambiado

- **oyente de la devolucion del gateway**

## [0.18.1] — 2026-10-29

### Corregido

- **el desistimiento no cortaba el acceso del alumnado sin cuenta**: `Enrollment::find_por_identidad()`
  devolvía la matrícula aunque estuviera `cancelada`, así que el curso y el examen seguían abiertos después
  de desistir. Ahora acepta `$solo_con_acceso` y la puerta del curso, el examen y el certificado lo exigen.
  Medido en la demo: sin el arreglo, `con_acceso_despues=3476`; con él, `0`.

## [0.18.0] — 2026-10-29

### Añadido

- **desistimiento sin cuenta**: enlace firmado en el correo de confirmación, con caducidad y de un solo uso
  efectivo (al cancelar la matrícula deja de estar disponible). Página `[convoca_desistir]`, creada al instalar.
- **el acceso se corta al pedir el desistimiento**, no al ejecutar la devolución: la matrícula pasa a
  `cancelada` en el mismo acto, que es lo que consulta la puerta de acceso.
- **prueba archivada**: fecha, IP, navegador y el texto declarado, en `_convoca_desistimiento_prueba`.
- **avisos**: a la entidad (para que devuelva) y al alumno (justificante de la solicitud).
- **no se ofrece a quien renunció al 103.m**: si consta la renuncia, la matrícula no admite desistimiento.

La devolución del dinero **no** se hace aquí: se ejecuta en el gateway (`convoca-gateway#13`).

## [0.17.0] — 2026-10-29

### Añadido

- **factura en la matrícula**: casilla opcional «Necesito factura» con NIF, razón social y dirección fiscal. Si
  se marca, el servidor exige NIF y razón social, y el pago los lleva (`payer_nif`, `payer_business`,
  `payer_address`) para que el recibo los imprima.
- **el pagador sale de la identidad** cuando no hay usuario de WordPress: sin esto el recibo se emitía sin
  destinatario en el recorrido sin cuenta.
- **el recibo va en el correo de confirmación**, así se descarga sin entrar en WordPress.
- **mayores de 14 años**: casilla obligatoria en la matrícula, con constancia en `_convoca_mayor_14`.
- **datos estructurados `Course`** (JSON-LD): nombre, descripción, entidad con su NIF, precio y moneda.
- **`Notifications::send_to_identidad()`**: los avisos al alumnado sin cuenta ya no necesitan `user_id`.

## [0.16.2] — 2026-10-29

### Corregido

- **la confirmación del art. 98.7 ya no sale por `wp_mail()` directo**: va por `Convoca\Core\Mailer`, con la
  identidad visual y el remitente del núcleo, la copia a la asociación y el registro. Si el núcleo no está o el
  envío falla, **el aviso no se pierde**: queda apuntado con su correo para reenviarlo, y `confirmacion_enviada`
  no se marca. El enlace de acceso del alumnado (`Identidad_Acceso::enviar()`) pasa por el mismo camino.
- Un aviso que no se ha podido enviar a alguien **sin cuenta** ya se puede apuntar: `record_pending_email()`
  guarda el correo, no un `user_id` que no existe.

### Añadido

- **texto de fábrica de la renuncia con versión** (`Condiciones::TEXTO_RENUNCIA_VERSION`, 2026-10-29), revisado
  contra el TRLGDCU. Ya no nombra a una entidad concreta: usa la de *Ajustes → Entidad* o «la entidad».
- La prueba registra la **versión del texto** que se mostró; si la entidad lo cambia, pasa a `propia-<fecha>`.
- **Aviso en Ajustes**: el texto de fábrica está revisado y, si se cambia, el redactado pasa a ser
  responsabilidad de la entidad, con la fecha del último cambio.

## [0.16.1] — 2026-10-29

### Cambiado

- La ficha del curso ofrece **matrícula sin cuenta**: en lugar de «Inicia sesión para matricularte», pinta el
  formulario de matrícula (correo y nombre, y el pago si es de pago) y deja el enlace de sesión como salida
  para quien ya tenga cuenta. Sin esto, la casilla de renuncia no llegaba a verse nunca en producción.

## [0.16.0] — 2026-10-29

### Añadido

- **la prueba del consentimiento se guarda aparte**, en su propia tabla (`convoca_consent_pruebas`): sobrevive
  al borrado de la matrícula y se conserva **cinco años** desde la aceptación (art. 1964.2 del Código Civil;
  art. 6.1.f y 17.3.e del RGPD). Una tarea diaria purga lo que vence.
- **confirmación en soporte duradero** (art. 98.7 TRLGDCU): al matricularse sale un correo con el curso, el
  importe, el texto literal aceptado y cómo desistir. Es la tercera condición del art. 103.m: sin esa
  confirmación, la renuncia no opera aunque la casilla esté marcada.
- **registro propio de certificados** (`convoca_certificados`): la validación por código ya no depende de la
  matrícula, así que un certificado sigue siendo válido aunque la persona se dé de baja. Los certificados no
  se revocan.
- texto de la casilla revisado: cita el art. 103.m, acota la pérdida al contenido digital, declara la casilla
  opcional, nombra la confirmación por correo y el registro de la aceptación.

### Cambiado

- `Condiciones::registrar_aceptacion()` recibe curso y correo y devuelve el identificador de la prueba archivada.
- `Certificate::data()` lee primero del registro propio y usa la matrícula solo como respaldo para los
  certificados emitidos antes de esta versión.

## [0.15.0] — 2026-10-29

### Añadido

- **prueba del consentimiento** (art. 103.m TRLGDCU): al matricularse se archiva el texto exacto del
  consentimiento (`_convoca_consentimiento_texto`) y, si se marcó la renuncia, el texto literal de la casilla
  con su huella `sha256`, la fecha en ISO-8601, la IP, el navegador (255 caracteres), el ámbito
  (`contenido_digital`) y la versión del plugin en `_convoca_renuncia_prueba`. Antes solo se guardaba la fecha
  y un `1`, así que no se podía demostrar qué se leyó.
- **texto de la casilla editable** en *Cursos → Ajustes* (`convoca_courses_renuncia_texto`), con redacción de
  fábrica revisada: pierde el derecho al consumir, solo contenido digital, casilla opcional y alternativa real.
- **ámbito por curso**: nueva casilla «Curso 100 % digital» en la ficha del curso, apagada por defecto. La
  renuncia solo se ofrece cuando la instalación la activa **y** el curso es digital; en cursos con directos,
  tutorías o material físico la casilla no aparece, y una renuncia enviada a mano se descarta. La excepción no
  cubre esa parte, que sigue siendo reembolsable.
- 17 pruebas nuevas entre `CondicionesPruebaTest` y `RenunciaAmbitoTest`.

## [0.14.0] — 2026-10-29

### Añadido

- **casilla de consentimiento en la matrícula**: obligatoria, con texto editable por instalación. Sin marcarla
  no hay matrícula, y se guarda la fecha en `_convoca_consentimiento`.
- **renuncia expresa al desistimiento**, apagada por defecto: si la entidad la activa, aparece como casilla
  **separada** y se guarda `_convoca_renuncia_desistimiento`. Es el consentimiento previo y expreso que exige
  el art. 103.m; sin él, los 14 días se respetan completos.
- Documentación del recorrido sin cuenta en `docs/alumnado-sin-cuenta.md` y los shortcodes nuevos en el README.

## [0.13.1] — 2026-10-29

### Corregido

- **`Attempts::decode()` no devolvía `identidad_id`**, así que al enviar un examen sin cuenta el intento
  parecía ajeno («Este intento no es tuyo»). Lo destapó la prueba de extremo a extremo.

## [0.13.0] — 2026-10-29

### Añadido

- **examen por identidad**: `Attempts::open()`, `submit()`, `current()`, `count_for()` y `best_by_exam()` aceptan
  `$identidad_id`. La puerta del examen para quien no tiene cuenta es tener matrícula en el curso.
- **certificado por identidad**: `Certificate::maybe_issue()` y `data()` resuelven el nombre y el progreso por
  identidad, y la emisión al aprobar sale del intento, no del usuario. `Progress::for_course_identidad()` y
  `Gradebook::for_user( ..., $identidad_id )`.

### Nota

- Todo es aditivo: sin `$identidad_id` el comportamiento es exactamente el de antes, así que las matrículas con
  usuario de WordPress no cambian.

## [0.12.2] — 2026-10-29

### Corregido

- **al matricularse se envía el acceso**. La prueba de extremo a extremo lo destapó: el alumno quedaba
  matriculado y sin recibir nada, y tenía que descubrir por su cuenta que había un formulario para pedir el
  enlace. Ahora `[convoca_matricula]` emite y envía el enlace y el código en el mismo paso.

## [0.12.1] — 2026-10-29

### Corregido

- **la migración de índices no borraba el índice viejo**. `dbDelta` crea el índice nuevo antes de que corra la
  migración, y la comprobación daba por buena la migración con los dos índices en pie. Con
  `user_lesson (user_id, lesson_id)` todavía activo, la segunda identidad que completara la misma lección habría
  chocado contra `user_id = 0`: justo el problema que la migración venía a resolver.
- Ahora el índice viejo se borra siempre que exista y el nuevo se añade solo si falta. Se fuerza la
  re-ejecución subiendo la versión de esquema a 2.2.1.

## [0.12.0] — 2026-10-29

### Añadido

- **progreso por identidad**: `Progress::complete_identidad()`, `is_completed_identidad()` y
  `completed_lesson_ids_identidad()`. El alumnado sin cuenta de WordPress ya no depende de `user_id`.

### Cambiado (esquema)

- `convoca_course_progress` y `convoca_course_attempts` ganan `identidad_id`.
- Los índices únicos pasan a `quien_lesson (user_id, identidad_id, lesson_id)` e
  `intento_identidad (exam_id, user_id, identidad_id, intento)`. Sin eso, todas las identidades comparten
  `user_id = 0` y la segunda no puede completar la misma lección ni repetir un examen.
- La migración es explícita (`Installer::migrar_indices_identidad()`): `dbDelta` añade columnas pero no cambia
  índices. Se ejecuta sola al actualizar y deja `identidad_id = 0` en todo lo existente.

## [0.11.0] — 2026-10-29

### Añadido

- **matrícula sin cuenta de WordPress**: `[convoca_matricula course="123"]` pide correo y nombre, crea la
  identidad y matricula. La matrícula guarda `_convoca_identidad_id`. No se crea ningún `WP_User`.
- Al **confirmar el pago**, la identidad queda marcada como verificada: pagar ya demuestra que el correo es
  bueno y no hay que pedir nada más.

### Nota

- Las matrículas anteriores siguen con su usuario de WordPress y no se migran. `Enrollment::enroll()` acepta
  `user_id = 0` solo cuando llega una identidad, así que ninguna llamada antigua cambia de comportamiento.

## [0.10.0] — 2026-10-29

### Añadido

- **identidad propia del alumnado, sin usuario de WordPress**: tabla `convoca_identidades` con el correo como
  clave, alta al confirmar el pago, enlace de un solo uso y **código de 6 cifras** como respaldo. El acceso se
  pide con `[convoca_acceso]`. No se crea ningún `WP_User` ni se toca `wp_users`.
- **texto de las condiciones editable por instalación**: en Ajustes se puede escribir el texto propio, que
  sustituye al de fábrica; los marcadores `{{nombre}}`, `{{nif}}`, `{{email_desistimiento}}`, `{{direccion}}` y
  `{{telefono}}` se siguen rellenando solos.

### Nota

- Las matrículas anteriores siguen con su usuario de WordPress: **no se migran** (decisión de JC del 29/10).
  La identidad propia atiende solo a las nuevas.

## [0.9.0] — 2026-10-29

### Añadido

- **plantilla propia de la ficha del curso**, apagada por defecto. Un tema puede seguir pintando el curso como
  hasta ahora; cuando lo pinta como una entrada cualquiera —sin matrícula, sin programa y sin botón de
  empezar—, se activa en **Cursos → Ajustes → Ficha del curso** y el plugin usa la suya. El cuerpo lo sigue
  pintando `[convoca_curso]`, así que no hay dos versiones de la misma lógica.
- El tema puede sustituirla con `convoca-curso.php` o `single-convoca_curso.php`.

## [0.8.0] — 2026-10-29

### Añadido

- **tareas tipo test con nota automática**. En el editor de la tarea hay un selector «Examen que la corrige»:
  si se elige uno, la nota de la tarea sale del examen —nadie corrige a mano— y entra en el acta y en la nota
  final con el peso que ya tuviera la tarea. Sin examen sigue funcionando la corrección manual, así que las
  tareas antiguas no cambian.

## [0.7.0] — 2026-10-29

### Añadido

- **datos de la entidad en Ajustes**: nombre o razón social, NIF/CIF, correo de desistimiento, dirección y
  teléfono. Los rellena **cada organización que instale el plugin**; no hay ni un dato escrito en el código.
- **`[convoca_condiciones]`**: publica las condiciones de venta con esos datos ya puestos. Mientras falte el
  nombre, el NIF o el correo de desistimiento, no se publica nada a los visitantes y el administrador ve el
  aviso con lo que falta.
- Plantilla en `templates/condiciones.md` con los marcadores `{{nombre}}`, `{{nif}}`,
  `{{email_desistimiento}}`, `{{direccion}}` y `{{telefono}}`.

## [0.6.2] — 2026-10-29

### Añadido

- **el recibo del pago lleva el nombre de quien paga**. Courses engancha
  `convoca_gateway_receipt_payer_name` y lo resuelve desde la matrícula: primero la ficha de socio y, si no
  la hay, el nombre del usuario. Sin datos nuevos y sin tocar Gateway.

## [0.6.1] — 2026-10-29

### Añadido

- **enlace firmado para el profesorado, sin cuenta de WordPress**: `Profesorado::token()` / `verificar()`
  con HMAC-SHA256, caducidad de 48 horas y comparación en tiempo constante. La corrección sigue siendo
  automática —el examen es tipo test y la nota se calcula sola—; esta pieza deja preparado el acceso por
  correo para cuando haga falta que una persona corrija, sin abrir cuentas.

## [0.6.0] — 2026-10-29

### Añadido

- **la matrícula se vincula a la ficha de socio por correo**. Si el correo de la persona coincide con el de
  una ficha de Members, la matrícula guarda `_convoca_member_id`. No crea fichas ni modifica datos de
  Members: solo deja el vínculo para que el área del socio pueda listar sus cursos
  (`Socio::cursos_de_socio()`).
- **borrador de condiciones de venta y desistimiento** en `docs/condiciones-venta-cursos.md`, **sin
  publicar**: recoge los 14 días, la excepción por consumo inmediato y los datos que faltan por decidir.

## [0.5.1] — 2026-10-29

### Añadido

- **el orden de las opciones cambia en cada intento**. Antes era fijo: con dos capturas de pantalla el
  examen quedaba resuelto. El orden es determinista por intento y pregunta, así que recargar no mueve las
  opciones, pero el intento siguiente las presenta de otra forma. El valor enviado sigue siendo el índice
  real de la opción: la corrección no cambia.

### Nota

- La aleatorización de **preguntas**, el **banco ampliado** (`Quiz::pick` sobre las candidatas), el
  **límite de tiempo** con su aviso y la **nota y fecha en el certificado** ya existían en el código. Lo que
  faltaba era el orden de las opciones.

## [0.5.0] — 2026-10-29

### Añadido

- **el material del curso deja de servirse por URL directa**. Los adjuntos se enlazan por un endpoint
  (`/convoca-material/<adjunto>/<lección>/`) que comprueba el acceso en cada petición, y el fichero se mueve
  a `uploads/convoca-privado/`, que el servidor web no sirve.
- **material público o privado por curso**: por defecto es privado si el curso se vende y público si es
  gratuito; el curso puede forzarlo con `_convoca_material_publico`.

### Seguridad

- Sin sesión, el endpoint responde **403**; sin matrícula también. Con matrícula activa, el fichero se
  descarga con `Content-Disposition: attachment` y `nosniff`.
- El fichero movido deja de estar en su ruta antigua: la URL que circulaba antes responde **404**.

## [0.4.2] — 2026-10-29

### Corregido

- **la página del examen escribía un aviso de PHP para cualquiera con sesión y sin matrícula**:
  `templates/examen.php` usa `$manage` y `Exam_View::data()` no la entregaba. Mismo descuido que #13, en otra
  plantilla.
- **el profesorado sin matrícula veía «necesitas matricularte»** en vez de poder previsualizar el examen,
  porque `$manage` siempre llegaba nulo.

### Añadido

- El test de contrato de plantillas deja de mirar solo la portada: recorre `curso`, `lecciones`, `leccion`,
  `notas`, `examen` y `tareas`, comparando lo que cada una documenta con lo que recibe su proveedor.

## [0.4.1] — 2026-10-28

### Corregido

- **la portada del curso escribía un aviso de PHP por cada visita de cada persona matriculada**:
  `$receipt_url` se calculaba en `render_course()` y no se pasaba a la plantilla, que la usa. El refactor
  que partió `course()` la dejó fuera del array.
- **el enlace «Ver el recibo del pago» no se pintaba nunca**: con la variable sin definir, la comparación
  contra cadena vacía siempre daba verdadero y el bloque nunca salía.

### Añadido

- Test de contrato entre la plantilla y el shortcode: las variables que `curso.php` documenta en su `@var`
  tienen que ser las que recibe, y la plantilla se renderiza comprobando que no hay ningún aviso.

## [0.4.0] — 2026-10-28

### Añadido

- **vínculo lección → página anotado al guardar**: al guardar una página se extraen sus
  `[convoca_curso_leccion id="N"]` y se anota `_convoca_pagina_id` en cada lección. Editar la página
  quitando un shortcode borra el vínculo de esa lección; despublicarla los limpia todos. El contenido
  anterior a este cambio sigue resolviéndose leyendo el contenido, como respaldo.
- **URL propia de lección**: `/cursos/<curso>/leccion/<slug>/`. Una lección bloqueada redirige al curso y
  un slug inexistente responde 404. Sin permalinks, todo sigue funcionando por la página.

## [0.3.6] — 2026-10-28

### Corregido

- **el resolvedor de enlaces de lección tenía tope de 20 páginas y fallaba en silencio**: contaba páginas
  del sitio, no del curso, y la búsqueda también se llevaba `[convoca_curso_lecciones]` (el índice), que
  gastaba plaza antes de descartarse. Un curso de 18 lecciones dejaba el presupuesto a una.
- **una sola consulta por petición**: el mapa lección → página se construye una vez y se reutiliza. Antes
  era una consulta por lección, y en frío una lectura `LIKE` sobre `wp_posts` cada vez.
- **aviso cuando falta la página**: quien puede editar el curso ve la marca «sin página» en la fila, en vez
  de un título sin enlace que nadie entiende.

## [0.3.5] — 2026-10-28

### Corregido

- **aprobar el examen no emitía el certificado**. Completar las lecciones pide el certificado cuando
  todavía no hay nota; después del examen nadie volvía a preguntar, así que el curso quedaba con el
  progreso al 100 %, la matrícula completada y el código del certificado vacío para siempre.
- El gancho `convoca_courses_exam_submitted` pasa a tener oyente: cuando el intento sale aprobado se
  vuelve a pedir la emisión por la matrícula. `maybe_issue()` ya comprueba progreso, nota mínima y
  devuelve el código existente, así que llamarlo de más no emite duplicados ni certifica a quien no
  ha aprobado.

## [0.3.4] — 2026-10-28

### Corregido

- **la portada del curso ya no puede desbordar la pila**: si `[convoca_curso]` aparecía dentro del propio
  curso, `the_content` volvía a ejecutar el shortcode sin tope y la petición moría por recursión. Ahora la
  guarda de reentrada corta la segunda llamada y ese shortcode se retira del contenido antes de expandirlo.
- **el temario sale en orden**: módulos y lecciones se ordenaban por su metadato de pertenencia, que vale
  el mismo id en todas las filas y no ordena nada. Pasan a ordenarse por `menu_order`.
- **el índice enlaza las lecciones**: cada lección accesible es un enlace a la página que la publica, con su
  ancla. Las bloqueadas siguen sin enlace, y si no hay página con el shortcode el título va sin enlace en
  vez de apuntar a ninguna parte.

## [0.3.3] — 2026-10-26

### Cambiado

- **campos de administracion y tipos de evaluacion**
- Los campos numericos no aceptan negativos.

# Changelog

Todas las versiones de Convoca Courses. El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el proyecto usa [Versionado Semántico](https://semver.org/lang/es/).

## [0.3.2] — 2026-10-22

**El módulo dejaba el panel del sitio sin acceso, administrador incluido.** Defecto crítico, encontrado
probando la demo con un navegador de verdad y medido con el módulo activo y desactivado.

### Corregido

- **Las tres capacidades por entrada del tipo de contenido se declaraban con capacidades globales**
  (`'read_post' => 'read'`, `'edit_post' => 'convoca_manage_courses'`, `'delete_post' => …`). WordPress
  copia esos valores a `$post_type_meta_caps`, y a partir de ahí cualquier `current_user_can( 'read' )`
  sin id de entrada se resuelve como `do_not_allow`: `/wp-admin` respondía 403 a todo el mundo y el
  registro se llenaba de «read_post debe comprobarse para una entrada específica». Ahora las tres van
  con nombre propio (`convoca_read_curso`, `convoca_edit_curso`, `convoca_delete_curso`) y un filtro
  `map_meta_cap` las traduce a lo que significan: leer es `read`, editar y borrar son la puerta del
  módulo. Medido en la demo: con la 0.3.1 `map_meta_cap( 'read' )` devolvía `["do_not_allow"]`; con la
  0.3.2 devuelve `["read"]` y el panel abre con 200.
- El mismo defecto en el tipo de contenido de convocatorias.

### Añadido

- `MetaCapsTest`: falla si alguna de las tres primitivas por entrada vuelve a llamarse como una
  capacidad global, y comprueba la traducción del puente.

## [0.3.1] — 2026-10-22

Cuatro defectos que solo aparecieron al probar la 0.3.0 en la demo con un navegador de verdad. Los tres
primeros dejaban la página a medias o guardaban mal los datos; ninguno se veía en las pruebas unitarias.

### Corregido

- **Las plantillas usaban las clases del módulo sin cualificar** (`Handlers::…` en vez de
  `\Convoca\Courses\Handlers::…`) y la página moría con «Class "Handlers" not found» a la altura del
  shortcode de tareas: detrás de él no se pintaba nada. Añadida la prueba `TemplatesNamespacedTest`, que
  revisa las plantillas una a una.
- **La entrega de una tarea se guardaba descuadrada**: los formatos de `$wpdb->insert()` no seguían el orden
  de las columnas, así que `contenido` se guardaba como `0` y `estado` como `0`. El armado de la fila vive
  ahora en `Task::row()`, puro y con prueba propia (`TaskRowTest`).
- **El detalle de la corrección no se veía al volver al examen**: la nota salía, pero no qué se había
  fallado. Se reconstruye de la tabla de respuestas (`Attempts::answers()`), que es donde estaba el dato.
- **Los bloques de examen, tareas y notas salían sin estilos**: sus contenedores no llevaban la clase
  `convoca-courses`, así que no heredaban la paleta ni las reglas base. Ahora la llevan, y el CSS cubre los
  componentes nuevos (preguntas, opciones, resultado, tarjetas de tarea, tablas, botón y aviso de aviso).
- La nota mínima se pinta como `50` y no como `50,0`, y quien gestiona el curso ya no ve el aviso de
  «necesitas estar matriculado».
- **Las capabilities se refrescan al actualizar.** Una instalación que venía de la 0.2.3 no tenía
  `convoca_grade_courses` y el módulo funcionaba solo por el respaldo de `manage_options`, así que no había
  forma de nombrar tutores. Medido en la demo: `current_user_can( 'convoca_manage_courses' )` respondía «no»
  al administrador.
- **Borrar un examen se lleva sus intentos y sus respuestas**: quedaban filas huérfanas (3 intentos y 8
  respuestas de un examen ya inexistente en la demo).

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
