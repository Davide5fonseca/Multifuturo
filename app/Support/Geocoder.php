<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Coordenadas a partir da morada, pelo Nominatim (OpenStreetMap) — sem chave de
 * API e sem custo.
 *
 * Privacidade: é enviada apenas a morada do imóvel, nunca dados de clientes.
 * O serviço exige um User-Agent que identifique quem chama, e no máximo um
 * pedido por segundo — daí o botão manual, em vez de pesquisar enquanto se
 * escreve.
 */
final class Geocoder
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /**
     * @param  array<string, mixed>  $address
     * @return array{lat: string, lon: string, label: string}|null
     */
    public static function search(array $address): ?array
    {
        $query = self::query($address);

        if ($query === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('agency.name', 'Multifuturo').' backoffice ('.config('app.url').')',
                'Accept-Language' => 'pt-PT',
            ])
                ->timeout(8)
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => mb_strtolower((string) ($address['country'] ?? 'pt')),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Geocodificação falhou', ['erro' => $e->getMessage()]);

            return null;
        }

        $result = $response->successful() ? ($response->json()[0] ?? null) : null;

        if (! is_array($result) || ! isset($result['lat'], $result['lon'])) {
            return null;
        }

        return [
            'lat' => (string) round((float) $result['lat'], 7),
            'lon' => (string) round((float) $result['lon'], 7),
            'label' => (string) ($result['display_name'] ?? $query),
        ];
    }

    /** @param  array<string, mixed>  $address */
    private static function query(array $address): string
    {
        $street = trim(implode(' ', array_filter([
            $address['address'] ?? null,
            $address['street_number'] ?? null,
        ])));

        return implode(', ', array_filter([
            $street !== '' ? $street : null,
            $address['zipcode'] ?? null,
            $address['locality'] ?? null,
            $address['city'] ?? null,
            $address['district'] ?? null,
        ]));
    }
}
