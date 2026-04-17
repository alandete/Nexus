# 003 — Uso del color primario para identidad de marca

**Fecha:** 2026-04-16
**Estado:** Aprobado
**Contexto:** Definicion de donde aplicar el color primario de la app (--app-brand: #585d8a)

## Problema

Atlassian DS usa azul (#0C66E4) como color de accion por defecto. Si todo usa los tokens genericos del DS, la app pierde identidad visual. Necesitamos que el usuario perciba que esta usando Nexus, no un producto generico.

## Decision

Usar `--app-brand` (#585d8a) en elementos especificos que refuerzan pertenencia, manteniendo los tokens del DS para el resto de la interfaz.

### Elementos con color primario

| Elemento | Token | Proposito |
|----------|-------|-----------|
| Sidebar: link activo (texto) | `--app-brand` | Indica seccion actual |
| Sidebar: link activo (fondo) | `rgba(--app-brand-rgb, 0.08)` | Fondo sutil de seleccion |
| Sidebar: tooltips | `--app-brand` | Refuerzo visual al explorar |
| Top bar: icono de marca | `--app-brand` | Identidad en la barra superior |
| Avatar fallback (inicial) | `--app-brand` | Consistencia con la marca |
| Login: boton submit | `--app-brand` | Primera interaccion con la app |
| Login: panel de branding | `linear-gradient(--app-brand, --app-brand-dark)` | Presentacion de marca |

### Elementos que NO usan color primario

| Elemento | Token | Razon |
|----------|-------|-------|
| Botones generales | `--ds-background-brand-bold` (azul DS) | Acciones del sistema, no de marca |
| Links | `--ds-link` (azul DS) | Convencion web universal |
| Alertas, lozenges | Tokens semanticos del DS | Significado funcional, no de marca |
| Focus rings | `--ds-border-focused` (azul DS) | Accesibilidad estandar |

## Justificacion

El color primario se reserva para elementos de **identidad y navegacion**, no para acciones funcionales. Esto permite que la app sea reconocible sin romper las convenciones del DS para interaccion.

## Regla permanente

**Al crear cualquier componente o seccion HTML, los elementos clave de identidad deben usar `--app-brand`**, sin saturar la interfaz. Esto garantiza que al cambiar el color primario, toda la identidad visual de la app cambie automaticamente.

Elementos de identidad: navegacion activa, branding, tooltips, avatars, encabezados de seccion, indicadores de estado propio.

Elementos funcionales (NO usan brand): botones de accion genericos, links de texto, focus rings, alertas, validaciones.
