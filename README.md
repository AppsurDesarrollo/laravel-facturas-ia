# appsur/laravel-facturas-ia

Extracción de **facturas españolas en PDF con IA** (OpenAI) y guardado **estructurado**
(proveedor, receptor, factura, albaranes y líneas) para cualquier proyecto Laravel.

Trae **todo de serie**: el modelo de IA, el modelo de respaldo, el **prompt** afinado para
facturas españolas (NIF con prefijo de país, IVA, varios albaranes por factura, portes,
abonos con total negativo, completitud), los **campos** de la factura y las **tablas** de BD.
Funciona nada más instalarlo y luego lo puedes editar en `config/facturas-ia.php`.

Es **headless**: no monta rutas ni UI ni envía datos a ningún sitio. Tú lo llamas y decides
qué hacer con la `Factura` que devuelve (o escuchas sus eventos).

## Requisitos
- PHP ^8.2 · Laravel 11, 12 o 13
- Una API key de OpenAI con acceso a la Responses API (visión + structured outputs)

## Instalación

El paquete vive en un repo privado de AppsurDesarrollo (no está en Packagist):

```bash
composer config repositories.appsur-facturas-ia vcs https://github.com/AppsurDesarrollo/laravel-facturas-ia
composer require appsur/laravel-facturas-ia:^1.0

php artisan vendor:publish --tag=facturas-ia-config      # config editable (opcional)
php artisan vendor:publish --tag=facturas-ia-migrations
php artisan migrate
```

`.env`:

```env
OPENAI_API_KEY="sk-..."
# Opcionales (panel de gastos, Usage/Cost API a nivel organización):
OPENAI_ADMIN_KEY="sk-admin-..."
OPENAI_PROJECT_ID="proj_..."
# Opcionales (por defecto ya son estos):
FACTURAS_IA_MODEL=gpt-5.4-mini
FACTURAS_IA_FALLBACK=gpt-4.1
# Tu(s) NIF para distinguir facturas emitidas (venta) de recibidas (compra):
FACTURAS_IA_OWN_NIFS="B12345678,ES-B99999999"
```

> O usa el comando `/install-facturas-ia` (Claude Code) que hace todo esto por ti.

## Uso

Una sola llamada: sube el PDF, extrae (con reproceso automático si no cuadra), normaliza y
devuelve la `Factura` con sus relaciones.

```php
use Appsur\FacturasIa\Facades\FacturasIa;

$factura = FacturasIa::fromPdf($request->file('pdf'), auth()->id());
// o desde una ruta en disco:
$factura = FacturasIa::fromPdf(storage_path('app/facturas/ejemplo.pdf'));

$factura->numero;      // "260492"
$factura->total;       // 4773.85
$factura->cuadra;      // true  (la suma de líneas + portes cuadra con el total)
$factura->duplicada;   // true si ya existe otra con el mismo NIF + nº
$factura->proveedor;   // Proveedor (dedupe por NIF)
$factura->receptor;    // Receptor
foreach ($factura->albaranes as $albaran) {
    foreach ($albaran->items as $item) {
        // $item->concepto, ->cantidad, ->iva, ->precio, ->importe
    }
}
```

Si la extracción falla lanza `Appsur\FacturasIa\Exceptions\ExtractionException`.

### Recibidas (compra) vs emitidas (venta)

Cada factura guarda su **`tipo`**: `recibida` (la recibes de un proveedor) o `emitida`
(la emites tú a un cliente). Dos formas de fijarlo:

```php
// 1) Explícito: si ya sabes en qué flujo estás (subida de "recibidas" vs "emitidas").
$factura = FacturasIa::fromPdf($pdf, auth()->id(), tipo: 'emitida');

// 2) Automático: define tu(s) NIF en FACTURAS_IA_OWN_NIFS (o config own_nifs) y se
//    deduce solo: tu NIF como emisor → 'emitida'; como receptor → 'recibida'.
$factura = FacturasIa::fromPdf($pdf);
$factura->tipo; // 'recibida' | 'emitida' | null (si no se pudo determinar)
```

Consultas cómodas con los scopes del modelo:

```php
use Appsur\FacturasIa\Models\Factura;
Factura::recibidas()->sum('total'); // compras (IVA soportado)
Factura::emitidas()->sum('total');  // ventas   (IVA repercutido)
```

### Extracción en cola (async)
`fromPdf()` es **síncrono** y bloquea la request ~10-20s mientras OpenAI procesa. Para no
bloquear una petición web, usa la cola: sube el PDF (rápido) y despacha la extracción a un
worker.

```php
// Sube y despacha el Job; devuelve el Document (consulta ->factura cuando esté listo).
$document = FacturasIa::queueFromPdf($request->file('pdf'), auth()->id());

// o, si el Document ya está subido:
dispatch(new \Appsur\FacturasIa\Jobs\ExtractFacturaJob($document));
```
Escucha `FacturaExtracted` / `ExtractionFailed` para saber cuándo termina (necesitas un
worker: `php artisan queue:work`).

### Dedupe
Si subes un **PDF idéntico** a uno ya extraído (mismo SHA-256), se devuelve la factura
existente **sin volver a llamar a OpenAI** (ahorra coste). Se controla con `config
facturas-ia.dedupe` (`FACTURAS_IA_DEDUPE`, por defecto `true`).

### Eventos (opcionales)
El paquete no envía nada; si quieres reaccionar (p. ej. mandar el JSON a tu API), escucha:

- `Appsur\FacturasIa\Events\FacturaExtracted` (`$event->factura`, `$event->run`)
- `Appsur\FacturasIa\Events\ExtractionFailed` (`$event->document`, `$event->run`)

## Configuración

Todo se edita en `config/facturas-ia.php`:

- **`default_model` / `fallback_model`** — modelo por defecto (`gpt-5.4-mini`) y el de respaldo
  al que se reprocesa si no cuadra (`gpt-4.1`; `null` desactiva el reproceso).
- **`own_nifs`** — tu(s) NIF (env `FACTURAS_IA_OWN_NIFS`) para autodetectar `tipo`
  (recibida/emitida) comparando con el emisor/receptor de cada factura.
- **`dedupe`** — si es `true` (por defecto), no re-extrae un PDF idéntico ya procesado.
- **`prompt`** — instrucciones de extracción.
- **`fields`** — qué campos se extraen de proveedor/receptor/factura/albarán/línea
  (activar/desactivar/añadir).
- **`models`** — catálogo con precios (USD/1M tokens) para calcular el coste.
- **`cuadre`** — tolerancias y tasas de IVA a probar.
- **`disk` / `path` / `table_prefix`** — dónde se guarda el PDF y prefijo de las tablas (`fia_`).

## Panel de gastos (opcional)

Con `OPENAI_ADMIN_KEY` configurada, `Appsur\FacturasIa\Services\OpenAiUsageService` lee el
gasto oficial de OpenAI (`costs()` / `usageByModel()`), filtrable por `OPENAI_PROJECT_ID`.

## Tablas

`fia_documents`, `fia_extraction_runs`, `fia_proveedores`, `fia_receptores`, `fia_facturas`,
`fia_albaranes`, `fia_items` (prefijo configurable).

## Licencia
Propietaria — AppsurDesarrollo.
