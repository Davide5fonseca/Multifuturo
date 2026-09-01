<x-layouts.portal title="Início">
    @php $firstName = explode(' ', trim(auth()->user()->name))[0]; @endphp

    <div class="p-saudacao">
        <p class="p-eyebrow">Início</p>
        <h1>Olá, {{ $firstName }}</h1>
        <p>{{ $modules->isEmpty() ? 'Ainda não lhe deram acesso a nenhum módulo.' : 'Escolha o módulo onde quer trabalhar.' }}</p>
    </div>

    @if ($modules->isEmpty())
        <div class="p-vazio">
            <div class="p-vazio__icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/></svg>
            </div>
            <h2>Sem módulos atribuídos</h2>
            <p>Peça a um administrador que lhe dê acesso.</p>
        </div>
    @else
        <div class="p-modulos" data-modules>
            @foreach ($modules as $module)
                <a class="p-modulo" href="{{ $module['url'] }}">
                    <span class="p-modulo__icone" aria-hidden="true">@includeIf('portal.icons.'.$module['icon'])</span>
                    <span>
                        <span class="p-modulo__nome">{{ $module['name'] }}</span>
                        @if ($module['description'])<span class="p-modulo__descricao">{{ $module['description'] }}</span>@endif
                    </span>
                    <svg class="p-modulo__seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            @endforeach
        </div>
    @endif

    @if (auth()->user()->isAdmin())
        <p class="p-nota">Administrador: as contas e os acessos aos módulos gerem-se em <a href="{{ route('filament.admin.resources.users.index') }}">Equipa</a> (dentro do módulo Imóveis).</p>
    @endif
</x-layouts.portal>
