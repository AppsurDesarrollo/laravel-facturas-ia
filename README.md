# appsur/laravel-facturas-ia

[![tests](https://github.com/AppsurDesarrollo/laravel-facturas-ia/actions/workflows/tests.yml/badge.svg)](https://github.com/AppsurDesarrollo/laravel-facturas-ia/actions/workflows/tests.yml)

Extracción de **facturas españolas en PDF con IA** (OpenAI) y guardado **estructurado**
(proveedor, receptor, factura, albaranes y líneas) para cualquier proyecto Laravel.

Trae **todo de serie**: el modelo de IA, el modelo de respaldo, el **prompt** afinado para
facturas españolas (NIF con prefijo de país, IVA, varios albaranes por factura, portes,
abonos con total negativo, completitud), los **campos** de la factura y las **tablas** de BD.
Funciona nada más instalarlo.

**Ajustes en base de datos + panel (v2.0):** todo lo editable (prompt, modelos y precios,
campos, NIF propios, tolerancias de cuadre, **y las claves de OpenAI**) se guarda en BD vía
[spatie/laravel-settings](https://github.com/spatie/laravel-settings) y se edita desde un
**panel de Ajustes** que el propio paquete aporta (página React/Inertia publicable). El
`config/facturas-ia.php` queda solo para **infra** (disco, rutas, prefijo de tablas) y como
**valores por defecto** para sembrar la BD. Ver [Ajustes en BD + panel](#ajustes-en-bd--panel).

El **motor de extracción sigue siendo headless**: `FacturasIa::fromPdf()` no envía datos a
ningún sitio; tú decides qué hacer con la `Factura` (o escuchas sus eventos). Lo único con UI
es el panel de Ajustes, y es **opcional** (solo si publicas la vista y la enlazas).

## Requisitos
- PHP ^8.2 · Laravel 11, 12 o 13
- Una API key de OpenAI con acceso a la Responses API (visión + structured outputs)
- [spatie/laravel-settings](https://github.com/spatie/laravel-settings) ^3.0 (se instala como
  dependencia; requiere la tabla `settings`)
- Solo para el **panel de Ajustes**: la app debe usar Inertia + React (stack shadcn/ui). Si no
  usas el panel, puedes configurarlo todo por `config`/`.env` y `tinker`.

## Instalación

El paquete vive en el repo público de AppsurDesarrollo (no está en Packagist), así que se
instala vía VCS:

```bash
composer config repositories.appsur-facturas-ia vcs https://github.com/AppsurDesarrollo/laravel-facturas-ia
composer require appsur/laravel-facturas-ia:^2.0

# Tabla `settings` de spatie (una vez por app, si no la tienes ya):
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag=migrations

# Del paquete:
php artisan vendor:publish --tag=facturas-ia-config       # infra + defaults (opcional)
php artisan vendor:publish --tag=facturas-ia-migrations   # tablas fia_*
php artisan vendor:publish --tag=facturas-ia-settings     # migración que siembra los ajustes en BD
php artisan vendor:publish --tag=facturas-ia-views        # panel de Ajustes (resources/js/pages/facturas-ia/)

php artisan migrate   # crea tablas + siembra el grupo de ajustes `facturas-ia`
```

`.env` **opcional** (v2.0): al ser todo editable en el panel/BD, no necesitas `.env`. Las
variables solo se usan como **valor inicial** al sembrar la BD en el primer `migrate` y como
**fallback** si el ajuste está vacío en BD:

```env
OPENAI_API_KEY="sk-..."           # o déjalo vacío y pon la clave en el panel de Ajustes
OPENAI_ADMIN_KEY="sk-admin-..."   # panel de gastos (Usage/Cost API), opcional
OPENAI_PROJECT_ID="proj_..."      # opcional
FACTURAS_IA_OWN_NIFS="TU_NIF"     # ejemplo; vacío por defecto. Tus NIF (coma para varios: "B12345678,ES-B99999999") → emitidas vs recibidas
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

$factura->numero;         // "260492"
$factura->base_imponible; // 3945.33  (total sin IVA; si falta, se deduce: total − cuota)
$factura->cuota_iva;      // 828.52   (importe del IVA; si falta, se deduce: total − base)
$factura->total;          // 4773.85
$factura->cuadra;         // true  (la suma de líneas + portes cuadra con el total)
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

## Ajustes en BD + panel

En v2.0 los ajustes **editables** viven en la **base de datos** (grupo `facturas-ia` de la
tabla `settings` de spatie) y se editan desde un **panel** que trae el paquete. Los servicios
leen de BD y, si un ajuste está vacío o la tabla aún no está migrada, **caen a `config`/`.env`**.

**Editable en BD / panel:** `prompt`, `defaultModel` / `fallbackModel`, `models` (catálogo +
precios USD/1M), `fields` (campos por grupo), `ownNifs`, `dedupe`, tolerancias de cuadre
(`cuadreToleranceAbs` / `cuadreTolerancePct` / `cuadreIvaRates`) y **las claves de OpenAI**
(`openaiKey`, `openaiAdminKey`, `openaiProjectId`, `openaiBaseUrl`).

**Solo en `config/facturas-ia.php` (infra + defaults, no en el panel):** `disk`, `path`,
`table_prefix`, `routes` (`prefix` / `middleware`) y `view`. Estos valores también sirven de
**semilla**: la migración de settings copia de `config` a BD en el primer `migrate`.

### Cómo se resuelve cada ajuste
`app(Appsur\FacturasIa\Settings\FacturasIaSettings::class)` (BD) → si está vacío o spatie no
está listo → `config('facturas-ia.*')` → default del propio ajuste. Puedes cambiarlo todo por
`tinker` sin panel:

```php
$s = app(Appsur\FacturasIa\Settings\FacturasIaSettings::class);
$s->defaultModel = 'gpt-4.1';
$s->openaiKey = 'sk-...';
$s->save();
```

### Integrar el panel en tu `/ajustes`
El paquete registra las rutas y renderiza la página publicada en
`resources/js/pages/facturas-ia/settings.tsx` (Inertia + shadcn base, portable). No se puede
inyectar dentro del `Tabs` de tu panel entre paquetes, así que **enlázalo** como un apartado
más:

- Ruta del panel (nombre): `facturas-ia.settings.edit` — por defecto en `/facturas-ia/ajustes`
  (configurable con `routes.prefix`; middleware por defecto `['web','auth']`).
- Añade en tu `/ajustes` una pestaña/enlace que navegue ahí, p. ej.:

```tsx
import { Link } from '@inertiajs/react';
<Link href={route('facturas-ia.settings.edit')}>Extracción IA</Link>
```

> **Seguridad:** al guardar las claves de OpenAI en BD, quedan en la tabla `settings` en claro
> (spatie no cifra por defecto). Protege la ruta del panel con el rol adecuado (ajusta
> `facturas-ia.routes.middleware`), restringe el acceso a la BD y considera cifrar la columna
> `payload` o dejar las claves en `.env` si tu modelo de amenazas lo requiere.

## Configuración (infra)

`config/facturas-ia.php` — lo que **no** va al panel:

- **`disk` / `path` / `table_prefix`** — dónde se guarda el PDF y prefijo de las tablas (`fia_`).
- **`routes.prefix` / `routes.middleware`** — URL y middleware del panel de Ajustes.
- **`view`** — nombre del componente Inertia del panel (`facturas-ia/settings`).
- El resto de claves (`prompt`, `models`, `fields`, `own_nifs`, `dedupe`, `cuadre`, `openai.*`,
  `default_model` / `fallback_model`) siguen aquí **como valores por defecto** para sembrar la BD.

## Panel de gastos (opcional)

Con `OPENAI_ADMIN_KEY` configurada, `Appsur\FacturasIa\Services\OpenAiUsageService` lee el
gasto oficial de OpenAI (`costs()` / `usageByModel()`), filtrable por `OPENAI_PROJECT_ID`.

## Tablas

`fia_documents`, `fia_extraction_runs`, `fia_proveedores`, `fia_receptores`, `fia_facturas`,
`fia_albaranes`, `fia_items` (prefijo configurable).

## Licencia
Propietaria — AppsurDesarrollo.
