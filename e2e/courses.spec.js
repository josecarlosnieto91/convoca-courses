// courses.spec.js — E2E de Convoca Courses contra la demo, con navegador real.
//
// Mide lo que solo se ve con WordPress delante: que la página del curso se pinte entera, que el
// examen se abra y corrija en el servidor, que la tarea se entregue con fichero, que las notas
// cuadren con sus pesos, y que los estilos del módulo (paleta, tarjetas, botón, contraste) estén
// aplicados de verdad. El fixture lo siembra `npm run seed` y lo borra `npm run cleanup`.
const { test, expect } = require('@playwright/test');
const { fixture, login, contraste, captura } = require('./fixtures');

const datos = fixture();

/**
 * Abre un intento si la página ofrece el botón (si ya hay uno abierto, el formulario está puesto).
 */
async function abrirExamen(page) {
  const abrir = page.getByRole('button', { name: /Abrir examen/i });
  if (await abrir.count()) {
    await abrir.click();
    await page.waitForLoadState('networkidle');
  }
  await expect(page.locator('.convoca-courses-exam__question').first()).toBeVisible();
}

test.describe('Convoca Courses — flujos públicos', () => {
  test('la portada y el listado de cursos cargan', async ({ page }, testInfo) => {
    const portada = await page.goto('/', { waitUntil: 'networkidle' });
    expect(portada.status()).toBe(200);
    await expect(page).toHaveTitle(/Convoca/);
    await captura(page, testInfo, '01-portada');

    const archivo = await page.goto(datos.archive_url, { waitUntil: 'networkidle' });
    expect(archivo.status()).toBe(200);

    // El archivo lista cursos: puede paginar y cachear, así que se comprueba que hay tarjetas y
    // enlaces de curso, y que el curso del fixture se sirve en su propia URL.
    await expect(page.getByRole('heading', { name: /Cursos/i }).first()).toBeVisible();
    const enlaces = page.locator(`a[href*="/cursos/"]`);
    expect(await enlaces.count()).toBeGreaterThan(0);
    await captura(page, testInfo, '02-listado-cursos');

    const ficha = await page.goto(datos.course_url, { waitUntil: 'networkidle' });
    expect(ficha.status()).toBe(200);
    await expect(page.locator('h1').first()).toContainText(datos.marca);
    await captura(page, testInfo, '02b-ficha-curso');
  });

  test('la página del curso pinta sus secciones y sus estilos', async ({ page }, testInfo) => {
    const resp = await page.goto(datos.page_url, { waitUntil: 'networkidle' });
    expect(resp.status()).toBe(200);

    // Sin fatales: el plugin cortaba la página a la mitad y detrás no se pintaba nada.
    const cuerpo = await page.textContent('body');
    expect(cuerpo).not.toContain('error crítico');
    expect(cuerpo).not.toContain('There has been a critical error');

    await expect(page.locator('.convoca-courses-exam')).toBeVisible();
    await expect(page.locator('.convoca-courses-tasks')).toBeVisible();
    await expect(page.locator('.convoca-courses-grades')).toBeVisible();
    // El acta es del profesorado: al visitante anónimo se le dice, no se le enseña.
    await expect(page.locator('.convoca-courses-gradebook, .convoca-courses-notice--locked').first()).toBeVisible();

    // El CSS del módulo viaja con su versión.
    const hoja = await page.evaluate(() =>
      Array.from(document.querySelectorAll('link[rel=stylesheet]')).map((l) => l.href).filter((h) => /courses\.css/.test(h))
    );
    expect(hoja.length).toBeGreaterThan(0);
    expect(hoja[0]).toMatch(/courses\.css\?ver=\d+\.\d+\.\d+/);

    // Paleta aplicada: el botón del módulo es el naranja de marca con tinta oscura, y se lee.
    const boton = page.locator('.convoca-courses .convoca-button, .convoca-courses__button').first();
    await expect(boton).toBeVisible();
    const estilo = await boton.evaluate((el) => {
      const c = getComputedStyle(el);
      return { fondo: c.backgroundColor, color: c.color, radio: c.borderRadius };
    });
    expect(estilo.fondo).toBe('rgb(255, 135, 0)');
    expect(estilo.color).toBe('rgb(26, 29, 36)');
    expect(contraste(estilo.color, estilo.fondo)).toBeGreaterThan(4.5);

    // Sin sesión no hay intento abierto, así que no hay tarjetas de pregunta: se mide el bloque del
    // examen (título, reglas y aviso). Las tarjetas se miden en el test con sesión de alumno.
    const bloque = page.locator('.convoca-courses-exam');
    await expect(bloque).toBeVisible();
    await expect(bloque.locator('.convoca-courses-exam__rules')).toContainText(/Nota mínima/i);

    // Los avisos llevan su barra lateral.
    const aviso = page.locator('.convoca-courses-notice').first();
    await expect(aviso).toBeVisible();
    const borde = await aviso.evaluate((el) => getComputedStyle(el).borderLeftWidth);
    expect(borde).toBe('4px');

    await captura(page, testInfo, '03-pagina-curso');
  });

  test('el alumno entra, hace el examen y ve su corrección', async ({ page }, testInfo) => {
    await login(page, datos.alumno.login, datos.alumno.pass);
    await page.goto(datos.page_url, { waitUntil: 'networkidle' });

    const usuario = await page.locator('.display-name').first().textContent();
    expect(usuario).toContain('e2e_alumno');

    // Si ya hay un intento abierto (por una pasada anterior) el formulario está puesto y no hay botón.
    const abrir = page.getByRole('button', { name: /Abrir examen/i });
    if (await abrir.count()) {
      await abrir.click();
      await page.waitForLoadState('networkidle');
    }

    // Cuatro preguntas, con radios y checkboxes de verdad, y la tarjeta con la superficie del módulo.
    await expect(page.locator('.convoca-courses-exam__question')).toHaveCount(4);
    const caja = await page.locator('.convoca-courses-exam__question').first().evaluate((el) => {
      const c = getComputedStyle(el);
      return { fondo: c.backgroundColor, borde: c.borderTopWidth, radio: c.borderRadius, relleno: c.paddingTop };
    });
    expect(caja.fondo).not.toBe('rgba(0, 0, 0, 0)');
    expect(caja.borde).toBe('1px');
    expect(parseFloat(caja.radio)).toBeGreaterThanOrEqual(8);
    expect(parseFloat(caja.relleno)).toBeGreaterThan(0);
    expect(await page.locator('.convoca-courses-exam__question input[type=radio]').count()).toBeGreaterThan(0);
    expect(await page.locator('.convoca-courses-exam__question input[type=checkbox]').count()).toBeGreaterThan(0);
    await captura(page, testInfo, '04-examen-abierto');

    // Todas correctas: Asturias, pares 2 y 4, Verdadero, cuatro patas.
    // Se elige por el ORDEN de la pregunta, no por su identificador: los ids del banco son globales
    // del sitio y cambian en cada siembra.
    const preguntas = page.locator('.convoca-courses-exam__question');
    const marcar = async (indice, valores) => {
      for (const valor of valores) {
        await preguntas.nth(indice).locator(`input[value="${valor}"]`).check();
      }
    };
    await marcar(0, ['1']);
    await marcar(1, ['0', '2']);
    await marcar(2, ['0']);
    await marcar(3, ['2']);

    await page.getByRole('button', { name: /Enviar examen/i }).click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.convoca-courses-notice').first()).toContainText('Examen enviado');
    const resultado = page.locator('.convoca-courses-exam__result');
    await expect(resultado).toContainText('100,00');
    await expect(resultado).toContainText('aprobado');
    await expect(page.locator('.convoca-courses-exam__detail li')).toHaveCount(4);
    await expect(page.locator('.convoca-courses-exam__detail li.is-correct')).toHaveCount(4);
    await captura(page, testInfo, '05-examen-corregido');
  });

  test('el alumno entrega una tarea con texto y fichero', async ({ page }, testInfo) => {
    await login(page, datos.alumno.login, datos.alumno.pass);
    await page.goto(datos.page_url, { waitUntil: 'networkidle' });

    const tarea = page.locator('#convoca-tarea-' + datos.task_open);
    await expect(tarea).toBeVisible();
    await tarea.locator('textarea[name=convoca_entrega]').fill('Entrega hecha desde la suite E2E.');
    await tarea.locator('input[type=file]').setInputFiles({
      name: 'entrega-e2e.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('Fichero de la suite E2E.\n'),
    });
    await tarea.getByRole('button', { name: /^Entregar$/ }).click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.convoca-courses-notice').first()).toContainText('Tarea entregada');
    const entrega = page.locator('#convoca-tarea-' + datos.task_open + ' .convoca-courses-task__submission');
    await expect(entrega).toContainText('Entrega hecha desde la suite E2E.');
    await expect(entrega).toContainText('Fichero entregado');
    await expect(entrega).toContainText('Pendiente de corrección');
    await captura(page, testInfo, '06-tarea-entregada');
  });

  test('las notas cuadran con sus pesos y el acta está cerrada al alumno', async ({ page }, testInfo) => {
    await login(page, datos.alumno.login, datos.alumno.pass);
    await page.goto(datos.page_url, { waitUntil: 'networkidle' });

    const notas = page.locator('.convoca-courses-grades');
    await expect(notas).toBeVisible();
    await expect(notas).toContainText('Nota final');
    // La tarea corregida del fixture pesa 20 y vale 90; el examen pesa 60.
    await expect(notas).toContainText('90,00');
    // La cabecera se pinta en mayúsculas por CSS: el texto del DOM va en caja normal.
    await expect(notas.locator('thead')).toContainText(/peso/i);

    // Los pesos se respetan: la nota final es la media ponderada de las piezas ya corregidas.
    // Se calcula a mano con lo que pinta la tabla y se compara con la fila «Nota final».
    const filas = await notas.locator('tbody tr').evaluateAll((trs) =>
      trs.map((tr) => Array.from(tr.querySelectorAll('td')).map((td) => td.innerText.trim()))
    );
    const numero = (txt) => parseFloat(String(txt).replace(/\./g, '').replace(',', '.'));

    const piezas = filas
      .map((celdas) => ({ peso: numero(celdas[1]), nota: numero(celdas[2]) }))
      .filter((p) => Number.isFinite(p.peso) && Number.isFinite(p.nota) && p.peso > 0);

    expect(piezas.length).toBeGreaterThan(0);

    const esperada = piezas.reduce((acc, p) => acc + p.peso * p.nota, 0) / piezas.reduce((acc, p) => acc + p.peso, 0);
    const finalPintada = numero(await notas.locator('tfoot th').last().innerText());

    expect(Number.isFinite(finalPintada)).toBe(true);
    expect(Math.abs(finalPintada - esperada)).toBeLessThanOrEqual(0.01);

    // El acta es del profesorado: al alumno se le dice, no se le enseña.
    await expect(page.locator('.convoca-courses-notice--locked')).toContainText('solo la ve quien gestiona el curso');
    await captura(page, testInfo, '07-notas-alumno');
  });
});

test.describe('Convoca Courses — panel', () => {
  // Este test estuvo saltado mientras el panel devolvía 403: la causa no era el entorno, era el
  // plugin (las capacidades por entrada del tipo de contenido contaminaban $post_type_meta_caps y
  // dejaban `read` en do_not_allow, así que /wp-admin cerraba a todo el mundo). Corregido en la
  // 0.3.2; si vuelve a fallar con 403, mirar MetaCapsTest antes que las cookies.
  test('el profesorado entra y ve las entregas del curso', async ({ page }, testInfo) => {
    await login(page, datos.profe.login, datos.profe.pass);

    const resp = await page.goto(
      `/wp-admin/edit.php?post_type=convoca_curso&page=convoca-courses-entregas&course_id=${datos.course_id}`,
      { waitUntil: 'domcontentloaded' }
    );

    // Si el panel no se abre, el motivo tiene que verse en el informe, no esconderse.
    // Ojo: mirar el texto del body entero da falsos positivos, porque WordPress mete en línea las
    // traducciones de los scripts ("no tienes permisos para subir este tipo de archivo"). Se mira la
    // zona del contenido y el título del documento.
    expect(resp.status(), 'HTTP del panel de entregas').toBe(200);
    expect(page.url()).toContain('page=convoca-courses-entregas');
    expect(await page.title()).not.toContain('Error');
    expect(await page.locator('#wpbody-content').innerText()).not.toContain('no tienes permisos');

    await expect(page.locator('h1')).toContainText('Entregas del alumnado');
    const tabla = page.locator('table.convoca-courses-entregas');
    await expect(tabla).toBeVisible();
    await expect(tabla).toContainText('Tarea corregida ' + datos.marca);
    await expect(tabla.locator('input[name=convoca_nota]').first()).toBeVisible();
    await expect(tabla.locator('input[name=_convoca_nonce]').first()).toHaveValue(/.+/);

    await captura(page, testInfo, '08-panel-entregas');
  });
});

test.describe('Convoca Courses — móvil', () => {
  test('en 390 px no hay desbordes y el examen sigue siendo usable', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, datos.alumno.login, datos.alumno.pass);
    await page.goto(datos.page_url, { waitUntil: 'networkidle' });

    const ancho = await page.evaluate(() => ({
      ventana: document.documentElement.clientWidth,
      documento: document.documentElement.scrollWidth,
    }));
    expect(ancho.documento).toBeLessThanOrEqual(ancho.ventana);

    await abrirExamen(page);

    const tarjeta = page.locator('.convoca-courses-exam__question').first();
    const caja = await tarjeta.boundingBox();
    expect(caja.width).toBeLessThanOrEqual(390);

    const opciones = page.locator('.convoca-courses-exam__option');
    expect(await opciones.count()).toBeGreaterThan(0);
    const desalineadas = await page.evaluate(() =>
      Array.from(document.querySelectorAll('.convoca-courses-exam__option')).filter((el) => {
        const r = el.getBoundingClientRect();
        return r.right > document.documentElement.clientWidth + 2;
      }).length
    );
    expect(desalineadas).toBe(0);

    await captura(page, testInfo, '09-movil');
  });
});
