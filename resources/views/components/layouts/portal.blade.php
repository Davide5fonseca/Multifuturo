{{--
    Layout do portal (entrada única e página de escolha). Fora do site
    público: sem navegação de visitante, sem indexação, com a marca e os
    tokens de desenho do site (Tailwind, app.css).

    Props: title. Slot: conteúdo.
--}}
@props(['title' => 'Portal'])
<!DOCTYPE html>
<html lang="pt-PT" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('agency.name') }}</title>
    <link rel="icon" href="{{ asset('images/marca/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/marca/favicon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-sand-50 text-ink">
    <header class="border-b border-sand-200 bg-sand-50/95">
        <div class="container-site flex h-20 items-center justify-between gap-6">
            <a href="{{ auth()->check() ? route('portal') : route('login') }}" class="flex items-center gap-3" aria-label="{{ config('agency.name') }}">
                <img src="{{ asset('images/marca/logotipo.png') }}" alt="{{ config('agency.name') }}" class="h-10 w-auto">
                <span class="label hidden sm:inline">Portal</span>
            </a>
            @auth
                <div class="flex items-center gap-5 text-sm">
                    <span class="hidden text-ink-muted sm:inline">{{ auth()->user()->name }}</span>
                    {{-- O único "terminar sessão": dentro dos módulos, o botão devolve ao portal. --}}
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="link">Terminar sessão</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="container-site py-12 sm:py-16">
        @if (session('status'))
            <p class="mx-auto mb-8 max-w-md border-l-2 border-olive-600 bg-sand-100 px-4 py-3 text-sm" role="status">{{ session('status') }}</p>
        @endif
        {{ $slot }}
    </main>

    <footer class="container-site pb-10 text-xs text-ink-muted">
        {{ config('agency.name') }} · área reservada à equipa · <a href="{{ route('home') }}" class="link">ver o site</a>
    </footer>
</body>
</html>
