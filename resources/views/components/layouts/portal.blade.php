{{--
    Layout do portal da equipa. Uma plataforma própria, sem nada da agência:
    nome e marca vêm de config/portal.php (PORTAL_NAME), o visual de
    resources/css/portal.css, e os textos são genéricos — o módulo de
    imóveis é só um dos cartões.

    Props:
      title   — título da página
      entrada — true nas páginas de login/verificação (painel de marca à
                esquerda, formulário à direita); false na página de escolha
                (barra superior escura, conteúdo claro).
--}}
@props(['title' => 'Portal', 'entrada' => false])
@php $portal = config('portal.name'); @endphp
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0B1220">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ $portal }}</title>
    {{-- Ícone próprio do portal (grelha de módulos), não o da agência. --}}
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#4F46E5"/><g fill="#fff"><rect x="7" y="7" width="8" height="8" rx="2"/><rect x="17" y="7" width="8" height="8" rx="2"/><rect x="7" y="17" width="8" height="8" rx="2"/><rect x="17" y="17" width="8" height="8" rx="2"/></g></svg>') }}">
    @vite(['resources/css/portal.css'])
</head>
<body>
@php
    $marca = '<span class="p-marca__simbolo" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>'
        .'<span><span class="p-marca__nome">'.e($portal).'</span><span class="p-marca__sub">Área de trabalho</span></span>';
@endphp
@if ($entrada)
    <div class="p-entrada">
        {{-- Painel da marca (esquerda). Decorativo: desaparece em ecrãs estreitos. --}}
        <aside class="p-entrada__marca-painel" aria-hidden="true">
            <span class="p-brilho p-brilho--cima"></span>
            <span class="p-brilho p-brilho--baixo"></span>

            <div class="p-marca">{!! $marca !!}</div>

            {{-- Só uma ponte: uma frase, sem discurso comercial. --}}
            <div class="p-discurso">
                <h2>Uma só entrada.<br>Todos os módulos.</h2>
                <p>Entre uma vez e escolha onde quer trabalhar.</p>
            </div>

            <p class="p-seguranca">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Acesso reservado · ligação segura
            </p>
        </aside>

        <main class="p-entrada__painel">
            <div class="p-entrada__caixa">
                <div class="p-marca p-entrada__marca-estreita">{!! $marca !!}</div>

                @if (session('status'))
                    <div class="p-alerta p-alerta--ok" role="status">{{ session('status') }}</div>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>
@else
    @php
        $pessoa = auth()->user();
        $iniciais = $pessoa ? (collect(explode(' ', trim($pessoa->name)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') ?: '–') : '–';
    @endphp
    <div class="p-app" data-app>
        <button class="p-veu" data-fechar-lateral hidden aria-label="Fechar menu"></button>

        {{-- Barra lateral: marca, navegação, e a pessoa com o único "terminar sessão". --}}
        <aside class="p-lateral" data-lateral>
            <div class="p-lateral__topo">
                <a href="{{ route('portal') }}" class="p-marca">{!! $marca !!}</a>
                <button class="p-lateral__fechar" data-fechar-lateral aria-label="Fechar menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="p-lateral__nav" aria-label="Navegação do portal">
                <a class="p-nav {{ request()->routeIs('portal') ? 'p-nav--ativo' : '' }}" href="{{ route('portal') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    <span>Início</span>
                </a>

                <p class="p-lateral__grupo">A minha conta</p>
                <a class="p-nav" href="{{ route('filament.admin.auth.profile') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span>Perfil e palavra-passe</span>
                </a>

                @if ($pessoa?->isAdmin())
                    <p class="p-lateral__grupo">Gestão</p>
                    <a class="p-nav {{ request()->routeIs('team.*') ? 'p-nav--ativo' : '' }}" href="{{ route('team.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>Equipa e acessos</span>
                    </a>
                @endif
            </nav>

            @auth
                <div class="p-lateral__pessoa">
                    <span class="p-avatar" aria-hidden="true">{{ $iniciais }}</span>
                    <span class="p-lateral__quem">
                        <span class="p-lateral__nome">{{ $pessoa->name }}</span>
                        <span class="p-lateral__email">{{ $pessoa->email }}</span>
                    </span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="p-lateral__sair" title="Terminar sessão" aria-label="Terminar sessão">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            @endauth
        </aside>

        <div class="p-trabalho">
            <header class="p-topo-movel">
                <button class="p-topo-movel__menu" data-abrir-lateral aria-label="Abrir menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="p-topo-movel__nome">{{ $portal }}</span>
            </header>

            <main class="p-principal">
                <div class="p-largura">
                    @if (session('status'))
                        <div class="p-alerta p-alerta--ok" role="status">{{ session('status') }}</div>
                    @endif
                    {{ $slot }}
                </div>
            </main>

            <footer class="p-rodape">{{ $portal }} · acesso reservado</footer>
        </div>
    </div>

    <script>
        // Barra lateral no telemóvel: abre com o botão de menu, fecha com o véu, o X ou Escape.
        (function () {
            var lateral = document.querySelector('[data-lateral]'), veu = document.querySelector('.p-veu');
            if (!lateral) return;
            function abrir() { lateral.classList.add('p-lateral--aberta'); veu.hidden = false; }
            function fechar() { lateral.classList.remove('p-lateral--aberta'); veu.hidden = true; }
            document.querySelectorAll('[data-abrir-lateral]').forEach(function (b) { b.addEventListener('click', abrir); });
            document.querySelectorAll('[data-fechar-lateral]').forEach(function (b) { b.addEventListener('click', fechar); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });
        })();
    </script>
@endif
</body>
</html>
