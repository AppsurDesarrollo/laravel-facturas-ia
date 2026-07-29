<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Services;

/**
 * Construye un JSON Schema válido para OpenAI Structured Outputs (strict) a partir
 * de los campos configurables en config('facturas-ia.fields').
 *
 * Reglas de strict-mode respetadas:
 *  - Todo objeto lleva additionalProperties:false.
 *  - "required" lista TODAS las claves de "properties" (la opcionalidad se modela
 *    como tipo nullable, p. ej. ["string","null"], no quitando la clave).
 */
class ExtractionSchemaBuilder
{
    public function build(): array
    {
        $fields = (array) config('facturas-ia.fields', []);
        $properties = [];

        // Un objeto sin propiedades es rechazado por OpenAI en strict mode: si un grupo no
        // tiene campos habilitados, se omite la clave en lugar de emitir un objeto vacío.
        if ($this->enabled($fields['proveedor'] ?? [])) {
            $properties['proveedor'] = $this->objectFromFields($fields['proveedor'] ?? []);
        }
        if ($this->enabled($fields['receptor'] ?? [])) {
            $properties['receptor'] = $this->objectFromFields($fields['receptor'] ?? []);
        }

        foreach ($this->enabled($fields['factura'] ?? []) as $field) {
            $properties[$field['key']] = $this->fieldSchema($field);
        }

        $properties['albaranes'] = [
            'type' => 'array',
            'description' => 'Lista de TODOS los albaranes de la factura. Una factura puede agrupar uno o varios albaranes '
                .'(con distintos números). Devuelve un elemento por CADA albarán distinto y agrupa sus líneas dentro de él; '
                .'no mezcles líneas de albaranes diferentes.',
            'minItems' => 1,
            'items' => $this->albaranSchema($fields),
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => array_keys($properties),
        ];
    }

    private function albaranSchema(array $fields): array
    {
        $properties = [];

        foreach ($this->enabled($fields['albaran'] ?? []) as $field) {
            $properties[$field['key']] = $this->fieldSchema($field);
        }

        $properties['items'] = [
            'type' => 'array',
            'description' => 'TODAS las líneas de detalle de este albarán, sin límite: incluye cada línea que aparezca.',
            'minItems' => 1,
            'items' => $this->objectFromFields($fields['item'] ?? []),
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => array_keys($properties),
        ];
    }

    /** Objeto con additionalProperties:false a partir de una lista de campos. */
    private function objectFromFields(array $fields): array
    {
        $properties = [];

        foreach ($this->enabled($fields) as $field) {
            $properties[$field['key']] = $this->fieldSchema($field);
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => array_keys($properties),
        ];
    }

    /** Esquema de un campo simple. Todos los campos son nullable. */
    private function fieldSchema(array $field): array
    {
        $type = $field['type'] ?? 'string';
        $label = $field['label'] ?? ($field['key'] ?? '');

        return match ($type) {
            'number' => [
                'type' => ['number', 'null'],
                'description' => $label,
            ],
            'date' => [
                'type' => ['string', 'null'],
                'description' => trim($label.' (formato ISO 8601: YYYY-MM-DD)'),
            ],
            default => [
                'type' => ['string', 'null'],
                'description' => $label,
            ],
        };
    }

    /** @return array<int, array> solo los campos con enabled !== false */
    private function enabled(array $fields): array
    {
        return array_values(array_filter($fields, fn ($f) => ($f['enabled'] ?? true) !== false));
    }
}
