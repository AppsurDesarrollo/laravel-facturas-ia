<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Events;

use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Models\ExtractionRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cuando la extracción de un documento falla (ningún run válido).
 */
class ExtractionFailed
{
    use Dispatchable;

    public function __construct(
        public Document $document,
        public ExtractionRun $run,
    ) {}
}
