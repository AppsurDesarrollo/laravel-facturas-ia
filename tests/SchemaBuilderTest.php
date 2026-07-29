<?php

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Services\ExtractionSchemaBuilder;

class SchemaBuilderTest extends TestCase
{
    public function test_schema_is_strict_valid(): void
    {
        $schema = (new ExtractionSchemaBuilder())->build();

        $this->assertStrict($schema);

        // Grupos anidados esperados presentes.
        $this->assertArrayHasKey('proveedor', $schema['properties']);
        $this->assertArrayHasKey('receptor', $schema['properties']);
        $this->assertArrayHasKey('albaranes', $schema['properties']);
        $this->assertSame('array', $schema['properties']['albaranes']['type']);
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
