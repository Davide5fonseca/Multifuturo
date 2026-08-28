<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * Registo de uma escolha no aviso de cookies — a prova do consentimento
 * (RGPD, art. 7.º, n.º 1). Só se escreve; nunca se edita.
 *
 * @property int $version
 * @property array<string, bool> $choices
 * @property string $action accept_all | reject_all | custom
 * @property ?string $locale
 * @property ?string $ip_hash
 * @property ?string $user_agent
 * @property Carbon $created_at
 */
class ConsentLog extends Model
{
    use Prunable;

    /** Quanto tempo se guarda a prova. */
    public const KEEP_MONTHS = 24;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'choices' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths(self::KEEP_MONTHS));
    }
}
