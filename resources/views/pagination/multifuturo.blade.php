{{-- Paginação com os tokens da marca. Usada nas listagens (Livewire) e nas zonas. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-4 border-t border-sand-200 pt-6 text-sm">
        <div>
            @if ($paginator->onFirstPage())
                <span class="text-ink-muted" aria-disabled="true">{!! __('pagination.previous') !!}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="link" @isset($this) wire:click.prevent="previousPage('{{ $paginator->getPageName() }}')" @endisset>{!! __('pagination.previous') !!}</a>
            @endif
        </div>

        <ul class="hidden items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="px-2 text-ink-muted" aria-disabled="true">{{ $element }}</li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="grid h-9 w-9 place-items-center rounded-md bg-olive-600 text-sand-50" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="grid h-9 w-9 place-items-center rounded-md hover:bg-sand-100" @isset($this) wire:click.prevent="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" @endisset aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        <div>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="link" @isset($this) wire:click.prevent="nextPage('{{ $paginator->getPageName() }}')" @endisset>{!! __('pagination.next') !!}</a>
            @else
                <span class="text-ink-muted" aria-disabled="true">{!! __('pagination.next') !!}</span>
            @endif
        </div>
    </nav>
@endif
