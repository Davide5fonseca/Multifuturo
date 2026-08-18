{{--
    Página de documento legal/institucional: secções (título + parágrafos) vindas de
    lang/pt/legal.php, com os dados da agência substituídos. Índice lateral em desktop.
--}}
@php
    $doc = __("legal.{$key}");
    $r = $replacements;
    $t = fn (string $s) => trans_replace($s, $r);
@endphp
<x-layouts.app :title="$doc['title']" :description="$t($doc['lead'])" :canonical="url()->current()">
    <article class="container-site pt-16 pb-24">
        <header class="max-w-3xl">
            <p class="label">{{ config('agency.name') }}</p>
            <h1 class="mt-3 text-4xl sm:text-5xl">{{ $doc['title'] }}</h1>
            <p class="mt-5 text-lg text-ink-muted">{{ $t($doc['lead']) }}</p>
            @isset($doc['updated'])
                <p class="mt-2 text-xs text-ink-muted">{{ $t($doc['updated']) }}</p>
            @endisset
        </header>

        <div class="mt-12 grid gap-12 lg:grid-cols-[240px_minmax(0,1fr)]">
            <nav class="hidden lg:block lg:sticky lg:top-8 lg:self-start" aria-label="Secções">
                <ol class="space-y-2 border-l border-sand-200 pl-4 text-sm text-ink-muted">
                    @foreach ($doc['sections'] as $i => $section)
                        <li><a href="#s{{ $i + 1 }}" class="hover:text-olive-700">{{ $section['title'] }}</a></li>
                    @endforeach
                </ol>
            </nav>

            <div class="max-w-2xl">
                @foreach ($doc['sections'] as $i => $section)
                    <section id="s{{ $i + 1 }}" class="mt-10 first:mt-0 scroll-mt-8">
                        <h2 class="font-sans text-lg font-medium">{{ $section['title'] }}</h2>
                        <div class="mt-3 space-y-3 text-ink/90 leading-relaxed">
                            @foreach ($section['paragraphs'] as $paragraph)
                                <p>{{ $t($paragraph) }}</p>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </article>
</x-layouts.app>
