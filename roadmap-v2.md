# NexusApp 2.0 — Roadmap de reescritura frontend

## Contexto

NexusApp es una herramienta para equipos de migración de contenidos educativos. Gestiona alianzas académicas, procesa contenido para Moodle/Canvas, convierte preguntas (GIFT/QTI), optimiza archivos y registra tareas con time tracking.

La versión 1.x funciona correctamente. El backend (PHP, MySQL, APIs) está pulido. El frontend tiene acumulación de parches, mezcla de clases Bootstrap con CSS custom, y necesita reescribirse con un design system coherente.

## Objetivo

Reescribir el frontend completo usando Atlassian Design System como base y crear el UI Kit para reutilizar los componentes de interfaz. El backend se copia tal cual. Los módulos pendientes (reportes PDF, dashboard de vencimientos) se construyen directamente en v2.

## Proyecto de referencia

El código fuente de v1 está en: `c:\laragon\www\NexusApp`

Lee ese proyecto para entender la funcionalidad completa de cada módulo antes de reescribir.

---

## Qué se REUTILIZA (copiar sin cambios)

- `config/config.php` — configuración central
- `config/database.php` — credenciales BD (gitignored)
- `config/database.example.php` — plantilla
- `includes/functions.php` — funciones globales, getDB(), getUsers(), getAlliances(), migraciones
- `includes/auth.php` — autenticación, permisos, getRoles()
- `includes/gift_actions.php` — conversor GIFT
- `includes/qti_actions.php` — conversor QTI
- `includes/question_parser.php` — parser compartido de preguntas
- `includes/alliance_actions.php` — procesamiento de plantillas de alianzas
- `includes/manage_alliances_actions.php` — CRUD alianzas
- `includes/user_actions.php` — CRUD usuarios
- `includes/backup_actions.php` — sistema de backups
- `includes/api_settings_actions.php` — config APIs
- `includes/projectinfo_actions.php` — info del proyecto
- `includes/tasks_actions.php` — API de tareas y time tracking
- `includes/diagnostics_actions.php` — diagnóstico del sistema
- `includes/cleanup_actions.php` — limpieza
- `includes/reports_actions.php` — reportes (en desarrollo)
- `includes/template_actions.php` — plantillas demo GIFT/QTI
- `includes/pdf_optimizer_actions.php` — optimizar PDF
- `includes/image_optimizer_actions.php` — optimizar imágenes
- `lang/` — todas las traducciones (es/en)
- `templates/` — plantillas HTML de alianzas (UNAB, UNIS)
- `data/` — archivos JSON de configuración
- `migrate-data.php` — script de migración JSON a MySQL
- `setup-deps.php` — instalador de dependencias
- `.htaccess` — reglas de rewrite

## Qué se REESCRIBE

- `index.php` — punto de entrada (misma lógica, HTML nuevo)
- `assets/css/variables.css` — tokens del design system
- `assets/css/styles.css` — estilos completos nuevos
- `assets/js/*.js` — misma funcionalidad, nuevo HTML/selectores
- `pages/*.php` — mismo PHP, HTML nuevo
- `includes/header.php` — navbar nueva
- `includes/footer.php` — footer nuevo
- `includes/slide-panel.php` — panel lateral nuevo

---

## Arquitectura del proyecto

```
PHP 8+ | MySQL | Bootstrap 5.3 → Atlassian DS
Flat-file MVC: index.php → pages/{$page}.php
i18n: __('section.key') → lang/{es,en}/*.php
Permisos: hasPermission($user, 'module', 'action')
Roles: admin, editor, viewer
```

### Routing (whitelist en index.php)
- `/home` — Dashboard
- `/tasks` — Tareas y time tracker
- `/alliances` — Formularios de alianzas
- `/utilities` — Convertir preguntas, optimizar PDF/imágenes
- `/settings` — Ajustes (usuarios, alianzas, proyecto, integraciones, backups, sistema, actividad, docs)
- `/login` — Acceso
- `/logout` — Cerrar sesión

### Base de datos MySQL
Tablas: users, roles, alliances, activity_log, tasks, time_entries, tags, task_tags, migrations

### Alianzas
- Facturables: se muestran en página Alianzas
- No facturables: solo en Tareas (para tareas internas)
- Campo `billable` en tabla alliances

---

## Orden de reescritura (de menor a mayor complejidad)

### Fase 1: Fundación
- [ ] Definir tokens CSS basados en Atlassian DS
- [ ] Crear layout base (navbar, main content, footer)
- [ ] Slide panel reutilizable
- [ ] Login page

### Fase 2: Páginas simples
- [ ] Dashboard (home.php) — progreso del proyecto, etapas
- [ ] Documentación (partials/docs.php) — sidebar + scroll

### Fase 3: Tareas (módulo principal)
- [ ] Rastreador (barra cronómetro)
- [ ] Tarjetas de próximas tareas (max 5, kanban)
- [ ] Tareas activas (en progreso/pausa)
- [ ] Lista de actividades por fecha (renderTaskRow reutilizable)
- [ ] Filtros y controles
- [ ] Indicador flotante del timer
- [ ] Gestión de etiquetas
- [ ] SlidePanel: programar tarea, editar tarea
- [ ] **NUEVO**: Reportes (generar, exportar PDF/Excel/CSV)
- [ ] **NUEVO**: Dashboard de vencimientos

### Fase 4: Utilidades
- [ ] Convertir preguntas (GIFT/QTI) — dropzone, formato, previsualización
- [ ] Optimizar PDF — wizard de 4 pasos
- [ ] Optimizar imágenes — comprimir, redimensionar, convertir

### Fase 5: Alianzas
- [ ] Formulario UNIS (Moodle/Canvas)
- [ ] Formulario UNAB (Moodle)
- [ ] Procesamiento y slide panel de resultados

### Fase 6: Ajustes
- [ ] Usuarios y roles
- [ ] Gestión de alianzas
- [ ] Proyecto (identidad + modo privado)
- [ ] Integraciones API
- [ ] Copias de seguridad
- [ ] Sistema (dependencias, BD, diagnóstico)
- [ ] Registro de actividad

---

## Decisiones de diseño ya tomadas

### UX
- Mobile-first siempre
- No usar iconos en títulos de secciones
- Campos sin bordes visibles, bordes sutiles solo al editar/focus
- Botones de acción: icono gris en reposo, color + fondo sutil en hover
- Etiquetas seleccionadas: fondo azul con borde azul
- Estados de tareas con colores: pendiente (gris), en progreso (azul), en pausa (amarillo), completada (verde), cancelada (rojo)
- Tarjetas programadas: fondo según prioridad (rojo urgente, naranja alta, azul media, gris baja)
- Alianzas: fondo sutil del color institucional (15% opacidad)
- Filtros: barra con fondo gris sutil, todos los elementos misma altura
- Navbar: sticky, se compacta al scroll

### Datos
- Timezone MySQL sincronizada con PHP al conectar
- Headers no-cache en todas las respuestas JSON
- Validar nombre + alianza + etiqueta antes de pausar/completar tarea
- Tareas vencidas se marcan automáticamente como urgentes
- Al iniciar timer, el estado pasa a in_progress sin importar estado anterior

### Internacionalización
- Español como idioma principal, inglés como secundario
- Cambio de idioma desde dropdown del usuario en navbar
- Todas las tildes correctas en español
- Traducciones por módulo: lang/{es,en}/{modulo}.php

### Seguridad
- CSRF en todas las peticiones POST
- Sesiones HttpOnly, SameSite=Strict
- Contraseñas bcrypt
- Claves API encriptadas AES-256-CBC
- APP_SECRET_KEY personalizada (no la default)

---

## Preferencias del usuario

- Comunicación en español
- No usar emojis en código ni respuestas
- No narrar cada paso al trabajar, solo mensajes prioritarios
- Si una solicitud no cumpple las mejores prácticas, sugerirlas
- Proponer siempre las mejores opciones de seguridad
- Iniciar con la base, diseno o estilos del Design system seleccionado en la primera fase
- Windows 11, Laragon, PHP 8.3
- Rol: migración de contenidos educativos (no diseño instruccional)
- Slogan: "Del contenido al aula, sin complicaciones"
