<x-layouts.app :title="__('ui.compare.title')" robots="noindex,follow">
    {{--
        Comparador. Sem ?slugs= no URL, o Alpine lê o localStorage e recarrega a
        página com os slugs escolhidos; sem JavaScript mostra-se o estado vazio,
        porque a escolha vive no browser por definição.

        Com ?slugs=, o servidor devolve só os imóveis publicados — e a página poda
        da lista os que ficaram pelo caminho (vendidos, retirados, apagados).
    --}}
    <section class="container-site pt-16 pb-24"
             x-data="{ init() {
                 @if ($requested)
                     $store.compare.prune(@js($properties->pluck('slug')->all()));
                 @else
                     const s = $store.compare.slugs;
                     if (s.length) { window.location.replace(@js(route('compare')) + '?slugs=' + encodeURIComponent(s.join(','))); }
                 @endif
             } }">
        <p class="label">{{ config('agency.name') }}</p>
        <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.compare.title') }}</h1>
        <p class="mt-4 max-w-xl text-ink-muted">{{ __('ui.compare.lead') }}</p>

        @if ($properties->count() < 2)
            <div class="mt-12 rounded-xl border border-sand-200 bg-sand-100 px-6 py-16 text-center">
                <p class="text-lg">{{ $properties->isEmpty() ? __('ui.compare.empty') : __('ui.compare.need_two') }}</p>
                <a href="{{ route('buy') }}" class="btn-primary mt-6">{{ __('ui.nav.buy') }}</a>
            </div>
        @else
            {{-- Em ecrãs estreitos a tabela desliza na horizontal; a coluna dos rótulos fica fixa. --}}
            <div class="mt-12 overflow-x-auto">
                <table class="w-full min-w-[44rem] border-collapse text-sm">
                    <caption class="sr-only">{{ __('ui.compare.title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="sticky left-0 z-10 w-40 bg-sand-50 pb-6 pr-4 text-left align-bottom">
                                <span class="label">{{ trans_choice('ui.compare.count', $properties->count(), ['count' => $properties->count()]) }}</span>
                            </th>
                            @foreach ($properties as $p)
                                @php $titulo = $p->title ?: trim(($p->property_type ?? '').' '.(\App\Support\Format::typology($p->bedrooms) ?? '')); @endphp
                                <th scope="col" class="w-1/3 pb-6 pl-4 text-left align-bottom font-normal">
                                    <a href="{{ route('property.show', $p) }}" class="block">
                                        <x-property.image :src="$p->cover_photo['url'] ?? null" :alt="$titulo" ratio="4/3" class="rounded-xl" sizes="33vw" />
                                        <span class="label mt-3 block">{{ __('ui.property.reference') }} {{ $p->reference ?? $p->internal_id }}</span>
                                        <span class="mt-1 block text-base leading-snug hover:underline">{{ $titulo }}</span>
                                    </a>
                                    <button type="button" x-cloak x-data class="link mt-2 text-xs"
                                            @click="$store.compare.toggle(@js($p->slug)); window.location.href = @js(route('compare'))">
                                        {{ __('ui.compare.remove') }}
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $label => $valores)
                            <tr class="border-t border-sand-200">
                                <th scope="row" class="sticky left-0 z-10 bg-sand-50 py-3 pr-4 text-left align-top font-medium text-ink-muted">{{ $label }}</th>
                                @foreach ($valores as $valor)
                                    <td class="py-3 pl-4 align-top {{ $valor === null ? 'text-ink-muted' : '' }}">{{ $valor ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-10 flex flex-wrap gap-3">
                <a href="{{ route('buy') }}" class="btn-secondary">{{ __('ui.compare.add_more') }}</a>
                <button type="button" x-cloak x-data class="btn-secondary"
                        @click="$store.compare.clear(); window.location.href = @js(route('compare'))">
                    {{ __('ui.compare.clear') }}
                </button>
            </div>
        @endif
    </section>
</x-layouts.app>
