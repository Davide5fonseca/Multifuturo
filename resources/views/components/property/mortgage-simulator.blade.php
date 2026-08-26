{{--
    Simulador de crédito habitação — na ficha, já com o preço do imóvel.

    Corre inteiramente no browser (Alpine): nenhum valor sai para o servidor,
    não há pedidos externos, e funciona com o site em cache. Fórmula da
    prestação constante (sistema francês):

        prestação = C · i / (1 − (1 + i)^−n)     i = TAN/12, n = anos × 12

    São valores indicativos: a TAEG, o spread e os seguros dependem do banco
    e de cada pessoa. O aviso está sempre visível, por baixo do resultado.
--}}
@props(['price'])

<div
    x-data="{
        price: {{ (int) round($price) }},
        downPct: 10,
        years: 30,
        rate: 3.5,

        get loan() { return Math.max(0, this.price * (1 - this.downPct / 100)); },
        get months() { return this.years * 12; },
        get payment() {
            const i = this.rate / 100 / 12;
            if (this.loan <= 0 || this.months <= 0) return 0;
            if (i === 0) return this.loan / this.months;
            return this.loan * i / (1 - Math.pow(1 + i, -this.months));
        },
        get totalInterest() { return Math.max(0, this.payment * this.months - this.loan); },

        eur(v) {
            return new Intl.NumberFormat('{{ app()->getLocale() === 'en' ? 'en-IE' : 'pt-PT' }}', {
                style: 'currency', currency: 'EUR', maximumFractionDigits: 0,
                // O pt-PT do browser não agrupa milhares abaixo de 10 000 ("5456 €");
                // o resto do site escreve "5 456 €" — força-se o agrupamento.
                useGrouping: 'always',
            }).format(Math.round(v));
        },
    }"
    class="rounded-2xl border border-sand-200 bg-sand-100 p-6 sm:p-8"
    data-simulator
    data-price="{{ (int) round($price) }}"
>
    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_260px]">
        {{-- Entradas --}}
        <div class="grid gap-6">
            <div>
                <div class="flex items-baseline justify-between gap-4">
                    <label for="sim-down" class="label">{{ __('ui.simulator.down_payment') }}</label>
                    <span class="text-sm tabular-nums text-ink" x-text="downPct + '% · ' + eur(price * downPct / 100)"></span>
                </div>
                <input id="sim-down" type="range" min="0" max="60" step="5" x-model.number="downPct"
                       class="mt-3 w-full accent-olive-600" aria-describedby="sim-note">
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-4">
                    <label for="sim-years" class="label">{{ __('ui.simulator.term') }}</label>
                    <span class="text-sm tabular-nums text-ink" x-text="years + ' {{ __('ui.simulator.years') }}'"></span>
                </div>
                <input id="sim-years" type="range" min="5" max="40" step="1" x-model.number="years"
                       class="mt-3 w-full accent-olive-600" aria-describedby="sim-note">
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-4">
                    <label for="sim-rate" class="label">{{ __('ui.simulator.rate') }}</label>
                    <span class="text-sm tabular-nums text-ink" x-text="rate.toFixed(2).replace('.', ',') + ' %'"></span>
                </div>
                <input id="sim-rate" type="range" min="0.5" max="8" step="0.05" x-model.number="rate"
                       class="mt-3 w-full accent-olive-600" aria-describedby="sim-note">
            </div>
        </div>

        {{-- Resultado --}}
        <div class="flex flex-col justify-between rounded-xl bg-sand-50 p-6 ring-1 ring-sand-200" aria-live="polite">
            <div>
                <p class="label">{{ __('ui.simulator.monthly') }}</p>
                <p class="price mt-2 text-4xl" x-text="eur(payment)"></p>
            </div>
            <dl class="mt-6 grid gap-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">{{ __('ui.simulator.loan') }}</dt>
                    <dd class="tabular-nums" x-text="eur(loan)"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">{{ __('ui.simulator.interest') }}</dt>
                    <dd class="tabular-nums" x-text="eur(totalInterest)"></dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-sand-200 pt-2 font-medium">
                    <dt>{{ __('ui.simulator.total') }}</dt>
                    <dd class="tabular-nums" x-text="eur(payment * months)"></dd>
                </div>
            </dl>
        </div>
    </div>

    <p id="sim-note" class="mt-6 text-xs leading-relaxed text-ink-muted">{{ __('ui.simulator.note') }}</p>
</div>
