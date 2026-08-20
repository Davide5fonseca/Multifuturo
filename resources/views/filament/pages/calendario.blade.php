{{--
    Calendário da agenda (vista mensal / semanal / diária).
    Grelha calculada em App\Filament\Pages\Calendario; cores por tipo de evento.
--}}
@php
    use Illuminate\Support\Carbon;
    $eventsByDay = $this->eventsByDay;
    $today = today();
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">

        {{-- Filtros --}}
        <aside class="space-y-4">
            <x-filament::section>
                <x-slot name="heading">Filtros</x-slot>

                <div class="space-y-4">
                    <div>
                        <label for="cal-user" class="text-sm font-medium text-gray-950 dark:text-white">Utilizador</label>
                        <select id="cal-user" wire:model.live="userId"
                                class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Todos</option>
                            @foreach ($this->userOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="cal-type" class="text-sm font-medium text-gray-950 dark:text-white">Tipo</label>
                        <select id="cal-type" wire:model.live="type"
                                class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Todos</option>
                            @foreach ($this->typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-950 dark:text-white">
                        <input type="checkbox" wire:model.live="showDone" class="rounded border-gray-300 text-primary-600">
                        Mostrar concluídos
                    </label>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Legenda</x-slot>
                <ul class="space-y-2 text-sm">
                    @foreach (\App\Enums\EventType::cases() as $tipo)
                        <li class="flex items-center gap-2">
                            <span @class([
                                'inline-block h-3 w-3 rounded-sm',
                                'bg-amber-400' => $tipo->value === 'call',
                                'bg-emerald-500' => $tipo->value === 'visit',
                                'bg-sky-500' => $tipo->value === 'meeting',
                                'bg-primary-600' => $tipo->value === 'task',
                                'bg-gray-400' => $tipo->value === 'reminder',
                            ])></span>
                            {{ $tipo->label() }}
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        </aside>

        {{-- Calendário --}}
        <x-filament::section>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="previous" label="Anterior" />
                    <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="next" label="Seguinte" />
                    <x-filament::button size="sm" color="gray" wire:click="goToday">Hoje</x-filament::button>
                    <h2 class="ms-2 text-lg font-semibold capitalize text-gray-950 dark:text-white">{{ $this->periodLabel }}</h2>
                </div>

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

            @if ($mode !== 'day')
                <div class="grid grid-cols-7 gap-px rounded-t-lg bg-primary-600 text-center text-xs font-medium uppercase tracking-wide text-white">
                    @foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'] as $dow)
                        <div class="py-2">{{ $dow }}</div>
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
                                'min-h-28 bg-white p-2 dark:bg-gray-900',
                                'min-h-[28rem]' => $mode === 'day',
                                'min-h-40' => $mode === 'week',
                                'opacity-50' => ! $this->isCurrentPeriod($day),
                                'ring-2 ring-inset ring-primary-500' => $isToday,
                            ])>
                                <div class="mb-1 flex items-center justify-between">
                                    <span @class([
                                        'text-xs font-semibold',
                                        'text-gray-500 dark:text-gray-400' => ! $isToday,
                                        'inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-white' => $isToday,
                                    ])>{{ $day->day }}</span>
                                    @if ($dayEvents->count() > 3 && $mode === 'month')
                                        <span class="text-[10px] text-gray-400">{{ $dayEvents->count() }}</span>
                                    @endif
                                </div>

                                <div class="space-y-1">
                                    @foreach ($mode === 'month' ? $dayEvents->take(3) : $dayEvents as $event)
                                        <a href="{{ route('filament.admin.resources.events.edit', $event) }}"
                                           title="{{ $event->title }}{{ $event->contact ? ' — '.$event->contact->name : '' }}"
                                           @class([
                                               'block truncate rounded px-1.5 py-1 text-[11px] leading-tight text-white transition hover:opacity-90',
                                               'bg-amber-500' => $event->type->value === 'call',
                                               'bg-emerald-600' => $event->type->value === 'visit',
                                               'bg-sky-600' => $event->type->value === 'meeting',
                                               'bg-primary-600' => $event->type->value === 'task',
                                               'bg-gray-500' => $event->type->value === 'reminder',
                                               'line-through opacity-60' => $event->is_done,
                                           ])>
                                            <span class="font-semibold">{{ $event->starts_at->format('H:i') }}</span>
                                            {{ $event->title }}
                                        </a>
                                    @endforeach

                                    @if ($mode === 'month' && $dayEvents->count() > 3)
                                        <button type="button" wire:click="setMode('day')" x-on:click="$wire.set('anchor', '{{ $key }}')"
                                                class="w-full text-left text-[10px] text-gray-500 hover:underline dark:text-gray-400">
                                            + {{ $dayEvents->count() - 3 }} mais
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            @if ($eventsByDay->isEmpty())
                <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">Sem eventos neste período.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
