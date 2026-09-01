<?php

/*
 * Separador Descrições: os textos do anúncio como no CRM (Texto principal,
 * Website (HTML), Brochura, Email / Lead), guardados em translations.{idioma}
 * e usados pelo site — título, descrição, meta description, keywords e o
 * texto HTML da ficha.
 */

use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Models\Property;
use App\Models\User;
use App\Support\Html;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('o Texto principal é gravado em translations e volta ao formulário', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-TXT-1',
            'business_type' => 'sale',
            'property_type' => 'Apartamento',
            'city' => 'Lisboa',
            'energy_rating' => 'B',
            'translations.pt.title' => 'Apartamento T2 com vista de rio',
            'translations.pt.keywords' => ['apartamento lisboa', 'vista rio'],
            'translations.pt.seo_description' => 'T2 renovado junto ao rio, em Lisboa.',
            'translations.pt.short_description' => 'Renovado, luminoso, a dois passos do rio.',
            'translations.pt.description' => "Sala ampla.\n\nCozinha equipada.",
            'translations.pt.brochure_title' => 'Brochura T2 rio',
            'translations.pt.email_subject' => 'O T2 que pediu',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Property::where('reference', 'MF-TXT-1')->firstOrFail();

    expect($p->title)->toBe('Apartamento T2 com vista de rio')
        ->and($p->keywords())->toBe(['apartamento lisboa', 'vista rio'])
        ->and($p->seo_description)->toBe('T2 renovado junto ao rio, em Lisboa.')
        ->and($p->short_description)->toBe('Renovado, luminoso, a dois passos do rio.')
        ->and($p->description)->toBe("Sala ampla.\n\nCozinha equipada.")
        ->and($p->translations['pt']['brochure_title'])->toBe('Brochura T2 rio')
        ->and($p->translations['pt']['email_subject'])->toBe('O T2 que pediu')
        // O inglês ficou em branco: não sobra um bloco de nulls no JSON.
        ->and($p->translations)->not->toHaveKey('en', json_encode($p->translations));

    Livewire::test(EditProperty::class, ['record' => $p->slug])
        ->assertFormSet([
            'translations.pt.title' => 'Apartamento T2 com vista de rio',
            'translations.pt.short_description' => 'Renovado, luminoso, a dois passos do rio.',
        ]);
});

it('o título tem no máximo 60 caracteres e a descrição curta 300', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'reference' => 'MF-TXT-2',
            'business_type' => 'sale',
            'property_type' => 'Moradia',
            'city' => 'Sintra',
            'energy_rating' => 'C',
            'translations.pt.title' => str_repeat('a', 61),
            'translations.pt.short_description' => str_repeat('b', 301),
        ])
        ->call('create')
        ->assertHasFormErrors(['translations.pt.title', 'translations.pt.short_description']);
});

it('o site usa a descrição SEO, as palavras-chave e a descrição curta', function () {
    $p = Property::factory()->create([
        'translations' => ['pt' => [
            'title' => 'Moradia V4 em Cascais',
            'keywords' => ['moradia cascais', 'piscina'],
            'seo_description' => 'Moradia V4 com piscina e jardim, a cinco minutos da praia.',
            'short_description' => 'Piscina, jardim e praia ao pé.',
            'description' => 'Texto longo da ficha.',
        ]],
    ]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->toContain('<meta name="description" content="Moradia V4 com piscina e jardim, a cinco minutos da praia.">')
        ->toContain('<meta name="keywords" content="moradia cascais, piscina">')
        ->toContain('Texto longo da ficha.')
        // A ficha tem a etiqueta da classe energética e o cartão de dados.
        ->toContain('Classe energética')
        ->toContain('data-testid="ficha"');

    // Sem descrição SEO, a curta serve de meta description; sem nenhuma, o início da descrição.
    $p->update(['translations' => ['pt' => ['title' => 'Moradia V4 em Cascais', 'short_description' => 'Piscina, jardim e praia ao pé.', 'description' => 'Texto longo da ficha.']]]);
    expect($this->get(route('property.show', $p))->getContent())->toContain('<meta name="description" content="Piscina, jardim e praia ao pé.">');

    $p->update(['translations' => ['pt' => ['title' => 'Moradia V4 em Cascais', 'description' => 'Texto longo da ficha.']]]);
    $semMeta = $this->get(route('property.show', $p))->getContent();
    expect($semMeta)->toContain('<meta name="description" content="Texto longo da ficha.">')
        ->not->toContain('name="keywords"');
});

it('o texto Website (HTML) substitui a descrição na ficha e sai limpo', function () {
    $p = Property::factory()->create([
        'translations' => ['pt' => [
            'title' => 'Loft em Aveiro',
            'description' => 'Descrição simples.',
            'website_html' => '<h2 style="color:red" onclick="x()">Um <strong>loft</strong> único</h2>'
                .'<p>Com <a href="https://exemplo.pt/x" target="_blank">ligação</a> e <a href="javascript:alert(1)">outra</a>.</p>'
                .'<script>alert(1)</script><img src="x" onerror="alert(2)">',
        ]],
    ]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    expect($html)->toContain('<h2>Um <strong>loft</strong> único</h2>')
        ->toContain('<a href="https://exemplo.pt/x" rel="noopener">ligação</a>')
        ->toContain('<a>outra</a>')
        ->toContain('<meta name="description" content="Descrição simples.">')
        // A página tem scripts e imagens legítimos; o que não pode sobreviver é o que veio do texto.
        ->not->toContain('alert(')
        ->not->toContain('onclick="x()"')
        ->not->toContain('onerror="alert(2)"')
        ->not->toContain('src="x"')
        ->not->toContain('style="color:red"');

    // A meta description e o JSON-LD aproveitam o HTML quando não há descrição simples.
    $p->update(['translations' => ['pt' => ['title' => 'Loft em Aveiro', 'website_html' => '<p>Só <em>HTML</em> aqui.</p>']]]);
    $so = $this->get(route('property.show', $p))->getContent();
    expect($so)->toContain('<meta name="description" content="Só HTML aqui.">')
        ->toContain('"description":"Só HTML aqui."');
});

it('em inglês, um texto vazio cai para o português', function () {
    $p = Property::factory()->create([
        'translations' => [
            'pt' => ['title' => 'Casa em Braga', 'short_description' => 'Curta PT', 'keywords' => ['braga']],
            'en' => ['title' => 'House in Braga'],
        ],
    ]);

    expect($p->translation('title', 'en'))->toBe('House in Braga')
        ->and($p->translation('short_description', 'en'))->toBe('Curta PT')
        ->and($p->keywords('en'))->toBe(['braga']);
});

it('Html::clean deixa só formatação de texto', function () {
    expect(Html::clean(null))->toBe('')
        ->and(Html::clean('<p class="x">Olá <b>mundo</b><br/></p>'))->toBe('<p>Olá <b>mundo</b><br></p>')
        ->and(Html::clean('<a href="mailto:geral@exemplo.pt">mail</a>'))->toBe('<a href="mailto:geral@exemplo.pt" rel="noopener">mail</a>')
        ->and(Html::clean('<a href="/pt/contactos">interno</a>'))->toBe('<a href="/pt/contactos" rel="noopener">interno</a>')
        ->and(Html::clean('<style>p{}</style><div><span>texto</span></div>'))->toBe('texto');
});
