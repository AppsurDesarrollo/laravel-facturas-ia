<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Services\ExtractionSchemaBuilder;
use Appsur\FacturasIa\Settings\FacturasIaSettings;

class SettingsTest extends TestCase
{
    public function test_settings_are_seeded_from_config(): void
    {
        $s = app(FacturasIaSettings::class);
        $this->assertSame('gpt-5.4-mini', $s->defaultModel);
        $this->assertNotEmpty($s->models);
        $this->assertNotEmpty($s->fields);
        $this->assertArrayHasKey('proveedor', $s->fields);
    }

    public function test_schema_builder_reads_fields_from_db_settings(): void
    {
        // Cambiar en BD (no en config) debe reflejarse en el schema.
        $s = app(FacturasIaSettings::class);
        $fields = $s->fields;
        $fields['proveedor'] = []; // deshabilita el grupo proveedor
        $s->fields = $fields;
        $s->save();

        $schema = (new ExtractionSchemaBuilder)->build();
        $this->assertArrayNotHasKey('proveedor', $schema['properties']);
    }
}
