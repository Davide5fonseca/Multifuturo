<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Notifications\LeadReply;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification as Aviso;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Detalhe de uma dúvida chegada pelo site.
 *
 * A dúvida em si não se edita — é o registo do que a pessoa enviou. O que se
 * faz aqui é responder-lhe, e a resposta fica guardada na própria dúvida.
 */
class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        $lead = $this->getRecord();

        return [
            Action::make('responder')
                ->label($lead->foiRespondida() ? 'Responder de novo' : 'Responder ao cliente')
                ->icon('heroicon-m-paper-airplane')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading('Responder ao cliente')
                // O email é obrigatório no formulário público e na base de dados:
                // há sempre para onde responder.
                ->modalDescription(fn () => "A resposta segue por email para {$lead->email}.")
                ->modalSubmitActionLabel('Enviar resposta')
                ->schema([
                    Textarea::make('body')
                        ->label('Mensagem')
                        ->rows(10)
                        ->required()
                        ->minLength(10)
                        ->default(fn () => self::rascunho($lead))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) use ($lead): void {
                    $autor = Auth::user()?->name ?? config('agency.name');

                    Notification::route('mail', $lead->email)
                        ->notify(new LeadReply($lead, $data['body'], $autor));

                    $lead->registarResposta($data['body'], $autor);

                    Aviso::make()
                        ->title('Resposta enviada')
                        ->body("Segue para {$lead->email}. Fica registada nesta dúvida.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['replies', 'replied_at']);
                }),

            // O pedido diz de que imóvel se trata: dá para lá ir num clique, em
            // vez de procurar a referência à mão em Imóveis.
            Action::make('verImovel')
                ->label('Abrir imóvel')
                ->icon('heroicon-m-home-modern')
                ->color('gray')
                ->url(fn () => $lead->property
                    ? route('filament.admin.resources.properties.edit', $lead->property)
                    : null)
                ->visible(fn () => $lead->property !== null),

            // Para quem prefere responder da sua própria caixa de correio, ou ligar.
            Action::make('emailDirecto')
                ->label('Abrir no meu email')
                ->icon('heroicon-m-envelope')
                ->color('gray')
                ->url(fn () => 'mailto:'.$lead->email),

            Action::make('telefonar')
                ->label('Telefonar')
                ->icon('heroicon-m-phone')
                ->color('gray')
                ->url(fn () => $lead->phone ? 'tel:'.preg_replace('/\s+/', '', $lead->phone) : null)
                ->visible(fn () => filled($lead->phone)),

            DeleteAction::make(),
        ];
    }

    /** Rascunho para quem responde não começar de uma folha em branco. */
    private static function rascunho(Lead $lead): string
    {
        $referencia = $lead->property?->reference;

        return match ($lead->source->value) {
            'property' => 'Obrigado pelo seu interesse'.($referencia ? " no imóvel {$referencia}" : '').".\n\n",
            'valuation' => "Obrigado pelo seu pedido de avaliação.\n\n",
            default => "Obrigado pelo seu contacto.\n\n",
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['replies_texto'] = collect($this->getRecord()->replies ?? [])
            ->map(fn (array $r) => Carbon::parse($r['at'])
                ->timezone(config('app.timezone'))
                ->translatedFormat('d/m/Y H:i')." — {$r['author']}\n{$r['body']}")
            ->implode("\n\n———\n\n");

        return $data;
    }
}
