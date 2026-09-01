{{--
    Layout do portal da equipa. Visual próprio (resources/css/portal.css),
    separado do site: quem entra tem de perceber que mudou de sítio.

    Props:
      title   — título da página
      entrada — true nas páginas de login/verificação (painel de marca à
                esquerda, formulário à direita); false na página de escolha
                (barra superior escura, conteúdo claro).
--}}
@props(['title' => 'Portal', 'entrada' => false])
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0B1220">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Portal · {{ config('agency.name') }}</title>
    <link rel="icon" href="{{ asset('images/marca/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/marca/favicon-180.png') }}">
    @vite(['resources/css/portal.css'])
</head>
<body>
@if ($entrada)
    <div class="p-entrada">
        {{-- Painel da marca (esquerda). Decorativo: desaparece em ecrãs estreitos. --}}
        <aside class="p-entrada__marca-painel" aria-hidden="true">
            <span class="p-brilho p-brilho--cima"></span>
            <span class="p-brilho p-brilho--baixo"></span>

            <div class="p-marca">
                <span class="p-marca__simbolo"><img src="{{ asset('images/marca/simbolo.png') }}" alt=""></span>
                <span><span class="p-marca__nome">{{ config('agency.name') }}</span><span class="p-marca__sub">Portal da equipa</span></span>
            </div>

            <div class="p-discurso">
                <h2>A agência, com uma só entrada.</h2>
                <p>Imóveis, dúvidas dos clientes, contactos, calendário e alertas — cada módulo no seu sítio, a mesma conta para todos.</p>
                <ul class="p-lista">
                    <li><span class="p-lista__icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v10h13V10"/><path d="M10 20v-6h4v6"/></svg></span><span>Fichas de imóveis publicadas no site em segundos</span></li>
                    <li><span class="p-lista__icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v10H8l-4 4V6z"/><path d="M8 10h8M8 13h5"/></svg></span><span>Dúvidas dos clientes respondidas a partir do backoffice</span></li>
                    <li><span class="p-lista__icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></span><span>Alertas e valores de mercado atualizados sozinhos</span></li>
                </ul>
            </div>

            <p class="p-seguranca">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Acesso reservado à equipa · verificação em duas etapas
            </p>
        </aside>

        <main class="p-entrada__painel">
            <div class="p-entrada__caixa">
                <div class="p-marca p-entrada__marca-estreita">
                    <span class="p-marca__simbolo"><img src="{{ asset('images/marca/simbolo.png') }}" alt="{{ config('agency.name') }}"></span>
                    <span><span class="p-marca__nome">{{ config('agency.name') }}</span><span class="p-marca__sub">Portal da equipa</span></span>
                </div>

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
                <a href="{{ route('portal') }}" class="p-marca">
                    <span class="p-marca__simbolo"><img src="{{ asset('images/marca/simbolo.png') }}" alt=""></span>
                    <span><span class="p-marca__nome">{{ config('agency.name') }}</span><span class="p-marca__sub">Portal da equipa</span></span>
                </a>
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

        <footer class="p-rodape">{{ config('agency.name') }} · Portal da equipa · <a href="{{ route('home') }}">ver o site público</a></footer>
    </div>
@endif
</body>
</html>
