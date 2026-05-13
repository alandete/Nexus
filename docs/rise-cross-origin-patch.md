# Corrección de paquetes Rise Web para iframes en Moodle

**Fecha:** Mayo 2026  
**Afecta:** Exportaciones Rise Web generadas desde mayo 2025 en adelante  
**Síntoma:** El paquete carga correctamente en pestaña directa pero falla dentro de un iframe embebido en Moodle

---

## El problema

A partir de la versión con hash de frontend `adf86e5ca90f5252684619d40324efbf12407ebb`, Articulate Rise incluye en el bundle principal de JavaScript el siguiente acceso a la propiedad `RiseLMSInterface` del objeto `window.parent`:

```javascript
const o = window.parent.RiseLMSInterface,
```

Cuando el paquete se ejecuta en un `iframe` cuyo origen es diferente al de la página padre (cross-origin), el navegador bloquea el acceso a `window.parent` con un `SecurityError`:

```
Uncaught SecurityError: Failed to read a named property 'RiseLMSInterface'
from 'Window': Blocked a frame with origin "https://repositorio.ejemplo.com"
from accessing a cross-origin frame.
```

Este error detiene la ejecución del bundle completo y el paquete no carga.

**Las exportaciones anteriores a mayo 2025 no contienen este código y funcionan sin problema.**

---

## Archivo afectado

Dentro del ZIP exportado desde Rise, el archivo está en:

```
lib/dist/<hash>.js
```

El nombre del archivo varía porque Rise usa hashes de contenido (Webpack). Puede haber más de un archivo `.js` en esa ruta, pero solo uno contiene el patrón.

**Ejemplo real:** `lib/dist/69565ad8.js`

---

## La corrección

Envolver el acceso en un bloque `try/catch` para que el `SecurityError` sea capturado silenciosamente y el valor quede como `undefined` en lugar de detener la ejecución.

### Cadena original (causa el error)

```javascript
window.parent.RiseLMSInterface
```

### Cadena corregida

```javascript
(function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})()
```

### En contexto (línea completa antes y después)

**Antes:**
```javascript
const o = window.parent.RiseLMSInterface,
```

**Después:**
```javascript
const o = (function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})(),
```

---

## Lógica de detección

Antes de aplicar el parche, verificar:

| Condición | Acción |
|-----------|--------|
| El `.js` contiene la cadena corregida `try{return window.parent.RiseLMSInterface}` | Ya está corregido — omitir |
| El `.js` contiene la cadena original `window.parent.RiseLMSInterface` | Aplicar corrección |
| Ningún `.js` contiene ninguna de las dos cadenas | Paquete anterior a mayo 2025 — no aplica |

---

## Implementación de referencia

### Node.js

```javascript
const fs   = require('fs');
const path = require('path');
const AdmZip = require('adm-zip'); // npm install adm-zip

const PATRON_ORIGINAL = 'window.parent.RiseLMSInterface';
const CADENA_PARCHE   = '(function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})()';

function patchRiseZip(inputPath) {
    const zip = new AdmZip(inputPath);
    const entries = zip.getEntries();
    const modified = [];

    for (const entry of entries) {
        if (!entry.entryName.endsWith('.js')) continue;

        const content = entry.getData().toString('utf8');

        if (content.includes(CADENA_PARCHE)) {
            console.log(`Ya corregido: ${entry.entryName}`);
            continue;
        }

        if (content.includes(PATRON_ORIGINAL)) {
            const fixed = content.split(PATRON_ORIGINAL).join(CADENA_PARCHE);
            zip.updateFile(entry.entryName, Buffer.from(fixed, 'utf8'));
            modified.push(entry.entryName);
            console.log(`Corregido: ${entry.entryName}`);
        }
    }

    if (modified.length > 0) {
        const dir  = path.dirname(inputPath);
        const base = path.basename(inputPath, '.zip');
        const lang = 'es'; // cambiar a 'en' para sufijo _fixed
        const suffix = lang === 'es' ? '_corregido' : '_fixed';
        const outPath = path.join(dir, base + suffix + '.zip');
        zip.writeZip(outPath);
        console.log(`ZIP guardado: ${outPath}`);
        return { status: 'patched', files: modified, output: outPath };
    }

    return { status: modified.length === 0 ? 'not_applicable' : 'already_patched' };
}

// Uso:
patchRiseZip('./mi-paquete-rise.zip');
```

**Dependencia:** `npm install adm-zip`

---

### Python

```python
import zipfile
import shutil
import os
import tempfile

PATRON_ORIGINAL = 'window.parent.RiseLMSInterface'
CADENA_PARCHE   = '(function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})()'

def patch_rise_zip(input_path: str, lang: str = 'es') -> dict:
    suffix = '_corregido' if lang == 'es' else '_fixed'
    base, _ = os.path.splitext(input_path)
    output_path = base + suffix + '.zip'

    tmp_dir = tempfile.mkdtemp()
    modified = []

    try:
        # Extraer
        with zipfile.ZipFile(input_path, 'r') as z:
            z.extractall(tmp_dir)

        # Recorrer .js recursivamente
        for root, _, files in os.walk(tmp_dir):
            for filename in files:
                if not filename.endswith('.js'):
                    continue

                filepath = os.path.join(root, filename)
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()

                if CADENA_PARCHE in content:
                    # Ya corregido
                    continue

                if PATRON_ORIGINAL in content:
                    fixed = content.replace(PATRON_ORIGINAL, CADENA_PARCHE)
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(fixed)
                    rel = os.path.relpath(filepath, tmp_dir)
                    modified.append(rel)
                    print(f'Corregido: {rel}')

        if not modified:
            # Determinar si no aplica o ya estaba corregido
            return {'status': 'not_applicable', 'files': []}

        # Reempaquetar
        with zipfile.ZipFile(output_path, 'w', zipfile.ZIP_DEFLATED) as zout:
            for root, _, files in os.walk(tmp_dir):
                for filename in files:
                    filepath = os.path.join(root, filename)
                    arcname = os.path.relpath(filepath, tmp_dir)
                    zout.write(filepath, arcname)

        print(f'ZIP guardado: {output_path}')
        return {'status': 'patched', 'files': modified, 'output': output_path}

    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)

# Uso:
result = patch_rise_zip('./mi-paquete-rise.zip')
print(result)
```

**Sin dependencias externas** — usa solo la librería estándar de Python.

---

### PHP

```php
<?php
const PATRON_ORIGINAL = 'window.parent.RiseLMSInterface';
const CADENA_PARCHE   = '(function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})()';

function patchRiseZip(string $inputPath, string $lang = 'es'): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('La extensión ZipArchive no está disponible');
    }

    $suffix  = $lang === 'en' ? '_fixed' : '_corregido';
    $base    = pathinfo($inputPath, PATHINFO_FILENAME);
    $dir     = pathinfo($inputPath, PATHINFO_DIRNAME);
    $outPath = $dir . '/' . $base . $suffix . '.zip';
    $tmpDir  = sys_get_temp_dir() . '/rise_patch_' . uniqid();

    if (!mkdir($tmpDir, 0755, true)) {
        throw new RuntimeException('No se pudo crear el directorio temporal');
    }

    $modified       = [];
    $alreadyPatched = false;

    try {
        // Extraer ZIP
        $zip = new ZipArchive();
        if ($zip->open($inputPath) !== true) {
            throw new RuntimeException('El archivo no es un ZIP válido');
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // Recorrer .js recursivamente
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'js') continue;

            $content = file_get_contents($file->getPathname());
            if ($content === false) continue;

            if (str_contains($content, CADENA_PARCHE)) {
                $alreadyPatched = true;
                continue;
            }

            if (str_contains($content, PATRON_ORIGINAL)) {
                $fixed = str_replace(PATRON_ORIGINAL, CADENA_PARCHE, $content);
                file_put_contents($file->getPathname(), $fixed);
                $modified[] = substr($file->getPathname(), strlen($tmpDir) + 1);
            }
        }

        if (empty($modified) && $alreadyPatched) {
            return ['status' => 'already_patched', 'files' => []];
        }

        if (empty($modified)) {
            return ['status' => 'not_applicable', 'files' => []];
        }

        // Reempaquetar
        $zipOut = new ZipArchive();
        $zipOut->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $outIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($outIterator as $file) {
            $arcName = substr($file->getPathname(), strlen($tmpDir) + 1);
            $zipOut->addFile($file->getPathname(), str_replace('\\', '/', $arcName));
        }
        $zipOut->close();

        echo "ZIP guardado: $outPath\n";
        return ['status' => 'patched', 'files' => $modified, 'output' => $outPath];

    } finally {
        // Limpieza del directorio temporal
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($tmpDir);
    }
}

// Uso:
$result = patchRiseZip('./mi-paquete-rise.zip');
print_r($result);
```

**Requisito:** extensión `ZipArchive` — incluida por defecto en PHP 8+. Si `sys_get_temp_dir()` no es escribible por el servidor web, reemplazar con la ruta de un directorio con permisos de escritura.

---

## Verificación

Después de aplicar el parche, verificar que:

1. El archivo `.js` corregido **no contiene** la cadena original `window.parent.RiseLMSInterface` de forma directa (solo dentro del `try/catch`)
2. El paquete corregido carga correctamente en un iframe embebido en Moodle
3. El paquete sigue funcionando en modo standalone (pestaña directa)

---

## ¿Se puede resolver con configuración del servidor?

No. Es importante entenderlo para no buscar la solución en el lado incorrecto.

El error lo lanza el **navegador**, no el servidor. La política Same-Origin (SOP) del navegador bloquea el acceso a `window.parent` cuando el iframe y la página padre tienen orígenes distintos. Ningún header HTTP ni configuración de servidor puede desactivar esa política — existe precisamente para proteger al usuario.

**Lo que se suele intentar y no funciona:**

| Técnica | Por qué no resuelve el problema |
|---|---|
| Headers CORS (`Access-Control-Allow-Origin`) | Controlan peticiones HTTP (fetch/XHR), no el acceso al DOM entre frames |
| `document.domain` | Técnica deprecada, ignorada por Chrome/Edge modernos |
| Headers COEP / COOP | Van en dirección contraria: restringen más, no relajan |

**La única alternativa sin parchear el ZIP** es servir el contenido Rise desde el mismo origen que Moodle:

- **Reverse proxy**: configurar Nginx/Apache en el servidor de Moodle para que una ruta como `moodle.ejemplo.com/repositorio/` apunte internamente al servidor de archivos Rise. Para el navegador, todo está en el mismo origen.
- **Alojar los archivos directamente en Moodle**: subir los ZIPs extraídos al servidor de Moodle en lugar de a un repositorio externo.

Ambas opciones implican un cambio de infraestructura que generalmente no es viable cuando el repositorio está en un servidor separado por diseño. **El parche al ZIP es la solución correcta y menos invasiva** para ese escenario.

---

## Notas adicionales

- La corrección es **no destructiva**: no modifica el comportamiento del paquete en contextos donde el acceso a `window.parent` sí está permitido (mismo origen o iframe con `allow-same-origin`)
- Si Rise publica una actualización que resuelva el problema de forma oficial, los paquetes nuevos no necesitarán este parche
- Los paquetes SCORM no están afectados — el problema es exclusivo de exportaciones **Rise Web**
