# Nexus 2.0

Herramienta para equipos de migracion de contenidos educativos. Gestiona alianzas academicas, procesa contenido para Moodle/Canvas, convierte preguntas (GIFT/QTI), optimiza archivos y registra tareas con time tracking.

## Stack

- **Backend:** PHP 8.3 + MySQL 8.0
- **Frontend:** CSS puro basado en Atlassian Design System (sin frameworks CSS)
- **Iconos:** Bootstrap Icons 1.11
- **Servidor:** Apache (Laragon) con mod_rewrite

## Requisitos

- PHP 8.3+
- MySQL 8.0+
- Apache con mod_rewrite habilitado
- Extensiones PHP: pdo_mysql, intl, mbstring, openssl, gd

## Instalacion

```bash
# 1. Clonar repositorio
git clone git@github.com:alandete/Nexus.git

# 2. Copiar configuracion de base de datos
cp config/database.example.php config/database.php
# Editar config/database.php con las credenciales locales

# 3. Configurar RewriteBase en .htaccess
# Dominio virtual (nexus.test): RewriteBase /
# Subdirectorio (localhost/Nexus/): RewriteBase /Nexus/

# 4. Acceder al sitio
# Las tablas de BD se crean automaticamente en el primer acceso
```

## Estructura

```
Nexus/
├── index.php              # Punto de entrada, routing
├── config/                # Configuracion (BD, sesiones, i18n)
├── includes/              # Backend: actions, auth, funciones
│   ├── header.php         # Top bar
│   ├── sidebar.php        # Navegacion lateral
│   ├── slide-panel.php    # Panel lateral reutilizable
│   └── *_actions.php      # APIs por modulo
├── pages/                 # Vistas por pagina
├── assets/
│   ├── css/
│   │   ├── variables.css  # Design tokens (Atlassian DS)
│   │   └── styles.css     # Componentes y layout
│   └── js/                # JavaScript por modulo
├── lang/{es,en}/          # Traducciones
├── templates/             # Plantillas HTML de alianzas
├── data/                  # Configuracion JSON
└── docs/design/           # Decisiones de diseno (ADR)
```

## Design System

Frontend construido replicando los tokens y patrones de Atlassian Design System en CSS puro:

- **Tokens:** Colores, tipografia, espaciado (grid 8px), elevacion, radios
- **Layout:** Top bar + sidebar colapsable (patron Jira/Confluence)
- **Componentes:** Botones, formularios, cards, lozenges, tags, tabs, tablas, tooltips, alerts, modals, progress, avatars
- **Identidad:** Color primario (`--app-brand`) aplicado en elementos clave de navegacion y branding
- **Responsive:** Mobile-first con 3 breakpoints (mobile, tablet 768px, desktop 992px)
- **Accesibilidad:** Focus visible, skip-to-content, ARIA roles, focus trap en paneles

## Decisiones de diseno

Documentadas en [`docs/design/`](docs/design/):

- 001 — Seleccion del Design System (Atlassian DS)
- 002 — Patron de layout (sidebar + top bar)
- 003 — Uso del color primario para identidad de marca

## Seguridad

- CSRF en todas las peticiones POST
- Sesiones HttpOnly, SameSite=Strict
- Contrasenas bcrypt
- Claves API encriptadas AES-256-CBC
- Headers de seguridad HTTP (nosniff, SAMEORIGIN, referrer-policy)
- Modo privado (noindex, nofollow)

## Licencia

Proyecto privado. Todos los derechos reservados.
