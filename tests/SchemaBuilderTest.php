<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Services\ExtractionSchemaBuilder;

class SchemaBuilderTest extends TestCase
{
    public function test_schema_is_strict_valid(): void
    {
        $schema = (new ExtractionSchemaBuilder)->build();

        $this->assertStrict($schema);

        // Grupos anidados esperados presentes.
        $this->assertArrayHasKey('proveedor', $schema['properties']);
        $this->assertArrayHasKey('receptor', $schema['properties']);
        $this->assertArrayHasKey('albaranes', $schema['properties']);
        $this->assertSame('array', $schema['properties']['albaranes']['type']);
    }

    public function test_group_without_fields_is_omitted_not_emitted_empty(): void
    {
        // Deshabilitar todos los campos de un grupo NO debe generar un objeto vacío
        // (OpenAI strict lo rechaza): la clave se omite y el schema sigue siendo válido.
        config(['facturas-ia.fields.proveedor' => []]);

        $schema = (new ExtractionSchemaBuilder)->build();

        $this->assertArrayNotHasKey('proveedor', $schema['properties']);
        $this->assertNotContains('proveedor', $schema['required']);
        $this->assertStrict($schema);
    }

    /** Regla strict de OpenAI: additionalProperties:false y required == keys en cada objeto. */
    private function assertStrict(array $node): void
    {
        $type = $node['type'] ?? null;

        if ($type === 'object') {
            $this->assertArrayHasKey('additionalProperties', $node);
            $this->assertFalse($node['additionalProperties']);
            $this->assertEqualsCanonicalizing(
                array_keys($node['properties']),
                $node['required'],
                'required debe listar todas las propiedades',
            );
            foreach ($node['properties'] as $child) {
                $this->assertStrict($child);
            }
        }

        if ($type === 'array') {
            $this->assertStrict($node['items']);
        }
    }
}
