# Spec 002 — Evaluación del alumnado en Convoca Courses

**Estado**: aprobada (F0, 22-10-2026) · **Versión objetivo**: 0.3.0
**Origen**: `~/.hermes/plans/2026-10-22_courses-evaluacion.md` y decisiones de producto de JC del 22-10-2026.

## 1. Problema

Hoy un curso se «completa» viendo lecciones: `Certificate` se emite por progreso y **no hay ninguna
comprobación de aprendizaje**. Eso deja el plugin en repositorio de vídeos: no se puede certificar nada con
fundamento, y no hay forma de saber si el alumno aprendió.

Además, el desbloqueo secuencial **existe pero viene apagado** (`CPT_Curso::is_sequential()` → `false`), así
que de fábrica se puede saltar de la lección 1 a la 12 sin abrir la 2.

## 2. Objetivo

Convertir el módulo en un curso evaluable, **en la versión gratuita**, y reservar el PRO para lo que necesita
una organización: cohortes, socios, tutores y certificado con marca.

## 3. Decisiones cerradas (JC, 22-10-2026)

| Tema | Decisión |
|---|---|
| Dónde vive | Dentro de `convoca-courses`; no hay plugin aparte |
| Evaluación | **Gratuita**: exámenes, tareas, notas, certificado con nota |
| PRO | Capa de organización: precio de socio, convocatorias, recordatorios, varios correctores, QR del certificado, banco compartido, correo al grupo |
| Escala | Nota **0-100**, aprobado **50** por defecto, configurable por curso |
| Progreso | **No pondera** en la nota: solo desbloquea |
| Secuencia | **Obligatoria en cursos nuevos**: sin terminar una lección no se abre la siguiente |
| Examen | Bloqueado hasta completar las lecciones (todas por defecto; configurable a módulo o %). Comprobación en servidor |
| Fechas | Cursos **con** fecha límite y cursos **indefinidos**: la caducidad es opcional por curso |
| Monetización | Precio por **condición de socio** (gratis / precio especial / descuento %), por curso, vía contrato con `members` |

## 4. Alcance

Dentro: esquema de datos (4 tablas), desbloqueo secuencial por defecto, `Access::can_take_exam()`,
caducidad y ventana de matrícula, banco de preguntas y examen tipo test (única, múltiple, V/F), intentos y
tiempo máximo, tareas con entrega de texto o fichero y corrección manual, libro de notas con pesos, CSV,
certificado con nota y puerta de aprobado, precio de socio, y las siete funciones PRO.

Fuera, con motivo: respuesta libre autocorregida por IA (red, coste, promesa no medible), proctoring,
SCORM/xAPI, insignias, cupones y suscripciones.

## 5. Criterios de aceptación

1. Las cuatro tablas existen tras instalar **y** tras actualizar desde 0.2.3, sin perder progreso ni
   matrículas; el esquema se aplica una sola vez y es idempotente.
2. Curso nuevo: la lección 2 no se ve hasta completar la 1; el examen no se abre hasta completar las
   lecciones. Probado por URL directa, no solo por interfaz.
3. Examen de 5 preguntas: se abre, se responde, se corrige; nota y aprobado coinciden con el cálculo a mano.
4. Corrección correcta en los tres tipos: única (puntos completos), múltiple con una de más (0), V/F, en
   blanco (0).
5. Intentos limitados y tiempo máximo se respetan en el servidor (envío tardío → `expired`).
6. Tarea con fichero corregida con nota y comentario; aviso encolado en la cola de correo del núcleo.
7. Nota final con pesos; certificado bloqueado por debajo del mínimo; curso sin evaluación se comporta igual
   que hoy.
8. Curso con caducidad: al vencer, solo lectura (ve progreso, notas y certificado; no avanza ni examina).
9. Cuatro casos de precio medidos con importe real; sin `members`, precio general y sin fatal.
10. Las siete funciones PRO, bloqueadas sin licencia (comprobado en el servidor) y operativas con licencia.
11. 0.3.0 con tag, CHANGELOG, `.pot` al día (guardián), CI y Cobertura en verde, cobertura ≥ 12 %.
12. Documentación publicada en `convoca-docs`.

## 6. Compatibilidad

- Cursos existentes: **no** se les enciende el desbloqueo secuencial (encerraría a alumnado en marcha); se
  avisa en el admin.
- Cursos sin evaluación: certificado por progreso, como hoy.
- Shortcodes y ganchos actuales no cambian de firma.
- Sin `members` o sin `gateway`, el módulo funciona igual; solo desaparecen las funciones que dependen de ellos.
