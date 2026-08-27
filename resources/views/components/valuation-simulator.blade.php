{{--
    Simulador "Quanto vale a minha casa?" — estimativa imediata.

    Os €/m² por concelho e tipo vêm de App\Support\Valuation (valores de
    referência do backoffice ou, na falta deles, mediana das nossas vendas
    publicadas). A tabela inteira vem embutida na página — é pequena — e a
    conta faz-se no browser: nada sai para o servidor até a pessoa carregar
    em "Pedir avaliação", e aí o formulário ao lado recebe os mesmos dados
    e a estimativa que ela viu.

        valor = €/m² × área × fator do estado, ±10 %, arredondado ao milhar
--}}
@props(['table'])

@php
    $typeLabels = collect(\App\Support\Valuation::TYPES)->mapWithKeys(fn ($t) => [$t => __('ui.valuation.type_'.$t)])->all();
    $conditionLabels = collect(array_keys(\App\Support\Valuation::CONDITIONS))->mapWithKeys(fn ($c) => [$c => __('ui.valuation.condition_'.$c)])->all();
    $intl = app()->getLocale() === 'en' ? 'en-IE' : 'pt-PT';
@endphp

@if (empty($table))
    <p class="rounded-xl border border-sand-200 bg-sand-100 px-5 py-4 text-sm text-ink-muted">{{ __('ui.valuation.unavailable') }}</p>
@else
<div
    x-data="{
        table: @js($table),
        types: @js($typeLabels),
        conditions: @js($conditionLabels),
        factors: @js(\App\Support\Valuation::CONDITIONS),
        margin: {{ \App\Support\Valuation::MARGIN }},
        city: '', type: 'apartment', area: null, condition: 'good',

        get cityTypes() { return this.table[this.city] ?? {}; },
        get base() { return this.cityTypes[this.type] ?? null; },
        get ready() { return this.base !== null && this.area > 0; },
        get mid() { return this.ready ? this.base.ppm2 * this.area * this.factors[this.condition] : 0; },
        get min() { return this.thousands(this.mid * (1 - this.margin)); },
        get max() { return this.thousands(this.mid * (1 + this.margin)); },
        get range() { return this.eur(this.min) + ' – ' + this.eur(this.max); },
        get basis() {
            if (!this.base) return '';
            const text = this.base.source === 'portfolio' ? @js(__('ui.valuation.basis_portfolio')) : @js(__('ui.valuation.basis_reference'));
            return text.replace(':n', this.base.n).replace(':city', this.city);
        },

        thousands(v) { return Math.round(v / 1000) * 1000; },
        eur(v) { return new Intl.NumberFormat('{{ $intl }}', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0, useGrouping: 'always' }).format(v); },
        int(v) { return new Intl.NumberFormat('{{ $intl }}', { maximumFractionDigits: 0, useGrouping: 'always' }).format(v); },
        // Ao mudar de concelho, um tipo sem valor lá salta para o primeiro que tenha.
        syncType() { if (this.city in this.table && !this.cityTypes[this.type]) this.type = Object.keys(this.cityTypes)[0]; },
        request() {
            window.dispatchEvent(new CustomEvent('valuation-estimate', { detail: {
                city: this.city, type: this.types[this.type], area: this.area,
                condition: this.conditions[this.condition], estimate: this.range,
                message: @js(__('ui.valuation.message')).replace(':estimate', this.range),
            } }));
            document.getElementById('lead-valuation')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
    }"
    class="rounded-2xl border border-sand-200 bg-sand-100 p-6 sm:p-8"
    data-valuation
>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="val-city" class="label">{{ __('ui.valuation.city') }}</label>
            <select id="val-city" x-model="city" @change="syncType()" class="field mt-2">
                <option value="" disabled>{{ __('ui.valuation.pick_city') }}</option>
                @foreach (array_keys($table) as $city)
                    <option value="{{ $city }}">{{ $city }}</option>
                @endforeach
                <option value="__other">{{ __('ui.valuation.other_city') }}</option>
            </select>
        </div>
        <div>
            <label for="val-type" class="label">{{ __('ui.valuation.type') }}</label>
            <select id="val-type" x-model="type" class="field mt-2">
                @foreach ($typeLabels as $key => $label)
                    <option value="{{ $key }}" :disabled="city in table && !cityTypes['{{ $key }}']">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="val-area" class="label">{{ __('ui.valuation.area') }}</label>
            <input id="val-area" type="number" min="10" max="100000" step="1" inputmode="numeric" x-model.number="area" class="field mt-2" aria-describedby="val-area-hint">
            <p id="val-area-hint" class="mt-1 text-xs text-ink-muted">{{ __('ui.valuation.area_hint') }}</p>
        </div>
        <div>
            <label for="val-condition" class="label">{{ __('ui.valuation.condition') }}</label>
            <select id="val-condition" x-model="condition" class="field mt-2">
                @foreach ($conditionLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-6" aria-live="polite">
        <template x-if="ready">
            <div class="rounded-xl bg-sand-50 p-6 ring-1 ring-sand-200">
                <p class="label">{{ __('ui.valuation.result') }}</p>
                <p class="price mt-2 text-3xl sm:text-4xl" x-text="range"></p>
                <p class="mt-2 text-sm text-ink-muted"><span x-text="'≈ ' + int(base.ppm2) + ' €/m²'"></span> · <span x-text="basis"></span></p>
                <button type="button" class="btn-primary mt-5" @click="request()">{{ __('ui.valuation.request') }}</button>
            </div>
        </template>
        <template x-if="city !== '' && !base">
            <p class="rounded-xl bg-sand-50 px-5 py-4 text-sm ring-1 ring-sand-200">{{ __('ui.valuation.no_data') }}</p>
        </template>
    </div>

    <p class="mt-5 text-xs leading-relaxed text-ink-muted">{{ __('ui.valuation.note') }}</p>
</div>
@endif
