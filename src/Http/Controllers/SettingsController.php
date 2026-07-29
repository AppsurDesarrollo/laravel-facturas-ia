<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Http\Controllers;

use Appsur\FacturasIa\Settings\FacturasIaSettings;
use Appsur\FacturasIa\Support\OpenAiModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de Ajustes del paquete (Inertia). Lee/guarda FacturasIaSettings (BD).
 */
class SettingsController
{
    public function edit(FacturasIaSettings $settings): Response
    {
        return Inertia::render((string) config('facturas-ia.view', 'facturas-ia/settings'), [
            'settings' => [
                'prompt' => $settings->prompt,
                'defaultModel' => $settings->defaultModel,
                'fallbackModel' => $settings->fallbackModel,
                'ownNifs' => $settings->ownNifs,
                'dedupe' => $settings->dedupe,
                'fields' => $settings->fields,
                'cuadreToleranceAbs' => $settings->cuadreToleranceAbs,
                'cuadreTolerancePct' => $settings->cuadreTolerancePct,
                'cuadreIvaRates' => $settings->cuadreIvaRates,
                'models' => OpenAiModelCatalog::all(), // lista con model_id (para tabla editable y selects)
                'openaiKey' => $settings->openaiKey,
                'openaiAdminKey' => $settings->openaiAdminKey,
                'openaiProjectId' => $settings->openaiProjectId,
                'openaiBaseUrl' => $settings->openaiBaseUrl,
            ],
            'updateUrl' => route('facturas-ia.settings.update'),
        ]);
    }

    public function update(Request $request, FacturasIaSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:20000'],
            'defaultModel' => ['required', 'string', 'max:100'],
            'fallbackModel' => ['nullable', 'string', 'max:100'],
            'ownNifs' => ['present', 'array'],
            'ownNifs.*' => ['string', 'max:40'],
            'dedupe' => ['boolean'],
            'fields' => ['present', 'array'],
            'cuadreToleranceAbs' => ['numeric', 'min:0'],
            'cuadreTolerancePct' => ['numeric', 'min:0', 'max:1'],
            'cuadreIvaRates' => ['present', 'array'],
            'cuadreIvaRates.*' => ['numeric', 'min:0', 'max:100'],
            'models' => ['present', 'array'],
            'openaiKey' => ['nullable', 'string', 'max:300'],
            'openaiAdminKey' => ['nullable', 'string', 'max:300'],
            'openaiProjectId' => ['nullable', 'string', 'max:200'],
            'openaiBaseUrl' => ['required', 'string', 'max:300'],
        ]);

        $settings->prompt = $data['prompt'];
        $settings->defaultModel = $data['defaultModel'];
        $settings->fallbackModel = ($data['fallbackModel'] ?? '') !== '' ? $data['fallbackModel'] : null;
        $settings->ownNifs = array_values(array_filter(array_map(fn ($n) => trim((string) $n), $data['ownNifs'])));
        $settings->dedupe = (bool) ($data['dedupe'] ?? true);
        $settings->fields = $this->normalizeFields($data['fields']);
        $settings->cuadreToleranceAbs = (float) $data['cuadreToleranceAbs'];
        $settings->cuadreTolerancePct = (float) $data['cuadreTolerancePct'];
        $settings->cuadreIvaRates = array_values(array_map(fn ($v) => (float) $v, $data['cuadreIvaRates']));
        $settings->models = $this->normalizeModels($data['models']);
        $settings->openaiKey = ($data['openaiKey'] ?? '') !== '' ? $data['openaiKey'] : null;
        $settings->openaiAdminKey = ($data['openaiAdminKey'] ?? '') !== '' ? $data['openaiAdminKey'] : null;
        $settings->openaiProjectId = ($data['openaiProjectId'] ?? '') !== '' ? $data['openaiProjectId'] : null;
        $settings->openaiBaseUrl = $data['openaiBaseUrl'];
        $settings->save();

        return back()->with('success', 'Ajustes guardados.');
    }

    /** @param  array<string, mixed>  $groups */
    private function normalizeFields(array $groups): array
    {
        $out = [];
        foreach (['proveedor', 'receptor', 'factura', 'albaran', 'item'] as $g) {
            $fields = is_array($groups[$g] ?? null) ? $groups[$g] : [];
            $out[$g] = collect($fields)
                ->map(fn ($f) => [
                    'key' => Str::slug((string) ($f['key'] ?? ''), '_'),
                    'label' => (string) ($f['label'] ?? ''),
                    'type' => in_array($f['type'] ?? 'string', ['string', 'number', 'date'], true) ? $f['type'] : 'string',
                    'enabled' => (bool) ($f['enabled'] ?? true),
                ])
                ->filter(fn ($f) => $f['key'] !== '')
                ->values()
                ->all();
        }

        return $out;
    }

    /** Lista de modelos [{model_id,...}] → mapa id => metadatos (formato del catálogo). */
    private function normalizeModels(array $models): array
    {
        $out = [];
        foreach ($models as $m) {
            $id = trim((string) ($m['model_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $cached = $m['cached'] ?? null;
            $out[$id] = [
                'label' => (string) ($m['label'] ?? $id),
                'in' => (float) ($m['in'] ?? 0),
                'out' => (float) ($m['out'] ?? 0),
                'cached' => ($cached === null || $cached === '') ? null : (float) $cached,
                'pdf' => (bool) ($m['pdf'] ?? true),
                'reasoning' => (bool) ($m['reasoning'] ?? false),
                'sort' => (int) ($m['sort'] ?? 0),
            ];
        }

        return $out;
    }
}
