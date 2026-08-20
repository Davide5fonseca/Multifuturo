<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Support\PropertyCache;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

/**
 * Edição de imóvel. O slug NUNCA é recalculado (partiria URLs indexados) e o
 * internal_id não muda.
 *
 * Fotografias: o componente de upload só gere ficheiros do nosso storage. As
 * imagens externas (importadas do antigo CRM, alojadas no CDN deles) não
 * aparecem no componente mas são preservadas — lidas do registo na gravação,
 * e não de uma propriedade de instância, que não sobrevive entre pedidos Livewire.
 */
class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->after(fn () => PropertyCache::flush()),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
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
