<?php

namespace App\Models;

use App\Enums\EventType;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evento da agenda: telefonema, visita, reunião, tarefa ou lembrete.
 * Pode estar ligado a um cliente e/ou a um imóvel.
 *
 * @property string $title
 * @property EventType $type
 * @property Carbon $starts_at
 * @property bool $is_done
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_done' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Por fazer e de hoje em diante.
     *
     * @param  Builder<Event>  $q
     */
    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('is_done', false)->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at');
    }

    /**
     * Por fazer e já passou da hora.
     *
     * @param  Builder<Event>  $q
     */
    public function scopeOverdue(Builder $q): Builder
    {
        return $q->where('is_done', false)->where('starts_at', '<', now())->orderBy('starts_at');
    }
}
