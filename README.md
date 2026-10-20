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
```

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
