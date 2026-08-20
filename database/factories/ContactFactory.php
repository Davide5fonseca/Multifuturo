<?php

namespace Database\Factories;

use App\Enums\ContactKind;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory de Contact — apenas para testes.
 *
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+351 9'.fake()->numerify('## ### ###'),
            'kind' => fake()->randomElement(ContactKind::cases()),
            'city' => fake()->randomElement(['Cascais', 'Lisboa', 'Oeiras', 'Sintra']),
            'preferences' => [],
        ];
    }
}
