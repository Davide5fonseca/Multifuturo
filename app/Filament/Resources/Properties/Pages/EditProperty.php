<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Properties\Schemas;
use App\Support\PropertyCache;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

/**
 * Edição de imóvel. O slug NUNCA é recalculado (partiria URLs indexados) e o
 * internal_id não muda.
 *
 * Cabeçalho como no CRM: referência + ID, menu "Ver", menu "Ações"
 * (Partilhar · Imprimir · Apagar propriedade), "Gravar" e "Sair".
 *
 * Fotografias: o componente de upload só gere ficheiros do nosso storage. As
 * imagens externas (importadas do antigo CRM, alojadas no CDN deles) não
 * aparecem no componente mas são preservadas — lidas do registo na gravação,
 * e não de uma propriedade de instância, que não sobrevive entre pedidos Livewire.
 */
class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    public function getTitle(): string
    {
        return (string) ($this->getRecord()->reference ?: 'Editar imóvel');
    }

    public function getSubheading(): ?string
    {
        return '(ID: '.$this->getRecord()->getKey().')';
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $publishable = $record->isPublishable();
        $publicUrl = $publishable ? route('property.show', $record) : null;

        return [
            ActionGroup::make([
                Action::make('verNoWebsite')
                    ->label('Ver no website')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url($publicUrl, shouldOpenInNewTab: true)
                    ->disabled(! $publishable)
                    ->tooltip($publishable ? null : 'A ficha não está publicada no site.'),
                Action::make('smartview')
                    ->label('Smartview')
                    ->disabled()
                    ->tooltip('Serviço do CRM da CASAFARI — não existe neste backoffice.'),
                Action::make('portais')
                    ->label('Portais')
                    ->disabled()
                    ->tooltip('Não há portais ligados ao sistema.'),
            ])
                ->label('Ver')
                ->button()
                ->color('gray'),

            ActionGroup::make([
                Action::make('partilhar')
                    ->label('Partilhar')
                    ->icon('heroicon-m-link')
                    ->disabled(! $publishable)
                    ->tooltip($publishable ? 'Copia a ligação pública da ficha.' : 'A ficha não está publicada no site.')
                    ->extraAttributes($publicUrl ? ['x-on:click' => 'window.navigator.clipboard.writeText('.json_encode($publicUrl, JSON_HEX_APOS | JSON_HEX_QUOT).')'] : [])
                    ->action(function () use ($publicUrl): void {
                        if ($publicUrl) {
                            Notification::make()
                                ->title('Ligação copiada')
                                ->body($publicUrl)
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('imprimir')
                    ->label('Imprimir')
                    ->icon('heroicon-m-printer')
                    ->extraAttributes(['x-on:click' => 'window.print()']),
                DeleteAction::make()
                    ->label('Apagar propriedade')
                    ->after(fn () => PropertyCache::flush()),
            ])
                ->label('Ações')
                ->button()
                ->color('gray'),

            Action::make('gravar')
                ->label('Gravar')
                ->icon('heroicon-m-check')
                ->action(fn () => $this->save())
                ->keyBindings(['mod+s']),

            Action::make('sair')
                ->label('Sair')
                ->color('gray')
                ->url(PropertyResource::getUrl('index')),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // As comodidades guardadas em `features` voltam aos grupos do formulário.
        $data = [...$data, ...Schemas\PropertyForm::splitDetailFeatures($data['features'] ?? [])];

        // Só os uploads locais entram no componente (caminhos relativos ao disco público).
        $data['photos'] = collect($data['photos'] ?? [])
            ->pluck('url')
            ->filter(fn ($u) => Str::startsWith($u, '/storage/'))
            ->map(fn ($u) => Str::after($u, '/storage/'))
            ->values()
            ->all();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = Schemas\PropertyForm::foldDetailFeatures($data);
        $external = collect($this->getRecord()->photos ?? [])
            ->pluck('url')
            ->reject(fn ($u) => Str::startsWith($u, '/storage/'))
            ->values();

        $photos = [];
        $order = 1;
        foreach ($external as $url) {
            $photos[] = ['url' => $url, 'order' => $order++];
        }
        foreach (CreateProperty::photosFromUploads($data['photos'] ?? []) as $photo) {
            $photos[] = ['url' => $photo['url'], 'order' => $order++];
        }

        $data['photos'] = $photos;
        $data['payload_hash'] = hash('sha256', json_encode($data));
        unset($data['slug'], $data['internal_id']); // imutáveis

        return $data;
    }

    protected function afterSave(): void
    {
        PropertyCache::flush();
    }
}
