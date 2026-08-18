<x-layouts.app :title="__('ui.favorites.title')" robots="noindex,follow">
    {{--
        Sem ?slugs= no URL, o Alpine lê o localStorage e recarrega a página com os slugs.
        Sem JS, mostra-se o estado vazio (os favoritos vivem no browser por definição).
    --}}
    <section class="container-site pt-16 pb-24"
             x-data="{ init() { @if (! $requested) const s = $store.favorites.slugs; if (s.length) { window.location.replace(@js(route('favorites')) + '?slugs=' + encodeURIComponent(s.join(','))); } @endif } }">
        <p class="label">{{ config('agency.name') }}</p>
        <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.favorites.title') }}</h1>
        <p class="mt-4 max-w-xl text-ink-muted">{{ __('ui.favorites.lead') }}</p>

        @if ($properties->isEmpty())
            <div class="mt-12 border border-sand-200 bg-sand-100 px-6 py-16 text-center">
                <p class="text-lg">{{ __('ui.favorites.empty') }}</p>
                <a href="{{ route('buy') }}" class="btn-primary mt-6">{{ __('ui.nav.buy') }}</a>
            </div>
        @else
            <p class="mt-10 text-sm text-ink-muted">{{ trans_choice('ui.favorites.count', $properties->count(), ['count' => $properties->count()]) }}</p>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($properties as $i => $property)
                    <div x-data x-show="$store.favorites.has(@js($property->slug))">
                        <x-property.card :property="$property" :eager="$i < 3" />
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
