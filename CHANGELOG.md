# Changelog

Todos los cambios relevantes del proyecto se documentan en este archivo.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

## [2.0.0-alpha.2] — 2026-04-18

### Agregado
- **Pagina `/system`** (ultima de Ajustes): diagnostico, dependencias, entorno PHP, BD y permisos.
  - Seccion de diagnostico con boton "Ejecutar", resumen visual (OK / Advertencias / Errores) y lista de issues con categoria, detalle y sugerencia de solucion
  - Dependencias en grid: Ghostscript, ImageMagick CLI, Imagick PHP, GD, MySQL con version y uso
  - Entorno PHP: version, memoria, limites de upload/POST, ejecucion, timezone, extensiones cargadas
  - Base de datos: host, db, user, version (sin exponer password)
  - Permisos de directorios criticos (data, backups, uploads)
- **Endpoint POST a `diagnostics_actions.php`** consumido via AJAX con actualizacion del DOM sin recargar
- **Limpieza separada**: el checkbox "Limpiar temporales" al crear snapshots **ya no borra** el activity_log (solo /temp/). Para limpiar el log se usa el boton dedicado en `/activity`

### Agregado previamente
- **Pagina `/snapshots`** (copias de seguridad): listado, creacion, restauracion, favoritos y eliminacion.
  - 4 stats prominentes (total, espacio usado, ultima copia, favoritas)
  - Indicador de rotacion con slots usados por tipo (datos / completas) y hint sobre proteccion por favoritos
  - Filtros: busqueda + chips de tipo (Todas / Datos / Completas / Favoritas)
  - Tarjetas con icono por tipo, lozenges, nota del usuario, meta (fecha relativa, tamaño)
  - Favoritas con borde amarillo lateral y lozenge "Protegida" (no se eliminan por rotacion)
  - Slide panel para crear: radio tipo (data/full con descripcion inline), nota opcional, checkbox "Limpiar temporales"
  - Hint dinamico segun tipo seleccionado (datos se restaura / full solo descarga)
  - Spinner durante creacion, Toast con resultado
  - Restauracion con ConfirmModal accesible (solo backups de datos)
  - Delete bloqueado para favoritas con tooltip explicativo
- **Componente chip-group**: filtros tipo pill con estado active (color primario)
- **Funcion `formatFileSize()`** en `functions.php` (reutilizable desde cualquier pagina)

### Agregado previamente
- **Pagina `/activity`**: registro de actividad del sistema con:
  - Tabla densa con timestamp (fecha + hora en mono), usuario (nombre + @handle), modulo, accion (lozenge por variante), detalle, IP
  - Filtros: rango de fechas (desde/hasta), usuario, modulo, accion
  - Boton limpiar filtros rapido
  - Boton vaciar registro (admin only, confirmacion accesible)
  - Paginacion con ellipsis inteligente (25 por pagina)
  - Empty state contextual (sin datos vs sin resultados de filtro)
  - Lozenges de colores por tipo de accion (login=info, create=success, delete=danger, etc.)
- **Componente `pagination`**: paginacion reutilizable con botones prev/next, numeros, ellipsis, estado active
- **Pagina de error standalone**: 403/404/500 ahora se renderizan con layout propio (sin sidebar/topbar), pagina enfocada con icono, codigo grande, titulo, descripcion y botones "Volver al inicio" + "Regresar"
- **`ErrorDocument`** en `.htaccess`: errores de Apache (403, 404, 500) redirigen a paginas custom
- **`.htaccess` en `backups/`**: denegacion explicita de acceso directo (defensa en profundidad)
- **`<base href="/">`** en paginas principales: resuelve paths relativos de assets desde raiz (arregla CSS roto cuando Apache sirve error page desde otro directorio)

### Cambiado
- Ruta `/backups` renombrada a `/snapshots` para evitar colision con el directorio fisico `backups/`
- Etiquetas `settings_overview.project_desc` y `settings_overview.application_desc` consistentes
- Botones con variante primary/default/warning/danger/success ahora tienen `color` explicito en `:hover` (evita que `a:hover` global sobrescriba el color del texto)
- `a.btn:hover` ya no hereda el `text-decoration: underline` del `a:hover` global

### Agregado previamente
- **Pagina `/integrations`**: gestion de claves API para servicios externos:
  - iLovePDF / iLoveIMG con email, contrasena, proyecto, Public Key y Secret Key
  - Lozenges "Guardada"/"No configurada" para indicar estado
  - Campos con toggle de mostrar/ocultar (ojo) y placeholder "Dejar vacio para conservar la actual"
  - Boton "Probar conexion" con feedback visual (exito/error) y render del plan
  - Guia colapsable paso a paso sobre como obtener las claves
  - Alert informativo de seguridad (AES-256)
  - Claves que se preservan si el campo se deja vacio al guardar (nunca se exponen)
- **Pagina `/application`** (antes `/project`): configuracion de la aplicacion con nuevos campos que antes requerian editar codigo:
  - Identidad: nombre, eslogan, descripcion, logo, **favicon**, **color primario**, **color de acento** (con preview en vivo)
  - Empresa: nombre, direccion, email, telefono, sitio web
  - Operacion: **zona horaria** (hoy era hardcoded), **idioma por defecto**, **modo mantenimiento** con mensaje personalizable y lista de IPs permitidas
  - Privacidad: modo privado (noindex/nofollow)
  - Barra de acciones sticky al final del form
  - Preview de colores en vivo (boton demo, lozenge, dot)
- **Modo mantenimiento** en `index.php`: al activarse, bloquea acceso a no-admins con HTTP 503; admins e IPs permitidas pueden seguir accediendo
- **Pagina standalone `/maintenance`**: mensaje personalizado + enlace discreto a login para admins
- **Inyeccion de colores de marca**: si hay colores configurados, se inyectan en `<head>` como override de `--app-brand` y `--app-accent`
- **Aplicacion de timezone e idioma** desde `projectinfo.json` en runtime
- **Pagina `/settings`**: overview con cards de acceso a cada sub-seccion
- **Pagina `/users`**: gestion completa de usuarios con:
  - Tabla densa con avatares, nombre, rol, idioma, ultimo acceso, estado
  - Busqueda en vivo, filtros por rol y estado
  - Stats compactas (total, activos, admins)
  - Breadcrumb a `/settings`
  - CRUD via slide panel con validacion inline y preview de foto
  - Lozenges por rol (Admin bold, Editor info, Viewer default)
- **Pagina `/manage-alliances`**: gestion de alianzas con:
  - Vista tarjetas (default) y tabla, con toggle
  - Busqueda y filtros por estado
  - Stats (total, activas, facturables)
  - Tarjetas con bandera, color institucional, stack de responsables, contador de archivos
  - CRUD via slide panel con archivos (upload/delete), color picker, responsables (usuario/externo)
- **`ConfirmModal`** reutilizable: dialogo accesible con Promise API, reemplaza `confirm()` nativo
- **`Toast`** (flag ADS): sistema de notificaciones no intrusivas con variantes (success/danger/warning/info)
- **Breadcrumb**: componente de navegacion con link a padre y current page
- **`relativeTime()`** en functions.php: tiempo relativo con i18n (time.just_now, time.minutes_ago, etc.)

### Cambiado
- **URLs limpias para cada sub-seccion de Ajustes**:
  - Antes: `/settings#usuarios`, `/settings#alianzas`, etc. (hash-based)
  - Ahora: `/users`, `/manage-alliances`, `/application`, `/integrations`, `/backups`, `/system`, `/activity`
- Sidebar actualizado con nuevos hrefs y estado active por URL
- Routing en `index.php` con lista expandida de paginas validas
- Renombre: "Proyecto" -> "Aplicacion" (descriptivo)
- `projectinfo_actions.php` extendido con validacion de colores hex, timezone, favicon y modo mantenimiento
- Ancho del slide panel: 420px -> 560px

---

## [2.0.0-alpha.1] — 2026-04-17

### Agregado
- Paginas de error: 404, 403, 500 con codigo de color primario y traducciones ES/EN

### Eliminado
- `includes/footer.php` — ya no se usa, la version esta en el sidebar

---

## [2.0.0-alpha.1] — 2026-04-16

### Agregado
- Design tokens CSS basados en Atlassian Design System (colores, tipografia, espaciado, elevacion)
- UI Kit completo: botones, formularios, cards, lozenges, tags, tabs, tablas, alerts, tooltips, avatars, toggle, spinner, progress, empty state, toasts
- Layout: top bar + sidebar colapsable con expand on hover
- Sistema global de tooltips (`data-tooltip` en cualquier elemento)
- Login: split-screen con panel de branding y formulario
- Slide panel reutilizable con focus trap y accesibilidad
- Page loader con spinner de color primario
- Dashboard: stats cards, accesos rapidos, proximas tareas, actividad reciente, progreso del proyecto
- Documentacion (`/documentation`): TOC lateral con scroll-spy, busqueda, secciones colapsables
- Traducciones ES/EN para todos los modulos nuevos
- Decisiones de diseno documentadas en `docs/design/` (001-004)
- `.gitignore` con proteccion de archivos sensibles
- Archivos `.example` para configuracion local

### Cambiado
- Frontend reescrito desde cero (sin Bootstrap CSS, solo Bootstrap Icons)
- Sidebar reemplaza navbar horizontal (patron Jira/Confluence)
- Sidebar se superpone al contenido en lugar de empujarlo
- Tipografia 16px en mobile, 14px en tablet+ para legibilidad
- Color primario (`--app-brand`) aplicado en elementos clave de identidad
- `APP_SECRET_KEY` movida a `config/secret.php` (gitignored)
- Cache CSS/JS deshabilitado durante desarrollo

### Corregido
- Auditoria ADS: 14 correcciones de accesibilidad (aria-hidden, heading hierarchy, aria-controls, roles)
- Inline styles movidos a clases CSS
- Cards con overflow hidden para evitar desbordamiento en mobile
- Alineacion de iconos del sidebar en estado colapsado

### Backend (sin cambios)
- Todos los archivos de `includes/*_actions.php` copiados de v1
- Config, auth, funciones, traducciones, plantillas copiadas de v1

[2.0.0-alpha.1]: https://github.com/alandete/Nexus/commits/main
