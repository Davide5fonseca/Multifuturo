<?php

namespace App\Filament\Widgets;

use App\Models\PropertyView;
use Filament\Widgets\ChartWidget;

/**
 * "Cliques em propriedades" — visualizações das fichas de imóvel nos últimos
 * 30 dias. Contagem agregada por dia: sem IP, sem cookies, sem rastreio.
 */
class PropertyViewsChart extends ChartWidget
{
    protected ?string $heading = 'Visualizações de imóveis (30 dias)';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '240px';

    protected ?string $pollingInterval = '60s';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $from = now()->subDays(29)->startOfDay();

        $byDay = PropertyView::query()
            ->where('viewed_on', '>=', $from->toDateString())
            ->selectRaw('viewed_on, SUM(views) AS total')
            ->groupBy('viewed_on')
            ->pluck('total', 'viewed_on');

        $labels = [];
        $values = [];

        for ($day = $from->copy(); $day <= now(); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->translatedFormat('d M');
            $values[] = (int) ($byDay[$key] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Visualizações',
                'data' => $values,
                'borderColor' => '#6B7248',          // azeitona da marca
                'backgroundColor' => 'rgba(107, 114, 72, 0.12)',
                'fill' => true,
                'tension' => 0.3,
                'pointRadius' => 2,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
