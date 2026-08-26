<?php

namespace Database\Factories;

use App\Enums\LeadKind;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory de Lead — apenas para testes.
 *
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+351 9'.fake()->numerify('## ### ###'),
            'message' => fake()->sentence(),
            'property_id' => null,
            'business_type' => null,
            'source' => LeadSource::Contact,
            'kind' => LeadKind::Buyer,
            'status' => LeadStage::Received,
            'priority' => 'normal',
            'payload' => null,
            'consent_contact' => false,
            'consent_marketing' => false,
            'policy_version' => '2026-08-18',
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => 'PestPHP',
        ];
    }
}
