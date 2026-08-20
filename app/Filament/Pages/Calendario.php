<?php

namespace App\Filament\Pages;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calendário da agenda — vista mensal, semanal e diária, como no antigo CRM.
 *
 * Feito à medida (sem package de calendário): a grelha é calculada aqui e
 * desenhada com os tokens da marca. Filtros por utilizador, tipo de evento e
 * "mostrar concluídos", à semelhança do CRM.
 */
class Calendario extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $title = 'Calendário';

    protected static ?string $navigationLabel = 'Calendário';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.calendario';

    /** Âncora da vista: primeiro dia do período mostrado (Y-m-d). */
    public string $anchor;

    /** month | week | day */
    public string $mode = 'month';

    public ?int $userId = null;

    public ?string $type = null;

    public bool $showDone = false;

    public function mount(): void
    {
        $this->anchor = now()->toDateString();
    }

    /* ------------------------------------------------------------ navegação */

    public function goToday(): void
    {
        $this->anchor = now()->toDateString();
    }

    public function previous(): void
    {
        $this->anchor = $this->shift(-1);
    }

    public function next(): void
    {
        $this->anchor = $this->shift(1);
    }

    private function shift(int $direction): string
    {
        $date = Carbon::parse($this->anchor);

        return match ($this->mode) {
            'day' => $date->addDays($direction)->toDateString(),
            'week' => $date->addWeeks($direction)->toDateString(),
            default => $date->addMonthsNoOverflow($direction)->toDateString(),
        };
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['month', 'week', 'day'], true) ? $mode : 'month';
    }

    /* --------------------------------------------------------------- dados */

    /** Título do período: "agosto 2026", "11–17 ago 2026" ou "quinta, 20 de agosto". */
    public function getPeriodLabelProperty(): string
    {
        $date = Carbon::parse($this->anchor);

        return match ($this->mode) {
            'day' => $date->translatedFormat('l, d \d\e F Y'),
            'week' => $date->copy()->startOfWeek()->translatedFormat('d M').' – '.$date->copy()->endOfWeek()->translatedFormat('d M Y'),
            default => $date->translatedFormat('F Y'),
        };
    }

    /** Primeiro e último dia mostrados (a grelha mensal inclui dias vizinhos). */
    private function range(): array
    {
        $date = Carbon::parse($this->anchor);

        return match ($this->mode) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            default => [$date->copy()->startOfMonth()->startOfWeek(), $date->copy()->endOfMonth()->endOfWeek()],
        };
    }

    /**
     * Eventos do período, agrupados por dia (Y-m-d).
     *
     * @return Collection<string, Collection<int, Event>>
     */
    public function getEventsByDayProperty(): Collection
    {
        [$from, $to] = $this->range();

        return Event::query()
            ->with(['contact', 'property', 'user'])
            ->whereBetween('starts_at', [$from, $to])
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when(! $this->showDone, fn ($q) => $q->where('is_done', false))
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Event $e) => $e->starts_at->toDateString());
    }

    /**
     * Dias do período, em semanas de 7 (vista mensal/semanal) ou um só dia.
     *
     * @return array<int, array<int, Carbon>>
     */
    public function getWeeksProperty(): array
    {
        [$from, $to] = $this->range();

        $days = [];
        for ($day = $from->copy(); $day <= $to; $day->addDay()) {
            $days[] = $day->copy();
        }

        return $this->mode === 'day' ? [$days] : array_chunk($days, 7);
    }

    /** @return array<int, string> */
    public function getUserOptionsProperty(): array
    {
        return User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function getTypeOptionsProperty(): array
    {
        return collect(EventType::cases())->mapWithKeys(fn (EventType $t) => [$t->value => $t->label()])->all();
    }

    public function isCurrentPeriod(Carbon $day): bool
    {
        return $this->mode === 'month'
            ? $day->isSameMonth(Carbon::parse($this->anchor))
            : true;
    }
}
