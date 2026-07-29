<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property int|null $user_id
 * @property string $model
 * @property string $status
 * @property array<string, mixed>|null $result_json
 * @property string|null $raw_output
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $total_tokens
 * @property int $cached_tokens
 * @property int|null $reasoning_tokens
 * @property string|null $cost_usd
 * @property int|null $latency_ms
 * @property string|null $error
 */
class ExtractionRun extends Model
{
    use PrefixedTable;

    protected string $baseTable = 'extraction_runs';

    protected $fillable = [
        'document_id',
        'user_id',
        'model',
        'status',
        'result_json',
        'raw_output',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cached_tokens',
        'reasoning_tokens',
        'cost_usd',
        'latency_ms',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'latency_ms' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
