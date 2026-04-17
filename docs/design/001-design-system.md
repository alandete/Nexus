# 001 — Seleccion del Design System

**Fecha:** 2026-04-16
**Estado:** Aprobado
**Contexto:** Reescritura del frontend de NexusApp v1 a v2

## Problema

La v1 usa Bootstrap 5.3 con CSS custom acumulado. No hay un sistema de diseno coherente: los componentes mezclan clases de Bootstrap con estilos ad-hoc, lo que dificulta la consistencia visual y el mantenimiento.

## Opciones evaluadas

1. **Bootstrap 5.3 (continuar)** — Familiar, pero perpetua el problema de mezcla de estilos
2. **Tailwind CSS** — Utility-first, flexible, pero no provee un lenguaje de componentes con specs de UX
3. **Atlassian Design System** — Design system completo con tokens, patrones, componentes documentados y lineamientos de UX/accesibilidad

## Decision

**Atlassian Design System** como base conceptual. No se usa Atlaskit (React), sino que se replican los tokens, patrones y specs en CSS puro sobre PHP.

## Justificacion

- Nexus es una herramienta de productividad para equipos — el mismo segmento que Jira/Confluence
- ADS provee tokens semanticos (color, tipografia, espaciado, elevacion) que garantizan consistencia
- Los patrones de UX (formularios, navegacion, estados) estan pensados para apps de trabajo diario
- Se eliminan las dependencias de Bootstrap CSS y JS
- Se mantiene Bootstrap Icons (solo la fuente de iconos, sin CSS framework)

## Consecuencias

- Todos los componentes se construyen desde cero con tokens ADS
- No hay dependencia de CDN de Bootstrap (solo iconos)
- Los tokens se definen en `assets/css/variables.css` con prefijo `--ds-`
- Los tokens especificos de la app usan prefijo `--app-`
