import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Panel de Ajustes de appsur/laravel-facturas-ia (publicable).
 * Enchúfalo en tu panel /ajustes con un enlace/pestaña a la ruta del paquete.
 * Usa componentes base de shadcn (@/components/ui/*) + elementos nativos para portabilidad.
 */

type Field = { key: string; label: string; type: string; enabled: boolean };
type FieldGroups = {
    proveedor: Field[];
    receptor: Field[];
    factura: Field[];
    albaran: Field[];
    item: Field[];
};
type ModelRow = {
    model_id: string;
    label: string;
    in: number;
    out: number;
    cached: number | null;
    pdf: boolean;
    reasoning: boolean;
    sort: number;
};
type Settings = {
    prompt: string;
    defaultModel: string;
    fallbackModel: string | null;
    ownNifs: string[];
    dedupe: boolean;
    fields: FieldGroups;
    cuadreToleranceAbs: number;
    cuadreTolerancePct: number;
    cuadreIvaRates: number[];
    models: ModelRow[];
    openaiKey: string | null;
    openaiAdminKey: string | null;
    openaiProjectId: string | null;
    openaiBaseUrl: string;
};
type Props = { settings: Settings; updateUrl: string };

const GROUPS: { key: keyof FieldGroups; title: string }[] = [
    { key: 'factura', title: 'Factura (cabecera)' },
    { key: 'proveedor', title: 'Proveedor (emisor)' },
    { key: 'receptor', title: 'Receptor (destinatario)' },
    { key: 'albaran', title: 'Albarán' },
    { key: 'item', title: 'Ítem (línea de albarán)' },
];

const TABS = [
    { id: 'ia', label: 'Modelo e IA' },
    { id: 'prompt', label: 'Prompt' },
    { id: 'campos', label: 'Campos' },
    { id: 'modelos', label: 'Modelos y precios' },
    { id: 'otros', label: 'Otros' },
] as const;

export default function FacturasIaSettings({ settings, updateUrl }: Props) {
    const [tab, setTab] = useState<(typeof TABS)[number]['id']>('ia');

    const form = useForm({
        prompt: settings.prompt,
        defaultModel: settings.defaultModel,
        fallbackModel: settings.fallbackModel ?? '',
        dedupe: settings.dedupe,
        fields: settings.fields,
        cuadreToleranceAbs: settings.cuadreToleranceAbs,
        cuadreTolerancePct: settings.cuadreTolerancePct,
        models: settings.models,
        openaiKey: settings.openaiKey ?? '',
        openaiAdminKey: settings.openaiAdminKey ?? '',
        openaiProjectId: settings.openaiProjectId ?? '',
        openaiBaseUrl: settings.openaiBaseUrl,
        ownNifsText: settings.ownNifs.join(', '),
        cuadreIvaRatesText: settings.cuadreIvaRates.join(', '),
    });

    const save = () => {
        form.transform((data) => ({
            ...data,
            ownNifs: data.ownNifsText.split(',').map((s) => s.trim()).filter(Boolean),
            cuadreIvaRates: data.cuadreIvaRatesText.split(',').map((s) => Number(s.trim())).filter((n) => !Number.isNaN(n)),
        }));
        form.put(updateUrl, { preserveScroll: true });
    };

    const setField = (group: keyof FieldGroups, fields: Field[]) => form.setData('fields', { ...form.data.fields, [group]: fields });

    return (
        <>
            <Head title="Ajustes de extracción" />
            <div className="mx-auto w-full max-w-4xl p-4">
                <h1 className="mb-1 text-2xl font-semibold">Ajustes de extracción (IA)</h1>
                <p className="mb-4 text-sm text-muted-foreground">Configura el modelo, el prompt, los campos y las claves. Se guarda en la base de datos.</p>

                {/* Pestañas ligeras */}
                <div className="mb-4 flex flex-wrap gap-1 border-b">
                    {TABS.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => setTab(t.id)}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium ${
                                tab === t.id ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {tab === 'ia' && (
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label>Modelo por defecto</Label>
                                <select
                                    className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                    value={form.data.defaultModel}
                                    onChange={(e) => form.setData('defaultModel', e.target.value)}
                                >
                                    {settings.models.map((m) => (
                                        <option key={m.model_id} value={m.model_id}>
                                            {m.label} ({m.model_id})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Modelo de respaldo (si no cuadra)</Label>
                                <select
                                    className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                    value={form.data.fallbackModel}
                                    onChange={(e) => form.setData('fallbackModel', e.target.value)}
                                >
                                    <option value="">— sin respaldo —</option>
                                    {settings.models.map((m) => (
                                        <option key={m.model_id} value={m.model_id}>
                                            {m.label} ({m.model_id})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="openaiKey">OpenAI API key</Label>
                            <Input id="openaiKey" type="password" value={form.data.openaiKey} onChange={(e) => form.setData('openaiKey', e.target.value)} placeholder="sk-…" />
                            <p className="text-xs text-muted-foreground">Se guarda en la base de datos. Si lo dejas vacío, se usa la de tu .env.</p>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label htmlFor="openaiAdminKey">Admin key (panel de gastos, opcional)</Label>
                                <Input id="openaiAdminKey" type="password" value={form.data.openaiAdminKey} onChange={(e) => form.setData('openaiAdminKey', e.target.value)} placeholder="sk-admin-…" />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="openaiProjectId">Project ID (opcional)</Label>
                                <Input id="openaiProjectId" value={form.data.openaiProjectId} onChange={(e) => form.setData('openaiProjectId', e.target.value)} placeholder="proj_…" />
                            </div>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="openaiBaseUrl">Base URL</Label>
                            <Input id="openaiBaseUrl" value={form.data.openaiBaseUrl} onChange={(e) => form.setData('openaiBaseUrl', e.target.value)} />
                        </div>
                    </div>
                )}

                {tab === 'prompt' && (
                    <div className="grid gap-1">
                        <Label>Prompt de extracción</Label>
                        <textarea
                            className="min-h-96 w-full rounded-md border bg-transparent p-3 text-sm"
                            value={form.data.prompt}
                            onChange={(e) => form.setData('prompt', e.target.value)}
                        />
                    </div>
                )}

                {tab === 'campos' && (
                    <div className="flex flex-col gap-6">
                        {GROUPS.map((g) => (
                            <FieldGroupEditor key={g.key} title={g.title} fields={form.data.fields[g.key] ?? []} onChange={(f) => setField(g.key, f)} />
                        ))}
                    </div>
                )}

                {tab === 'modelos' && <ModelsEditor models={form.data.models} onChange={(m) => form.setData('models', m)} />}

                {tab === 'otros' && (
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-1">
                            <Label htmlFor="ownNifs">Tus NIF (separados por comas) — para recibidas/emitidas</Label>
                            <Input id="ownNifs" value={form.data.ownNifsText} onChange={(e) => form.setData('ownNifsText', e.target.value)} placeholder="B12345678, ES-B99999999" />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.dedupe} onChange={(e) => form.setData('dedupe', e.target.checked)} />
                            Detectar duplicados por hash (no re-extraer un PDF idéntico)
                        </label>
                        <div className="grid gap-2 sm:grid-cols-3">
                            <div className="grid gap-1">
                                <Label>Tolerancia cuadre (€)</Label>
                                <Input type="number" step="0.01" value={form.data.cuadreToleranceAbs} onChange={(e) => form.setData('cuadreToleranceAbs', Number(e.target.value))} />
                            </div>
                            <div className="grid gap-1">
                                <Label>Tolerancia (% del total)</Label>
                                <Input type="number" step="0.001" value={form.data.cuadreTolerancePct} onChange={(e) => form.setData('cuadreTolerancePct', Number(e.target.value))} />
                            </div>
                            <div className="grid gap-1">
                                <Label>Tasas de IVA a probar</Label>
                                <Input value={form.data.cuadreIvaRatesText} onChange={(e) => form.setData('cuadreIvaRatesText', e.target.value)} placeholder="0, 4, 10, 21" />
                            </div>
                        </div>
                    </div>
                )}

                <div className="mt-6 flex justify-end border-t pt-4">
                    <Button onClick={save} disabled={form.processing}>
                        {form.processing ? 'Guardando…' : 'Guardar ajustes'}
                    </Button>
                </div>
            </div>
        </>
    );
}

function FieldGroupEditor({ title, fields, onChange }: { title: string; fields: Field[]; onChange: (fields: Field[]) => void }) {
    const update = (i: number, patch: Partial<Field>) => onChange(fields.map((f, idx) => (idx === i ? { ...f, ...patch } : f)));
    const remove = (i: number) => onChange(fields.filter((_, idx) => idx !== i));
    const add = () => onChange([...fields, { key: '', label: '', type: 'string', enabled: true }]);

    return (
        <div className="rounded-lg border p-4">
            <p className="mb-3 text-sm font-semibold">{title}</p>
            <div className="flex flex-col gap-2">
                {fields.map((f, i) => (
                    <div key={i} className="flex flex-wrap items-center gap-2">
                        <input type="checkbox" checked={f.enabled} onChange={(e) => update(i, { enabled: e.target.checked })} />
                        <Input className="w-40" placeholder="Etiqueta" value={f.label} onChange={(e) => update(i, { label: e.target.value })} />
                        <Input className="w-40 font-mono text-xs" placeholder="clave_json" value={f.key} onChange={(e) => update(i, { key: e.target.value })} />
                        <select className="h-9 rounded-md border bg-transparent px-2 text-sm" value={f.type} onChange={(e) => update(i, { type: e.target.value })}>
                            <option value="string">texto</option>
                            <option value="number">número</option>
                            <option value="date">fecha</option>
                        </select>
                        <Button size="sm" variant="ghost" onClick={() => remove(i)}>
                            Quitar
                        </Button>
                    </div>
                ))}
            </div>
            <Button variant="outline" size="sm" className="mt-3" onClick={add}>
                Añadir campo
            </Button>
        </div>
    );
}

function ModelsEditor({ models, onChange }: { models: ModelRow[]; onChange: (m: ModelRow[]) => void }) {
    const update = (i: number, patch: Partial<ModelRow>) => onChange(models.map((m, idx) => (idx === i ? { ...m, ...patch } : m)));
    const remove = (i: number) => onChange(models.filter((_, idx) => idx !== i));
    const add = () => onChange([...models, { model_id: '', label: '', in: 0, out: 0, cached: null, pdf: true, reasoning: false, sort: (models.at(-1)?.sort ?? 0) + 5 }]);

    return (
        <div className="overflow-x-auto">
            <p className="mb-3 text-sm text-muted-foreground">Modelos disponibles y su precio (USD por 1M de tokens). Se usan para elegir modelo y calcular el gasto.</p>
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2 pr-2 font-medium">model_id</th>
                        <th className="py-2 pr-2 font-medium">Etiqueta</th>
                        <th className="py-2 pr-2 font-medium">In</th>
                        <th className="py-2 pr-2 font-medium">Out</th>
                        <th className="py-2 pr-2 font-medium">Cached</th>
                        <th className="py-2 pr-2 font-medium">PDF</th>
                        <th className="py-2 pr-2 font-medium">Razona</th>
                        <th className="py-2 pr-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    {models.map((m, i) => (
                        <tr key={i} className="border-b last:border-0">
                            <td className="py-1 pr-2"><Input className="w-32 font-mono text-xs" value={m.model_id} onChange={(e) => update(i, { model_id: e.target.value })} /></td>
                            <td className="py-1 pr-2"><Input className="w-32" value={m.label} onChange={(e) => update(i, { label: e.target.value })} /></td>
                            <td className="py-1 pr-2"><Input className="w-20" type="number" step="0.01" value={m.in} onChange={(e) => update(i, { in: Number(e.target.value) })} /></td>
                            <td className="py-1 pr-2"><Input className="w-20" type="number" step="0.01" value={m.out} onChange={(e) => update(i, { out: Number(e.target.value) })} /></td>
                            <td className="py-1 pr-2"><Input className="w-20" type="number" step="0.001" value={m.cached ?? ''} onChange={(e) => update(i, { cached: e.target.value === '' ? null : Number(e.target.value) })} /></td>
                            <td className="py-1 pr-2 text-center"><input type="checkbox" checked={m.pdf} onChange={(e) => update(i, { pdf: e.target.checked })} /></td>
                            <td className="py-1 pr-2 text-center"><input type="checkbox" checked={m.reasoning} onChange={(e) => update(i, { reasoning: e.target.checked })} /></td>
                            <td className="py-1 pr-2"><Button size="sm" variant="ghost" onClick={() => remove(i)}>Quitar</Button></td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <Button variant="outline" size="sm" className="mt-3" onClick={add}>
                Añadir modelo
            </Button>
        </div>
    );
}
