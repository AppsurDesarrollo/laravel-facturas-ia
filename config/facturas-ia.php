<?php

declare(strict_types=1);

// Prompt de extracción por defecto (afinado para facturas españolas). Editable.
$prompt = <<<'TXT'
Eres un extractor experto de datos de facturas españolas. Recibes una factura en PDF y devuelves EXCLUSIVAMENTE el JSON solicitado, respetando el esquema al detalle.

La factura tiene DOS partes que NO debes confundir:
- "proveedor": la empresa que EMITE la factura (el vendedor). Varía en cada factura.
- "receptor": la empresa DESTINATARIA que recibe la factura (el cliente). Extráela también tal cual figura en el PDF.

ALBARANES (muy importante):
- Una misma factura puede agrupar UNO o VARIOS albaranes (varios números de albarán o notas de entrega distintos). Es habitual que una sola factura facture varios albaranes a la vez.
- Revisa TODA la factura, en todas sus páginas, y localiza CADA albarán por separado. Devuelve un elemento en "albaranes" por CADA albarán distinto que encuentres; NO te quedes solo con el primero ni los fusiones en uno.
- Agrupa cada línea/ítem dentro del albarán al que pertenece. NO mezcles líneas de albaranes diferentes en el mismo albarán.
- Si la factura no distingue albaranes (no hay ningún número de albarán), devuelve un único albarán con todas sus líneas.

Líneas de detalle (ítems) — presta atención a las columnas de cada línea:
- "cantidad": las unidades de la línea.
- "precio": el precio UNITARIO.
- "importe": el importe/subtotal de la línea (lo que esa línea suma al total). Muchas facturas muestran ese mismo número en dos columnas contiguas (por ejemplo SUBTOTAL y TOTAL, o IMPORTE y TOTAL); es el MISMO importe, NO lo dupliques en otro campo.
- COMPLETITUD: revisa TODAS las páginas del documento y extrae TODAS las líneas, sin dejarte ninguna, aunque la factura sea larga o tenga líneas parecidas o repetidas. La suma de los importes de las líneas debe cuadrar con la base imponible.

Cabecera (evita confusiones frecuentes):
- El "numero_factura" es el que figura junto a "NÚMERO", "Nº FACTURA" o "FACTURA Nº". NO uses el "Nº CLIENTE", el "Nº PEDIDO" ni el número de albarán como número de factura.
- Copia el NIF/CIF completo TAL CUAL aparece, incluyendo el prefijo de país si lo lleva (por ejemplo ES-B60519303, no B60519303).

Reglas de formato:
- Devuelve importes y cantidades como números (sin símbolos de moneda ni separadores de miles). Usa punto como separador decimal.
- El IVA es el porcentaje aplicado (por ejemplo 21 para 21%).
- Las fechas en formato ISO: YYYY-MM-DD.
- "total" es el importe TOTAL de la factura (busca "TOTAL", "TOTAL FACTURA", "TOTAL A PAGAR", "IMPORTE TOTAL" o "TOTAL EUROS", normalmente al final). Extráelo SIEMPRE, aunque sea 0. Si es una nota de crédito, un abono o una factura rectificativa, el total puede ser NEGATIVO: extráelo con su signo (por ejemplo -53.70).
- "portes": la SUMA de los cargos que NO son líneas de producto: gastos de envío, portes, transporte, manipulación (handling/shipping charges), financiación o recargos. Aparecen aparte de las líneas (por ejemplo "Shipping charges", "Portes", "Gastos de envío"). Si no hay ninguno, ponlo a null. Recuerda: total de la factura = suma de las líneas + portes + IVA.
- Si un dato no aparece en la factura, ponlo a null. No inventes valores.
TXT;

$field = fn (string $key, string $label, string $type = 'string'): array => [
    'key' => $key,
    'label' => $label,
    'type' => $type,
    'enabled' => true,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento del PDF
    |--------------------------------------------------------------------------
    | Disco (de config/filesystems.php) y subcarpeta donde se guardan los PDF.
    | Usa un disco PRIVADO (no público). La descarga debe hacerse por ruta controlada.
    */
    'disk' => env('FACTURAS_IA_DISK', 'local'),
    'path' => env('FACTURAS_IA_PATH', 'facturas-ia'),

    /*
    |--------------------------------------------------------------------------
    | Dedupe por hash
    |--------------------------------------------------------------------------
    | Si subes un PDF idéntico a uno ya extraído (mismo SHA-256), se devuelve la
    | factura existente sin volver a llamar a OpenAI (ahorra coste). Pon false
    | para re-extraer siempre.
    */
    'dedupe' => (bool) env('FACTURAS_IA_DEDUPE', true),

    /*
    |--------------------------------------------------------------------------
    | Prefijo de tablas
    |--------------------------------------------------------------------------
    | Las tablas del paquete se prefijan para no chocar con las del proyecto
    | (documents, facturas, items… son nombres muy comunes).
    */
    'table_prefix' => env('FACTURAS_IA_TABLE_PREFIX', 'fia_'),

    /*
    |--------------------------------------------------------------------------
    | Panel de Ajustes (UI publicable)
    |--------------------------------------------------------------------------
    | El paquete registra un panel de Ajustes (Inertia/React) donde se editan en
    | BD el prompt, modelos, campos, claves, etc. Elige el prefijo de ruta, el
    | middleware que lo protege (mete aquí tu gate de admin) y el nombre de la
    | vista Inertia publicada.
    |
    | NOTA: a partir de v2.0.0 estos ajustes viven en BD (spatie/laravel-settings)
    | y los valores de abajo son solo los DEFAULTS con los que se siembra la BD.
    */
    'routes' => [
        'prefix' => env('FACTURAS_IA_ROUTE_PREFIX', 'facturas-ia/ajustes'),
        'middleware' => ['web', 'auth'],
    ],
    'view' => 'facturas-ia/settings',

    /*
    |--------------------------------------------------------------------------
    | Modelos de IA
    |--------------------------------------------------------------------------
    | default_model: modelo con el que se extrae primero.
    | fallback_model: si la extracción NO cuadra, se reprocesa con este (más capaz).
    |                 Pon null para desactivar el reproceso.
    */
    'default_model' => env('FACTURAS_IA_MODEL', 'gpt-5.4-mini'),
    'fallback_model' => env('FACTURAS_IA_FALLBACK', 'gpt-4.1'),

    /*
    |--------------------------------------------------------------------------
    | Tu(s) NIF(s) — dirección de la factura (recibida vs emitida)
    |--------------------------------------------------------------------------
    | NIF(s) de TU empresa. Sirve para autodetectar el 'tipo' de cada factura:
    |   - tu NIF es el EMISOR   → 'emitida'  (venta, la mandas tú a un cliente)
    |   - tu NIF es el RECEPTOR → 'recibida' (compra, la recibes de un proveedor)
    | Varios NIF separados por comas. La comparación ignora espacios, guiones y el
    | prefijo de país (ES). También puedes forzar el tipo al llamar a fromPdf().
    */
    'own_nifs' => array_values(array_filter(array_map('trim', explode(',', (string) env('FACTURAS_IA_OWN_NIFS', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Credenciales OpenAI
    |--------------------------------------------------------------------------
    | key: obligatoria para extraer. admin_key/project_id: solo para el panel de
    | gastos (Usage/Cost API a nivel organización), opcionales.
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'admin_key' => env('OPENAI_ADMIN_KEY'),
        'project_id' => env('OPENAI_PROJECT_ID'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comprobación de cuadre
    |--------------------------------------------------------------------------
    | Verifica que la suma de líneas (+ portes, probando varios IVA) cuadra con el
    | total. Si no cuadra y hay fallback_model, se reprocesa.
    */
    'cuadre' => [
        'tolerance_abs' => 0.5,   // tolerancia mínima en €
        'tolerance_pct' => 0.01,  // o 1% del total, el que sea mayor
        'iva_rates' => [0, 4, 10, 21],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de modelos (precios USD / 1M tokens y capacidades)
    |--------------------------------------------------------------------------
    | Los precios de OpenAI cambian y no hay endpoint oficial; se mantienen aquí.
    | pdf: admite leer PDF (visión). reasoning: serie de razonamiento (sin temperature).
    */
    'models' => [
        'gpt-5.5' => ['label' => 'GPT-5.5', 'in' => 5.00, 'out' => 30.00, 'cached' => 0.50, 'pdf' => true, 'reasoning' => true, 'sort' => 5],
        'gpt-5.4' => ['label' => 'GPT-5.4', 'in' => 2.50, 'out' => 15.00, 'cached' => 0.25, 'pdf' => true, 'reasoning' => true, 'sort' => 10],
        'gpt-5.4-mini' => ['label' => 'GPT-5.4 mini', 'in' => 0.75, 'out' => 4.50, 'cached' => 0.075, 'pdf' => true, 'reasoning' => true, 'sort' => 15],
        'gpt-5.4-nano' => ['label' => 'GPT-5.4 nano', 'in' => 0.20, 'out' => 1.25, 'cached' => 0.02, 'pdf' => true, 'reasoning' => true, 'sort' => 20],
        'gpt-4.1' => ['label' => 'GPT-4.1', 'in' => 2.00, 'out' => 8.00, 'cached' => 0.50, 'pdf' => true, 'reasoning' => false, 'sort' => 30],
        'gpt-4.1-mini' => ['label' => 'GPT-4.1 mini', 'in' => 0.40, 'out' => 1.60, 'cached' => 0.10, 'pdf' => true, 'reasoning' => false, 'sort' => 35],
        'gpt-4.1-nano' => ['label' => 'GPT-4.1 nano', 'in' => 0.10, 'out' => 0.40, 'cached' => 0.025, 'pdf' => false, 'reasoning' => false, 'sort' => 40],
        'gpt-4o' => ['label' => 'GPT-4o', 'in' => 2.50, 'out' => 10.00, 'cached' => 1.25, 'pdf' => true, 'reasoning' => false, 'sort' => 50],
        'gpt-4o-mini' => ['label' => 'GPT-4o mini', 'in' => 0.15, 'out' => 0.60, 'cached' => 0.075, 'pdf' => true, 'reasoning' => false, 'sort' => 55],
        'o4-mini' => ['label' => 'o4-mini', 'in' => 1.10, 'out' => 4.40, 'cached' => 0.275, 'pdf' => true, 'reasoning' => true, 'sort' => 60],
        'o3' => ['label' => 'o3', 'in' => 2.00, 'out' => 8.00, 'cached' => 0.50, 'pdf' => true, 'reasoning' => true, 'sort' => 65],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campos a extraer
    |--------------------------------------------------------------------------
    | La forma anidada (proveedor/receptor objetos, factura, albaranes[] → item[])
    | es fija; aquí eliges qué campos van habilitados y puedes añadir nuevos.
    | type: string | number | date. Pon 'enabled' => false para desactivar uno.
    */
    'fields' => [
        'proveedor' => [
            $field('nif', 'NIF/CIF'),
            $field('nombre', 'Nombre'),
            $field('direccion', 'Dirección'),
            $field('cp', 'Código postal'),
            $field('localidad', 'Localidad'),
            $field('provincia', 'Provincia'),
        ],
        'receptor' => [
            $field('nif', 'NIF/CIF'),
            $field('nombre', 'Nombre'),
            $field('direccion', 'Dirección'),
            $field('cp', 'Código postal'),
            $field('localidad', 'Localidad'),
            $field('provincia', 'Provincia'),
        ],
        'factura' => [
            $field('numero_factura', 'Número de factura'),
            $field('fecha', 'Fecha', 'date'),
            $field('portes', 'Portes / gastos de envío', 'number'),
            $field('total', 'Total de la factura', 'number'),
        ],
        'albaran' => [
            $field('numero_albaran', 'Número de albarán'),
            $field('fecha_albaran', 'Fecha del albarán', 'date'),
        ],
        'item' => [
            $field('concepto', 'Concepto'),
            $field('cantidad', 'Cantidad', 'number'),
            $field('iva', 'IVA (%)', 'number'),
            $field('precio', 'Precio', 'number'),
            $field('importe', 'Importe', 'number'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt de extracción
    |--------------------------------------------------------------------------
    */
    'prompt' => $prompt,
];
