<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Código da verificação em duas etapas (por email). Uso único, validade
 * curta; guarda-se só o hash.
 *
 * @property int $user_id
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property ?Carbon $used_at
 * @property Carbon $created_at
 */
class MfaCode extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** Códigos ainda utilizáveis: por usar e dentro da validade. @param Builder<self> $query */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
