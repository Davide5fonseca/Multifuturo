<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Properties\Schemas;
use App\Models\Property;
use App\Support\PropertyCache;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

/**
 * Criação de imóvel no backoffice. Os campos técnicos são gerados aqui:
 *  - internal_id: "BO-" + ULID (BO = backoffice; distingue de importações do CRM);
 *  - slug: tipo-concelho-referência, gerado UMA vez e nunca recalculado;
 *  - payload_hash: sha256 do conteúdo (mantém a coluna coerente);
 *  - photos: o upload devolve caminhos no disco público → [{url, order}].
 */
class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    /** O mesmo cabeçalho do CRM da edição; Ver e Ações desbloqueiam depois de gravar. */
    protected function getHeaderActions(): array
    {
        $aindaNaoGravada = 'Disponível depois de gravar a ficha.';

        return [
            Action::make('verNoWebsite')
                ->label('Ver no website')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->disabled()
                ->tooltip($aindaNaoGravada),
            ActionGroup::make([
                Action::make('partilhar')->label('Partilhar')->disabled()->tooltip($aindaNaoGravada),
                Action::make('imprimir')->label('Imprimir')->icon('heroicon-m-printer')->extraAttributes(['x-on:click' => 'window.print()']),
                Action::make('apagar')->label('Apagar propriedade')->color('danger')->disabled()->tooltip($aindaNaoGravada),
            ])
                ->label('Ações')
                ->button()
                ->color('gray'),
            Action::make('gravar')
                ->label('Gravar')
                ->icon('heroicon-m-check')
                ->action(fn () => $this->create())
                ->keyBindings(['mod+s']),
            Action::make('sair')
                ->label('Sair')
                ->color('gray')
                ->url(PropertyResource::getUrl('index')),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = Schemas\PropertyForm::foldDetailFeatures($data);
        $data['translations'] = self::withGeneratedTitle($data);
        $data['internal_id'] = 'BO-'.strtolower((string) Str::ulid());
        $data['photos'] = self::photosFromUploads($data['photos'] ?? []);
        $data['slug'] = Property::generateSlug(
            $data['property_type'] ?? null,
            $data['city'] ?? null,
            $data['reference'] ?? null,
            $data['internal_id']
        );
        $data['payload_hash'] = hash('sha256', json_encode($data));
        $data['synced_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        PropertyCache::flush();
    }

    /**
     * Título do anúncio. O separador "Descrições" do CRM ainda não foi
     * trabalhado e o formulário não tem onde o escrever, mas o site precisa de
     * um nome para a ficha — gera-se a partir do tipo, tipologia e concelho
     * ("Moradia T3 em Espinho"). Um título já existente nunca é substituído.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withGeneratedTitle(array $data): array
    {
        $translations = $data['translations'] ?? [];

        if (filled($translations['pt']['title'] ?? null)) {
            return $translations;
        }

        $parts = array_filter([
            $data['property_type'] ?? null,
            ($data['typology'] ?? null) !== 'Não aplicável' ? ($data['typology'] ?? null) : null,
            filled($data['city'] ?? null) ? 'em '.$data['city'] : null,
        ]);

        $translations['pt']['title'] = implode(' ', $parts) ?: (string) ($data['reference'] ?? 'Imóvel');

        return $translations;
    }

    /**
     * Caminhos do FileUpload ("imoveis/x.jpg") → formato do site [{url, order}].
     *
     * @param  array<int, string>  $paths
     * @return array<int, array{url: string, order: int}>
     */
    public static function photosFromUploads(array $paths): array
    {
        $photos = [];
        foreach (array_values($paths) as $i => $path) {
            if (is_string($path) && $path !== '') {
                $photos[] = ['url' => Str::startsWith($path, ['/', 'http']) ? $path : '/storage/'.$path, 'order' => $i + 1];
            }
        }

        return $photos;
    }
}
