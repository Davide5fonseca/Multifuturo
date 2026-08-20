<?php

namespace Database\Seeders;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Models\Zone;
use App\Support\PropertyCache;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Conteúdo de DEMONSTRAÇÃO — apenas para desenvolvimento/apresentação.
 *
 * Cria imóveis fictícios (internal_id com prefixo DEMO-) para se ver o site
 * "cheio" antes de haver dados reais. Sem fotografias: usa o placeholder da
 * marca. Reexecutar substitui os imóveis demo existentes e não toca em mais nada.
 *
 * NUNCA corre em produção. Remover tudo com:
 *   Property::where('internal_id', 'like', 'DEMO-%')->delete()
 * ou apagá-los no backoffice.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoContentSeeder não corre em produção.');
        }

        Property::query()->where('internal_id', 'like', 'DEMO-%')->delete();

        foreach ($this->properties() as $i => $data) {
            Property::query()->create([
                'internal_id' => 'DEMO-'.($i + 1),
                'reference' => $data['reference'],
                'price' => $data['price'],
                'currency' => 'EUR',
                'business_type' => $data['business'],
                'property_type' => $data['type'],
                'property_condition' => $data['condition'] ?? 'Usado',
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'] ?? max(1, (int) floor(($data['bedrooms'] ?? 1) / 2) + 1),
                'house_area' => $data['area'],
                'plot_area' => $data['plot'] ?? null,
                'gross_area' => $data['area'] !== null ? round($data['area'] * 1.15) : null,
                'country' => 'PT',
                'district' => $data['district'],
                'city' => $data['city'],
                'locality' => $data['locality'],
                'zipcode' => $data['zip'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lon' => $data['lon'] ?? null,
                'gmap_visible' => $data['gmap'] ?? true,
                'floor_number' => $data['floor'] ?? null,
                'build_year' => $data['year'] ?? null,
                'energy_rating' => $data['ce'],
                'translations' => ['pt' => ['title' => $data['title'], 'description' => $data['description']]],
                'photos' => [],  // sem fotografias: o site mostra o placeholder da marca
                'features' => $data['features'],
                'broker' => ['name' => $data['broker'], 'photo' => null],
                'slug' => Property::generateSlug($data['type'], $data['city'], $data['reference'], 'DEMO-'.($i + 1)),
                'payload_hash' => hash('sha256', 'demo-'.$i),
                'crm_updated_at' => now()->subDays($data['days_ago']),
                'is_active' => true,
                'is_exclusive' => $data['exclusive'] ?? false,
                'is_featured' => $data['featured'] ?? false,
                'synced_at' => now(),
            ]);
        }

        // Editorial de exemplo para duas zonas (mostra o formato das páginas de zona).
        Zone::query()->updateOrCreate(
            ['city_slug' => 'cascais', 'locality_slug' => null],
            [
                'title' => 'Viver em Cascais',
                'meta_description' => 'Apartamentos e moradias em Cascais — do centro histórico ao Estoril, com acompanhamento local da Multifuturo.',
                'intro' => 'Entre a serra de Sintra e a linha do Estoril, Cascais junta praia, marina e um centro histórico que se percorre a pé — um dos concelhos mais procurados da Grande Lisboa.',
                'body' => "Do apartamento junto à estação à moradia com jardim na zona da Quinta da Marinha, o mercado de Cascais é dos mais dinâmicos do país, com procura nacional e internacional durante todo o ano.\n\nA Multifuturo acompanha o concelho freguesia a freguesia: sabemos o que se vendeu, por quanto, e o que os compradores procuram em cada zona. É esse conhecimento que pomos ao serviço de quem vende e de quem compra.",
                'is_published' => true,
            ]
        );

        Zone::query()->updateOrCreate(
            ['city_slug' => 'lisboa', 'locality_slug' => null],
            [
                'title' => 'Comprar casa em Lisboa',
                'meta_description' => 'Imóveis em Lisboa — Estrela, Campo de Ourique, Alvalade e mais, com a carteira atualizada da Multifuturo.',
                'intro' => 'Lisboa é um mosaico de bairros com personalidades muito diferentes — e preços, tipologias e ritmos de mercado a condizer.',
                'body' => 'Da Estrela a Alvalade, ajudamos a escolher o bairro certo para cada fase da vida: escolas, transportes, comércio de rua e o equilíbrio entre tranquilidade e proximidade ao centro.',
                'is_published' => true,
            ]
        );

        PropertyCache::flush();

        $this->command?->info('Conteúdo demo criado: '.Property::where('internal_id', 'like', 'DEMO-%')->count().' imóveis + 2 zonas editoriais.');
    }

    /** @return array<int, array<string, mixed>> */
    private function properties(): array
    {
        return [
            [
                'reference' => 'MF-2041', 'title' => 'Moradia contemporânea com piscina e jardim',
                'description' => "Moradia de arquitetura contemporânea num lote de 620 m², com áreas amplas viradas ao jardim e piscina orientada a sul.\n\nPiso térreo com sala de 55 m² em open space, cozinha totalmente equipada e suite de hóspedes. No piso superior, três suites com varanda e roupeiros. Garagem para duas viaturas, painéis solares e aspiração central.\n\nA cinco minutos das praias e dos colégios internacionais.",
                'business' => BusinessType::Sale, 'type' => 'Moradia', 'condition' => 'Como novo',
                'bedrooms' => 4, 'bathrooms' => 5, 'area' => 310, 'plot' => 620, 'year' => 2019, 'ce' => 'A',
                'district' => 'Lisboa', 'city' => 'Cascais', 'locality' => 'Cascais e Estoril', 'zip' => '2750-319',
                'lat' => 38.7057, 'lon' => -9.4249, 'price' => 1250000,
                'features' => ['piscina', 'jardim', 'garagem', 'painéis solares', 'ar condicionado', 'alarme'],
                'broker' => 'Marta Fonseca', 'days_ago' => 2, 'featured' => true, 'exclusive' => true,
            ],
            [
                'reference' => 'MF-2038', 'title' => 'Apartamento T3 com terraço e vista de mar',
                'description' => "Apartamento de 142 m² num edifício de 2019 com três frentes e terraço de 28 m² orientado a sul, com vista desafogada até ao mar.\n\nSala com 40 m², cozinha equipada com ilha, suite principal com closet e dois quartos com roupeiros. Dois lugares de garagem e arrecadação.\n\nZona tranquila a dez minutos a pé do centro e da estação.",
                'business' => BusinessType::Sale, 'type' => 'Apartamento', 'condition' => 'Como novo',
                'bedrooms' => 3, 'bathrooms' => 2, 'area' => 142, 'year' => 2019, 'ce' => 'B', 'floor' => 3,
                'district' => 'Lisboa', 'city' => 'Cascais', 'locality' => 'Monte Estoril', 'zip' => '2765-278',
                'lat' => 38.7076, 'lon' => -9.4014, 'price' => 785000,
                'features' => ['terraço', 'garagem', 'elevador', 'arrecadação', 'vista de mar'],
                'broker' => 'Marta Fonseca', 'days_ago' => 5, 'featured' => true,
            ],
            [
                'reference' => 'MF-2035', 'title' => 'T2 renovado em Campo de Ourique',
                'description' => "Apartamento totalmente renovado num prédio pombalino reabilitado, a dois passos do mercado de Campo de Ourique.\n\nRenovação de 2023 com isolamento acústico, caixilharia com vidro duplo, ar condicionado e cozinha equipada. Pé-direito alto e muita luz natural.\n\nUma localização de bairro, com tudo à porta.",
                'business' => BusinessType::Sale, 'type' => 'Apartamento', 'condition' => 'Renovado',
                'bedrooms' => 2, 'bathrooms' => 1, 'area' => 84, 'year' => 1940, 'ce' => 'C', 'floor' => 2,
                'district' => 'Lisboa', 'city' => 'Lisboa', 'locality' => 'Campo de Ourique', 'zip' => '1350-115',
                'lat' => 38.7169, 'lon' => -9.1665, 'price' => 465000,
                'features' => ['ar condicionado', 'cozinha equipada', 'vidros duplos'],
                'broker' => 'Ricardo Almeida', 'days_ago' => 9, 'featured' => true,
            ],
            [
                'reference' => 'MF-2029', 'title' => 'Moradia V3 com relvado em Oeiras',
                'description' => "Moradia térrea com 200 m² de relvado, totalmente murada, numa rua sossegada de Oeiras.\n\nSala com lareira, cozinha renovada, três quartos (um em suite) e escritório. Anexo com lavandaria e zona de churrasco.\n\nA dez minutos da A5 e das praias de Santo Amaro e Carcavelos.",
                'business' => BusinessType::Sale, 'type' => 'Moradia',
                'bedrooms' => 3, 'bathrooms' => 3, 'area' => 165, 'plot' => 420, 'year' => 1988, 'ce' => 'D',
                'district' => 'Lisboa', 'city' => 'Oeiras', 'locality' => 'Oeiras e São Julião da Barra', 'zip' => '2780-271',
                'lat' => 38.6979, 'lon' => -9.3113, 'price' => 690000,
                'features' => ['jardim', 'lareira', 'churrasqueira', 'garagem'],
                'broker' => 'Ricardo Almeida', 'days_ago' => 14,
            ],
            [
                'reference' => 'MF-2044', 'title' => 'T1 mobilado junto à estação — arrendamento',
                'description' => "Apartamento mobilado e equipado, pronto a habitar, a três minutos a pé da estação de comboios.\n\nSala com varanda, cozinha em open space com máquinas, quarto com roupeiro. Condomínio com elevador.\n\nContrato mínimo de 12 meses; 2 rendas de caução.",
                'business' => BusinessType::Rent, 'type' => 'Apartamento',
                'bedrooms' => 1, 'bathrooms' => 1, 'area' => 58, 'year' => 2005, 'ce' => 'C', 'floor' => 4,
                'district' => 'Lisboa', 'city' => 'Oeiras', 'locality' => 'Paço de Arcos', 'zip' => '2770-059',
                'lat' => 38.6912, 'lon' => -9.2916, 'price' => 1250,
                'features' => ['mobilado', 'elevador', 'varanda'],
                'broker' => 'Sofia Ramos', 'days_ago' => 3, 'featured' => true,
            ],
            [
                'reference' => 'MF-2042', 'title' => 'T2 com terraço na Estrela — arrendamento',
                'description' => "Segundo andar de um prédio reabilitado, com terraço privativo de 15 m² e vista sobre os telhados da Estrela.\n\nDois quartos com roupeiro, sala com cozinha aberta e casa de banho com janela. Sem mobília; eletrodomésticos incluídos.\n\nDisponível a partir do próximo mês.",
                'business' => BusinessType::Rent, 'type' => 'Apartamento', 'condition' => 'Renovado',
                'bedrooms' => 2, 'bathrooms' => 1, 'area' => 96, 'year' => 1936, 'ce' => 'C', 'floor' => 2,
                'district' => 'Lisboa', 'city' => 'Lisboa', 'locality' => 'Estrela', 'zip' => '1200-664',
                'lat' => 38.7150, 'lon' => -9.1607, 'price' => 1850,
                'features' => ['terraço', 'cozinha equipada', 'vidros duplos'],
                'broker' => 'Sofia Ramos', 'days_ago' => 6,
            ],
            [
                'reference' => 'MF-2018', 'title' => 'Quinta com moradia e terreno plano em Sintra',
                'description' => "Propriedade com 2 400 m² de terreno plano, moradia principal V4 e anexo com potencial para turismo rural.\n\nPoço, pomar e zona de horta. A moradia precisa de obras de atualização — excelente oportunidade para criar uma casa de família à medida.\n\nLocalização discreta; morada exata fornecida mediante contacto.",
                'business' => BusinessType::Sale, 'type' => 'Quinta', 'condition' => 'Para recuperar',
                'bedrooms' => 4, 'bathrooms' => 2, 'area' => 220, 'plot' => 2400, 'year' => 1972, 'ce' => 'E',
                'district' => 'Lisboa', 'city' => 'Sintra', 'locality' => 'São João das Lampas', 'zip' => '2705-737',
                'lat' => 38.8712, 'lon' => -9.3945, 'gmap' => false, 'price' => 520000,
                'features' => ['terreno plano', 'poço', 'pomar', 'anexo'],
                'broker' => 'Marta Fonseca', 'days_ago' => 30, 'exclusive' => true,
            ],
            [
                'reference' => 'MF-2001', 'title' => 'Terreno urbano com projeto aprovado em Sesimbra',
                'description' => "Lote urbano de 1 200 m² com projeto aprovado para moradia unifamiliar de dois pisos (área bruta de construção de 280 m²).\n\nInfraestruturas à porta; taxas de licenciamento pagas. A dez minutos das praias da Califórnia e do Ouro.",
                'business' => BusinessType::Sale, 'type' => 'Terreno',
                'bedrooms' => null, 'bathrooms' => null, 'area' => null, 'plot' => 1200, 'ce' => 'Isento',
                'district' => 'Setúbal', 'city' => 'Sesimbra', 'locality' => 'Sesimbra (Santiago)', 'zip' => '2970-593',
                'lat' => 38.4479, 'lon' => -9.1015, 'price' => 320000,
                'features' => ['projeto aprovado', 'infraestruturas'],
                'broker' => 'Ricardo Almeida', 'days_ago' => 45,
            ],
            [
                'reference' => 'MF-2047', 'title' => 'Loja com montra em zona de passagem — Algés',
                'description' => "Loja de rés-do-chão com 75 m², montra de 6 metros e pé-direito de 3,2 m, em artéria comercial de grande passagem.\n\nWC e zona de armazém. Livre de trespasse; pronta a adaptar a qualquer atividade.",
                'business' => BusinessType::Rent, 'type' => 'Loja',
                'bedrooms' => null, 'bathrooms' => 1, 'area' => 75, 'year' => 1985, 'ce' => 'D',
                'district' => 'Lisboa', 'city' => 'Oeiras', 'locality' => 'Algés', 'zip' => '1495-069',
                'lat' => 38.7009, 'lon' => -9.2318, 'price' => 1400,
                'features' => ['montra', 'armazém'],
                'broker' => 'Sofia Ramos', 'days_ago' => 21,
            ],
            [
                'reference' => 'MF-2050', 'title' => 'Penthouse T4 com vista rio — preço sob consulta',
                'description' => "Último piso de um empreendimento premium, com 40 m² de terraço panorâmico sobre o Tejo, piscina no condomínio e três lugares de garagem.\n\nAcabamentos de autor, domótica completa e vista de rio de todas as divisões.\n\nDetalhes e visitas mediante contacto com o consultor.",
                'business' => BusinessType::Sale, 'type' => 'Apartamento', 'condition' => 'Novo',
                'bedrooms' => 4, 'bathrooms' => 4, 'area' => 240, 'year' => 2024, 'ce' => 'A+', 'floor' => 9,
                'district' => 'Lisboa', 'city' => 'Lisboa', 'locality' => 'Parque das Nações', 'zip' => '1990-231',
                'lat' => 38.7681, 'lon' => -9.0972, 'price' => null,
                'features' => ['terraço', 'piscina', 'domótica', 'garagem', 'vista de rio', 'condomínio fechado'],
                'broker' => 'Marta Fonseca', 'days_ago' => 1, 'featured' => true, 'exclusive' => true,
            ],
        ];
    }
}
