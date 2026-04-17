# 004 — Pagina de documentacion

**Fecha:** 2026-04-16
**Estado:** Aprobado
**Contexto:** Reescritura de la seccion de documentacion de v1

## Problema

En v1, la documentacion era un partial dentro de Settings, con un TOC de 4 items sin busqueda, sin scroll-spy, y sin sub-navegacion. El contenido era un muro de tablas pequenas dificil de explorar.

## Decision

Pagina independiente (`/documentation`) con:

### Layout
- **TOC lateral fijo** (sticky) con secciones colapsables, scroll-spy activo e indicador de seccion
- **Busqueda en el TOC** que filtra secciones y sub-secciones en tiempo real
- **Contenido principal** con ancho maximo de 800px para legibilidad optima

### Estructura del contenido
- 4 secciones principales: Vista general, Alianzas, Utilidades, Ajustes
- Cada seccion con sub-articulos navegables desde el TOC
- Feature grid visual para caracteristicas (iconos + descripcion)
- Code blocks con etiquetas descriptivas
- Tablas compactas para especificaciones
- Listas con pasos numerados para flujos de trabajo

### Accesibilidad
- TOC con `role="tree"` y `aria-expanded`
- Articulos con `scroll-margin-top` para compensar el topbar fijo
- Busqueda con `aria-label`
- Navegacion por teclado en el TOC

### Responsive
- Mobile: TOC oculto, contenido ocupa todo el ancho
- Tablet+: TOC lateral de 260px con sticky positioning

### Nota tecnica
La ruta es `/documentation` (no `/docs`) porque existe un directorio fisico `docs/` que causa conflicto con Apache mod_rewrite.

## Mejoras sobre v1

| Aspecto | v1 | v2 |
|---------|----|----|
| Ubicacion | Partial dentro de Settings | Pagina independiente |
| TOC | 4 items estaticos | Scroll-spy + colapsable + busqueda |
| Sub-navegacion | No | Si, por articulo |
| Busqueda | No | Filtro en tiempo real |
| Feature overview | Lista de texto | Grid con iconos |
| Code examples | Pre basico | Bloques con etiqueta |
| Responsive | Sidebar estatico | TOC oculto en mobile |
