# Convoca Courses

Módulo de cursos online para WordPress: cursos con módulos y lecciones, matrícula, progreso,
certificado verificable y venta opcional.

* **Dependencia obligatoria:** [`convoca-core`](https://github.com/josecarlosnieto91/convoca-core) — y nada más.
* **Opcional:** `convoca-gateway`, solo para cobrar la matrícula.
* Licencia: GPL-2.0-or-later.

## Qué hay dentro

| Pieza | Tipo | Dónde |
|---|---|---|
| Curso | CPT `convoca_curso` | `includes/CPT_Curso.php` |
| Módulo | CPT `convoca_modulo` | `includes/CPT_Modulo.php` |
| Lección | CPT `convoca_leccion` | `includes/CPT_Leccion.php` |
| Matrícula | CPT `convoca_matricula` | `includes/CPT_Matricula.php` |
| Progreso | tabla `{prefijo}convoca_course_progress` | `includes/Progress.php`, `includes/Installer.php` |

La API pública del módulo son **shortcodes**, no plantillas del tema:

```
[convoca_curso id="123"]            Portada del curso: datos, progreso y matrícula.
[convoca_curso_lecciones id="123"]  Índice de lecciones con progreso y bloqueos.
[convoca_curso_leccion id="456"]    Render de una lección concreta.
[convoca_curso_examen id="789"]     Examen del curso.
[convoca_curso_tareas id="123"]     Tareas y entregas.
[convoca_curso_notas id="123"]      Notas de quien está mirando.
[convoca_curso_acta id="123"]       Acta del alumnado (solo profesorado).
[convoca_matricula course="123"]    Matrícula sin cuenta: correo y nombre.
[convoca_acceso]                    Entrada con enlace al correo o código de 6 cifras.
[convoca_condiciones]              Condiciones de venta con los datos de la entidad.
```

### Alumnado sin cuenta de WordPress

Desde la 0.11.0 el alumnado **no necesita usuario de WordPress**. El correo es la identidad, se entra con un
enlace de un solo uso o con un código de 6 cifras, y todo (matrícula, progreso, examen y certificado) cuelga de
esa identidad. No se crea ningún `WP_User`.

Las matrículas anteriores siguen con su usuario de WordPress: **no se migran**.

Ver `docs/alumnado-sin-cuenta.md` para el recorrido completo y qué hay que rellenar en Ajustes.

## Desarrollo

```bash
composer install
composer test          # phpcs + phpstan + phpunit
vendor/bin/phpcs --standard=.phpcs.xml.dist
vendor/bin/phpstan analyse --configuration=phpstan.neon
vendor/bin/phpunit -c phpunit-unit.xml
```

## Pruebas

* `tests/Unit/ProgressTest.php` — el porcentaje que decide el certificado.
* `tests/Unit/HierarchyTest.php` — validación curso → módulo → lección.
* `tests/Unit/VideoSignatureTest.php` — firma AWS V4 contra el vector publicado por AWS.
* `tests/Unit/LicenseTest.php` — PRO y periodo de gracia.
* `tests/Unit/ManifestTest.php` — regla 0 (ningún nombre de entidad), versiones coherentes y
  secciones del readme.

## Paquete

```bash
python3 ~/repos/convoca-release/pack-plugin.py ~/repos/convoca-courses
```

Genera el zip sin ficheros de desarrollo y con `vendor/` de producción.
