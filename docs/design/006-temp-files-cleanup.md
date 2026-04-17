# 006 — Limpieza automatica de archivos temporales

**Fecha:** 2026-04-18
**Estado:** Diferida a fase futura
**Contexto:** Gestion del directorio `/temp/` que crece con el uso de las utilidades.

## Problema

El directorio `/temp/` acumula archivos generados por los modulos de Utilidades (PDF optimizados, imagenes comprimidas, ZIPs de conversion de preguntas) sin limpieza automatica. Actualmente solo se limpia cuando un admin marca el checkbox al crear un backup.

Con el uso prolongado, este directorio puede crecer significativamente y ocupar espacio en disco innecesariamente.

## Opciones evaluadas

1. **Limpiar al finalizar cada operacion** — Inmediato pero impide re-descargar
2. **TTL por archivo (on request)** — Scan en cada request, borra lo viejo
3. **Limpieza oportunista (diaria en bootstrap)** — Una vez al dia al primer request
4. **Cron job externo** — Requiere configurar cron en el servidor

## Decision

Implementacion de **Opcion 3 (oportunista)**, diferida a fase posterior (probablemente junto con el modulo de Utilidades donde se generan los archivos).

### Plan de implementacion

1. En el bootstrap (`config/config.php` o `index.php`), verificar si paso mas de 24h desde la ultima limpieza
2. Guardar timestamp de ultima limpieza en `data/.last_cleanup` (o tabla `system_meta`)
3. Si paso el tiempo, ejecutar limpieza de archivos en `/temp/` mas viejos que X horas (ej. 24h, configurable)
4. Registrar en activity_log la limpieza automatica

### Configuracion propuesta

Agregar a `/application`:
- Toggle "Limpieza automatica de temporales"
- Input numerico "Eliminar archivos con mas de X horas"

## Criterio para promocion

Implementar cuando se construya el modulo de Utilidades (Fase 4) ya que es el principal generador de archivos en /temp/.

## Referencias

- [docs/design/005-backup-deferred-features.md](005-backup-deferred-features.md) — Otras features diferidas relacionadas a mantenimiento
