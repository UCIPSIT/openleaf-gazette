# OpenLeaf Gazette - Organizacion de indice por volumen

## Objetivo

Organizar Gaceta y Revista para publicar multiples volumenes (`vol-01`, `vol-02`, etc.) con mantenimiento simple y escalable.

## 1) Estructura de carpetas recomendada

```text
images/pdfs/gaceta/<anio>/vol-01.pdf
images/pdfs/gaceta/<anio>/vol-02.pdf
images/pdfs/revista/<anio>/vol-01.pdf
images/pdfs/revista/<anio>/vol-02.pdf
```

Ejemplo real:

```text
images/pdfs/gaceta/2026/vol-01.pdf
images/pdfs/gaceta/2026/vol-02.pdf
images/pdfs/revista/2026/vol-01.pdf
images/pdfs/revista/2026/vol-02.pdf
```

## 2) Convencion de claves para el mapeo

Usar este patron:

```text
<tipo>-<anio>-vol-<numero>
```

Ejemplos:
- `gaceta-2026-vol-01`
- `gaceta-2026-vol-02`
- `revista-2026-vol-01`

## 3) Plantilla para `Mapeo PDF por seccion (opcional)`

```text
# Gaceta
gaceta-2026-vol-01=images/pdfs/gaceta/2026/vol-01.pdf
gaceta-2026-vol-02=images/pdfs/gaceta/2026/vol-02.pdf

# Revista
revista-2026-vol-01=images/pdfs/revista/2026/vol-01.pdf
revista-2026-vol-02=images/pdfs/revista/2026/vol-02.pdf
```

## 4) Estructura de contenido en Joomla

Recomendado:

1. Pagina indice (solo enlaces a volumenes).
2. Una pagina por volumen (solo un shortcode `{openleaf ...}`).

Ejemplo:

- Pagina: `Gaceta 2026 - Indice`
- Pagina: `Gaceta 2026 - Vol 01` con:

```text
{openleaf section="gaceta-2026-vol-01"}
```

- Pagina: `Gaceta 2026 - Vol 02` con:

```text
{openleaf section="gaceta-2026-vol-02"}
```

## 5) Flujo operativo por roles (sin admin global)

1. `Editor/Publisher` sube el PDF en `Content -> Media` (ACL `Create` permitido).
2. `Editor/Publisher` actualiza contenido del volumen con `{openleaf section="..."}`.
3. `Admin de plugin` mantiene el mapa central `section_pdf_map` (si aplica).

## 6) Buenas practicas

- Evitar muchos viewers en una misma pagina.
- Mantener nomenclatura consistente por anio y volumen.
- Usar paginas indice para navegacion.
- Mantener un PDF por pagina para mejor rendimiento.
