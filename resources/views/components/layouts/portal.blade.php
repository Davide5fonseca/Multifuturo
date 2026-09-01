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
    <div class="p-app">
        <header class="p-topo">
            <div class="p-topo__dentro">
                <a href="{{ route('portal') }}" class="p-marca">{!! $marca !!}</a>
                @auth
                    @php
                        $pessoa = auth()->user();
                        $iniciais = collect(explode(' ', trim($pessoa->name)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') ?: '–';
                    @endphp
                    <div class="p-topo__pessoa">
                        <span class="p-avatar" aria-hidden="true">{{ $iniciais }}</span>
                        <span class="p-topo__quem"><span class="p-topo__nome">{{ $pessoa->name }}</span><span class="p-topo__email">{{ $pessoa->email }}</span></span>
                        {{-- O único "terminar sessão": dentro dos módulos, o botão devolve ao portal. --}}
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="p-btn p-btn--fantasma">Terminar sessão</button>
                        </form>
                    </div>
                @endauth
            </div>
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
@endif
</body>
</html>
