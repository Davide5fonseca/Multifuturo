<x-layouts.portal title="Início">
    @php $firstName = explode(' ', trim(auth()->user()->name))[0]; @endphp

    <div class="max-w-2xl">
        <p class="label">Portal</p>
        <h1 class="mt-3 text-4xl sm:text-5xl">Olá, {{ $firstName }}</h1>
        <p class="mt-4 text-ink-muted">
            {{ $modules->isEmpty() ? 'Ainda não lhe deram acesso a nenhum módulo.' : 'Escolha o módulo onde quer trabalhar.' }}
        </p>
    </div>

    @if ($modules->isEmpty())
        <div class="mt-12 max-w-xl rounded-2xl border border-sand-200 bg-sand-100 px-6 py-12 text-center">
            <p class="text-lg">Sem módulos atribuídos</p>
            <p class="mt-2 text-sm text-ink-muted">Peça a um administrador que lhe dê acesso.</p>
        </div>
    @else
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-modules>
            @foreach ($modules as $module)
                <a href="{{ $module['url'] }}" class="group flex flex-col rounded-2xl border border-sand-200 bg-sand-100 p-6 transition hover:border-olive-600/40 hover:bg-sand-50">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-olive-600 text-sand-50" aria-hidden="true">
                        @includeIf('portal.icons.'.$module['icon'], [])
                    </span>
                    <span class="mt-5 font-serif text-2xl">{{ $module['name'] }}</span>
                    @if ($module['description'])
                        <span class="mt-2 text-sm text-ink-muted">{{ $module['description'] }}</span>
                    @endif
                    <span class="mt-6 text-sm font-medium text-olive-700 group-hover:underline">Abrir →</span>
                </a>
            @endforeach
        </div>
    @endif

    @if (auth()->user()->isAdmin())
        <p class="mt-12 text-sm text-ink-muted">
            Administrador: gere as contas e os acessos em
            <a href="{{ route('filament.admin.resources.users.index') }}" class="link">Equipa</a>.
        </p>
    @endif
</x-layouts.portal>
