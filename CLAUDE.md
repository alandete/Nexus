# Nexus 2.0 — Instrucciones para Claude Code

## Idioma
Responde siempre en español. Sin excepciones.

## Estilo de respuesta
- Mensajes cortos y directos; sin recapitulaciones al final.
- Sin emojis en código ni en respuestas.
- Durante una tarea no narres cada paso; solo comunica dudas o errores.

## Gestión de contexto
- Cuando se use `/compact`, preservar: decisiones de arquitectura, errores activos, tareas pendientes y convenciones de código establecidas.
- Iniciar conversaciones nuevas por tarea o sesión de trabajo en lugar de mantener una sola conversación larga.

## Prompts frecuentes
Frases cortas preferidas para tareas comunes:
- "Nuevo componente: [nombre]" → crear página/componente siguiendo el patrón existente.
- "Bug: [descripción]" → diagnosticar y corregir sin tocar código no relacionado.
- "Pulir: [archivo o sección]" → ajustes visuales/UX sin cambios funcionales.
- "Commit" → revisar cambios, actualizar CHANGELOG y commitear.
- "Qué falta" → revisar tareas pendientes del proyecto.

## Commits
- Escanear por secretos antes de commitear.
- Actualizar CHANGELOG.md con cada commit.
- Acumular varios cambios antes de commitear; no commitear tras cada micro-ajuste.

## Diseño
- Usar `--app-brand` en elementos clave de identidad sin saturar.
- El sidebar se superpone al contenido, nunca lo empuja.
- Replicar tooltips en todos los elementos que necesiten contexto.

## Entorno
- Windows 11, Laragon, PHP 8.3, MySQL 8.
- Proyecto en `c:\laragon\www\Nexus`.
