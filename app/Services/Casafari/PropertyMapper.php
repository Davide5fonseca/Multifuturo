<?php

namespace App\Services\Casafari;

use App\Enums\BusinessType;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Throwable;

/**
 * Converte o XML bruto de UM imóvel do feed num array pronto para o model
 * Property. Toda a nomenclatura de nós vem de config('casafari.mapping') —
 * este ficheiro não conhece nomes de elementos, só a forma dos dados.
 *
 * REGRA DE PRIVACIDADE
 * O feed inclui um elemento Owner (proprietário) com nome, email e telefone.
 * Esses dados são do proprietário, não do anúncio: não há base legal para os
 * copiar para o site (RGPD — minimização), e uma fuga expunha pessoas que
 * nunca consentiram aparecer aqui. Por isso o nó é REMOVIDO do DOM antes de
 * qualquer leitura (ver removeIgnoredNodes) e não existe coluna para ele.
 * Do Broker (consultor) guardam-se apenas nome e foto — contactos só com
 * autorização expressa do cliente.
 *
 * Os dados são de uma fonte externa: tudo é tratado como não confiável
 * (tipos forçados, strings aparadas e limitadas, URLs validados).
 */
class PropertyMapper
{
    /**
     * @return array<string, mixed> atributos para Property (sem slug/hash/synced_at/is_active)
     *
     * @throws InvalidArgumentException se o nó não tiver internal_id
     */
    public function map(string $rawXml): array
    {
        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = false;

        // LIBXML_NONET/NOENT: sem rede nem expansão de entidades — o feed é externo.
        if (! @$doc->loadXML($rawXml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('XML de imóvel inválido.');
        }

        $root = $doc->documentElement;

        $this->removeIgnoredNodes($doc, $root);

        $xpath = new DOMXPath($doc);
        $cfg = config('casafari.mapping');

        $data = [];

        foreach ($cfg['fields'] as $column => $path) {
            $data[$column] = $this->value($xpath, $root, $path);
        }

        $data = $this->castColumns($data, $cfg);

        if (blank($data['internal_id'] ?? null)) {
            throw new InvalidArgumentException('Imóvel sem internal_id no feed.');
        }

        $data['translations'] = $this->translations($xpath, $root, $cfg['translations']);
        $data['photos'] = $this->photos($xpath, $root, $cfg['photos']);
        $data['features'] = $this->features($xpath, $root, $cfg['features']);
        $data['broker'] = $this->broker($xpath, $root, $cfg['broker']);

        return $data;
    }

    /**
     * Apaga do DOM os elementos listados em casafari.feed.ignored_nodes (Owner).
     * Fica assim garantido que nenhum caminho de mapeamento, presente ou futuro,
     * consegue ler esses dados por engano.
     */
    private function removeIgnoredNodes(DOMDocument $doc, DOMElement $root): void
    {
        foreach ((array) config('casafari.feed.ignored_nodes', []) as $name) {
            $nodes = iterator_to_array($doc->getElementsByTagName($name));
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    /**
     * Lê um valor pelo caminho simplificado ('A/B', '@attr', 'A/@attr'). Devolve
     * null se não existir ou estiver vazio.
     */
    private function value(DOMXPath $xpath, DOMElement $context, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $expr = $this->toXPath($path);
        $nodes = $xpath->query($expr, $context);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $text = trim((string) $nodes->item(0)?->textContent);

        return $text === '' ? null : $text;
    }

    /** '@attr' → '@attr'; 'A/B' → 'A/B'; 'A/@x' → 'A/@x' — XPath relativo, sem prefixo. */
    private function toXPath(string $path): string
    {
        return ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function castColumns(array $data, array $cfg): array
    {
        $truthy = array_map('strtolower', $cfg['truthy']);
        $bool = fn (?string $v): bool => $v !== null && in_array(strtolower($v), $truthy, true);
        $int = fn (?string $v): ?int => ($v !== null && is_numeric($v)) ? (int) round((float) $v) : null;
        $dec = fn (?string $v): ?float => ($v !== null && is_numeric(str_replace(',', '.', $v))) ? (float) str_replace(',', '.', $v) : null;
        $str = fn (?string $v, int $max): ?string => $v === null ? null : mb_substr($v, 0, $max);
        $url = fn (?string $v): ?string => ($v !== null && filter_var($v, FILTER_VALIDATE_URL) && str_starts_with($v, 'http')) ? mb_substr($v, 0, 2048) : null;

        $businessRaw = strtolower((string) ($data['business_type'] ?? ''));
        $business = $cfg['business_type_map'][$businessRaw] ?? null;

        return [
            'internal_id' => $str($data['internal_id'], 64),
            'reference' => $str($data['reference'], 64),
            'price' => $dec($data['price']),
            'currency' => strtoupper($str($data['currency'], 3) ?? 'EUR'),
            'business_type' => $business ? BusinessType::from($business) : null,
            'property_type' => $str($data['property_type'], 64),
            'property_condition' => $str($data['property_condition'], 64),
            'bedrooms' => $int($data['bedrooms']),
            'bathrooms' => $int($data['bathrooms']),
            'house_area' => $dec($data['house_area']),
            'plot_area' => $dec($data['plot_area']),
            'gross_area' => $dec($data['gross_area']),
            'country' => strtoupper($str($data['country'], 2) ?? 'PT'),
            'district' => $str($data['district'], 96),
            'city' => $str($data['city'], 96),
            'locality' => $str($data['locality'], 96),
            'zone' => $str($data['zone'], 96),
            'zipcode' => $str($data['zipcode'], 16),
            'lat' => $dec($data['lat']),
            'lon' => $dec($data['lon']),
            'gmap_visible' => $bool($data['gmap_visible']),
            'floor_number' => $int($data['floor_number']),
            'build_year' => $int($data['build_year']),
            'energy_rating' => $str($data['energy_rating'], 8),
            'crm_property_url' => $url($data['crm_property_url']),
            'video_url' => $url($data['video_url']),
            'virtual_tour_url' => $url($data['virtual_tour_url']),
            'floorplan_url' => $url($data['floorplan_url']),
            'crm_updated_at' => $this->date($data['crm_updated_at']),
            'is_exclusive' => $bool($data['is_exclusive']),
            'is_featured' => $bool($data['is_featured']),
        ];
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            // Normaliza para o fuso da aplicação: o Eloquent grava sem offset.
            return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Traduções: { "pt": { "title": …, "description": … }, "en": … }.
     * O idioma vem de um atributo (lang="pt") ou de um elemento irmão, conforme
     * casafari.feed.lang_mode. Sem idioma explícito assume default_locale.
     *
     * @param  array<string, string>  $map
     * @return array<string, array<string, ?string>>
     */
    private function translations(DOMXPath $xpath, DOMElement $root, array $map): array
    {
        $mode = config('casafari.feed.lang_mode', 'attribute');
        $langName = config('casafari.feed.lang_name', 'lang');
        $default = config('casafari.feed.default_locale', 'pt');

        $out = [];

        foreach ($map as $key => $path) {
            $nodes = $xpath->query($this->toXPath($path), $root);
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                /** @var DOMElement $node */
                $locale = $default;

                if ($mode === 'attribute' && $node->hasAttribute($langName)) {
                    $locale = $node->getAttribute($langName);
                } elseif ($mode === 'element') {
                    $sibling = $xpath->query($langName, $node->parentNode)?->item(0);
                    if ($sibling) {
                        $locale = trim($sibling->textContent);
                    }
                }

                $locale = strtolower(substr(preg_replace('/[^a-zA-Z_-]/', '', $locale) ?: $default, 0, 5));
                $text = trim($node->textContent);

                // Primeiro valor por idioma ganha; texto vazio não sobrepõe.
                if ($text !== '' && ! isset($out[$locale][$key])) {
                    $out[$locale][$key] = mb_substr($text, 0, $key === 'title' ? 255 : 20000);
                }
            }
        }

        return $out;
    }

    /**
     * Fotos: [ { "url": …, "order": n }, … ] ordenadas. URLs inválidos são descartados.
     *
     * @param  array<string, ?string>  $cfg
     * @return array<int, array{url: string, order: int}>
     */
    private function photos(DOMXPath $xpath, DOMElement $root, array $cfg): array
    {
        $items = $xpath->query($this->toXPath($cfg['container'].'/'.$cfg['item']), $root);
        if ($items === false) {
            return [];
        }

        $photos = [];
        $i = 0;

        foreach ($items as $item) {
            /** @var DOMElement $item */
            $i++;
            $url = $cfg['url'] ? $this->value($xpath, $item, $cfg['url']) : trim($item->textContent);
            $url = trim((string) $url);

            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'http')) {
                continue;
            }

            $order = $cfg['order'] ? $this->value($xpath, $item, $cfg['order']) : null;

            $photos[] = [
                'url' => mb_substr($url, 0, 2048),
                'order' => is_numeric($order) ? (int) $order : $i,
            ];
        }

        usort($photos, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_values($photos);
    }

    /**
     * Características: lista de strings normalizadas (minúsculas, aparadas, sem duplicados).
     *
     * @param  array<string, string>  $cfg
     * @return array<int, string>
     */
    private function features(DOMXPath $xpath, DOMElement $root, array $cfg): array
    {
        $items = $xpath->query($this->toXPath($cfg['container'].'/'.$cfg['item']), $root);
        if ($items === false) {
            return [];
        }

        $features = [];
        foreach ($items as $item) {
            $text = mb_strtolower(trim($item->textContent));
            if ($text !== '') {
                $features[] = mb_substr($text, 0, 96);
            }
        }

        return array_values(array_unique($features));
    }

    /**
     * Consultor: só nome e foto. Não se guardam email nem telefone.
     *
     * @param  array<string, string>  $cfg
     * @return array{name: ?string, photo: ?string}|null
     */
    private function broker(DOMXPath $xpath, DOMElement $root, array $cfg): ?array
    {
        $name = $this->value($xpath, $root, $cfg['name']);
        $photo = $this->value($xpath, $root, $cfg['photo']);

        if ($name === null && $photo === null) {
            return null;
        }

        return [
            'name' => $name !== null ? mb_substr($name, 0, 128) : null,
            'photo' => ($photo !== null && filter_var($photo, FILTER_VALIDATE_URL)) ? mb_substr($photo, 0, 2048) : null,
        ];
    }
}
