{{--
    Galeria: capa grande + até 4 miniaturas; lightbox em Alpine com teclado
    (Esc, ← →), foco preso e lazy loading. Sem JS: as imagens continuam visíveis
    e a capa é um link direto para o ficheiro.
--}}
@props(['photos' => [], 'title' => ''])
@php
    $photos = array_values(array_filter($photos, fn ($p) => ! empty($p['url'])));
    $total = count($photos);
    $cover = $photos[0]['url'] ?? null;
    $thumbs = array_slice($photos, 1, 4);
@endphp
<div x-data="{ open: false, i: 0, total: @js($total), urls: @js(array_column($photos, 'url')),
               show(n) { this.i = ((n % this.total) + this.total) % this.total; this.open = true; document.body.style.overflow = 'hidden'; $nextTick(() => $refs.dialog?.focus()); },
               close() { this.open = false; document.body.style.overflow = ''; },
               next() { this.i = (this.i + 1) % this.total }, prev() { this.i = (this.i - 1 + this.total) % this.total } }"
     @keydown.escape.window="open && close()" @keydown.arrow-right.window="open && next()" @keydown.arrow-left.window="open && prev()">

    <div class="grid gap-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <a href="{{ $cover ?: '#' }}" @click.prevent="total && show(0)" class="relative block overflow-hidden rounded-xl bg-sand-200" @if(!$cover) aria-hidden="true" tabindex="-1" @endif>
            <x-property.image :src="$cover" :alt="$title" ratio="4/3" :eager="true" sizes="(min-width: 1024px) 66vw, 100vw" />
            @if ($total > 1)
                <span class="absolute bottom-4 right-4 rounded-full bg-sand-50/95 px-4 py-1.5 text-xs font-medium text-ink">{{ __('ui.property.open_gallery') }} ({{ $total }})</span>
            @endif
        </a>
        @if ($thumbs)
            {{-- Desktop: duas miniaturas empilhadas (2 linhas), à altura da capa.
                 Telemóvel: fila de até 4. As restantes ficam no lightbox. --}}
            <div class="grid grid-cols-4 gap-2 lg:grid-cols-1 lg:grid-rows-2">
                @foreach ($thumbs as $k => $ph)
                    <a href="{{ $ph['url'] }}" @click.prevent="show({{ $k + 1 }})"
                       @class(['relative block overflow-hidden rounded-lg bg-sand-200', 'lg:hidden' => $k >= 2])>
                        <x-property.image :src="$ph['url']" :alt="__('ui.property.photo_n', ['n' => $k + 2, 'total' => $total])" ratio="4/3" sizes="(min-width: 1024px) 16vw, 25vw" />
                        @if ($k === 1 && $total > 3)
                            <span class="absolute inset-0 hidden items-center justify-center bg-ink/45 font-serif text-2xl text-sand-50 lg:flex" aria-hidden="true">+{{ $total - 3 }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Lightbox --}}
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex flex-col bg-ink/95 text-sand-50" role="dialog" aria-modal="true" :aria-label="@js(__('ui.property.gallery'))" x-ref="dialog" tabindex="-1" x-trap.noscroll="open">
        <div class="flex items-center justify-between px-4 py-3 text-sm">
            <span x-text="`${i + 1} / ${total}`"></span>
            <button type="button" @click="close()" class="p-2 hover:text-clay-400" aria-label="{{ __('ui.property.close') }}">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="relative flex flex-1 items-center justify-center px-12">
            <button type="button" @click="prev()" class="absolute left-2 p-3 hover:text-clay-400" aria-label="{{ __('ui.property.prev') }}">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg>
            </button>
            <template x-if="open">
                <img :src="urls[i]" :alt="`${@js($title)} — ${i + 1}`" class="max-h-[85vh] max-w-full object-contain" data-fallback="{{ asset('images/placeholder-property.jpg') }}">
            </template>
            <button type="button" @click="next()" class="absolute right-2 p-3 hover:text-clay-400" aria-label="{{ __('ui.property.next') }}">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</div>
