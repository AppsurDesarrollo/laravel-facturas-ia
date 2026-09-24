<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Añade los campos "base_imponible" (total sin IVA) y "cuota_iva" (importe del IVA)
 * a la cabecera de la factura y refresca el prompt para que la IA los extraiga.
 * En instalaciones nuevas la siembra inicial ya los trae desde config.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('facturas-ia.fields', function ($fields) {
            // El migrator puede entregar stdClass (JSON decodificado): normalizar a array.
            $fields = json_decode((string) json_encode($fields), true) ?: [];
            $factura = $fields['factura'] ?? [];
            $keys = array_column($factura, 'key');

            $nuevos = [];
            if (! in_array('base_imponible', $keys, true)) {
                $nuevos[] = ['key' => 'base_imponible', 'label' => 'Base imponible (total sin IVA)', 'type' => 'number', 'enabled' => true];
            }
            if (! in_array('cuota_iva', $keys, true)) {
                $nuevos[] = ['key' => 'cuota_iva', 'label' => 'Cuota de IVA (importe)', 'type' => 'number', 'enabled' => true];
            }

            if ($nuevos !== []) {
                // Insertar antes de "total" si existe, si no al final.
                $idx = array_search('total', $keys, true);
                if ($idx === false) {
                    $factura = array_merge($factura, $nuevos);
                } else {
                    array_splice($factura, (int) $idx, 0, $nuevos);
                }
                $fields['factura'] = $factura;
            }

            return $fields;
        });

        $this->migrator->update('facturas-ia.prompt', fn () => (string) config('facturas-ia.prompt', ''));
    }
};
