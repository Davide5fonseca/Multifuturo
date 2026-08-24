{{--
    Calendário da agenda (vista mensal / semanal / diária).
    Grelha calculada em App\Filament\Pages\Calendario. Tudo num só cartão, com
    os filtros na barra do topo e a legenda no rodapé, para o mês caber no ecrã
    sem deslocamento. As cores vêm de EventType::hex() como estilo inline —
    não dependem da compilação do Tailwind.
--}}
@php
    $eventsByDay = $this->eventsByDay;
    $today = today();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        {{-- Barra de topo: navegação + filtros + vistas --}}
        <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <div class="flex items-center gap-1.5">
                <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="previous" label="Anterior" />
                <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="next" label="Seguinte" />
                <x-filament::button size="sm" color="gray" wire:click="goToday">Hoje</x-filament::button>
                <h2 class="ms-2 whitespace-nowrap text-base font-semibold capitalize text-gray-950 dark:text-white">{{ $this->periodLabel }}</h2>
            </div>

            <div class="flex flex-1 flex-wrap items-center justify-end gap-x-3 gap-y-2">
                <label class="sr-only" for="cal-user">Utilizador</label>
                <select id="cal-user" wire:model.live="userId"
                        class="fi-input rounded-lg border-gray-300 py-1.5 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Utilizador: todos</option>
                    @foreach ($this->userOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>

                <label class="sr-only" for="cal-type">Tipo</label>
                <select id="cal-type" wire:model.live="type"
                        class="fi-input rounded-lg border-gray-300 py-1.5 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Tipo: todos</option>
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <label class="flex items-center gap-1.5 whitespace-nowrap text-sm text-gray-950 dark:text-white">
                    <input type="checkbox" wire:model.live="showDone" class="rounded border-gray-300 text-primary-600">
                    Concluídos
                </label>

                <div class="flex items-center gap-1">
                    @foreach (['month' => 'Mês', 'week' => 'Semana', 'day' => 'Dia'] as $value => $label)
                        <x-filament::button
                            size="sm"
                            :color="$mode === $value ? 'primary' : 'gray'"
                            wire:click="setMode('{{ $value }}')">
                            {{ $label }}
                        </x-filament::button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($mode !== 'day')
            <div class="grid grid-cols-7 gap-px rounded-t-lg bg-primary-600 text-center text-xs font-medium uppercase tracking-wide text-white">
                @foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'] as $dow)
                    <div class="py-1.5">{{ $dow }}</div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-px overflow-hidden rounded-b-lg bg-gray-200 dark:bg-gray-700">
            @foreach ($this->weeks as $week)
                <div @class(['grid gap-px', 'grid-cols-7' => $mode !== 'day', 'grid-cols-1' => $mode === 'day'])>
                    @foreach ($week as $day)
                        @php
                            $key = $day->toDateString();
                            $dayEvents = $eventsByDay[$key] ?? collect();
                            $isToday = $day->isSameDay($today);
                        @endphp
                        <div @class([
                            'bg-white p-1.5 dark:bg-gray-900',
                            'min-h-[5.25rem]' => $mode === 'month',
                            'min-h-[24rem]' => $mode === 'day',
                            'min-h-36' => $mode === 'week',
                            'opacity-50' => ! $this->isCurrentPeriod($day),
                            'ring-2 ring-inset ring-primary-500' => $isToday,
                        ])>
                            <div class="mb-0.5 flex items-center justify-between">
                                <span @class([
                                    'text-xs font-semibold',
                                    'text-gray-500 dark:text-gray-400' => ! $isToday,
                                    'inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-white' => $isToday,
                                ])>{{ $day->day }}</span>
                            </div>

                            <div class="space-y-0.5">
                                @foreach ($mode === 'month' ? $dayEvents->take(2) : $dayEvents as $event)
                                    <a href="{{ route('filament.admin.resources.events.edit', $event) }}"
                                       title="{{ $event->type->label() }}: {{ $event->title }}{{ $event->contact ? ' — '.$event->contact->name : '' }}"
                                       style="background-color: {{ $event->type->hex() }}"
                                       @class([
                                           'block truncate rounded px-1.5 py-0.5 text-[11px] leading-tight text-white transition hover:opacity-90',
                                           'line-through opacity-60' => $event->is_done,
                                       ])>
                                        <span class="font-semibold">{{ $event->starts_at->format('H:i') }}</span>
                                        {{ $event->title }}
                                    </a>
                                @endforeach

                                @if ($mode === 'month' && $dayEvents->count() > 2)
                                    <button type="button" wire:click="setMode('day')" x-on:click="$wire.set('anchor', '{{ $key }}')"
                                            class="w-full text-left text-[10px] text-gray-500 hover:underline dark:text-gray-400">
                                        + {{ $dayEvents->count() - 2 }} mais
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Legenda --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-600 dark:text-gray-300">
            @foreach (\App\Enums\EventType::cases() as $tipo)
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background-color: {{ $tipo->hex() }}"></span>
                    {{ $tipo->label() }}
                </span>
            @endforeach
        </div>

        @if ($eventsByDay->isEmpty())
            <p class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400">Sem eventos neste período.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
