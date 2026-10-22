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

## Lo que está pendiente de decisión

- **El `/wp-admin` de la demo responde 403 a las peticiones automatizadas**, incluso con una sesión real
  creada por el formulario de login y con un usuario con la capacidad del módulo. Mientras no se abra, el
  test del panel está **desactivado por defecto**: se ejecuta con `E2E_PANEL=1`. Issue abierto en el repo
  que corresponda con la evidencia.
