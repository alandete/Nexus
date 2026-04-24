# Changelog

Todos los cambios relevantes del proyecto se documentan en este archivo.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

## [2.0.0-alpha.3] — 2026-04-19 (en desarrollo)

### Hito de produccion

- **Fecha objetivo: 2026-04-30**. El modulo de tareas debe estar terminado antes de fin de mes para arrancar el registro de actividades desde el **2026-05-01**.
- Debe incluir, ademas de las sub-fases, las validaciones criticas:
  - Backend que impida solapamiento de registros de tiempo (ver deuda tecnica abajo).
  - Backend que resetee el status al descartar un timer recien iniciado.
  - Validacion Clockify ↔ Nexus completada y confirmada antes del cierre.
- **Prioridad de cierre** (11 dias habiles disponibles): backend fixes → Importar/Exportar → Limpieza → Reportes → Clockify validation.

### Fase 5 — Limpieza selectiva en `/manage-tasks` — 2026-04-24

**Tab Limpieza**:
- Filtros: alianza, estado (En progreso, Pausadas, Completadas, Canceladas, Pendientes sin actividad) y fecha anterior a.
- Vista previa calcula tareas y entradas afectadas antes de borrar. Botón "Eliminar selección" deshabilitado hasta confirmar preview.
- Doble confirmación modal antes de ejecutar.
- Opción "Eliminar todo" con doble confirmación reforzada para borrado total de tareas y entradas.
- Backend: `includes/tasks_cleanup_actions.php` con acciones `preview`, `execute` y `nuke`.

**Correcciones**:
- Contador de filtros en `/tasks` usaba objetos con `priority` y `tag_ids` hardcodeados; ahora usa `groupEntriesByTask` y refleja alianza, prioridad y etiquetas correctamente.
- Al eliminar la tarea activa desde el panel, el tracker se resetea en lugar de quedar en estado huérfano.

### Fase 5 — Importar / Exportar en `/manage-tasks` — 2026-04-24

**Tab Exportar**:
- Selector de rango: Hoy / Esta semana / Mes actual / Mes anterior / Personalizado (mismos rangos que `/tasks`).
- Selector de formato: Nexus CSV (para reimportar) o Clockify CSV (replica exacta de columnas del export de Clockify).
- Descarga via GET a `includes/io_actions.php?action=export`. CSV con BOM UTF-8 para compatibilidad con Excel.
- Clockify CSV incluye: Proyecto, Cliente, Descripcion, Usuario, Etiquetas, Fecha/Hora inicio, Fecha/Hora fin (formato 12h AM/PM), Duracion (h:mm y decimal).

**Tab Importar**:
- Selector de formato de entrada: Nexus CSV o Clockify CSV.
- Drop zone drag & drop + seleccion de archivo.
- Parseo 100% en cliente (FileReader + parseador RFC 4180 manual).
- Deteccion automatica de alianzas y etiquetas desconocidas:
  - Alianzas: selector con alianzas existentes + opcion "Descartar entradas".
  - Etiquetas: selector con etiquetas existentes + opcion "Crear nueva" (por defecto).
- Vista previa de las primeras 10 filas parseadas.
- Contador de entradas activas (excluye las descartadas) en el boton de confirmar.
- Backend (`includes/io_actions.php` accion `import`):
  - Crea tags nuevas si corresponde.
  - Busca o crea tarea por (titulo + alianza + usuario); status `completed`.
  - INSERT IGNORE en `task_tags` para no duplicar vinculos.
  - Salta duplicados exactos (mismo task_id + start_time).
  - Salta solapamientos con otros entries del usuario.
  - Todo dentro de una transaccion; retorna conteo de insertados, duplicados y solapados.

### Sub-fase 4.5 Reportes — 2026-04-22

**Pagina `/reports`** (nueva):
- Filtros inline en una linea: tipo (Resumido/Detallado) + rango (Ultima semana / Mes anterior / Personalizado) + selector de usuario (solo admin).
- Auto-carga inicial con defaults (Resumido + Mes anterior + usuario logueado).
- Header del reporte en 2 columnas: izquierda nombre de usuario + periodo, derecha tiempo total en tipografia grande.
- Grafico doughnut con % dentro de cada segmento y leyenda abajo (Chart.js 4.4.0 + plugin custom `pctLabelsPlugin`).
- Tabla resumen por alianza (siempre visible).
- Modo Detallado: cards por alianza con header resumen (`N tareas · tiempo total`) + tabla de tareas adentro con zebra; card similar para "Total por etiqueta". Bordes uniformes a 1px, sin radius, padding generoso en columnas.

**Calendario personalizado** (Litepicker 2.0.12 + plugin ranges):
- Popover flotante que no empuja contenido, 2 meses visibles.
- Presets no redundantes con los botones de rango: "La semana pasada", "Ultimos 15 dias", "Ultimos 30 dias", "Este anio".
- Popover con sombra amplia, separado del trigger con `transform: translateY`.
- Panel de presets con padding interno y hover en tono neutro (contraste AAA).

**Exports**:
- CSV con BOM UTF-8 + CRLF (acentos correctos en Excel).
- XLSX via ExcelJS 4.4.0 con formato (fills brand, bordes, zebra, headers semibold, tiempo total destacado). Reemplaza a SheetJS que no soportaba estilos.
- PDF via `window.print()` con `@media print` (oculta sidebar/filtros, aplana sombras, fuerza page-break por card). Titulo del documento cambia temporalmente para que el PDF guardado tenga nombre legible.
- Helper `statusLabel()` que traduce los estados de tareas (pending/in_progress/paused/completed) en los exports.
- Toast de confirmacion tras descargar CSV y Excel.

**Accesibilidad**:
- Radiogroups con `aria-labelledby`, roving tabindex, teclado completo (flechas, Home, End).
- `aria-busy` en el contenedor durante generacion, `role="status"` en spinner de carga, `role="alert"` en errores.
- `<h2>` semantico en header del reporte, `<th scope="col">` + `<caption class="sr-only">` en todas las tablas generadas.
- `aria-haspopup="dialog"` en boton Personalizado.

**Backend** (`includes/tasks_report_actions.php`):
- Accion `monthly` acepta `start`/`end` en `YYYY-MM-DD`, default = mes actual.
- Accion `users_list` para popular el select (solo admin).
- Helper `formatRangeLabel()` genera "Marzo 2026" si el rango cubre un mes natural completo, o "01/03/2026 — 07/03/2026" en rangos arbitrarios.
- Retorna `period`, `user`, `total_seconds`, `task_count`, `by_alliance`, y opcionalmente `tasks_by_alliance` / `by_tag` segun tipo de reporte.

**UX en `/tasks`**:
- Botones del header reordenados: Nueva tarea (primary) + Reportes (default con fondo neutro, en vez de btn-subtle transparente) para mejor jerarquia visual.

**Pulido vista impresa PDF — 2026-04-23**:
- Encabezado: logo+app a la izquierda; tipo de informe+usuario a la derecha con `flex:1; min-width:0` (elimina el `max-width:50%` que recortaba el texto).
- Gráfico: `margin-top: 0.7cm` y `margin-bottom: 1cm` para más aire respecto al encabezado y la tabla de alianzas.
- Paginación y footer: número de página y fecha de generación via `@page @bottom-right` / `@bottom-center` (CSS Paged Media), con inyección dinámica de la fecha en `exportPDF()`.
- Gráfico en impresión: `chartInstance.resize(302, 302)` con `responsive: false` antes de `window.print()`; restaura tamaño y leyenda en el `setTimeout` post-print.
- Tablas: cambio a `border-collapse: separate; border-spacing: 0` con borde exterior en el elemento wrapper (`<div>` / `<article>`), no en `<table>`. Bordes interiores solo en los lados internos de cada celda. Diseño de tabla abierto (sin bordes laterales) para evitar el bug de Chrome que recorta el borde derecho al 100% del ancho de página.

### Sesion 2026-04-21 — pulido pre-produccion

**Backend**:
- `timer_discard` ahora elimina la tarea por completo si era `in_progress` sin entries previos (evita "tareas fantasma"). Si tenia entries o estaba `paused`, vuelve a `pending`
- Nuevo helper `validateTaskRequirements(taskId)` que verifica alianza + ≥1 etiqueta
- `create`, `timer_pause` y `timer_stop` rechazan con `requires_completion: true` si faltan esos datos
- `timer_start` setea `due_date = CURDATE()` al crear una tarea desde el rastreador
- Endpoint `list` ya no filtra por `alliance_id`: siempre carga todas las tareas/entries del rango. El filtro de alianza se aplica solo en cliente sobre Ayer/Historial
- `log_manual` arranca en el `end_time` del ultimo entry del dia (en vez de siempre a las 09:00) para evitar colisiones sistemicas

**UX / listado**:
- Orden de secciones ajustado: `Proximas | Activas | Hoy | Filtros | Ayer | Historial` (barra movida un paso atras)
- Los filtros locales (busqueda, alianza, prioridad, etiquetas) solo afectan a Ayer e Historial. Proximas/Activas/Hoy son vista "actual" completa
- Contador de registros en la barra (estilo tinte brand cuando hay filtros activos)
- Columna "Hora" (primera sesion del dia) en Hoy y Ayer (antes solo en Historial)
- Formato 12H (AM/PM) en todos los horarios visibles y en mensajes de solapamiento
- Toast ampliado a `max-width: 560px` para mensajes de overlap largos
- Empty state del carrusel de Proximas centrado horizontalmente
- Fallbacks hardcoded del JS corregidos ortograficamente

**Corrector ortografico — pase unico**:
- 258 correcciones aplicadas en 12 archivos `lang/es/*.php` (`-cion/-sion`, `proxima/-o/-s`, `version`, `aplicacion`, `contrasena`, `imagenes`, `vacio`, `via`, `numero`, `cronometro`, `codigo`, `metodo`, `categoria`, `dia/-s`, `ultimo/-a/-s`, etc.) y en fallbacks de `assets/js/tasks.js` y `manage-tasks.js`. Keys PHP intactas
- Agendado para Fase QA: `cspell` con `.cspell.json` + pre-commit hook

**Migracion de BD**:
- La app ya no lee de `nexusapp` (del proyecto anterior) sino de la BD nueva `nexus` con usuario MySQL dedicado
- Se conservaron `alliances`, `tags`, `roles` y `migrations`. Usuario admin creado con password `password`
- Backup de `nexusapp` guardado en `backups/nexusapp-dump-20260420-150928.sql` por si se necesita restaurar

**Seed de prueba**:
- 60 tareas con dates/prioridades variadas cubriendo los ultimos 2 meses
- Cada tarea con alianza + 2-4 etiquetas; 70% con sesiones trabajadas 0/1/2/3/5/7 dias despues
- 179 time entries sin solapamientos (el seed valida huecos libres)

### Backend fixes criticos (completados 2026-04-21)

- **`timer_discard` ahora resetea el status de la tarea**. Si despues de borrar la entry activa la tarea queda sin ningun entry, su status vuelve a `pending`. Elimina el caso de "tareas fantasma" en Activas.
- **Helper `findOverlappingEntry` en backend**. Formula estandar de overlap (a.start < b.end AND b.start < a.end) que busca entries del mismo `user_id` que colisionen con un rango dado. Reutilizable.
- **`time_entry_update` valida solapamiento** antes de aplicar el UPDATE. Rechaza con mensaje indicando la tarea conflictiva y su rango. El frontend (`saveFormEntry`) ya validaba pero ahora el endpoint es defensivo incluso ante requests directos.
- **`log_manual` valida solapamiento** al crear entries manuales. Ademas ahora arranca en el `end_time` del ultimo entry cerrado del dia (en vez de siempre a las 09:00) para evitar colisiones sistemicas.

### Tareas para fase QA (Fase 8)

- **Setup de cspell (o similar) para corrector ortografico automatico**. Configurar `.cspell.json` con diccionario `es-ES` + glosario del proyecto (Atlassian, lozenge, bcrypt, nexus, etc.) y dejarlo en un pre-commit hook. Eso evita que se cuelen palabras sin tilde/acento como "Proximas" en vez de "Proximas". Complementa el pase manual unico hecho en 2026-04-21.

### Tareas semana actual

- **Validacion Clockify ↔ Nexus** (esta semana): exportar time entries de Clockify del mes en curso (UI: `Reports → Detailed → Export → CSV`) y mapearlas manualmente contra los campos de la tabla `time_entries` de Nexus para verificar que los tiempos estan alineados. Hacer con cuidado: confirmar que `start_time` / `end_time` / `duration_seconds` / `task.title` coinciden para cada sesion. Esto valida si vale la pena construir la integracion automatica via API (documentada en el agente, con `X-Api-Key`, 10 req/s, endpoint `/workspaces/{ws}/user/{uid}/time-entries?hydrated=true`) como nuevo proveedor en `/integrations`.

### Fase 5 — primera entrega `/manage-tasks` (CRUD de etiquetas)

- **Nueva ruta** `/manage-tasks` registrada en `index.php`, sidebar de Ajustes y settings overview con icono `bi-list-check`
- **Layout por tabs**: Etiquetas · Importar y exportar · Limpieza de datos. Las dos ultimas como placeholder "Proximamente"
- **CRUD de etiquetas inline** (sin slide panel):
  - Tabla con columnas `Nombre | Color | Uso | Eliminar`
  - Nombre editable inline (bordes transparentes hasta focus, guarda al blur o Enter)
  - Color combinado en un solo control: picker nativo + input hex, sincronizados en vivo (editas uno y el otro refleja el cambio)
  - Fila "nueva etiqueta" siempre visible al final con fondo brand-tint y boton (+) verde. Al crear, la etiqueta salta a su posicion alfabetica y el foco vuelve al input "nueva" para agregar varias rapido
  - Eliminar con ConfirmModal que advierte si la etiqueta esta en uso (muestra cuantas tareas)
  - Stats: Total / En uso / Sin uso
- **Traducciones** en `lang/{es,en}/manage_tasks.php` + claves `menu.manage_tasks`, `settings_overview.manage_tasks_desc`, `common.add`

### Iteracion continua de 4.2 (ronda 3)

- **Historial rediseñado**: agrupa entries por tarea dentro de cada dia con header `Viernes, 17 de abril de 2026` + total del dia. Mismo schema de columnas que Activas/Hoy/Ayer + nueva columna "Hora" (primera hora de inicio) antes de Tiempo. Tareas ordenadas desc por hora de inicio. Paginacion de 7 dias por pagina con ellipsis + reset a pagina 1 al filtrar
- **Etiquetas agrupadas** en las tablas: un solo chip `🏷️ N etiquetas` con tooltip custom que lista los nombres. Ancho de columna reducido a 120px. Mas espacio para el nombre de la tarea
- **Filtro de detalle por dia**: al expandir una fila en Hoy / Ayer / Historial, los registros mostrados se filtran al dia visible (antes mostraba todo el historico de la tarea y mezclaba sesiones de fechas distintas). En Activas sigue mostrando todo (tiene sentido alli)
- **Validacion de solapamiento** al editar registros: `saveFormEntry` valida que el rango `[start, end]` no colisione con otros entries del mismo dia del usuario (via `findOverlappingEntry` sobre `listState.data.by_date`). Si hay overlap, Toast con el nombre de la tarea conflictiva y su rango
- **Formato consistente de tiempos**: `formatDuration` sin segundos (coherente con las horas `HH:MM`). Redondea al minuto mas cercano; sesiones `< 1m` se marcan como tal
- **Anchos de columna ajustados**: alianza 90px (0.7fr), tarea 260px (3fr), estado 110px, etiquetas 120px, hora 55px (historial), tiempo 70px
- **Boton guardar de registros** con hover verde (variante `btn-icon-success`) para alinearlo con el eliminar (rojo)

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
