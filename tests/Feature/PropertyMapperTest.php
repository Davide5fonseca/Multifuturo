<?php

/*
 * Fase 7 — PropertyMapper isolado: normalização, tolerância a dados maus e
 * segurança do XML (fonte externa, não confiável).
 */

use App\Enums\BusinessType;
use App\Services\Casafari\PropertyMapper;

function mapXml(string $inner): array
{
    return (new PropertyMapper)->map("<Property>{$inner}</Property>");
}

it('mapeia decimais com vírgula, booleanos variados e datas com fuso', function () {
    $d = mapXml('<ID>1</ID><BusinessType>rent</BusinessType><Price>1.250,50</Price><HouseArea>85,5</HouseArea>
        <Location><GmapVisible>Sim</GmapVisible></Location><Exclusive>YES</Exclusive><Featured>nope</Featured>
        <UpdatedAt>2026-01-15T10:00:00+00:00</UpdatedAt>');

    // "1.250,50" não é numérico em PHP → null (não inventamos 1250.50); "85,5" → 85.5
    expect($d['price'])->toBeNull()
        ->and($d['house_area'])->toBe(85.5)
        ->and($d['business_type'])->toBe(BusinessType::Rent)
        ->and($d['gmap_visible'])->toBeTrue()
        ->and($d['is_exclusive'])->toBeTrue()
        ->and($d['is_featured'])->toBeFalse()
        ->and($d['crm_updated_at']->timezone->getName())->toBe(config('app.timezone'))
        ->and($d['crm_updated_at']->utc()->format('H:i'))->toBe('10:00');
});

it('rejeita URLs que não sejam http(s) e datas inválidas', function () {
    $d = mapXml('<ID>2</ID><BusinessType>sale</BusinessType><Url>javascript:alert(1)</Url><VideoUrl>ftp://x/y</VideoUrl>
        <VirtualTourUrl>https://ok.example.test/t</VirtualTourUrl><UpdatedAt>não é data</UpdatedAt>
        <Photos><Photo>data:image/png;base64,AAAA</Photo><Photo>https://cdn.example.test/1.jpg</Photo></Photos>');

    expect($d['crm_property_url'])->toBeNull()
        ->and($d['video_url'])->toBeNull()
        ->and($d['virtual_tour_url'])->toBe('https://ok.example.test/t')
        ->and($d['crm_updated_at'])->toBeNull()
        ->and($d['photos'])->toHaveCount(1)
        ->and($d['photos'][0]['url'])->toBe('https://cdn.example.test/1.jpg');
});

it('finalidade desconhecida é rejeitada (o sync regista erro em vez de adivinhar)', function () {
    expect(fn () => mapXml('<ID>3</ID><BusinessType>troca</BusinessType>'))
        ->toThrow(InvalidArgumentException::class, 'finalidade');
});

it('lança exceção sem internal_id e com XML inválido', function () {
    expect(fn () => mapXml('<Reference>X</Reference>'))->toThrow(InvalidArgumentException::class, 'internal_id');
    expect(fn () => (new PropertyMapper)->map('<Property><ID>1</ID>'))->toThrow(InvalidArgumentException::class);
});

it('suporta o idioma como elemento irmão quando configurado', function () {
    config(['casafari.feed.lang_mode' => 'element', 'casafari.feed.lang_name' => 'Language',
        'casafari.mapping.translations' => ['title' => 'Texts/Text/Title', 'description' => 'Texts/Text/Description']]);

    $d = mapXml('<ID>4</ID><BusinessType>sale</BusinessType>
        <Texts>
            <Text><Language>pt</Language><Title>Casa</Title><Description>Bonita</Description></Text>
            <Text><Language>en</Language><Title>House</Title></Text>
        </Texts>');

    expect($d['translations'])->toBe(['pt' => ['title' => 'Casa', 'description' => 'Bonita'], 'en' => ['title' => 'House']]);
});

it('lê o URL da foto num sub-elemento quando configurado', function () {
    config(['casafari.mapping.photos' => ['container' => 'Images', 'item' => 'Image', 'url' => 'Url', 'order' => 'Position']]);

    $d = mapXml('<ID>5</ID><BusinessType>sale</BusinessType><Images>
        <Image><Url>https://cdn.example.test/b.jpg</Url><Position>2</Position></Image>
        <Image><Url>https://cdn.example.test/a.jpg</Url><Position>1</Position></Image>
    </Images>');

    expect(array_column($d['photos'], 'url'))->toBe(['https://cdn.example.test/a.jpg', 'https://cdn.example.test/b.jpg']);
});

it('remove o Owner mesmo que um caminho de mapeamento aponte para dentro dele', function () {
    // Se alguém, por engano, mapear um campo para Owner/Email, o nó já não existe.
    config(['casafari.mapping.fields.reference' => 'Owner/Email']);

    $d = mapXml('<ID>6</ID><BusinessType>sale</BusinessType><Owner><Email>x@example.test</Email></Owner>');

    expect($d['reference'])->toBeNull();
});

it('não resolve entidades externas (XXE) e trata DOCTYPE sem rebentar', function () {
    $xml = '<?xml version="1.0"?><!DOCTYPE p [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><Property><ID>7</ID><BusinessType>sale</BusinessType><Title>&xxe;</Title></Property>';

    try {
        $d = (new PropertyMapper)->map($xml);
        // Se mapear, o título nunca pode conter conteúdo do ficheiro do sistema.
        expect($d['translations']['pt']['title'] ?? '')->not->toContain('root:');
    } catch (InvalidArgumentException) {
        expect(true)->toBeTrue(); // rejeitar também é aceitável
    }
});

it('limita o tamanho de strings vindas do feed', function () {
    $long = str_repeat('a', 500);
    $d = mapXml("<ID>{$long}</ID><BusinessType>sale</BusinessType><Reference>{$long}</Reference><EnergyRating>{$long}</EnergyRating>");

    expect(strlen($d['internal_id']))->toBe(64)
        ->and(strlen($d['reference']))->toBe(64)
        ->and(strlen($d['energy_rating']))->toBe(8);
});
