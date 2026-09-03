<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\Property;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A primeira coisa que se vê ao entrar: quatro números com o estado do negócio,
 * cada um com a sua linha de tendência. Atualizam-se sozinhos de meio em meio
 * minuto — quem deixa o painel aberto vê chegar os pedidos sem carregar em nada.
 */
class DashboardStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            $this->imoveis(),
            $this->porResponder(),
            $this->pedidos(),
            $this->carteira(),
        ];
    }

    /** Imóveis no site, e quantos entraram este mês. */
    private function imoveis(): Stat
    {
        $publicados = Property::query()->active()->count();
        $esteMes = Property::query()->active()->where('published_at', '>=', now()->startOfMonth())->count();

        return Stat::make('Imóveis no site', (string) $publicados)
            ->description($esteMes > 0 ? "+{$esteMes} este mês" : 'Sem entradas este mês')
            ->descriptionIcon($esteMes > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus-small')
            ->color($esteMes > 0 ? 'success' : 'gray')
            ->chart($this->porMes(Property::query()->active(), 'published_at'));
    }

    /**
     * Pedidos ainda sem resposta. É o número que interessa de manhã: fica
     * vermelho enquanto houver alguém à espera.
     */
    private function porResponder(): Stat
    {
        $abertos = Lead::query()->whereNull('replied_at')->count();
        $antigo = Lead::query()->whereNull('replied_at')->oldest()->value('created_at');

        return Stat::make('Pedidos por responder', (string) $abertos)
            ->description($abertos === 0
                ? 'Tudo respondido'
                : 'O mais antigo: '.Carbon::parse($antigo)->diffForHumans())
            ->descriptionIcon($abertos === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle')
            ->color($abertos === 0 ? 'success' : 'danger')
            ->url(route('filament.admin.resources.leads.index'));
    }

    /** Pedidos dos últimos 30 dias, com a linha dos últimos 14. */
    private function pedidos(): Stat
    {
        $ultimos30 = Lead::query()->where('created_at', '>=', now()->subDays(30))->count();
        $anteriores30 = Lead::query()
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        $variacao = $anteriores30 === 0
            ? ($ultimos30 > 0 ? 'Primeiros 30 dias com pedidos' : 'Sem pedidos no período')
            : sprintf('%+d%% face aos 30 dias anteriores', round(($ultimos30 - $anteriores30) / $anteriores30 * 100));

        return Stat::make('Pedidos (30 dias)', (string) $ultimos30)
            ->description($variacao)
            ->descriptionIcon($ultimos30 >= $anteriores30 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($ultimos30 >= $anteriores30 ? 'success' : 'warning')
            ->chart($this->porDia(Lead::query(), 'created_at', 14));
    }

    /** O que a carteira vale, à venda e com preço público. */
    private function carteira(): Stat
    {
        $venda = Property::query()->active()->forSale()->where('price_visible', true);
        $total = (int) (clone $venda)->sum('price');
        $quantos = (clone $venda)->count();

        return Stat::make('Carteira à venda', Format::price($total, 'EUR'))
            ->description($quantos === 1 ? '1 imóvel com preço público' : "{$quantos} imóveis com preço público")
            ->descriptionIcon('heroicon-m-home-modern')
            ->color('primary');
    }

    /**
     * Contagem por mês dos últimos seis, para a linha de tendência.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, int>
     */
    private function porMes($query, string $coluna): array
    {
        $desde = now()->startOfMonth()->subMonths(5);

        return $this->serie($this->contar($query, $coluna, 'YYYY-MM', $desde), 6, fn (int $i) => $desde->copy()->addMonths($i)->format('Y-m'));
    }

    /**
     * Contagem por dia dos últimos N, para a linha de tendência.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, int>
     */
    private function porDia($query, string $coluna, int $dias): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();

        return $this->serie($this->contar($query, $coluna, 'YYYY-MM-DD', $desde), $dias, fn (int $i) => $desde->copy()->addDays($i)->format('Y-m-d'));
    }

    /**
     * Contagem agrupada por período, numa só consulta.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, int>
     */
    private function contar($query, string $coluna, string $formato, Carbon $desde): array
    {
        // A coluna e o formato são escritos aqui no código, nunca vêm de fora.
        return $query->clone()
            ->where($coluna, '>=', $desde)
            ->selectRaw("to_char({$coluna}, '{$formato}') as periodo, count(*) as total")
            ->groupBy('periodo')
            ->pluck('total', 'periodo')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Preenche os períodos sem nada com zero: um gráfico com buracos mente
     * sobre a forma da curva.
     *
     * @param  array<string, int>  $contagens
     * @return array<int, int>
     */
    private function serie(array $contagens, int $periodos, callable $chave): array
    {
        $serie = [];

        for ($i = 0; $i < $periodos; $i++) {
            $serie[] = (int) ($contagens[$chave($i)] ?? 0);
        }

        return $serie;
    }
}
