# Changelog

Todos los cambios relevantes de `appsur/laravel-facturas-ia`.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y [SemVer](https://semver.org/lang/es/).

## [1.2.0]

### Added
- **Extracción en cola (async):** `ExtractFacturaJob` (`ShouldQueue`) y helper `FacturasIa::queueFromPdf()` para no bloquear la request web durante los ~10-20s de OpenAI.
- **Dedupe por hash** (`config facturas-ia.dedupe`, por defecto `true`): re-subir un PDF idéntico devuelve la factura existente sin volver a llamar a OpenAI.
- **Validación temprana** de configuración (clave OpenAI y modelo por defecto en catálogo) antes de tocar disco/BD.
- CI (GitHub Actions), análisis estático (Larastan/PHPStan nivel 5), estilo (Pint), `declare(strict_types=1)` y más tests (fallo → evento/excepción, dedupe, upload, usage, cuadre, schema con grupo vacío).

### Fixed
- **Reproceso con el modelo de respaldo también cuando la 1ª extracción FALLA** (antes solo si no cuadraba); una caída transitoria del modelo primario ya no aborta la extracción.
- El schema ya no emite objetos vacíos (que OpenAI strict rechaza) cuando se deshabilitan todos los campos de un grupo: se omite la clave.

### Removed
- Campo `descuento` de `items` (era un campo muerto: se persistía pero el schema nunca lo pedía, así que siempre era `null`).

## [1.1.0]

### Added
- Campo `tipo` de la factura: `recibida` (compra) / `emitida` (venta). Se fija explícitamente (`FacturasIa::fromPdf($pdf, tipo: 'emitida')`) o se autodetecta comparando tu NIF (`config own_nifs` / `FACTURAS_IA_OWN_NIFS`) con emisor/receptor. Scopes `Factura::recibidas()` / `emitidas()`.

## [1.0.0]

### Added
- Versión inicial: extracción de facturas españolas en PDF con OpenAI (Responses API) y guardado estructurado (proveedor, receptor, factura, albaranes, líneas) con cuadre, dedupe de proveedores por NIF y reproceso con modelo de respaldo. Headless, configurable en `config/facturas-ia.php`. Eventos `FacturaExtracted` / `ExtractionFailed`.
