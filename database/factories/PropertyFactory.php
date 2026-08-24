<?php

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory de Property — APENAS para testes automatizados. Os dados são
 * fictícios e nunca devem servir de seed em ambientes reais: a única fonte
 * de imóveis é o feed do CASAFARI.
 *
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['apartamento', 'moradia', 'terreno', 'loja']);
        $city = fake()->randomElement(['Lisboa', 'Cascais', 'Oeiras', 'Sintra', 'Porto']);
        $reference = 'MF-'.fake()->unique()->numberBetween(1000, 99999);
        $internalId = (string) fake()->unique()->numberBetween(100000, 999999);

        return [
            'internal_id' => $internalId,
            'reference' => $reference,
            'price' => fake()->numberBetween(120, 2500) * 1000,
            'currency' => 'EUR',
            'business_type' => BusinessType::Sale,
            'property_type' => $type,
            'property_condition' => fake()->randomElement(['Novo', 'Usado', 'Em construção']),
            'bedrooms' => fake()->numberBetween(0, 5),
            'bathrooms' => fake()->numberBetween(1, 4),
            'house_area' => fake()->numberBetween(40, 400),
            'plot_area' => null,
            'gross_area' => fake()->numberBetween(50, 450),
            'country' => 'PT',
            'district' => 'Lisboa',
            'city' => $city,
            'locality' => fake()->randomElement(['Estrela', 'Estoril', 'Algés', 'Queluz', 'Bonfim']),
            'zone' => null,
            'zipcode' => fake()->numerify('####-###'),
            'lat' => fake()->latitude(38.6, 38.8),
            'lon' => fake()->longitude(-9.5, -9.1),
            'gmap_visible' => true,
            'floor_number' => fake()->numberBetween(0, 8),
            'build_year' => fake()->numberBetween(1950, 2025),
            'energy_rating' => fake()->randomElement(['A+', 'A', 'B', 'B-', 'C', 'D', 'E', 'F']),
            'crm_property_url' => null,
            'video_url' => null,
            'virtual_tour_url' => null,
            'floorplan_url' => null,
            'translations' => [
                'pt' => [
                    'title' => ucfirst($type).' T'.fake()->numberBetween(0, 5).' em '.$city,
                    'description' => fake()->paragraph(),
                ],
            ],
            'photos' => [
                ['url' => 'https://example.test/fotos/'.$internalId.'/1.jpg', 'order' => 1],
                ['url' => 'https://example.test/fotos/'.$internalId.'/2.jpg', 'order' => 2],
            ],
            'features' => fake()->randomElements(['elevador', 'garagem', 'varanda', 'terraco', 'piscina', 'jardim'], 2),
            'broker' => ['name' => fake()->name(), 'photo' => null],
            'slug' => Str::slug("{$type} {$city} {$reference}"),
            'payload_hash' => hash('sha256', $internalId),
            'crm_updated_at' => fake()->dateTimeBetween('-1 year'),
            'is_active' => true,
            'is_exclusive' => fake()->boolean(20),
            'is_featured' => false,
            'synced_at' => now(),
        ];
    }

    public function forRent(): static
    {
        return $this->state(fn () => [
            'business_type' => BusinessType::Rent,
            'price' => fake()->numberBetween(600, 4000),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withoutMap(): static
    {
        return $this->state(fn () => ['gmap_visible' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
