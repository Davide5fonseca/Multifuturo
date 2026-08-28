<?php

namespace App\Observers;

use App\Models\Property;
use App\Models\PropertyActivity;
use App\Support\Format;
use Illuminate\Support\Facades\Auth;

/**
 * Escreve o histórico de alterações dos imóveis (o "Actualizações" do CRM):
 * criação, mudanças de preço, mudanças de estado (publicado, vendido, fora de
 * mercado) e edições genéricas — sempre com o utilizador que as fez.
 */
class PropertyObserver
{
    /** Estados seguidos: campo => [rótulo quando falso, rótulo quando verdadeiro]. */
    private const STATUS_FIELDS = [
        'is_active' => ['Retirada do site', 'Publicada'],
        'is_sold' => ['Já não está vendida', 'Vendida'],
        'off_market' => ['Voltou ao mercado', 'Fora de mercado'],
    ];

    public function created(Property $property): void
    {
        $this->log($property, 'created');
    }

    public function updated(Property $property): void
    {
        if ($property->wasChanged('price')) {
            $this->log($property, 'price', sprintf(
                '%s → %s',
                Format::price($property->getOriginal('price'), $property->currency),
                Format::price($property->price, $property->currency),
            ));
        }

        // "Actual" (estado interno) — vive no jsonb, por isso compara-se à mão.
        $statusBefore = self::statusOf($property->getOriginal('admin'));
        $statusAfter = self::statusOf($property->admin);

        if ($statusBefore !== $statusAfter) {
            $this->log($property, 'status', $statusAfter);
        }

        foreach (self::STATUS_FIELDS as $field => [$off, $on]) {
            if ($property->wasChanged($field)) {
                $this->log($property, 'status', $property->{$field} ? $on : $off);
            }
        }

        // Edição sem nada disto (texto, fotografias, características…).
        if ($statusBefore === $statusAfter && ! $property->wasChanged(['price', 'is_active', 'is_sold', 'off_market'])) {
            $this->log($property, 'updated');
        }
    }

    /**
     * Primeira vez publicável → published_at. É esta data que os alertas de
     * imóveis usam para saber o que é novo; nunca mais se altera (uma edição,
     * uma retirada e volta, não fazem do imóvel uma novidade).
     */
    public function saved(Property $property): void
    {
        if ($property->published_at === null && $property->isPublishable()) {
            $property->forceFill(['published_at' => now()])->saveQuietly();
        }
    }

    /**
     * Ida para a reciclagem. O imóvel continua na base de dados, por isso o
     * registo pode apontar-lhe — e a ficha pode ser reposta a partir daqui.
     */
    public function deleted(Property $property): void
    {
        // Apagar de vez dispara este evento e o forceDeleted: aqui só interessa
        // a ida para a reciclagem, senão escrevia-se um registo a apontar para
        // uma linha que já não existe.
        if ($property->isForceDeleting()) {
            return;
        }

        $this->log($property, 'deleted', 'Movido para a reciclagem');
    }

    public function restored(Property $property): void
    {
        $this->log($property, 'status', 'Reposto da reciclagem');
    }

    /**
     * Apagado de vez. A chave estrangeira é em cascata: a esta altura o
     * histórico do imóvel já foi com ele, e a linha não lhe pode apontar. A
     * referência fica no detalhe, para ficar registado quem o apagou.
     */
    public function forceDeleted(Property $property): void
    {
        PropertyActivity::query()->create([
            'property_id' => null,
            'user_id' => Auth::id(),
            'type' => 'deleted',
            'detail' => trim('Apagado definitivamente: '.$property->reference.' — '.($property->title ?? ''), ' —'),
        ]);
    }

    /** @param  array<string, mixed>|string|null  $admin */
    private static function statusOf(array|string|null $admin): string
    {
        if (is_string($admin)) {
            $admin = json_decode($admin, true) ?: [];
        }

        return (string) (data_get($admin, 'status') ?: Property::STATUS_ACTIVE);
    }

    private function log(Property $property, string $type, ?string $detail = null): void
    {
        PropertyActivity::query()->create([
            'property_id' => $property->getKey(),
            'user_id' => Auth::id(),
            'type' => $type,
            'detail' => $detail,
        ]);
    }
}
