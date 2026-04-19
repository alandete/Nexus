# Changelog

Todos los cambios relevantes del proyecto se documentan en este archivo.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

## [2.0.0-alpha.3] — 2026-04-19 (en desarrollo)

### Deuda tecnica priorizada (resolver antes de Sub-fase 4.5 Reportes)

- **Backend `timer_discard` no resetea el status de la tarea**. Cuando el usuario descarta un timer recien iniciado, el entry se borra pero la tarea queda en `in_progress` sin ninguna sesion. Genera "tareas fantasma" en Activas y falsea totales en reportes futuros.
  - Workaround actual: el frontend filtra activas con `total_seconds > 0` o con al menos un entry en rango.
  - Fix pendiente en backend: `timerDiscard` debe verificar si la tarea tenia otras entries; si no, setear status a `pending`. Ver `includes/tasks_actions.php:472` (hay TODO inline).

### Iteracion continua de 4.2 (ronda 2)

- **Boton "Nueva tarea"** en el page-header (primary con icono +). Abre el slide panel en modo `create` → crea tareas programadas con status `pending` via endpoint `create`
- **Nueva seccion "Tareas de hoy"** (arriba de la barra de filtros, entre Activas y filtros). Agrupa entries del dia excluyendo las tareas aun activas. Se oculta cuando no hay actividad
- **Orden de secciones actualizado**: Proximas -> Activas -> Hoy -> Filtros -> Ayer -> Historial. "Activas" ahora tambien se oculta cuando esta vacia
- **Helper `renderTaskTable`** reutilizable (Activas + Hoy + Ayer comparten schema): columnas configurables via CSS var, acciones opcionales, modo `expandable` con chevron y fila detalle lazy-load
- **Fila detalle colapsable** en Activas y Hoy: Prioridad | Vencimiento | Desde | chip de estado (a tiempo / con retraso). Debajo descripcion + tabla de registros agrupada por fecha (solo lectura). Borde inferior sutil como separador
- **Edicion de tarea centralizada en el slide panel**:
  - Alianza | Etiquetas en la misma fila. Etiquetas como **dropdown multiselect** con checkboxes + crear etiqueta nueva dentro del dropdown
  - Fila readonly: Atendida desde | Tiempo acumulado | Estado (a tiempo / retraso)
  - Seccion de Registros editable al final: hora inicio/fin con inputs `type=time`, duracion recalculada en vivo, guardar (hover verde) y eliminar (hover rojo + ConfirmModal). Total se recalcula automaticamente
- **Filtros**: filtro de etiquetas ahora **multi-select** (semantica OR) en la barra principal, con trigger que muestra nombres o conteo segun seleccion. Campos de fecha reducidos a 120px. Barra con fondo brand-tint para destacar como "control"
- **Paleta de prioridades** en cards de proximas: baja (gris), media (azul #0052cc), alta (amarillo), urgente (naranja vibrante), vencida (rojo). Fondos tintados sin bordes
- **Carrusel de proximas**:
  - Alianza al top como badge junto al label de prioridad y etiqueta "VENCIDA"
  - Mobile: **peek effect** (card al 90% para que asome la siguiente)
  - Desktop y mobile: **mask-image** fade a la derecha que se apaga al llegar al final (clase `at-end`)
  - Comentarios `>>> AJUSTAR ...` en el CSS para ubicar rapido los valores de peek y degradado
- **Tracker inline**:
  - Etiquetas compactas: un solo chip con conteo (`🏷️ 3 etiquetas`) + tooltip custom con los nombres completos
  - La fecha de vencimiento **ya no se muestra** en el rastreador (se asume actividad del dia)
  - Cronometro de alto contraste (brand solido + texto blanco bold)
  - Alianza con color institucional como tint en el badge
- **Alianza con color institucional** aplicada en las tres ubicaciones: tracker, cards proximas y tabla (via `color-mix` con `--alliance-color`)
- **Layout tabla (Activas/Hoy/Ayer)**:
  - Columnas: Alianza | Tarea (con contador) | Estado | Etiquetas | Tiempo | Acciones. Estado en minusculas, alineado a la izquierda
  - Badge de alianza con tamano fijo (width 100% de la celda, ellipsis si es largo)
  - Chips de etiquetas compactos dentro de la celda
  - Acciones: Reanudar (success), Editar, Eliminar (danger), Expandir detalle
- **Formato de fechas uniforme**: helper `formatDateDMY(value)` convierte YYYY-MM-DD a DD/MM/YYYY en todos los textos de fecha (Vencimiento, Desde, fechas de registros, headers de historial). Los `input type="date"` siguen con el formato del navegador
- **Fix**: edicion de tarea desde la lista ya no contamina el state del tracker. `openEditForm` con `opts.task` aisla la edicion; `handleEditSubmit` solo sincroniza state si la tarea editada coincide con el timer corriendo
- **Fix**: hidratacion completa del state tras `timer_start` y `timer_status` (endpoints que no devuelven priority/due_date/description/tag_ids) via fetch adicional a `get`
- **Fix**: `todayStr()` y `yesterdayStr()` usan fecha local en vez de `toISOString()` UTC
- **Fix**: cards de proximas en fila horizontal (flex-direction row explicito, antes heredaban column del padre)
- **Fix**: el alert "datos incompletos" en mobile agrupa icono + texto en un wrapper para evitar que se separen


### Agregado
- **Pagina `/tasks` — Sub-fase 4.2: Listado y filtros**
  - **Secciones apiladas** (orden): Proximas, Activas, Ayer (se oculta si vacia), Barra de filtros, Historial. Titulos de seccion sin borde inferior, mas pegados al contenido
  - **Proximas tareas** como **carrusel horizontal** (cards de 280px ancho, altura minima 180px):
    - Scroll horizontal con scrollbar discreto cuando hay mas de ~4-5 cards (segun ancho del viewport)
    - Fondo con tinte de color segun prioridad (sin bordes): medium (brand), high (orange), urgent (red), low (neutral); vencidas con rojo mas intenso
    - Orden: vencidas -> urgentes -> altas -> medias -> bajas (dentro de cada grupo por fecha asc)
    - Tag "VENCIDA" rojo solido para las atrasadas
    - Alianza como texto sin fondo brand; etiquetas como chips planos sin icono
    - Footer agrupa fecha (izquierda) + acciones (Editar, Iniciar, Eliminar) a la derecha
    - Iniciar con btn-icon success hover, Eliminar con btn-icon danger hover y ConfirmModal
  - **Tareas activas** y **Tareas de ayer** con layout tabla sin `<table>` (CSS Grid):
    - Columnas: Alianza, Tarea (con contador de registros), Estado, Etiquetas, Tiempo, Botones
    - Lozenge "Corriendo" si la fila corresponde al timer activo
    - Contador de registros = entries en el rango filtrado (para ayer: sesiones del dia)
    - En <992px se colapsa a layout card (headers ocultos, celdas apiladas)
  - **Historial**: entradas individuales agrupadas por dia con total diario (ayer va a su propia seccion)
  - **Barra de filtros compacta** (posicion entre Activas y Ayer): busqueda, rango fechas, alianza, prioridad, etiqueta, boton limpiar como icono
  - Filtros al servidor: rango fechas + alianza. Filtros locales: busqueda, prioridad, etiqueta
  - Empty states contextuales por seccion
  - Auto-refresh tras start/pause/stop/discard/edit
- **Pagina `/tasks` — Sub-fase 4.1: Cronometro y tarea activa (flujo hibrido)**
  - **Tracker inline friccion-cero**: input grande con icono de cronometro y boton play, el usuario escribe el nombre y arranca sin ningun formulario previo
  - **Autocomplete en vivo** al escribir (debounce 250ms): muestra hasta 6 tareas existentes con alianza + lozenge de estado. Clic en una sugerencia reanuda el timer asociado
  - **Validacion tardia**: el timer arranca solo con titulo; la alianza y etiquetas son obligatorias unicamente al pausar o completar
  - **Estado activo compacto en una sola fila**: `[●] titulo · etiquetas · alianza · prioridad · fecha · tiempo · [editar][pausar][completar][descartar]`. Cronometro reducido (1.125rem con fondo brand sutil) y tarjeta con padding compacto para no ocupar espacio innecesario
  - **Chips de meta con estado vacio dashed**: si falta alianza o etiquetas se muestran chips con borde punteado para invitar a completarlos
  - **Alerta inline "datos incompletos"** compacta con CTA textual (no bloquea el uso, solo avisa)
  - **Prioridad con color semantico en el chip**: alta (orange) y urgente (red); baja y media neutrales
  - Botones de accion como iconos con tooltip (edit, pause warning, stop success, discard danger) — ahorra espacio sin perder contexto
  - **Flujo forzado al pausar/completar con datos incompletos**: abre el slide panel, se guarda y reintenta la accion automaticamente
  - Restauracion automatica si ya hay un timer corriendo al cargar la pagina
  - ConfirmModal para completar y descartar
- **Slide panel "Editar tarea" / "Completar informacion"**
  - Orden: titulo -> descripcion (siempre visible) -> alianza -> etiquetas -> [prioridad | fecha de vencimiento] en una sola fila
  - Chips de etiquetas compactos (padding 2px / 100) para aprovechar mejor el espacio
  - Chips de etiquetas toggleables visualmente (color primario cuando seleccionado)
  - Creacion rapida de etiquetas desde el mismo panel (sin salir)
  - Validacion inline por campo con mensajes especificos
  - En modo "forceComplete" se muestra un alert warning arriba indicando los campos obligatorios
- **Campos con foco destacado**: borde transparente por defecto (ruido visual minimo), al hacer focus el borde adopta `--app-brand` con halo suave (box-shadow 3px al 18% de opacidad)
- **Accesibilidad**
  - `aria-live="polite"` en el display del timer
  - `aria-autocomplete`, `aria-controls`, `aria-expanded` en el input del autocomplete
  - Focus management en el slide panel
  - Tooltips en acciones de iconos
  - Labels y descripciones ARIA en formulario

### Cambiado
- `data/stages.json` reestructurado con 9 fases. Fase 4 marcada como `in-progress` (sub-fase 4.1 completa)
- Nueva Fase 5: `/manage-tasks` en Ajustes (import/export, cleanup, CRUD de etiquetas) para construir despues del modulo Tareas

---

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
