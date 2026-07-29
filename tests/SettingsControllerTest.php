<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Settings\FacturasIaSettings;
use Inertia\Testing\AssertableInertia;

class SettingsControllerTest extends TestCase
{
    public function test_edit_renders_inertia_settings_page(): void
    {
        $this->get(route('facturas-ia.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('facturas-ia/settings', false)
                ->has('settings.prompt')
                ->has('settings.models')
                ->where('settings.defaultModel', 'gpt-5.4-mini'));
    }

    public function test_update_persists_settings_to_db(): void
    {
        $payload = [
            'prompt' => 'Nuevo prompt',
            'defaultModel' => 'gpt-4.1',
            'fallbackModel' => null,
            'ownNifs' => ['ES-B1'],
            'dedupe' => false,
            'fields' => [
                'proveedor' => [['key' => 'nif', 'label' => 'NIF', 'type' => 'string', 'enabled' => true]],
                'receptor' => [],
                'factura' => [],
                'albaran' => [],
                'item' => [['key' => 'concepto', 'label' => 'Concepto', 'type' => 'string', 'enabled' => true]],
            ],
            'cuadreToleranceAbs' => 1.0,
            'cuadreTolerancePct' => 0.02,
            'cuadreIvaRates' => [0, 21],
            'models' => [
                ['model_id' => 'gpt-4.1', 'label' => 'GPT-4.1', 'in' => 2, 'out' => 8, 'cached' => 0.5, 'pdf' => true, 'reasoning' => false, 'sort' => 1],
            ],
            'openaiKey' => 'sk-nuevo',
            'openaiAdminKey' => null,
            'openaiProjectId' => null,
            'openaiBaseUrl' => 'https://api.openai.com/v1',
        ];

        $this->put(route('facturas-ia.settings.update'), $payload)->assertRedirect();

        $s = app(FacturasIaSettings::class);
        $this->assertSame('Nuevo prompt', $s->prompt);
        $this->assertSame('gpt-4.1', $s->defaultModel);
        $this->assertNull($s->fallbackModel);
        $this->assertFalse($s->dedupe);
        $this->assertSame(['ES-B1'], $s->ownNifs);
        $this->assertSame('sk-nuevo', $s->openaiKey);
        $this->assertArrayHasKey('gpt-4.1', $s->models);
    }
}
