# 002 — Patron de layout: Side navigation + Top bar

**Fecha:** 2026-04-16
**Estado:** Aprobado
**Contexto:** Definicion del layout principal de la aplicacion

## Problema

La v1 usa un top navbar horizontal con 5 items principales. Los modulos con sub-secciones (Settings tiene 7+, Utilidades tiene 3 herramientas) requieren navegacion interna adicional. Esto genera:

- 2+ clics para llegar a una funcion especifica (ej: Utilidades > tab PDF)
- Settings se convierte en un mega-modulo con sub-menu propio
- La navbar consume 56-60px de espacio vertical permanente
- No hay forma de ver todas las opciones disponibles sin navegar

## Opciones evaluadas

### A. Top navbar horizontal (v1)
- **Pro:** Simple, familiar
- **Contra:** No escala con sub-secciones, desperdicia espacio vertical, oculta profundidad

### B. Side navigation + top bar minima
- **Pro:** Soporta secciones colapsables, acceso directo a sub-paginas, maximiza area de contenido, patron usado por Jira/Confluence
- **Contra:** Consume espacio horizontal (mitigable con modo colapsado)

### C. Top navbar + sidebar contextual
- **Pro:** Sidebar cambia segun el modulo activo
- **Contra:** Inconsistente, el usuario no sabe que hay en otros modulos

## Decision

**Opcion B: Side navigation + top bar minima**

### Estructura

```
+--[Top bar: 40px]-----------------------------+
| [=] Logo  Nexus             [avatar ▼]       |
+------+----------------------------------------+
| SIDE | CONTENIDO PRINCIPAL                    |
| NAV  |                                        |
| 240px|                                        |
|      |                                        |
| Home |                                        |
| Tareas                                        |
| Alianzas                                      |
|   UNIS                                        |
|   UNAB                                        |
| Utilidades                                    |
|   Preguntas                                   |
|   PDF                                         |
|   Imagenes                                    |
| Ajustes                                       |
|   Usuarios                                    |
|   Alianzas                                    |
|   Proyecto                                    |
|   ...                                         |
+------+----------------------------------------+
```

### Responsive

| Viewport | Sidebar | Top bar |
|----------|---------|---------|
| Mobile (<768px) | Oculto, drawer desde izquierda via hamburger | Logo + hamburger + avatar |
| Tablet (768-991px) | Colapsado a iconos (48px), expande al hover | Logo + avatar |
| Desktop (992px+) | Expandido (240px), colapsable con toggle | Logo + avatar + nombre |

## Justificacion

1. **Reduce pasos** — Sub-secciones visibles directamente: "Optimizar PDF" es 1 clic, no 2
2. **Escala mejor** — Settings con 7 sub-items se expande naturalmente en el sidebar
3. **Mas area de contenido** — Sin navbar de 56px, el contenido usa todo el alto del viewport
4. **Patron Atlassian** — Jira, Confluence y Bitbucket usan este patron. Los componentes del DS estan disenados para este layout
5. **Consistencia** — La navegacion es siempre visible y predecible, sin menus ocultos en desktop

## Consecuencias

- `header.php` se reescribe como top bar minima (logo, toggle, avatar)
- Se crea `sidebar.php` como componente de navegacion principal
- `index.php` cambia su estructura HTML de single-column a sidebar + content
- `footer.php` se simplifica o se elimina (la info pasa al sidebar)
- El login mantiene su layout split-screen independiente (no usa sidebar)
- Se necesitan nuevos tokens CSS para sidebar width y transiciones
