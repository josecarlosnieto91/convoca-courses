#!/usr/bin/env python3
"""Prueba del recorrido público sin JavaScript contra el WordPress de desarrollo.

Hace lo que haría una persona: inicia sesión, abre la portada del curso, se matricula con el
formulario, abre una lección, la marca como completada. Todo por HTTP y sin ejecutar JavaScript.
"""
import json
import pathlib
import re
import sys

import requests

BASE = "http://localhost:8080"
USER = "alumno2-prueba"
PASSWORD = "Prueba-Segura-2026"

# Los identificadores salen de lo que acaba de sembrar dev-tools/verificar.php: fijarlos a mano en
# este fichero es exactamente cómo se escriben pruebas que pasan sin comprobar nada.
DATOS = json.loads((pathlib.Path(__file__).parent / "resultado.json").read_text(encoding="utf-8"))
COURSE_ID = int(DATOS["curso_id"])
COURSE_PAGE = int(DATOS["pagina_curso"])
LESSON_PAGE = int(DATOS["pagina_leccion"])
LESSON_ID = int(DATOS["lecciones"][0])

ok = 0
fallos = []


def check(cond, msg):
    global ok
    if cond:
        ok += 1
        print(f"  OK   {msg}")
    else:
        fallos.append(msg)
        print(f"  FALLO {msg}")


session = requests.Session()
session.headers["User-Agent"] = "convoca-courses-check/1.0"

print("== Inicio de sesión ==")
r = session.get(f"{BASE}/wp-login.php", timeout=20)
r = session.post(
    f"{BASE}/wp-login.php",
    data={
        "log": USER,
        "pwd": PASSWORD,
        "wp-submit": "Acceder",
        "redirect_to": f"{BASE}/wp-admin/",
        "testcookie": "1",
    },
    timeout=20,
    allow_redirects=True,
)
check(any("wordpress_logged_in" in c.name for c in session.cookies), "la sesión queda iniciada")

print("\n== Portada del curso (sin matrícula) ==")
r = session.get(f"{BASE}/?page_id={COURSE_PAGE}", timeout=20)
check(r.status_code == 200, "la portada responde 200")
check("Matricularme" in r.text, "se ofrece el botón de matrícula")
check("convoca-courses__modules" in r.text, "se listan los módulos")

m = re.search(r'name="_convoca_nonce" value="([^"]+)"', r.text)
check(m is not None, "el formulario trae nonce")
nonce = m.group(1) if m else ""

print("\n== Matrícula por formulario ==")
r = session.post(
    f"{BASE}/wp-admin/admin-post.php",
    data={
        "action": "convoca_courses_enroll",
        "course_id": str(COURSE_ID),
        "_convoca_nonce": nonce,
    },
    headers={"Referer": f"{BASE}/?page_id={COURSE_PAGE}"},
    timeout=20,
    allow_redirects=False,
)
check(r.status_code in (301, 302, 303), f"la matrícula redirige (HTTP {r.status_code})")
check("convoca_msg=enrolled" in r.headers.get("Location", ""), "el aviso es «enrolled»")

# Se sigue la redirección de verdad: es lo que hace el navegador.
r = session.get(r.headers["Location"], timeout=20)
check("Matrícula realizada" in r.text, "la portada muestra el aviso de matrícula")
check("de 12 lecciones completadas" in r.text, "la portada muestra el progreso")

print("\n== Lección con acceso ==")
r = session.get(f"{BASE}/?page_id={LESSON_PAGE}", timeout=20)
check(r.status_code == 200, "la lección responde 200")
check("Marcar como completada" in r.text, "se ofrece marcar la lección como completada")
check("Convoca\\Courses" not in r.text, "la página no filtra rutas internas")

m = re.search(r'name="_convoca_nonce" value="([^"]+)"', r.text)
nonce = m.group(1) if m else ""

print("\n== Completar lección ==")
r = session.post(
    f"{BASE}/wp-admin/admin-post.php",
    data={
        "action": "convoca_courses_complete",
        "lesson_id": str(LESSON_ID),
        "_convoca_nonce": nonce,
    },
    headers={"Referer": f"{BASE}/?page_id={LESSON_PAGE}"},
    timeout=20,
    allow_redirects=False,
)
check(r.status_code in (301, 302, 303), f"completar redirige (HTTP {r.status_code})")
check("convoca_msg=completed" in r.headers.get("Location", ""), "el aviso es «completed»")

r = session.get(f"{BASE}/?page_id={LESSON_PAGE}", timeout=20)
check("Lección completada" in r.text, "la lección queda marcada como completada")

print("\n== Nonce inválido ==")
r = session.post(
    f"{BASE}/wp-admin/admin-post.php",
    data={
        "action": "convoca_courses_enroll",
        "course_id": str(COURSE_ID),
        "_convoca_nonce": "nonce-falso",
    },
    timeout=20,
    allow_redirects=False,
)
check("convoca_msg=nonce" in r.headers.get("Location", ""), "un nonce falso se rechaza")

print("\n== Resumen ==")
print(f"Comprobaciones: {ok} correctas, {len(fallos)} fallidas")
for f in fallos:
    print(f"  - {f}")
sys.exit(0 if not fallos else 1)
