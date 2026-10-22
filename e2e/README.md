# Suite E2E de Convoca Courses (Playwright)

Mide el plugin **desplegado en la demo**, con WordPress y MySQL de verdad. No hay dobles: el fixture se
siembra con wp-cli en el sitio y los tests entran con el formulario real de WordPress.

## Cómo se ejecuta

```bash
npm install                # @playwright/test
npm run seed               # siembra curso, examen, tareas y dos personas de prueba en la demo
npm run test:e2e           # escritorio (Desktop Chrome) y móvil (Pixel 5)
npm run cleanup            # borra el fixture de la demo
```

Variables: `E2E_BASE` (por defecto `https://demo.getconvoca.app`), `E2E_SSH_HOST` (por defecto
`andromeda`), `E2E_WP_PATH` y `E2E_WP_USER` (ruta y usuario del sitio en ese host).

El fixture se guarda en `e2e/.fixtures.json` (**ignorado por git**: lleva las credenciales generadas de
las personas de prueba). Si falta, la suite no inventa datos: para y dice cómo sembrarlo.

## Qué se comprueba

| Test | Qué mide |
|---|---|
| portada y listado | portada 200, archivo de cursos con tarjetas y la ficha del curso del fixture |
| página del curso | las cuatro secciones del módulo, sin fatales, `courses.css?vX.Y.Z`, paleta del botón (`rgb(255,135,0)` sobre `rgb(26,29,36)`, contraste > 4.5), tarjeta de examen con superficie, borde, radio y relleno, y aviso con su barra de 4 px |
| examen | login real del alumno, apertura del intento, 4 preguntas con radios y checkboxes, envío, nota `100,00`, aprobado y las 4 líneas del detalle correctas |
| tarea | entrega con texto y fichero por `setInputFiles`, aviso, texto guardado y «Fichero entregado» |
| notas | piezas con su peso, nota `90,00`, **nota final calculada a mano y comparada con la que pinta la tabla** (±0,01) y acta cerrada al alumnado |
| panel (opcional) | el profesorado entra en *Cursos → Entregas* y ve la tabla y el formulario de corrección |
| móvil | 390 px: sin desbordes horizontales, tarjeta dentro del ancho y opciones alineadas |

Cada test adjunta capturas al informe HTML (`playwright-report/index.html`) y deja los PNG en
`test-results/`.

## El 403 del panel que no era del entorno

Durante el desarrollo de esta suite el `/wp-admin` de la demo respondía 403 a todo el mundo, incluso a un
administrador recién creado. Parecía una restricción del sitio. No lo era: **era el plugin**. El tipo de
contenido declaraba `'read_post' => 'read'`, WordPress copia ese valor a `$post_type_meta_caps` y desde
ahí cualquier `current_user_can( 'read' )` sin id de entrada se resuelve como `do_not_allow`. Corregido en
la 0.3.2 (las tres capacidades por entrada tienen nombre propio y las traduce `CPT_Base::bridge_meta_caps`).

Si el test del panel vuelve a dar 403, el sospechoso es ese mapa, no las cookies: `MetaCapsTest` lo vigila.

Cómo se comprobó, por si hay que repetirlo:

```bash
wp eval 'echo wp_json_encode( map_meta_cap( "read", 1 ) );'   # ["read"] bien, ["do_not_allow"] mal
wp plugin deactivate convoca-courses && wp eval '...' && wp plugin activate convoca-courses
```
