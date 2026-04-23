# OpenLeaf Gazette - Guia de uso en Joomla Admin

## 1) Instalar plugin

1. Entra a `Joomla Administrator`.
2. Ve a `System -> Install -> Extensions`.
3. Sube el ZIP del plugin.
4. Ve a `System -> Manage -> Plugins` y habilita `Content - OpenLeaf Gazette`.

## 2) Configuracion base (1 PDF por defecto)

1. Abre el plugin `Content - OpenLeaf Gazette`.
2. Ajusta estos campos:
   - `Modo por defecto`: `Native`
   - `PDF por defecto (desde Admin)`: selecciona tu PDF en Media Manager
   - `Modo visual por defecto (native)`: `Screen`
   - `Intentar fullscreen automaticamente`: `No`
   - `Mostrar boton descargar (native)`: `Si`
3. Guarda.

Con esto, el shortcode minimo funciona sin poner ruta del PDF:

```text
{openleaf}
```

Comportamiento visual actual del visor nativo:
- La numeracion visible se muestra como `Numeracion del PDF` y se renderiza fuera del PDF.
- El boton inferior descarga el PDF original en su resolucion completa.

## 3) Administrar varios PDFs desde configuracion del plugin

Usa el campo `Mapeo PDF por seccion (opcional)` para cargar multiples PDFs desde una sola configuracion.

Formato por linea (acepta ambos):

```text
clave=ruta_pdf
clave|ruta_pdf
```

Ejemplo recomendado:

```text
# Claves libres para shortcode section/key
revista-enero=images/pdfs/revista-enero.pdf
revista-febrero=images/pdfs/revista-febrero.pdf

# Mapeo automatico por Itemid de menu
itemid:125=images/pdfs/gaceta-escuela.pdf
menu:140=images/pdfs/gaceta-investigacion.pdf

# Mapeo automatico por categoria de articulo
catid:8=images/pdfs/gaceta-extension.pdf
category:12=images/pdfs/gaceta-postgrado.pdf

# Fallback global del mapa
default=images/pdfs/gaceta-general.pdf
```

Notas:
- Lineas que empiezan con `#` o `;` se ignoran (comentarios).
- Si repites una clave, prevalece la ultima.
- La ruta debe ser relativa al sitio, por ejemplo `images/pdfs/archivo.pdf`.

## 4) Como usarlo por seccion

Modo 1 (clave manual por shortcode):

```text
{openleaf section="revista-enero"}
```

Modo 2 (automatico por menu/categoria):

```text
{openleaf}
```

Si existe `itemid:ID` o `catid:ID` en el mapa, el plugin toma ese PDF automaticamente.

Prioridad de resolucion (modo native):
1. `file` o `pdf` en el shortcode.
2. `section` o `key` en el shortcode (busca en el mapa).
3. `itemid:ID` o `menu:ID` en el mapa.
4. `catid:ID` o `category:ID` en el mapa.
5. `context:...`, luego `default` o `*` en el mapa.
6. `PDF por defecto (desde Admin)`.

## 5) Donde usarlo

- Articulo (`com_content`): pega `{openleaf}` en el texto del articulo.
- Modulo `Custom` en posicion de plantilla:
  1. Crea/edita modulo tipo `Custom`.
  2. Pega `{openleaf}`.
  3. Activa `Prepare Content = Yes`.
- Componente propio:
  - Ejecuta `onContentPrepare` sobre el texto antes de renderizar la vista.

## 6) Ejemplo con parametros manuales

```text
{openleaf mode="native" file="images/pdfs/ucips-gazette-base3f-27012026-prueba.pdf" fit="screen" autofullscreen="0" download="1"}
```

## 7) Permitir subida de PDF por roles no-admin (ACL)

Si deseas que `Editor`, `Publisher` u otros roles suban PDFs (sin depender del admin), configuralo en permisos de Joomla:

1. Ve a `Content -> Media`.
2. Entra a `Options -> Permissions`.
3. Selecciona el grupo objetivo (ejemplo: `Editor` o `Publisher`).
4. Define como minimo:
   - `Create = Allowed` (necesario para subir PDF)
   - `Edit Own = Allowed` (recomendado para gestionar sus propios archivos)
5. Guarda.

Flujo recomendado por rol no-admin:

1. Subir PDF en Media Manager, por ejemplo en `images/pdfs/`.
2. En su articulo/modulo agregar:

```text
{openleaf mode="native" file="images/pdfs/mi-gaceta.pdf"}
```

3. Guardar y publicar.

Con esto, el plugin no requiere que el usuario sea administrador para publicar una gaceta con PDF propio.

Si el PDF no aparece al seleccionar `PDF por defecto (desde Admin)`:

1. Verifica usar version del plugin `>= 1.3.2` (selector ajustado a documentos).
2. Revisa `System -> Global Configuration -> Media` y confirma que `doc_extensions` incluya `pdf`.
3. Confirma permisos ACL del grupo para crear/subir archivos en Media Manager.

## 8) Organizacion recomendada para indice de Gaceta/Revista (vol-01, vol-02, ...)

Estructura sugerida de archivos en Media Manager:

```text
images/pdfs/gaceta/2026/vol-01.pdf
images/pdfs/gaceta/2026/vol-02.pdf
images/pdfs/revista/2026/vol-01.pdf
images/pdfs/revista/2026/vol-02.pdf
```

Plantilla sugerida para `Mapeo PDF por seccion (opcional)`:

```text
# Gaceta
gaceta-2026-vol-01=images/pdfs/gaceta/2026/vol-01.pdf
gaceta-2026-vol-02=images/pdfs/gaceta/2026/vol-02.pdf

# Revista
revista-2026-vol-01=images/pdfs/revista/2026/vol-01.pdf
revista-2026-vol-02=images/pdfs/revista/2026/vol-02.pdf
```

Modelo recomendado de contenido:

1. Pagina indice (solo listado de enlaces).
2. Una pagina por volumen (render del PDF).

Ejemplo pagina de volumen:

```text
{openleaf section="gaceta-2026-vol-01"}
```

Ventaja: escalable, facil de mantener y evita cargar muchos PDFs pesados en una sola pagina.

Guia extendida:
- `docs/ORGANIZACION_INDICE_ES.md`

## 9) Distribucion y release

Flujo recomendado para distribuir una nueva version:

1. Sube cambios al repo y confirma `version` en `gacetaflipbook.xml`.
2. Genera ZIP local:

```bash
./scripts/build-zip.sh
```

3. Commit + push a `main`.
4. Crea tag semantico y publicalo:

```bash
git tag vX.Y.Z
git push origin vX.Y.Z
```

5. Verifica en GitHub Actions:
- Job `Build Joomla Plugin ZIP` en verde.
- Release creada con el ZIP adjunto.

## 10) Capturas de referencia

![Dashboard Joomla Admin](docs/screenshots/01-admin-dashboard.png)
![Listado de plugins OpenLeaf](docs/screenshots/02-plugin-list-openleaf.png)
![Configuracion del plugin](docs/screenshots/03-plugin-config-openleaf.png)
![Ejemplo de mapeo por seccion](docs/screenshots/04-plugin-config-map-example.png)
