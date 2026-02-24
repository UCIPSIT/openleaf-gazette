# Joomla Content Plugin: Gaceta Flipbook

Plugin de contenido para Joomla 5 que permite mostrar una gaceta en formato flipbook.

## Modos

- `embed`: incrusta una URL externa (ideal para apariencia casi exacta de FlipHTML5).
- `native`: renderiza un PDF local/remoto con `pdf.js + StPageFlip`.

## Instalacion

1. Ir a `System -> Install -> Extensions` en Joomla Administrator.
2. Subir el zip del plugin.
3. Activar `Content - Gaceta Flipbook`.

## Uso en articulo

Embed:

```text
{gacetaflip mode="embed" url="https://online.fliphtml5.com/HmoralesZ/HCJ-gaceta-piloto-01/#p=26"}
```

Native:

```text
{gacetaflip mode="native" file="images/pdfs/gaceta.pdf" start="26" width="560" height="760" maxpages="0"}
```
