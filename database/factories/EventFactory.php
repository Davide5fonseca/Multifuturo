<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory de Event — apenas para testes.
 *
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(EventType::cases()),
            'starts_at' => fake()->dateTimeBetween('-2 days', '+10 days'),
            'is_done' => false,
        ];
    }
}
