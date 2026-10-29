# Revisión jurídica de la renuncia al desistimiento (art. 103.m TRLGDCU)

Revisión del texto de la casilla y del registro probatorio, con el texto consolidado del TRLGDCU publicado
por el BOE (última actualización, 28/02/2026) y el RGPD. Fecha: 29 de octubre de 2026.

> Aviso de alcance: esto es una revisión técnica del cumplimiento, con las fuentes citadas y el texto
> literal comprobado. No sustituye el dictamen de un abogado si la asociación necesita defender una
> reclamación concreta.

## 1. Qué dice exactamente la ley

Art. 103.m del TRLGDCU (redacción del art. 82.13 del Real Decreto-ley 24/2021, en vigor desde el
28/05/2022). El derecho de desistimiento **no se aplica** a:

> «m) El suministro de contenido digital que no se preste en un soporte material cuando la ejecución haya
> comenzado y, si el contrato impone al consumidor o usuario una obligación de pago, cuando se den las
> siguientes condiciones:
> 1.º El consumidor o usuario haya otorgado su consentimiento previo para iniciar la ejecución durante el
> plazo del derecho de desistimiento.
> 2.º El consumidor o usuario haya expresado su conocimiento de que, en consecuencia, pierde su derecho de
> desistimiento; y
> 3.º El empresario haya proporcionado una confirmación con arreglo al artículo 98.7 o al artículo 99.2.»

Tres precisiones que cambian el diseño, no solo la redacción:

1. **No es «que conste», es una confirmación activa del empresario.** La tercera condición no se cumple
   guardando un registro: exige que el empresario **haya proporcionado** una confirmación en soporte
   duradero. Art. 98.7: la confirmación se facilita «en un plazo razonable después de la celebración del
   contrato a distancia» e incluye, cuando proceda, «la confirmación del previo consentimiento expreso del
   consumidor y usuario y del conocimiento por su parte de la pérdida del derecho de desistimiento».
   Un registro interno sin correo de confirmación **no activa la excepción**.
2. **La pérdida es del contenido digital, no del contrato entero.** El precepto habla de suministro de
   contenido digital; las prestaciones no digitales (directos, tutorías, material impreso) quedan fuera y
   siguen siendo reembolsables.
3. **La ejecución tiene que haber comenzado.** Antes de consumir, no hay excepción: si alguien compra y no
   abre nada, desiste y se le devuelve.

## 2. Cómo queda el texto de la casilla (revisado)

> Quiero empezar el curso ahora, sin esperar a que terminen los 14 días naturales de desistimiento. Sé que,
> al abrir el contenido digital del curso —las lecciones y sus materiales—, empieza su ejecución y pierdo el
> derecho de desistimiento sobre ese contenido digital, de modo que no podré pedir la devolución de lo que ya
> haya consumido (art. 103.m del TRLGDCU). Sé también que la parte del curso que no sea contenido digital
> —sesiones en directo, tutorías o material impreso, si las hubiera— sigue sujeta al derecho de desistimiento,
> aunque marque esta casilla. Entiendo que esta casilla es opcional: si no la marco, hago el curso igual y
> conservo los 14 días naturales completos. Y sé que la asociación me enviará por correo la confirmación de
> esta contratación y de este consentimiento, y que la aceptación quedará registrada con su fecha y los datos
> técnicos de la conexión, según la política de privacidad.

Correspondencia con el precepto:

| Requisito legal | Dónde se cumple |
|---|---|
| Consentimiento **previo** para iniciar la ejecución durante el plazo | «Quiero empezar el curso ahora, sin esperar…»; casilla marcada antes de matricular |
| Consentimiento **expreso** | Casilla propia, separada de la de condiciones, que hay que marcar |
| **Conocimiento** de la pérdida del derecho | «pierdo el derecho de desistimiento sobre ese contenido digital» + consecuencia («no podré pedir la devolución de lo ya consumido») |
| **Confirmación** del empresario (98.7) | «me enviará por correo la confirmación de esta contratación y de este consentimiento»; el correo se manda al matricular y se marca la fecha |
| **Alcance**: solo contenido digital | «la parte del curso que no sea contenido digital … sigue sujeta al derecho de desistimiento» |
| **Consentimiento libre** (no abusiva: art. 82.1 y 89 TRLGDCU) | «esta casilla es opcional: si no la marco, hago el curso igual y conservo los 14 días»; en la práctica, la casilla va sin marcar y sin `required`, y la matrícula se completa sin ella |
| **Transparencia** (arts. 5 y 7 TRLGDCU; art. 13 RGPD) | «la aceptación quedará registrada con su fecha y los datos técnicos de la conexión, según la política de privacidad» |

Lo que se ha evitado a propósito:

- No se dice que la renuncia cubra «el curso entero»: sería una cláusula abusiva por exceso de alcance
  (art. 89.2 TRLGDCU, cláusulas que excluyen derechos del consumidor sin previsión legal).
- No se condiciona la compra: la casilla no es obligatoria y el flujo alternativo existe y está probado.
- No se presenta como renuncia genérica a reclamar: se ciñe a lo que el 103.m permite.

## 3. Qué se ha tenido que cambiar en el código

1. **Confirmación en soporte duradero (condición 3.ª)**: correo `Confirmación de tu matrícula en «<curso>»`
   con fecha, importe, texto literal aceptado (o, si no hubo renuncia, el recordatorio de los 14 días), cómo
   desistir, datos de la entidad y aviso de conservación. Se marca la fecha de envío en el registro.
2. **Prueba aparte de la matrícula**, para que borrar la matrícula no destruya la prueba:
   tabla `convoca_consent_pruebas` con texto del consentimiento, texto de la renuncia, huella `sha256`,
   fecha UTC, IP, navegador, ámbito y versión del plugin.
3. **Retención de cinco años** desde la aceptación, con purga diaria automática.
4. **Certificados no revocados**: registro propio (`convoca_certificados`) para que la verificación por
   código no dependa de que la matrícula siga existiendo.
5. **Ámbito por curso**: la casilla solo se ofrece en cursos marcados como 100 % digital y con la renuncia
   activada en la instalación; en un curso mixto no aparece y, si se fuerza por POST, se descarta.

## 4. Conservación de la prueba durante cinco años

Base jurídica:

- **Plazo**: cinco años desde la aceptación, alineado con el artículo 1964.2 del Código Civil (prescripción
  de las acciones personales) y con el plazo de responsabilidad por falta de conformidad en contenidos y
  servicios digitales (arts. 114 bis y 120 TRLGDCU, en la redacción del Real Decreto-ley 7/2021). Es el
  tiempo en que alguien puede reclamar por esta contratación.
- **Base del tratamiento**: ejecución del contrato (art. 6.1.b RGPD) para el consentimiento y el registro de
  la renuncia; interés legítimo en la defensa frente a reclamaciones (art. 6.1.f RGPD) para la conservación
  más allá de la relación contractual.
- **Límite**: el derecho de supresión no alcanza a estos datos mientras el plazo esté vivo (art. 17.3.e
  RGPD: tratamiento necesario para la formulación, ejercicio o defensa de reclamaciones). De ahí que la
  prueba viva **fuera** de la matrícula: así se puede atender una supresión de la matrícula sin destruir la
  prueba y sin incumplir el RGPD.
- **Minimización**: se guarda lo imprescindible para acreditar (texto, huella, fecha, IP y navegador) y el
  correo de la persona, que es lo que permite vincular la prueba con quien reclama. Nada más.
- **Deber de información**: la política de privacidad tiene que decir qué se conserva, cuánto y por qué.

## 5. Certificados: no se revocan

Un certificado acredita una formación que la persona completó. Desistir no borra lo aprendido y una baja no
convierte en falso un certificado emitido, así que **no se revoca**. Consecuencias:

- La verificación pública funciona aunque la matrícula se haya borrado (registro propio).
- La condición de «válido» no depende del estado de la matrícula, del pago ni de la relación contractual.
- Si en algún momento se detectara un certificado emitido por error, se invalida con una nota en el registro
  y se documenta el motivo; no se borra en silencio.

## 6. Riesgos que quedan, y cómo se mitigan

| Riesgo | Mitigación |
|---|---|
| Que alguien diga que la casilla era obligatoria para comprar | La casilla va sin marcar, sin `required` y separada; hay pruebas que lo fijan y el camino alternativo está verificado |
| Que la confirmación no salga (SMTP caído) y la excepción no opere | El envío se intenta al matricular; conviene además un aviso en el escritorio cuando un consentimiento no tiene confirmación enviada (pendiente) |
| Curso mixto con la casilla visible | El ámbito por curso lo impide: sin la marca «100 % digital», la casilla no se pinta |
| Reclamación sobre la parte no digital | El texto la excluye expresamente del alcance de la renuncia |
| Texto de fábrica sustituido por otro peor | La opción es editable, pero la revisión es del texto de fábrica: si se cambia, hay que revisarlo |

## 7. Pendientes que no son código

- Publicar en la web el **formulario de desistimiento** (art. 104 TRLGDCU) y enlazarlo desde el correo de
  confirmación, con dirección de correo dedicada.
- Decidir el **procedimiento de reembolso** (mismo medio de pago, plazo de 14 días naturales desde el
  desistimiento, art. 107.1 TRLGDCU).
- Revisar la **política de privacidad** con lo que dice el apartado 4.
- Si el curso incorpora directos o material impreso, **desmarcar** «100 % digital» ese curso.
