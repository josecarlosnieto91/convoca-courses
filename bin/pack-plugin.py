#!/usr/bin/env python3
"""Empaquetado de plugins Convoca para WordPress.org / SVN.

Uso:
    python3 pack-plugin.py <repo-path> [--out-dir DIR] [--skip-composer]

Genera un zip limpio listo para el Directorio de Plugins de WordPress.org:
- excluye todo el tooling de desarrollo (.git, .github, tests, dev-tools,
  phpstan/phpcs/phpunit, *.md, etc.) según .distignore del repo
- regenera vendor/ de PRODUCCIÓN (composer install --no-dev --no-interaction)
  con licencias de terceros incluidas
- coloca el zip en out-dir (default: ~/convoca-release/)

NO sube nada a SVN ni a wp.org: solo produce el artefacto.
"""
import argparse
import os
import re
import shutil
import subprocess
import sys
import stat
import tempfile
import time
import zipfile

DEFAULT_OUT = os.path.expanduser("~/convoca-release")

# Exclusiones de seguridad adicionales (además del .distignore del repo)
# Se comparan por nombre (primer segmento de la ruta o basename), así que valen para
# cualquier repo sin que haya que acordarse de añadirlas a cada .distignore.
#
# `auth.json`, `playwright-report` y `test-results` son artefactos de las pruebas de
# navegador: el primero es el ESTADO DE SESIÓN (cookies de WordPress) y el segundo un
# informe HTML con las trazas. Acabaron servidos por HTTP en sitios reales porque
# vivían en la raíz del repo y nadie los excluía; el .distignore de cada repo no los
# cubría y una copia del árbol se los llevaba enteros.
# Artefactos que NUNCA deben viajar en un paquete, ni siquiera dentro de vendor/.
# Se comprueban por nombre en cualquier nivel de la ruta.
PROHIBIDOS = {"auth.json", "storageState.json", ".fixture.json", "playwright-report",
              "test-results", ".phpunit.result.cache", "__pycache__"}

HARD_EXCLUDES = {
    ".git", ".svn", "node_modules", ".DS_Store", ".distignore",
    ".htaccess",  # archivos ocultos de servidor: wp.org los prohíbe en el paquete
    "auth.json", "storageState.json", ".fixture.json",
    "playwright-report", "test-results",
    ".phpunit.result.cache", "__pycache__",
}


def run(cmd, cwd=None, check=True):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, cwd=cwd)
    if check and r.returncode != 0:
        raise RuntimeError(f"Error ejecutando: {cmd}\n{r.stderr[-2000:]}")
    return r.stdout.strip()


def read_distignore(repo):
    path = os.path.join(repo, ".distignore")
    patterns = []
    if os.path.exists(path):
        for line in open(path):
            line = line.strip()
            if line and not line.startswith("#"):
                patterns.append(line)
    return patterns


def included(path, repo, patterns):
    """¿Debe incluirse `path` (relativo al repo) en el zip?"""
    rel = os.path.relpath(path, repo).replace(os.sep, "/")
    first = rel.split("/")[0]
    base = os.path.basename(path)
    if first in HARD_EXCLUDES or base in HARD_EXCLUDES:
        return False
    import fnmatch
    for pat in patterns:
        p = pat.rstrip("/")
        if p == rel:
            return False
        if p.endswith("/*") and rel.startswith(p[:-2] + "/"):
            return False
        # Patrón simple de archivo/directorio en la raíz (con o sin glob)
        if "/" not in p:
            if fnmatch.fnmatch(first, p):
                return False
            if rel.startswith(p.split("*")[0]) and rel != p and "/" not in rel:
                if fnmatch.fnmatch(rel, p):
                    return False
        else:
            # Patrón con ruta (p.ej. docs/ o includes/*.tmp)
            if p.endswith("/") and rel.startswith(p):
                return False
            if fnmatch.fnmatch(rel, p):
                return False
    return True


def modo_legible(ruta):
    """Modo del fichero normalizado: 644, o 755 si era ejecutable.

    Por qué: los ficheros del árbol de trabajo pueden estar en 600 (le pasaba a `LICENSE` en los
    ocho repos) y el paquete viaja con ese modo — el plugin se despliega con un fichero que
    **nadie más puede leer**. Git solo guarda 644/755, así que esto no se ve en el repo y solo
    se nota en producción. El paquete no puede sacarlo así.
    """
    return 0o755 if os.access(ruta, os.X_OK) else 0o644


def escribir(zf, ruta, arcname):
    """Añade un fichero al paquete con el modo normalizado."""
    modo = modo_legible(ruta)
    info = zipfile.ZipInfo(arcname, date_time=time.localtime(os.path.getmtime(ruta))[:6])
    info.external_attr = (modo | stat.S_IFREG) << 16
    info.compress_type = zipfile.ZIP_DEFLATED
    with open(ruta, "rb") as fh:
        zf.writestr(info, fh.read())


def build_zip(repo, out_dir, skip_composer=False, leave_prod=False):
    repo = os.path.abspath(repo)
    slug = os.path.basename(repo)  # convoca-core, etc.

    # Versión desde el header PHP principal
    main_php = None
    for f in sorted(os.listdir(repo)):
        if f.startswith(slug) and f.endswith(".php"):
            main_php = os.path.join(repo, f)
            break
    if not main_php:
        raise RuntimeError(f"No se encontró el archivo principal en {repo}")
    version = None
    for line in open(main_php):
        m = re.match(r"\s*\*\s*Version:\s*([0-9.]+)", line)
        if m:
            version = m.group(1)
            break
    if not version:
        raise RuntimeError(f"No se encontró Version: en {main_php}")

    os.makedirs(out_dir, exist_ok=True)
    zip_path = os.path.join(out_dir, f"{slug}-{version}-wporg.zip")
    tmp_zip = zip_path + ".tmp"

    patterns = read_distignore(repo)

    # ── El árbol del paquete se monta en una COPIA, no en el repo ─────────────
    #
    # Antes, `composer install --no-dev` se ejecutaba DENTRO del repositorio que se
    # empaquetaba y luego se restauraba el vendor de desarrollo «a mano». Si el proceso
    # se interrumpía entre las dos líneas (Ctrl-C, fallo de composer, timeout del guion
    # que lo invoca), el repo de trabajo se quedaba SIN phpcs, SIN phpstan y SIN phpunit,
    # y el error que aparecía después no apuntaba a ninguna parte (issue #1).
    #
    # Ahora se trabaja sobre una copia temporal: el repositorio no se toca ni un byte, así
    # que la interrupción no puede dejarlo a medias. Y como no se toca, tampoco hay que
    # «restaurar» nada después.
    trabajo = tempfile.mkdtemp(prefix=f"pack-{slug}-")
    dev_antes = os.path.exists(os.path.join(repo, "vendor", "bin", "phpcs"))

    include_vendor = False
    try:
        shutil.copytree(
            repo,
            trabajo,
            dirs_exist_ok=True,
            ignore=shutil.ignore_patterns(".git", "node_modules", "__pycache__"),
        )

        if os.path.exists(os.path.join(repo, "composer.json")) and not skip_composer:
            if shutil.which("composer"):
                print(f"[composer] vendor --no-dev en la copia de trabajo de {slug}...")
                run("composer install --no-dev --no-interaction --prefer-dist "
                    "--no-progress --classmap-authoritative "
                    "--ignore-platform-req=ext-iconv", cwd=trabajo)
                include_vendor = True
            else:
                print("[composer] WARN: composer no encontrado; vendor local tal cual")
                include_vendor = os.path.isdir(os.path.join(trabajo, "vendor"))

        with zipfile.ZipFile(tmp_zip, "w", zipfile.ZIP_DEFLATED) as zf:
            for root, dirs, files in os.walk(trabajo):
                rel_root = os.path.relpath(root, trabajo).replace(os.sep, "/")
                in_vendor_root = include_vendor and (rel_root == "vendor" or rel_root.startswith("vendor/"))
                # Podar directorios excluidos (vendor viaja completo si include_vendor)
                keep_dirs = []
                for d in dirs:
                    abs_d = os.path.join(root, d)
                    rel_d = os.path.relpath(abs_d, trabajo).replace(os.sep, "/")
                    if in_vendor_root:
                        keep_dirs.append(d)  # todo dentro de vendor de producción viaja
                    elif include_vendor and rel_d == "vendor":
                        keep_dirs.append(d)  # vendor de producción viaja entero
                    elif included(abs_d, trabajo, patterns):
                        keep_dirs.append(d)
                dirs[:] = keep_dirs
                # Incluir archivos: dentro de vendor de producción viaja entero, salvo los
                # artefactos de pruebas (el `vendor/bin/.phpunit.result.cache` se cuela ahí
                # porque el vendor no se regenera en limpio y la exclusión normal no llega).
                for f in files:
                    fp = os.path.join(root, f)
                    rel = os.path.relpath(fp, trabajo).replace(os.sep, "/")
                    if f in PROHIBIDOS:
                        continue
                    if in_vendor_root:
                        escribir(zf, fp, f"{slug}/{rel}")  # dentro de vendor: incluido
                    elif include_vendor and rel == "vendor/autoload.php":
                        escribir(zf, fp, f"{slug}/{rel}")
                    elif included(fp, trabajo, patterns):
                        escribir(zf, fp, f"{slug}/{rel}")

        os.replace(tmp_zip, zip_path)
    finally:
        shutil.rmtree(trabajo, ignore_errors=True)

    # El empaquetado no puede dejar el repositorio sin herramientas de desarrollo. Es la
    # comprobación del escenario del issue #1: si alguna vez vuelve a tocar el repo, salta
    # aquí, en voz alta, y no tres días después con un «No existe el fichero» al correr
    # las pruebas.
    if dev_antes and not os.path.exists(os.path.join(repo, "vendor", "bin", "phpcs")):
        raise RuntimeError(
            "El empaquetado se ha llevado por delante el vendor de desarrollo del repositorio: "
            f"falta {repo}/vendor/bin/phpcs. El repo tiene que quedar intacto."
        )

    # El paquete se comprueba a sí mismo ANTES de darse por bueno. Un artefacto de
    # desarrollo que se cuele aquí acaba servido por HTTP en un sitio real: eso ya pasó
    # (el estado de sesión de las pruebas de navegador llegó a producción dentro de un
    # paquete). Un error aquí, que se ve, es mejor que un fichero de sesión en Lugg.
    PROHIBIDOS_CHECK = PROHIBIDOS
    with zipfile.ZipFile(zip_path) as zf:
        nombres = zf.namelist()
        colados = [n for n in nombres
                   if any(parte in PROHIBIDOS_CHECK for parte in n.split("/")[1:])]
    if colados:
        os.remove(zip_path)
        raise RuntimeError(
            "Paquete descartado: llevaba artefactos de desarrollo.\n  "
            + "\n  ".join(colados[:10])
        )
    if len(nombres) == 0:
        os.remove(zip_path)
        raise RuntimeError(
            "Paquete descartado: salió vacío. El empaquetado no encontró la fuente "
            f"({repo}); revisa la ruta antes de fiarte del resultado."
        )

    ilegibles = [i.filename for i in zipfile.ZipFile(zip_path).infolist()
                 if not i.is_dir() and not ((i.external_attr >> 16) & 0o044)]
    if ilegibles:
        os.remove(zip_path)
        raise RuntimeError(
            "Paquete descartado: llevaba ficheros que nadie más puede leer (sin permiso de "
            "lectura para grupo u otros).\n  " + "\n  ".join(ilegibles[:10])
        )

    size_mb = os.path.getsize(zip_path) / 1024 / 1024
    print(f"[OK] {zip_path}  ({size_mb:.1f} MB, {len(nombres)} entradas)")
    return zip_path, version


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("repo")
    ap.add_argument("--out-dir", default=DEFAULT_OUT)
    ap.add_argument("--skip-composer", action="store_true")
    ap.add_argument("--leave-prod", action="store_true",
                    help="(ya no hace falta: el repositorio no se toca al empaquetar)")
    args = ap.parse_args()
    build_zip(args.repo, args.out_dir, skip_composer=args.skip_composer,
              leave_prod=args.leave_prod)


if __name__ == "__main__":
    main()
