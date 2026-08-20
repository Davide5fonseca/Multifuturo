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

        foreach (self::STATUS_FIELDS as $field => [$off, $on]) {
            if ($property->wasChanged($field)) {
                $this->log($property, 'status', $property->{$field} ? $on : $off);
            }
        }

        // Edição sem nada disto (texto, fotografias, características…).
        if (! $property->wasChanged(['price', 'is_active', 'is_sold', 'off_market'])) {
            $this->log($property, 'updated');
        }
    }

    public function deleted(Property $property): void
    {
        $this->log($property, 'deleted');
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
