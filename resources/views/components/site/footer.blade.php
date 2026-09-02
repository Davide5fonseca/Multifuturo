{{--
    Rodapé. Obrigações legais da mediação imobiliária em Portugal:
    - Licença AMI visível (Lei n.º 15/2013);
    - Ligação ao Livro de Reclamações eletrónico (DL n.º 74/2017);
    - Ligações à política de privacidade, termos e cookies.
    O verde azeitona só aparece aqui como fundo por ser uma área "fechada" — é o único bloco grande em olive-900.
--}}
@php
    $agency = config('agency');
    $social = array_filter($agency['social'] ?? []);
@endphp
<footer class="mt-24 bg-olive-900 text-sand-100 print:hidden">
    <div class="container-site flex flex-col gap-12 py-16 md:flex-row md:items-start md:justify-between">
        <div class="max-w-sm">
            {{-- No rodapé há altura para o logótipo completo. A imagem é verde
                 sobre fundo escuro: leva um filtro para ficar em bege claro. --}}
            <a href="{{ route('home') }}" class="inline-block" aria-label="{{ $agency['name'] }}">
                <img src="{{ asset('images/marca/logotipo.png') }}" alt="{{ $agency['name'] }}"
                     width="588" height="540" class="h-24 w-auto brightness-0 invert opacity-90">
            </a>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-sand-200">
                {{ $agency['name'] }}
                @if ($agency['address'])<br>{{ $agency['address'] }}@endif
                @if ($agency['phone'])<br><a href="tel:{{ preg_replace('/\s+/', '', $agency['phone']) }}" class="hover:text-sand-50">{{ $agency['phone'] }}</a>@endif
                @if ($agency['email'])<br><a href="mailto:{{ $agency['email'] }}" class="hover:text-sand-50">{{ $agency['email'] }}</a>@endif
            </p>
            <p class="mt-6 text-sm font-medium text-sand-50" data-testid="ami">
                @if (filled($agency['ami']))
                    {{ __('ui.footer.ami', ['number' => $agency['ami']]) }}
                @else
                    {{ __('ui.footer.ami_missing') }}
                @endif
            </p>
        </div>

        <div>
            <h2 class="label text-sand-200">{{ __('ui.footer.properties') }}</h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('buy') }}" class="hover:text-sand-50">{{ __('ui.nav.buy') }}</a></li>
                <li><a href="{{ route('rent') }}" class="hover:text-sand-50">{{ __('ui.nav.rent') }}</a></li>
                <li><a href="{{ route('valuation') }}" class="hover:text-sand-50">{{ __('ui.nav.valuation') }}</a></li>
            </ul>
            @if ($social)
                <h2 class="label mt-8 text-sand-200">{{ __('ui.footer.follow') }}</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($social as $network => $url)
                        <li><a href="{{ $url }}" rel="noopener" target="_blank" class="hover:text-sand-50">{{ ucfirst($network) }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h2 class="label text-sand-200">{{ __('ui.footer.legal') }}</h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('privacy') }}" class="hover:text-sand-50">{{ __('ui.footer.privacy') }}</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-sand-50">{{ __('ui.footer.terms') }}</a></li>
                <li><a href="{{ route('cookies') }}" class="hover:text-sand-50">{{ __('ui.footer.cookies') }}</a></li>
                <li><button type="button" x-data @click="$store.consent.manage()" class="hover:text-sand-50">{{ __('legal.consent.manage') }}</button></li>
                <li>
                    <a href="{{ $agency['complaints_book_url'] }}" rel="noopener" target="_blank" class="hover:text-sand-50">
                        {{ __('ui.footer.complaints_book') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="border-t border-olive-700">
        <div class="container-site py-6 text-xs text-sand-200">
            {{ __('ui.footer.rights', ['year' => now()->year, 'name' => $agency['name']]) }}
        </div>
    </div>
</footer>
