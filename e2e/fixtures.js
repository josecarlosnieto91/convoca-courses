// fixtures.js — datos del fixture sembrado en la demo por e2e/seed-demo.sh
//
// Si no existe `.fixtures.json`, la suite no inventa datos: para y dice cómo sembrarlo.
const fs = require('fs');
const path = require('path');

function fixture() {
  const ruta = path.join(__dirname, '.fixtures.json');

  if (!fs.existsSync(ruta)) {
    throw new Error(
      'Falta e2e/.fixtures.json: ejecuta antes `npm run seed` (siembra curso, examen, tareas y personas de prueba en la demo).'
    );
  }

  return JSON.parse(fs.readFileSync(ruta, 'utf8'));
}

/** Inicia sesión con el formulario real de WordPress. */
async function login(page, usuario, clave) {
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', usuario);
  await page.fill('#user_pass', clave);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);
  return page;
}

/** Contraste WCAG entre dos colores rgb(). */
function contraste(rgbA, rgbB) {
  const lum = (rgb) => {
    const [r, g, b] = rgb.match(/\d+/g).slice(0, 3).map((v) => {
      const c = parseInt(v, 10) / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const [a, b] = [lum(rgbA), lum(rgbB)].sort((x, y) => y - x);
  return (a + 0.05) / (b + 0.05);
}

/** Captura de pantalla adjunta al informe HTML. */
async function captura(page, testInfo, nombre) {
  const ruta = testInfo.outputPath(nombre + '.png');
  await page.screenshot({ path: ruta, fullPage: true });
  await testInfo.attach(nombre, { path: ruta, contentType: 'image/png' });
  return ruta;
}

module.exports = { fixture, login, contraste, captura };
