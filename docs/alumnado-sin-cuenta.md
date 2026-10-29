# Alumnado sin cuenta de WordPress

El correo es la identidad. No se crea ningún `WP_User` y no hay contraseñas.

## El recorrido

1. **Matrícula** — `[convoca_matricula course="123"]`. Pide **correo y nombre** y una casilla obligatoria de
   consentimiento. Al terminar, el plugin **envía el acceso** al correo (enlace y código) en el mismo paso.
2. **Entrada** — el enlace del correo abre una sesión en una cookie firmada; si el enlace no llega o caduca, el
   mismo correo trae un **código de 6 cifras**. También hay `[convoca_acceso]` para pedir uno nuevo.
3. **Curso** — lecciones y progreso van por identidad (`Progress::complete_identidad`).
4. **Examen** — `Attempts` guarda el intento con `identidad_id`; la puerta es tener matrícula en el curso.
5. **Certificado** — se emite al aprobar y se verifica públicamente por su código, con el nombre de la persona
   y su nota.

## Qué hay que configurar

**Cursos → Ajustes:**

| Campo | Para qué |
|---|---|
| Datos de la entidad (nombre, NIF, correo de desistimiento) | Condiciones de venta y recibo |
| Texto de las condiciones | Sustituye al de fábrica; admite marcadores `{{nombre}}`, `{{nif}}`… |
| Casilla de consentimiento | Texto que se marca al matricularse. Obligatorio |
| Renuncia expresa al desistimiento | **Apagado por defecto.** Ver abajo |
| Ficha del curso | Plantilla propia del plugin, apagada por defecto |

## El consentimiento y el artículo 103.m

Sin la casilla marcada **no hay matrícula**. Se guarda la fecha en `_convoca_consentimiento`.

La **renuncia al desistimiento** (excepción de contenido digital, art. 103.m TRLGDCU) está **apagada por
defecto** y solo debe encenderse si la entidad tiene el texto revisado: exige consentimiento previo, expreso y
**separado**, que es justo esa segunda casilla. Mientras esté apagada, los 14 días se respetan completos.

Si se activa y la persona la marca, la matrícula guarda `_convoca_renuncia_desistimiento = 1`.

## Qué NO hace

- No migra las matrículas antiguas: siguen con su usuario de WordPress.
- No crea fichas de socio. El vínculo con Members es por correo y solo anota `_convoca_member_id`.
- No hay contraseñas ni recuperación de contraseña: el acceso es siempre por correo.

## Cómo se comprueba

```bash
# En la demo, con un correo cualquiera:
curl -s "https://demo.getconvoca.app/e2e-matricula/" | grep -o 'name="_convoca_nonce" value="[^"]*"'
# POST a /wp-admin/admin-post.php con action=convoca_matricula, course, email, nombre, consentimiento=1
# El correo sale con el enlace y el código; el enlace deja la cookie convoca_identidad.
```
