<?php

namespace Database\Seeders;

use App\Enums\ContactKind;
use App\Enums\EventType;
use App\Enums\LeadKind;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyActivity;
use App\Models\PropertyView;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Dados de DEMONSTRAÇÃO do CRM (clientes, leads com pipeline, agenda e
 * visualizações) para se ver a dashboard preenchida antes de haver uso real.
 * Nunca corre em produção; reexecutar substitui os registos de demonstração.
 */
class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoCrmSeeder não corre em produção.');
        }

        $user = User::query()->first() ?? User::factory()->create(['name' => 'Equipa Multifuturo']);
        $properties = Property::query()->take(4)->get();

        // Limpar a demonstração anterior.
        Event::query()->where('title', 'like', '[DEMO]%')->delete();
        Lead::query()->where('name', 'like', '%[DEMO]')->delete();
        Contact::query()->where('name', 'like', '%[DEMO]')->delete();

        $clientes = collect([
            ['name' => 'Marta Ribeiro [DEMO]', 'kind' => ContactKind::Owner, 'city' => 'Cascais'],
            ['name' => 'Tomás Nogueira [DEMO]', 'kind' => ContactKind::Owner, 'city' => 'Oeiras'],
            ['name' => 'Sara Lopes [DEMO]', 'kind' => ContactKind::Buyer, 'city' => 'Lisboa'],
            ['name' => 'Hugo Martins [DEMO]', 'kind' => ContactKind::Buyer, 'city' => 'Sintra'],
            ['name' => 'Inês Carvalho [DEMO]', 'kind' => ContactKind::Both, 'city' => 'Cascais'],
        ])->map(fn (array $c) => Contact::query()->create($c + [
            'email' => str($c['name'])->before(' [DEMO]')->slug().'@example.test',
            'phone' => '+351 9'.random_int(10, 39).' '.random_int(100, 999).' '.random_int(100, 999),
            'assigned_to' => $user->id,
        ]));

        // Leads de angariação (quem quer vender/arrendar connosco).
        foreach ([
            ['contact' => $clientes[0], 'status' => LeadStage::Prospecting, 'priority' => LeadPriority::Normal],
            ['contact' => $clientes[1], 'status' => LeadStage::ContactOwner, 'priority' => LeadPriority::Urgent],
            ['contact' => $clientes[4], 'status' => LeadStage::Valuation, 'priority' => LeadPriority::High],
        ] as $row) {
            $this->lead($row['contact'], LeadKind::Listing, $row['status'], $row['priority'], $user, null);
        }

        // Leads de compradores (incluindo pedidos vindos do site).
        foreach ([
            ['contact' => $clientes[2], 'status' => LeadStage::Received, 'priority' => LeadPriority::Normal, 'property' => $properties->get(0)],
            ['contact' => $clientes[2], 'status' => LeadStage::Qualification, 'priority' => LeadPriority::High, 'property' => $properties->get(0)],
            ['contact' => $clientes[3], 'status' => LeadStage::Visit, 'priority' => LeadPriority::Normal, 'property' => $properties->get(1)],
        ] as $row) {
            $this->lead($row['contact'], LeadKind::Buyer, $row['status'], $row['priority'], $user, $row['property']);
        }

        // Agenda: alguns eventos atrasados e outros por vir.
        $agenda = [
            ['[DEMO] Telefonar à Sra. Ribeiro', EventType::Call, now()->subDays(2)->setTime(9, 47), $clientes[0]],
            ['[DEMO] Visita ao apartamento', EventType::Visit, now()->addDay()->setTime(14, 0), $clientes[2]],
            ['[DEMO] Reunião com proprietário', EventType::Meeting, now()->addDays(3)->setTime(10, 30), $clientes[1]],
            ['[DEMO] Preparar avaliação', EventType::Task, now()->addDays(5)->setTime(11, 0), $clientes[4]],
            ['[DEMO] Aniversário — enviar mensagem', EventType::Reminder, now()->addDays(7)->setTime(9, 0), $clientes[3]],
        ];
        foreach ($agenda as [$title, $type, $when, $contact]) {
            Event::query()->create([
                'title' => $title,
                'type' => $type,
                'starts_at' => $when,
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'property_id' => $properties->first()?->id,
            ]);
        }

        // Histórico de alterações (o observer só regista o que acontecer daqui para a frente).
        PropertyActivity::query()->delete();
        $historico = [
            ['created', null, 1],
            ['price', '520 001 € → 520 000 €', 2],
            ['price', '1 350 001 € → 1 350 000 €', 4],
            ['status', 'Publicada', 6],
            ['updated', null, 8],
            ['status', 'Retirada do site', 11],
        ];
        foreach ($historico as $i => [$type, $detail, $diasAtras]) {
            $property = $properties->get($i % max(1, $properties->count()));
            if ($property) {
                PropertyActivity::query()->create([
                    'property_id' => $property->id,
                    'user_id' => $user->id,
                    'type' => $type,
                    'detail' => $detail,
                    'created_at' => now()->subDays($diasAtras)->subHours(random_int(1, 9)),
                    'updated_at' => now()->subDays($diasAtras),
                ]);
            }
        }

        // Visualizações dos últimos 30 dias, para o gráfico ter forma.
        PropertyView::query()->delete();
        foreach ($properties as $property) {
            for ($i = 29; $i >= 0; $i--) {
                $views = random_int(0, 6) + ($i % 7 === 0 ? random_int(2, 9) : 0);
                if ($views > 0) {
                    PropertyView::query()->create([
                        'property_id' => $property->id,
                        'viewed_on' => now()->subDays($i)->toDateString(),
                        'views' => $views,
                    ]);
                }
            }
        }

        $this->command?->info(sprintf(
            'Demo CRM: %d clientes, %d leads, %d eventos, visualizações de 30 dias.',
            Contact::query()->where('name', 'like', '%[DEMO]')->count(),
            Lead::query()->where('name', 'like', '%[DEMO]')->count(),
            Event::query()->where('title', 'like', '[DEMO]%')->count(),
        ));
    }

    private function lead(Contact $contact, LeadKind $kind, LeadStage $status, LeadPriority $priority, User $user, ?Property $property): void
    {
        Lead::query()->create([
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'message' => $kind === LeadKind::Listing
                ? 'Gostaria de saber quanto vale o meu imóvel.'
                : 'Tenho interesse neste imóvel; podem contactar-me?',
            'source' => $kind === LeadKind::Listing ? LeadSource::Valuation : LeadSource::Property,
            'kind' => $kind,
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => $user->id,
            'contact_id' => $contact->id,
            'property_id' => $property?->id,
            'policy_version' => config('agency.privacy_policy_version'),
            'consent_contact' => true,
            'created_at' => now()->subDays(random_int(0, 9))->subHours(random_int(0, 20)),
        ]);
    }
}
