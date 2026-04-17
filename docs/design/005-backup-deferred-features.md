# 005 — Funciones diferidas del sistema de copias de seguridad

**Fecha:** 2026-04-18
**Estado:** Aprobado (diferidas a fases futuras)
**Contexto:** Definicion del alcance minimo para el frontend de `/snapshots` vs features enterprise.

## Decision

Para la version alpha, el frontend de `/snapshots` se construye sobre el backend existente sin agregar arquitectura compleja. Las features enterprise se documentan aqui como fases futuras, a implementar solo si el caso de uso lo justifica.

## Incluido en alpha (frontend nuevo)

- Feedback visual durante creacion (spinner + deshabilitar)
- Listado con tamaño, fecha legible, tipo (chip), notas, favorito
- Modal de confirmacion para restauracion (en lugar de `confirm()`)
- Aviso claro sobre tipo y limites (full solo descarga)
- Indicador de rotacion (`X de 3 slots usados`)
- Diferenciacion visual de favoritos (lozenge + borde)
- Cleanup integrado como checkbox opcional al crear
- Filtros: tipo + favoritos
- Stat "Ultima copia: hace X dias"

## Diferidas a fases futuras

### Fase A: Scheduling automatico
- **Que:** Crear backups automaticos en intervalos definidos (diario, semanal)
- **Por que no ahora:** Requiere cron/scheduler en el servidor, configuracion por usuario
- **Dependencias:** Infraestructura de tareas programadas

### Fase B: Almacenamiento en la nube
- **Que:** Replicar backups a S3, Google Drive o similar
- **Por que no ahora:** Overhead grande, requiere credenciales adicionales
- **Dependencias:** Integraciones API, manejo de credenciales encriptadas

### Fase C: Encriptacion de backups
- **Que:** ZIP con contraseña o cifrado AES antes de guardar
- **Por que no ahora:** El servidor es la unica fuente; no hay transporte a terceros
- **Dependencias:** libzip con soporte de encriptacion, gestion de claves

### Fase D: Backups incrementales
- **Que:** Guardar solo diferencias respecto al backup anterior
- **Por que no ahora:** Complejidad alta, reconstruccion de estado complica restauracion
- **Dependencias:** Sistema de hashing/diff robusto

### Fase E: Verificacion de integridad
- **Que:** Checksums o firmas para detectar corrupcion
- **Por que no ahora:** Riesgo bajo en servidor local; overhead al crear
- **Dependencias:** Algoritmo de checksum + almacenamiento de metadatos

### Fase F: Restauracion selectiva
- **Que:** Elegir que tablas/archivos restaurar del backup
- **Por que no ahora:** UI compleja, caso de uso marginal
- **Dependencias:** Preview del contenido del ZIP

### Fase G: Diff entre backups
- **Que:** Ver que cambio entre dos backups
- **Por que no ahora:** UI compleja, los datos cambian mucho
- **Dependencias:** Comparador de contenido

### Fase H: Notificaciones y monitoreo
- **Que:** Email/notificacion al completar backup o fallar
- **Por que no ahora:** Requiere sistema de email
- **Dependencias:** Servicio SMTP configurado

## Criterio de promocion a implementacion

Una feature diferida se promueve cuando:
1. Hay un caso de uso real documentado (no especulativo)
2. Se tiene la infraestructura necesaria
3. El costo de implementacion es proporcional al valor
