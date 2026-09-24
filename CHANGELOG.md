# Changelog

Todos los cambios relevantes de `appsur/laravel-facturas-ia`.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y [SemVer](https://semver.org/lang/es/).

## [2.1.0]

### Added
- **Base imponible y cuota de IVA:** la extracción devuelve también `base_imponible`
  (total sin IVA) y `cuota_iva` (importe del IVA), tal como aparecen desglosados en la
  factura. Nuevas columnas en `fia_facturas`, campos en `fields.factura` y prompt
  actualizado (con suma de bases/cuotas cuando hay varios tipos de IVA).
- Si la IA solo consigue uno de los dos valores, el otro se deduce por aritmética
  (`total = base + IVA`) en `FacturaNormalizer`.
- Migración de BD `2024_01_04_000000_add_base_iva_to_fia_facturas_table` y migración de
  ajustes `2024_01_04_000001_add_base_iva_fields` (añade los campos al grupo `factura`
  en instalaciones existentes y refresca el prompt).

## [2.0.0]

> **Breaking:** los ajustes editables pasan de `config/facturas-ia.php` a la **base de datos**
> (spatie/laravel-settings). Requiere instalar spatie, publicar la tabla `settings` y la
> migración de ajustes del paquete, y `migrate`. Ver README → *Instalación* y *Ajustes en BD*.

### Added
- **Ajustes en BD + panel:** clase `Settings\FacturasIaSettings` (grupo `facturas-ia`) y
  migración `database/settings/*` que siembra desde `config`. Todo lo editable vive en BD:
  `prompt`, `defaultModel` / `fallbackModel`, catálogo `models` (con precios), `fields`,
  `ownNifs`, `dedupe`, tolerancias de cuadre y **las claves de OpenAI** (`openaiKey`,
  `openaiAdminKey`, `openaiProjectId`, `openaiBaseUrl`).
- **Panel de Ajustes publicable** (React/Inertia + shadcn base, portable): página
  `resources/js/pages/facturas-ia/settings.tsx`, `Http\Controllers\SettingsController`
  (`edit`/`update`) y rutas `facturas-ia.settings.edit` / `.update` (tag `facturas-ia-views`;
  prefijo/middleware configurables en `config facturas-ia.routes`).
- Los servicios leen de BD con **fallback a `config`/`.env`**: si un ajuste está vacío o la
  tabla `settings` aún no existe, se usa el valor de config. El `.env` queda **opcional**.

### Changed
- `config/facturas-ia.php` conserva `prompt`, `models`, `fields`, `own_nifs`, `dedupe`,
  `cuadre` y `openai.*` **solo como valores por defecto** para sembrar la BD; añade el bloque
  `routes` (prefix/middleware) y `view` para el panel.
- Nueva dependencia `spatie/laravel-settings: ^3.0`.

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
