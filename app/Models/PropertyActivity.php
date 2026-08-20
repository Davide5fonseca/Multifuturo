<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico automático de um imóvel — o "Actualizações" do CRM: quem mexeu,
 * quando e o quê ("520 001 € → 520 000 €"). Escrito pelo PropertyObserver.
 *
 * @property string $type
 * @property ?string $detail
 */
class PropertyActivity extends Model
{
    protected $guarded = ['id'];

    /** Rótulos dos tipos (badge na dashboard). */
    public const LABELS = [
        'created' => 'Nova',
        'price' => 'Preço',
        'status' => 'Estado',
        'updated' => 'Alterada',
        'deleted' => 'Apagada',
    ];

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
